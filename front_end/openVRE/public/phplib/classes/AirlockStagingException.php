<?php

namespace OpenVRE;

use RuntimeException;
use Throwable;


/**
 * Failure copying files to the storage shared with the Airlock.
 */
class AirlockStagingException extends RuntimeException
{
    public function __construct(
        string $message = "Cannot stage the files for the Airlock",
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
