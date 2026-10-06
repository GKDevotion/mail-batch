<?php

namespace App\Exceptions;

use RuntimeException;

/** Message is safe to show to the user. */
class SmtpHostNotAllowedException extends RuntimeException
{
}
