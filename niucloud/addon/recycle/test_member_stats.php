<?php
/**
 * 会员统计数据调试脚本
 * 用于排查会员数据不显示的问题
 * 
 * 使用方法：
 * php think run addon/recycle/test_member_stats.php
 */

// 引入框架
require_once __DIR__ . '/../../think';

use think\facade\Db;

echo "\n========== 会员统计数据调试 ==========\n\n";

// 1. 检查会员表是否存在
echo "【1】检查会员表...\n";
try {
    $tableExists = Db::query("SHOW TABLES LIKE 'ns_member'");
    if ($tableExists) {
        echo "✅ 会员表存在\n";
    } else {
        echo "❌ 会员表不存在\n";
        exit;
    }
} catch (\Exception $e) {
    echo "❌ 查询失败: " . $e->getMessage() . "\n";
    exit;
}

// 2. 查询会员总数
echo "\n【2】查询会员总数...\n";
$totalCount = Db::name('member')->count();
echo "数据库中会员总数（包含已删除）: {$totalCount}\n";

// 3. 查询未删除的会员
echo "\n【3】查询未删除的会员...\n";
$activeCount = Db::name('member')->where('delete_time', 0)->count();
echo "未删除的会员数: {$activeCount}\n";

// 4. 查询指定手机号的会员
echo "\n【4】查询指定手机号会员 (15100000000)...\n";
$member = Db::name('member')
    ->where('mobile', '15100000000')
    ->find();

if ($member) {
    echo "✅ 找到会员:\n";
    echo "  - member_id: {$member['member_id']}\n";
    echo "  - username: {$member['username']}\n";
    echo "  - nickname: {$member['nickname']}\n";
    echo "  - mobile: {$member['mobile']}\n";
    echo "  - site_id: {$member['site_id']}\n";
    echo "  - create_time: " . date('Y-m-d H:i:s', $member['create_time']) . " ({$member['create_time']})\n";
    echo "  - delete_time: {$member['delete_time']}\n";
    
    // 5. 按站点统计
    echo "\n【5】按站点统计会员...\n";
    $siteStats = Db::name('member')
        ->field('site_id, COUNT(*) as count')
        ->where('delete_time', 0)
        ->group('site_id')
        ->select()
        ->toArray();
    
    foreach ($siteStats as $stat) {
        echo "  - 站点 {$stat['site_id']}: {$stat['count']} 个会员\n";
    }
    
    // 6. 查询会员详细信息
    echo "\n【6】该会员详细信息...\n";
    $fields = [
        'member_id', 'username', 'nickname', 'mobile', 'site_id', 
        'create_time', 'delete_time', 'login_time', 'pid', 
        'register_channel', 'register_type', 'status'
    ];
    
    foreach ($fields as $field) {
        $value = $member[$field] ?? 'NULL';
        if ($field == 'create_time' || $field == 'login_time') {
            $value = $value > 0 ? date('Y-m-d H:i:s', $value) . " ({$value})" : '0';
        }
        echo "  - {$field}: {$value}\n";
    }
    
    // 7. 测试统计查询
    echo "\n【7】测试统计查询（该会员所在站点）...\n";
    $siteId = $member['site_id'];
    
    // 总会员数
    $totalMembers = Db::name('member')
        ->where([
            ['site_id', '=', $siteId],
            ['delete_time', '=', 0],
            ['create_time', '>', 0]
        ])
        ->count();
    echo "  - 站点 {$siteId} 总会员数: {$totalMembers}\n";
    
    // 今日新增
    $todayStart = strtotime(date('Y-m-d 00:00:00'));
    $todayEnd = strtotime(date('Y-m-d 23:59:59'));
    $todayNew = Db::name('member')
        ->where([
            ['site_id', '=', $siteId],
            ['delete_time', '=', 0],
            ['create_time', '>', 0],
            ['create_time', 'between', [$todayStart, $todayEnd]]
        ])
        ->count();
    echo "  - 今日新增: {$todayNew}\n";
    
    // 8. 检查时间范围问题
    echo "\n【8】检查会员创建时间是否在今天...\n";
    $memberCreateTime = $member['create_time'];
    $isToday = $memberCreateTime >= $todayStart && $memberCreateTime <= $todayEnd;
    echo "  - 会员创建时间: " . date('Y-m-d H:i:s', $memberCreateTime) . "\n";
    echo "  - 今天开始时间: " . date('Y-m-d H:i:s', $todayStart) . "\n";
    echo "  - 今天结束时间: " . date('Y-m-d H:i:s', $todayEnd) . "\n";
    echo "  - 是否在今天: " . ($isToday ? '✅ 是' : '❌ 否') . "\n";
    
} else {
    echo "❌ 未找到手机号为 15100000000 的会员\n";
    echo "\n尝试查找其他会员...\n";
    $members = Db::name('member')
        ->where('delete_time', 0)
        ->limit(5)
        ->select()
        ->toArray();
    
    if ($members) {
        echo "找到以下会员:\n";
        foreach ($members as $m) {
            echo "  - {$m['member_id']}: {$m['mobile']} ({$m['username']})\n";
        }
    } else {
        echo "❌ 数据库中没有任何会员数据\n";
    }
}

// 9. SQL 查询测试
echo "\n【9】完整SQL查询测试...\n";
echo "执行SQL: \n";
$sql = "SELECT COUNT(*) as count FROM ns_member WHERE delete_time = 0 AND create_time > 0";
echo $sql . "\n";
$result = Db::query($sql);
echo "结果: " . ($result[0]['count'] ?? 0) . " 个会员\n";

echo "\n========== 调试完成 ==========\n\n";

echo "【问题排查建议】\n";
echo "1. 如果会员数为0，请检查会员是否已创建\n";
echo "2. 如果 delete_time 不为0，说明会员已被软删除\n";
echo "3. 如果 create_time 为0，说明会员未完成注册\n";
echo "4. 如果 site_id 不匹配，请确认当前登录的站点ID\n";
echo "5. 检查前端传递的时间参数是否正确\n";
echo "\n";

