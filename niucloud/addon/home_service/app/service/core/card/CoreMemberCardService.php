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

namespace addon\home_service\app\service\core\card;

use addon\home_service\app\dict\card\MemberCardDict;
use addon\home_service\app\dict\goods\CardDict;
use addon\home_service\app\model\card\CardUseRecords;
use addon\home_service\app\model\card\MemberCard;
use addon\home_service\app\model\card\MemberCardItem;
use core\base\BaseCoreService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 订单操作日志
 * Class CoreHotelOrderService
 * @package app\service\core\order
 */
class CoreMemberCardService extends BaseCoreService
{
    /**
     * 创建会员卡
     * @param array $data
     * @return void
     */
    public static function create($data) {
        Db::startTrans();
        try {
            $expire_time = [
                CardDict::PERMANENT_CARD => 0,
                CardDict::MONTHLY_CARD   => strtotime('+1 month', time()),
                CardDict::SEASON_CARD    => strtotime('+3 months', time()),
                CardDict::YEAR_CARD      => strtotime('+1 year', time()),
            ];

            // 总可用次数
            $total_num = 0;

            //卡参数
            $member_card_data = [
                'site_id' => $data[0]['site_id'] ?? 0,
                'member_id' => $data[0]['member_id'] ?? 0,
                'card_id' => $data[0]['card_id'] ?? 0,
                'card_no' => create_no(),
                'order_id' => $data[0]['order_id'] ?? 0,
                'status' => MemberCardDict::WAIT_USE,
                'create_time' => time(),
                'expire_time' => $expire_time[ $data[0]['valid_type'] ],
            ];

            // 卡项参数
            $member_card_item = [];
            foreach ($data as $value){
                $total_num += $value['max_use_times'];
                $member_card_item[] = [
                    'site_id' => $value['site_id'],
                    'card_id' => $value['card_id'],
                    'card_sku_id' => $value['card_sku_id'],
                    'member_id' => $value['member_id'],
                    'goods_id' => $value['goods_id'],
                    'goods_sku_id' => $value['goods_sku_id'],
                    'num' => $value['max_use_times'],
                    'sku_unit' => $value['sku_unit'],
                    'price' => $value['price'],
                    'original_price' => $value['original_price'],
                    'expire_time' => $expire_time[ $value['valid_type'] ],
                ];
            }

            $member_card_data['total_num'] = $total_num;

            $member_card_res = (new MemberCard())->create($member_card_data);
            $member_card_id = $member_card_res->id;
            foreach ($member_card_item as &$item) {
                $item['member_card_id'] = $member_card_id;
            }
            (new MemberCardItem())->saveAll($member_card_item);

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 次卡使用
     * @param int $site_id
     * @param int $member_id
     * @param int $order_id
     * @param array $card_data
     * @return bool
     */
    public static function use(int $site_id, int $member_id, int $order_id, array $card_data) {
        $member_card_id = $card_data['member_card_id'] ?? 0;
        $member_card_item_id = $card_data['member_card_item_id'] ?? 0;

        $card_item = (new MemberCardItem())->where([ ['site_id', '=', $site_id], ['item_id', '=', $member_card_item_id] ])
            ->with(['memberCard'])
            ->findOrEmpty();
        if ($card_item->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_ITEM_NOT_EXIST');
        if ($card_item->expire_time > 0 && $card_item->expire_time < time()) throw new CommonException('HOME_SERVICE_CARD_IS_EXPIRE');
        if ($card_item->memberCard->status != MemberCardDict::WAIT_USE) throw new CommonException('HOME_SERVICE_CARD_STATUS_ABNORMAL');
        if ($card_item->num - $card_item->use_num < 1) throw new CommonException('HOME_SERVICE_CARD_ITEM_USABLE_NUM_INSUFFICIENT');

        Db::startTrans();
        try {
            if ($card_item->memberCard->total_use_num +1 >= $card_item->memberCard->total_num){
                $card_item->memberCard->status = MemberCardDict::USED;
            }

            $card_item->memberCard->total_use_num = Db::raw("total_use_num + 1");
            $card_item->memberCard->save();

            $card_item->use_num = Db::raw("use_num + 1");
            $card_item->save();

            $use_records_data = [
                'site_id' => $site_id,
                'member_id' => $member_id,
                'order_id' => $order_id,
                'member_card_id' => $member_card_id,
                'member_card_item_id' => $member_card_item_id,
                'card_id' => $card_item['card_id'],
                'card_sku_id' => $card_item['card_sku_id'],
                'create_time' => time(),
            ];

            (new CardUseRecords())->create($use_records_data);

            Db::commit();

            return true;
        }  catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 恢复(只单个恢复)
     * @return void
     */
    public function recoverMemberCard($site_id,$order_id)
    {
        Db::startTrans();
        try {
            $card_use_record = (new CardUseRecords())->where([ ['site_id', '=', $site_id], ['order_id', '=', $order_id] ])->findOrEmpty();
            if ($card_use_record->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_USE_RECORD_NOT_EXIST');
            $member_card_id = $card_use_record['member_card_id'] ?? 0;
            $member_card_item_id = $card_use_record['member_card_item_id'] ?? 0;

            $card = (new MemberCard())->where([ ['site_id', '=', $site_id], ['id', '=', $member_card_id] ])->findOrEmpty();
            if ($card->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_NOT_EXIST');

            $card_item = (new MemberCardItem())->where([ ['site_id', '=', $site_id], ['item_id', '=', $member_card_item_id] ])->findOrEmpty();
            if ($card_item->isEmpty()) throw new CommonException('HOME_SERVICE_CARD_ITEM_NOT_EXIST');

            if ($card->status == MemberCardDict::USED && ($card->expire_time > 0 && $card->expire_time > time())){
                $card->status = MemberCardDict::WAIT_USE;
            }

            $card->total_use_num = Db::raw("total_use_num - 1");
            $card->save();

            $card_item->use_num = Db::raw("use_num - 1");
            $card_item->save();


            Db::commit();
            return true;
        }  catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
        return true;
    }

    /**
     * 过期
     * @param $ids
     * @return void
     */
    public function expire($ids)
    {
        $where = [
            ['id', 'in', $ids],
            ['status', '=', MemberCardDict::WAIT_USE]
        ];
        $data = [
            'status' => MemberCardDict::EXPIRE
        ];
        $this->model->where($where)->update($data);
        return true;
    }
}
