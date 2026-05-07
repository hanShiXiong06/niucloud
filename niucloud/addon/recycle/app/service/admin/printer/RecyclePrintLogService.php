<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\printer;

use addon\recycle\app\model\printer\RecyclePrintLog;
use core\base\BaseAdminService;

/**
 * 回收打印日志服务
 * Class RecyclePrintLogService
 * @package addon\recycle\app\service\admin\printer
 */
class RecyclePrintLogService extends BaseAdminService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new RecyclePrintLog();
    }

    /**
     * 获取分页列表
     * @param array $where
     * @return array
     */
    public function getPage(array $where = []): array
    {
        $searchModel = $this->model
            ->where([['site_id', '=', $this->site_id]])
            ->field('log_id,site_id,scene_key,scene_name,biz_type,biz_id,order_id,device_id,template_id,template_name,printer_id,printer_name,copies,status,message,operator_uid,create_time')
            ->order('create_time desc');

        if (!empty($where['scene_key'])) {
            $searchModel->where('scene_key', '=', $where['scene_key']);
        }
        if ($where['status'] !== '' && $where['status'] !== null) {
            $searchModel->where('status', '=', (int)$where['status']);
        }
        if (!empty($where['keyword'])) {
            $keyword = $this->model->handelSpecialCharacter($where['keyword']);
            $searchModel->where(function ($query) use ($keyword) {
                $query->whereLike('template_name|printer_name|message', '%' . $keyword . '%');
            });
        }

        return $this->pageQuery($searchModel);
    }

    /**
     * 记录打印日志
     * @param array $data
     * @return int
     */
    public function record(array $data): int
    {
        $payload = array_merge([
            'site_id' => $this->site_id,
            'scene_key' => '',
            'scene_name' => '',
            'biz_type' => '',
            'biz_id' => 0,
            'order_id' => 0,
            'device_id' => 0,
            'template_id' => 0,
            'template_name' => '',
            'printer_id' => 0,
            'printer_name' => '',
            'copies' => 1,
            'status' => 0,
            'message' => '',
            'operator_uid' => $this->uid ?? 0,
            'plan_snapshot' => [],
            'request_snapshot' => [],
            'response_snapshot' => [],
        ], $data);

        $log = $this->model->create($payload);
        return (int)($log['log_id'] ?? 0);
    }
}
