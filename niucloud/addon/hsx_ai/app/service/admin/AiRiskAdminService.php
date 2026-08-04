<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\admin;

use addon\hsx_ai\app\model\AiRiskEvent;
use app\model\member\Member;
use core\base\BaseAdminService;

final class AiRiskAdminService extends BaseAdminService
{
    public function getPage(array $where): array
    {
        $memberTable = (new Member())->getTable();
        $query = AiRiskEvent::alias('r')
            ->leftJoin($memberTable . ' m', "r.actor_type = 'member' AND m.member_id = r.actor_id AND m.site_id = r.site_id")
            ->where('r.site_id', '=', $this->site_id);
        if (!empty($where['risk_level'])) $query->where('r.risk_level', '>=', (int)$where['risk_level']);
        if (!empty($where['risk_type'])) $query->where('r.risk_type', '=', (string)$where['risk_type']);
        if (!empty($where['status'])) $query->where('r.status', '=', (string)$where['status']);
        if (!empty($where['keyword'])) {
            $keyword = mb_substr(trim((string)$where['keyword']), 0, 60);
            $query->whereLike('r.risk_type|r.action|r.remark|m.nickname|m.mobile', '%' . $keyword . '%');
        }
        return $query->field([
            'r.id', 'r.conversation_id', 'r.actor_type', 'r.actor_id', 'r.risk_type', 'r.risk_level',
            'r.risk_score', 'r.evidence_json', 'r.action', 'r.status', 'r.frozen_until', 'r.remark',
            'r.create_at', 'r.update_at', 'm.nickname', 'm.mobile', 'm.headimg',
        ])->order('r.id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
    }
}
