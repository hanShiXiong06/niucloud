<?php

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\CommunityComment;
use addon\sd_xiaoyuan\app\model\ConfessionComment;
use addon\sd_xiaoyuan\app\model\Community;
use addon\sd_xiaoyuan\app\model\Confession;
use app\model\member\Member;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 评论服务
 */
class CommentService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 获取树洞帖子评论列表
     */
    public function getCommunityComments(int $postId, int $page = 1, int $limit = 20)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['post_id', '=', $postId],
            ['status', '=', 1]
        ];

        $model = new CommunityComment();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加会员头像和昵称
        $member_ids = array_column($list, 'member_id');
        $members = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', array_unique($member_ids)]])->field('member_id,nickname,headimg')->select()->toArray();
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

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 发布树洞评论
     */
    public function addCommunityComment(array $data)
    {
        $post = (new Community())->where([
            ['id', '=', $data['post_id']],
            ['site_id', '=', $this->site_id],
            ['status', '=', 1]
        ])->find();

        if (empty($post)) {
            throw new CommonException('帖子不存在或已下架');
        }

        $comment = CommunityComment::create([
            'site_id' => $this->site_id,
            'post_id' => $data['post_id'],
            'member_id' => $this->member_id,
            'parent_id' => $data['parent_id'] ?? 0,
            'reply_member_id' => $data['reply_member_id'] ?? 0,
            'content' => $data['content'],
            'status' => 1,
            'create_time' => time()
        ]);
        // 更新帖子评论数
        $post->inc('comment_count')->update();

        return ['id' => $comment->id];
    }

    /**
     * 获取表白墙评论列表
     */
    public function getConfessionComments(int $confessionId, int $page = 1, int $limit = 20)
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['confession_id', '=', $confessionId],
            ['status', '=', 1]
        ];

        $model = new ConfessionComment();
        $count = $model->where($where)->count();
        $list = $model->where($where)
            ->page($page, $limit)
            ->order('id desc')
            ->select()
            ->toArray();

        // 附加会员头像和昵称
        $member_ids = array_column($list, 'member_id');
        $members = [];
        if (!empty($member_ids)) {
            $member_list = (new Member())->where([['member_id', 'in', array_unique($member_ids)]])->field('member_id,nickname,headimg')->select()->toArray();
            foreach ($member_list as $m) {
                $members[$m['member_id']] = $m;
            }
        }
        foreach ($list as &$item) {
            $mid = $item['member_id'] ?? 0;
            $item['nickname'] = $members[$mid]['nickname'] ?? '';
            $item['avatar'] = $members[$mid]['headimg'] ?? '';
            $item['headimg'] = $members[$mid]['headimg'] ?? ''; // 同时设置headimg字段确保兼容性
        }
        unset($item);

        return ['count' => $count, 'list' => $list];
    }

    /**
     * 发布表白墙评论
     */
    public function addConfessionComment(array $data)
    {
        $confession = (new Confession())->where([
            ['id', '=', $data['confession_id']],
            ['site_id', '=', $this->site_id]
        ])->find();

        if (empty($confession)) {
            throw new CommonException('表白不存在');
        }

        $comment = ConfessionComment::create([
            'site_id' => $this->site_id,
            'confession_id' => $data['confession_id'],
            'member_id' => $this->member_id,
            'parent_id' => $data['parent_id'] ?? 0,
            'content' => $data['content'],
            'is_anonymous' => $data['is_anonymous'] ?? 0,
            'status' => 1,
            'create_time' => time()
        ]);

        // 更新表白评论数
        $confession->inc('comment_count')->update();

        return ['id' => $comment->id];
    }

    /**
     * 删除评论
     */
    public function deleteComment(int $id, string $type = 'community')
    {
        if ($type === 'community') {
            $comment = (new CommunityComment())->where([
                ['id', '=', $id],
                ['member_id', '=', $this->member_id]
            ])->find();
        } else {
            $comment = (new ConfessionComment())->where([
                ['id', '=', $id],
                ['member_id', '=', $this->member_id]
            ])->find();
        }

        if (empty($comment)) {
            throw new CommonException('评论不存在或无权删除');
        }

        $comment->delete();
        return true;
    }
}
