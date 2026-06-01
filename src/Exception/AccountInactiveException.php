<?php
namespace App\Exception;
// Returns HTTP 422 — account exists but is deactivated
class AccountInactiveException extends \RuntimeException {}
