<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service\notice;
/** Source changed before submission. This is not a provider failure and must never be retried. */
final class ObsoleteNoticeException extends \RuntimeException {}
