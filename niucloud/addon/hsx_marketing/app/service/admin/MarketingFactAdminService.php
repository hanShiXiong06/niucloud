<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\service\admin;

use addon\hsx_marketing\app\model\MarketingFact;
use addon\hsx_marketing\app\service\core\MarketingEngineService;
use core\base\BaseAdminService;
use core\exception\CommonException;

final class MarketingFactAdminService extends BaseAdminService
{
    public function page(array $where): array
    {
        $query = MarketingFact::where('site_id', '=', $this->site_id);
        if (!empty($where['process_status'])) $query->where('process_status', '=', (string)$where['process_status']);
        if (!empty($where['keyword'])) {
            $keyword = '%' . trim((string)$where['keyword']) . '%';
            $query->where(function ($q) use ($keyword) {
                $q->whereLike('business_no', $keyword)->whereOrLike('event_id', $keyword)->whereOrLike('fact_key', $keyword);
            });
        }
        $page = $query->order('id desc')->paginate([
            'list_rows' => max(1, min(100, (int)($where['limit'] ?? 15))),
            'page' => max(1, (int)($where['page'] ?? 1)),
        ])->toArray();
        $names = ['pending' => '待处理', 'processing' => '处理中', 'success' => '已处理', 'failed' => '处理失败'];
        foreach ($page['data'] as &$row) $row['process_status_name'] = $names[(string)$row['process_status']] ?? (string)$row['process_status'];
        unset($row);
        return $page;
    }

    public function retry(int $id): array
    {
        $fact = MarketingFact::where([['site_id', '=', $this->site_id], ['id', '=', $id]])->findOrEmpty();
        if ($fact->isEmpty()) throw new CommonException('营销事实不存在');
        if ((string)$fact['process_status'] === 'success') throw new CommonException('该事实已经处理成功，无需重试');
        $fact->save(['process_status' => 'pending', 'next_retry_at' => time(), 'update_at' => time()]);
        $result = (new MarketingEngineService())->processFact($id);
        if (empty($result['handled'])) throw new CommonException((string)($result['message'] ?? '事实处理仍然失败'));
        return $result;
    }
}
