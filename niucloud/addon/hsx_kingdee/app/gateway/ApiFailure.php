<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\gateway;

/** Only controlled local messages cross the HTTP and audit boundaries. */
final class ApiFailure extends \RuntimeException
{
    public function __construct(public string $reason, string $message)
    {
        parent::__construct($message);
    }
}
