<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;
final class ProviderException extends \RuntimeException
{
    private bool $unknown;
    public function __construct(string $message, bool $unknown = true) { parent::__construct($message); $this->unknown = $unknown; }
    public function isUnknown(): bool { return $this->unknown; }
}
