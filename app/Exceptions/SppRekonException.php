<?php

namespace App\Exceptions;

use Exception;

class SppRekonException extends Exception
{
    protected array $context = [];
    protected string $userMessage;
    protected string $errorCode;
    protected int $httpStatusCode;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        int $code = 0,
        ?Throwable $previous = null,
        array $context = [],
        int $httpStatusCode = 500
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->userMessage = $userMessage ?: $this->getDefaultUserMessage();
        $this->errorCode = $this->generateErrorCode();
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getUserMessage(): string
    {
        return $this->userMessage;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    protected function getDefaultUserMessage(): string
    {
        return 'Terjadi kesalahan pada sistem. Silakan coba beberapa saat lagi.';
    }

    protected function generateErrorCode(): string
    {
        return 'SPR-' . strtoupper(uniqid());
    }

    public function toArray(): array
    {
        return [
            'error_code' => $this->errorCode,
            'message' => $this->userMessage,
            'context' => $this->context,
            'timestamp' => now()->toISOString(),
            'status_code' => $this->httpStatusCode
        ];
    }
}