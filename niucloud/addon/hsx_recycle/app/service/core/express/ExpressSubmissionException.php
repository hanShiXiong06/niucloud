<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use core\exception\CommonException;

/** 区分确定拒绝与外部结果未知；unknown 绝不能自动重下或切换渠道。 */
class ExpressSubmissionException extends CommonException
{
    private string $submissionOutcome;

    public function __construct(string $message, string $outcome = 'unknown', ?\Throwable $previous = null)
    {
        $this->submissionOutcome = $outcome === 'rejected' ? 'rejected' : 'unknown';
        parent::__construct($message, 0, $previous);
    }

    public function outcome(): string { return $this->submissionOutcome; }
    public function isUnknown(): bool { return $this->submissionOutcome === 'unknown'; }
}
