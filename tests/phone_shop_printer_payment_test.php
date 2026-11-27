<?php
declare(strict_types=1);

// 小票内容隔离回归：不连接数据库，不调用打印机，不触发订单或资金写入。
require dirname(__DIR__) . '/niucloud/addon/phone_shop/app/listener/printer/PrinterContentListener.php';

use addon\phone_shop\app\listener\printer\PrinterContentListener;

set_error_handler(static function (int $severity, string $message, string $file, int $line): void {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$count = 0;
$assert = static function (bool $condition, string $message) use (&$count): void {
    $count++;
    if (!$condition) throw new RuntimeException($message);
};
$listener = new PrinterContentListener();
$renderMethod = new ReflectionMethod($listener, 'getOrderInfoContent');
$renderMethod->setAccessible(true);
$template = [
    'order_item' => ['status' => 1, 'value' => ['pay_type']],
    'order_money' => ['status' => 0, 'fontSize' => '', 'fontWeight' => ''],
    'shop_remark' => ['status' => 0],
];
$render = static function (array $order, array $overrides = []) use ($listener, $renderMethod, $template): string {
    foreach ($overrides as $section => $values) {
        $template[$section] = array_replace($template[$section], $values);
    }
    return $renderMethod->invoke($listener, $template, $order);
};

$cases = [
    '线下待确认，无线上流水' => [['payment_mode' => 'offline_pending'], '线下付款（待确认）'],
    '线下收款，无线上流水' => [['payment_mode' => 'offline_cash'], '线下收款'],
    '线下挂账，无线上流水' => [['payment_mode' => 'offline_credit'], '线下挂账'],
    '挂账不得采用未完成的线上支付类型' => [['payment_mode' => 'offline_credit', 'pay' => ['type_name' => '微信支付']], '线下挂账'],
    '待确认不得采用未完成的线上支付类型' => [['payment_mode' => 'offline_pending', 'pay' => ['type_name' => '支付宝']], '线下付款（待确认）'],
    '正常线上微信支付' => [['payment_mode' => 'online', 'pay' => ['type_name' => '微信支付']], '微信支付'],
    '旧订单仍保留支付类型' => [['pay' => ['type_name' => '余额支付']], '余额支付'],
    '未知模式保留有效支付类型' => [['payment_mode' => 'other', 'pay' => ['type_name' => '支付宝']], '支付宝'],
    '无交易号不产生pay键' => [[], '未记录'],
    '有交易号但没有支付记录' => [['out_trade_no' => 'test-not-found', 'pay' => []], '未记录'],
    '支付记录为null' => [['pay' => null], '未记录'],
    '支付记录没有type_name' => [['pay' => ['type' => '']], '未记录'],
    '支付方式为空' => [['pay' => ['type_name' => '']], '未记录'],
];
foreach ($cases as $name => [$order, $expected]) {
    $before = $order;
    $assert(str_contains($render($order), '支付方式：' . $expected . "\n"), $name);
    $assert($order === $before, $name . '：仅渲染，不修改订单数据');
}

foreach (['offline_pending', 'offline_credit'] as $mode) {
    $content = $render(['payment_mode' => $mode, 'order_money' => '7680.00'], ['order_money' => ['status' => 1]]);
    $assert(str_contains($content, '订单金额：￥7680.00'), $mode . '明确打印订单金额');
    $assert(!str_contains($content, '实付金额'), $mode . '不能把未收金额标成已实付');
}
foreach (['offline_cash', 'online'] as $mode) {
    $content = $render(['payment_mode' => $mode, 'order_money' => '7680.00', 'pay' => ['type_name' => '微信支付']], ['order_money' => ['status' => 1]]);
    $assert(str_contains($content, '实付金额：￥7680.00'), $mode . '保持原有金额显示，不重新算价');
}
$assert(!str_contains($render([], ['order_item' => ['value' => []]]), '支付方式'), '模板未选择支付方式时不额外显示');

restore_error_handler();
echo "PASS phone_shop receipt payment rendering: {$count} assertions; no database or printer calls.\n";
