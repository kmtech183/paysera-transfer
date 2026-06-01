<?php
namespace App\Exception;
// Returns HTTP 422 — request valid but business rule fails
class InsufficientFundsException extends \RuntimeException {}

