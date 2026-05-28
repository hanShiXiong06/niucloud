<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: hsx
// +----------------------------------------------------------------------

namespace addon\hsx_recycle\app\service\admin\category;

use addon\hsx_recycle\app\model\category\RecycleCategory;
use addon\hsx_recycle\app\model\category\RecycleCategoryQuoteHistory;
use core\base\BaseAdminService;

/**
 * 回收分类报价单历史快照服务
 */
class RecycleCategoryQuoteHistoryService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecycleCategoryQuoteHistory();
    }

    /**
     * 写入一条历史快照
     */
    public function snapshot(int $category_id, string $images, string $remark = ''): void
    {
        if ($category_id <= 0 || $images === '') {
            return;
        }
        $this->model->create([
            'site_id'       => $this->site_id,
            'category_id'   => $category_id,
            'images'        => $images,
            'operator_id'   => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'remark'        => $remark,
            'create_time'   => time(),
        ]);
    }

    /**
     * 按日期获取每个分类的报价单快照
     * 不传日期 → 取当前最新；传日期 → 取该日 23:59:59 之前最新一条
     * 返回 [category_id => snapshot]
     */
    public function getSnapshotMapByDate(?string $date = null, array $category_ids = []): array
    {
        if (empty($category_ids)) {
            return [];
        }

        $end_time = $this->resolveEndTime($date);

        $query = $this->model
            ->where('site_id', $this->site_id)
            ->whereIn('category_id', $category_ids);
        if ($end_time !== null) {
            $query->where('create_time', '<=', $end_time);
        }

        $list = $query->order('create_time desc, id desc')->select()->toArray();

        $map = [];
        foreach ($list as $row) {
            $cid = (int)$row['category_id'];
            if (!isset($map[$cid])) {
                $map[$cid] = $row;
            }
        }
        return $map;
    }

    /**
     * 获取某个分类的历史列表（按时间倒序）
     */
    public function getHistoryByCategory(int $category_id, int $limit = 100): array
    {
        if ($category_id <= 0) {
            return [];
        }
        return $this->model
            ->where([
                ['site_id', '=', $this->site_id],
                ['category_id', '=', $category_id],
            ])
            ->order('create_time desc, id desc')
            ->limit($limit)
            ->select()
            ->toArray();
    }

    /**
     * 历史快照分页（支持日期筛选 + 分类筛选）
     */
    public function getPage(array $where = []): array
    {
        $query = $this->model->where('site_id', $this->site_id);

        if (!empty($where['category_id'])) {
            $query->where('category_id', (int)$where['category_id']);
        }
        if (!empty($where['start_time']) && !empty($where['end_time'])) {
            $query->whereBetween('create_time', [(int)$where['start_time'], (int)$where['end_time']]);
        } elseif (!empty($where['date'])) {
            $start = strtotime($where['date'] . ' 00:00:00');
            $end   = strtotime($where['date'] . ' 23:59:59');
            if ($start && $end) {
                $query->whereBetween('create_time', [$start, $end]);
            }
        }

        $query->order('create_time desc, id desc');
        return $this->pageQuery($query);
    }

    /**
     * 把日期转成「截至当天 23:59:59」时间戳；为空返回 null（取最新）
     */
    private function resolveEndTime(?string $date): ?int
    {
        if (!$date) {
            return null;
        }
        $ts = strtotime($date . ' 23:59:59');
        return $ts ?: null;
    }
}
