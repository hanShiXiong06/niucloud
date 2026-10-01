<?php
declare(strict_types=1);
namespace addon\hsx_express\app\service\core;

/** 顺丰国内单包裹协议；只负责映射与证据校验，不修改业务状态。 */
final class SfProtocol
{
    private static function text($value, string $label, int $max, bool $required = true): string
    {
        if (!is_scalar($value) && $value !== null) throw new ProviderException($label . '格式不正确，本次未提交', false);
        $value = trim((string)$value);
        if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) throw new ProviderException($label . '为空、过长或含控制字符，本次未提交', false);
        return $value;
    }
    public static function createOrder(array $config, array $payload, string $merchantOrderNo, string $scene = 'waybill'): array
    {
        if (!in_array($scene, ['waybill','pickup'], true)) throw new ProviderException('顺丰业务场景不支持', false);
        $orderId = self::text($merchantOrderNo, '原包裹编号', 64);
        $product = (string)($config['product_code'] ?? '');
        if (!in_array($product, array_column(SfConfigService::products(), 'value'), true)) throw new ProviderException('请选择已支持的顺丰产品', false);
        $pay = (int)($config['pay_method'] ?? 0);
        $card = self::text($config['monthly_card'] ?? '', '月结卡号', 20, false);
        if (!in_array($pay,[1,2,3],true) || ($pay === 2 && $card !== '') || ($pay === 3 && $card === '')) throw new ProviderException('顺丰运费付款方式与月结卡号不匹配', false);
        $weight = $payload['weight'] ?? 0;
        if (!is_numeric($weight) || !is_finite((float)$weight) || (float)$weight < 0.001 || (float)$weight > 1000) throw new ProviderException('请填写正确的包裹重量（0.001 至 1000 千克）', false);
        $count = $payload['count'] ?? 1;
        if (!is_numeric($count) || (float)$count !== 1.0) throw new ProviderException('当前顺丰直连接口按单包裹取号，请拆分包裹处理', false);
        $contacts = [];
        foreach (['sender' => 1, 'receiver' => 2] as $side => $type) {
            $input = $payload[$side] ?? [];
            if (!is_array($input)) throw new ProviderException('寄收件人资料格式不正确', false);
            $label = $type === 1 ? '寄件人' : '收件人';
            $row = ['contactType' => $type, 'contact' => self::text($input['name'] ?? '', $label.'姓名', 100),
                'mobile' => self::text($input['mobile'] ?? '', $label.'电话', 20), 'country' => 'CN',
                'address' => self::text($input['address'] ?? '', $label.'完整地址', 200)];
            foreach (['province' => ['province',30], 'city' => ['city',100], 'district' => ['county',30]] as $key => [$field,$max]) {
                $value = self::text($input[$key] ?? '', $label.$key, $max, false);
                if ($value !== '') $row[$field] = $value;
            }
            $contacts[] = $row;
        }
        $data = ['language' => 'zh-CN', 'orderId' => $orderId, 'expressTypeId' => (int)$product, 'payMethod' => $pay,
            'parcelQty' => 1, 'totalWeight' => round((float)$weight,3), 'isDocall' => $scene === 'pickup' ? 1 : 0,
            'isReturnRoutelabel' => 1, 'isUnifiedWaybillNo' => 1, 'isGenWaybillNo' => 1,
            'cargoDetails' => [['name' => self::text($payload['cargo'] ?? '', '托寄物名称', 100), 'count' => 1, 'unit' => '件', 'weight' => round((float)$weight,3)]],
            'contactInfoList' => $contacts];
        if ($card !== '') $data['monthlyCard'] = $card;
        $businessNo = self::text($payload['business_no'] ?? '', '业务单号', 100, false);
        if ($businessNo !== '') $data['custReferenceNo'] = $businessNo;
        if ($scene === 'pickup') {
            $start = self::appointment((string)($payload['pickup_start_at'] ?? ''));
            $end = self::appointment((string)($payload['pickup_end_at'] ?? ''));
            if ($start->getTimestamp() <= time() || $end <= $start) throw new ProviderException('请选择未来的有效上门取件时段，本次未叫件', false);
            $data['sendStartTm'] = $start->format('Y-m-d H:i:s');
            $data['extraInfoList'] = [['attrName' => 'pickupAppointEndTime', 'attrVal' => $end->format('Y-m-d H:i:s')]];
        }
        return $data;
    }
    private static function appointment(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, new \DateTimeZone('Asia/Shanghai'));
        if (!$date || $date->format('Y-m-d H:i:s') !== $value) throw new ProviderException('预约时段格式不正确，请重新选择取件时段', false);
        return $date;
    }
    public static function searchOrder(string $merchantOrderNo): array
    {
        return ['searchType' => '1', 'orderId' => self::text($merchantOrderNo,'原包裹编号',64), 'language' => 'zh-CN'];
    }
    public static function cancelOrder(string $merchantOrderNo, string $waybillNo = '', string $reason = ''): array
    {
        $data = ['dealType' => 2, 'language' => 'zh-CN', 'orderId' => self::text($merchantOrderNo,'原包裹编号',64), 'waybillNoInfoList' => []];
        if ($waybillNo !== '') $data['waybillNoInfoList'] = [['waybillType' => 1, 'waybillNo' => self::text($waybillNo,'原运单号',30)]];
        // 原因留在本地审计，不把订单/财务说明误传为顺丰业务字段。
        return $data;
    }
    private static function object($value): array
    {
        if (is_string($value)) {
            try { $value = json_decode($value,true,64,JSON_THROW_ON_ERROR); } catch (\Throwable $e) { return []; }
        }
        return is_array($value) ? $value : [];
    }
    private static function succeeded(array $result): bool
    {
        return in_array($result['success'] ?? null, [true, 'true'], true) && (string)($result['errorCode'] ?? '') === 'S0000';
    }
    public static function normalizeOrder(array $result): array
    {
        $data = self::object($result['msgData'] ?? []);
        $waybills = [];
        foreach ((array)($data['waybillNoInfoList'] ?? []) as $row) {
            if (is_array($row) && (string)($row['waybillType'] ?? '') === '1' && preg_match('/^[A-Za-z0-9]{8,30}$/D', (string)($row['waybillNo'] ?? ''))) $waybills[] = (string)$row['waybillNo'];
        }
        $waybills = array_values(array_unique($waybills));
        $success = self::succeeded($result);
        $filter = (string)($data['filterResult'] ?? '');
        $code = (string)($result['errorCode'] ?? '');
        // 只有文档明确的参数/地址拒绝才允许重新申请；系统异常、重复订单、查无结果均须核实原单。
        $reject = ['1010','1011','1012','1014','1015','1016','1023','6126','8096','8114','8119','8256','8057'];
        return ['success' => $success, 'confirmed' => $success && $filter === '2' && count($waybills) === 1 && !empty($data['orderId']),
            'order_no' => is_scalar($data['orderId'] ?? null) ? (string)$data['orderId'] : '',
            'waybill_no' => count($waybills) === 1 ? $waybills[0] : '', 'filter_result' => $filter, 'cancelled' => false,
            'definitive_rejected' => !$success && in_array($code, $reject, true) && !$waybills,
            'code' => $code, 'message' => $success ? (['1' => '顺丰需人工确认收派条件', '2' => '顺丰已返回可收派结果；不代表已揽收', '3' => '顺丰暂不支持当前地址收派', '4' => '顺丰暂不能确定收派条件'][$filter] ?? '顺丰未返回明确收派结果') : self::safeMessage($result)];
    }
    public static function normalizeCancel(array $result, string $expectedOrderId = ''): array
    {
        $data = self::object($result['msgData'] ?? []);
        $order = is_scalar($data['orderId'] ?? null) ? (string)$data['orderId'] : '';
        $confirmed = self::succeeded($result) && (string)($data['resStatus'] ?? '') === '2' && $order !== '';
        if ($expectedOrderId !== '') $confirmed = $confirmed && hash_equals($expectedOrderId, $order);
        // 官方取消接口明确：8037已消单、8253已取消。只在调用方持有原单上下文时接纳；
        // “已确认或已消单”(8019)有歧义，不能放行。响应身份冲突也不得覆盖。
        if ($expectedOrderId !== '' && in_array((string)($result['errorCode'] ?? ''), ['8037','8253'], true)
            && ($order === '' || hash_equals($expectedOrderId,$order))) $confirmed = true;
        return ['confirmed' => $confirmed, 'order_no' => $order, 'message' => $confirmed ? '顺丰已确认取消原订单' : self::safeMessage($result, '尚未取得对应原单的取消确认')];
    }
    public static function cancelConfirmed(array $result, string $expectedOrderId = ''): bool
    {
        return self::normalizeCancel($result, $expectedOrderId)['confirmed'];
    }
    public static function pdfRequest(array $config, array $task, array $originalPayload = []): array
    {
        return ['templateCode' => self::text($config['template_code'] ?? '', '顺丰PDF模板编码',100), 'version' => '2.0',
            'fileType' => 'pdf', 'sync' => true, 'documents' => [['masterWaybillNo' => self::text($task['waybill_no'] ?? '', '原运单号',30)]]];
    }
    public static function normalizePdf(array $result, string $expectedWaybill = ''): array
    {
        // 云打印2.0成功示例只有success=true，不含订单接口的S0000。
        if (!in_array($result['success'] ?? null, [true, 'true'], true) || !in_array((string)($result['errorCode'] ?? ''), ['', 'S0000'], true)) {
            $message = self::safeMessage($result, '顺丰尚未生成PDF，请核对模板及接口权限');
            // 云打印沙箱已验证的无错误码响应，仅识别完整固定句式，绝不回显账号或模板值。
            if (($result['success'] ?? null) === false && (string)($result['errorCode'] ?? '') === ''
                && is_string($result['errorMessage'] ?? null)
                && preg_match('/\AtemplateCode:[A-Za-z0-9_-]{1,100} is not matched the clientCode:[A-Za-z0-9_-]{1,64}\z/', $result['errorMessage'])) {
                $message = '顺丰PDF模板与当前顾客编码不匹配，请到当前应用的云打印接口详情复制已分配模板编码，再重新获取原单PDF';
            }
            throw new ProviderException($message, false);
        }
        $obj = self::object($result['obj'] ?? []);
        if (($obj['fileType'] ?? '') !== 'pdf') throw new ProviderException('顺丰返回的不是PDF文件，已阻止下载', false);
        $files = $obj['files'] ?? [];
        if (!is_array($files) || count($files) !== 1 || !is_array($files[0])) throw new ProviderException('顺丰PDF文件数量异常，请核实原单文件', false);
        $file = $files[0];
        $waybill = (string)($file['waybillNo'] ?? '');
        if ($expectedWaybill === '' || !hash_equals($expectedWaybill,$waybill)) throw new ProviderException('顺丰PDF运单号与原单不一致，已阻止下载', false);
        $url = (string)($file['url'] ?? '');
        $parts = parse_url($url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'eos-scp-core-shenzhen-futian1-oss.sf-express.com'
            || !empty($parts['user']) || !empty($parts['pass']) || (isset($parts['port']) && (int)$parts['port'] !== 443) || !empty($parts['fragment'])) throw new ProviderException('顺丰PDF下载域名不在已验证名单中，请联系平台核实官方新域名', false);
        $token = $file['token'] ?? '';
        if (!is_string($token) || $token === '' || strlen($token) > 4096 || preg_match('/[\r\n]/', $token)) throw new ProviderException('顺丰PDF下载凭据不完整，请重新获取原单PDF', false);
        return ['url' => $url, 'token' => $token, 'waybill_no' => $waybill, 'expires_at' => time() + 23 * 3600];
    }
    public static function safeMessage($result, string $fallback = '顺丰未返回明确结果，请核实原单'): string
    {
        $code = is_array($result) ? (string)($result['errorCode'] ?? '') : '';
        $known = ['1010' => '寄件地址未填写', '1011' => '寄件联系人未填写', '1012' => '寄件电话未填写',
            '1014' => '收件地址未填写', '1015' => '收件联系人未填写', '1016' => '收件电话未填写',
            '1023' => '托寄物品名未填写', '6126' => '月结卡号不合法，应为10位数字',
            '8096' => '预约超出营业时间，无法上门收件，请重新选择营业时段', '8114' => '月结卡号没有下单权限，请联系顺丰客户经理开通',
            '8119' => '月结卡号不存在或已失效，请核对账号', '8256' => '所选产品不支持到付或寄付现结，请核对产品约定与月结付款方式',
            '8057' => '当前服务产品不支持此请求，请核实账号权限', '8016' => '原订单编号已存在，请查询原单，不要重新下单',
            '8018' => '暂未查询到原订单，不能据此认定未下单，请核实', '6150' => '暂未查询到原订单，不能据此认定未下单，请核实',
            '8037' => '顺丰提示已消单，请核实原订单身份', '8253' => '顺丰提示已取消，请核实原订单身份',
            '8019' => '顺丰提示已确认或已消单，取消结果仍需核实', '8017' => '订单与运单不匹配，已阻止修改',
            '20052' => '月结卡号与原订单不匹配，未完成操作'];
        // 不直接展示第三方原文：可能回显密钥、下载token、地址等敏感内容。
        $label = $known[$code] ?? $fallback;
        return preg_match('/^[A-Za-z0-9_-]{1,32}$/D',$code) ? $label . '（顺丰代码 '.$code.'）' : $label;
    }
}
