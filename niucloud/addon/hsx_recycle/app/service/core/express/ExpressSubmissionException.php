<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express;

use core\exception\CommonException;

/** 区分确定拒绝与外部结果未知；unknown 绝不能自动重下或切换渠道。 */
class ExpressSubmissionException extends CommonException
{
    private string $submissionOutcome;
    private array $submissionIdentifiers = [];

    public function __construct(string $message, string $outcome = 'unknown', ?\Throwable $previous = null, array $identifiers = [])
    {
        $this->submissionOutcome = $outcome === 'rejected' ? 'rejected' : 'unknown';
        // 部分响应仍可能包含后续核实所需的编号；只带编号，不携带凭证或成功状态。
        foreach (['orderNo', 'provider_task_id', 'deliveryId'] as $field) {
            $value = $identifiers[$field] ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                $this->submissionIdentifiers[$field] = trim((string)$value);
            }
        }
        parent::__construct($message, 0, $previous);
    }

    public function outcome(): string { return $this->submissionOutcome; }
    public function isUnknown(): bool { return $this->submissionOutcome === 'unknown'; }
    public function identifiers(): array { return $this->submissionIdentifiers; }
}
