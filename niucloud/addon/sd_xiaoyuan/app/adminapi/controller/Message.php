<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\adminapi\controller;

use addon\sd_xiaoyuan\app\service\core\MessageService;
use core\base\BaseAdminController;
use think\Response;

/**
 * 系统消息管理控制器
 */
class Message extends BaseAdminController
{
    /**
     * 发送系统消息
     */
    public function send(): Response
    {
        $data = $this->request->params([
            ['member_id', 0],
            ['title', ''],
            ['content', ''],
            ['type', 'SYSTEM'],
        ]);
        
        if (empty($data['member_id']) || empty($data['title']) || empty($data['content'])) {
            return fail('请填写完整信息');
        }
        
        $id = (new MessageService())->send((int)$data['member_id'], $data['type'], $data['title'], $data['content']);
        return success(['id' => $id]);
    }

    /**
     * 批量发送系统消息
     */
    public function batchSend(): Response
    {
        $data = $this->request->params([
            ['member_ids', []],
            ['title', ''],
            ['content', ''],
            ['type', 'SYSTEM'],
        ]);
        
        if (empty($data['member_ids']) || empty($data['title']) || empty($data['content'])) {
            return fail('请填写完整信息');
        }
        
        $service = new MessageService();
        foreach ($data['member_ids'] as $member_id) {
            $service->send((int)$member_id, $data['type'], $data['title'], $data['content']);
        }
        
        return success('发送成功');
    }

    /**
     * 获取消息类型列表
     */
    public function typeList(): Response
    {
        $list = (new MessageService())->getTypeList();
        return success($list);
    }

    /**
     * 获取发送历史记录
     */
    public function history(): Response
    {
        $params = $this->request->params([
            ['page', 1],
            ['limit', 10],
            ['type', ''],
            ['keyword', '']
        ]);
        
        $service = new MessageService();
        $result = $service->getHistory($this->request->siteId(), $params);
        return success($result);
    }

    /**
     * 发送给全体用户
     */
    public function sendAll(): Response
    {
        $data = $this->request->params([
            ['title', ''],
            ['content', ''],
            ['type', 'SYSTEM'],
        ]);
        
        if (empty($data['title']) || empty($data['content'])) {
            return fail('请填写完整信息');
        }
        
        $service = new MessageService();
        $result = $service->sendToAll($this->request->siteId(), $data['type'], $data['title'], $data['content']);
        return success($result);
    }
}
