<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/** 站点产品目录绑定及站点独立覆盖。 */
class ErpSiteCatalogProduct extends BaseModel
{
    protected $pk = 'site_product_id';
    protected $name = 'erp_site_catalog_product';
    protected $autoWriteTimestamp = false;
}
