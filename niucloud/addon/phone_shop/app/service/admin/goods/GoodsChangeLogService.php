<?php
declare(strict_types=1);
namespace addon\phone_shop\app\service\admin\goods;

use addon\phone_shop\app\service\core\goods\CoreGoodsChangeLogService;
use core\base\BaseAdminService;
use core\exception\AdminException;
use think\facade\Db;

final class GoodsChangeLogService extends BaseAdminService
{
    public function getPage(int $goodsId): array
    {
        $apis = (new \app\service\admin\auth\AuthService())->getAuthApiList();
        if (!in_array('phone_shop/goods', $apis['get'] ?? [], true)) throw new AdminException('没有查看商品列表的权限');
        if ($goodsId <= 0 || !Db::name('phone_shop_goods')->where('site_id', $this->site_id)->where('goods_id', $goodsId)->count()) {
            throw new AdminException('商品不存在或不属于当前站点');
        }
        $page = $this->getPageParam();
        $rows = Db::name('phone_shop_goods_change_log')->where('site_id', $this->site_id)->where('goods_id', $goodsId)
            ->order('id desc')->paginate(['page' => $page['page'], 'list_rows' => min(50, (int)$page['limit'])])->toArray();
        foreach ($rows['data'] as &$row) {
            $row['source_name'] = CoreGoodsChangeLogService::SOURCES[$row['source']] ?? '商品维护';
            $row['changes'] = json_decode((string)$row['changes'], true) ?: [];
        }
        unset($row);
        return $rows;
    }
}
