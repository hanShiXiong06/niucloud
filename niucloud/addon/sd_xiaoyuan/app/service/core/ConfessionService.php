<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Confession;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 表白墙服务
 */
class ConfessionService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取表白列表
     */
    public function getList($params)
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $keyword = $params['keyword'] ?? '';
        $type = $params['type'] ?? '';
        $school_id = $params['school_id'] ?? 0;

        $where = [
            ['site_id', '=', $this->site_id],
        ];

        if (isset($params['member_id']) && $params['member_id']) {
            $where[] = ['member_id', '=', $params['member_id']];
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = ['status', '=', (int)$params['status']];
            }
        } else {
            $where[] = ['status', '=', 1];
        }

        if (!empty($keyword)) {
            $where[] = ['content|target_name', 'like', '%' . $keyword . '%'];
        }

        if (!empty($type)) {
            $where[] = ['type', '=', $type];
        }

        if ($school_id > 0) {
            $where[] = ['school_id', '=', $school_id];
        }

        $model = new Confession();
        $count = $model->where($where)->count();
        
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('create_time desc')
            ->select()
            ->toArray();

        // 附加会员头像和昵称
        $member_ids = array_column($list, 'member_id');
        $confession_ids = array_column($list, 'id');
        $members = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', array_unique($member_ids)]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
        }
        // 查询学校名称
        $schoolIds = array_unique(array_filter(array_column($list, 'school_id')));
        $schoolMap = [];
        if (!empty($schoolIds)) {
            $schoolModel = new \addon\sd_xiaoyuan\app\model\School();
            $schools = $schoolModel->where('id', 'in', $schoolIds)
                ->field('id, name')
                ->select()
                ->toArray();
            $schoolMap = array_column($schools, null, 'id');
        }

        // 查询当前用户的点赞状态
        $likedConfessionIds = [];
        if ($this->member_id && !empty($confession_ids)) {
            $likeModel = new \addon\sd_xiaoyuan\app\model\ConfessionLike();
            $likes = $likeModel->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['confession_id', 'in', $confession_ids]
            ])->column('confession_id');
            $likedConfessionIds = array_flip($likes);
        }
        
        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['nickname'] = $members[$mid]['nickname'] ?? '';
            $item['avatar'] = $members[$mid]['headimg'] ?? '';
            $item['headimg'] = $members[$mid]['headimg'] ?? '';
            $item['school_name'] = $schoolMap[$item['school_id']]['name'] ?? '';
            $item['is_liked'] = isset($likedConfessionIds[$item['id']]);
        }
        unset($item);

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取表白墙统计
     */
    public function getStats()
    {
        $model = new Confession();
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];

        $total = $model->where($where)->count();
        $todayStart = strtotime(date('Y-m-d'));
        $today = $model->where($where)->where('create_time', '>=', $todayStart)->count();

        // 获取总点赞数
        $like = $model->where($where)->sum('like_count');

        return [
            'total' => $total,
            'today' => $today,
            'like' => (int)$like
        ];
    }

    /**
     * 获取表白详情
     */
    public function getDetail($id)
    {
        $confession = (new Confession())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($confession)) {
            throw new CommonException('表白不存在');
        }

        // 增加浏览量
        $confession->inc('view_count', 1)->update();

        $data = $confession->toArray();

        // 附加会员头像和昵称
        if (!empty($data['member_id'])) {
            $member = (new Member())->where([['member_id', '=', $data['member_id']]])->field('member_id,nickname,headimg')->findOrEmpty();
            if (!$member->isEmpty()) {
                $data['nickname'] = $member['nickname'] ?: '';
                $data['avatar'] = !empty($member['headimg']) ? $member['headimg'] : '';
                $data['headimg'] = !empty($member['headimg']) ? $member['headimg'] : '';
            }
        }
        
        // 确保字段存在
        if (!isset($data['nickname'])) $data['nickname'] = '';
        if (!isset($data['avatar'])) $data['avatar'] = '';
        if (!isset($data['headimg'])) $data['headimg'] = '';

        // 查询当前用户是否已点赞
        $data['is_liked'] = false;
        if ($this->member_id) {
            $likeModel = new \addon\sd_xiaoyuan\app\model\ConfessionLike();
            $existLike = $likeModel->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['confession_id', '=', $id]
            ])->find();
            $data['is_liked'] = !empty($existLike);
        }

        return $data;
    }

    /**
     * 发布表白
     */
    public function publish($data)
    {
        // 获取配置，判断是否需要审核
        $configService = new ConfigService();
        $config = $configService->getConfig();
        // 使用 confession_auto_approve 配置（0=需要审核，1=自动通过）
        $autoApprove = $config['confession_auto_approve'] ?? 0;
        
        $confessionData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'school_id' => $data['school_id'] ?? 0,
            'campus' => $data['campus'] ?? '',
            'content' => $data['content'] ?? '',
            'type' => $data['type'] ?? 'ALL',
            'images' => is_array($data['images'] ?? []) ? json_encode($data['images']) : $data['images'],
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'target_name' => $data['target_name'] ?? '',
            'target_info' => $data['target_info'] ?? '',
            'status' => $autoApprove ? 1 : 0, // 根据配置决定：1-已通过，0-待审核
            'create_time' => time(),
            'update_time' => time()
        ];

        $confession = new Confession();
        $confession->save($confessionData);

        return ['id' => $confession->id];
    }

    /**
     * 删除表白
     */
    public function delete($id)
    {
        $confession = (new Confession())->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($confession)) {
            throw new CommonException('表白不存在或无权删除');
        }

        $confession->delete();
        return true;
    }

    /**
     * 点赞表白
     */
    public function like($confession_id)
    {
        $confession = (new Confession())->where([
            ['id', '=', $confession_id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($confession)) {
            throw new CommonException('表白不存在');
        }

        // 检查是否是自己的表白
        if ($confession['member_id'] == $this->member_id) {
            throw new CommonException('不能给自己的表白点赞');
        }

        // 检查是否已经点过赞
        $likeModel = new \addon\sd_xiaoyuan\app\model\ConfessionLike();
        $existLike = $likeModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['confession_id', '=', $confession_id]
        ])->find();

        if ($existLike) {
            throw new CommonException('您已经点过赞了');
        }

        // 添加点赞记录
        $likeModel->save([
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'confession_id' => $confession_id,
            'create_time' => time()
        ]);

        // 增加点赞数
        $confession->inc('like_count', 1)->update();
        
        return true;
    }

    /**
     * 获取我的表白列表
     */
    public function getMyList($params)
    {
        $params['member_id'] = $this->member_id;
        return $this->getList($params);
    }
}
