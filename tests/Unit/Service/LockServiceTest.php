<?php
namespace App\Tests\Unit\Service;
 
use App\Service\LockService;
use PHPUnit\Framework\TestCase;
use Predis\Client;
 
// ── Custom Redis stub — replaces createMock(Client::class) ────────
// PHPUnit cannot mock Predis magic methods (__call).
// This stub manually implements the exact commands LockService uses.
class FakeRedisClient extends Client
{
    private array $store = [];
    public ?string $lastSetKey   = null;
    public ?string $lastDelKey   = null;
    public bool    $setReturnsOk = true; // control what set() returns

    // Simulates: $redis->set($key, '1', 'EX', 30, 'NX')
    public function set(string $key, mixed $value, mixed ...$options): mixed
    {
        $this->lastSetKey = $key;

        // If NX flag and key already exists → return null (lock taken)
        if (in_array('NX', $options, true) && isset($this->store[$key])) {
            return null;
        }

        if (!$this->setReturnsOk) {
            return null; // Simulate lock already held
        }

        $this->store[$key] = $value;
        return 'OK';
    }

    // Simulates: $redis->del([$key])
    public function del(array $keys): int
    {
        foreach ($keys as $key) {
            $this->lastDelKey = $key;
            unset($this->store[$key]);
        }
        return count($keys);
    }

    public function hasKey(string $key): bool
    {
        return isset($this->store[$key]);
    }
}

// ── Actual Tests ───────────────────────────────────────────────────
class LockServiceTest extends TestCase
{
    private FakeRedisClient $redis;
    private LockService     $lockService;

    protected function setUp(): void
    {
        $this->redis       = new FakeRedisClient();
        $this->lockService = new LockService($this->redis);
    }

    public function testAcquireLockSucceeds(): void
    {
        $this->redis->setReturnsOk = true;

        $result = $this->lockService->acquireLock('uuid-aaa', 'uuid-bbb');

        $this->assertTrue($result);
    }

    public function testAcquireLockFailsWhenAlreadyHeld(): void
    {
        $this->redis->setReturnsOk = false;

        $result = $this->lockService->acquireLock('uuid-aaa', 'uuid-bbb');

        $this->assertFalse($result);
    }

    public function testLockKeyIsSortedToPreventDeadlock(): void
    {
        // A→B transfer
        $this->lockService->acquireLock('zzz-uuid', 'aaa-uuid');
        $keyAtoB = $this->redis->lastSetKey;

        // Reset store so second lock can be acquired
        $this->redis = new FakeRedisClient();
        $this->lockService = new LockService($this->redis);

        // B→A transfer — must produce SAME lock key
        $this->lockService->acquireLock('aaa-uuid', 'zzz-uuid');
        $keyBtoA = $this->redis->lastSetKey;

        $this->assertEquals(
            $keyAtoB,
            $keyBtoA,
            'Lock key must be identical regardless of transfer direction'
        );
    }

    public function testReleaseLockDeletesRedisKey(): void
    {
        // Acquire first so the key exists
        $this->lockService->acquireLock('uuid-aaa', 'uuid-bbb');

        // Release
        $this->lockService->releaseLock('uuid-aaa', 'uuid-bbb');

        // Verify del() was called with the correct key
        $this->assertNotNull($this->redis->lastDelKey);
        $this->assertStringContainsString('lock:transfer:', $this->redis->lastDelKey);
    }

    public function testLockKeyContainsPrefix(): void
    {
        $this->lockService->acquireLock('uuid-111', 'uuid-222');

        $this->assertStringStartsWith(
            'lock:transfer:',
            $this->redis->lastSetKey
        );
    }

    public function testCannotAcquireSameLockTwice(): void
    {
        // First acquire
        $first = $this->lockService->acquireLock('uuid-aaa', 'uuid-bbb');
        $this->assertTrue($first);

        // Second acquire — key now exists in store → NX fails → returns null → false
        $second = $this->lockService->acquireLock('uuid-aaa', 'uuid-bbb');
        $this->assertFalse($second);
    }
}