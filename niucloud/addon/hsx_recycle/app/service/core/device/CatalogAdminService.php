<?php
declare(strict_types=1);
namespace addon\hsx_recycle\app\service\core\device;

use app\dict\sys\AppTypeDict;
use core\base\BaseAdminService;
use core\exception\CommonException;

/** 仅供纯资料服务使用；订单、财务、打印等混合业务不得继承此类。 */
class CatalogAdminService extends BaseAdminService
{
    protected int $catalogBusinessSiteId;
    protected bool $catalogWorker = false;
    private ?DeviceCatalogWriteLock $catalogWriteLock = null;

    public function __construct()
    {
        parent::__construct();
        $this->catalogBusinessSiteId = (int)$this->site_id;
        $this->site_id = (new DeviceCatalogPolicy())->ownerSiteId($this->catalogBusinessSiteId);
    }

    protected function assertCatalogWritable(): void
    {
        $this->catalogWriteLock ??= new DeviceCatalogWriteLock();
        $this->catalogWriteLock->acquire();
        if ($this->catalogBusinessSiteId === 0 && !$this->catalogWorker && $this->app_type !== AppTypeDict::ADMIN) {
            throw new CommonException('仅平台管理员可以维护公共资料库');
        }
        (new DeviceCatalogPolicy())->assertLocalWritable($this->catalogBusinessSiteId);
        $this->site_id = (new DeviceCatalogPolicy())->ownerSiteId($this->catalogBusinessSiteId);
    }

    protected function isPublicConsumer(): bool
    {
        return $this->catalogBusinessSiteId > 0 && (int)$this->site_id === 0;
    }
}
