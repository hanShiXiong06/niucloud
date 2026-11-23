<?php
declare(strict_types=1);
namespace addon\hsx_erp\app\support;

/** Staff confirmation is a different fact from pricing, or a customer accepting an offer. */
final class RecycleInboundActor
{
    public static function fromItems(array $items): ?array
    {
        $actor = null;
        foreach ($items as $item) {
            $spec = $item['spec_json'] ?? null;
            if (is_string($spec)) $spec = json_decode($spec, true);
            $proof = $spec['inbound_operator'] ?? null;
            if (($spec['source_plugin'] ?? '') !== 'hsx_recycle' || !is_array($proof)
                || !is_int($proof['uid'] ?? null) || $proof['uid'] < 1 || !is_int($proof['log_id'] ?? null) || $proof['log_id'] < 1
                || !is_int($proof['device_id'] ?? null) || $proof['device_id'] < 1 || $proof['device_id'] !== ($spec['source_device_id'] ?? null)
                || !is_string($proof['name'] ?? null) || trim($proof['name']) === ''
                || ($actor && $actor['uid'] !== $proof['uid'])) return null;
            $actor = ['uid' => $proof['uid'], 'name' => $proof['name']];
        }
        return $actor;
    }
}
