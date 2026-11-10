<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The AI coach could not produce a response. The message is safe to show to the user.
 */
class CoachException extends RuntimeException {}
