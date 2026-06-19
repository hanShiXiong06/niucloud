<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\admin;

use addon\hsx_erp\app\model\ai\HsxAiConversation;
use core\base\BaseAdminService;
use core\exception\CommonException;

/**
 * AI 对话存储（按站点 + 操作人隔离）
 */
class AiConversationService extends BaseAdminService
{
    /**
     * 历史列表（不含消息体）
     */
    public function lists(string $scene = ''): array
    {
        $query = HsxAiConversation::where([
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
        ]);
        if ($scene !== '') {
            $query->where('scene', '=', $scene);
        }
        return $query->field('id,scene,title,tokens,update_time,create_time')
            ->order('update_time desc')
            ->limit(100)
            ->select()->toArray();
    }

    /**
     * 对话详情（含消息体）
     */
    public function detail(int $id): array
    {
        $info = HsxAiConversation::where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
        ])->findOrEmpty();
        if ($info->isEmpty()) {
            throw new CommonException('对话不存在');
        }
        return $info->toArray();
    }

    /**
     * 新建/更新对话
     * @param array $data { id, scene, title, messages, tokens }
     * @return int conversation id
     */
    public function save(array $data): int
    {
        $messages = $data['messages'] ?? [];
        if (!is_array($messages)) {
            $messages = [];
        }
        $scene = (string)($data['scene'] ?? 'general');
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            $title = $this->guessTitle($messages);
        }
        $tokens = (int)($data['tokens'] ?? 0);
        $now = time();
        $id = (int)($data['id'] ?? 0);

        if ($id > 0) {
            $model = HsxAiConversation::where([
                ['id', '=', $id],
                ['site_id', '=', $this->site_id],
                ['uid', '=', $this->uid],
            ])->findOrEmpty();
            if ($model->isEmpty()) {
                throw new CommonException('对话不存在');
            }
            $model->save([
                'title'       => $title,
                'messages'    => $messages,
                'tokens'      => $tokens,
                'update_time' => $now,
            ]);
            return $id;
        }

        $model = HsxAiConversation::create([
            'site_id'     => $this->site_id,
            'uid'         => $this->uid,
            'scene'       => $scene,
            'title'       => $title,
            'messages'    => $messages,
            'tokens'      => $tokens,
            'create_time' => $now,
            'update_time' => $now,
        ]);
        return (int)$model->id;
    }

    /**
     * 删除对话
     */
    public function remove(int $id): bool
    {
        $model = HsxAiConversation::where([
            ['id', '=', $id],
            ['site_id', '=', $this->site_id],
            ['uid', '=', $this->uid],
        ])->findOrEmpty();
        if ($model->isEmpty()) {
            throw new CommonException('对话不存在');
        }
        $model->delete();
        return true;
    }

    protected function guessTitle(array $messages): string
    {
        foreach ($messages as $m) {
            if (($m['role'] ?? '') === 'user') {
                $t = trim((string)($m['content'] ?? ''));
                if ($t !== '') {
                    return mb_substr($t, 0, 20);
                }
            }
        }
        return '新对话';
    }
}
