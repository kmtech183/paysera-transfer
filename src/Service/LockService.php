<?php
namespace App\Service;
 
use App\Exception\LockAcquisitionException;
use Predis\Client;
 
class LockService
{
    // Lock expires after 30s even if process crashes — prevents permanent deadlock
    private const TTL_SECONDS = 30;
    private const LOCK_PREFIX = 'lock:transfer:';
 
    public function __construct(private readonly Client $redis) {}
 
    public function acquireLock(string $fromUuid, string $toUuid): bool
    {
        $key = $this->buildKey($fromUuid, $toUuid);
 
        // SET key '1' EX 30 NX
        // NX = only set if key does NOT exist (atomic check-and-set)
        // EX = expire in 30 seconds automatically
        $result = $this->redis->set($key, '1', 'EX', self::TTL_SECONDS, 'NX');
 
        return $result !== null; // null = key already existed = lock taken
    }
 
    public function releaseLock(string $fromUuid, string $toUuid): void
    {
        $this->redis->del([$this->buildKey($fromUuid, $toUuid)]);
    }
 
    private function buildKey(string $a, string $b): string
    {
        // CRITICAL: Sort UUIDs alphabetically
        // Transfer A->B and B->A both use key 'lock:transfer:aaa:bbb'
        // Without sorting: A->B locks 'aaa:bbb', B->A locks 'bbb:aaa' = DEADLOCK
        $uuids = [$a, $b];
        sort($uuids);
 
        return self::LOCK_PREFIX . implode(':', $uuids);
    }
}
