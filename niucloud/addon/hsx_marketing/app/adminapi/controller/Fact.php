<?php
declare(strict_types=1);

namespace addon\hsx_marketing\app\adminapi\controller;

use addon\hsx_marketing\app\service\admin\MarketingFactAdminService;
use core\base\BaseAdminController;

final class Fact extends BaseAdminController
{
    public function lists() { return success((new MarketingFactAdminService())->page($this->request->params([['process_status', ''], ['keyword', ''], ['page', 1], ['limit', 15]]))); }
    public function retry(int $id) { return success('事实已重新处理', (new MarketingFactAdminService())->retry($id)); }
}
