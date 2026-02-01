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

namespace addon\phone_shop\app\adminapi\controller\sys;

use addon\phone_shop\app\service\admin\sys\ShortLinkService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 小程序 Short Link 控制器
 * Class ShortLink
 * @package addon\phone_shop\app\adminapi\controller\sys
 */
class ShortLink extends BaseAdminController
{
    /**
     * 生成单个 Short Link
     * @return Response
     */
    public function generate()
    {
        $data = $this->request->params([
            ['page_url', ''],
            ['page_title', ''],
            ['is_permanent', false]
        ]);

        try {
            $shortLink = (new ShortLinkService())->generateShortLink($data);
            return success(['link' => $shortLink]);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /**
     * 批量生成商品 Short Link
     * @return Response
     */
    public function batchGenerate()
    {
        $data = $this->request->params([
            ['goods_list', []]
        ]);

        if (empty($data['goods_list'])) {
            return fail('商品列表不能为空');
        }

        try {
            $result = (new ShortLinkService())->batchGenerateGoodsShortLink($data['goods_list']);
            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
