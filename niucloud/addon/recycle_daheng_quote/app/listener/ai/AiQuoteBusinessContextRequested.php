<?php
declare(strict_types=1);

namespace addon\recycle_daheng_quote\app\listener\ai;

use addon\recycle_daheng_quote\app\service\core\quotation\AiQuoteReadService;

final class AiQuoteBusinessContextRequested
{
    public function handle(array $event): array
    {
        if ((string)($event['scene'] ?? '') !== 'phone_shop.customer_assistant') return [];
        $prompt = trim((string)($event['current_prompt'] ?? ''));
        $contextPrompt = trim((string)($event['prompt'] ?? $prompt));
        $activeIntent = trim((string)($event['active_intent'] ?? ''));
        $continuesQuote = $activeIntent === 'recycle_daheng_quote' && !$this->isExplicitShoppingIntent($prompt);
        if (!$this->isQuoteIntent($prompt) && !$continuesQuote
            && !($this->isQuoteIntent($contextPrompt) && $this->isFollowUp($prompt))) return [];
        $siteId = (int)($event['site_id'] ?? 0);
        if (!AiIntegrationGuard::allowed($siteId)) {
            return ['consumer' => 'recycle_daheng_quote', 'handled' => true, 'allowed' => false, 'locked' => true];
        }
        if ($siteId <= 0 || $siteId !== (int)request()->siteId()) {
            return ['consumer' => 'recycle_daheng_quote', 'handled' => true, 'allowed' => false];
        }

        $facts = (new AiQuoteReadService())->resolve($siteId, $prompt, $contextPrompt);
        $context = [
            'query' => $prompt,
            'domain' => 'apple_recycle_quote',
            'facts' => $facts,
            'answer_requirements' => [
                '价格、日期、等级和扣价说明只能使用 facts 中的数据，不得凭模型知识补充或换算。',
                '若 status=needs_clarification，一次只追问缺少的型号或内存。',
                '若 status=no_snapshot，明确说明该日期没有快照，不得插值或拿其他日期冒充。',
                '若 mode=quote_aggregate，必须说明共命中 source_count 张报价单，并逐张整理 datasets，不得只回答第一张。',
                '用户在卖机器，不得向用户推荐购买商城商品。',
                '历史报价没有同期扣价快照时，必须提示 adjustment_notice，不能套用当前扣价规则。',
                '趋势固定展示 start_date 到 end_date 的连续七个自然日，缺失日期保留为空。',
            ],
        ];
        return [
            'consumer' => 'recycle_daheng_quote',
            'handled' => true,
            'allowed' => true,
            'context' => json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'blocks' => $this->blocks($facts),
            'suggestions' => (array)($facts['suggestions'] ?? []),
        ];
    }

    private function isQuoteIntent(string $prompt): bool
    {
        return $prompt !== '' && (bool)preg_match('/回收|报价|收多少钱|收价|量级|行情|走势|趋势|卖.*多少|今天收|历史价|天前/iu', $prompt);
    }

    private function isExplicitShoppingIntent(string $prompt): bool
    {
        return (bool)preg_match('/我要买|想买|购买|下单|在售|库存|推荐.*(?:买|手机)|帮我选|选机|卖给我/iu', $prompt);
    }

    private function isFollowUp(string $prompt): bool
    {
        return $prompt !== '' && (bool)preg_match('/还有|全部|所有|都给我|其他|另外|花机|内报|靓机|充新|小花|大花|等级|成色|备注|扣价|这个|该型号|再查|继续/iu', $prompt);
    }

    private function blocks(array $facts): array
    {
        if ((string)($facts['status'] ?? '') !== 'success') return [];
        return [[
            'type' => match ((string)($facts['mode'] ?? '')) {
                'trend' => 'price_trend',
                'quote_aggregate' => 'quote_aggregate',
                default => 'quote_card',
            },
            'source_plugin' => 'recycle_daheng_quote',
            'data' => $facts,
        ]];
    }
}
