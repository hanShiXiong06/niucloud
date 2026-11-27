<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\adminapi\controller\device;
use addon\hsx_recycle\app\service\admin\device\DeviceCatalogConfigService;
use addon\hsx_recycle\app\service\admin\device\DeviceCatalogPrintRecoveryService;
use core\base\BaseAdminController;
final class DeviceCatalog extends BaseAdminController
{
    public function info() { return success((new DeviceCatalogConfigService())->info()); }
    public function choose() { return success((new DeviceCatalogConfigService())->choose((int)$this->request->param('enabled', 0) === 1)); }
    public function preview() { return success((new DeviceCatalogConfigService())->preview()); }
    public function promote() { return success((new DeviceCatalogConfigService())->promote((string)$this->request->param('fingerprint', ''))); }
    public function sites() { return success((new DeviceCatalogConfigService())->sites($this->request->params([['keyword', ''], ['page', 1]]))); }
    public function grant() { return success((new DeviceCatalogConfigService())->grant((int)$this->request->param('site_id', 0), (int)$this->request->param('allowed', 0) === 1)); }
    public function previewPrintRecovery() { return success((new DeviceCatalogPrintRecoveryService())->preview()); }
    public function restorePrintRecovery() { return success((new DeviceCatalogPrintRecoveryService())->restore((string)$this->request->param('fingerprint', ''))); }
}
