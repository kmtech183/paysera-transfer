<?php
namespace App\Exception;
// Returns HTTP 422 — sender/receiver currencies don't match
class CurrencyMismatchException extends \RuntimeException {}
