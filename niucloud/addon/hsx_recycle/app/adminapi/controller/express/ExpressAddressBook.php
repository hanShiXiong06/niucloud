<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\adminapi\controller\express;

use addon\hsx_recycle\app\service\admin\express\ExpressAddressBookService;
use core\base\BaseAdminController;

/**
 * 快递常用地址控制器
 */
class ExpressAddressBook extends BaseAdminController
{
    public function lists()
    {
        $data = $this->request->params([
            ['address_type', ''],
            ['keyword', ''],
        ]);

        return success((new ExpressAddressBookService())->getList($data));
    }

    public function save()
    {
        $data = $this->request->params([
            ['id', 0],
            ['address_type', ''],
            ['name', ''],
            ['mobile', ''],
            ['province', ''],
            ['city', ''],
            ['district', ''],
            ['address', ''],
            ['tag', ''],
            ['is_default', 0],
            ['is_top', 0],
            ['sort', 0],
        ]);

        $id = (new ExpressAddressBookService())->saveAddress($data);
        return success(['id' => $id], '保存成功');
    }

    public function del(int $id)
    {
        (new ExpressAddressBookService())->del($id);
        return success([], '删除成功');
    }

    public function setDefault(int $id)
    {
        (new ExpressAddressBookService())->setDefault($id);
        return success([], '设置成功');
    }

    public function setTop(int $id)
    {
        $isTop = (int)$this->request->param('is_top', 1);
        (new ExpressAddressBookService())->setTop($id, $isTop);
        return success([], '设置成功');
    }
}
