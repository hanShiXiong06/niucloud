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

namespace addon\ai_image\app\api\controller\aiimagemodel;

use core\base\BaseApiController;
use addon\ai_image\app\service\api\aiimagemodel\AiimageModelService;


/**
 * 智能体控制器
 * Class AiimageModel
 * @package addon\ai_image\app\adminapi\controller\aiimagemodel
 */
class AiimageModel extends BaseApiController
{
    /**
     * 获取智能体列表
     * @return \think\Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ["name", ""],
            ["status", ""],
            ["is_vip", ""]
        ]);
        return success((new AiimageModelService())->getPage($data));
    }

    /**
     * 智能体详情
     * @param int $id
     * @return \think\Response
     */
    public function info(int $id)
    {
        return success((new AiimageModelService())->getInfo($id));
    }

    /**
     * 添加智能体
     * @return \think\Response
     */
    public function add()
    {
        $data = $this->request->params([
            ["name", ""],
            ["logo", ""],
            ["desc", ""],
            ["prompt", ""],
            ["sort", 0],
            ["demo_image", ""],
            ["status", 0],
            ["point", 0],
            ["is_vip", 0],
            ["is_upload_image", 1],
            ["is_prompt",0],
        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagemodel\AiimageModel.add');
        $id = (new AiimageModelService())->add($data);
        return success('ADD_SUCCESS', ['id' => $id]);
    }

    /**
     * 智能体编辑
     * @param $id  智能体id
     * @return \think\Response
     */
    public function edit(int $id)
    {
        $data = $this->request->params([
            ["name", ""],
            ["logo", ""],
            ["desc", ""],
            ["prompt", ""],
            ["sort", 0],
            ["demo_image", ""],
            ["status", 0],
            ["point", 0],
            ["is_vip", 0],
            ["is_upload_image", 1],
            ["is_prompt",0],

        ]);
        $this->validate($data, 'addon\ai_image\app\validate\aiimagemodel\AiimageModel.edit');
        (new AiimageModelService())->edit($id, $data);
        return success('EDIT_SUCCESS');
    }

    /**
     * 智能体删除
     * @param $id  智能体id
     * @return \think\Response
     */
    public function del(int $id)
    {
        (new AiimageModelService())->del($id);
        return success('DELETE_SUCCESS');
    }


}
