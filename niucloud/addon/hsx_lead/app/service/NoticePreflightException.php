<?php
declare(strict_types=1);
namespace addon\hsx_lead\app\service;

/** A locally detected failure before any channel sender was called. */
final class NoticePreflightException extends \RuntimeException
{
}
