<?php
declare(strict_types=1);

namespace addon\hsx_recycle\app\service\admin\stats;

use addon\hsx_recycle\app\model\order\RecycleDevice;
use addon\hsx_recycle\app\model\order\RecycleOrder;
use addon\hsx_recycle\app\model\order\RecycleOrderLog;
use addon\hsx_recycle\app\model\order\RecycleConsignmentOrder;
use addon\hsx_recycle\app\dict\order\RecycleOrderDict;
use app\model\member\Member;
use app\model\sys\SysUser;
use app\model\sys\SysUserRole;
use app\service\admin\user\UserRoleService;
use core\base\BaseAdminService;
use think\facade\Db;
use think\facade\Log;

/**
 * 回收业务统计服务
 * Class RecycleStatsService
 * @package addon\hsx_recycle\app\service\admin\stats
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
     * 通过模型获取真实表名，交给框架处理表前缀。
     */
    private function orderTable(): string
    {
        return (new RecycleOrder())->getTable();
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
     * 获取指定操作人签收的订单ID
     * 签收归属以订单状态流转日志为准，避免把质检/定价人员误算为签收人。
     */
    private function getSignedOrderIdsByOperator(int $userId, string $startTime = '', string $endTime = ''): array
    {
        if ($userId <= 0) {
            return [];
        }

        $query = RecycleOrderLog::alias('l')
            ->join($this->orderTable() . ' o', 'l.order_id = o.id')
            ->where([
                ['o.site_id', '=', $this->site_id],
                ['l.operator_id', '=', $userId],
                ['l.new_status', '=', RecycleOrderDict::ORDER_STATUS_SIGNED],
                ['o.status', '>=', RecycleOrderDict::ORDER_STATUS_SIGNED],
            ]);

        if ($startTime) {
            $query->where('l.create_at', '>=', strtotime($startTime . ' 00:00:00'));
        }
        if ($endTime) {
            $query->where('l.create_at', '<=', strtotime($endTime . ' 23:59:59'));
        }

        return array_values(array_unique(array_map('intval', $query->column('l.order_id'))));
    }

    /**
     * 获取普通用户签收统计（按真实签收操作人统计）
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
        
        $orderIds = $this->getSignedOrderIdsByOperator($userId, $startTime, $endTime);
        if (empty($orderIds)) {
            return [
                'signed_order_count' => 0,
                'signed_device_count' => 0,
                'category_breakdown' => []
            ];
        }

        $signedOrderCount = count($orderIds);
        $deviceWhere = [
            ['site_id', '=', $this->site_id],
            ['order_id', 'in', $orderIds],
        ];

        $deviceQuery = RecycleDevice::where($deviceWhere);
        $signedDeviceCount = $deviceQuery->count();
        
        $categoryStats = RecycleDevice::where($deviceWhere)
            ->field('category_id, COUNT(*) as count')
            ->group('category_id')
            ->select()
            ->toArray();
        
        $categoryBreakdown = [];
        foreach ($categoryStats as $stat) {
            $categoryBreakdown[] = [
                'category_name' => $this->getCategoryName((int)$stat['category_id']),
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
            ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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

        // 今日代卖数量
        $todayConsignmentCount = RecycleConsignmentOrder::where([
            ['site_id', '=', $this->site_id],
            ['create_time', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])->count();

        // 挂牌中代卖设备数
        $consignmentListingCount = RecycleConsignmentOrder::where([
            ['site_id', '=', $this->site_id],
            ['status', 'in', [0, 1]], // 待上架 + 代卖中
        ])->count();

        // 代卖成交额（今日）
        $consignmentSoldAmount = RecycleConsignmentOrder::where([
            ['site_id', '=', $this->site_id],
            ['status', '>=', 2], // 已售出及之后
            ['update_time', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])->sum('sold_price') ?: 0;

        // 代卖服务收益（今日）
        $consignmentServiceFee = RecycleConsignmentOrder::where([
            ['site_id', '=', $this->site_id],
            ['status', '>=', 2],
            ['update_time', 'between', [strtotime($today . ' 00:00:00'), strtotime($today . ' 23:59:59')]]
        ])->sum('service_fee') ?: 0;

        // 待退回设备数
        $pendingReturn = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['dispose_type', '=', 'return'],
            ['status', '<>', 6], // 尚未完成退回
        ])->count();

        // 待确认设备数（已报价待客户确认）
        $pendingConfirmCount = RecycleDevice::where([
            ['site_id', '=', $this->site_id],
            ['status', '=', 5], // 待确认状态
        ])->count();

        return [
            'today_order_count' => $todayOrderCount,
            'yesterday_order_count' => $yesterdayOrderCount,
            'today_check_count' => $todayCheckCount,
            'today_check_breakdown' => $checkBreakdown,
            'today_price_count' => $todayPriceCount,
            'today_payment_amount' => $todayPaymentAmount,
            'today_payment_count' => $todayPaymentCount,
            'today_return_count' => $todayReturnCount,
            'today_consignment_count' => $todayConsignmentCount,
            'consignment_listing_count' => $consignmentListingCount,
            'consignment_sold_amount' => $consignmentSoldAmount,
            'consignment_service_fee' => $consignmentServiceFee,
            'today_return_device_count' => $todayReturnCount,
            'pending_return' => $pendingReturn,
            'pending_confirm_count' => $pendingConfirmCount,
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
            
            // 签收统计：按真实签收操作人统计
            $signedOrderIds = $this->getSignedOrderIdsByOperator((int)$userId, $startTime, $endTime);
            $signedOrderCount = 0;
            $signedDeviceCount = 0;
            $categoryBreakdownText = [];
            
            if (!empty($signedOrderIds)) {
                $signedOrderCount = count($signedOrderIds);
                $deviceWhere = [
                    ['site_id', '=', $this->site_id],
                    ['order_id', 'in', $signedOrderIds],
                ];
                
                $signedDeviceCount = RecycleDevice::where($deviceWhere)->count();
                
                $signCategoryStats = RecycleDevice::where($deviceWhere)
                    ->field('category_id, COUNT(*) as count')
                    ->group('category_id')
                    ->select()
                    ->toArray();
                
                foreach ($signCategoryStats as $stat) {
                    $categoryBreakdownText[] = $this->getCategoryName((int)$stat['category_id']) . ' ' . $stat['count'] . '台';
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
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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
     * 员工考核看板 · 只读聚合
     * 在已有「工作量计数」基础上，补充各环节平均时效与金额贡献，供前端计算综合评分与排名。
     * 纯读、不改任何业务数据；单表聚合为主，全程故障隔离（异常即 0，绝不影响接口）。
     * @param array $params [start_time, end_time, user_id?]
     * @return array
     */
    public function getStaffKpiBoard(array $params): array
    {
        $startTime = $params['start_time'] ?? '';
        $endTime   = $params['end_time'] ?? '';
        $specificUserId = (int)($params['user_id'] ?? 0);

        $startTs = $startTime ? strtotime($startTime . ' 00:00:00') : 0;
        $endTs   = $endTime ? strtotime($endTime . ' 23:59:59') : 0;

        // 复用已有的工作量计数（签收/质检/定价/打款台数），保证口径一致、不重复造轮子
        $base = [];
        try {
            foreach ($this->getUserDetailStats($params) as $row) {
                $base[(int)$row['user_id']] = $row;
            }
        } catch (\Throwable $e) {
            $base = [];
        }

        $users = $this->getUserList();
        if ($specificUserId) {
            $users = array_filter($users, function ($u) use ($specificUserId) {
                return (int)$u['uid'] === $specificUserId;
            });
        }

        $result = [];
        foreach ($users as $user) {
            $uid = (int)$user['uid'];
            $row = $base[$uid] ?? [
                'user_id'             => $uid,
                'user_name'           => $user['real_name'] ?: $user['username'],
                'user_type_name'      => $this->getUserRoleName($uid),
                'signed_order_count'  => 0,
                'signed_device_count' => 0,
                'check_count'         => 0,
                'price_count'         => 0,
                'payment_count'       => 0,
            ];

            // —— 各环节平均时效（秒）：仅统计该员工亲自操作、且首尾时间戳齐全的设备 —— //
            // 质检时长：录入(create_at) → 质检完成(check_at)
            $row['avg_check_duration'] = $this->kpiAvgDuration('check_uid', $uid, 'check_at', 'create_at', $startTs, $endTs, 'check_at');
            // 定价时长：质检(check_at) → 定价(price_at)
            $row['avg_price_duration'] = $this->kpiAvgDuration('price_uid', $uid, 'price_at', 'check_at', $startTs, $endTs, 'price_at');
            // 打款时长：定价(price_at) → 打款(pay_time)
            $row['avg_pay_duration']   = $this->kpiAvgDuration('pay_uid', $uid, 'pay_time', 'price_at', $startTs, $endTs, 'pay_time');

            // —— 金额贡献 —— //
            // 回收成本贡献：该员工打款设备实付合计
            $row['total_pay_amount'] = $this->kpiSumAmount('pay_uid', $uid, 'pay_amount', 'pay_time', $startTs, $endTs);
            // 定价毛利参考：已售设备 (sell_price - final_price) 均值（sell_price 由数据中台回写）
            $row['avg_margin'] = $this->kpiAvgMargin($uid, $startTs, $endTs);

            $result[] = $row;
        }

        return $result;
    }

    /**
     * KPI 平均时长（秒）：endField - startField，限定操作人与时间窗，两端时间戳须均 > 0 且 end >= start。
     */
    private function kpiAvgDuration(string $opField, int $uid, string $endField, string $startField, int $startTs, int $endTs, string $windowField): float
    {
        try {
            $query = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                [$opField, '=', $uid],
            ])->whereRaw("`{$endField}` > 0 AND `{$startField}` > 0 AND `{$endField}` >= `{$startField}`");
            if ($startTs) $query->where($windowField, '>=', $startTs);
            if ($endTs)   $query->where($windowField, '<=', $endTs);
            $res = $query->fieldRaw("AVG(`{$endField}` - `{$startField}`) as v")->find();
            $v = $res['v'] ?? null;
            return $v !== null ? round((float)$v, 1) : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * KPI 金额合计：限定操作人 / 时间窗。
     */
    private function kpiSumAmount(string $opField, int $uid, string $amountField, string $windowField, int $startTs, int $endTs): float
    {
        try {
            $query = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                [$opField, '=', $uid],
            ])->where($windowField, '>', 0);
            if ($startTs) $query->where($windowField, '>=', $startTs);
            if ($endTs)   $query->where($windowField, '<=', $endTs);
            $v = $query->sum($amountField);
            return $v ? round((float)$v, 2) : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /**
     * KPI 定价毛利均值：已售(sell_price>0) 设备 (sell_price - final_price) 均值，按定价人统计。
     */
    private function kpiAvgMargin(int $uid, int $startTs, int $endTs): float
    {
        try {
            $query = RecycleDevice::where([
                ['site_id', '=', $this->site_id],
                ['price_uid', '=', $uid],
            ])->whereRaw("`sell_price` > 0 AND `final_price` > 0");
            if ($startTs) $query->where('price_at', '>=', $startTs);
            if ($endTs)   $query->where('price_at', '<=', $endTs);
            $res = $query->fieldRaw("AVG(`sell_price` - `final_price`) as v")->find();
            $v = $res['v'] ?? null;
            return $v !== null ? round((float)$v, 2) : 0.0;
        } catch (\Throwable $e) {
            return 0.0;
        }
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
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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

            // 获取所有有签收操作的用户ID
            $signUserIds = RecycleOrderLog::alias('l')
                ->join($this->orderTable() . ' o', 'l.order_id = o.id')
                ->where([
                    ['o.site_id', '=', $this->site_id],
                    ['l.operator_id', '>', 0],
                    ['l.new_status', '=', RecycleOrderDict::ORDER_STATUS_SIGNED],
                ])
                ->group('l.operator_id')
                ->column('l.operator_id');
            
            // 合并并去重
            $allUserIds = array_unique(array_merge($checkUserIds, $priceUserIds, $payUserIds, $signUserIds));
            
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
            
            // 签收统计：按真实签收操作人统计
            $signedOrderIds = $this->getSignedOrderIdsByOperator((int)$uid, $startTime, $endTime);
            $signedOrderCount = 0;
            $signedDeviceCount = 0;
            $signCategoryStats = [];
            
            if (!empty($signedOrderIds)) {
                $signedOrderCount = count($signedOrderIds);
                $deviceWhere = [
                    ['site_id', '=', $this->site_id],
                    ['order_id', 'in', $signedOrderIds],
                ];
                
                $signedDeviceCount = RecycleDevice::where($deviceWhere)->count();
                
                $categoryStats = RecycleDevice::where($deviceWhere)
                    ->field('category_id, COUNT(*) as count')
                    ->group('category_id')
                    ->select()
                    ->toArray();
                
                foreach ($categoryStats as $stat) {
                    $signCategoryStats[] = [
                        'category_name' => $this->getCategoryName((int)$stat['category_id']),
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
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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
                $shouldInclude = ($signedOrderCount > 0 || $checkCount > 0 || $priceCount > 0 || $paymentCount > 0);
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
                          ->join($this->orderTable() . ' o', 'd.order_id = o.id')
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
            $where[] = ['sign_at', 'between', $timeWhere];
        }
        
        // 统计签收订单数量
        $signedOrderCount = RecycleOrder::where($where)
            ->where('status', '>=', RecycleOrderDict::ORDER_STATUS_SIGNED)
            ->where('sign_at', '>', 0)
            ->count();
        
        // 统计签收设备数量
        $deviceWhere = [
            ['d.site_id', '=', $this->site_id]
        ];
        
        if (!empty($timeWhere)) {
            $deviceWhere[] = ['o.sign_at', 'between', $timeWhere];
        }
        
        if ($categoryId > 0) {
            $deviceWhere[] = ['d.category_id', '=', $categoryId];
        }
        
        $signedDeviceCount = RecycleDevice::alias('d')
            ->join($this->orderTable() . ' o', 'd.order_id = o.id')
            ->where($deviceWhere)
            ->where('o.status', '>=', RecycleOrderDict::ORDER_STATUS_SIGNED)
            ->where('o.sign_at', '>', 0)
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
                $deviceWhere[] = ['o.sign_at', 'between', $timeWhere];
            }
            
            // 统计该分类的签收设备数量
            $signedDeviceCount = RecycleDevice::alias('d')
                ->join($this->orderTable() . ' o', 'd.order_id = o.id')
                ->where($deviceWhere)
                ->where('o.status', '>=', RecycleOrderDict::ORDER_STATUS_SIGNED)
                ->where('o.sign_at', '>', 0)
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
