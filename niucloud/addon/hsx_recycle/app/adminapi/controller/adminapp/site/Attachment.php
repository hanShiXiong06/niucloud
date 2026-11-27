<?php

namespace addon\hsx_recycle\app\adminapi\controller\adminapp\site;

use app\service\admin\sys\AttachmentService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 手机管理端附件管理。
 */
class Attachment extends BaseAdminController
{
    public function getConfig()
    {
        // 核心 AttachmentService 无 getConfig() 方法，此处直接返回前端所需字段
        // is_cropper: 是否启用图片裁剪（0 关闭 / 1 开启）
        return success([
            'is_cropper' => 0
        ]);
    }

    public function categoryLists()
    {
        $data = $this->request->params([
            ['type', ''],
            ['name', ''],
        ]);
        return success((new AttachmentService())->getCategoryList($data));
    }

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
