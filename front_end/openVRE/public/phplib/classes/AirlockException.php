<?php

namespace OpenVRE;

use RuntimeException;
use Throwable;


/**
 * Error returned by the Airlock API, or failure reaching it. The code holds
 * the HTTP status (0 when Airlock could not be reached).
 */
class AirlockException extends RuntimeException
{
    public function __construct(
        string $message = "Airlock request failed",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }


    /**
     * Build the exception from a failed AirlockClient response.
     */
    public static function fromResponse(array $response): self
    {
        return new self($response['error'] ?? "Airlock request failed", (int) ($response['status'] ?? 0));
    }
}
