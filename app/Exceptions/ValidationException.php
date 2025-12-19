<?php

namespace App\Exceptions;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;

class ValidationException extends SppRekonException
{
    protected array $errors;
    protected ValidatorContract $validator;

    public function __construct(
        ValidatorContract $validator,
        string $message = "",
        string $userMessage = "",
        array $context = []
    ) {
        $this->validator = $validator;
        $this->errors = $validator->errors()->toArray();

        $context = array_merge($context, [
            'validation_errors' => $this->errors
        ]);

        $userMessage = $userMessage ?: $this->generateUserMessage();

        parent::__construct($message ?: 'Validation failed', $userMessage, 0, null, $context, 422);
    }

    protected function getDefaultUserMessage(): string
    {
        return 'Data yang Anda masukkan tidak valid. Periksa kembali input Anda.';
    }

    protected function generateErrorCode(): string
    {
        return 'VAL-' . strtoupper(uniqid());
    }

    private function generateUserMessage(): string
    {
        $firstError = collect($this->errors)->first();

        if ($firstError && is_array($firstError)) {
            return $firstError[0] ?? $this->getDefaultUserMessage();
        }

        return $this->getDefaultUserMessage();
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidator(): ValidatorContract
    {
        return $this->validator;
    }

    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'validation_errors' => $this->errors
        ]);
    }
}