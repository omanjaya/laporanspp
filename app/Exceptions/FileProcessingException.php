<?php

namespace App\Exceptions;

class FileProcessingException extends SppRekonException
{
    protected ?string $fileName = null;
    protected ?int $fileSize = null;
    protected ?string $fileType = null;
    protected ?string $filePath = null;

    public function __construct(
        string $message = "",
        string $userMessage = "",
        ?string $fileName = null,
        ?int $fileSize = null,
        ?string $fileType = null,
        ?string $filePath = null,
        array $context = [],
        int $httpStatusCode = 400
    ) {
        $this->fileName = $fileName;
        $this->fileSize = $fileSize;
        $this->fileType = $fileType;
        $this->filePath = $filePath;

        $context = array_merge($context, [
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'file_type' => $fileType,
            'file_path' => $filePath
        ]);

        parent::__construct($message, $userMessage, 0, null, $context, $httpStatusCode);
    }

    protected function getDefaultUserMessage(): string
    {
        if ($this->fileSize && $this->fileSize > 0) {
            $sizeMB = round($this->fileSize / 1024 / 1024, 2);
            return "File '{$this->fileName}' ({$sizeMB}MB) tidak dapat diproses. Periksa format dan ukuran file.";
        }

        return "File tidak dapat diproses. Pastikan format file sesuai dan tidak rusak.";
    }

    protected function generateErrorCode(): string
    {
        return 'FILE-' . strtoupper(uniqid());
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function getFileType(): ?string
    {
        return $this->fileType;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
}