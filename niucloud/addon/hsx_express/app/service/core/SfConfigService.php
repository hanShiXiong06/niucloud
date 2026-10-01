<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

use addon\hsx_express\app\support\Cipher;
use app\service\core\sys\CoreConfigService;
use core\exception\CommonException;

/** 两种业务独立启用、独立保存；沙箱与生产凭据不能隐式混用。 */
final class SfConfigService
{
    public const MASK = '******';
    public static function defaults(): array
    {
        return ['enabled' => 0, 'environment' => 'sandbox', 'client_code' => '', 'check_word' => '',
            'monthly_card' => '', 'product_code' => '2', 'pay_method' => 1, 'template_code' => '',
            'use_ack' => 0, 'callback_base_url' => ''];
    }
    private static function key(string $scene): string
    {
        if (!in_array($scene, ['waybill', 'pickup'], true)) throw new CommonException('请选择电子面单或上门取件业务');
        return 'HSX_EXPRESS_SF_' . strtoupper($scene);
    }
    public function get(int $siteId, string $scene = 'waybill', bool $masked = false): array
    {
        $raw = (new CoreConfigService())->getConfigValue($siteId, self::key($scene));
        $data = array_replace(self::defaults(), is_array($raw) ? array_intersect_key($raw, self::defaults()) : []);
        $data['check_word'] = Cipher::decrypt((string)($raw['check_word_cipher'] ?? ''));
        $data['provider'] = 'sf_direct';
        $data['scene'] = $scene;
        if ($masked) {
            $data['readiness'] = self::readiness($data, $scene);
            $data['has_check_word'] = $data['check_word'] !== '';
            $data['check_word'] = $data['has_check_word'] ? self::MASK : '';
            $data['options'] = self::options($scene);
        }
        return $data;
    }
    public function save(int $siteId, string $scene, array $input): array
    {
        $key = self::key($scene);
        if ($siteId <= 0) throw new CommonException('请在客户站点配置本店顺丰账号');
        foreach (array_intersect_key($input, self::defaults()) as $value) {
            if (!is_scalar($value) && $value !== null) throw new CommonException('配置字段必须为文本或开关');
            if (is_string($value) && mb_strlen($value) > 500) throw new CommonException('配置字段过长，请核对');
        }
        $old = $this->get($siteId, $scene);
        $data = array_replace(self::defaults(), array_intersect_key($old, self::defaults()), array_intersect_key($input, self::defaults()));
        foreach ($data as &$value) if (is_string($value)) $value = trim($value);
        unset($value);
        if (!in_array($data['environment'], ['sandbox', 'production'], true)) throw new CommonException('请选择沙箱或生产环境');
        if (!in_array((string)$data['product_code'], array_column(self::products(), 'value'), true)) throw new CommonException('请选择当前支持的顺丰快递产品');
        if (!in_array((string)$data['pay_method'], ['1', '2', '3'], true)) throw new CommonException('请选择寄付、到付或第三方付');
        $data['pay_method'] = (int)$data['pay_method'];
        $identityChanged = $old['environment'] !== $data['environment'] || $old['client_code'] !== $data['client_code'];
        $newSecret = trim((string)($input['check_word'] ?? ''));
        $clear = in_array('check_word', (array)($input['clear_secrets'] ?? []), true);
        $data['check_word'] = $clear ? '' : (($newSecret !== '' && $newSecret !== self::MASK) ? $newSecret : ($identityChanged ? '' : $old['check_word']));
        $data['enabled'] = (int)!empty($data['enabled']);
        $data['use_ack'] = (int)!empty($data['use_ack']);
        if ($identityChanged) { $data['enabled'] = 0; $data['use_ack'] = 0; }
        if ($data['client_code'] !== '' && (!preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $data['client_code']))) throw new CommonException('顾客编码格式不正确，请复制顺丰后台的编码');
        if ($data['monthly_card'] !== '' && !preg_match('/^[0-9]{10}$/D', $data['monthly_card'])) throw new CommonException('月结卡号应为10位数字；现结请留空');
        if ($data['pay_method'] === 2 && $data['monthly_card'] !== '') throw new CommonException('到付不能同时提交寄方月结卡号，请清空月结卡号后保存');
        if ($data['pay_method'] === 3 && $data['monthly_card'] === '' && $data['enabled']) throw new CommonException('第三方付需要已获授权的第三方月结卡号');
        $data['callback_base_url'] = rtrim($data['callback_base_url'], '/');
        if ($data['callback_base_url'] !== '' && !ConfigService::validBaseUrl($data['callback_base_url'])) throw new CommonException('公网根地址须为不带路径、参数或账号的 HTTPS 域名');
        if ($data['enabled'] && !self::readiness($data, $scene)['ready']) throw new CommonException('暂不能启用：' . implode('；', self::readiness($data, $scene)['missing']));
        $data['check_word_cipher'] = Cipher::encrypt($data['check_word']);
        unset($data['check_word']);
        (new CoreConfigService())->setConfig($siteId, $key, $data);
        $result = $this->get($siteId, $scene, true);
        if ($identityChanged) $result['save_notice'] = '账号或环境已变更，当前业务已关闭；请核对新环境校验码并重新确认开通后启用。';
        return $result;
    }
    public static function readiness(array $config, string $scene = 'waybill'): array
    {
        self::key($scene);
        $checks = [];
        $add = static function(string $key, string $label, bool $passed, string $message) use (&$checks) { $checks[] = compact('key', 'label', 'passed', 'message'); };
        $add('cipher', '部署密钥', Cipher::available(), '请平台管理员配置 app.auth_key 保护本站物流凭据');
        $add('environment', '接口环境', in_array($config['environment'] ?? '', ['sandbox', 'production'], true), '请选择沙箱或生产环境');
        $add('client_code', '顺丰顾客编码', (bool)preg_match('/^[A-Za-z0-9_-]{1,64}$/D', (string)($config['client_code'] ?? '')), '填写顺丰应用的顾客编码 / partnerID');
        $secret = (string)($config['check_word'] ?? '');
        $add('check_word', '当前环境校验码', $secret !== '' && $secret !== self::MASK, '填写对应沙箱或生产环境的校验码，不是应用名称');
        $add('product', '快递产品', in_array((string)($config['product_code'] ?? ''), array_column(self::products(), 'value'), true), '请选择与顺丰客户经理约定开通的产品');
        $pay = (int)($config['pay_method'] ?? 0); $card = (string)($config['monthly_card'] ?? '');
        $add('payment', '运费付款方式', in_array($pay, [1,2,3], true) && ($pay !== 2 || $card === '') && ($pay !== 3 || $card !== ''), '到付不填月结号；第三方付须填写获授权的第三方月结号');
        $add('monthly_card', '月结卡号（现结可空）', $card === '' || (bool)preg_match('/^[0-9]{10}$/D', $card), '月结卡号应为10位数字，且已绑定当前应用；现结请留空');
        if ($scene === 'waybill') $add('template', '顺丰 PDF 模板', trim((string)($config['template_code'] ?? '')) !== '', '从顺丰云打印面单2.0获取模板编码，不是快递100模板ID');
        $add('confirmation', '开通确认', !empty($config['use_ack']), '确认当前账号具备对应产品、付款方式及接口权限');
        $missing = array_column(array_filter($checks, static fn($row) => !$row['passed']), 'message');
        return ['ready' => !$missing, 'checks' => $checks, 'missing' => $missing, 'external_verified' => false,
            'message' => '仅检查本地配置，不下单、不叫件、不扣费，不代表服务地区、额度或实际打印已验证。'];
    }
    public static function products(): array
    {
        return [['value' => '1', 'label' => '顺丰特快'], ['value' => '2', 'label' => '顺丰标快']];
    }
    public static function options(string $scene = 'waybill'): array
    {
        self::key($scene);
        return ['scene' => $scene, 'products' => self::products(),
            'environments' => [['value' => 'sandbox', 'label' => '沙箱测试（不能真实发货）'], ['value' => 'production', 'label' => '生产环境（真实业务）']],
            'pay_methods' => [['value' => 1, 'label' => '寄方付'], ['value' => 2, 'label' => '收方付（到付）'], ['value' => 3, 'label' => '第三方付（需授权）']],
            'docs' => ['console' => 'https://qiao.sf-express.com/console', 'api' => 'https://qiao.sf-express.com/Api?category=1&apiClassify=1',
                'products' => 'https://open.sf-express.com/developSupport/734349?activeIndex=324604', 'pdf' => 'https://qiao.sf-express.com/Api?category=6&apiClassify=2']];
    }
}
