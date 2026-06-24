<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Guestbook;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 留言板服务
 */
class GuestbookService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取留言列表
     */
    public function getList($params)
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];

        $model = new Guestbook();
        $count = $model->where($where)->count();
        
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('is_top desc, create_time desc')
            ->select()
            ->toArray();
        
        // 关联查询用户信息
        if (!empty($list)) {
            $memberIds = array_unique(array_column($list, 'member_id'));
            
            $memberMap = [];
            if (!empty($memberIds)) {
                $memberModel = new \app\model\member\Member();
                $members = $memberModel->where('member_id', 'in', $memberIds)
                    ->field('member_id, nickname, headimg')
                    ->select()
                    ->toArray();
                $memberMap = array_column($members, null, 'member_id');
            }
                
            foreach ($list as &$item) {
                $item['member_nickname'] = $memberMap[$item['member_id']]['nickname'] ?? '匿名用户';
                $item['member_headimg'] = $memberMap[$item['member_id']]['headimg'] ?? '';
            }
        }

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取留言详情
     */
    public function getDetail($id)
    {
        $item = (new Guestbook())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($item)) {
            throw new CommonException('留言不存在');
        }

        $data = $item->toArray();
        
        // 获取用户信息
        if (!empty($data['member_id'])) {
            $memberModel = new \app\model\member\Member();
            $member = $memberModel->where('member_id', $data['member_id'])
                ->field('member_id, nickname, headimg')
                ->find();
            if ($member) {
                $data['member_nickname'] = $member['nickname'] ?? '匿名用户';
                $data['member_headimg'] = $member['headimg'] ?? '';
            }
        }

        return $data;
    }

    /**
     * 发布留言
     */
    public function publish($data)
    {
        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            throw new CommonException('留言内容不能为空');
        }

        $postData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'content' => $content,
            'images' => is_array($data['images'] ?? []) ? json_encode($data['images']) : ($data['images'] ?? ''),
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'status' => 1,
            'reply' => '',
            'reply_time' => 0,
            'is_top' => 0,
            'create_time' => time(),
            'update_time' => time()
        ];

        $item = new Guestbook();
        $item->save($postData);

        return ['id' => $item->id];
    }

    /**
     * 删除留言
     */
    public function delete($id)
    {
        $item = (new Guestbook())->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($item)) {
            throw new CommonException('留言不存在或无权删除');
        }

        $item->delete();
        return true;
    }

    /**
     * 获取我的留言列表
     */
    public function getMyList($params)
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;

        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        $model = new Guestbook();
        $count = $model->where($where)->count();
        
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取留言统计
     */
    public function getStats()
    {
        $model = new Guestbook();
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];

        $total_count = $model->where($where)->count();
        $today_count = $model->where($where)
            ->whereDay('create_time')
            ->count();

        return [
            'total_count' => $total_count,
            'today_count' => $today_count
        ];
    }
}
