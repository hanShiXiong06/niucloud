<?php
declare(strict_types=1);

namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsArrivalService;
use app\service\admin\auth\AuthService;
use core\base\BaseAdminService;
use core\exception\CommonException;

class GoodsArrivalService extends BaseAdminService
{
    /** 即使升级后菜单尚未刷新，也不能因新路由未注册权限而默认放行群发。 */
    private function authorize(): void
    {
        if (AuthService::isSuperAdmin()) return;
        $apis = (new AuthService())->getAuthApiList();
        $allowed = array_map('strtolower', $apis['post'] ?? []);
        if (!array_intersect($allowed, ['phone_shop/goods/transfer/tasks/:id/notice', 'phone_shop/goods/transfer/tasks/<id>/notice'])) {
            throw new CommonException('没有“上新通知”权限，请更新插件菜单并由管理员授权');
        }
    }

    public function preview(int $id): array
    {
        $this->authorize();
        return (new CoreGoodsArrivalService())->preview((int)$this->site_id, $id);
    }

    public function send(int $id): array
    {
        $this->authorize();
        return (new CoreGoodsArrivalService())->create((int)$this->site_id, $id, (int)$this->uid, (string)$this->username);
    }

    public function retry(int $id): array
    {
        $this->authorize();
        return (new CoreGoodsArrivalService())->retry((int)$this->site_id, $id);
    }

    public function results(int $id, int $page, int $limit): array
    {
        $this->authorize();
        return (new CoreGoodsArrivalService())->results((int)$this->site_id, $id, $page, $limit);
    }

    public function supplement(int $id, string $audienceToken): array
    {
        $this->authorize();
        return (new CoreGoodsArrivalService())->supplement((int)$this->site_id, $id, $audienceToken);
    }
}
