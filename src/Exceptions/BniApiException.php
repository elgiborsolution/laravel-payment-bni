<?php

namespace ESolution\BNIPayment\Exceptions;

use Exception;

class BniApiException extends Exception
{
    public function __construct(
        string $message,
        public readonly string $bniCode,
        public readonly array $response = [],
        public readonly array $context = []
    ) {
        parent::__construct($message);
    }

    public function context(): array
    {
        return [
            'bni_code' => $this->bniCode,
            'response' => $this->response,
            'context' => $this->context,
        ];
    }
}
