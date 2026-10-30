<?php
declare(strict_types=1);

namespace addon\hsx_lead\app\provider;

/** Only safe, locally-authored messages are allowed across the provider boundary. */
final class ProviderException extends \RuntimeException
{
    public function __construct(private readonly string $reason, string $message)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
