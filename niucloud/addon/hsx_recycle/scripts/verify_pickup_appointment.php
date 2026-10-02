<?php
declare(strict_types=1);

namespace core\exception { class CommonException extends \RuntimeException {} }
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    require dirname(__DIR__) . '/app/service/core/express/PickupAppointmentPolicy.php';
    use addon\hsx_recycle\app\service\core\express\PickupAppointmentPolicy as Policy;
    $checks = 0;
    function check(bool $ok, string $message): void {
        if (!$ok) throw new \RuntimeException('FAIL ' . $message);
        $GLOBALS['checks']++;
    }
    $zone = new \DateTimeZone('Asia/Shanghai');
    $cases = [
        '2026-10-02 05:44:54' => '2026-10-02 09:00-18:00',
        '2026-10-02 08:45:01' => '2026-10-02 09:30-18:00',
        '2026-10-02 12:15:00' => '2026-10-02 13:00-18:00',
        '2026-10-02 15:59:59' => '2026-10-02 16:30-18:00',
        '2026-10-02 16:00:00' => '2026-10-03 09:00-18:00',
        '2026-10-31 23:59:00' => '2026-11-01 09:00-18:00',
        '2026-12-31 23:59:59' => '2027-01-01 09:00-18:00',
    ];
    foreach ($cases as $clock => $expected) {
        $now = new \DateTimeImmutable($clock, $zone);
        $result = Policy::resolve([], $now);
        check($result['pickup_time'] === $expected, 'future window at ' . $clock);
        check(Policy::validate($expected, [], $now) === $expected, 'displayed window accepted unchanged');
        check(Policy::validate('', [], $now) === $expected, 'missing time gets server window at ' . $clock);
        check(Policy::validate(" \t\n", [], $now) === $expected, 'blank time gets server window');
        check(str_contains($result['pickup_time_text'], substr($expected, 11)), 'human display equals submitted time');
    }
    check(Policy::resolve([], new \DateTimeImmutable('2026-10-01T21:44:54Z'))['pickup_time'] === $cases['2026-10-02 05:44:54'], 'server timezone cannot shift Chinese pickup date');
    check(Policy::resolve(['start' => '14:00', 'end' => '15:00', 'cutoff' => '13:00'], new \DateTimeImmutable('2026-10-02 12:00:00', $zone))['pickup_time'] === '2026-10-02 14:00-15:00', 'custom store window applied');
    check(Policy::validate('', [], new \DateTimeImmutable('2026-10-01T21:44:54Z')) === '2026-10-02 09:00-18:00', 'missing time uses Beijing date regardless of server timezone');
    check(Policy::validate('', ['start' => '14:00', 'end' => '15:00', 'cutoff' => '13:00'], new \DateTimeImmutable('2026-10-02 12:00:00', $zone)) === '2026-10-02 14:00-15:00', 'missing time respects configured store window');
    $now = new \DateTimeImmutable('2026-10-02 12:15:00', $zone);
    foreach (['2026-10-02 08:00-18:00', '2026-10-02 12:00-18:00', '2026-10-02 13:00-23:00', '2026-10-06 09:00-18:00', '2026-10-02 15:00-15:10', '2026-10-32 09:00-18:00'] as $value) {
        try { Policy::validate($value, [], $now); throw new \RuntimeException('unexpected acceptance'); }
        catch (\core\exception\CommonException $e) { check(str_contains($e->getMessage(), '尚未提交订单'), 'stale/forged time rejected before order ' . $value); }
    }
    foreach (['2026-10-02 15:00-17:00', '2026-10-03 09:00-11:00', '2026-10-04 11:00-13:00', '2026-10-05 13:00-15:00'] as $value) {
        check(Policy::validate($value, [], $now) === $value, 'customer can select today/tomorrow/next two days ' . $value);
        $selected = Policy::resolve([], $now, $value);
        check($selected['pickup_time'] === $value && !$selected['pickup_time_changed'], 'preflight preserves chosen interval');
        check(in_array($value, array_merge(...array_map(static fn($day) => array_column($day['slots'], 'value'), $selected['pickup_time_options'])), true), 'chosen interval stays visible');
    }
    $selected = Policy::resolve([], $now, '2026-10-02 09:00-11:00');
    check($selected['pickup_time_changed'] && $selected['pickup_time'] === '2026-10-02 13:00-18:00', 'expired selection marked changed for customer reconfirmation');
    check(array_column($selected['pickup_time_options'], 'label') === ['今天', '明天', '后天', '大后天'], 'four named dates returned');
    check(array_column(Policy::resolve([], new \DateTimeImmutable('2026-10-02 17:00:00', $zone))['pickup_time_options'], 'label') === ['明天', '后天', '大后天'], 'today hidden after cutoff');
    $lastWindow = Policy::resolve(['cutoff' => '17:30'], new \DateTimeImmutable('2026-10-02 17:01:00', $zone), '2026-10-02 17:30-18:00');
    check(!$lastWindow['pickup_time_changed'] && $lastWindow['pickup_time_text'] !== '', 'still-future previously selected final window stays visible even when new default moved to tomorrow');
    foreach (['2026-10-02 05:00:00', '2026-10-02 12:15:00', '2026-10-02 15:59:59', '2026-10-31 23:59:00', '2026-12-31 23:59:59'] as $clock) {
        $clock = new \DateTimeImmutable($clock, $zone);
        foreach (Policy::resolve([], $clock)['pickup_time_options'] as $day) foreach ($day['slots'] as $slot) {
            check(Policy::validate($slot['value'], [], $clock) === $slot['value'], 'every offered slot passes server validation');
        }
    }
    foreach ([['start' => '18:00', 'end' => '09:00'], ['cutoff' => '18:00'], ['start' => '25:00'], ['start' => []]] as $invalid) {
        try { Policy::normalize($invalid, true); throw new \RuntimeException('invalid settings accepted'); }
        catch (\core\exception\CommonException $e) { check(true, 'invalid administrator setting rejected'); }
        check(Policy::normalize($invalid) === Policy::DEFAULTS, 'read-only fallback does not crash');
    }
    $openLate = ['start' => '09:00', 'end' => '18:00', 'cutoff' => '17:30'];
    foreach (['16:00:00' => '16:00-17:00', '16:39:00' => '16:39-17:00', '16:44:59' => '16:44-17:00',
        '16:45:00' => '17:00-18:00', '16:59:59' => '17:00-18:00', '17:01:00' => '17:01-18:00'] as $time => $range) {
        $clock = new \DateTimeImmutable('2026-10-02 ' . $time, $zone);
        $result = Policy::resolve($openLate, $clock, '', true);
        check($result['pickup_time'] === 'immediate', 'immediate default at ' . $time);
        check($result['pickup_time_range'] === '2026-10-02 ' . $range, 'requested minute boundary at ' . $time);
        check(Policy::validate('immediate', $openLate, $clock, true) === $result['pickup_time_range'], 'submit recalculates immediate range');
        check(Policy::validate('', $openLate, $clock, true) === $result['pickup_time_range'], 'missing time also gets nearest range');
        check($result['pickup_time_options'][0]['slots'][0]['value'] === 'immediate', 'immediate is first selectable option');
        check(str_contains($result['pickup_time_text'], $range), 'customer sees actual interval');
    }
    $clock = new \DateTimeImmutable('2026-10-02 16:39:00', $zone);
    check(Policy::resolve([], $clock, '', true)['pickup_time'] === '2026-10-03 09:00-18:00', 'default 16:00 cutoff is never bypassed');
    check(Policy::resolve($openLate, $clock, '2026-10-03 09:00-11:00', true)['pickup_time'] === '2026-10-03 09:00-11:00', 'manual future choice is not replaced by immediate');
    check(Policy::resolve($openLate, new \DateTimeImmutable('2026-10-02 17:30:00', $zone), 'immediate', true)['pickup_time_changed'], 'crossing cutoff requires confirmation instead of silently booking tomorrow');
    check(Policy::resolve($openLate, new \DateTimeImmutable('2026-10-02 08:00:00', $zone), '', true)['pickup_time'] !== 'immediate', 'no immediate option before opening');
    foreach ([false, true] as $capability) {
        try {
            Policy::validate('immediate', [], $clock, $capability);
            throw new \RuntimeException('immediate accepted outside capability/hours');
        } catch (\core\exception\CommonException $e) { check(true, 'immediate cannot bypass capability or cutoff'); }
    }
    check(Policy::validate('immediate', $openLate, new \DateTimeImmutable('2026-10-02T08:39:00Z'), true) === '2026-10-02 16:39-17:00', 'immediate calculation uses Beijing timezone');
    for ($minute = 0; $minute < 1440; $minute++) {
        $clock = (new \DateTimeImmutable('2026-10-02 00:00:00', $zone))->modify('+' . $minute . ' minutes');
        $result = Policy::resolve($openLate, $clock, '', true);
        check(Policy::validate($result['pickup_time'], $openLate, $clock, true) === $result['pickup_time_range'], 'all minutes default accepted ' . $minute);
    }
    echo "PASS {$checks} appointment checks; no DB/network/real booking\n";
}
