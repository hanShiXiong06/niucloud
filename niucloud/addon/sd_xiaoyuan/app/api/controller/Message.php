<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\MessageService;
use core\base\BaseApiController;
use think\Response;

/**
 * 系统消息接口
 */
class Message extends BaseApiController
{
    /**
     * 获取消息类型列表
     */
    public function typeList(): Response
    {
        $list = (new MessageService())->getTypeList();
        return success($list);
    }

    /**
     * 获取消息列表
     */
    public function list(): Response
    {
        $data = $this->request->params([
            ['type', ''],
            ['is_read', ''],
            ['page', 1],
            ['limit', 10],
        ]);
        
        $list = (new MessageService())->getPage($data);
        return success($list);
    }

    /**
     * 获取未读消息数量
     */
    public function unreadCount(): Response
    {
        $type = $this->request->param('type', '');
        $count = (new MessageService())->getUnreadCount($type);
        return success(['count' => $count]);
    }

    /**
     * 获取各类型未读消息数量
     */
    public function unreadCountByType(): Response
    {
        $counts = (new MessageService())->getUnreadCountByType();
        return success($counts);
    }

    /**
     * 标记消息已读
     */
    public function read(): Response
    {
        $message_id = $this->request->param('message_id', 0);
        if (empty($message_id)) {
            return fail('参数错误');
        }
        
        (new MessageService())->read((int)$message_id);
        return success('操作成功');
    }

    /**
     * 标记所有消息已读
     */
    public function readAll(): Response
    {
        $type = $this->request->param('type', '');
        (new MessageService())->readAll($type);
        return success('操作成功');
    }

    /**
     * 删除消息
     */
    public function del(): Response
    {
        $message_id = $this->request->param('message_id', 0);
        if (empty($message_id)) {
            return fail('参数错误');
        }
        
        (new MessageService())->del((int)$message_id);
        return success('删除成功');
    }

    /**
     * 清空消息
     */
    public function clear(): Response
    {
        $type = $this->request->param('type', '');
        (new MessageService())->clear($type);
        return success('清空成功');
    }
}
