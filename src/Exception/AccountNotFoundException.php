<?php
namespace App\Exception;
// Returns HTTP 404 — account UUID doesn't exist
class AccountNotFoundException extends \RuntimeException {}
