<?php
declare(strict_types=1);

namespace core\exception;

/** A channel outcome, not proof of terminal delivery or user acknowledgement. */
final class NoticeDeliveryException extends NoticeException
{
    private string $channelName;
    private string $status;
    private string $providerErrorCode;

    public function __construct(string $channel, string $status, string $providerCode, string $message, ?\Throwable $previous = null)
    {
        $this->channelName = $channel;
        $this->status = in_array($status, ['rejected', 'unknown', 'not_submitted'], true) ? $status : 'unknown';
        $this->providerErrorCode = preg_match('/^-?[0-9]{1,10}$/D', $providerCode) ? $providerCode : '';
        parent::__construct($message, 0, $previous);
    }

    public function channel(): string { return $this->channelName; }
    public function deliveryStatus(): string { return $this->status; }
    public function providerCode(): string { return $this->providerErrorCode; }
}
