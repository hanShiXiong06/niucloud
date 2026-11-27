<?php
declare(strict_types=1);
namespace addon\hsx_kingdee_jdy\app\support;

/** Only these curated messages may cross the admin API boundary. */
final class Problem extends \RuntimeException
{
    public string $reason;
    public bool $retryable;

    public function __construct(string $reason, string $message, bool $retryable = false)
    {
        parent::__construct($message);
        $this->reason = $reason;
        $this->retryable = $retryable;
    }
}
