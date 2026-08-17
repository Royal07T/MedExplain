<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the FastAPI service returns an error or malformed payload.
 *
 * Permanent: callers should fail fast, not retry.
 */
final class FastApiResponseException extends RuntimeException {}