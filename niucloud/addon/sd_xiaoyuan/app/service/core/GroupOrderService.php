<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\GroupOrder;
use addon\sd_xiaoyuan\app\model\GroupMember;
use addon\sd_xiaoyuan\app\model\CampusAuth;
use addon\sd_xiaoyuan\app\model\Credit;
use addon\sd_xiaoyuan\app\service\core\MessageService;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;
use think\facade\Db;

/**
 * 拼单好饭服务 - 使用独立的拼单表
 */
class GroupOrderService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new GroupOrder();
    }

    /**
     * 拼单状态常量（映射到order status）
     */
    const STATUS_GROUPING = 0;      // 拼单中（待支付/待接单）
    const STATUS_SUCCESS = 10;      // 已成团
    const STATUS_DELIVERING = 20;   // 配送中
    const STATUS_COMPLETED = 30;    // 已完成
    const STATUS_CANCELLED = 90;    // 已取消
    const STATUS_FAILED = 91;       // 拼单失败

    /**
     * 获取拼单类型列表
     */
    public function getTypeList()
    {
        return [
            'TEA' => '拼奶茶',
            'FOOD' => '拼外卖',
            'FRUIT' => '拼水果',
            'RIDE' => '拼车',
            'OTHER' => '其他',
        ];
    }

    /**
     * 获取拼单统计
     */
    public function getStats()
    {
        $where = [
            ['site_id', '=', $this->site_id]
        ];

        $total = $this->model->where($where)->count();
        $completed = $this->model->where(array_merge($where, [['status', '=', 30]]))->count();
        $todayStart = strtotime(date('Y-m-d'));
        $today = $this->model->where($where)->where('create_time', '>=', $todayStart)->count();

        return [
            'total' => $total,
            'completed' => $completed,
            'today' => $today
        ];
    }

    /**
     * 获取拼单列表
     */
    public function getPage(array $params = [])
    {
        $where = [
            ['site_id', '=', $this->site_id]
        ];

        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }
        if (!empty($params['member_id'])) {
            $where[] = ['member_id', '=', $params['member_id']];
        }
        if (!empty($params['group_ids'])) {
            $where[] = ['id', 'in', $params['group_ids']];
        }
        if (!empty($params['group_type'])) {
            $where[] = ['group_type', '=', $params['group_type']];
        }

        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 10);

        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        // 附加会员信息
        $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', $member_ids]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
        }

        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
            $item['member_headimg'] = $members[$mid]['headimg'] ?? '';
        }
        unset($item);

        return ['list' => $list, 'count' => $count];
    }

    /**
     * 获取拼单详情
     */
    public function getInfo(int $id)
    {
        $item = $this->model->where([['id', '=', $id], ['site_id', '=', $this->site_id]])->find();
        if (empty($item)) {
            throw new CommonException('拼单不存在');
        }

        $info = $item->toArray();

        // 获取参与成员
        $members = (new GroupMember())->where([['group_id', '=', $id]])->select()->toArray();
        $info['members'] = $members;

        return $info;
    }

    /**
     * 发起拼单
     */
    public function create(int $member_id, array $data)
    {
        Db::startTrans();
        try {
            $groupData = [
                'site_id' => $this->site_id,
                'group_no' => 'G' . date('YmdHis') . mt_rand(1000, 9999),
                'member_id' => $member_id,
                'school_id' => $data['school_id'] ?? 0,
                'campus' => $data['campus'] ?? '',
                'group_type' => $data['group_type'] ?? 'OTHER',
                'title' => $data['title'] ?? '',
                'content' => $data['content'] ?? '',
                'images' => $data['images'] ?? '',
                'shop_name' => $data['shop_name'] ?? '',
                'shop_address' => $data['shop_address'] ?? '',
                'delivery_address' => $data['delivery_address'] ?? '',
                'delivery_lng' => $data['delivery_lng'] ?? '',
                'delivery_lat' => $data['delivery_lat'] ?? '',
                'min_members' => intval($data['min_members'] ?? 2),
                'max_members' => intval($data['max_members'] ?? 10),
                'current_members' => 1,
                'per_price' => floatval($data['per_price'] ?? 0),
                'total_price' => floatval($data['per_price'] ?? 0),
                'delivery_fee' => floatval($data['delivery_fee'] ?? 0),
                'deadline' => intval($data['deadline'] ?? (time() + 3600)),
                'status' => self::STATUS_GROUPING,
                'create_time' => time(),
            ];

            $group = $this->model->create($groupData);

            // 添加发起人为团长
            (new GroupMember())->create([
                'site_id' => $this->site_id,
                'group_id' => $group->id,
                'member_id' => $member_id,
                'is_leader' => 1,
                'order_content' => $data['order_content'] ?? '',
                'amount' => floatval($data['per_price'] ?? 0),
                'pay_status' => 0,
                'status' => 0,
                'create_time' => time(),
            ]);

            Db::commit();
            return $group->id;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 参与拼单
     */
    public function join(int $group_id, int $member_id, array $data)
    {
        $group = $this->model->where([['id', '=', $group_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($group)) {
            throw new CommonException('拼单不存在');
        }

        if ($group['status'] != self::STATUS_GROUPING) {
            throw new CommonException('拼单已结束');
        }

        if ($group['current_members'] >= $group['max_members']) {
            throw new CommonException('拼单人数已满');
        }
        if ($group['deadline'] > 0 && $group['deadline'] < time()) {
            throw new CommonException('拼单已过期');
        }

        // 检查是否同校
        $auth = (new CampusAuth())->where([['member_id', '=', $member_id], ['site_id', '=', $this->site_id], ['status', '=', 1]])->find();
        if (empty($auth)) {
            throw new CommonException('请先完成校园帮实名认证');
        }
        if ($auth['school_id'] != $group['school_id']) {
            throw new CommonException('仅支持本校拼单');
        }

        // 检查是否已参与
        $exists = (new GroupMember())->where([['group_id', '=', $group_id], ['member_id', '=', $member_id]])->find();
        if (!empty($exists)) {
            throw new CommonException('您已参与该拼单');
        }

        $amount = floatval($data['amount'] ?? $group['per_price']);

        Db::startTrans();
        try {
            (new GroupMember())->create([
                'site_id' => $this->site_id,
                'group_id' => $group_id,
                'member_id' => $member_id,
                'is_leader' => 0,
                'order_content' => $data['order_content'] ?? '',
                'amount' => $amount,
                'pay_status' => 0,
                'status' => 0,
                'create_time' => time(),
            ]);

            $newCount = $group['current_members'] + 1;
            $newTotal = floatval($group['total_price']) + $amount;
            
            $this->model->where('id', $group_id)->update([
                'current_members' => $newCount,
                'total_price' => $newTotal,
            ]);

            if ($newCount >= $group['min_members']) {
                (new MessageService())->send($group['member_id'], 'GROUP', '拼单已成团', '您发起的拼单已达到最小人数，可以开始配送', ['link_id' => $group_id]);
            }

            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 退出拼单
     */
    public function quit(int $group_id, int $member_id)
    {
        $group = $this->model->where([['id', '=', $group_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($group)) {
            throw new CommonException('拼单不存在');
        }
        if ($group['status'] != self::STATUS_GROUPING) {
            throw new CommonException('拼单已结束，无法退出');
        }

        $member = (new GroupMember())->where([['group_id', '=', $group_id], ['member_id', '=', $member_id]])->find();
        if (empty($member)) {
            throw new CommonException('您未参与该拼单');
        }
        if ($member['is_leader'] == 1) {
            throw new CommonException('团长不能退出，请取消拼单');
        }

        Db::startTrans();
        try {
            $member->delete();
            $newCount = max(1, $group['current_members'] - 1);
            $newTotal = floatval($group['total_price']) - floatval($member['amount']);
            
            $this->model->where('id', $group_id)->update([
                'current_members' => $newCount,
                'total_price' => max(0, $newTotal),
            ]);
            Db::commit();
            return true;
        } catch (\Exception $e) {
            Db::rollback();
            throw new CommonException($e->getMessage());
        }
    }

    /**
     * 取消拼单(团长)
     */
    public function cancel(int $group_id, int $member_id)
    {
        $group = $this->model->where([['id', '=', $group_id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($group)) {
            throw new CommonException('拼单不存在');
        }
        if ($group['status'] != self::STATUS_GROUPING) {
            throw new CommonException('拼单已结束，无法取消');
        }

        $this->model->where('id', $group_id)->update([
            'status' => self::STATUS_CANCELLED,
        ]);

        $members = (new GroupMember())->where([['group_id', '=', $group_id]])->select();
        foreach ($members as $m) {
            if ($m['member_id'] != $member_id) {
                (new MessageService())->send($m['member_id'], 'GROUP', '拼单已取消', '您参与的拼单已被团长取消', ['link_id' => $group_id]);
            }
        }
        return true;
    }

    /**
     * 确认成团(团长)
     */
    public function confirmSuccess(int $group_id, int $member_id)
    {
        $group = $this->model->where([['id', '=', $group_id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($group)) {
            throw new CommonException('拼单不存在');
        }
        if ($group['status'] != self::STATUS_GROUPING) {
            throw new CommonException('拼单状态异常');
        }

        if ($group['current_members'] < $group['min_members']) {
            throw new CommonException('未达到最小人数，无法成团');
        }

        $this->model->where('id', $group_id)->update([
            'status' => self::STATUS_SUCCESS,
        ]);

        $members = (new GroupMember())->where([['group_id', '=', $group_id]])->select();
        foreach ($members as $m) {
            (new MessageService())->send($m['member_id'], 'GROUP', '拼单已成团', '您参与的拼单已成团，请等待配送', ['link_id' => $group_id]);
        }
        return true;
    }

    /**
     * 完成拼单(团长)
     */
    public function complete(int $group_id, int $member_id)
    {
        $group = $this->model->where([['id', '=', $group_id], ['member_id', '=', $member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($group)) {
            throw new CommonException('拼单不存在');
        }
        if (!in_array($group['status'], [self::STATUS_SUCCESS, self::STATUS_DELIVERING])) {
            throw new CommonException('拼单状态异常');
        }

        $this->model->where('id', $group_id)->update([
            'status' => self::STATUS_COMPLETED,
        ]);

        $members = (new GroupMember())->where([['group_id', '=', $group_id]])->select();
        foreach ($members as $m) {
            (new MessageService())->send($m['member_id'], 'GROUP', '拼单已完成', '您参与的拼单已完成', ['link_id' => $group_id]);
        }
        return true;
    }

    /**
     * 获取我创建的拼单列表
     */
    public function getMyCreate(array $params = [])
    {
        $params['member_id'] = $this->member_id;
        return $this->getPage($params);
    }

    /**
     * 获取我参与的拼单列表
     */
    public function getMyJoin(array $params = [])
    {
        $groupIds = (new GroupMember())->where([['member_id', '=', $this->member_id]])->column('group_id');
        if (empty($groupIds)) {
            return ['list' => [], 'count' => 0];
        }
        $params['group_ids'] = $groupIds;
        return $this->getPage($params);
    }

    /**
     * 获取拼单状态列表
     */
    public function getStatusList()
    {
        return [
            self::STATUS_GROUPING => '拼单中',
            self::STATUS_SUCCESS => '已成团',
            self::STATUS_DELIVERING => '配送中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_CANCELLED => '已取消',
            self::STATUS_FAILED => '拼单失败',
        ];
    }
}
