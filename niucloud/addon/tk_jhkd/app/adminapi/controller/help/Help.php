<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的多应用管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace addon\tk_jhkd\app\adminapi\controller\help;

use core\base\BaseAdminController;
use addon\tk_jhkd\app\service\admin\help\HelpService;


/**
 * 帮助中心控制器
 * Class Help
 * @package addon\tk_jhkd\app\adminapi\controller\help
 */
class Help extends BaseAdminController
{
    /**
     * 获取帮助中心列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["title", ""]
        ]);
        return success((new HelpService())->getPage($data));
    }

    public function asyncHelp()
    {
        return success('操作成功',(new HelpService())->asyncHelp());
    }

    /**
     * 帮助中心详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new HelpService())->getInfo($id));
    }

    /**
     * 添加帮助中心
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["title", ""],
            ["content", ""],

        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\help\Help.add');
        $id = (new HelpService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 帮助中心编辑
     * @param $id  帮助中心id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ["title", ""],
            ["content", ""],

        ]);
        $this->validate($data, 'addon\tk_jhkd\app\validate\help\Help.edit');
        (new HelpService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 帮助中心删除
     * @param $id  帮助中心id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new HelpService())->del($id);
        return success('DELETE_SUCCESS');
    }


}
