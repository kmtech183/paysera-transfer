<?php
namespace App\Service;
 
use Predis\Client;
 
class IdempotencyService
{
    // Cache result for 24 hours — covers any reasonable retry window
    private const TTL_SECONDS  = 86400;
    private const KEY_PREFIX   = 'idempotency:transfer:';
 
    public function __construct(private readonly Client $redis) {}
 
    // Check if this idempotency key was already used
    // Returns the cached response array, or null if first time
    public function getStoredResponse(string $key): ?array
    {
        $data = $this->redis->get(self::KEY_PREFIX . $key);
 
        if ($data === null) {
            return null; // First time — proceed with transfer
        }
 
        return json_decode($data, true); // Return cached result
    }
 
    // Store the response after successful transfer
    public function storeResponse(string $key, array $response): void
    {
        $this->redis->set(
            self::KEY_PREFIX . $key,
            json_encode($response),
            'EX',
            self::TTL_SECONDS
        );
    }
 
    // Clear a key (useful for testing)
    public function clearKey(string $key): void
    {
        $this->redis->del([self::KEY_PREFIX . $key]);
    }
}
