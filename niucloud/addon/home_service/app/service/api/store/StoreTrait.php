<?php


namespace addon\home_service\app\service\api\store;

use addon\home_service\app\model\store\Store;
use core\exception\CommonException;

trait StoreTrait
{
    protected $store_id;
    protected $store_info;
    // 新增：标记是否已查询过，避免重复查库
    private $store_checked = false;

    /**
     * 校验（同一实例中只查询一次）
     * @throws CommonException
     */
    protected function checkStore()
    {
        // 已查询过，直接返回（核心优化）
        if ($this->store_checked) {
            return;
        }
        $this->store_info = (new Store())
            ->field('store_id,site_id,member_id,mobile,is_default,store_name,headimg,service_ratio')
            ->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id],
                ['is_default', '=', 1],
            ])
            ->findOrEmpty()
            ->toArray();
        $this->store_id = $this->store_info['store_id'] ?? 0;
        if (empty($this->store_id)) {
            throw new CommonException('HOME_SERVICE_STORE_NOT_EXIST');
        }
        // 标记为已查询
        $this->store_checked = true;
    }

    // 提供获取方法（自动触发校验，但只查一次）
    public function getTechnicianId()
    {
        $this->checkStore();
        return $this->store_id;
    }

    public function getTechnicianInfo()
    {
        $this->checkStore();
        return $this->technician_info;
    }
}
