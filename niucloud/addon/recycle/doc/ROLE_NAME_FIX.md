# 角色名称显示修复说明

## 问题描述

在统计功能中，所有用户的 `user_type_name` 都显示为"管理员"，无法正确显示用户的真实角色名称（如"质检员"等自定义角色）。

## 问题原因

原来的角色判断逻辑存在以下问题：

1. **只基于操作记录推断角色**：没有正确读取niucloud系统中的用户角色配置
2. **没有获取真实角色名称**：没有从`sys_role`表中获取角色的实际名称
3. **权限判断和显示名称混淆**：用于权限判断的角色类型和用于显示的角色名称使用了同一套逻辑

## 修复方案

### 1. 新增角色名称获取方法

添加了 `getUserRoleName()` 方法，专门用于获取用户的真实角色名称：

```php
protected function getUserRoleName(int $userId, int $siteId = 0): string
{
    // 1. 超级管理员判断
    if (AuthService::isSuperAdmin()) {
        return '超级管理员';
    }
    
    // 2. 获取用户角色信息
    $userRoleService = new UserRoleService();
    $userRole = $userRoleService->getUserRole($siteId, $userId);
    
    // 3. 站点管理员判断
    if ($userRole['is_admin']) {
        return '站点管理员';
    }
    
    // 4. 获取真实角色名称
    $roleIds = $userRole['role_ids'] ?? [];
    if (!empty($roleIds)) {
        $roleNames = $userRoleService->getRoleByUserRoleIds($roleIds, $siteId);
        if (!empty($roleNames)) {
            return implode('、', $roleNames); // 多角色用顿号分隔
        }
    }
    
    // 5. 兜底：根据操作记录推断
    // ...
}
```

### 2. 分离权限判断和显示逻辑

- `getCurrentUserRole()` - 用于权限判断，返回简化的角色类型（admin/checker/pricer/user）
- `getUserRoleName()` - 用于显示，返回真实的角色名称（如"质检员"、"估价员"等）

### 3. 修复统计方法

在 `getUserStats()` 和 `getRankingStats()` 方法中，使用新的角色名称获取方法：

```php
// 修复前
'user_type_name' => match($userType) {
    'checker' => '质检员',
    'pricer' => '估价员',
    'admin' => '管理员',
    default => '用户'
},

// 修复后
'user_type_name' => $this->getUserRoleName($uid, $this->site_id),
```

## 修复内容

### 1. 服务层修改 (`RecycleStatsService.php`)

1. **新增依赖**：
   ```php
   use app\service\admin\user\UserRoleService;
   ```

2. **新增方法**：
   - `getUserRoleName()` - 获取用户真实角色名称

3. **修改方法**：
   - `getCurrentUserRole()` - 优化角色类型判断逻辑
   - `getUserStats()` - 使用新的角色名称获取方法
   - `getRankingStats()` - 使用新的角色名称获取方法

### 2. 角色名称获取逻辑

按优先级获取角色名称：

1. **超级管理员** → "超级管理员"
2. **站点管理员** → "站点管理员"  
3. **自定义角色** → 从`sys_role`表获取真实角色名称
4. **操作记录推断** → "质检员"、"估价员"、"管理员"
5. **默认** → "普通用户"

### 3. 多角色支持

如果用户拥有多个角色，使用顿号（、）分隔显示：
- 单角色：`质检员`
- 多角色：`质检员、估价员`

## 测试验证

创建了测试脚本 `test_role_fix.php` 来验证：

1. 用户角色信息获取
2. 角色名称正确显示
3. 多角色情况处理
4. 站点角色列表查询

## 预期效果

修复后，统计功能中的用户角色显示将正确反映：

- **超级管理员** → 显示"超级管理员"
- **站点管理员** → 显示"站点管理员"
- **质检员角色** → 显示"质检员"（或自定义的角色名称）
- **估价员角色** → 显示"估价员"（或自定义的角色名称）
- **多角色用户** → 显示"质检员、估价员"
- **普通用户** → 显示"普通用户"

## 相关文件

- `niucloud/niucloud/addon/recycle/app/service/admin/stats/RecycleStatsService.php` - 主要修复文件
- `niucloud/niucloud/app/service/admin/user/UserRoleService.php` - 角色服务
- `niucloud/niucloud/app/model/sys/SysRole.php` - 角色模型
- `niucloud/niucloud/app/model/sys/SysUserRole.php` - 用户角色关联模型

修复完成后，统计功能将正确显示每个用户的真实角色名称。 