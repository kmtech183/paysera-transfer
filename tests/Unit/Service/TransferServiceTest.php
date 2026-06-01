<?php

namespace App\Tests\Unit\Service;

use App\Entity\Account;
use App\Exception\InsufficientFundsException;
use PHPUnit\Framework\TestCase;

// ✅ Class name must match filename exactly
class TransferServiceTest extends TestCase
{
    public function testDebitReducesBalance(): void
    {
        $account = new Account('Alice', 'EUR');
        $account->credit('500.000000');
        $account->debit('100.000000');

        $this->assertEquals('400.000000', $account->getBalance());
    }

    public function testDebitThrowsWhenInsufficientFunds(): void
    {
        $this->expectException(InsufficientFundsException::class);

        $account = new Account('Alice', 'EUR');
        $account->credit('50.000000');
        $account->debit('100.000000');
    }

    public function testCreditIncreasesBalance(): void
    {
        $account = new Account('Bob', 'EUR');
        $account->credit('250.000000');

        $this->assertEquals('250.000000', $account->getBalance());
    }

    public function testNoPrecisionLossWithDecimalArithmetic(): void
    {
        // Float would give 0.30000000000000004 — bcmath gives exact 0.300000
        $account = new Account('Carol', 'EUR');
        $account->credit('0.100000');
        $account->credit('0.200000');

        $this->assertEquals('0.300000', $account->getBalance());
    }
}