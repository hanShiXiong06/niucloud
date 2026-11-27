<?php
declare(strict_types=1);

namespace addon\hsx_kingdee\app\support;

/** Remote response interpretation is independent of HTTP success and persistence. */
final class DeliveryPolicy
{
    public static function response(array $body, int $expectedCount = 1): array
    {
        if ($expectedCount < 1) throw new \InvalidArgumentException('必须指定正数的预期单据数量');
        $status = $body['Result']['ResponseStatus'] ?? null;
        if (!is_array($status) || !array_key_exists('IsSuccess', $status)) {
            return ['state' => 'unknown', 'reason' => '响应格式不明，必须查单核实，禁止盲目重发'];
        }
        $successes = $status['SuccessEntitys'] ?? [];
        $errors = $status['Errors'] ?? [];
        if (!is_array($successes) || !is_array($errors)) return ['state' => 'unknown', 'reason' => '返回行结构异常，必须查单核实'];
        if ($errors !== [] && $successes !== []) return ['state' => 'partial', 'reason' => '部分成功；保留成功行，仅处理失败行'];
        if ($status['IsSuccess'] !== true) return ['state' => 'blocked', 'reason' => '金蝶业务校验未通过，待人工核对'];
        if ($errors !== [] || !is_array($successes) || count($successes) !== $expectedCount) {
            return ['state' => 'unknown', 'reason' => '成功数量不一致，必须查单核实'];
        }
        return ['state' => 'accepted', 'reason' => '接口已接受本次操作，仍需按单据阶段确认'];
    }

    public static function transportFailure(bool $isWrite, int $attempts): array
    {
        if ($isWrite) return ['state' => 'unknown', 'retry' => false, 'delay' => 0];
        return ['state' => $attempts >= 5 ? 'blocked' : 'retry', 'retry' => $attempts < 5, 'delay' => min(1800, 60 * 2 ** min(5, max(0, $attempts - 1)))];
    }

    public static function reconcile(array $remoteMatches, string $expectedHash, string $targetStatus): array
    {
        if ($expectedHash === '' || !in_array($targetStatus, ['saved', 'submitted', 'audited'], true)) {
            return ['state' => 'manual', 'reason' => '缺少有效来源摘要或目标状态，禁止自动接续'];
        }
        if (count($remoteMatches) !== 1) return ['state' => 'manual', 'reason' => '未找到唯一原单；查不到也不能证明从未写入'];
        $doc = $remoteMatches[0];
        if (!is_array($doc) || !hash_equals($expectedHash, (string)($doc['source_hash'] ?? ''))) return ['state' => 'manual', 'reason' => '来源或金额摘要不一致'];
        $status = (string)($doc['status'] ?? '');
        if ($status === $targetStatus) return ['state' => 'confirmed', 'next' => 'none'];
        if ($status === 'saved') return ['state' => 'resume', 'next' => 'submit'];
        if ($status === 'submitted' && $targetStatus === 'audited') return ['state' => 'resume', 'next' => 'audit'];
        return ['state' => 'manual', 'reason' => '原单状态不符合预期'];
    }
}
