<?php

namespace Aqqo\OData\Exceptions;

class QueryException extends \Exception
{
    public function __serialize(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'file' => $this->getFile(),
            'line' => $this->getLine(),
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->message = $data['message'];
        $this->code = $data['code'];
        $this->file = $data['file'];
        $this->line = $data['line'];
    }
}