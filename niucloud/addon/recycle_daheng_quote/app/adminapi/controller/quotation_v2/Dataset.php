<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\adminapi\controller\quotation_v2;

use addon\recycle_daheng_quote\app\service\admin\quotation_v2\DatasetService;
use core\base\BaseAdminController;

/**
 * 报价 2.0 数据集
 */
class Dataset extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['dataset_name', ''],
            ['status', ''],
        ]);
        return success((new DatasetService())->getPage($data));
    }

    public function all()
    {
        $data = $this->request->params([
            ['quotation_id', ''],
            ['dataset_name', ''],
            ['status', ''],
        ]);
        return success((new DatasetService())->getAll($data));
    }

    public function info(int $id)
    {
        return success((new DatasetService())->getInfo($id));
    }

    public function add()
    {
        $data = $this->request->post();
        $id = (new DatasetService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    public function edit(int $id)
    {
        (new DatasetService())->edit($id, $this->request->post());
        return success('EDIT_SUCCESS');
    }

    public function del(int $id)
    {
        (new DatasetService())->del($id);
        return success('DELETE_SUCCESS');
    }

    public function initChaoniuDefaults()
    {
        return success((new DatasetService())->initChaoniuDefaults());
    }
}
