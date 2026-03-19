<?php

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\HouseService;
use addon\sd_xiaoyuan\app\model\HouseOrder;
use core\base\BaseApiController;

/**
 * 房屋租赁控制器
 */
class House extends BaseApiController
{
    /**
     * 获取房源列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['house_type', ''],
            ['min_price', 0],
            ['max_price', 0],
            ['school_id', 0],
            ['keyword', '']
        ]);

        $service = new HouseService();
        $data = $service->getList($params);
        return success($data);
    }

    /**
     * 获取房源详情
     */
    public function detail()
    {
        $id = $this->request->param('id', 0);
        $service = new HouseService();
        $data = $service->getDetail((int)$id);
        return success($data);
    }

    /**
     * 发布房源
     */
    public function publish()
    {
        $data = $this->request->params([
            ['title', ''],
            ['house_type', 'RENT'],
            ['room_type', ''],
            ['area', 0],
            ['price', 0],
            ['deposit', 0],
            ['address', ''],
            ['lng', ''],
            ['lat', ''],
            ['images', []],
            ['cover_image', ''],
            ['facilities', []],
            ['description', ''],
            ['contact_name', ''],
            ['contact_mobile', '']
        ]);

        // 映射前端字段名到数据库字段名
        $data['rent_price'] = $data['price'];
        $data['rooms'] = $data['room_type'];
        $data['content'] = $data['description'];

        $member_id = $this->request->memberId();
        $service = new HouseService();
        $result = $service->publish($member_id, $data);
        return success($result);
    }

    /**
     * 更新房源
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $data = $this->request->params([
            ['title', null],
            ['house_type', null],
            ['room_type', null],
            ['area', null],
            ['price', null],
            ['deposit', null],
            ['address', null],
            ['images', null],
            ['cover_image', null],
            ['facilities', null],
            ['description', null],
            ['contact_name', null],
            ['contact_mobile', null]
        ]);

        // 映射前端字段名到数据库字段名
        if ($data['price'] !== null) $data['rent_price'] = $data['price'];
        if ($data['room_type'] !== null) $data['rooms'] = $data['room_type'];
        if ($data['description'] !== null) $data['content'] = $data['description'];

        $member_id = $this->request->memberId();
        $service = new HouseService();
        $service->update($member_id, $id, $data);
        return success();
    }

    /**
     * 删除房源
     */
    public function delete()
    {
        $id = $this->request->param('id');
        $member_id = $this->request->memberId();
        $service = new HouseService();
        $service->delete($member_id, $id);
        return success();
    }

    /**
     * 下架房源
     */
    public function offline()
    {
        $id = $this->request->param('id');
        $member_id = $this->request->memberId();
        $service = new HouseService();
        $service->offline($member_id, $id);
        return success();
    }

    /**
     * 我的房源
     */
    public function my()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', '']
        ]);

        $service = new HouseService();
        $data = $service->getMyList($params);
        return success($data);
    }

    /**
     * 预约看房/下单
     */
    public function order()
    {
        $data = $this->request->params([
            ['house_id', 0],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['message', '']
        ]);

        if (empty($data['house_id'])) return fail('参数错误');
        if (empty($data['contact_name'])) return fail('请输入联系人');
        if (empty($data['contact_mobile'])) return fail('请输入联系电话');

        $member_id = $this->request->memberId();

        $order = new HouseOrder();
        $order->save([
            'site_id' => $this->request->siteId(),
            'member_id' => $member_id,
            'house_id' => $data['house_id'],
            'contact_name' => $data['contact_name'],
            'contact_mobile' => $data['contact_mobile'],
            'message' => $data['message'],
            'status' => 0,
            'create_time' => time(),
            'update_time' => time()
        ]);

        return success(['id' => $order->id]);
    }

    /**
     * 我的房源订单
     */
    public function myOrders()
    {
        $page = (int)$this->request->param('page', 1);
        $limit = (int)$this->request->param('limit', 10);
        $member_id = $this->request->memberId();
        $site_id = $this->request->siteId();

        $model = new HouseOrder();
        $where = [
            ['member_id', '=', $member_id],
            ['site_id', '=', $site_id]
        ];

        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加房源信息
        $houseIds = array_column($list, 'house_id');
        if (!empty($houseIds)) {
            $houses = (new \addon\sd_xiaoyuan\app\model\House())
                ->whereIn('id', $houseIds)
                ->column('title,images,address,rent_price', 'id');
            foreach ($list as &$item) {
                $item['house'] = $houses[$item['house_id']] ?? [];
            }
        }

        return success(['count' => $count, 'list' => $list]);
    }
}
