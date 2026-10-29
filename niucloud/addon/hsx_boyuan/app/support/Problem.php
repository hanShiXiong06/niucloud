<?php
declare(strict_types=1);
namespace addon\hsx_boyuan\app\support;
final class Problem extends \RuntimeException
{
    public function __construct(public string $reason, string $message, public string $effect = 'none') { parent::__construct($message); }
}
