<?php
declare(strict_types=1);

namespace addon\recycle\app\adminapi\controller\sys;

use addon\recycle\app\service\admin\sys\ShortLinkService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 回收订单小程序 Short Link 控制器
 * Class ShortLink
 * @package addon\recycle\app\adminapi\controller\sys
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
     * 生成回收订单分享链接
     * @return Response
     */
    public function generateOrderLink()
    {
        $data = $this->request->params([
            ['order_id', 0],
            ['order_no', '']
        ]);

        if (empty($data['order_id'])) {
            return fail('订单ID不能为空');
        }

        try {
            $result = (new ShortLinkService())->generateOrderShortLink($data);
            return success($result);
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }
}
