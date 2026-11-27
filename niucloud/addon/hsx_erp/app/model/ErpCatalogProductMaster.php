<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\model;

use core\base\BaseModel;

/** 平台标准产品模板。不得直接按站点查询，站点侧必须通过绑定表访问。 */
class ErpCatalogProductMaster extends BaseModel
{
    protected $pk = 'master_product_id';
    protected $name = 'erp_catalog_product_master';
    protected $autoWriteTimestamp = false;
}
