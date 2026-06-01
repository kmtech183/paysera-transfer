<?php
namespace App\Exception;
// Returns HTTP 429 — another transfer in progress for same accounts
class LockAcquisitionException extends \RuntimeException {}
