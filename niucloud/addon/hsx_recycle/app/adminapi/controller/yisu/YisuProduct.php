<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\yisu;

use addon\hsx_recycle\app\service\admin\yisu\YisuProductService;
use core\base\BaseAdminController;

/**
 * 易速产品配置控制器
 * Class YisuProduct
 * @package addon\hsx_recycle\app\adminapi\controller\yisu
 */
class YisuProduct extends BaseAdminController
{
    /**
     * 获取产品列表
     * @return \think\Response
     */
    public function lists()
    {
        $service = new YisuProductService();
        $list = $service->getList();

        return success($list);
    }

    /**
     * 批量更新产品配置
     * @return \think\Response
     */
    public function batchUpdate()
    {
        $products = $this->request->param('products', []);

        if (empty($products)) {
            return fail('产品列表不能为空');
        }

        $service = new YisuProductService();
        $service->batchUpdate($products);

        return success([], '更新成功');
    }

    /**
     * 修改产品状态
     * @return \think\Response
     */
    public function modifyStatus()
    {
        $productCode = $this->request->param('product_code', '');
        $status = $this->request->param('status', 0);

        if (empty($productCode)) {
            return fail('产品代码不能为空');
        }

        $service = new YisuProductService();
        $service->modifyStatus($productCode, (int)$status);

        return success([], '修改成功');
    }

    /**
     * 获取启用的产品列表
     * @return \think\Response
     */
    public function enabled()
    {
        $service = new YisuProductService();
        $list = $service->getEnabledProducts();

        return success($list);
    }
}
