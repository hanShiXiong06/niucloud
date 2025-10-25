<?php
/**
 * 会员统计数据调试脚本
 * 独立运行版本
 */

// 定义应用目录
define('ROOT_PATH', __DIR__ . '/../../');

// 加载框架引导文件
require ROOT_PATH . 'vendor/autoload.php';

// 执行HTTP应用并响应
$http = (new think\App())->http;

$response = $http->run();

// 初始化数据库连接
use think\facade\Db;
use think\facade\Config;

echo "\n========== 会员统计数据调试 ==========\n\n";

try {
    // 1. 检查会员表
    echo "【1】检查会员表...\n";
    $tableExists = Db::query("SHOW TABLES LIKE 'ns_member'");
    if ($tableExists) {
        echo "✅ 会员表存在\n";
    } else {
        echo "❌ 会员表不存在\n";
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
        echo "  - username: " . ($member['username'] ?? 'NULL') . "\n";
        echo "  - nickname: " . ($member['nickname'] ?? 'NULL') . "\n";
        echo "  - mobile: {$member['mobile']}\n";
        echo "  - site_id: {$member['site_id']}\n";
        echo "  - create_time: " . ($member['create_time'] > 0 ? date('Y-m-d H:i:s', $member['create_time']) : '0') . " ({$member['create_time']})\n";
        echo "  - delete_time: {$member['delete_time']}\n";
        echo "  - status: " . ($member['status'] ?? 'NULL') . "\n";
        
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
        
        // 6. 测试统计查询
        echo "\n【6】测试统计查询（该会员所在站点）...\n";
        $siteId = $member['site_id'];
        
        // 总会员数
        $totalMembers = Db::name('member')
            ->where([
                ['site_id', '=', $siteId],
                ['delete_time', '=', 0],
                ['create_time', '>', 0]
            ])
            ->count();
        echo "  - 站点 {$siteId} 总会员数（排除已删除和未注册）: {$totalMembers}\n";
        
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
        
        // 7. 检查时间
        echo "\n【7】检查会员创建时间...\n";
        $memberCreateTime = $member['create_time'];
        $isToday = $memberCreateTime >= $todayStart && $memberCreateTime <= $todayEnd;
        echo "  - 会员创建时间: " . date('Y-m-d H:i:s', $memberCreateTime) . "\n";
        echo "  - 今天范围: " . date('Y-m-d H:i:s', $todayStart) . " ~ " . date('Y-m-d H:i:s', $todayEnd) . "\n";
        echo "  - 是否在今天: " . ($isToday ? '✅ 是' : '❌ 否') . "\n";
        
        // 8. 问题诊断
        echo "\n【8】问题诊断...\n";
        if ($member['delete_time'] > 0) {
            echo "  ⚠️  会员已被软删除！\n";
        }
        if ($member['create_time'] == 0) {
            echo "  ⚠️  会员未完成注册（create_time = 0）！\n";
        }
        if ($member['create_time'] > 0 && $member['delete_time'] == 0) {
            echo "  ✅ 会员状态正常\n";
            if (!$isToday) {
                echo "  💡 提示：会员不是今天注册的，如果查询今日统计会查不到\n";
            }
        }
        
    } else {
        echo "❌ 未找到手机号为 15100000000 的会员\n";
        
        echo "\n尝试查找最近的会员...\n";
        $members = Db::name('member')
            ->where('delete_time', 0)
            ->order('member_id', 'desc')
            ->limit(5)
            ->select()
            ->toArray();
        
        if ($members) {
            echo "找到以下会员（最新5个）:\n";
            foreach ($members as $m) {
                $mobile = $m['mobile'] ?? '无';
                $username = $m['username'] ?? '无';
                echo "  - ID:{$m['member_id']} | 手机:{$mobile} | 用户名:{$username} | 站点:{$m['site_id']}\n";
            }
        } else {
            echo "❌ 数据库中没有任何会员数据\n";
        }
    }
    
    echo "\n========== 调试完成 ==========\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ 执行出错: " . $e->getMessage() . "\n";
    echo "错误文件: " . $e->getFile() . "\n";
    echo "错误行号: " . $e->getLine() . "\n";
}

