# 统计功能最终修复说明

## 修复的问题

### 1. 构造函数参数错误
**错误信息**: `Too few arguments to function core\base\BaseController::__construct()`
**修复**: 在 `Stats.php` 控制器中正确传递 `App $app` 参数

### 2. 角色名称显示错误
**问题**: 所有用户都显示为"超级管理员"
**修复**: 重构角色判断逻辑，正确获取用户的真实角色名称

### 3. 用户统计返回空数据
**问题**: `getUserStats` 接口返回 `{"data":[],"msg":"操作成功","code":1}`
**原因**: 用户ID查询逻辑有问题，无法正确获取有操作记录的用户
**修复**: 
- 分别查询有质检操作和定价操作的用户ID
- 合并并去重用户ID列表
- 只有有实际操作记录的用户才会出现在统计结果中

### 4. 分类统计不显示
**问题**: `showCategoryStats` 初始为 `false`，导致分类统计不加载
**原因**: 前端的计算属性依赖于异步获取的用户角色
**修复**: 调整数据获取顺序，在获取用户角色后再决定是否显示分类统计

### 5. 排行榜参数类型错误
**错误信息**: `limit(): Argument #1 ($offset) must be of type int, string given`
**修复**: 将 `limit` 参数强制转换为整数类型

## 修复详情

### 后端修复 (`RecycleStatsService.php`)

1. **用户ID查询优化**:
```php
// 修复前 - 复杂的联合查询
$userIds = RecycleDevice::where($where)
    ->where(function($query) {
        $query->where('check_uid', '>', 0)
              ->whereOr('price_uid', '>', 0);
    })
    ->group('check_uid,price_uid')
    ->column('check_uid,price_uid');

// 修复后 - 分别查询并合并
$checkUserIds = RecycleDevice::where($where)
    ->where('check_uid', '>', 0)
    ->group('check_uid')
    ->column('check_uid');

$priceUserIds = RecycleDevice::where($where)
    ->where('price_uid', '>', 0)
    ->group('price_uid')
    ->column('price_uid');

$allUserIds = array_unique(array_merge($checkUserIds, $priceUserIds));
```

2. **参数类型修复**:
```php
// 修复前
$limit = $params['limit'] ?? 10;

// 修复后
$limit = (int)($params['limit'] ?? 10); // 确保转换为整数类型
```

3. **角色名称获取优化**:
```php
protected function getUserRoleName(int $userId, int $siteId = 0): string
{
    // 1. 获取用户角色信息
    $userRole = $userRoleService->getUserRole($siteId, $userId);
    
    // 2. 区分超级管理员和站点管理员
    if ($userRole['is_admin']) {
        $superAdminUid = $this->getSuperAdminUid();
        return ($userId == $superAdminUid) ? '超级管理员' : '站点管理员';
    }
    
    // 3. 获取真实角色名称
    $roleIds = $userRole['role_ids'] ?? [];
    if (!empty($roleIds)) {
        $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, $siteId);
        if (!empty($roleNames)) {
            return implode('、', $roleNames);
        }
    }
    
    // 4. 根据操作记录推断
    return $this->getRoleNameByOperation($userId);
}
```

### 前端修复 (`dashboard.vue`)

1. **数据获取顺序调整**:
```javascript
// 修复前 - 同步调用所有方法
const fetchData = () => {
    fetchTodayStats()
    fetchCategoryStats()
    fetchUserStats()
    if (userRole.value === 'admin') {
        fetchRankingData()
    }
}

// 修复后 - 先获取角色，再根据角色获取相应数据
const fetchTodayStats = async () => {
    const res = await getTodayStats({})
    if (res.data && res.data.user_role) {
        userRole.value = res.data.user_role
        
        // 角色获取后，重新获取分类统计和排行榜
        fetchCategoryStats()
        if (userRole.value === 'admin') {
            fetchRankingData()
        }
    }
}

const fetchData = () => {
    fetchTodayStats() // 包含角色获取
    fetchUserStats()  // 不依赖角色
}
```

2. **添加调试日志**:
```javascript
console.log('用户角色:', userRole.value)
console.log('检查是否显示分类统计:', showCategoryStats.value, '用户角色:', userRole.value)
console.log('分类统计数据:', categoryStats.value)
console.log('用户统计数据:', userStats.value)
```

## 测试验证

修复后，以下接口应该正常工作：

1. **今日统计**: `GET /adminapi/recycle/stats/getTodayStats`
   - 返回今日工作概览和用户角色信息

2. **用户统计**: `GET /adminapi/recycle/stats/getUserStats?start_time=&end_time=`
   - 返回有操作记录的用户统计数据
   - 正确显示用户角色名称

3. **分类统计**: `GET /adminapi/recycle/stats/getCategoryStats?start_time=&end_time=`
   - 质检员和管理员可见
   - 返回各设备分类的统计数据

4. **排行榜**: `GET /adminapi/recycle/stats/getRankingStats?rank_type=check`
   - 仅管理员可见
   - 支持质检、定价、回收、金额排行

## 功能特性

- ✅ **权限控制**: 基于niucloud官方权限系统
- ✅ **角色识别**: 正确显示用户真实角色名称
- ✅ **数据过滤**: 只显示有操作记录的用户
- ✅ **时间筛选**: 支持日期范围查询
- ✅ **多维统计**: 今日、分类、用户、排行榜统计
- ✅ **实时数据**: 直接从业务表查询，无需预聚合

## 相关文件

- `niucloud/niucloud/addon/recycle/app/service/admin/stats/RecycleStatsService.php` - 统计服务
- `niucloud/niucloud/addon/recycle/app/adminapi/controller/Stats.php` - 统计控制器
- `niucloud/admin/src/addon/recycle/views/stats/dashboard.vue` - 统计页面
- `niucloud/admin/src/addon/recycle/api/stats.ts` - API接口

修复完成后，回收业务统计系统功能完整，可以正常使用。 