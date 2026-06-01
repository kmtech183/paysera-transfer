<?php
namespace App\DTO;
 
use Symfony\Component\Validator\Constraints as Assert;
 
class TransferRequestDTO
{
    public function __construct(
 
        #[Assert\NotBlank(message: 'from_account is required')]
        #[Assert\Uuid(message: 'from_account must be a valid UUID')]
        public readonly string $fromAccount,
 
        #[Assert\NotBlank(message: 'to_account is required')]
        #[Assert\Uuid(message: 'to_account must be a valid UUID')]
        public readonly string $toAccount,
 
        #[Assert\NotBlank(message: 'amount is required')]
        #[Assert\Positive(message: 'amount must be greater than zero')]
        #[Assert\Regex(
            pattern: '/^\d+(\.\d{1,6})?$/',
            message: 'amount must be a valid decimal e.g. 100.00'
        )]
        public readonly string $amount,
 
        #[Assert\NotBlank(message: 'currency is required')]
        #[Assert\Length(exactly: 3, exactMessage: 'currency must be 3 characters')]
        #[Assert\Currency(message: 'currency must be a valid ISO code e.g. EUR')]
        public readonly string $currency,
 
        // Optional — client provides for duplicate-request protection
        public readonly ?string $idempotencyKey = null,
    ) {}
}
