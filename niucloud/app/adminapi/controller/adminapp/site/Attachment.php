<?php
// +----------------------------------------------------------------------
// | Niucloud-admin 企业快速开发的saas管理平台
// +----------------------------------------------------------------------
// | 官方网址：https://www.niucloud.com
// +----------------------------------------------------------------------
// | niucloud团队 版权所有 开源版本可自由商用
// +----------------------------------------------------------------------
// | Author: Niucloud Team
// +----------------------------------------------------------------------

namespace app\adminapi\controller\adminapp\site;

use app\service\admin\sys\AttachmentService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 附件管理(移动端)
 */
class Attachment extends BaseAdminController
{

    /**
     * 获取附件配置
     * @return Response
     */
    public function getConfig()
    {
        return success((new AttachmentService())->getConfig());
    }

    /**
     * 附件分类列表
     * @return Response
     */
    public function categoryLists()
    {
        $data = $this->request->params([
            ['type', ''],
            ['name', ''],
        ]);
        return success((new AttachmentService())->getCategoryList($data));
    }

    /**
     * 附件列表
     * @return Response
     */
    public function lists()
    {
        $data = $this->request->params([
            ['att_type', ''],
            ['cate_id', 0],
            ['real_name', ''],
            ['page', 0],
            ['limit', 0],
        ]);
        return success((new AttachmentService())->getPage($data));
    }

    /**
     * 上传Base64图片
     * @return Response
     */
    public function uploadImageBase64()
    {
        $data = $this->request->params([
            ['content', ''],
            ['cate_id', 0],
        ]);

        if (empty($data['content'])) {
            return fail('图片内容不能为空');
        }

        try {
            $result = (new AttachmentService())->uploadBase64Image($data['content'], $data['cate_id']);
            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
