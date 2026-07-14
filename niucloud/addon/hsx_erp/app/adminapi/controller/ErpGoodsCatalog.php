<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\adminapi\controller;

use addon\hsx_erp\app\service\admin\ErpGoodsCatalogService;
use addon\hsx_erp\app\service\admin\ErpGoodsCatalogImportTaskService;
use core\base\BaseAdminController;

class ErpGoodsCatalog extends BaseAdminController
{
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1], ['limit', 30], ['keyword', ''], ['category_path', ''],
            ['brand_name', ''], ['series_name', ''], ['is_enabled', ''],
        ]);
        return success((new ErpGoodsCatalogService())->lists($params));
    }

    public function summary()
    {
        return success((new ErpGoodsCatalogService())->summary());
    }

    public function hierarchy()
    {
        $params = $this->request->params([
            ['node_type', 'root'], ['category_path', ''], ['brand_name', ''],
            ['series_name', ''], ['keyword', ''], ['site_product_id', 0], ['limit', 200],
            ['include_filters', 0], ['include_disabled', 0],
        ]);
        return success((new ErpGoodsCatalogService())->hierarchy($params));
    }

    public function saveProduct(int $id)
    {
        $data = $this->request->params([
            ['category_path', ''], ['brand_name', ''], ['series_name', ''],
            ['product_name', ''], ['is_enabled', 1], ['sort', 0],
        ]);
        $service = new ErpGoodsCatalogService();
        $siteProductId = $id > 0 ? ($service->edit($id, $data) ? $id : 0) : $service->add($data);
        return success(['site_product_id' => $siteProductId]);
    }

    public function deleteProduct(int $id)
    {
        return success((new ErpGoodsCatalogService())->delete($id));
    }

    public function sortNode()
    {
        $data = $this->request->params([
            ['node_type', ''], ['category_path', ''], ['brand_name', ''],
            ['series_name', ''], ['site_product_id', 0], ['sort', 0],
        ]);
        return success(['affected_count' => (new ErpGoodsCatalogService())->sortNode($data)]);
    }

    public function export()
    {
        return success((new ErpGoodsCatalogService())->exportRows());
    }

    public function importUpload()
    {
        $sourceKey = (string)$this->request->param('source_key', 'excel_product_catalog');
        return success((new ErpGoodsCatalogImportTaskService())->upload($this->request->file('file'), $sourceKey));
    }

    public function importTasks()
    {
        $params = $this->request->params([['status', ''], ['page', 1], ['limit', 10]]);
        return success((new ErpGoodsCatalogImportTaskService())->getPage($params));
    }

    public function importTaskInfo(int $id)
    {
        return success((new ErpGoodsCatalogImportTaskService())->getInfo($id));
    }

    public function importTaskRetry(int $id)
    {
        return success((new ErpGoodsCatalogImportTaskService())->retry($id));
    }

    public function importTaskDelete(int $id)
    {
        return success((new ErpGoodsCatalogImportTaskService())->delete($id));
    }
}
