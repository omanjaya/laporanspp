<?php

namespace App\Exceptions;

class RateLimitException extends SppRekonException
{
    protected int $maxAttempts;
    protected int $remainingAttempts;
    protected int $retryAfter;
    protected ?string $key = null;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        int $maxAttempts = 0,
        int $remainingAttempts = 0,
        int $retryAfter = 60,
        ?string $key = null,
        array $context = [],
        int $httpStatusCode = 429
    ) {
        $this->maxAttempts = $maxAttempts;
        $this->remainingAttempts = $remainingAttempts;
        $this->retryAfter = $retryAfter;
        $this->key = $key;

        $context = array_merge($context, [
            'max_attempts' => $maxAttempts,
            'remaining_attempts' => $remainingAttempts,
            'retry_after' => $retryAfter,
            'rate_limit_key' => $key
        ]);

        parent::__construct($message, $userMessage, 0, null, $context, $httpStatusCode);
    }

    protected function getDefaultUserMessage(): string
    {
        $minutes = round($this->retryAfter / 60);
        return "Terlalu banyak permintaan. Silakan coba lagi dalam {$minutes} menit.";
    }

    protected function generateErrorCode(): string
    {
        return 'RATE-' . strtoupper(uniqid());
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function getRemainingAttempts(): int
    {
        return $this->remainingAttempts;
    }

    public function getRetryAfter(): int
    {
        return $this->retryAfter;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function getRetryAfterMinutes(): int
    {
        return (int) ceil($this->retryAfter / 60);
    }
}