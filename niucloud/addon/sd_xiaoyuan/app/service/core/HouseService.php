<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\House;
use addon\sd_xiaoyuan\app\model\School;
use addon\sd_xiaoyuan\app\service\admin\ConfigService;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 房屋租赁服务
 */
class HouseService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取房源列表
     */
    public function getList($params)
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 10);
        $house_type = $params['house_type'] ?? '';
        $min_price = $params['min_price'] ?? 0;
        $max_price = $params['max_price'] ?? 0;
        $school_id = (int)($params['school_id'] ?? 0);

        $member_id = (int)($params['member_id'] ?? 0);

        $where = [
            ['site_id', '=', $this->site_id],
        ];

        // 我的房源列表不限制status=1，公开列表只显示已发布
        if ($member_id > 0) {
            $where[] = ['member_id', '=', $member_id];
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = ['status', '=', (int)$params['status']];
            }
        } else {
            $where[] = ['status', '=', 1];
        }

        if (!empty($house_type)) {
            $where[] = ['house_type', '=', $house_type];
        }

        if ($min_price > 0) {
            $where[] = ['rent_price', '>=', $min_price];
        }

        if ($max_price > 0) {
            $where[] = ['rent_price', '<=', $max_price];
        }

        $keyword = $params['keyword'] ?? '';
        if (!empty($keyword)) {
            $where[] = ['title|address|content', 'like', '%' . $keyword . '%'];
        }

        $model = new House();

        // 如果传了school_id，按学校筛选（使用FIND_IN_SET查询nearby_schools字段）
        // 查询条件：school_id等于当前学校 或 nearby_schools包含当前学校
        if ($school_id > 0) {
            $model = $model->where(function($query) use ($school_id) {
                $query->where('school_id', '=', $school_id)
                    ->whereOrRaw('FIND_IN_SET(?, nearby_schools)', [$school_id]);
            });
        }

        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        $list = $this->formatList($list);

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 格式化列表数据，添加前端所需的别名字段
     */
    protected function formatList($list)
    {
        // 关联会员信息
        $member_ids = array_unique(array_filter(array_column($list, 'member_id')));
        $members = [];
        if (!empty($member_ids)) {
            $members = (new \app\model\member\Member())->where([['member_id', 'in', $member_ids]])->column('nickname,headimg', 'member_id');
        }
        
        foreach ($list as &$item) {
            $item = $this->formatItem($item);
            $mid = $item['member_id'] ?? 0;
            $item['member_nickname'] = $members[$mid]['nickname'] ?? '';
            $item['member_avatar'] = $members[$mid]['headimg'] ?? '';
        }
        unset($item);
        return $list;
    }

    /**
     * 格式化单条数据
     */
    protected function formatItem($item)
    {
        $item['price'] = $item['rent_price'] ?? 0;
        $item['room_type'] = $item['rooms'] ?? '';
        $item['description'] = $item['content'] ?? '';
        // 解析images JSON
        if (!empty($item['images']) && is_string($item['images'])) {
            $decoded = json_decode($item['images'], true);
            if (is_array($decoded)) {
                $item['images'] = $decoded;
            }
        }
        // 封面图：优先cover_image，否则取images第一张
        if (empty($item['cover_image']) && !empty($item['images'])) {
            $imgs = is_array($item['images']) ? $item['images'] : [];
            $item['cover_image'] = $imgs[0] ?? '';
        }
        // 解析facilities（逗号分隔）
        if (!empty($item['facilities']) && is_string($item['facilities'])) {
            $item['facilities'] = explode(',', $item['facilities']);
        }
        // 解析nearby_schools（逗号分隔）
        if (!empty($item['nearby_schools']) && is_string($item['nearby_schools'])) {
            $item['nearby_schools'] = explode(',', $item['nearby_schools']);
        }
        return $item;
    }

    /**
     * 获取房源详情
     */
    public function getDetail($id)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($house)) {
            throw new CommonException('房源不存在');
        }

        // 增加浏览量
        $house->inc('view_count', 1)->update();

        return $this->formatItem($house->toArray());
    }

    /**
     * 发布房源
     */
    public function publish($member_id, $data)
    {
        // 获取配置，判断是否自动审核通过
        $configService = new ConfigService();
        $config = $configService->getConfig();
        $autoApprove = $config['house_auto_approve'] ?? 0;
        
        $houseData = [
            'site_id' => $this->site_id,
            'member_id' => $member_id,
            'title' => $data['title'],
            'house_type' => $data['house_type'] ?? 'RENT',
            'rooms' => $data['rooms'] ?? ($data['room_type'] ?? ''),
            'area' => $data['area'] ?? 0,
            'rent_price' => $data['rent_price'] ?? ($data['price'] ?? 0),
            'deposit' => $data['deposit'] ?? 0,
            'address' => $data['address'],
            'lng' => $data['lng'] ?? '',
            'lat' => $data['lat'] ?? '',
            'cover_image' => $data['cover_image'] ?? '',
            'images' => is_array($data['images'] ?? []) ? json_encode($data['images']) : ($data['images'] ?? ''),
            'facilities' => is_array($data['facilities'] ?? []) ? implode(',', $data['facilities']) : ($data['facilities'] ?? ''),
            'content' => $data['content'] ?? ($data['description'] ?? ''),
            'contact_name' => $data['contact_name'] ?? '',
            'contact_mobile' => $data['contact_mobile'] ?? '',
            'status' => $autoApprove ? House::STATUS_PUBLISHED : House::STATUS_PENDING, // 根据配置决定状态：1-自动通过，0-待审核
            'create_time' => time(),
            'update_time' => time()
        ];

        $house = new House();
        $house->save($houseData);

        return ['id' => $house->id];
    }

    /**
     * 更新房源
     */
    public function update($member_id, $id, $data)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($house)) {
            throw new CommonException('房源不存在或无权修改');
        }

        $updateData = array_filter([
            'title' => $data['title'] ?? null,
            'house_type' => $data['house_type'] ?? null,
            'rooms' => $data['rooms'] ?? ($data['room_type'] ?? null),
            'area' => $data['area'] ?? null,
            'rent_price' => $data['rent_price'] ?? ($data['price'] ?? null),
            'deposit' => $data['deposit'] ?? null,
            'address' => $data['address'] ?? null,
            'content' => $data['content'] ?? ($data['description'] ?? null),
            'contact_name' => $data['contact_name'] ?? null,
            'contact_mobile' => $data['contact_mobile'] ?? null,
            'cover_image' => $data['cover_image'] ?? null,
            'update_time' => time()
        ], function($v) { return $v !== null; });

        if (isset($data['images'])) {
            $updateData['images'] = is_array($data['images']) ? json_encode($data['images']) : $data['images'];
        }

        if (isset($data['facilities'])) {
            $updateData['facilities'] = is_array($data['facilities']) ? implode(',', $data['facilities']) : $data['facilities'];
        }

        $house->save($updateData);
        return true;
    }

    /**
     * 删除房源
     */
    public function delete($member_id, $id)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($house)) {
            throw new CommonException('房源不存在或无权删除');
        }

        $house->delete();
        return true;
    }

    /**
     * 下架房源
     */
    public function offline($member_id, $id)
    {
        $house = (new House())->where([
            ['id', '=', $id],
            ['member_id', '=', $member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($house)) {
            throw new CommonException('房源不存在或无权操作');
        }

        $house->save(['status' => House::STATUS_OFFLINE, 'update_time' => time()]);
        return true;
    }

    /**
     * 获取我的房源列表
     */
    public function getMyList($params)
    {
        $params['member_id'] = $this->member_id;
        return $this->getList($params);
    }
}
