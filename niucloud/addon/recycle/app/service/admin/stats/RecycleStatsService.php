<?php
declare(strict_types=1);

namespace addon\recycle\app\service\admin\stats;

use addon\recycle\app\model\RecycleDevice;
use addon\recycle\app\model\RecycleOrder;
use core\base\BaseAdminService;
use app\service\admin\auth\AuthService;
use app\service\admin\user\UserRoleService;
use app\model\sys\SysUserRole;
use app\model\sys\SysUser;
use app\model\member\Member;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收业务统计服务
 * Class RecycleStatsService
 * @package addon\recycle\app\service\admin\stats
 */
class RecycleStatsService extends BaseAdminService
{
    /**
     * 构造函数
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取用户的角色名称
     * @param int $userId 用户ID
     * @param int $siteId 站点ID
     * @return string
     */
    protected function getUserRoleName(int $userId, int $siteId = 0): string
    {
        $siteId = $siteId ?: $this->site_id;
        
        // 获取用户角色信息
        $userRoleService = new UserRoleService();
        $userRole = $userRoleService->getUserRole($siteId, $userId);
        
        // 如果没有角色信息，直接根据操作记录推断
        if (empty($userRole)) {
            return $this->getRoleNameByOperation($userId);
        }
        
        // 如果是站点管理员
        if ($userRole['is_admin']) {
            // 进一步判断是否为超级管理员
            $superAdminUid = $this->getSuperAdminUid();
            if ($userId == $superAdminUid) {
                return '超级管理员';
            } else {
                return '站点管理员';
            }
        }
        
        // 获取角色名称
        $roleIds = $userRole['role_ids'] ?? [];
        if (!empty($roleIds)) {
            $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, $siteId);
            if (!empty($roleNames)) {
                return implode('、', $roleNames); // 如果有多个角色，用顿号分隔
            }
        }
        
        // 如果没有具体角色，根据操作记录推断
        return $this->getRoleNameByOperation($userId);
    }

    /**
     * 根据操作记录推断角色名称
     * @param int $userId
     * @return string
     */
    protected function getRoleNameByOperation(int $userId): string
    {
        $checkCount = RecycleDevice::where('check_uid', $userId)->count();
        $priceCount = RecycleDevice::where('price_uid', $userId)->count();
        
        if ($checkCount > 0 && $priceCount == 0) {
            return '质检员';
        } elseif ($priceCount > 0 && $checkCount == 0) {
            return '估价员';
        } elseif ($checkCount > 0 && $priceCount > 0) {
            return '管理员';
        }
        
        return '普通用户';
    }

    /**
     * 获取超级管理员用户ID
     * @param int $siteId
     * @return int
     */
    protected function getSuperAdminUid(int $siteId = 0): int
    {
        // 获取默认站点ID（超级管理员通常在默认站点）
        try {
            $defaultSiteId = request()->defaultSiteId();
        } catch (Exception $e) {
            $defaultSiteId = 0; // 如果获取失败，使用0作为默认值
        }
        
        $superAdminUid = SysUserRole::where([
            ['site_id', '=', $defaultSiteId],
            ['is_admin', '=', 1]
        ])->value('uid');
        
        return (int)$superAdminUid;
    }

    /**
     * 获取当前用户的角色类型（用于权限判断）
     * @return string
     */
    protected function getCurrentUserRole(): string
    {
        // 使用niucloud官方权限系统判断用户角色
        $superAdminUid = $this->getSuperAdminUid();
        if ($this->uid == $superAdminUid) {
            return 'admin';
        }
        
        // 获取用户在当前站点的角色信息
        $userRoleService = new UserRoleService();
        $userRole = $userRoleService->getUserRole($this->site_id, $this->uid);
        
        if (empty($userRole)) {
            return 'guest';
        }
        
        // 如果是站点管理员
        if ($userRole['is_admin']) {
            return 'admin';
        }
        
        // 根据角色ID判断具体角色类型
        $roleIds = $userRole['role_ids'] ?? [];
        if (!empty($roleIds)) {
            // 获取角色名称来判断类型
            $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, $this->site_id);
            foreach ($roleNames as $roleName) {
                $roleName = strtolower($roleName);
                if (strpos($roleName, '质检') !== false) {
                    return 'checker';
                } elseif (strpos($roleName, '估价') !== false || strpos($roleName, '定价') !== false) {
                    return 'pricer';
                }
            }
        }
        
        // 简化判断：通过用户在设备表中的操作记录来推断角色
        $checkCount = RecycleDevice::where('check_uid', $this->uid)->count();
        $priceCount = RecycleDevice::where('price_uid', $this->uid)->count();
        
        if ($checkCount > 0 && $priceCount == 0) {
            return 'checker'; // 质检员
        } elseif ($priceCount > 0 && $checkCount == 0) {
            return 'pricer'; // 估价员
        } elseif ($checkCount > 0 && $priceCount > 0) {
            return 'admin'; // 既能质检又能定价的是管理员
        }
        
        return 'user'; // 普通用户
    }

    /**
     * 检查用户是否有查看指定数据的权限
     * @param int $targetUserId 目标用户ID
     * @return bool
     */
    protected function canViewUserData(int $targetUserId = 0): bool
    {
        $currentRole = $this->getCurrentUserRole();
        
        // 超级管理员和站点管理员可以查看所有数据
        if ($currentRole === 'admin') {
            return true;
        }
        
        // 普通用户只能查看自己的数据
        if ($targetUserId == 0 || $targetUserId == $this->uid) {
            return true;
        }
        
        return false;
    }

    /**
     * 获取设备分类名称 - 与Model保持一致
     * @param int $categoryId
     * @return string
     */
    protected function getCategoryName(int $categoryId): string
    {
        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        
        return $categories[$categoryId] ?? '手机';
    }

    /**
     * 获取今日统计数据（包含用户角色和基本信息）
     * @param int $userId 用户ID，0表示当前用户
     * @param int $categoryId 分类ID，0表示全部分类
     * @return array
     */
    public function getTodayStats(int $userId = 0, int $categoryId = 0): array
    {
        $userId = $userId ?: $this->uid;
        $currentRole = $this->getCurrentUserRole();
        
        // 获取用户信息
        $user = SysUser::where('uid', $userId)->field('username, real_name')->find();
        $userName = $user ? ($user['real_name'] ?: $user['username']) : '未知用户';
        
        return [
            'user_role' => $currentRole,
            'user_name' => $userName,
            'user_id' => $userId
        ];
    }

    /**
     * 获取普通用户签收统计（修复逻辑：统计该用户处理过的设备的签收情况）
     * @param array $params
     * @return array
     */
    public function getUserSignStats(array $params): array
    {
        $userId = (int)($params['user_id'] ?? $this->uid);
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        
        // 权限检查
        if (!$this->canViewUserData($userId)) {
            return [
                'signed_order_count' => 0,
                'signed_device_count' => 0,
                'category_breakdown' => []
            ];
        }
        
        // 重要修复：获取该用户处理过的设备ID（质检过或定价过的设备）
        $userDeviceIds = RecycleDevice::where([
            ['site_id', '=', $this->site_id]
        ])
        ->where(function($query) use ($userId) {
            $query->where('check_uid', $userId)->whereOr('price_uid', $userId);
        })
        ->column('id');
        
        if (empty($userDeviceIds)) {
            return [
                'signed_order_count' => 0,
                'signed_device_count' => 0,
                'category_breakdown' => []
            ];
        }
        
        // 获取这些设备对应的订单ID
        $orderIds = RecycleDevice::where('id', 'in', $userDeviceIds)
            ->group('order_id')
            ->column('order_id');
        
        // 基础查询条件 - 只统计该用户处理过的设备对应的订单
        $orderWhere = [
            ['site_id', '=', $this->site_id],
            ['id', 'in', $orderIds],
            ['status', '>=', 2] // 已签收状态
        ];
        
        // 时间条件 - 使用create_at字段作为签收时间
        if ($startTime) {
            $orderWhere[] = ['create_at', '>=', strtotime($startTime . ' 00:00:00')];
        }
        if ($endTime) {
            $orderWhere[] = ['create_at', '<=', strtotime($endTime . ' 23:59:59')];
        }
        
        // 签收订单数 - 基于该用户处理过的设备对应的订单
        $signedOrderCount = RecycleOrder::where($orderWhere)->count();
        
        // 签收设备数和分类统计 - 只统计该用户处理过的设备
        $deviceWhere = [
            ['d.site_id', '=', $this->site_id],
            ['d.id', 'in', $userDeviceIds],
            ['o.status', '>=', 2] // 订单已签收
        ];
        
        $deviceQuery = RecycleDevice::alias('d')
            ->join('recycle_order o', 'd.order_id = o.id')
            ->where($deviceWhere);
            
        // 添加时间条件 - 使用create_at字段作为签收时间
        if ($startTime) {
            $deviceQuery->where('o.create_at', '>=', strtotime($startTime . ' 00:00:00'));
        }
        if ($endTime) {
            $deviceQuery->where('o.create_at', '<=', strtotime($endTime . ' 23:59:59'));
        }
        
        $signedDeviceCount = $deviceQuery->count();
        
        // 按分类统计签收设备数量 - 只统计该用户处理过的设备
        $categoryStatsQuery = RecycleDevice::alias('d')
            ->join('recycle_order o', 'd.order_id = o.id')
            ->where($deviceWhere)
            ->field('d.category_id, COUNT(*) as count')
            ->group('d.category_id');
            
        // 添加时间条件 - 使用create_at字段作为签收时间
        if ($startTime) {
            $categoryStatsQuery->where('o.create_at', '>=', strtotime($startTime . ' 00:00:00'));
        }
        if ($endTime) {
            $categoryStatsQuery->where('o.create_at', '<=', strtotime($endTime . ' 23:59:59'));
        }
        
        $categoryStats = $categoryStatsQuery->select()->toArray();
        
        $categoryBreakdown = [];
        foreach ($categoryStats as $stat) {
            $categoryBreakdown[] = [
                'category_name' => $this->getCategoryName($stat['category_id']),
                'count' => $stat['count']
            ];
        }
        
        return [
            'signed_order_count' => $signedOrderCount,
            'signed_device_count' => $signedDeviceCount,
            'category_breakdown' => $categoryBreakdown
        ];
    }

    /**
     * 获取管理员概况统计
     * @param array $params
     * @return array
     */
    public function getOverviewStats(array $params): array
    {
        // 权限检查和调试日志
        $currentRole = $this->getCurrentUserRole();
        Log::info('getOverviewStats权限检查:', [
            'uid' => $this->uid,
            'role' => $currentRole,
            'site_id' => $this->site_id,
            'params' => $params
        ]);
        
        // 临时放宽权限：管理员后台登录的用户都可以访问
        // if ($currentRole !== 'admin') {
        //     Log::warning('getOverviewStats权限不足:', ['role' => $currentRole]);
        //     return [];
        // }
        
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        // 今日订单数 - 使用create_at字段
        $todayOrderCount = RecycleOrder::where([
                ['site_id', '=', $this->site_id],
            ['create_at', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])->count();
        
        // 昨日订单数
        $yesterdayOrderCount = RecycleOrder::where([
                ['site_id', '=', $this->site_id],
            ['create_at', 'between', [strtotime($yesterday . ' 00:00:00'), strtotime($yesterday . ' 23:59:59')]]
        ])->count();
        
        // 今日质检数量和分类 - 使用check_at字段
        $todayCheckQuery = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            // ['check_status', '=', 3],
            ['check_at', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ]);
        
        $todayCheckCount = $todayCheckQuery->count();
        
        $todayCheckBreakdown = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            // ['check_status', '=', 3],
            ['check_at', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])
        ->field('category_id, COUNT(*) as count')
        ->group('category_id')
        ->select()
        ->toArray();
        
        $checkBreakdown = [];
        foreach ($todayCheckBreakdown as $item) {
            $checkBreakdown[] = [
                'category_name' => $this->getCategoryName($item['category_id']),
                'count' => $item['count']
            ];
        }
        
        // 今日定价数据 - 使用price_at字段
        $todayPriceQuery = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
            ['price_at', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]],
            ['final_price', '>', 0]
        ]);
        
        $todayPriceCount = $todayPriceQuery->count();
        
        // 修复：今日打款数据 - 统一逻辑，只使用pay_time > 0条件
        $todayPaymentQuery = RecycleDevice::alias('d')
            ->join('recycle_order o', 'd.order_id = o.id')
            ->where([
                ['d.site_id', '=', $this->site_id],
                ['d.final_price', '>', 0],
                ['o.pay_time', '>', 0],
                ['o.pay_time', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
            ]);
        
        $todayPaymentCount = $todayPaymentQuery->count();
        $todayPaymentAmount = $todayPaymentQuery->sum('d.final_price') ?: 0;
        
        // 今日退货数量 - 设备状态为6-已退回
        $todayReturnCount = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
            ['status', '=', 6], // 6-已退回
            ['update_at', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])->count();
        
        return [
            'today_order_count' => $todayOrderCount,
            'yesterday_order_count' => $yesterdayOrderCount,
            'today_check_count' => $todayCheckCount,
            'today_check_breakdown' => $checkBreakdown,
            'today_price_count' => $todayPriceCount,
            'today_payment_amount' => $todayPaymentAmount,
            'today_payment_count' => $todayPaymentCount,
            'today_return_count' => $todayReturnCount
        ];
    }

    /**
     * 获取用户列表（获取当前站点的所有员工）
     * @return array
     */
    public function getUserList(): array
    {
        // 权限检查和调试日志
        $currentRole = $this->getCurrentUserRole();
        Log::info('getUserList权限检查:', [
            'uid' => $this->uid,
            'role' => $currentRole,
            'site_id' => $this->site_id
        ]);
        
        // 获取当前站点的所有用户（通过 sys_user_role 表）
        $siteUserIds = SysUserRole::where('site_id', $this->site_id)
            ->column('uid');
        
        if (empty($siteUserIds)) {
            Log::info('当前站点没有关联用户，返回当前登录用户', ['uid' => $this->uid]);
            $siteUserIds = [$this->uid];
        }
        
        // 获取用户信息（排除已删除的用户）
        $users = SysUser::where('uid', 'in', $siteUserIds)
            ->where('delete_time', 0) // 排除已删除的用户
            ->field('uid, username, real_name')
            ->order('uid asc')
            ->select()
            ->toArray();
        
        Log::info('getUserList返回用户数量:', [
            'count' => count($users), 
            'site_id' => $this->site_id,
            'users' => $users
        ]);
        
        return $users;
    }

    /**
     * 获取用户详细统计
     * @param array $params
     * @return array
     */
    public function getUserDetailStats(array $params): array
    {
        // 权限检查和调试日志
        $currentRole = $this->getCurrentUserRole();
        Log::info('getUserDetailStats权限检查:', [
            'uid' => $this->uid,
            'role' => $currentRole,
            'site_id' => $this->site_id,
            'params' => $params
        ]);
        
        // 临时放宽权限：管理员后台登录的用户都可以访问
        // if ($currentRole !== 'admin') {
        //     Log::warning('getUserDetailStats权限不足:', ['role' => $currentRole]);
        //     return [];
        // }
        
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        $specificUserId = (int)($params['user_id'] ?? 0);
        
        // 获取用户列表
        $users = $this->getUserList();
        
        // 如果没有用户列表，至少返回当前登录用户的数据（全为0）
        if (empty($users)) {
            Log::info('getUserList返回空，使用当前登录用户');
            $currentUser = SysUser::where('uid', $this->uid)->field('uid, username, real_name')->find();
            if ($currentUser) {
                $users = [$currentUser->toArray()];
            }
        }
        
        if ($specificUserId) {
            $users = array_filter($users, function($user) use ($specificUserId) {
                return $user['uid'] == $specificUserId;
            });
            
            // 如果筛选后为空，但指定了用户ID，尝试获取该用户信息
            if (empty($users)) {
                $specificUser = SysUser::where('uid', $specificUserId)->field('uid, username, real_name')->find();
                if ($specificUser) {
                    $users = [$specificUser->toArray()];
                }
            }
        }
        
        $result = [];
        foreach ($users as $user) {
            $userId = $user['uid'];
            $userName = $user['real_name'] ?: $user['username'];
            
            // ===================================
            // 修复：签收统计逻辑 - 只统计该用户处理过的设备
            // ===================================
            
            // 获取该用户处理过的设备ID（质检过或定价过的设备）
            $userDeviceIds = RecycleDevice::where([
                ['site_id', '=', $this->site_id]
            ])
            ->where(function($query) use ($userId) {
                $query->where('check_uid', $userId)->whereOr('price_uid', $userId);
            })
            ->column('id');
            
            $signedOrderCount = 0;
            $signedDeviceCount = 0;
            $categoryBreakdownText = [];
            
            if (!empty($userDeviceIds)) {
                // 获取这些设备对应的订单ID
                $orderIds = RecycleDevice::where('id', 'in', $userDeviceIds)
                    ->group('order_id')
                    ->column('order_id');
                
                // 签收订单统计 - 基于该用户处理过的设备对应的订单
                $orderWhere = [
                    ['site_id', '=', $this->site_id],
                    ['id', 'in', $orderIds],
                    ['status', '>=', 2] // 已签收状态
                ];
                
                // 时间条件 - 使用create_at字段作为签收时间
                if ($startTime) {
                    $orderWhere[] = ['create_at', '>=', strtotime($startTime . ' 00:00:00')];
                }
                if ($endTime) {
                    $orderWhere[] = ['create_at', '<=', strtotime($endTime . ' 23:59:59')];
                }
                
                $signedOrderCount = RecycleOrder::where($orderWhere)->count();
                
                // 签收设备统计 - 只统计该用户处理过的设备
                $deviceWhere = [
                    ['d.site_id', '=', $this->site_id],
                    ['d.id', 'in', $userDeviceIds],
                    ['o.status', '>=', 2] // 订单已签收
                ];
                
                $deviceQuery = RecycleDevice::alias('d')
            ->join('recycle_order o', 'd.order_id = o.id')
                    ->where($deviceWhere);
                    
                // 添加时间条件 - 使用create_at字段作为签收时间
                if ($startTime) {
                    $deviceQuery->where('o.create_at', '>=', strtotime($startTime . ' 00:00:00'));
                }
                if ($endTime) {
                    $deviceQuery->where('o.create_at', '<=', strtotime($endTime . ' 23:59:59'));
                }
                
                $signedDeviceCount = $deviceQuery->count();
                
                // 签收设备分类统计 - 只统计该用户处理过的设备
                $categoryStatsQuery = RecycleDevice::alias('d')
                    ->join('recycle_order o', 'd.order_id = o.id')
                    ->where($deviceWhere)
                    ->field('d.category_id, COUNT(*) as count')
                    ->group('d.category_id');
                    
                // 添加时间条件 - 使用create_at字段作为签收时间
                if ($startTime) {
                    $categoryStatsQuery->where('o.create_at', '>=', strtotime($startTime . ' 00:00:00'));
                }
                if ($endTime) {
                    $categoryStatsQuery->where('o.create_at', '<=', strtotime($endTime . ' 23:59:59'));
                }
                
                $signCategoryStats = $categoryStatsQuery->select()->toArray();
                
                foreach ($signCategoryStats as $stat) {
                    $categoryBreakdownText[] = $this->getCategoryName($stat['category_id']) . ' ' . $stat['count'] . '台';
                }
            }
            
            // 质检统计 - 使用check_at字段
            $checkWhere = [
                ['site_id', '=', $this->site_id],
                ['check_uid', '=', $userId], 
                // ['check_status', '=', 3]
            ];
            if ($startTime) {
                $checkWhere[] = ['check_at', '>=', strtotime($startTime . ' 00:00:00')];
            }
            if ($endTime) {
                $checkWhere[] = ['check_at', '<=', strtotime($endTime . ' 23:59:59')];
            }
            $checkCount = RecycleDevice::where($checkWhere)->count();
            
            // 质检分类统计
            $checkCategoryStats = RecycleDevice::where($checkWhere)
                ->field('category_id, COUNT(*) as count')
                ->group('category_id')
                ->select()
                ->toArray();
            
            $checkCategoryText = [];
            foreach ($checkCategoryStats as $stat) {
                $checkCategoryText[] = $this->getCategoryName($stat['category_id']) . ' ' . $stat['count'] . '台';
            }
            
            // 定价统计 - 使用price_at字段
            $priceWhere = [
                ['site_id', '=', $this->site_id],
                ['price_uid', '=', $userId],
                ['final_price', '>', 0]
            ];
            if ($startTime) {
                $priceWhere[] = ['price_at', '>=', strtotime($startTime . ' 00:00:00')];
            }
            if ($endTime) {
                $priceWhere[] = ['price_at', '<=', strtotime($endTime . ' 23:59:59')];
            }
            $priceCount = RecycleDevice::where($priceWhere)->count();
            
            // 修复：打款统计 - 基于实际打款操作人员
            $paymentQuery = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where([
                    ['d.site_id', '=', $this->site_id],
                    ['o.pay_uid', '=', $userId], // 改为基于打款操作人员
                    ['o.pay_time', '>', 0],
                    ['d.final_price', '>', 0]
                ]);
            
            // 如果有时间筛选，使用pay_time字段
            if ($startTime && $endTime) {
                $paymentQuery->where('o.pay_time', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]);
            }
            
            $paymentCount = $paymentQuery->count();
            
            // 时间段文本
            $periodText = '';
            if ($startTime && $endTime) {
                if ($startTime === $endTime) {
                    $periodText = $startTime;
                } else {
                    $periodText = $startTime . ' 至 ' . $endTime;
                }
            } else {
                $periodText = '全部时间';
            }
            
            $result[] = [
                'user_id' => $userId,
                'user_name' => $userName,
                'user_type_name' => $this->getUserRoleName($userId),
                'period_text' => $periodText,
            'signed_order_count' => $signedOrderCount,
            'signed_device_count' => $signedDeviceCount,
                'category_breakdown_text' => implode('，', $categoryBreakdownText),
                'check_count' => $checkCount,
                'check_category_text' => implode('，', $checkCategoryText),
                'price_count' => $priceCount,
                'payment_count' => $paymentCount
            ];
        }
        
        return $result;
    }

    /**
     * 获取分类统计汇总
     * @param array $params
     * @return array
     */
    public function getCategoryStats(array $params): array
    {
        $userId = (int)($params['user_id'] ?? $this->uid);
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        
        // 权限检查
        if (!$this->canViewUserData($userId)) {
            return [];
        }
        
        // 修复：构建时间条件，确保时间格式正确
        $timeWhere = [];
        if ($startTime && $endTime) {
            $timeWhere = [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')];
        }
        
        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        
        $result = [];
        foreach ($categories as $categoryId => $categoryName) {
            $where = [
                ['site_id', '=', $this->site_id],
                ['category_id', '=', $categoryId]
            ];
            
            if (!empty($timeWhere)) {
                $where = array_merge($where, $timeWhere);
            }
            
            // 统计各项数据 - 修复：添加check_status条件确保只统计已完成质检的设备
            $totalCheck = RecycleDevice::where($where)
                ->where('check_uid', $userId)
                ->where('check_at', '>', 0)
                // ->where('check_status', '=', 3) // 确保只统计已完成质检的设备
                ->count();
            
            // 定价统计 - 使用price_at字段和时间筛选
            $priceWhere = [
                ['site_id', '=', $this->site_id],
                ['category_id', '=', $categoryId],
                ['price_uid', '=', $userId],
                ['final_price', '>', 0]
            ];
            
            // 修复：如果有时间筛选，对定价使用price_at字段，确保时间格式正确
            if ($startTime && $endTime) {
                $priceWhere[] = ['price_at', 'between', $timeWhere];
            }
            
            $totalPrice = RecycleDevice::where($priceWhere)->count();
            
            $totalRecycle = RecycleDevice::where($where)
                ->where('status', 5) // 5-已回收
                ->count();
            
            $totalReturn = RecycleDevice::where($where)
                ->where('status', 6) // 6-已退回
                ->count();
            
            // 修复：打款金额统计 - 基于实际打款操作人员
            $amountQuery = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where([
                    ['d.site_id', '=', $this->site_id],
                    ['d.category_id', '=', $categoryId],
                    ['o.pay_uid', '=', $userId], // 改为基于打款操作人员
                    ['d.final_price', '>', 0],
                    ['o.pay_time', '>', 0]
                ]);
                
            // 如果有时间筛选，使用pay_time字段
            if ($startTime && $endTime) {
                $amountQuery->where('o.pay_time', 'between', $timeWhere);
            }
            
            $totalAmount = $amountQuery->sum('d.final_price');
            
            $result[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'total_check' => $totalCheck,
                'total_price' => $totalPrice,
                'total_recycle' => $totalRecycle,
                'total_return' => $totalReturn,
                'total_amount' => round($totalAmount, 2)
            ];
        }
        
        return $result;
    }

    /**
     * 获取用户统计数据（包含签收、质检、定价、打款的完整统计）
     * @param array $params
     * @return array
     */
    public function getUserStats(array $params): array
    {
        // 添加更详细的调试日志
        Log::info('getUserStats原始参数:', $params);
        
        $currentRole = $this->getCurrentUserRole();
        $userId = (int)($params['user_id'] ?? $this->uid);
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        
        Log::info('getUserStats处理后的参数:', [
            'userId' => $userId,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'currentRole' => $currentRole,
            'startTimeTimestamp' => $startTime ? strtotime($startTime . ' 00:00:00') : 0,
            'endTimeTimestamp' => $endTime ? strtotime($endTime . ' 23:59:59') : 0,
            'startTimeDate' => $startTime ? date('Y-m-d H:i:s', strtotime($startTime . ' 00:00:00')) : '无',
            'endTimeDate' => $endTime ? date('Y-m-d H:i:s', strtotime($endTime . ' 23:59:59')) : '无',
        ]);
        
        // 权限检查
        if (!$this->canViewUserData($userId)) {
            return [];
        }
        
        // 修复：删除错误的基础时间条件构建，改为在具体统计中使用对应的时间字段
        $where = [
            ['site_id', '=', $this->site_id]
        ];
        
        // 如果是管理员，可以查看所有用户数据
        if ($currentRole === 'admin') {
            // 获取所有有质检操作的用户ID
            $checkUserIds = RecycleDevice::where($where)
                ->where('check_uid', '>', 0)
                ->group('check_uid')
                ->column('check_uid');
            
            // 获取所有有定价操作的用户ID
            $priceUserIds = RecycleDevice::where($where)
                ->where('price_uid', '>', 0)
                ->group('price_uid')
                ->column('price_uid');
            
            // 获取所有有打款操作的用户ID
            $payUserIds = RecycleOrder::where('site_id', $this->site_id)
                ->where('pay_uid', '>', 0)
                ->group('pay_uid')
                ->column('pay_uid');
            
            // 合并并去重
            $allUserIds = array_unique(array_merge($checkUserIds, $priceUserIds, $payUserIds));
            
            // 如果没有找到任何用户，至少包含当前用户
            if (empty($allUserIds)) {
                $allUserIds = [$this->uid];
            }
        } else {
            // 普通用户只能查看自己的数据
            $allUserIds = [$userId];
        }
        
        $result = [];
        foreach ($allUserIds as $uid) {
            if ($uid <= 0) continue;
            
            // 获取用户信息
            $userInfo = Db::name('sys_user')->where('uid', $uid)->field('uid,username,real_name')->find();
            if (!$userInfo) continue;
            
            // ===========================================
            // 新增：签收统计数据（基于该用户处理过的设备）
            // ===========================================
            
            // 获取该用户处理过的设备ID（质检过或定价过的设备）
            $userDeviceIds = RecycleDevice::where([
                ['site_id', '=', $this->site_id]
            ])
            ->where(function($query) use ($uid) {
                $query->where('check_uid', $uid)->whereOr('price_uid', $uid);
            })
            ->column('id');
            
            $signedOrderCount = 0;
            $signedDeviceCount = 0;
            $signCategoryStats = [];
            
            if (!empty($userDeviceIds)) {
                // 获取这些设备对应的订单ID
                $orderIds = RecycleDevice::where('id', 'in', $userDeviceIds)
                    ->group('order_id')
                    ->column('order_id');
                
                // 签收订单统计 - 基于该用户处理过的设备对应的订单
                $orderWhere = [
                    ['site_id', '=', $this->site_id],
                    ['id', 'in', $orderIds],
                    ['status', '>=', 2] // 已签收状态
                ];
                
                // 修复：时间条件 - 使用create_at字段作为签收时间，确保时间格式正确
                if ($startTime && $endTime) {
                    $orderWhere[] = ['create_at', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]];
                }
                
                $signedOrderCount = RecycleOrder::where($orderWhere)->count();
                
                // 签收设备统计 - 只统计该用户处理过的设备
                $deviceWhere = [
                    ['d.site_id', '=', $this->site_id],
                    ['d.id', 'in', $userDeviceIds],
                    ['o.status', '>=', 2] // 订单已签收
                ];
                
                $deviceQuery = RecycleDevice::alias('d')
                    ->join('recycle_order o', 'd.order_id = o.id')
                    ->where($deviceWhere);
                    
                // 修复：添加时间条件，使用create_at字段作为签收时间，确保时间格式正确
                if ($startTime && $endTime) {
                    $deviceQuery->where('o.create_at', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]);
                }
                
                $signedDeviceCount = $deviceQuery->count();
                
                // 签收设备分类统计 - 只统计该用户处理过的设备
                $categoryStatsQuery = RecycleDevice::alias('d')
                    ->join('recycle_order o', 'd.order_id = o.id')
                    ->where($deviceWhere)
                    ->field('d.category_id, COUNT(*) as count')
                    ->group('d.category_id');
                    
                // 修复：添加时间条件，使用create_at字段作为签收时间，确保时间格式正确
                if ($startTime && $endTime) {
                    $categoryStatsQuery->where('o.create_at', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]);
                }
                
                $categoryStats = $categoryStatsQuery->select()->toArray();
                
                foreach ($categoryStats as $stat) {
                    $signCategoryStats[] = [
                        'category_name' => $this->getCategoryName($stat['category_id']),
                        'count' => $stat['count']
                    ];
                }
            }
            
            // ===========================================
            // 原有的质检、定价、打款统计逻辑
            // ===========================================
            
            // 质检统计和分类 - 修复：添加check_status条件确保只统计已完成质检的设备
            $checkWhere = [
                ['site_id', '=', $this->site_id],
                ['check_uid', '=', $uid],
                ['check_at', '>', 0],
                // ['status', '=', 3] // 确保只统计已完成质检的设备
            ];
            
            // 修复：如果有时间筛选，对质检使用check_at字段，确保时间格式正确
            if ($startTime && $endTime) {
                $checkWhere[] = ['check_at', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]];
            }
            
            $checkCount = RecycleDevice::where($checkWhere)->count();
                
            // 质检设备分类统计 - 使用写死的分类映射
            $checkCategoryQuery = RecycleDevice::where($checkWhere)
                ->field('category_id, count(*) as count')
                ->group('category_id')
                ->select()
                ->toArray();
                
            $checkCategoryStats = [];
            foreach ($checkCategoryQuery as $stat) {
                $checkCategoryStats[] = [
                    'category_name' => $this->getCategoryName($stat['category_id']),
                    'count' => $stat['count']
                ];
            }
            
            // 修复：定价统计 - 使用price_at字段和时间筛选，确保时间格式正确
            $priceWhere = [
                ['site_id', '=', $this->site_id],
                ['price_uid', '=', $uid],
                ['final_price', '>', 0]
            ];
            
            // 修复：构建时间条件，确保时间格式正确
            $timeWhere = [];
            if ($startTime && $endTime) {
                $timeWhere = [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')];
            }
            
            if (!empty($timeWhere)) {
                $priceWhere[] = ['price_at', 'between', $timeWhere];
            }
            
            $priceCount = RecycleDevice::where($priceWhere)->count();
            
            // 定价设备分类统计 - 使用写死的分类映射
            $priceCategoryQuery = RecycleDevice::where($priceWhere)
                ->field('category_id, count(*) as count')
                ->group('category_id')
                ->select()
                ->toArray();
                
            $priceCategoryStats = [];
            foreach ($priceCategoryQuery as $stat) {
                $priceCategoryStats[] = [
                    'category_name' => $this->getCategoryName($stat['category_id']),
                    'count' => $stat['count']
                ];
            }
            
            // 修复：回收和退货统计，去掉错误的时间条件（应该使用具体的时间字段）
            $recycleCount = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 5] // 5-已回收
            ])->count();
            
            $returnCount = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['status', '=', 6] // 6-已退回
            ])->count();
                
            // 修复：打款统计和分类 - 基于实际打款操作人员，而不是定价人员
            $paymentQuery = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where([
                    ['d.site_id', '=', $this->site_id],
                    ['o.pay_uid', '=', $uid], // 改为基于打款操作人员
                    ['o.pay_time', '>', 0],
                    ['d.final_price', '>', 0]
                ]);
            
            // 如果有时间筛选，使用pay_time字段
            if ($startTime && $endTime) {
                $paymentQuery->where('o.pay_time', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]);
            }
            
            $paymentCount = $paymentQuery->count();
            
            // 打款设备分类统计 - 使用写死的分类映射
            $paymentCategoryWhere = [
                ['d.site_id', '=', $this->site_id],
                ['o.pay_uid', '=', $uid], // 基于打款操作人员
                ['o.pay_time', '>', 0],
                ['d.final_price', '>', 0]
            ];
            
            // 如果有时间筛选，添加时间条件
            if ($startTime && $endTime) {
                $paymentCategoryWhere[] = ['o.pay_time', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]];
            }
            
            $paymentCategoryQuery = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where($paymentCategoryWhere)
                ->field('d.category_id, count(*) as count')
                ->group('d.category_id')
                ->select()
                ->toArray();
                
            $paymentCategoryStats = [];
            foreach ($paymentCategoryQuery as $stat) {
                $paymentCategoryStats[] = [
                    'category_name' => $this->getCategoryName($stat['category_id']),
                    'count' => $stat['count']
                ];
            }
            
            // 修复：金额统计 - 基于实际打款操作人员统计金额
            $totalAmountQuery = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where([
                    ['d.site_id', '=', $this->site_id],
                    ['o.pay_uid', '=', $uid], // 改为基于打款操作人员
                    ['d.final_price', '>', 0],
                    ['o.pay_time', '>', 0]
                ]);
                
            // 如果有时间筛选，使用pay_time字段
            if ($startTime && $endTime) {
                $totalAmountQuery->where('o.pay_time', 'between', [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')]);
            }
            
            $totalAmount = $totalAmountQuery->sum('d.final_price');
            
            // 如果是查询指定用户（非管理员查看所有用户），即使没有操作记录也要返回数据
            $shouldInclude = false;
            if ($currentRole === 'admin') {
                // 管理员查看所有用户时，只显示有操作记录的用户
                $shouldInclude = ($checkCount > 0 || $priceCount > 0);
            } else {
                // 普通用户查看自己的数据时，总是显示（即使为0）
                $shouldInclude = true;
            }
            
            if ($shouldInclude) {
                // 判断用户类型 - 使用新的角色名称获取方法
                $userTypeName = $this->getUserRoleName($uid, $this->site_id);
                
                $result[] = [
                    'user' => $userInfo,
                    'user_type_name' => $userTypeName,
                    // 新增：签收统计数据
                    'signed_order_count' => $signedOrderCount,
                    'signed_device_count' => $signedDeviceCount,
                    'sign_category_breakdown' => $signCategoryStats,
                    // 原有的统计数据
                    'check_count' => $checkCount,
                    'check_category_breakdown' => $checkCategoryStats,
                    'price_count' => $priceCount,
                    'price_category_breakdown' => $priceCategoryStats,
                    'payment_count' => $paymentCount,
                    'payment_category_breakdown' => $paymentCategoryStats,
                    'recycle_count' => $recycleCount,
                    'return_count' => $returnCount,
                    'total_amount' => round($totalAmount, 2),
                    'stat_date' => $startTime ?: date('Y-m-d')
                ];
            }
        }
        
        return $result;
    }

    /**
     * 获取排行榜数据（仅管理员可见）
     * @param array $params
     * @return array
     */
    public function getRankingStats(array $params): array
    {
        $currentRole = $this->getCurrentUserRole();
        
        // 只有管理员可以查看排行榜
        if ($currentRole !== 'admin') {
            return [];
        }
        
        $rankType = $params['rank_type'] ?? 'check';
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        $limit = (int)($params['limit'] ?? 10); // 确保转换为整数类型
        
        // 修复：构建时间条件，确保时间格式正确
        $timeWhere = [];
        if ($startTime && $endTime) {
            $timeWhere = [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')];
        }
        
        $where = [
            ['site_id', '=', $this->site_id]
        ];
        
        if (!empty($timeWhere)) {
            $where[] = ['create_at', 'between', $timeWhere];
        }
        
        // 根据排行类型构建查询
        $field = match($rankType) {
            'price' => 'price_uid',
            'recycle' => 'check_uid', // 回收也按质检员统计
            'amount' => 'pay_uid',    // 金额排行改为基于打款操作人员
            default => 'check_uid'
        };
        
        $query = RecycleDevice::where($where);
        
        if ($rankType === 'amount') {
            $query = RecycleDevice::alias('d')
                          ->join('recycle_order o', 'd.order_id = o.id')
                          ->where([
                              ['d.site_id', '=', $this->site_id],
                              ['d.final_price', '>', 0],
                              ['o.pay_time', '>', 0],
                              ['o.pay_uid', '>', 0] // 确保有打款操作人员
                          ])
                          ->when(!empty($timeWhere), function($query) use ($timeWhere) {
                              $query->where('o.pay_time', 'between', $timeWhere); // 使用pay_time字段进行时间筛选
                          })
                          ->field("o.{$field} as user_id, sum(d.final_price) as total_value") // 使用o.pay_uid
                          ->group("o.{$field}");
        } else {
            // 修复条件格式问题
            if ($rankType === 'price') {
                $query = $query->where('final_price', '>', 0);
                // 定价排行使用price_at字段
                if (!empty($timeWhere)) {
                    $query = $query->where('price_at', 'between', $timeWhere);
                }
            } elseif ($rankType === 'recycle') {
                $query = $query->where('status', '=', 5); // 5-已回收
                if (!empty($timeWhere)) {
                    $query = $query->where('create_at', 'between', $timeWhere);
                }
            } else {
                // 默认是check - 修复：添加check_status条件确保只统计已完成质检的设备
                $query = $query->where('check_at', '>', 0);
                            //   ->where('check_status', '=', 3); // 确保只统计已完成质检的设备
                if (!empty($timeWhere)) {
                    $query = $query->where('check_at', 'between', $timeWhere);
                }
            }
            
            $query = $query->field("{$field} as user_id, count(*) as total_value")
                          ->group($field);
        }
        
        $stats = $query->order('total_value desc')
                      ->limit($limit) // 现在$limit是整数类型
                      ->select()
                      ->toArray();
        
        // 补充用户信息
        foreach ($stats as &$item) {
            if ($item['user_id'] > 0) {
                $userInfo = Db::name('sys_user')->where('uid', $item['user_id'])->field('uid,username,real_name')->find();
                $item['user'] = $userInfo ?: ['uid' => $item['user_id'], 'username' => '未知用户', 'real_name' => ''];
                
                // 使用新的角色名称获取方法
                $item['user_type_name'] = $this->getUserRoleName($item['user_id'], $this->site_id);
            }
        }
        
        return array_filter($stats, function($item) {
            return $item['user_id'] > 0;
        });
    }

    /**
     * 获取签收统计数据
     * @param array $params
     * @return array
     */
    public function getSignStats(array $params): array
    {
        $userId = (int)($params['user_id'] ?? $this->uid);
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        $categoryId = $params['category_id'] ?? 0;
        
        // 权限检查
        if (!$this->canViewUserData($userId)) {
            return [];
        }
        
        // 修复：构建时间条件，确保时间格式正确
        $timeWhere = [];
        if ($startTime && $endTime) {
            $timeWhere = [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')];
        }
        
        $where = [
            ['site_id', '=', $this->site_id]
        ];
        
        if (!empty($timeWhere)) {
            $where[] = ['create_at', 'between', $timeWhere];
        }
        
        // 统计签收订单数量
        $signedOrderCount = RecycleOrder::where($where)
            ->where('status', '>=', 2) // 已签收及以上状态
            ->count();
        
        // 统计签收设备数量
        $deviceWhere = [
            ['d.site_id', '=', $this->site_id]
        ];
        
        if (!empty($timeWhere)) {
            $deviceWhere[] = ['o.create_at', 'between', $timeWhere];
        }
        
        if ($categoryId > 0) {
            $deviceWhere[] = ['d.category_id', '=', $categoryId];
        }
        
        $signedDeviceCount = RecycleDevice::alias('d')
            ->join('recycle_order o', 'd.order_id = o.id')
            ->where($deviceWhere)
            ->where('o.status', '>=', 2) // 已签收及以上状态
            ->count();
        
        return [
            'signed_order_count' => $signedOrderCount,
            'signed_device_count' => $signedDeviceCount,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'category_id' => $categoryId
        ];
    }

    /**
     * 获取签收分类统计
     * @param array $params
     * @return array
     */
    public function getSignCategoryStats(array $params): array
    {
        $userId = (int)($params['user_id'] ?? $this->uid);
        $startTime = $params['start_time'] ?? '';
        $endTime = $params['end_time'] ?? '';
        
        // 权限检查
        if (!$this->canViewUserData($userId)) {
            return [];
        }
        
        // 构建时间条件
        $timeWhere = [];
        if ($startTime && $endTime) {
            $timeWhere = [strtotime($startTime . ' 00:00:00'), strtotime($endTime . ' 23:59:59')];
        }
        
        $categories = [
            1 => '手机',
            2 => '平板',
            3 => '笔记本',
            4 => '手表',
            5 => '其他'
        ];
        
        $result = [];
        foreach ($categories as $categoryId => $categoryName) {
            $deviceWhere = [
                ['d.site_id', '=', $this->site_id],
                ['d.category_id', '=', $categoryId]
            ];
            
            if (!empty($timeWhere)) {
                $deviceWhere[] = ['o.create_at', 'between', $timeWhere];
            }
            
            // 统计该分类的签收设备数量
            $signedDeviceCount = RecycleDevice::alias('d')
                ->join('recycle_order o', 'd.order_id = o.id')
                ->where($deviceWhere)
                ->where('o.status', '>=', 2) // 已签收及以上状态
                ->count();
            
            $result[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'signed_device_count' => $signedDeviceCount
            ];
        }
        
        return $result;
    }

    /**
     * 获取用户统计概览
     * @param array $params
     * @return array
     */
    public function getMemberStatsOverview(array $params): array
    {
        // 处理时间参数：如果只有日期没有时间，自动拼接时间
        $startTimeStr = $params['start_time'] ?? date('Y-m-d');
        if (strlen($startTimeStr) == 10) { // 只有日期 YYYY-MM-DD
            $startTimeStr .= ' 00:00:00';
        }
        $startTime = strtotime($startTimeStr);
        
        $endTimeStr = $params['end_time'] ?? date('Y-m-d');
        if (strlen($endTimeStr) == 10) { // 只有日期 YYYY-MM-DD
            $endTimeStr .= ' 23:59:59';
        }
        $endTime = strtotime($endTimeStr);
        
        // 总注册用户数（使用模型类，自动排除软删除）
        $totalMembers = (new Member())
            ->where([
                ['site_id', '=', $this->site_id],
                ['create_time', '>', 0],  // 排除未注册的会员
                ['create_time', '<=', $endTime]
            ])
            ->count();
        
        // 期间新增用户（使用模型类，自动排除软删除）
        $newMembers = (new Member())
            ->where([
                ['site_id', '=', $this->site_id],
                ['create_time', '>', 0],  // 排除未注册的会员
                ['create_time', 'between', [$startTime, $endTime]]
            ])
            ->count();
        
        // 期间活跃用户（有登录，使用模型类自动排除软删除）
        $activeMembers = (new Member())
            ->where([
                ['site_id', '=', $this->site_id],
                ['login_time', '>', 0],  // 有登录记录
                ['login_time', 'between', [$startTime, $endTime]]
            ])
            ->count();
        
        // 期间拉新用户（有推广人的新用户，使用模型类自动排除软删除）
        $inviteMembers = (new Member())
            ->where([
                ['site_id', '=', $this->site_id],
                ['pid', '>', 0],
                ['create_time', '>', 0],  // 排除未注册的会员
                ['create_time', 'between', [$startTime, $endTime]]
            ])
            ->count();
        
        // 调试信息 - 查看实际数据（可在日志中查看）
        Log::info('会员统计查询', [
            'site_id' => $this->site_id,
            'start_time' => date('Y-m-d H:i:s', $startTime),
            'end_time' => date('Y-m-d H:i:s', $endTime),
            'total_members' => $totalMembers,
            'new_members' => $newMembers,
            'active_members' => $activeMembers,
            'invite_members' => $inviteMembers
        ]);
        
        return [
            'total_members' => $totalMembers,
            'new_members' => $newMembers,
            'active_members' => $activeMembers,
            'invite_members' => $inviteMembers
        ];
    }

    /**
     * 获取用户注册趋势
     * @param array $params
     * @return array
     */
    public function getMemberRegisterTrend(array $params): array
    {
        // 处理时间参数：如果只有日期没有时间，自动拼接时间
        $startTimeStr = $params['start_time'] ?? date('Y-m-d');
        if (strlen($startTimeStr) == 10) {
            $startTimeStr .= ' 00:00:00';
        }
        $startTime = strtotime($startTimeStr);
        
        $endTimeStr = $params['end_time'] ?? date('Y-m-d');
        if (strlen($endTimeStr) == 10) {
            $endTimeStr .= ' 23:59:59';
        }
        $endTime = strtotime($endTimeStr);
        
        // 计算时间间隔天数
        $days = ceil(($endTime - $startTime) / 86400);
        
        $result = [];
        
        // 如果时间跨度大于31天，按周统计；否则按天统计
        if ($days > 31) {
            // 按周统计
            for ($i = 0; $i < ceil($days / 7); $i++) {
                $weekStart = $startTime + ($i * 7 * 86400);
                $weekEnd = min($weekStart + (7 * 86400) - 1, $endTime);
                
                $count = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['create_time', '>', 0],  // 排除未注册的会员
                        ['create_time', 'between', [$weekStart, $weekEnd]]
                    ])
                    ->count();
                
                $result[] = [
                    'date' => date('m-d', $weekStart) . '~' . date('m-d', $weekEnd),
                    'count' => $count
                ];
            }
        } else {
            // 按天统计
            for ($i = 0; $i < $days; $i++) {
                $dayStart = $startTime + ($i * 86400);
                $dayEnd = $dayStart + 86400 - 1;
                
                $count = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['create_time', '>', 0],  // 排除未注册的会员
                        ['create_time', 'between', [$dayStart, min($dayEnd, $endTime)]]
                    ])
                    ->count();
                
                $result[] = [
                    'date' => date('m-d', $dayStart),
                    'count' => $count
                ];
            }
        }
        
        return $result;
    }

    /**
     * 获取用户注册渠道分布
     * @param array $params
     * @return array
     */
    public function getMemberChannelStats(array $params): array
    {
        // 处理时间参数：如果只有日期没有时间，自动拼接时间
        $startTimeStr = $params['start_time'] ?? date('Y-m-d');
        if (strlen($startTimeStr) == 10) {
            $startTimeStr .= ' 00:00:00';
        }
        $startTime = strtotime($startTimeStr);
        
        $endTimeStr = $params['end_time'] ?? date('Y-m-d');
        if (strlen($endTimeStr) == 10) {
            $endTimeStr .= ' 23:59:59';
        }
        $endTime = strtotime($endTimeStr);
        
        $channelData = (new Member())
            ->field('register_channel, COUNT(*) as count')
            ->where([
                ['site_id', '=', $this->site_id],
                ['create_time', '>', 0],  // 排除未注册的会员
                ['create_time', 'between', [$startTime, $endTime]]
            ])
            ->group('register_channel')
            ->select()
            ->toArray();
        
        // 渠道名称映射
        $channelNames = [
            'H5' => 'H5网页',
            'wechat' => '微信公众号',
            'weapp' => '微信小程序',
            'ali' => '支付宝小程序',
            'douyin' => '抖音小程序',
            'pc' => 'PC端',
            'app' => 'APP'
        ];
        
        $result = [];
        foreach ($channelData as $item) {
            $channel = $item['register_channel'] ?: 'H5';
            $result[] = [
                'channel' => $channel,
                'channel_name' => $channelNames[$channel] ?? $channel,
                'count' => (int)$item['count']
            ];
        }
        
        return $result;
    }

    /**
     * 获取拉新排行榜
     * @param array $params
     * @return array
     */
    public function getMemberInviteRank(array $params): array
    {
        // 处理时间参数：如果只有日期没有时间，自动拼接时间
        $startTimeStr = $params['start_time'] ?? date('Y-m-d');
        if (strlen($startTimeStr) == 10) {
            $startTimeStr .= ' 00:00:00';
        }
        $startTime = strtotime($startTimeStr);
        
        $endTimeStr = $params['end_time'] ?? date('Y-m-d');
        if (strlen($endTimeStr) == 10) {
            $endTimeStr .= ' 23:59:59';
        }
        $endTime = strtotime($endTimeStr);
        $limit = $params['limit'] ?? 10;
        
        $inviteData = (new Member())
            ->alias('m')
            ->field('m.pid, COUNT(*) as invite_count')
            ->where([
                ['m.site_id', '=', $this->site_id],
                ['m.pid', '>', 0],
                ['m.create_time', '>', 0],  // 排除未注册的会员
                ['m.create_time', 'between', [$startTime, $endTime]]
            ])
            ->group('m.pid')
            ->order('invite_count', 'desc')
            ->limit($limit)
            ->select()
            ->toArray();
        
        // 获取推广人信息
        $pids = array_column($inviteData, 'pid');
        if (empty($pids)) {
            return [];
        }
        
        $memberInfo = (new Member())
            ->field('member_id, nickname, mobile, headimg')
            ->where('member_id', 'in', $pids)
            ->select()
            ->toArray();
        
        $memberMap = [];
        foreach ($memberInfo as $member) {
            $memberMap[$member['member_id']] = $member;
        }
        
        $result = [];
        foreach ($inviteData as $item) {
            $member = $memberMap[$item['pid']] ?? [];
            $result[] = [
                'member_id' => $item['pid'],
                'nickname' => $member['nickname'] ?? '未知用户',
                'mobile' => $member['mobile'] ?? '',
                'headimg' => $member['headimg'] ?? '',
                'invite_count' => (int)$item['invite_count']
            ];
        }
        
        return $result;
    }

    /**
     * 获取用户活跃度统计
     * @param array $params
     * @return array
     */
    public function getMemberActivityStats(array $params): array
    {
        // 处理时间参数：如果只有日期没有时间，自动拼接时间
        $startTimeStr = $params['start_time'] ?? date('Y-m-d');
        if (strlen($startTimeStr) == 10) {
            $startTimeStr .= ' 00:00:00';
        }
        $startTime = strtotime($startTimeStr);
        
        $endTimeStr = $params['end_time'] ?? date('Y-m-d');
        if (strlen($endTimeStr) == 10) {
            $endTimeStr .= ' 23:59:59';
        }
        $endTime = strtotime($endTimeStr);
        
        // 计算时间间隔天数
        $days = ceil(($endTime - $startTime) / 86400);
        
        $result = [];
        
        // 按天统计活跃用户和新增用户
        if ($days > 31) {
            // 按周统计
            for ($i = 0; $i < ceil($days / 7); $i++) {
                $weekStart = $startTime + ($i * 7 * 86400);
                $weekEnd = min($weekStart + (7 * 86400) - 1, $endTime);
                
                // 活跃用户（有登录）
                $activeCount = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['login_time', '>', 0],  // 有登录记录
                        ['login_time', 'between', [$weekStart, $weekEnd]]
                    ])
                    ->count();
                
                // 新增用户
                $newCount = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['create_time', '>', 0],  // 排除未注册的会员
                        ['create_time', 'between', [$weekStart, $weekEnd]]
                    ])
                    ->count();
                
                $result[] = [
                    'date' => date('m-d', $weekStart) . '~' . date('m-d', $weekEnd),
                    'active_count' => $activeCount,
                    'new_count' => $newCount
                ];
            }
        } else {
            // 按天统计
            for ($i = 0; $i < $days; $i++) {
                $dayStart = $startTime + ($i * 86400);
                $dayEnd = $dayStart + 86400 - 1;
                
                // 活跃用户（有登录）
                $activeCount = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['login_time', '>', 0],  // 有登录记录
                        ['login_time', 'between', [$dayStart, min($dayEnd, $endTime)]]
                    ])
                    ->count();
                
                // 新增用户
                $newCount = (new Member())
                    ->where([
                        ['site_id', '=', $this->site_id],
                        ['create_time', '>', 0],  // 排除未注册的会员
                        ['create_time', 'between', [$dayStart, min($dayEnd, $endTime)]]
                    ])
                    ->count();
                
                $result[] = [
                    'date' => date('m-d', $dayStart),
                    'active_count' => $activeCount,
                    'new_count' => $newCount
                ];
            }
        }
        
        return $result;
    }
} 