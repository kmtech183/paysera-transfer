<?php
namespace App\Exception;
// Returns HTTP 200 — same idempotency key seen before
class DuplicateTransactionException extends \RuntimeException
{
    public function __construct(private readonly array $cachedResponse)
    {
        parent::__construct('Duplicate transaction detected');
    }
 
    public function getCachedResponse(): array
    {
        return $this->cachedResponse;
    }
}
