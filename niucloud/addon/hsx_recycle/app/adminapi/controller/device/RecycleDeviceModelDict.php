<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\device;

use addon\hsx_recycle\app\service\admin\device\RecycleDeviceModelDictService;
use core\base\BaseAdminController;
use think\App;

/**
 * 回收设备分类
 */
class RecycleDeviceModelDict extends BaseAdminController
{
    protected RecycleDeviceModelDictService $service;

    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->service = new RecycleDeviceModelDictService();
    }

    public function lists()
    {
        $data = $this->request->params([
            ['keyword', ''],
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        return success($this->service->getPage($data));
    }

    public function options()
    {
        $data = $this->request->params([
            ['keyword', ''],
        ]);

        return success($this->service->options($data));
    }

    public function children()
    {
        $data = $this->request->params([
            ['pid', 0],
            ['keyword', ''],
            ['status', ''],
            ['limit', 200],
        ]);

        return success($this->service->children($data));
    }

    public function tree()
    {
        return success($this->service->tree());
    }

    public function add()
    {
        $data = $this->request->params([
            ['category_name', ''],
            ['subcategory_name', ''],
            ['brand_name', ''],
            ['series_name', ''],
            ['model_name', ''],
            ['path', []],
            ['status', 1],
            ['sort', 0],
        ]);

        return success($this->service->add($data));
    }

    public function edit(int $id)
    {
        $data = $this->request->params([
            ['category_name', ''],
            ['subcategory_name', ''],
            ['brand_name', ''],
            ['series_name', ''],
            ['model_name', ''],
            ['path', []],
            ['status', 1],
            ['sort', 0],
        ]);

        return success($this->service->edit($id, $data));
    }

    public function del(int $id)
    {
        return success($this->service->delete($id));
    }

    public function quickAdd()
    {
        $data = $this->request->params([
            ['content', ''],
        ]);

        return success($this->service->quickAdd((string)$data['content']));
    }

    public function externalImport()
    {
        $data = $this->request->params([
            ['rows', []],
            ['source', 'recycle_spider'],
        ]);

        return success($this->service->importExternalRows(is_array($data['rows']) ? $data['rows'] : [], (string)$data['source']));
    }

    public function updateSort()
    {
        $data = $this->request->params([
            ['sort_list', []],
        ]);

        return success($this->service->updateSort(is_array($data['sort_list']) ? $data['sort_list'] : []));
    }
}
