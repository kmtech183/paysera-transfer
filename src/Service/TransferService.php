<?php
namespace App\Service;
 
use App\Entity\Account;
use App\Entity\Transaction;
use App\Exception\AccountInactiveException;
use App\Exception\AccountNotFoundException;
use App\Exception\CurrencyMismatchException;
use App\Exception\DuplicateTransactionException;
use App\Exception\LockAcquisitionException;
use App\Repository\AccountRepository;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use InvalidArgumentException;
 
class TransferService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AccountRepository      $accountRepo,
        private readonly LockService            $lockService,
        private readonly IdempotencyService     $idempotencyService,
        private readonly LoggerInterface        $logger,
        private readonly TransactionRepository  $transactionRepository
    ) {}
 
    public function transfer(
        string  $fromUuid,
        string  $toUuid,
        string  $amount,
        string  $currency,
        ?string $idempotencyKey = null
    ): array {

        // ── STEP 0: Block Self-Transfer ────────────────
        if ($fromUuid === $toUuid) {
            throw new \InvalidArgumentException('Sender and recipient accounts must be different.');
        }
 
        // ── STEP 1: Idempotency check (before lock — fast path) ──
        if ($idempotencyKey !== null) {
            $cached = $this->idempotencyService->getStoredResponse($idempotencyKey);
            if ($cached !== null) {
                $this->logger->info('Idempotent request — returning cached response', [
                    'idempotency_key' => $idempotencyKey,
                ]);
                throw new DuplicateTransactionException($cached);
            }
        }
 
        // ── STEP 2: Acquire Redis distributed lock ───────────────
        // Must happen BEFORE opening DB transaction
        if (!$this->lockService->acquireLock($fromUuid, $toUuid)) {
            throw new LockAcquisitionException(
                'A transfer between these accounts is already in progress. Retry shortly.'
            );
        }
 
        try {
            // ── STEP 3: DB transaction (atomic — all or nothing) ──
            $response = $this->em->wrapInTransaction(
                function () use ($fromUuid, $toUuid, $amount, $currency, $idempotencyKey) {
 
                    // ── STEP 4: Load with pessimistic write lock ──
                    // Generates: SELECT * FROM accounts WHERE uuid=? FOR UPDATE
                    // Other transactions cannot read/write these rows until commit
                    $from = $this->accountRepo->findByUuidForUpdate($fromUuid);
                    if (!$from) {
                        throw new AccountNotFoundException("Sender not found: {$fromUuid}");
                    }
 
                    $to = $this->accountRepo->findByUuidForUpdate($toUuid);
                    if (!$to) {
                        throw new AccountNotFoundException("Recipient not found: {$toUuid}");
                    }
 
                    // ── STEP 5: Business rule validation ─────────
                    if (!$from->isActive()) {
                        throw new AccountInactiveException('Sender account is inactive');
                    }
                    if (!$to->isActive()) {
                        throw new AccountInactiveException('Recipient account is inactive');
                    }
                    if ($from->getCurrency() !== $currency) {
                        throw new CurrencyMismatchException(
                            "Sender currency is {$from->getCurrency()}, requested {$currency}"
                        );
                    }
                    if ($to->getCurrency() !== $currency) {
                        throw new CurrencyMismatchException(
                            "Recipient currency is {$to->getCurrency()}, requested {$currency}"
                        );
                    }
                    if (bccomp($amount, '0', 6) <= 0) {
                        throw new \InvalidArgumentException('Amount must be positive');
                    }
 
                    // ── STEP 6: Atomic debit + credit ─────────────
                    // Entity throws InsufficientFundsException if balance < amount
                    $from->debit($amount);
                    $to->credit($amount);
 
                    // ── STEP 7: Record transaction ────────────────
                    $transaction = new Transaction(
                        $from, $to, $amount, $currency, $idempotencyKey
                    );
                    $transaction->markCompleted();
                    $this->em->persist($transaction);
 
                    // ── STEP 8: Flush — ONE atomic DB commit ──────
                    // UPDATE accounts (from), UPDATE accounts (to),
                    // INSERT transactions — all in same transaction
                    $this->em->flush();
 
                    $this->logger->info('Transfer completed successfully', [
                        'transaction_uuid' => $transaction->getUuid(),
                        'from'             => $fromUuid,
                        'to'               => $toUuid,
                        'amount'           => $amount,
                        'currency'         => $currency,
                    ]);
 
                    return [
                        'transaction_id' => $transaction->getUuid(),
                        'status'         => $transaction->getStatus(),
                        'amount'         => $transaction->getAmount(),
                        'currency'       => $transaction->getCurrency(),
                        'from_account'   => $from->getUuid(),
                        'to_account'     => $to->getUuid(),
                        'created_at'     => $transaction->getCreatedAt()->format(DATE_ATOM),
                    ];
                }
            ); // wrapInTransaction: auto-commit on success, auto-rollback on exception
 
            // ── STEP 9: Cache result for idempotency ──────────────
            if ($idempotencyKey !== null) {
                $this->idempotencyService->storeResponse($idempotencyKey, $response);
            }
 
            return $response;
 
        } finally {
            // ── STEP 10: ALWAYS release lock ──────────────────────
            // finally runs even if exception thrown — lock is NEVER stuck
            $this->lockService->releaseLock($fromUuid, $toUuid);
        }
    }

    public function getTransactionRepository(): TransactionRepository
    {
        return $this->transactionRepository;
    }
}
