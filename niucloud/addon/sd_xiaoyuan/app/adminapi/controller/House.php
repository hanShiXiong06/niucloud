<?php

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\model\House as HouseModel;
use core\base\BaseAdminController;

/**
 * 房屋租赁管理控制器
 */
class House extends BaseAdminController
{
    /**
     * 房源列表
     */
    public function lists()
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['status', ''],
            ['school_id', ''],
            ['house_type', ''],
            ['keyword', '']
        ]);

        $where = [['site_id', '=', $this->request->siteId()]];

        if ($params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }

        if (!empty($params['house_type'])) {
            $where[] = ['house_type', '=', $params['house_type']];
        }

        if (!empty($params['keyword'])) {
            $where[] = ['title|address', 'like', '%' . $params['keyword'] . '%'];
        }

        $model = new HouseModel();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($params['page'], $params['limit'])
            ->order('id desc')
            ->select()
            ->toArray();

        // 关联学校名称
        if (!empty($list)) {
            // 收集所有学校ID
            $all_school_ids = [];
            foreach ($list as $item) {
                if (!empty($item['school_id'])) {
                    $all_school_ids[] = $item['school_id'];
                }
                if (!empty($item['nearby_schools'])) {
                    $nearby = is_string($item['nearby_schools']) ? explode(',', $item['nearby_schools']) : $item['nearby_schools'];
                    if (is_array($nearby)) {
                        $all_school_ids = array_merge($all_school_ids, array_map('intval', $nearby));
                    }
                }
            }
            $all_school_ids = array_unique(array_filter($all_school_ids));
            
            $schools = [];
            if (!empty($all_school_ids)) {
                $schools = (new \addon\sd_xiaoyuan\app\model\School())->where([['id', 'in', $all_school_ids]])->column('name', 'id');
            }
            
            foreach ($list as &$item) {
                $item['school_name'] = $schools[$item['school_id'] ?? 0] ?? '';
                // 附近学校名称
                $nearby = [];
                if (!empty($item['nearby_schools'])) {
                    $nearby_ids = is_string($item['nearby_schools']) ? explode(',', $item['nearby_schools']) : $item['nearby_schools'];
                    if (is_array($nearby_ids)) {
                        foreach ($nearby_ids as $sid) {
                            $sid = intval($sid);
                            if (isset($schools[$sid])) {
                                $nearby[] = $schools[$sid];
                            }
                        }
                    }
                }
                $item['nearby_schools_name'] = implode('、', $nearby);
            }
            unset($item);
        }

        return success(['count' => $count, 'list' => $list]);
    }

    /**
     * 房源详情
     */
    public function info($id)
    {
        $info = (new HouseModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($info)) {
            return $this->error('房源不存在');
        }

        return success($info->toArray());
    }

    /**
     * 审核房源
     */
    public function audit()
    {
        $id = $this->request->param('id');
        $status = $this->request->param('status', 1);

        $house = (new HouseModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($house)) {
            return $this->error('房源不存在');
        }

        $house->save(['status' => $status, 'update_time' => time()]);
        return success('操作成功');
    }

    /**
     * 发布房源（后台）
     */
    public function add()
    {
        $data = $this->request->params([
            ['title', ''],
            ['house_type', 'RENT'],
            ['nearby_schools', []],
            ['facilities', []],
            ['rent_price', 0],
            ['deposit', 0],
            ['area', 0],
            ['rooms', ''],
            ['floor', ''],
            ['address', ''],
            ['lng', ''],
            ['lat', ''],
            ['cover_image', ''],
            ['images', ''],
            ['content', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['rent_type', 'MONTH'],
            ['school_id', 0],
            ['campus', '']
        ]);

        if (empty($data['title'])) return $this->error('请输入标题');
        if (empty($data['address'])) return $this->error('请输入地址');

        $data['site_id'] = $this->request->siteId();
        $data['member_id'] = 0;
        $data['status'] = 1;
        $data['images'] = is_array($data['images']) ? json_encode($data['images']) : $data['images'];
        $data['nearby_schools'] = is_array($data['nearby_schools']) ? implode(',', $data['nearby_schools']) : $data['nearby_schools'];
        $data['facilities'] = is_array($data['facilities']) ? implode(',', $data['facilities']) : $data['facilities'];
        $data['create_time'] = time();
        $data['update_time'] = time();

        (new HouseModel())->save($data);
        return success('发布成功');
    }

    /**
     * 编辑房源（后台）
     */
    public function edit()
    {
        $id = $this->request->param('id');
        $house = (new HouseModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($house)) return $this->error('房源不存在');

        $data = $this->request->params([
            ['title', ''],
            ['house_type', ''],
            ['nearby_schools', []],
            ['facilities', []],
            ['rent_price', 0],
            ['deposit', 0],
            ['area', 0],
            ['rooms', ''],
            ['floor', ''],
            ['address', ''],
            ['lng', ''],
            ['lat', ''],
            ['cover_image', ''],
            ['images', ''],
            ['content', ''],
            ['contact_name', ''],
            ['contact_mobile', ''],
            ['rent_type', ''],
            ['school_id', 0],
            ['campus', '']
        ]);

        $updateData = array_filter($data, function($v) { return $v !== '' && $v !== null; });
        if (isset($data['images'])) {
            $updateData['images'] = is_array($data['images']) ? json_encode($data['images']) : $data['images'];
        }
        if (isset($data['cover_image'])) {
            $updateData['cover_image'] = $data['cover_image'];
        }
        if (isset($data['nearby_schools'])) {
            $updateData['nearby_schools'] = is_array($data['nearby_schools']) ? implode(',', $data['nearby_schools']) : $data['nearby_schools'];
        }
        if (isset($data['facilities'])) {
            $updateData['facilities'] = is_array($data['facilities']) ? implode(',', $data['facilities']) : $data['facilities'];
        }
        $updateData['update_time'] = time();

        $house->save($updateData);
        return success('编辑成功');
    }

    /**
     * 删除房源
     */
    public function delete()
    {
        $id = $this->request->param('id');

        $house = (new HouseModel())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->request->siteId()]
        ])->find();

        if (empty($house)) {
            return $this->error('房源不存在');
        }

        $house->delete();
        return success('删除成功');
    }
}
