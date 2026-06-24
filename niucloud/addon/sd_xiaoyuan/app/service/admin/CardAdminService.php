<?php

namespace addon\sd_xiaoyuan\app\service\admin;

use addon\sd_xiaoyuan\app\model\CardOrder;
use addon\sd_xiaoyuan\app\model\ServiceCard;
use addon\sd_xiaoyuan\app\service\core\CardService as CoreCardService;
use core\base\BaseAdminService;

class CardAdminService extends BaseAdminService
{
    public function getCardList()
    {
        (new CoreCardService())->initDefaultCards($this->site_id);
        return (new ServiceCard())->where('site_id', $this->site_id)->order('sort asc,id asc')->select()->toArray();
    }

    public function saveCards(array $list)
    {
        $now = time();
        foreach ($list as $row) {
            if (empty($row['id'])) {
                continue;
            }
            (new ServiceCard())->where([
                ['id', '=', (int)$row['id']],
                ['site_id', '=', $this->site_id],
            ])->update([
                'name' => $row['name'] ?? '',
                'subtitle' => $row['subtitle'] ?? '',
                'price' => floatval($row['price'] ?? 0),
                'origin_price' => floatval($row['origin_price'] ?? 0),
                'times' => max(1, (int)($row['times'] ?? 1)),
                'status' => (int)($row['status'] ?? 1),
                'sort' => (int)($row['sort'] ?? 0),
                'update_time' => $now,
            ]);
        }
        return true;
    }

    public function getOrderList(array $params)
    {
        $page = max(1, (int)($params['page'] ?? 1));
        $limit = max(1, (int)($params['limit'] ?? 10));
        $where = [['site_id', '=', $this->site_id]];
        if (!empty($params['card_type'])) {
            $where[] = ['card_type', '=', $params['card_type']];
        }
        if ($params['pay_status'] !== '' && $params['pay_status'] !== null) {
            $where[] = ['pay_status', '=', (int)$params['pay_status']];
        }
        $model = new CardOrder();
        $list = $model->where($where)->order('id desc')->page($page, $limit)->select()->toArray();
        $count = $model->where($where)->count();
        if (!empty($list)) {
            $memberIds = array_unique(array_column($list, 'member_id'));
            $memberRows = (new \app\model\member\Member())->where([['member_id', 'in', $memberIds]])->field('member_id,nickname,mobile')->select()->toArray();
            $members = [];
            foreach ($memberRows as $m) {
                $members[$m['member_id']] = $m;
            }
            foreach ($list as &$row) {
                $m = $members[$row['member_id']] ?? [];
                $row['member_nickname'] = $m['nickname'] ?? '';
                $row['member_mobile'] = $m['mobile'] ?? '';
                $row['create_time_text'] = $row['create_time'] ? date('Y-m-d H:i:s', (int)$row['create_time']) : '';
                $row['pay_time_text'] = $row['pay_time'] ? date('Y-m-d H:i:s', (int)$row['pay_time']) : '';
            }
        }
        return ['list' => $list, 'count' => $count, 'page' => $page, 'limit' => $limit];
    }
}
