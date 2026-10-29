<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\category;

use addon\hsx_recycle\app\service\admin\category\QuoteImageSyncService;
use core\base\BaseAdminController;

class QuoteImageSync extends BaseAdminController
{
    public function info() { return success((new QuoteImageSyncService())->info()); }
    public function status() { return success((new QuoteImageSyncService())->status()); }
    public function save() { return success((new QuoteImageSyncService())->save($this->request->post())); }
    public function discover() { return success((new QuoteImageSyncService())->discover((string)$this->request->post('source_url', ''))); }
    public function run() { return success((new QuoteImageSyncService())->run()); }
    public function cancel() { return success((new QuoteImageSyncService())->cancel((string)$this->request->post('run_id', ''))); }
}
