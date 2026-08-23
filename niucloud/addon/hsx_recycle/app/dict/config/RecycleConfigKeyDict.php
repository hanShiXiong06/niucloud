<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\dict\config;

/**
 * 回收插件系统配置键
 */
class RecycleConfigKeyDict
{
    const THIRD_PARTY = 'recycle_third_party_config';
    const DEVICE_QUERY = 'recycle_device_query_config';
    const ORDER_SUBMIT = 'recycle_order_submit_config';
    /** 本地设备工具型号别名到本站叶子型号的人工学习映射 */
    const DEVICE_MODEL_ALIAS = 'recycle_device_model_alias_config';
    const EXPRESS_PRODUCT_CATALOG = 'recycle_express_product_catalog';
    const EXPRESS_PRODUCT_IMPORT = 'recycle_express_product_import';
}
