<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\yisu;

use addon\hsx_recycle\app\service\admin\express\ExpressProductImportTaskService;
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
     * 获取 Tab + Tree 产品目录。
     */
    public function catalog()
    {
        $provider = (string)$this->request->param('provider', 'yisu');
        return success((new YisuProductService())->getView($provider));
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
        $provider = (string)$this->request->param('provider', 'yisu');
        $service->batchUpdate($products, $provider);

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
        $provider = (string)$this->request->param('provider', 'yisu');
        $service->modifyStatus($productCode, (int)$status, $provider);

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

    /**
     * 创建 Excel 异步导入任务。
     */
    public function import()
    {
        $provider = (string)$this->request->param('provider', 'yisu');
        return success((new ExpressProductImportTaskService())->upload(
            $this->request->file('file'),
            $provider
        ));
    }

    /**
     * 下载快递产品导入模板。
     */
    public function importTemplate()
    {
        try {
            $filePath = (new ExpressProductImportTaskService())->generateTemplate();
            return download($filePath, '快递产品导入模板.xlsx');
        } catch (\Throwable $e) {
            return fail('模板生成失败：' . $e->getMessage());
        }
    }

    public function importTasks()
    {
        return success((new ExpressProductImportTaskService())->getTasks());
    }

    public function importTaskInfo(string $taskId)
    {
        return success((new ExpressProductImportTaskService())->getTask($taskId));
    }

    public function importTaskRetry(string $taskId)
    {
        return success((new ExpressProductImportTaskService())->retry($taskId));
    }
}
