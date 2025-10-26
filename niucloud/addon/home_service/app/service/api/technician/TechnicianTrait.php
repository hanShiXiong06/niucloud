<?php


namespace addon\home_service\app\service\api\technician;

use addon\home_service\app\model\technician\Technician;
use core\exception\CommonException;

trait TechnicianTrait
{
    protected $technician_id;
    protected $technician_info;
    // 新增：标记是否已查询过，避免重复查库
    private $technician_checked = false;

    /**
     * 师傅校验（同一实例中只查询一次）
     * @throws CommonException
     */
    protected function checkTechnician()
    {
        // 已查询过，直接返回（核心优化）
        if ($this->technician_checked) {
            return;
        }
        $this->technician_info = (new Technician())
            ->field('id,site_id,member_id,mobile,real_name,status,category_id,city_id,store_id')
            ->where([
                ['member_id', '=', $this->member_id],
                ['site_id', '=', $this->site_id]
            ])
            ->findOrEmpty()
            ->toArray();

        $this->technician_id = $this->technician_info['id'] ?? 0;
        if (empty($this->technician_id)) {
            throw new CommonException('HOME_SERVICE_TECHNICIAN_NOT_EXIST');
        }

        // 标记为已查询
        $this->technician_checked = true;
    }

    // 提供获取方法（自动触发校验，但只查一次）
    public function getTechnicianId()
    {
        $this->checkTechnician();
        return $this->technician_id;
    }

    public function getTechnicianInfo()
    {
        $this->checkTechnician();
        return $this->technician_info;
    }
}
