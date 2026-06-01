<?php

namespace App\Entity;

use App\Repository\AccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 36)]
    private string $uuid;

    #[ORM\Column(length: 255)]
    private ?string $ownerName = null;

    #[ORM\Column(length: 3)]
    private ?string $currency = 'EUR';

    #[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 6)]
    private ?string $balance = '0.000000';

    #[ORM\Column]
    private ?bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $updatedAt = null;    

    #[ORM\Column]
    private ?int $version = 0;

    public function __construct(string $ownerName, string $currency = 'EUR')
    {
        $this->uuid      = Uuid::uuid4()->toString();
        $this->ownerName = $ownerName;
        $this->currency  = $currency;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ── Business logic ────────────────────────────────────────
    public function debit(string $amount): void
    {
        if (bccomp(bcsub($this->balance, $amount, 6), '0', 6) < 0) {
            throw new \App\Exception\InsufficientFundsException(
                "Insufficient funds. Balance: {$this->balance}, Requested: {$amount}"
            );
        }
        $this->balance = bcsub($this->balance, $amount, 6);
    }
 
    public function credit(string $amount): void
    {
        $this->balance = bcadd($this->balance, $amount, 6);
    }


    // ── Getters ───────────────────────────────────────────────
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): static
    {
        $this->uuid = $uuid;

        return $this;
    }

    public function getOwnerName(): ?string
    {
        return $this->ownerName;
    }

    public function setOwnerName(string $ownerName): static
    {
        $this->ownerName = $ownerName;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getVersion(): ?int
    {
        return $this->version;
    }

    public function setVersion(int $version): static
    {
        $this->version = $version;

        return $this;
    }
}
