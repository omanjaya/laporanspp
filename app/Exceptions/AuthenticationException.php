<?php

namespace App\Exceptions;

class AuthenticationException extends SppRekonException
{
    protected ?string $apiKey = null;
    protected ?string $userAgent = null;
    protected ?string $ipAddress = null;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        ?string $apiKey = null,
        ?string $userAgent = null,
        ?string $ipAddress = null,
        array $context = [],
        int $httpStatusCode = 401
    ) {
        $this->apiKey = $apiKey;
        $this->userAgent = $userAgent;
        $this->ipAddress = $ipAddress;

        $context = array_merge($context, [
            'api_key_preview' => $apiKey ? substr($apiKey, 0, 8) . '***' : null,
            'user_agent' => $userAgent,
            'ip_address' => $ipAddress
        ]);

        parent::__construct($message, $userMessage, 0, null, $context, $httpStatusCode);
    }

    protected function getDefaultUserMessage(): string
    {
        return 'Autentikasi gagal. Pastikan API key valid dan Anda memiliki izin akses.';
    }

    protected function generateErrorCode(): string
    {
        return 'AUTH-' . strtoupper(uniqid());
    }

    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }
}