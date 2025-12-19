<?php

namespace App\Exceptions;

class DatabaseException extends SppRekonException
{
    protected string $query;
    protected ?string $table;
    protected string $operation;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        string $operation = "",
        ?string $table = null,
        ?string $query = null,
        array $context = [],
        int $httpStatusCode = 500
    ) {
        $this->operation = $operation;
        $this->table = $table;
        $this->query = $query;

        $context = array_merge($context, [
            'operation' => $operation,
            'table' => $table,
            'query' => $query
        ]);

        parent::__construct($message, $userMessage, 0, null, $context, $httpStatusCode);
    }

    protected function getDefaultUserMessage(): string
    {
        return 'Terjadi kesalahan pada database. Silakan coba beberapa saat lagi atau hubungi administrator.';
    }

    protected function generateErrorCode(): string
    {
        return 'DB-' . strtoupper(uniqid());
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getTable(): ?string
    {
        return $this->table;
    }

    public function getQuery(): ?string
    {
        return $this->query;
    }
}