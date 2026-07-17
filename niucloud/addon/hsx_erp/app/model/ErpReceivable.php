<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;
use addon\hsx_erp\app\service\admin\ErpFinanceTaskService;
use think\facade\Log;

class ErpReceivable extends BaseModel
{
    protected $pk = 'id';
    protected $name = 'erp_receivable';
    protected $autoWriteTimestamp = false;

    protected static function onAfterInsert($model): void
    {
        try {
            ErpFinanceTaskService::forSite((int)$model->site_id)->syncReceivable((int)$model->id);
        } catch (\Throwable $e) {
            Log::warning('ERP应收自动分配失败', ['id' => (int)$model->id, 'message' => $e->getMessage()]);
        }
    }
}
