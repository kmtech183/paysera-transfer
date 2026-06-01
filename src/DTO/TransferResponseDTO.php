<?php
namespace App\DTO;
 
class TransferResponseDTO
{
    public function __construct(
        public readonly string $transactionId,
        public readonly string $status,
        public readonly string $amount,
        public readonly string $currency,
        public readonly string $fromAccount,
        public readonly string $toAccount,
        public readonly string $createdAt,
    ) {}
 
    // Convert to plain array for JSON response
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'status'         => $this->status,
            'amount'         => $this->amount,
            'currency'       => $this->currency,
            'from_account'   => $this->fromAccount,
            'to_account'     => $this->toAccount,
            'created_at'     => $this->createdAt,
        ];
    }
}
