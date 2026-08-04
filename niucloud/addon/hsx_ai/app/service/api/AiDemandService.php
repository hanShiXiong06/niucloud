<?php
declare(strict_types=1);

namespace addon\hsx_ai\app\service\api;

use addon\hsx_ai\app\model\AiConversation;
use addon\hsx_ai\app\model\AiDemand;
use addon\hsx_ai\app\model\AiMessage;
use core\base\BaseApiService;

final class AiDemandService extends BaseApiService
{
    public function capture(?AiConversation $conversation, ?AiMessage $message, string $prompt): void
    {
        if (!$conversation || !$message) return;
        $entities = $this->entities($prompt);
        [$budgetMin, $budgetMax] = $this->budget($prompt);
        $requirements = [];
        foreach (['备用机', '游戏', '拍照', '续航', '小屏', '大屏', '轻薄', '成色', '保修', '国行'] as $keyword) {
            if (mb_stripos($prompt, $keyword) !== false) $requirements[] = $keyword;
        }
        $intent = preg_match('/(?:买|购买|推荐|找一台|想看|选机|在售)/u', $prompt) ? 'purchase_consultation' : 'product_consultation';
        $demand = AiDemand::where([
            ['site_id', '=', (int)$this->site_id],
            ['conversation_id', '=', (int)$conversation->id],
            ['status', '=', 'open'],
        ])->findOrEmpty();
        $oldEntities = $demand->isEmpty() ? [] : (array)$demand->entities_json;
        $oldRequirements = $demand->isEmpty() ? [] : (array)$demand->requirements_json;
        $sourceIds = $demand->isEmpty() ? [] : (array)$demand->source_message_ids_json;
        $sourceIds[] = (int)$message->id;
        $data = [
            'site_id' => (int)$this->site_id,
            'conversation_id' => (int)$conversation->id,
            'actor_type' => 'member',
            'actor_id' => (int)$this->member_id,
            'intent' => $intent,
            'customer_type' => 'unknown',
            'entities_json' => array_values(array_unique(array_merge($oldEntities, $entities))),
            'budget_min' => $budgetMin > 0 ? $budgetMin : (float)($demand->budget_min ?? 0),
            'budget_max' => $budgetMax > 0 ? $budgetMax : (float)($demand->budget_max ?? 0),
            'requirements_json' => array_values(array_unique(array_merge($oldRequirements, $requirements))),
            'urgency' => preg_match('/(?:今天|马上|急|尽快)/u', $prompt) ? 'high' : 'normal',
            'lead_score' => min(100, 20 + ($budgetMax > 0 ? 25 : 0) + ($entities !== [] ? 25 : 0) + ($requirements !== [] ? 10 : 0)),
            'confidence' => $entities !== [] || $budgetMax > 0 ? 0.75 : 0.45,
            'source_message_ids_json' => array_values(array_unique(array_map('intval', $sourceIds))),
            'update_at' => time(),
        ];
        if ($demand->isEmpty()) {
            $data['status'] = 'open';
            $data['create_at'] = time();
            AiDemand::create($data);
        } else {
            $demand->save($data);
        }
        $conversation->save(['last_intent' => $intent, 'update_at' => time()]);
    }

    private function entities(string $prompt): array
    {
        $result = [];
        foreach (['苹果', 'iPhone', '华为', '荣耀', '小米', 'OPPO', 'vivo', '三星', '一加'] as $brand) {
            if (mb_stripos($prompt, $brand) !== false) $result[] = 'brand:' . $brand;
        }
        if (preg_match_all('/\b(?:64|128|256|512)\s*[GT]?(?:B)?\b|\b[124]\s*T(?:B)?\b/iu', $prompt, $matches)) {
            foreach ($matches[0] as $memory) $result[] = 'memory:' . strtoupper(preg_replace('/\s+/', '', $memory));
        }
        if (preg_match('/(?:iPhone\s*)?(?<!\d)([89]|1\d|20)(?!\d)\s*(Pro\s*Max|Pro|Plus|Air)?/iu', $prompt, $model)) {
            $result[] = 'model:' . trim(($model[1] ?? '') . ' ' . ($model[2] ?? ''));
        }
        return array_values(array_unique($result));
    }

    private function budget(string $prompt): array
    {
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:到|至|[-~])\s*(\d+(?:\.\d+)?)\s*(千|万|元)?/u', $prompt, $match)) {
            $unit = (string)($match[3] ?? '元');
            $factor = $unit === '万' ? 10000 : ($unit === '千' ? 1000 : 1);
            return [round((float)$match[1] * $factor, 2), round((float)$match[2] * $factor, 2)];
        }
        if (preg_match('/(?:预算|价位|以内|左右|不超过|最多)[^\d]{0,6}(\d+(?:\.\d+)?)\s*(千|万|元)?|(?:预算|价位)?\s*(\d+(?:\.\d+)?)\s*(千|万|元)(?:以内|左右|以下)?/u', $prompt, $match)) {
            $firstNumber = (string)($match[1] ?? '');
            $firstUnit = (string)($match[2] ?? '');
            $number = (float)($firstNumber !== '' ? $firstNumber : ($match[3] ?? 0));
            $unit = $firstUnit !== '' ? $firstUnit : (string)($match[4] ?? '元');
            $value = $number * ($unit === '万' ? 10000 : ($unit === '千' ? 1000 : 1));
            return [0.0, round($value, 2)];
        }
        return [0.0, 0.0];
    }
}
