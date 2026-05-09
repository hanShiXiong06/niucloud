<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Community;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 树洞服务
 */
class CommunityService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取帖子列表
     */
    public function getList($params)
    {
        $page = $params['page'] ?? 1;
        $limit = $params['limit'] ?? 10;
        $category_id = $params['category_id'] ?? 0;
        $member_id = $params['member_id'] ?? 0;
        $sort = $params['sort'] ?? 'new';
        $keyword = $params['keyword'] ?? '';
        $school_id = $params['school_id'] ?? 0;

        $where = [
            ['site_id', '=', $this->site_id],
        ];

        if ($member_id > 0) {
            $where[] = ['member_id', '=', $member_id];
            if (isset($params['status']) && $params['status'] !== '') {
                $where[] = ['status', '=', (int)$params['status']];
            }
        } else {
            $where[] = ['status', '=', 1];
        }

        if ($category_id > 0) {
            $where[] = ['category_id', '=', $category_id];
        }

        if (!empty($keyword)) {
            $where[] = ['title|content', 'like', '%' . $keyword . '%'];
        }

        if ($school_id > 0) {
            $where[] = ['school_id', '=', $school_id];
        }

        // 排序规则
        $orderStr = 'create_time desc';
        if ($sort === 'hot') {
            $orderStr = 'view_count desc, like_count desc, create_time desc';
        } elseif ($sort === 'recommend') {
            $orderStr = 'is_recommend desc, is_top desc, like_count desc, create_time desc';
        }

        $model = new Community();
        $count = $model->where($where)->count();
        
        $list = $model->where($where)
            ->page($page, $limit)
            ->order($orderStr)
            ->select()
            ->toArray();
        
        // 关联查询用户信息和分类名称
        if (!empty($list)) {
            $memberIds = array_unique(array_column($list, 'member_id'));
            $categoryIds = array_unique(array_filter(array_column($list, 'category_id')));
            $postIds = array_column($list, 'id');
            
            // 查询用户信息
            $memberMap = [];
            if (!empty($memberIds)) {
                $memberModel = new \app\model\member\Member();
                $members = $memberModel->where('member_id', 'in', $memberIds)
                    ->field('member_id, nickname, headimg')
                    ->select()
                    ->toArray();
                $memberMap = array_column($members, null, 'member_id');
            }
            
            // 查询分类名称
            $categoryMap = [];
            if (!empty($categoryIds)) {
                $categoryModel = new \addon\sd_xiaoyuan\app\model\CommunityCategory();
                $categories = $categoryModel->where('id', 'in', $categoryIds)
                    ->field('id, name')
                    ->select()
                    ->toArray();
                $categoryMap = array_column($categories, null, 'id');
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
            $likedPostIds = [];
            if ($this->member_id && !empty($postIds)) {
                $likeModel = new \addon\sd_xiaoyuan\app\model\CommunityLike();
                $likes = $likeModel->where([
                    ['site_id', '=', $this->site_id],
                    ['member_id', '=', $this->member_id],
                    ['post_id', 'in', $postIds]
                ])->column('post_id');
                $likedPostIds = array_flip($likes);
            }
            
            foreach ($list as &$item) {
                $item['member_nickname'] = $memberMap[$item['member_id']]['nickname'] ?? '';
                $item['member_headimg'] = $memberMap[$item['member_id']]['headimg'] ?? '';
                $item['category_name'] = $categoryMap[$item['category_id']]['name'] ?? '';
                $item['school_name'] = $schoolMap[$item['school_id']]['name'] ?? '';
                $item['is_liked'] = isset($likedPostIds[$item['id']]);
                
                // 格式化时间
                if (!empty($item['create_time']) && is_numeric($item['create_time'])) {
                    $item['create_time'] = date('Y-m-d H:i', $item['create_time']);
                }
                if (!empty($item['update_time']) && is_numeric($item['update_time'])) {
                    $item['update_time'] = date('Y-m-d H:i', $item['update_time']);
                }
            }
        }

        return [
            'count' => $count,
            'list' => $list
        ];
    }

    /**
     * 获取社区统计
     */
    public function getStats()
    {
        $model = new Community();
        $where = [
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ];

        $post_count = $model->where($where)->count();
        $view_count = $model->where($where)->sum('view_count');
        $like_count = $model->where($where)->sum('like_count');

        return [
            'post_count' => $post_count,
            'view_count' => $view_count,
            'like_count' => $like_count
        ];
    }

    /**
     * 获取帖子详情
     */
    public function getDetail($id)
    {
        $post = (new Community())->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($post)) {
            throw new CommonException('帖子不存在');
        }

        // 增加浏览量
        $post->inc('view_count', 1)->update(['update_time' => time()]);

        $data = $post->toArray();
        
        // 获取用户信息
        if (!empty($data['member_id'])) {
            $memberModel = new \app\model\member\Member();
            $member = $memberModel->where('member_id', $data['member_id'])
                ->field('member_id, nickname, headimg')
                ->find();
            if ($member) {
                $data['member_nickname'] = $member['nickname'] ?? '';
                $data['member_headimg'] = $member['headimg'] ?? '';
            }
        }

        // 查询当前用户是否已点赞
        $data['is_liked'] = false;
        if ($this->member_id) {
            $likeModel = new \addon\sd_xiaoyuan\app\model\CommunityLike();
            $existLike = $likeModel->where([
                ['site_id', '=', $this->site_id],
                ['member_id', '=', $this->member_id],
                ['post_id', '=', $id]
            ])->find();
            $data['is_liked'] = !empty($existLike);
        }

        // 格式化时间
        if (!empty($data['create_time']) && is_numeric($data['create_time'])) {
            $data['create_time'] = date('Y-m-d H:i', $data['create_time']);
        }
        if (!empty($data['update_time']) && is_numeric($data['update_time'])) {
            $data['update_time'] = date('Y-m-d H:i', $data['update_time']);
        }

        return $data;
    }

    /**
     * 发布帖子
     */
    public function publish($data)
    {
        // 获取配置，判断是否自动审核通过
        $configService = new ConfigService();
        $config = $configService->getConfig();
        $autoApprove = $config['community_auto_approve'] ?? 0;
        
        $postData = [
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'school_id' => $data['school_id'] ?? 0,
            'campus' => $data['campus'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'title' => $data['title'] ?? '',
            'content' => $data['content'] ?? '',
            'images' => is_array($data['images'] ?? []) ? json_encode($data['images']) : $data['images'],
            'video' => $data['video'] ?? '',
            'status' => $autoApprove ? 1 : 0, // 根据配置决定状态：1-自动通过，0-待审核
            'create_time' => time(),
        ];

        $post = new Community();
        $post->save($postData);

        return ['id' => $post->id];
    }

    /**
     * 删除帖子
     */
    public function delete($id)
    {
        $post = (new Community())->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($post)) {
            throw new CommonException('帖子不存在或无权删除');
        }

        $post->delete();
        return true;
    }

    /**
     * 点赞帖子
     */
    public function like($post_id)
    {
        $post = (new Community())->where([
            ['id', '=', $post_id],
            ['site_id', '=', $this->site_id]
        ])->find();
        
        if (empty($post)) {
            throw new CommonException('帖子不存在');
        }

        // 检查是否是自己的帖子
        if ($post['member_id'] == $this->member_id) {
            throw new CommonException('不能给自己的帖子点赞');
        }

        // 检查是否已经点过赞
        $likeModel = new \addon\sd_xiaoyuan\app\model\CommunityLike();
        $existLike = $likeModel->where([
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id],
            ['post_id', '=', $post_id]
        ])->find();

        if ($existLike) {
            throw new CommonException('您已经点过赞了');
        }

        // 添加点赞记录
        $likeModel->save([
            'site_id' => $this->site_id,
            'member_id' => $this->member_id,
            'post_id' => $post_id,
            'create_time' => time()
        ]);

        // 增加点赞数
        $post->inc('like_count', 1)->update();
        
        return true;
    }

    /**
     * 获取我的帖子列表
     */
    public function getMyList($params)
    {
        $params['member_id'] = $this->member_id;
        return $this->getList($params);
    }

    /**
     * 获取树洞分类
     */
    public function getCategories()
    {
        $model = new \addon\sd_xiaoyuan\app\model\CommunityCategory();
        $list = $model->where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->order('sort asc, id asc')->select()->toArray();

        return $list;
    }
}
