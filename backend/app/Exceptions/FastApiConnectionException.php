<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when the FastAPI service cannot be reached (connection/timeout).
 *
 * Transient: callers should retry.
 */
final class FastApiConnectionException extends RuntimeException {}