<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;

/** Proven local non-submission, not a provider rejection or an uncertain send. */
final class NoticeDeliveryDeferredException extends \RuntimeException
{
    private int $until;
    public function __construct(int $until) { $this->until=$until;parent::__construct(NoticeDeliveryWindow::message($until)); }
    public function until(): int { return$this->until; }
}
