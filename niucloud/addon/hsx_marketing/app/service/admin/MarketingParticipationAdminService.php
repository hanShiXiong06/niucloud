<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\admin;

use core\base\BaseAdminService;
use core\exception\CommonException;
use think\facade\Db;

/** 营销活动只读追溯。不重算进度、不重放业务、不触发奖励或通知。 */
final class MarketingParticipationAdminService extends BaseAdminService
{
    private const CLAIM_NAMES = ['running' => '进行中', 'completed' => '已达标', 'rewarded' => '奖励流程已处理', 'expired' => '已过期', 'cancelled' => '已取消'];
    private const REWARD_NAMES = ['claimable' => '待领取', 'pending' => '待发放', 'processing' => '发放中', 'success' => '发放成功', 'failed' => '发放失败', 'expired' => '已过期', 'cancelled' => '已取消 / 撤回'];
    private const CLAIM_FIELDS = 'id,claim_no,campaign_id,member_id,member_name,member_mobile,cycle_start_at,cycle_end_at,status,progress_value,target_value,completed_at,rewarded_at,create_at,update_at';

    public function page(int $campaignId, array $filters): array
    {
        $campaign = $this->campaign($campaignId);
        $query = $this->filteredClaims($campaignId, $filters);
        $summary = (clone $query)->fieldRaw("COUNT(*) AS record_count, COUNT(DISTINCT c.member_id) AS member_count, COALESCE(SUM(CASE WHEN c.status IN ('completed','rewarded') THEN 1 ELSE 0 END),0) AS reached_count")->find();
        $attention = clone $query;
        $this->rewardExists($attention, $campaignId, 'attention');
        $summary['attention_count'] = $attention->count();
        $summary = array_map('intval', $summary);
        $page = $query->field($this->claimFields('c'))->order('c.create_at desc,c.id desc')->paginate($this->paging($filters))->toArray();
        $claimIds = array_map('intval', array_column($page['data'], 'id'));
        $rewardGroups = $claimIds ? Db::name('marketing_reward_order')->alias('r')
            ->join('marketing_claim c', 'c.id = r.claim_id AND c.site_id = r.site_id AND c.campaign_id = r.campaign_id AND c.member_id = r.member_id')
            ->where('r.site_id', $this->site_id)->where('r.campaign_id', $campaignId)->whereIn('r.claim_id', $claimIds)
            ->fieldRaw("r.claim_id,r.status,COUNT(*) AS total,SUM(CASE WHEN r.status = 'failed' OR r.failure_code <> '' THEN 1 ELSE 0 END) AS attention_count")
            ->group('r.claim_id,r.status')->select()->toArray() : [];
        $rewardSummary = [];
        foreach ($rewardGroups as $group) {
            $id = (int)$group['claim_id'];
            $rewardSummary[$id]['statuses'][] = ['status' => $group['status'], 'name' => self::REWARD_NAMES[$group['status']] ?? '未知状态', 'count' => (int)$group['total']];
            $rewardSummary[$id]['attention_count'] = ($rewardSummary[$id]['attention_count'] ?? 0) + (int)$group['attention_count'];
        }
        foreach ($page['data'] as &$row) {
            $row = $this->claimView($row);
            $row['rewards'] = $rewardSummary[(int)$row['id']] ?? ['statuses' => [], 'attention_count' => 0];
        }
        unset($row);
        return ['campaign' => $campaign, 'summary' => $summary, 'records' => $page, 'claim_statuses' => self::CLAIM_NAMES, 'reward_statuses' => self::REWARD_NAMES];
    }

    public function detail(int $campaignId, int $claimId, array $params): array
    {
        $campaign = $this->campaign($campaignId);
        $claim = Db::name('marketing_claim')->where('site_id', $this->site_id)->where('campaign_id', $campaignId)->where('id', $claimId)->field(self::CLAIM_FIELDS)->find();
        if (!$claim) throw new CommonException('参与记录不存在或不属于当前活动');

        // 使用处理业务时保存的归属凭据，不能用“同会员 + 时间相近”冒充实际计入。
        // JSON 路径中的数字活动 ID 必须加引号；不依据当前规则反推历史记录。
        $path = '$."_marketing_applied_claims"."' . $campaignId . '"';
        $facts = Db::name('marketing_fact')->alias('f')->where('f.site_id', $this->site_id)->where('f.member_id', (int)$claim['member_id'])
            ->where('f.process_status', 'success')
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(CASE WHEN JSON_VALID(f.payload_json) THEN f.payload_json ELSE '{}' END, :claim_path)) = :claim_value", ['claim_path' => $path, 'claim_value' => (string)$claimId])
            ->field('f.id,f.event_id,f.business_no,f.business_type,f.business_id,f.fact_type,f.quantity,f.occurred_at,f.processed_at,f.payload_json')
            ->order('f.occurred_at desc,f.id desc')->paginate($this->paging($params, 'fact_'))->toArray();
        foreach ($facts['data'] as &$fact) {
            $payload = json_decode((string)$fact['payload_json'], true) ?: [];
            // 只返回展示字段，不泄露整个事件、第三方请求或内部配置。
            $fact['order_source_name'] = ['customer' => '客户自主下单', 'agent' => '员工代下单'][$payload['order_source'] ?? ''] ?? '未记录';
            $fact['imei'] = is_scalar($payload['imei'] ?? '') ? (string)($payload['imei'] ?? '') : '';
            $fact['amount'] = isset($payload['amount']) && is_numeric($payload['amount']) ? (string)$payload['amount'] : null;
            unset($fact['payload_json']);
        }
        unset($fact);
        $rewards = Db::name('marketing_reward_order')->where('site_id', $this->site_id)->where('campaign_id', $campaignId)
            ->where('claim_id', $claimId)->where('member_id', (int)$claim['member_id'])
            ->field('id,reward_no,reward_type,reward_name,reward_value,reward_quantity,grant_mode,status,create_at,claimed_at,granted_at,claim_expire_at,reward_expire_at,failure_code,failure_reason,update_at')
            ->order('id desc')->paginate($this->paging($params, 'reward_'))->toArray();
        foreach ($rewards['data'] as &$reward) {
            $reward['status_name'] = self::REWARD_NAMES[$reward['status']] ?? '未知状态';
            $reward['needs_attention'] = $reward['status'] === 'failed' || (string)$reward['failure_code'] !== '';
        }
        unset($reward);
        return ['campaign' => $campaign, 'claim' => $this->claimView($claim), 'facts' => $facts, 'rewards' => $rewards,
            'trace_note' => '仅列出明确记录为计入本次任务的业务（含冲减）。旧记录未保存归属凭据时不会推测补齐，明细可能不完整；当前进度以参与记录为准。奖励以逐笔发放、撤回结果为准。'];
    }

    private function campaign(int $id): array
    {
        $row = Db::name('marketing_campaign')->where('site_id', $this->site_id)->where('id', $id)
            ->field('id,title,subtitle,status,target_value,target_unit,start_at,end_at,participation_mode,grant_mode,description')->find();
        if (!$row) throw new CommonException('营销活动不存在');
        return $row;
    }

    private function filteredClaims(int $campaignId, array $filters)
    {
        $query = Db::name('marketing_claim')->alias('c')->where('c.site_id', $this->site_id)->where('c.campaign_id', $campaignId);
        $keyword = trim((string)($filters['keyword'] ?? ''));
        if (mb_strlen($keyword) > 100) throw new CommonException('搜索内容请控制在100字以内');
        if ($keyword !== '') {
            $like = '%' . addcslashes($keyword, '%_\\') . '%';
            $query->where(function ($q) use ($keyword, $like) {
                $q->whereLike('c.member_name|c.member_mobile|c.claim_no', $like);
                if (ctype_digit($keyword)) $q->whereOr('c.member_id', '=', $keyword);
            });
        }
        $status = (string)($filters['status'] ?? '');
        if ($status !== '' && !isset(self::CLAIM_NAMES[$status])) throw new CommonException('参与状态不正确');
        if ($status !== '') $query->where('c.status', $status);
        $rewardStatus = (string)($filters['reward_status'] ?? '');
        if ($rewardStatus !== '' && !isset(self::REWARD_NAMES[$rewardStatus]) && !in_array($rewardStatus, ['none', 'attention'], true)) throw new CommonException('奖励状态不正确');
        $timeType = (string)($filters['time_type'] ?? 'create_at');
        if (!in_array($timeType, ['create_at', 'completed_at', 'granted_at'], true)) throw new CommonException('时间维度不正确');
        $start = $this->dateBoundary((string)($filters['start_date'] ?? ''), false);
        $end = $this->dateBoundary((string)($filters['end_date'] ?? ''), true);
        if ($start && $end && $start >= $end) throw new CommonException('开始日期不能晚于结束日期');
        if ($timeType === 'granted_at' && ($start || $end)) {
            // 日期和奖励状态需命中同一笔奖励，不拼接不同奖励的条件制造假匹配。
            if ($rewardStatus === 'none') $query->whereRaw('1 = 0');
            else $this->rewardExists($query, $campaignId, $rewardStatus, $start, $end);
        } else {
            if ($start || $end) {
                $query->where('c.' . $timeType, '>', 0);
                if ($start) $query->where('c.' . $timeType, '>=', $start);
                if ($end) $query->where('c.' . $timeType, '<', $end);
            }
            if ($rewardStatus !== '') $this->rewardExists($query, $campaignId, $rewardStatus);
        }
        return $query;
    }

    private function rewardExists($query, int $campaignId, string $status, int $start = 0, int $end = 0): void
    {
        $sub = Db::name('marketing_reward_order')->alias('r')->where('r.site_id', $this->site_id)->where('r.campaign_id', $campaignId)
            ->whereColumn('r.claim_id', 'c.id')->whereColumn('r.member_id', 'c.member_id')->field('r.id');
        if ($status === 'attention') $sub->where(function ($q) { $q->where('r.status', 'failed')->whereOr('r.failure_code', '<>', ''); });
        elseif ($status !== '' && $status !== 'none') $sub->where('r.status', $status);
        if ($start || $end) {
            $sub->where('r.granted_at', '>', 0);
            if ($start) $sub->where('r.granted_at', '>=', $start);
            if ($end) $sub->where('r.granted_at', '<', $end);
        }
        if ($status === 'none') $query->whereNotExists($sub->buildSql(false));
        else $query->whereExists($sub->buildSql(false));
    }

    private function dateBoundary(string $value, bool $end): int
    {
        if ($value === '') return 0;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Asia/Shanghai'));
        if (!$date || $date->format('Y-m-d') !== $value) throw new CommonException('日期格式不正确，请重新选择');
        return ($end ? $date->modify('+1 day') : $date)->getTimestamp();
    }

    private function paging(array $filters, string $prefix = ''): array
    {
        return ['page' => max(1, (int)($filters[$prefix . 'page'] ?? 1)), 'list_rows' => max(1, min(50, (int)($filters[$prefix . 'limit'] ?? 15)))];
    }

    private function claimFields(string $alias): string
    {
        return implode(',', array_map(static fn($field) => $alias . '.' . $field, explode(',', self::CLAIM_FIELDS)));
    }

    private function claimView(array $row): array
    {
        $row['status_name'] = self::CLAIM_NAMES[$row['status']] ?? '未知状态';
        return $row;
    }
}
