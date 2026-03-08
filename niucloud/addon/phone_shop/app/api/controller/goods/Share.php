<?php
declare(strict_types=1);

namespace addon\phone_shop\app\api\controller\goods;

use addon\phone_shop\app\service\api\goods\ShareService;
use core\base\BaseApiController;
use think\Response;

/**
 * 商品分享控制器
 * Class Share
 * @package addon\phone_shop\app\api\controller
 */
class Share extends BaseApiController
{
    /**
     * 生成商品分享短链接
     * @return Response
     */
    public function generateLink()
    {
        $data = $this->request->params([
            ['goods_id', 0],
            ['share_user_id', 0]
        ]);

        if (empty($data['goods_id'])) {
            return fail('商品ID不能为空');
        }

        try {
            $result = (new ShareService())->generateGoodsShareLink($data);
            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
