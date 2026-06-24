<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\service\core\ErpConfigService;
use core\base\BaseAdminService;

/**
 * ERP / 财务 设置(站点级):ERP 总开关、现结开关。
 */
class ErpSettingService extends BaseAdminService
{
    protected ErpConfigService $config;

    public function __construct()
    {
        parent::__construct();
        $this->config = new ErpConfigService();
    }

    public function get(): array
    {
        return $this->config->get($this->site_id);
    }

    public function save(array $data): bool
    {
        return $this->config->save($this->site_id, $data);
    }
}
