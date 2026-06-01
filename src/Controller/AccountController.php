<?php

namespace App\Controller;

use App\Entity\Account;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[OA\Tag(name: 'Accounts')]
class AccountController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em
    ) {}
 
    #[OA\Post(
        path: '/api/v1/accounts',
        summary: 'Create a new bank account',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['owner_name'],
                properties: [
                    new OA\Property(property:'owner_name',      type:'string',  example:'Alice'),
                    new OA\Property(property:'currency',        type:'string',  example:'EUR'),
                    new OA\Property(property:'initial_balance', type:'number',  example:500.00),
                ]
            )
        ),
        responses: [
            new OA\Response(response:201, description:'Account created successfully'),
            new OA\Response(response:400, description:'Missing required fields'),
        ]
    )]
    #[Route('/api/v1/accounts', name: 'app_account_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
 
        if (empty($data['owner_name'])) {
            return $this->json(['error' => 'owner_name is required'], 400);
        }

        if (empty($data['currency'])) {
            return $this->json(['error' => 'Currency is required'], 400);
        }
        
        $account = new Account(
            $data['owner_name'],
            $data['currency'] ?? 'EUR'
        );
 
        if (!empty($data['initial_balance'])) {
            $account->credit((string) $data['initial_balance']);
        }
 
        $this->em->persist($account);
        $this->em->flush();
 
        return $this->json([
            'uuid'       => $account->getUuid(),
            'owner_name' => $account->getOwnerName(),
            'currency'   => $account->getCurrency(),
            'balance'    => $account->getBalance(),
        ], 201);
    }
 
    #[OA\Get(
        path: '/api/v1/accounts/{uuid}',
        summary: 'Get account details and current balance',
        parameters: [
            new OA\Parameter(name:'uuid', in:'path', required:true,
                schema: new OA\Schema(type:'string'), example:'abc-123-uuid')
        ],
        responses: [
            new OA\Response(response:200, description:'Account details returned'),
            new OA\Response(response:404, description:'Account not found'),
        ]
    )]
    #[Route('/api/v1/accounts/{uuid}', name: 'app_account_show', methods: ['GET'])]
    public function show(string $uuid): JsonResponse
    {
        $account = $this->em->getRepository(Account::class)
            ->findOneBy(['uuid' => $uuid]);
 
        if (!$account) {
            return $this->json(['error' => 'Account not found'], 404);
        }
 
        return $this->json([
            'uuid'       => $account->getUuid(),
            'owner_name' => $account->getOwnerName(),
            'currency'   => $account->getCurrency(),
            'balance'    => $account->getBalance(),
            'is_active'  => $account->isActive(),
        ]);
    }
}
