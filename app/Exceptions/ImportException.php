<?php

namespace App\Exceptions;

class ImportException extends SppRekonException
{
    protected ?int $rowNumber = null;
    protected ?string $column = null;
    protected ?string $fileName = null;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        ?int $rowNumber = null,
        ?string $column = null,
        ?string $fileName = null,
        array $context = [],
        int $httpStatusCode = 400
    ) {
        $this->rowNumber = $rowNumber;
        $this->column = $column;
        $this->fileName = $fileName;

        $context = array_merge($context, [
            'row_number' => $rowNumber,
            'column' => $column,
            'file_name' => $fileName
        ]);

        parent::__construct($message, $userMessage, 0, null, $context, $httpStatusCode);
    }

    protected function getDefaultUserMessage(): string
    {
        if ($this->rowNumber && $this->column) {
            return "Kesalahan pada baris {$this->rowNumber}, kolom '{$this->column}'. Periksa format file Anda.";
        } elseif ($this->rowNumber) {
            return "Kesalahan pada baris {$this->rowNumber}. Periksa data pada baris tersebut.";
        }

        return "Format file tidak valid atau terjadi kesalahan saat memproses data.";
    }

    protected function generateErrorCode(): string
    {
        return 'IMP-' . strtoupper(uniqid());
    }

    public function getRowNumber(): ?int
    {
        return $this->rowNumber;
    }

    public function getColumn(): ?string
    {
        return $this->column;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }
}