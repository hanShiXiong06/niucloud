<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\api\controller;

use addon\sd_xiaoyuan\app\service\core\GameCompanionService;
use core\base\BaseApiController;
use think\Response;

/**
 * 游戏陪玩接口
 */
class GameCompanion extends BaseApiController
{
    /**
     * 列表
     */
    public function list(): Response
    {
        $params = $this->request->params([
            ['game_type', ''],
            ['service_type', ''],
            ['school_id', ''],
            ['keyword', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $service = new GameCompanionService();
        $result = $service->getList($params);
        return success($result);
    }

    /**
     * 详情
     */
    public function detail(): Response
    {
        $id = intval($this->request->param('id', 0));
        if (empty($id)) return fail('参数错误');

        $service = new GameCompanionService();
        $detail = $service->getDetail($id);
        return success($detail);
    }

    /**
     * 发布
     */
    public function publish(): Response
    {
        $data = $this->request->params([
            ['game_type', ''],
            ['game_name', ''],
            ['rank_level', ''],
            ['service_type', ''],
            ['title', ''],
            ['content', ''],
            ['images', []],
            ['price', 0],
            ['unit', '小时'],
            ['voice_chat', 1],
            ['gender', 0],
            ['online_time', ''],
            ['school_id', 0],
            ['nickname', ''],
            ['avatar', ''],
        ]);

        if (empty($data['game_type']) || empty($data['service_type']) || empty($data['title'])) {
            return fail('请填写完整信息');
        }
        if ($data['price'] <= 0) {
            return fail('请设置价格');
        }

        $service = new GameCompanionService();
        $id = $service->publish($data);
        return success(['id' => $id]);
    }

    /**
     * 编辑
     */
    public function edit(): Response
    {
        $id = intval($this->request->param('id', 0));
        if (empty($id)) return fail('参数错误');

        $data = $this->request->params([
            ['game_type', ''],
            ['game_name', ''],
            ['rank_level', ''],
            ['service_type', ''],
            ['title', ''],
            ['content', ''],
            ['images', []],
            ['price', 0],
            ['unit', '小时'],
            ['voice_chat', 1],
            ['gender', 0],
            ['online_time', ''],
            ['school_id', 0],
        ]);

        $service = new GameCompanionService();
        $service->edit($id, $data);
        return success('编辑成功');
    }

    /**
     * 上架/下架
     */
    public function setStatus(): Response
    {
        $id = intval($this->request->param('id', 0));
        $status = intval($this->request->param('status', 0));
        if (empty($id)) return fail('参数错误');

        $service = new GameCompanionService();
        $service->setStatus($id, $status);
        return success('操作成功');
    }

    /**
     * 删除
     */
    public function del(): Response
    {
        $id = intval($this->request->param('id', 0));
        if (empty($id)) return fail('参数错误');

        $service = new GameCompanionService();
        $service->del($id);
        return success('删除成功');
    }

    /**
     * 我的列表
     */
    public function my(): Response
    {
        $params = $this->request->params([
            ['status', ''],
            ['page', 1],
            ['limit', 10],
        ]);

        $service = new GameCompanionService();
        $result = $service->getMyList($params);
        return success($result);
    }

    /**
     * 游戏类型列表
     */
    public function gameTypes(): Response
    {
        $service = new GameCompanionService();
        return success($service->getGameTypes());
    }

    /**
     * 服务类型列表
     */
    public function serviceTypes(): Response
    {
        $service = new GameCompanionService();
        return success($service->getServiceTypes());
    }
}
