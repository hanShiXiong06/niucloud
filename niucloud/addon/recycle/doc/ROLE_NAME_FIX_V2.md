# 角色名称显示修复说明 V2

## 问题描述

在统计功能中，用户的 `user_type_name` 显示不正确：
- 所有用户都显示为"超级管理员"或"管理员"
- 无法正确显示用户的真实角色名称（如"质检员"等自定义角色）

## 问题分析

从返回的数据可以看到：
```json
{
    "user": {"uid": 1, "username": "admin", "real_name": ""},
    "user_type_name": "超级管理员"
},
{
    "user": {"uid": 2, "username": "hsx", "real_name": "核实下"},
    "user_type_name": "超级管理员"
}
```

两个用户都显示为"超级管理员"，这明显不正确。

## 根本原因

1. **静态方法调用问题**：`AuthService::isSuperAdmin()` 是静态方法，无法正确获取特定用户的信息
2. **角色判断逻辑不准确**：没有正确区分超级管理员和站点管理员
3. **缺少精确的角色查询**：没有正确从数据库获取用户的真实角色信息

## 修复方案 V2

### 1. 重构角色名称获取逻辑

```php
protected function getUserRoleName(int $userId, int $siteId = 0): string
{
    // 1. 获取用户角色信息
    $userRole = $userRoleService->getUserRole($siteId, $userId);
    
    // 2. 没有角色信息 -> 根据操作记录推断
    if (empty($userRole)) {
        return $this->getRoleNameByOperation($userId);
    }
    
    // 3. 是管理员 -> 进一步区分超级管理员和站点管理员
    if ($userRole['is_admin']) {
        $superAdminUid = $this->getSuperAdminUid();
        if ($userId == $superAdminUid) {
            return '超级管理员';
        } else {
            return '站点管理员';
        }
    }
    
    // 4. 有具体角色 -> 获取真实角色名称
    $roleIds = $userRole['role_ids'] ?? [];
    if (!empty($roleIds)) {
        $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, $siteId);
        if (!empty($roleNames)) {
            return implode('、', $roleNames);
        }
    }
    
    // 5. 兜底 -> 根据操作记录推断
    return $this->getRoleNameByOperation($userId);
}
```

### 2. 新增操作记录推断方法

```php
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
```

### 3. 优化超级管理员判断

```php
protected function getSuperAdminUid(int $siteId = 0): int
{
    try {
        $defaultSiteId = request()->defaultSiteId();
    } catch (Exception $e) {
        $defaultSiteId = 0;
    }
    
    $superAdminUid = SysUserRole::where([
        ['site_id', '=', $defaultSiteId],
        ['is_admin', '=', 1]
    ])->value('uid');
    
    return (int)$superAdminUid;
}
```

## 修复逻辑流程

1. **获取用户角色信息** - 从 `sys_user_role` 表获取用户在指定站点的角色信息
2. **判断管理员类型** - 如果 `is_admin=1`，进一步区分超级管理员和站点管理员
3. **获取具体角色名称** - 如果有 `role_ids`，从 `sys_role` 表获取真实角色名称
4. **操作记录推断** - 如果以上都没有，根据用户的操作记录推断角色
5. **默认处理** - 最后返回"普通用户"

## 预期修复效果

修复后，不同用户应该显示正确的角色名称：

- **用户ID 1 (admin)** → 如果是真正的超级管理员，显示"超级管理员"
- **用户ID 2 (hsx)** → 如果是质检员角色，显示"质检员"或具体的自定义角色名称

## 测试建议

1. **检查数据库结构**：
   - 查看 `sys_user_role` 表中用户的角色配置
   - 查看 `sys_role` 表中的角色定义
   - 确认哪个用户是真正的超级管理员

2. **验证角色显示**：
   - 超级管理员应该显示"超级管理员"
   - 站点管理员应该显示"站点管理员"
   - 自定义角色应该显示真实的角色名称
   - 无角色用户根据操作记录推断

## 相关文件

- `RecycleStatsService.php` - 主要修复文件
- `getUserStats()` 和 `getRankingStats()` 方法已更新使用新的角色名称获取逻辑

修复完成后，统计功能应该能正确显示每个用户的真实角色名称。 