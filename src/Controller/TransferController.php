<?php

namespace App\Controller;


use App\DTO\TransferRequestDTO;
use App\DTO\TransferResponseDTO;
use App\Exception\DuplicateTransactionException;
use App\Repository\TransactionRepository;   
use App\Service\TransferService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

 
#[OA\Tag(name: 'Transfers')]
class TransferController extends AbstractController
{
    public function __construct(
        private readonly TransferService    $transferService,
        private readonly ValidatorInterface $validator,        
    ) {}
 
    #[OA\Post(
        path: '/api/v1/transfers',
        summary: 'Transfer funds between two accounts',
        description: 'Atomic fund transfer with Redis locking and idempotency support.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['from_account','to_account','amount','currency'],
                properties: [
                    new OA\Property(property:'from_account',    type:'string', example:'sender-uuid-here'),
                    new OA\Property(property:'to_account',      type:'string', example:'receiver-uuid-here'),
                    new OA\Property(property:'amount',          type:'string', example:'100.00'),
                    new OA\Property(property:'currency',        type:'string', example:'EUR'),
                    new OA\Property(property:'idempotency_key', type:'string', example:'unique-client-key-001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response:201, description:'Transfer successful'),
            new OA\Response(response:200, description:'Duplicate request — original result returned'),
            new OA\Response(response:400, description:'Validation failed'),
            new OA\Response(response:404, description:'Account not found'),
            new OA\Response(response:422, description:'Insufficient funds or currency mismatch'),
            new OA\Response(response:429, description:'Transfer in progress — retry shortly'),
        ]
    )]
    #[Route('/api/v1/transfers', name: 'app_transfer_create', methods: ['POST'])]
    public function transfer(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
 
        if (!is_array($data)) {
            return $this->json(['error' => 'Invalid JSON body'], 400);
        }
 
        // Build + validate DTO
        $dto = new TransferRequestDTO(
            fromAccount:    $data['from_account']    ?? '',
            toAccount:      $data['to_account']      ?? '',
            amount:         (string)($data['amount'] ?? ''),
            currency:       $data['currency']        ?? '',
            idempotencyKey: $data['idempotency_key'] ?? null,
        );
 
        $violations = $this->validator->validate($dto);
        if (count($violations) > 0) {
            $errors = [];
            foreach ($violations as $v) {
                $errors[] = $v->getPropertyPath().': '.$v->getMessage();
            }
            return $this->json(['errors' => $errors], 400);
        }
 
        try {
            $result = $this->transferService->transfer(
                fromUuid:       $dto->fromAccount,
                toUuid:         $dto->toAccount,
                amount:         $dto->amount,
                currency:       $dto->currency,
                idempotencyKey: $dto->idempotencyKey,
            );
 
            return $this->json($result, 201);
 
        } catch (DuplicateTransactionException $e) {
            // Same idempotency key — return original result with 200
            return $this->json($e->getCachedResponse(), 200);
        }
        // All other exceptions handled by ExceptionListener (Step 11)
    }
 
    #[OA\Get(
        path: '/api/v1/transfers/{uuid}',
        summary: 'Get transaction status by UUID',
        parameters: [
            new OA\Parameter(name:'uuid', in:'path', required:true,
                schema: new OA\Schema(type:'string'))
        ],
        responses: [
            new OA\Response(response:200, description:'Transaction found'),
            new OA\Response(response:404, description:'Transaction not found'),
        ]
    )]
    #[Route('/api/v1/transfers/{uuid}', name: 'app_transfer_show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $repo = $this->transferService->getTransactionRepository();
        $tx   = $repo->findByUuid($uuid);

 
        if (!$tx) {
            return $this->json(['error' => 'Transaction not found'], 404);
        }
 
        return $this->json([
            'transaction_id' => $tx->getUuid(),
            'status'         => $tx->getStatus(),
            'amount'         => $tx->getAmount(),
            'currency'       => $tx->getCurrency(),
            'from_account'   => $tx->getFromAccount()->getUuid(),
            'to_account'     => $tx->getToAccount()->getUuid(),
            'created_at'     => $tx->getCreatedAt()->format(DATE_ATOM),
        ]);
    }
}
