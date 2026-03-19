<?php
declare(strict_types=1);

namespace addon\sd_xiaoyuan\app\service\core;

use addon\sd_xiaoyuan\app\model\GameCompanion;
use addon\sd_xiaoyuan\app\model\School;
use core\base\BaseApiService;
use core\exception\CommonException;

/**
 * 游戏陪玩服务
 */
class GameCompanionService extends BaseApiService
{
    public function __construct()
    {
        parent::__construct();
        $this->model = new GameCompanion();
    }

    /**
     * 获取列表（前端）
     */
    public function getList(array $params): array
    {
        $where = [['site_id', '=', $this->site_id], ['status', '=', GameCompanion::STATUS_ONLINE]];

        if (!empty($params['game_type'])) {
            $where[] = ['game_type', '=', $params['game_type']];
        }
        if (!empty($params['service_type'])) {
            $where[] = ['service_type', '=', $params['service_type']];
        }
        if (!empty($params['school_id'])) {
            $where[] = ['school_id', '=', $params['school_id']];
        }
        if (!empty($params['keyword'])) {
            $where[] = ['title|content|game_name', 'like', '%' . $params['keyword'] . '%'];
        }

        $order = 'is_top desc, id desc';
        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);

        $list = $this->model->where($where)
            ->order($order)
            ->page($page, $limit)
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        return [
            'list' => $list,
            'count' => $count
        ];
    }

    /**
     * 获取详情
     */
    public function getDetail(int $id): array
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty()->toArray();

        if (empty($info)) {
            throw new CommonException('陪玩信息不存在');
        }

        // 增加浏览量
        $this->model->where('id', $id)->inc('view_count')->update();

        return $info;
    }

    /**
     * 发布陪玩
     */
    public function publish(array $data): int
    {
        $data['site_id'] = $this->site_id;
        $data['member_id'] = $this->member_id;
        $data['status'] = GameCompanion::STATUS_ONLINE; // 默认审核通过
        $data['create_time'] = time();
        $data['update_time'] = time();

        if (is_array($data['images'] ?? null)) {
            $data['images'] = json_encode($data['images'], JSON_UNESCAPED_UNICODE);
        }

        $res = $this->model->create($data);

        // 通知发布者
        (new MessageService())->send($this->member_id, 'SYSTEM', '陪玩发布成功', '您的游戏陪玩信息已提交，等待审核');

        return $res->id;
    }

    /**
     * 编辑陪玩
     */
    public function edit(int $id, array $data): bool
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('陪玩信息不存在');
        }

        if (is_array($data['images'] ?? null)) {
            $data['images'] = json_encode($data['images'], JSON_UNESCAPED_UNICODE);
        }

        $data['update_time'] = time();
        $data['status'] = GameCompanion::STATUS_PENDING; // 编辑后重新审核

        $this->model->where('id', $id)->update($data);
        return true;
    }

    /**
     * 上架/下架
     */
    public function setStatus(int $id, int $status): bool
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('陪玩信息不存在');
        }

        $this->model->where('id', $id)->update([
            'status' => $status,
            'update_time' => time()
        ]);
        return true;
    }

    /**
     * 删除
     */
    public function del(int $id): bool
    {
        $info = $this->model->where([
            ['id', '=', $id],
            ['member_id', '=', $this->member_id],
            ['site_id', '=', $this->site_id]
        ])->findOrEmpty();

        if ($info->isEmpty()) {
            throw new CommonException('陪玩信息不存在');
        }

        $this->model->where('id', $id)->delete();
        return true;
    }

    /**
     * 我的陪玩列表
     */
    public function getMyList(array $params): array
    {
        $where = [
            ['site_id', '=', $this->site_id],
            ['member_id', '=', $this->member_id]
        ];

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        $page = intval($params['page'] ?? 1);
        $limit = intval($params['limit'] ?? 10);

        $list = $this->model->where($where)
            ->order('id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        $count = $this->model->where($where)->count();

        // 获取学校名称
        if (!empty($list)) {
            $schoolIds = array_unique(array_filter(array_column($list, 'school_id')));
            if (!empty($schoolIds)) {
                $schools = (new School())->whereIn('id', $schoolIds)->column('name', 'id');
                foreach ($list as &$item) {
                    $item['school_name'] = $schools[$item['school_id']] ?? '';
                }
                unset($item);
            }
        }

        return [
            'list' => $list,
            'count' => $count
        ];
    }

    /**
     * 获取游戏类型列表
     */
    public function getGameTypes(): array
    {
        $types = GameCompanion::getGameTypeList();
        $result = [];
        foreach ($types as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }
        return $result;
    }

    /**
     * 获取服务类型列表
     */
    public function getServiceTypes(): array
    {
        $types = GameCompanion::getServiceTypeList();
        $result = [];
        foreach ($types as $value => $label) {
            $result[] = ['value' => $value, 'label' => $label];
        }
        return $result;
    }
}
