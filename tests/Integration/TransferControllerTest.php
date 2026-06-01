<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TransferControllerTest extends WebTestCase
{
    private function makeClient(): KernelBrowser
    {
        $client = static::createClient();

        // ── Wipe DB tables ─────────────────────────────────────────
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        $conn = $em->getConnection();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        $conn->executeStatement('TRUNCATE TABLE `' . $em->getClassMetadata(\App\Entity\Transaction::class)->getTableName() . '`');
        $conn->executeStatement('TRUNCATE TABLE `' . $em->getClassMetadata(\App\Entity\Account::class)->getTableName() . '`');
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');

        // ── Wipe Redis idempotency keys ────────────────────────────
        // Prevents stale idempotency cache from previous test run
        try {
            $redis = static::getContainer()->get(\Predis\Client::class);
            $keys  = $redis->keys('idempotency:transfer:*');
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } catch (\Throwable $e) {
            // Redis not available — skip flush
        }

        return $client;
    }

    private function createAccount(
        KernelBrowser $client,
        string        $name,
        float         $balance  = 0,
        string        $currency = 'EUR'
    ): string {
        $client->request(
            'POST', '/api/v1/accounts', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'owner_name'      => $name,
                'currency'        => $currency,
                'initial_balance' => $balance,
            ])
        );

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey(
            'uuid',
            $data,
            'Account creation failed: ' . json_encode($data)
        );

        return $data['uuid'];
    }

    // ── Test 1 ─────────────────────────────────────────────────────
    public function testSuccessfulTransferReturns201(): void
    {
        $client = $this->makeClient();

        $from = $this->createAccount($client, 'Alice', 500.00);
        $to   = $this->createAccount($client, 'Bob',   0.00);

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account'    => $from,
                'to_account'      => $to,
                'amount'          => '100.00',
                'currency'        => 'EUR',
                'idempotency_key' => uniqid('test-', true), // ← unique every run
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        $body = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('completed', $body['status']);
        $this->assertArrayHasKey('transaction_id', $body);
    }

    // ── Test 2 ─────────────────────────────────────────────────────
    public function testBalancesUpdateCorrectlyAfterTransfer(): void
    {
        $client = $this->makeClient();

        $from = $this->createAccount($client, 'Alice', 500.00);
        $to   = $this->createAccount($client, 'Bob',   100.00);

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => $from,
                'to_account'   => $to,
                'amount'       => '200.00',
                'currency'     => 'EUR',
            ])
        );

        $this->assertResponseStatusCodeSame(201);

        // Alice: 500 - 200 = 300
        $client->request('GET', '/api/v1/accounts/' . $from);
        $alice = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('300.000000', $alice['balance']);

        // Bob: 100 + 200 = 300
        $client->request('GET', '/api/v1/accounts/' . $to);
        $bob = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('300.000000', $bob['balance']);
    }

    // ── Test 3 ─────────────────────────────────────────────────────
    public function testInsufficientFundsReturns422(): void
    {
        $client = $this->makeClient();

        $from = $this->createAccount($client, 'Broke', 10.00);
        $to   = $this->createAccount($client, 'Rich',  0.00);

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => $from,
                'to_account'   => $to,
                'amount'       => '999.00',
                'currency'     => 'EUR',
            ])
        );

        $this->assertResponseStatusCodeSame(422);
    }

    // ── Test 4 ─────────────────────────────────────────────────────
    public function testUnknownAccountReturns404(): void
    {
        $client = $this->makeClient();

        $real = $this->createAccount($client, 'Alice', 100.00);

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => $real,
                // ← valid UUID v4 format, random, guaranteed not in DB
                'to_account'   => sprintf(
                    '%08x-%04x-4%03x-%04x-%012x',
                    random_int(0, 0xFFFFFFFF),
                    random_int(0, 0xFFFF),
                    random_int(0, 0xFFF),
                    random_int(0x8000, 0xBFFF),
                    random_int(0, 0xFFFFFFFFFFFF)
                ),
                'amount'   => '10.00',
                'currency' => 'EUR',
            ])
        );

        $this->assertResponseStatusCodeSame(404);
    }

    // ── Test 5 ─────────────────────────────────────────────────────
    public function testMissingFieldsReturns400(): void
    {
        $client = $this->makeClient();

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['from_account' => 'some-uuid'])
        );

        $this->assertResponseStatusCodeSame(400);
    }

    // ── Test 6 ─────────────────────────────────────────────────────
    public function testSameIdempotencyKeyReturnsSameResult(): void
    {
        $client = $this->makeClient();

        $from = $this->createAccount($client, 'Alice', 500.00);
        $to   = $this->createAccount($client, 'Bob',   0.00);

        // Unique key per test run — never collides with previous runs
        $idempotencyKey = uniqid('idem-test-', true);

        $payload = json_encode([
            'from_account'    => $from,
            'to_account'      => $to,
            'amount'          => '50.00',
            'currency'        => 'EUR',
            'idempotency_key' => $idempotencyKey,
        ]);

        // First call → must be 201
        $client->request('POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(201);
        $first = json_decode($client->getResponse()->getContent(), true);

        // Second call same key → must be 200
        $client->request('POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'], $payload);
        $this->assertResponseStatusCodeSame(200);
        $second = json_decode($client->getResponse()->getContent(), true);

        // Same transaction UUID both times
        $this->assertEquals($first['transaction_id'], $second['transaction_id']);

        // Balance deducted only ONCE — 500 - 50 = 450
        $client->request('GET', '/api/v1/accounts/' . $from);
        $alice = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('450.000000', $alice['balance']);
    }

    // ── Test 7 ─────────────────────────────────────────────────────
    public function testZeroAmountReturns400(): void
    {
        $client = $this->makeClient();

        $from = $this->createAccount($client, 'Alice', 500.00);
        $to   = $this->createAccount($client, 'Bob',   0.00);

        $client->request(
            'POST', '/api/v1/transfers', [], [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'from_account' => $from,
                'to_account'   => $to,
                'amount'       => '0',
                'currency'     => 'EUR',
            ])
        );

        $this->assertResponseStatusCodeSame(400);
    }
}