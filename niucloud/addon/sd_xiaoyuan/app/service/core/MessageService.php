<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\Message;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 系统消息服务
 */
class MessageService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new Message();
    }

    /**
     * 发送消息
     */
    public function send(int $member_id, string $type, string $title, string $content, array $extra = [], int $from_member_id = 0)
    {
        $data = [
            'site_id' => $this->site_id,
            'member_id' => $member_id,
            'from_member_id' => $from_member_id,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'link_type' => $extra['link_type'] ?? '',
            'link_id' => $extra['link_id'] ?? ($extra['task_id'] ?? ($extra['group_id'] ?? ($extra['goods_id'] ?? 0))),
            'is_read' => 0,
            'create_time' => time(),
        ];
        
        $res = $this->model->create($data);
        return $res->id;
    }

    /**
     * 获取消息列表
     */
    public function getPage(array $where = []): array
    {
        $field = 'id,type,title,content,extra,link_type,link_id,is_read,create_time';
        $order = 'id desc';

        $where['member_id'] = $this->member_id;
        $search_model = $this->model->where([['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]])->withSearch(['type', 'is_read'], $where)->field($field)->order($order);
        $result = $this->pageQuery($search_model);
        
        // 转换返回格式，前端期望 list 而不是 data
        return [
            'list' => $result['data'] ?? [],
            'count' => $result['total'] ?? 0,
            'page' => $result['current_page'] ?? 1,
            'limit' => $result['per_page'] ?? 10
        ];
    }

    /**
     * 获取未读消息数量
     */
    public function getUnreadCount(string $type = '')
    {
        $where = [['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['is_read', '=', 0]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }
        return $this->model->where($where)->count();
    }

    /**
     * 获取各类型未读消息数量
     */
    public function getUnreadCountByType()
    {
        $types = Message::getTypeList();
        $result = [];
        foreach ($types as $type => $name) {
            $result[$type] = $this->getUnreadCount($type);
        }
        $result['total'] = array_sum($result);
        return $result;
    }

    /**
     * 标记消息已读
     */
    public function read(int $message_id)
    {
        $message = $this->model->where([['id', '=', $message_id], ['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($message)) {
            throw new CommonException('消息不存在');
        }
        
        if ($message['is_read'] == 0) {
            $message->save([
                'is_read' => 1,
                'read_time' => time(),
            ]);
        }
        
        return true;
    }

    /**
     * 标记所有消息已读
     */
    public function readAll(string $type = '')
    {
        $where = [['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id], ['is_read', '=', 0]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }
        
        $this->model->where($where)->update([
            'is_read' => 1,
            'read_time' => time(),
        ]);
        
        return true;
    }

    /**
     * 删除消息
     */
    public function del(int $message_id)
    {
        $message = $this->model->where([['id', '=', $message_id], ['member_id', '=', $this->member_id], ['site_id', '=', $this->site_id]])->find();
        if (empty($message)) {
            throw new CommonException('消息不存在');
        }
        
        $message->delete();
        return true;
    }

    /**
     * 清空消息
     */
    public function clear(string $type = '')
    {
        $where = [['site_id', '=', $this->site_id], ['member_id', '=', $this->member_id]];
        if ($type !== '') {
            $where[] = ['type', '=', $type];
        }
        
        $this->model->where($where)->delete();
        return true;
    }

    /**
     * 获取消息类型列表
     */
    public function getTypeList()
    {
        return Message::getTypeList();
    }

    /**
     * 获取发送历史记录
     */
    public function getHistory(int $siteId, array $params)
    {
        $where = [['site_id', '=', $siteId]];
        
        if (!empty($params['type'])) {
            $where[] = ['type', '=', $params['type']];
        }
        
        if (!empty($params['keyword'])) {
            $where[] = ['title|content', 'like', '%' . $params['keyword'] . '%'];
        }
        
        $list = $this->model->where($where)
            ->order('create_time desc')
            ->page($params['page'], $params['limit'])
            ->select()
            ->toArray();
            
        $count = $this->model->where($where)->count();
        
        return [
            'list' => $list,
            'count' => $count
        ];
    }

    /**
     * 直接发送消息（无需登录上下文）
     */
    public function sendDirect(int $siteId, int $member_id, string $type, string $title, string $content, array $extra = [])
    {
        $data = [
            'site_id' => $siteId,
            'member_id' => $member_id,
            'from_member_id' => 0,
            'type' => $type,
            'title' => $title,
            'content' => $content,
            'extra' => json_encode($extra, JSON_UNESCAPED_UNICODE),
            'link_type' => $extra['link_type'] ?? '',
            'link_id' => $extra['link_id'] ?? 0,
            'is_read' => 0,
            'create_time' => time(),
        ];
        return $this->model->create($data)->id;
    }

    /**
     * 通知同校在线接单员有新订单
     */
    public function notifyOnlineRunners(int $siteId, int $schoolId, string $title, string $content, array $extra = [])
    {
        $runnerModel = new \addon\sd_xiaoyuan\app\model\runner\Runner();
        $runners = $runnerModel->where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
            ['is_online', '=', 1],
            ['school_id', '=', $schoolId]
        ])->field('id,member_id')->select()->toArray();

        $count = 0;
        foreach ($runners as $runner) {
            $this->sendDirect($siteId, $runner['member_id'], 'ORDER', $title, $content, $extra);
            $count++;
        }
        return $count;
    }

    /**
     * 发送给全体用户
     */
    public function sendToAll(int $siteId, string $type, string $title, string $content)
    {
        // 获取所有用户
        $memberModel = new \app\model\member\Member();
        $members = $memberModel->where('site_id', $siteId)
            ->field('member_id')
            ->select()
            ->toArray();
        
        $count = 0;
        foreach ($members as $member) {
            $this->send($member['member_id'], $type, $title, $content);
            $count++;
        }
        
        return ['count' => $count];
    }
}
