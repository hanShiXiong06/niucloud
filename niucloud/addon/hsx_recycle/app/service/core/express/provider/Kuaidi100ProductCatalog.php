<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\core\express\provider;

/**
 * 上门取件官方参考目录。纯数据，不请求快递100，不表示本站账号已经开通。
 * 仅用于管理端选择及保存新配置；不得用于拦截既有订单的查询、回调或取消。
 */
class Kuaidi100ProductCatalog
{
    public const VERIFIED_AT = '2026-09-29';
    public const ONLINE_DOCUMENT = 'https://api.kuaidi100.com/document/603cb649a62a19500e19866b';
    public const OFFLINE_DOCUMENT = 'https://api.kuaidi100.com/document/cduan-ji-jian-jie-kou-wen-dang';

    public static function guide(): array
    {
        return [
            'provider' => 'kuaidi100',
            'verified_at' => self::VERIFIED_AT,
            'source_type' => 'official_reference_catalog',
            'notice' => '这是官方文档参考目录，不是本站账号已开通产品清单。保存及格式检查不代表账号权限、余额、线路运力或实际取件已经验证。',
            'sources' => [
                ['label' => '上门取件线上支付：承运商及业务类型参数表', 'url' => self::ONLINE_DOCUMENT],
                ['label' => '上门取件线下支付：承运商及默认业务类型', 'url' => self::OFFLINE_DOCUMENT],
                ['label' => '正式账号授权 Key 获取指引', 'url' => 'https://api.kuaidi100.com/document/chakankey'],
                ['label' => '线上支付开通、合作价格与调试指引', 'url' => 'https://api.kuaidi100.com/document/shang-jia-ji-jian-ce-shi'],
                ['label' => '沙箱测试账号、凭证与模拟回调教程', 'url' => 'https://api.kuaidi100.com/document/jijianceshipingtai'],
                ['label' => '免费沙箱测试与产品选型', 'url' => 'https://api.kuaidi100.com/document/ji-jian-select-test'],
                ['label' => '同品牌多通道 channelSw 获取说明', 'url' => 'https://api.kuaidi100.com/document/shang-jia-ji-jian-duo-tong-dao'],
                ['label' => '线上支付寄件价格调试工具', 'url' => 'https://api.kuaidi100.com/debug-tool/express-official-price/#debug'],
            ],
            'modes' => self::modes(),
            'setup_steps' => [
                ['title' => '开通本站自己的寄件账号', 'description' => '先注册并登录快递100企业管理后台，联系客户经理确认需要的上门取件线上或线下模式、承运商、业务类型及结算条件；拥有 Key 不等于已经开通寄件。', 'url' => 'https://api.kuaidi100.com/document/shang-jia-ji-jian-ce-shi'],
                ['title' => '获取正式凭证', 'description' => '正式 Key 在企业管理后台“我的信息 → 企业信息”查看；Secret 按寄件文档在企业管理后台获取。只填写本站账号，不要将密钥发给客户或写入小程序。', 'url' => 'https://api.kuaidi100.com/document/chakankey'],
                ['title' => '从目录选择承运商和产品', 'description' => '按已开通模式选择。线上只支持寄付；线下圆通、中通不支持到付。线下仅提供官方默认标准快递，特约产品未获得逐承运商官方清单，不开放手填。'],
                ['title' => '多通道按商务提供的值填写', 'description' => '同一快递公司只有一个合作通道时 channelSw 留空；多个合作通道时向快递100商务获取对应值，不能自行猜测。', 'url' => 'https://api.kuaidi100.com/document/shang-jia-ji-jian-duo-tong-dao'],
                ['title' => '先使用专属沙箱账号联调', 'description' => '联系商务或技术人员申请沙箱账号，在测试账号信息页获取测试 Key 和 Secret。测试环境不会创建真实快递订单，可模拟下单、回调和轨迹；不能用正式凭证代替测试凭证。', 'url' => 'https://api.kuaidi100.com/document/jijianceshipingtai'],
                ['title' => '配置回调并验收后开放', 'description' => '准备可公开访问的 HTTPS 回调地址。查价和格式检查不等于真实取件；确认权限、余额、收寄线路后，再经授权完成正式预约、回调和取件验收。'],
            ],
        ];
    }

    public static function modes(): array
    {
        $online = [
            self::carrier('shunfeng', '顺丰速运', ['顺丰标快', '顺丰特快']),
            self::carrier('jd', '京东物流', ['特惠送']),
            self::carrier('debangkuaidi', '德邦快递', ['标准快递', '德邦大件360', '精准卡航', '精准汽运']),
            self::carrier('jtexpress', '极兔速递', ['标准快递']),
            self::carrier('yuantong', '圆通快递', ['标准快递']),
            self::carrier('shentong', '申通快递', ['标准快递']),
            self::carrier('zhongtong', '中通快递', ['标准快递']),
            self::carrier('yunda', '韵达快递', ['标准快递']),
            self::carrier('ems', 'EMS', ['标准快递']),
            self::unsupported('kuaidi100zhixuan', '快递100智选', [], '官方要求处理 302 自动改派；本站当前业务固定承运商，暂不支持智选改派。'),
            self::unsupported('zhongtongkuaiyun', '中通快运', ['标准快递'], '官方要求对接支付状态同步 synPay；当前适配器尚未实现，不能启用。'),
            self::unsupported('yimidida', '壹米滴答', [], '官方要求对接支付状态同步 synPay，且业务类型参数表未明确列值；当前暂不支持。'),
        ];
        $offline = [];
        foreach ([
            'shunfeng' => '顺丰速运', 'jd' => '京东物流', 'debangkuaidi' => '德邦快递',
            'yuantong' => '圆通快递', 'zhongtong' => '中通快递', 'shunfengkuaiyun' => '顺丰快运',
            'sxjdfreight' => '顺心捷达', 'kuayue' => '跨越速运', 'ems' => 'EMS',
        ] as $code => $name) {
            $carrier = self::carrier($code, $name, []);
            $carrier['products'] = [[
                'value' => '标准快递', 'label' => '标准快递（官方默认）',
                'note' => '线下文档仅说明默认标准快递，未提供逐承运商特约产品清单；此选项不代表本站已开通。',
            ]];
            $carrier['payments'] = in_array($code, ['yuantong', 'zhongtong'], true) ? ['SHIPPER'] : ['SHIPPER', 'CONSIGNEE'];
            $offline[] = $carrier;
        }
        return [
            ['value' => 'online', 'label' => '上门取件线上支付',
                'note' => '产品名称来自线上文档业务类型参数表。运费由本站快递100账号结算，仅支持寄付。',
                'payments' => [['value' => 'SHIPPER', 'label' => '寄付']], 'carriers' => $online],
            ['value' => 'offline', 'label' => '上门取件线下支付',
                'note' => '承运商来自线下文档公司表；产品仅为官方默认标准快递，非逐承运商特约清单。圆通、中通不支持到付。',
                'payments' => [['value' => 'SHIPPER', 'label' => '寄付'], ['value' => 'CONSIGNEE', 'label' => '到付']], 'carriers' => $offline],
        ];
    }

    /** 只在保存新配置时调用；停用草稿允许保留未完成或旧的选择。 */
    public static function normalizeForSave(array $config, bool $strict): array
    {
        foreach (['mode', 'carrier_code', 'service_type'] as $field) {
            if (isset($config[$field]) && is_string($config[$field])) $config[$field] = trim($config[$field]);
        }
        $selectedMode = null;
        foreach (self::modes() as $mode) {
            if (($config['mode'] ?? '') === $mode['value']) $selectedMode = $mode;
        }
        if ($selectedMode === null) {
            if ($strict) throw new \InvalidArgumentException('请选择快递100已开通的线上支付或线下支付模式');
            return $config;
        }
        $selectedCarrier = null;
        foreach ($selectedMode['carriers'] as $carrier) {
            if (($config['carrier_code'] ?? '') === $carrier['code']) $selectedCarrier = $carrier;
        }
        if ($selectedCarrier === null) {
            if ($strict) throw new \InvalidArgumentException('所选快递公司不在当前支付模式的官方参考目录中，请重新选择');
            return $config;
        }
        // 公司展示名由受控目录规范，不能以客户端提交的名称冒充另一承运商。
        $config['carrier_name'] = $selectedCarrier['name'];
        if (!$strict) return $config;
        if (!empty($selectedCarrier['disabled'])) throw new \InvalidArgumentException($selectedCarrier['reason']);
        if (!in_array($config['service_type'] ?? '', array_column($selectedCarrier['products'], 'value'), true)) {
            throw new \InvalidArgumentException('请选择当前承运商的目录产品；不支持手填或跨支付模式使用产品名称');
        }
        return $config;
    }

    private static function carrier(string $code, string $name, array $products): array
    {
        return ['code' => $code, 'name' => $name, 'products' => array_map(static function (string $product): array {
            return ['value' => $product, 'label' => $product];
        }, $products)];
    }

    private static function unsupported(string $code, string $name, array $products, string $reason): array
    {
        return self::carrier($code, $name, $products) + ['disabled' => true, 'reason' => $reason];
    }
}
