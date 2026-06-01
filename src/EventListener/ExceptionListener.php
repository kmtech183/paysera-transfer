<?php
namespace App\EventListener;
 
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
 
class ExceptionListener
{
    public function __construct(private readonly LoggerInterface $logger) {}
 
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
 
        // Let Symfony handle non-API routes normally
        $path = $event->getRequest()->getPathInfo();
        if (!str_starts_with($path, '/api')) {
            return;
        }
 
        // Map exception type to HTTP status code
        $statusCode = match (true) {
            $exception instanceof HttpExceptionInterface         => $exception->getStatusCode(),
            $exception instanceof \App\Exception\AccountNotFoundException,
            $exception instanceof \App\Exception\AccountInactiveException  => 404,
            $exception instanceof \App\Exception\InsufficientFundsException,
            $exception instanceof \App\Exception\CurrencyMismatchException => 422,
            $exception instanceof \App\Exception\LockAcquisitionException  => 429,
            $exception instanceof \App\Exception\DuplicateTransactionException => 200,
            $exception instanceof \InvalidArgumentException                 => 400,
            default                                                          => 500,
        };
 
        // Log 5xx errors — never log 4xx (client errors are expected)
        if ($statusCode >= 500) {
            $this->logger->error('Unhandled exception', [
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => $exception->getTraceAsString(),
            ]);
        }
 
        // For duplicate transactions, return cached response with 200
        if ($exception instanceof \App\Exception\DuplicateTransactionException) {
            $event->setResponse(new JsonResponse($exception->getCachedResponse(), 200));
            return;
        }
 
        // Never expose internal error details to client
        $message = $statusCode >= 500
            ? 'An internal server error occurred'
            : $exception->getMessage();
 
        $event->setResponse(new JsonResponse([
            'error'  => $message,
            'status' => $statusCode,
        ], $statusCode));
    }
}
