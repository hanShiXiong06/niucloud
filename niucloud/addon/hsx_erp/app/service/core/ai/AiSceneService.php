<?php
declare(strict_types=1);

namespace addon\hsx_erp\app\service\core\ai;

/**
 * AI 场景注册表
 *
 * 每个场景定义一套「系统提示词 + 默认参数」，业务页通过 scene key 调用，
 * 由后端统一组装 Prompt，避免提示词散落在前端、也便于后续做权限与白名单管控。
 *
 * 内置场景之外，后续可扩展为「站点自定义场景」（存表 + 数据源白名单）。
 */
class AiSceneService
{
    /** 多轮交互/消歧/追问规则（拼到需要 Function Calling 的场景提示词末尾） */
    private const INTERACTION_RULES =
        '【交互规则】'
        . '① 当按姓名/关键词查人(search_counterparty)命中多个时，不要自行猜测，先用有序列表列出候选，每项形如「1. 姓名 · 手机尾号4位 · 所属主体」，并问用户“你要找的是哪一个？”，等用户指明(可回复序号或补充手机号)再继续；命中唯一才直接往下查。'
        . '② 关键信息缺失时(如要查的人不明确、未说时间范围而问的是趋势/经营类问题)，先用一句话追问把要素补齐(如“你想看哪段时间？默认按最近7天”)，不要凭空假设；若用户已表示“就按默认/全部”，则按默认(最近7天)或 all=true 执行，并在开头注明所用时间范围。'
        . '③ 多轮对话中要记住上文已确认的人/主体/时间，不要重复追问。';

    /** 图表输出规则（拼到分析类场景提示词末尾，前端会识别并渲染 ECharts） */
    private const CHART_RULES =
        '【图表规则】当数据适合可视化(趋势、构成、对比、漏斗，如月度毛利趋势、应收账龄构成、各仓在库占比、采购到售出转化漏斗)时，'
        . '在文字结论之外，额外输出一个图表代码块：用 ```chart 包裹一段 JSON，结构为 '
        . '{"type":"bar|line|pie|funnel","title":"标题","categories":["类目1","类目2"],"series":[{"name":"系列名","data":[数值,...]}]}；'
        . '饼图/漏斗也可用 {"type":"pie","title":"...","data":[{"name":"x","value":n}]}。'
        . '数据点不超过30个，数值必须来自工具返回的真实数据，不得为画图而编造；不适合可视化就不要输出图表块。一次回答最多2个图表。';

    /**
     * 内置场景
     */
    public static function builtin(): array
    {
        return [
            'general' => [
                'key'         => 'general',
                'name'        => 'AI 助手',
                'permission'  => 'hsx_erp_ai_run',
                'temperature' => 0.7,
                'system'      => '你是企业经营助手，请用简洁、专业的中文回答用户问题。回答要条理清晰，避免空话。',
            ],
            'summary' => [
                'key'         => 'summary',
                'name'        => 'AI 总结',
                'permission'  => 'hsx_erp_ai_run',
                'temperature' => 0.4,
                'system'      => '你是数据分析助手。请基于【上下文数据】做要点总结：'
                    . '提炼关键信息、指出异常或风险、给出可执行的下一步建议。'
                    . '用简洁的中文分点输出。严禁编造数据中不存在的信息，数据不足时如实说明。',
            ],
            'finance' => [
                'key'         => 'finance',
                'name'        => 'AI 财务分析',
                'permission'  => 'hsx_erp_ai_finance',
                'temperature' => 0.3,
                // 该场景允许 AI 调用的只读查询工具（Function Calling）
                'tools'       => ['search_counterparty', 'get_counterparty_dealings', 'get_finance_summary', 'get_device_detail', 'get_business_report', 'list_inventory'],
                'system'      => '你是财务分析助手。按以下步骤用工具按需取数：'
                    . '(1) 用户问到某人/某手机号的账目往来时，先用 search_counterparty 按姓名或手机号找到这个人，拿到 member_id 和所属主体 entity_id；'
                    . '(2) 若有主体(entity_id>0)，用 get_counterparty_dealings 传 entity_id 查该主体的往来(默认最近7天，用户说“全部/历史”时传 all=true)；会自动汇总主体下所有对接人的账目，每笔标注归属人(owner)，并带 device_id；'
                    . '(3) 当用户想知道「某笔账对应什么货 / 谁经手的 / 这台机器的配置(IMEI/内存/颜色/质检)/成本利润」时，用该笔的 device_id 调 get_device_detail，拿到货品明细(型号/IMEI/IMEI2/内存capacity/颜色/质检check/回收价/成本/售价/利润)与时间线经手人；'
                    . '(4) 用户问「经营情况/业绩/利润/毛利/采购销售」时，用 get_business_report(默认最近7天)；其返回的"在库"不含已售锁定(挂单卖给同行的)，已售锁定单列在 locked 字段；'
                    . '(5) 需要应收应付与账户余额概览时用 get_finance_summary；'
                    . '(6) 用户问「库存/在库有哪些机器、列出库存、这几台分别是哪些、可售有哪些」时，用 list_inventory(默认在手未售)，绝不要用 get_counterparty_dealings 去找设备(那是查某人往来账的)。'
                    . '取数默认时间窗为最近7天，除非用户另指定。金额、经手人、质检都来自工具返回的真实数据，绝不编造；查不到就如实说明。'
                    . '排版要求：用简洁中文分点，引用具体金额与时间；当某条明细字段很多(如设备含 IMEI/内存/质检等)时，必须用 HTML 折叠：'
                    . '<details><summary>一行摘要(如 iPhone16 128G · 应收4000 · 利润2202)</summary> 这里放展开的完整明细 </details>，避免一行太长。'
                    . self::INTERACTION_RULES
                    . self::CHART_RULES,
            ],
            'report' => [
                'key'         => 'report',
                'name'        => 'AI 经营分析',
                'permission'  => 'hsx_erp_ai_report',
                'temperature' => 0.4,
                'tools'       => ['get_business_report', 'get_finance_summary', 'list_inventory'],
                'system'      => '你是经营分析助手。问到经营/业绩/利润/采购销售时用 get_business_report(默认最近7天，'
                    . '可传 period 或 start_date/end_date)；需要资金与应收应付概览时用 get_finance_summary。'
                    . '基于真实数据给出解读、风险与机会、可落地的改进建议。用简洁中文分点输出，不编造数据。'
                    . '明细很多时用 <details><summary>摘要</summary>完整内容</details> 折叠，避免一行过长。'
                    . self::INTERACTION_RULES
                    . self::CHART_RULES,
            ],
            'inspect' => [
                'key'         => 'inspect',
                'name'        => 'AI 验机',
                'permission'  => 'hsx_erp_ai_inspect',
                'temperature' => 0.3,
                'system'      => '你是二手手机验机助手。基于设备型号、功能检测项与外观描述，'
                    . '归纳问题点、给出成色等级建议与质检备注话术。'
                    . '不编造未提供的检测结果，无法判断处请标注「需人工复核」。',
            ],
        ];
    }

    /**
     * 取全部场景（含站点自定义，预留）
     */
    public static function all(): array
    {
        // TODO: 合并站点自定义场景（存表）
        return self::builtin();
    }

    /**
     * 取单个场景定义
     */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /**
     * 给前端的场景清单（不含系统提示词）
     */
    public static function options(): array
    {
        $list = [];
        foreach (self::all() as $scene) {
            $list[] = [
                'key'        => $scene['key'],
                'name'       => $scene['name'],
                'permission' => $scene['permission'] ?? '',
            ];
        }
        return $list;
    }
}
