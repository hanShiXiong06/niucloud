<?php
declare(strict_types=1);

namespace addon\phone_shop\app\listener\ai;

use addon\phone_shop\app\dict\goods\GoodsDict;
use addon\phone_shop\app\model\goods\Category;
use addon\phone_shop\app\model\goods\Goods;
use addon\phone_shop\app\model\goods\Label;
use addon\phone_shop\app\model\goods\Service;
use addon\phone_shop\app\service\api\goods\GoodsService;
use addon\phone_shop\app\service\core\goods\CoreGoodsDescriptionService;

final class AiMallBusinessContextRequested
{
    public function handle(array $event): array
    {
        if ((string)($event['scene'] ?? '') !== 'phone_shop.customer_assistant') return [];
        $siteId = (int)($event['site_id'] ?? 0);
        if (!AiIntegrationGuard::allowed($siteId)) {
            return ['consumer' => 'phone_shop', 'handled' => true, 'allowed' => false, 'locked' => true];
        }
        if ($siteId <= 0 || $siteId !== (int)request()->siteId()) {
            return ['consumer' => 'phone_shop', 'handled' => true, 'allowed' => false];
        }
        $prompt = trim((string)($event['prompt'] ?? ''));
        $currentPrompt = trim((string)($event['current_prompt'] ?? $prompt));
        // 明确的回收问价交给报价插件；未安装或未授权时由 AI 接入锁统一拒绝。
        $activeIntent = trim((string)($event['active_intent'] ?? ''));
        if ($this->isRecycleQuoteQuestion($currentPrompt)
            || ($activeIntent === 'recycle_daheng_quote' && !$this->isExplicitShoppingIntent($currentPrompt))
            || ($this->isRecycleQuoteQuestion($prompt) && $this->isRecycleQuoteFollowUp($currentPrompt))) return [];
        if ($this->isOutOfScopeQuestion($currentPrompt)) {
            return [
                'consumer' => 'phone_shop',
                'handled' => true,
                'allowed' => false,
                'suggestions' => ['预算 3000 元推荐什么手机', '想看苹果 256G 的机器', '推荐一台性价比高的备用机'],
            ];
        }
        if ($this->isCategoryQuestion($currentPrompt)) {
            return $this->categoryContext($currentPrompt);
        }
        $isShoppingIntent = $this->isShoppingQuestion($currentPrompt)
            || ($this->isShoppingFollowUp($currentPrompt) && $this->isShoppingQuestion($prompt));
        if (!$isShoppingIntent) {
            return $this->clarificationContext($currentPrompt);
        }

        $where = array_merge([
            'page' => 1,
            'limit' => 8,
            'in_stock' => 1,
            'order' => 'latest',
        ], $this->filters($prompt));
        $goodsService = new GoodsService();
        $memberInfo = $goodsService->getMemberInfo();
        $memberContext = $this->memberContext((array)($event['actor'] ?? []), $memberInfo);
        $page = $goodsService->getPage($where);
        $rows = array_slice((array)($page['data'] ?? []), 0, 8);
        $detailMap = $this->detailMap($siteId, array_map(static fn(array $row): int => (int)($row['goods_id'] ?? 0), $rows));
        $dictionaries = $this->dictionaries($siteId, $detailMap);
        $goods = [];
        foreach ($rows as $row) {
            $sku = (array)($row['goodsSku'] ?? $row['goods_sku'] ?? []);
            $detail = (array)($detailMap[(int)($row['goods_id'] ?? 0)] ?? []);
            $inspection = $this->publicInspection($detail['qc_report'] ?? $row['qc_report'] ?? []);
            $attributes = $this->publicAttributes($detail['attr_format'] ?? []);
            $labels = $this->namesForIds($detail['label_ids'] ?? [], $dictionaries['labels']);
            $categories = $this->namesForIds($detail['goods_category'] ?? [], $dictionaries['categories']);
            $services = $this->servicesForIds($detail['service_ids'] ?? [], $dictionaries['services']);
            $description = $this->plainText((string)($detail['goods_desc'] ?? ''), 800);
            $factCount = count($inspection['items']) + count($attributes) + count($labels) + count($services) + ($description !== '' ? 1 : 0);
            $pricing = $this->pricingFacts($row, $sku, $goodsService, $memberInfo, $memberContext);
            $goods[] = [
                'goods_id' => (int)($row['goods_id'] ?? 0),
                'name' => (string)($row['goods_name'] ?? ''),
                'subtitle' => (string)($row['sub_title'] ?? ''),
                'brand' => (string)($row['goods_brand']['brand_name'] ?? ''),
                'memory' => (string)($row['memory_group'] ?? ''),
                'condition' => (string)($row['condition_grade'] ?? ''),
                'image' => (string)($row['goods_cover_thumb_small'] ?? $row['goods_cover'] ?? ''),
                // price 保留为当前账号最终可购价，兼容既有资源卡；其余字段用于消除价格语义歧义。
                'price' => $pricing['current_price'],
                'current_price' => $pricing['current_price'],
                'current_price_type' => $pricing['current_price_type'],
                'current_price_label' => $pricing['current_price_label'],
                'regular_price' => $pricing['regular_price'],
                'member_price' => $pricing['member_price'],
                'saving_vs_regular' => $pricing['saving_vs_regular'],
                'member_level_name' => $pricing['member_level_name'],
                'market_reference_price' => $pricing['market_reference_price'],
                'market_price' => $pricing['market_reference_price'],
                'stock' => (int)($sku['stock'] ?? $row['stock'] ?? 0),
                'sku_name' => (string)($sku['sku_name'] ?? ''),
                'description' => $description,
                'attributes' => $attributes,
                'quality_inspection' => $inspection,
                'labels' => $labels,
                'categories' => $categories,
                'services' => $services,
                'fact_count' => $factCount,
                'facts_label' => $this->factsLabel($inspection, $attributes, $description),
                'detail_path' => '/addon/phone_shop/pages/goods/detail?goods_id=' . (int)($row['goods_id'] ?? 0),
            ];
        }
        return [
            'consumer' => 'phone_shop',
            'handled' => true,
            'allowed' => true,
            'context' => json_encode([
                'query' => $currentPrompt,
                'matched_count' => count($goods),
                'member_context' => $memberContext,
                'products' => $goods,
                'fact_note' => '商品均来自当前站点实时在售库存；current_price 是当前账号此刻实际可购价，regular_price 是普通售价，member_price 是当前会员等级价，market_reference_price 只是页面划线参考价，绝不能当作普通售价或成交价；quality_inspection 仅包含商城允许公开的质检事实。',
                'answer_requirements' => [
                    '先给购买结论，再说明价格、成色、公开质检结果和适合人群。',
                    '若 member_context.authenticated=true，必须先用“你当前是{customer_type_name}，本店给你的实际可购价是…”这个语义说明身份及 current_price，再单独说明 regular_price；两者不同须明确节省金额。',
                    '若 current_price_type=discount_price，必须称为当前活动价，并另行说明 member_price，不能把活动优惠错误归因于会员身份。',
                    'market_reference_price 只能称为划线参考价，不能称为正常售价、原价、市场成交价或最终价格。',
                    '质检异常必须优先、明确提醒，不得用营销描述掩盖异常。',
                    'description 是商家商品说明，不等于经过知识库校验的通用机型参数。',
                    '没有提供的芯片、上市时间、发热或新机实时价格必须明确说本站暂未维护，不得根据型号猜测。',
                    '不要机械复述字段，要把事实组织成普通顾客容易理解的建议。',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resources' => $goods,
            'suggestions' => ['预算再低一点', '优先看成色好的', '只看 256G'],
        ];
    }

    private function isShoppingQuestion(string $prompt): bool
    {
        if ($prompt === '') return false;
        return (bool)preg_match('/手机|二手|商品|机器|选机|推荐|预算|价格|多少钱|库存|有货|成色|内存|容量|配置|购买|想买|想要|下单|保修|电池|屏幕|质检|异常|适合|合适|优缺点|值不值|怎么样|看看|帮我挑|送人|老人|学生|孩子|游戏|拍照|备用|这台|那台|这个|那个|上一台|第一台|苹果|iphone|华为|荣耀|小米|红米|oppo|vivo|三星|一加|魅族|平板|手表|耳机|只看|低一点|高一点|便宜点|贵一点|换一批|(?:64|128|256|512)\s*g|(?:1|2)\s*t/iu', $prompt);
    }

    private function isRecycleQuoteQuestion(string $prompt): bool
    {
        return (bool)preg_match('/回收|报价|收多少钱|收价|量级|行情|走势|趋势|卖.*多少|今天收|历史价|天前/iu', $prompt);
    }

    private function isExplicitShoppingIntent(string $prompt): bool
    {
        return (bool)preg_match('/我要买|想买|购买|下单|在售|库存|推荐.*(?:买|手机)|帮我选|选机|卖给我/iu', $prompt);
    }

    private function isRecycleQuoteFollowUp(string $prompt): bool
    {
        return (bool)preg_match('/还有|全部|所有|都给我|其他|另外|花机|内报|靓机|充新|小花|大花|等级|成色|备注|扣价|这个|该型号|再查|继续/iu', $prompt);
    }

    private function isCategoryQuestion(string $prompt): bool
    {
        return (bool)preg_match('/分类|品类|目录|都有什么(?:品牌|牌子|系列)|有哪些(?:品牌|系列)|什么系列/iu', $prompt);
    }

    private function categoryContext(string $prompt): array
    {
        $categories = (new AiMallCategoryQuery())->query((int)request()->siteId(), '', 120);
        $suggestions = array_slice(array_values(array_unique(array_filter(array_map(
            static fn(array $row): string => (int)($row['level'] ?? 0) <= 2 ? trim((string)($row['name'] ?? '')) : '',
            $categories
        )))), 0, 4);
        return [
            'consumer' => 'phone_shop',
            'handled' => true,
            'allowed' => true,
            'context' => json_encode([
                'query' => $prompt,
                'intent_state' => 'category_browse',
                'categories' => $categories,
                'answer_requirements' => [
                    '先按层级用简洁名称回答本站已开放的分类或系列，不得编造未提供的分类。',
                    '分类较多时先展示最相关的一级、二级分类，再追问用户想看哪个，不要一次堆满所有节点。',
                    '后续查商品时使用 category_id 作为稳定关联，不要只靠分类名称猜测。',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resources' => [],
            'suggestions' => $suggestions !== [] ? $suggestions : ['看看手机', '看看平板', '看看手表'],
        ];
    }

    private function isOutOfScopeQuestion(string $prompt): bool
    {
        return (bool)preg_match('/忽略(?:以上|之前)|系统提示|角色扮演|越狱|prompt|写代码|编程|天气|新闻|政治|股票|彩票|医疗|疾病|法律|作文|翻译|电影|歌曲|历史人物/iu', $prompt);
    }

    private function isShoppingFollowUp(string $prompt): bool
    {
        return (bool)preg_match('/还有|别的|其他|换一(?:个|台|批)|再来|继续|再看看|低一点|高一点|便宜点|贵一点|第[\x{4e00}-\x{9fa5}\d]+(?:个|台)?|上一(?:个|台)?|详细说|展开说|就它|这台|那台/iu', $prompt);
    }

    private function clarificationContext(string $prompt): array
    {
        return [
            'consumer' => 'phone_shop',
            'handled' => true,
            'allowed' => true,
            'context' => json_encode([
                'query' => $prompt,
                'intent_state' => 'needs_clarification',
                'likely_intent' => '用户正在商城选机助手入口咨询，优先推断为买手机或挑选在售商品，但当前条件不足以准确查库存。',
                'answer_requirements' => [
                    '不要回复“我只负责本站商品挑选和购买咨询”。',
                    '先自然回应用户，并说明你对其需求的合理理解。',
                    '一次只追问一个最关键条件，优先顺序为用途、预算、品牌、内存、成色。',
                    '给出两到三个容易点击或直接回答的选项，不要一次罗列整张问卷。',
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'resources' => [],
            'suggestions' => ['预算 3000 元左右', '想看苹果手机', '需要一台备用机'],
        ];
    }

    private function memberContext(array $actor, array $memberInfo): array
    {
        $authenticated = (int)($actor['id'] ?? 0) > 0;
        $levelName = trim((string)($memberInfo['memberLevelData']['level_name'] ?? ''));
        return [
            'authenticated' => $authenticated,
            'has_member_level' => $authenticated && (int)($memberInfo['member_level'] ?? 0) > 0,
            'level_name' => $levelName !== '' ? $levelName : ($authenticated ? '普通会员' : '游客'),
            'customer_type' => !$authenticated ? 'guest' : ($levelName === '同行' ? 'peer' : 'retail'),
            'customer_type_name' => !$authenticated ? '游客' : ($levelName === '同行' ? '同行会员' : ($levelName !== '' ? $levelName : '普通会员')),
        ];
    }

    private function pricingFacts(array $row, array $sku, GoodsService $goodsService, array $memberInfo, array $memberContext): array
    {
        $regularPrice = round((float)($sku['price'] ?? 0), 2);
        $currentPrice = round((float)($sku['show_price'] ?? $regularPrice), 2);
        $currentType = (string)($sku['show_type'] ?? GoodsDict::ORIGINAL_PRICE);
        $memberPrice = null;
        if (!empty($memberContext['authenticated'])) {
            $memberPrice = round((float)$goodsService->getMemberPrice(
                $memberInfo,
                (string)($row['member_discount'] ?? ''),
                $sku['member_price'] ?? '',
                $regularPrice
            ), 2);
        }
        $label = match ($currentType) {
            GoodsDict::MEMBER_PRICE => (string)$memberContext['level_name'] . '价',
            GoodsDict::DISCOUNT_PRICE => '当前活动价',
            default => '普通售价',
        };
        return [
            'current_price' => $currentPrice,
            'current_price_type' => $currentType,
            'current_price_label' => $label,
            'regular_price' => $regularPrice,
            'member_price' => $memberPrice,
            'saving_vs_regular' => $memberPrice === null ? 0.0 : max(0, round($regularPrice - $memberPrice, 2)),
            'member_level_name' => !empty($memberContext['authenticated']) ? (string)$memberContext['level_name'] : '',
            'market_reference_price' => round((float)($sku['market_price'] ?? 0), 2),
        ];
    }

    private function filters(string $prompt): array
    {
        $filters = [];
        $brands = ['苹果', 'iPhone', '华为', '荣耀', '小米', '红米', 'OPPO', 'vivo', '三星', '一加', '魅族'];
        foreach ($brands as $brand) {
            if (stripos($prompt, $brand) !== false) { $filters['keyword'] = $brand; break; }
        }
        if (preg_match('/(?:预算|价格|价位)?\s*(\d{3,6})\s*(?:元|块)?\s*(以内|以下|左右|上下)?/u', $prompt, $matches)) {
            $price = (int)$matches[1];
            $range = (string)($matches[2] ?? '');
            if (in_array($range, ['以内', '以下'], true)) {
                $filters['end_price'] = $price;
            } elseif ($price >= 300) {
                $filters['start_price'] = round($price * 0.75);
                $filters['end_price'] = round($price * 1.15);
            }
        }
        if (preg_match('/\b(64|128|256|512)\s*g\b|\b(1|2)\s*t\b/iu', $prompt, $memory)) {
            $filters['memory_group'] = strtoupper(str_replace(' ', '', $memory[0]));
        }
        return $filters;
    }

    /** 一次补齐列表接口未返回的公开商品详情，避免逐件调用详情接口产生浏览量副作用。 */
    private function detailMap(int $siteId, array $goodsIds): array
    {
        $goodsIds = array_values(array_unique(array_filter(array_map('intval', $goodsIds))));
        if ($goodsIds === []) return [];
        $rows = (new Goods())->where([
            ['site_id', '=', $siteId],
            ['status', '=', 1],
            ['delete_time', '=', 0],
        ])->whereIn('goods_id', $goodsIds)
            ->field('goods_id,goods_desc,attr_format,qc_report,label_ids,service_ids,goods_category')
            ->select()->toArray();
        $map = [];
        $descriptionService = new CoreGoodsDescriptionService();
        foreach ($rows as $row) {
            $row['goods_desc'] = $descriptionService->sanitize((string)($row['goods_desc'] ?? ''));
            $map[(int)$row['goods_id']] = $row;
        }
        return $map;
    }

    private function dictionaries(int $siteId, array $details): array
    {
        $categoryIds = $labelIds = $serviceIds = [];
        foreach ($details as $detail) {
            $categoryIds = array_merge($categoryIds, $this->idList($detail['goods_category'] ?? []));
            $labelIds = array_merge($labelIds, $this->idList($detail['label_ids'] ?? []));
            $serviceIds = array_merge($serviceIds, $this->idList($detail['service_ids'] ?? []));
        }
        $categoryIds = array_values(array_unique($categoryIds));
        $labelIds = array_values(array_unique($labelIds));
        $serviceIds = array_values(array_unique($serviceIds));
        $categories = [];
        if ($categoryIds !== []) {
            $rows = (new Category())->where('site_id', $siteId)->whereIn('category_id', $categoryIds)
                ->field('category_id,category_name,category_full_name')->select()->toArray();
            foreach ($rows as $row) {
                $categories[(int)$row['category_id']] = trim((string)($row['category_full_name'] ?? ''))
                    ?: trim((string)($row['category_name'] ?? ''));
            }
        }
        $labels = $labelIds === [] ? [] : (new Label())->where([['site_id', '=', $siteId], ['status', '=', 1]])->whereIn('label_id', $labelIds)->column('label_name', 'label_id');
        $services = [];
        if ($serviceIds !== []) {
            foreach ((new Service())->where('site_id', $siteId)->whereIn('service_id', $serviceIds)->field('service_id,service_name,desc')->select()->toArray() as $service) {
                $services[(int)$service['service_id']] = ['name' => (string)$service['service_name'], 'description' => $this->plainText((string)$service['desc'], 160)];
            }
        }
        return ['categories' => $categories, 'labels' => $labels, 'services' => $services];
    }

    private function idList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : explode(',', $value);
        }
        if (!is_array($value)) return [];
        return array_values(array_filter(array_map('intval', $value), static fn(int $id): bool => $id > 0));
    }

    private function namesForIds($ids, array $map): array
    {
        $result = [];
        foreach ($this->idList($ids) as $id) {
            $name = trim((string)($map[$id] ?? ''));
            if ($name !== '') $result[] = $name;
        }
        return array_values(array_unique($result));
    }

    private function servicesForIds($ids, array $map): array
    {
        $result = [];
        foreach ($this->idList($ids) as $id) {
            if (!empty($map[$id])) $result[] = $map[$id];
        }
        return $result;
    }

    /** 只输出商城公开质检项，明确过滤串号、成本、客户等敏感事实。 */
    private function publicInspection($value): array
    {
        $data = $this->jsonArray($value);
        if (array_key_exists('enabled', $data) && empty($data['enabled'])) return ['title' => '', 'summary' => '', 'abnormal_count' => 0, 'items' => []];
        $source = !empty($data['result_items']) ? (array)$data['result_items'] : (array)($data['items'] ?? []);
        $items = [];
        $abnormalCount = 0;
        foreach ($source as $item) {
            if (!is_array($item) || !empty($item['hidden']) || (isset($item['visible']) && empty($item['visible']))) continue;
            $name = trim((string)($item['field_name'] ?? $item['key'] ?? $item['name'] ?? ''));
            if ($name === '' || $this->sensitive($name)) continue;
            $raw = $item['value'] ?? $item['labels'] ?? $item['values'] ?? $item['text'] ?? '';
            if (is_array($raw)) $raw = implode('、', array_map('strval', $raw));
            $text = $this->plainText((string)$raw, 160);
            if ($text === '') continue;
            $severity = trim((string)($item['severity'] ?? 'normal')) ?: 'normal';
            if (!in_array($severity, ['normal', 'abnormal', 'general'], true)) $severity = 'normal';
            if ($severity !== 'normal') $abnormalCount++;
            $items[] = ['name' => $name, 'value' => $text, 'severity' => $severity];
            if (count($items) >= 20) break;
        }
        $summary = implode('；', array_map(static fn(array $item): string => $item['name'] . '：' . $item['value'], array_slice($items, 0, 6)));
        return [
            'title' => trim((string)($data['title'] ?? '公开质检报告')),
            'summary' => $summary,
            'abnormal_count' => $abnormalCount,
            'items' => $items,
        ];
    }

    private function publicAttributes($value): array
    {
        $data = $this->jsonArray($value);
        $items = [];
        foreach ($data as $key => $item) {
            if (is_array($item)) {
                $name = trim((string)($item['attr_name'] ?? $item['name'] ?? $item['spec_name'] ?? ''));
                $raw = $item['attr_value_name'] ?? $item['value_name'] ?? $item['value'] ?? '';
            } else {
                $name = is_string($key) ? trim($key) : '';
                $raw = $item;
            }
            if ($name === '' || $this->sensitive($name) || is_array($raw)) continue;
            $text = $this->plainText((string)$raw, 160);
            if ($text !== '') $items[] = ['name' => $name, 'value' => $text];
            if (count($items) >= 24) break;
        }
        return $items;
    }

    private function jsonArray($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode(trim((string)$value), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function sensitive(string $name): bool
    {
        return (bool)preg_match('/imei|meid|sn|串号|序列号|手机号|电话|客户|供应商|成本|采购价|利润|银行卡|支付宝|微信号/iu', $name);
    }

    private function plainText(string $value, int $limit): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim((string)preg_replace('/\s+/u', ' ', $value));
        return mb_substr($value, 0, $limit);
    }

    private function factsLabel(array $inspection, array $attributes, string $description): string
    {
        $parts = [];
        if ($inspection['items'] !== []) $parts[] = '质检 ' . count($inspection['items']) . ' 项';
        if ((int)$inspection['abnormal_count'] > 0) $parts[] = '异常 ' . (int)$inspection['abnormal_count'] . ' 项';
        if ($attributes !== []) $parts[] = '参数 ' . count($attributes) . ' 项';
        if ($description !== '') $parts[] = '商品说明';
        return implode(' · ', $parts);
    }
}
