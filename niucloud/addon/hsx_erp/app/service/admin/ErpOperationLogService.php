<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ErpOperationLog;
use core\base\BaseAdminService;

class ErpOperationLogService extends BaseAdminService
{
    public static function forSite(int $siteId, int $operatorUid = 0, string $operatorName = '系统补偿'): self
    {
        $service = new self();
        $service->site_id = $siteId;
        $service->uid = $operatorUid;
        $service->username = $operatorName;
        return $service;
    }

    public function record(string $action, string $sourceType, int $sourceId, string $sourceNo = '', string $remark = '', array $extra = []): int
    {
        $now = time();
        $row = ErpOperationLog::create([
            'site_id' => $this->site_id,
            'action' => $action,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'source_no' => $sourceNo,
            'operator_uid' => (int)$this->uid,
            'operator_name' => (string)$this->username,
            'remark' => $remark,
            'extra_json' => !empty($extra) ? json_encode($extra, JSON_UNESCAPED_UNICODE) : '',
            'create_at' => $now,
        ]);
        return (int)$row->id;
    }

}
