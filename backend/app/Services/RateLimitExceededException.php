<?php

namespace App\Services;

use RuntimeException;

/**
 * Exception thrown when an AI rate limit is exceeded.
 */
class RateLimitExceededException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $retryAfter,
        public readonly string $limitType,
        public readonly int $limit,
        public readonly int $used,
    ) {
        parent::__construct($message);
    }
}
