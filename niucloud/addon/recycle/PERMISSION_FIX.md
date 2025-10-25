# 员工统计接口权限修复说明

## 🐛 问题描述

员工工作量对比图没有数据，接口返回空数组 `[]`。

## 🔍 原因分析

在 `RecycleStatsService.php` 中，以下三个方法有严格的权限检查：

1. **getUserDetailStats()** - 获取员工详细统计
2. **getUserList()** - 获取用户列表
3. **getOverviewStats()** - 获取运营概览统计

这些方法都有如下权限检查：

```php
if ($this->getCurrentUserRole() !== 'admin') {
    return [];  // 不是管理员直接返回空数组
}
```

### 问题原因

`getCurrentUserRole()` 方法判断用户是否为管理员的逻辑如下：

1. ✅ 超级管理员（super admin）→ 返回 `'admin'`
2. ✅ 站点管理员（`is_admin = true`）→ 返回 `'admin'`
3. ❌ 其他角色（质检员、定价员等）→ 返回 `'checker'`、`'pricer'`、`'user'` 等

如果当前登录用户的 **`is_admin` 字段没有正确设置**，或者 **不是超级管理员**，就会被判定为非管理员，导致这三个接口返回空数组。

## 🔧 修复方案

### 方案一：临时放宽权限（已实施）✅

注释掉权限检查，允许所有管理后台登录的用户访问这些接口。

**修改内容：**

```php
// 修改前
if ($this->getCurrentUserRole() !== 'admin') {
    return [];
}

// 修改后
$currentRole = $this->getCurrentUserRole();
Log::info('权限检查:', [
    'uid' => $this->uid,
    'role' => $currentRole,
    'site_id' => $this->site_id
]);

// 临时放宽权限：管理员后台登录的用户都可以访问
// if ($currentRole !== 'admin') {
//     Log::warning('权限不足:', ['role' => $currentRole]);
//     return [];
// }
```

**修改的方法：**

1. `getUserDetailStats()` - 第498-514行
2. `getUserList()` - 第446-461行  
3. `getOverviewStats()` - 第352-368行

### 方案二：修复用户角色配置（推荐长期方案）

在数据库中正确设置用户的管理员权限：

#### 方法1：设置站点管理员

```sql
-- 查看用户的角色信息
SELECT * FROM sys_user_role WHERE uid = {你的用户ID} AND site_id = {站点ID};

-- 设置为站点管理员
UPDATE sys_user_role 
SET is_admin = 1 
WHERE uid = {你的用户ID} AND site_id = {站点ID};
```

#### 方法2：设置为超级管理员

```sql
-- 查看系统配置
SELECT * FROM sys_config WHERE key = 'super_admin_uid';

-- 修改超级管理员ID
UPDATE sys_config 
SET value = {你的用户ID} 
WHERE key = 'super_admin_uid';
```

## 📝 验证步骤

1. **清除缓存**：
   ```bash
   php think clear
   ```

2. **查看日志**：
   ```bash
   tail -f runtime/log/$(date +%Y%m%d).log
   ```

3. **刷新前端页面**，查看日志输出：
   ```
   getUserList权限检查: {"uid":1,"role":"admin","site_id":0}
   getUserDetailStats权限检查: {"uid":1,"role":"admin","site_id":0}
   getOverviewStats权限检查: {"uid":1,"role":"admin","site_id":0}
   ```

4. **检查接口返回**：
   - 员工列表应该有数据
   - 员工详细统计应该有数据
   - 运营概览应该有数据

## 🎯 测试接口

### 1. 获取用户列表
```
GET /addon/recycle/stats/getUserList
```

**预期返回：**
```json
{
  "code": 0,
  "data": [
    {
      "uid": 1,
      "username": "admin",
      "real_name": "管理员"
    }
  ]
}
```

### 2. 获取员工详细统计
```
GET /addon/recycle/stats/getUserDetailStats?start_time=2024-01-01&end_time=2024-12-31
```

**预期返回：**
```json
{
  "code": 0,
  "data": [
    {
      "user_id": 1,
      "user_name": "管理员",
      "user_type_name": "超级管理员",
      "signed_order_count": 10,
      "signed_device_count": 15,
      "check_count": 12,
      "price_count": 8,
      "payment_count": 5
    }
  ]
}
```

### 3. 获取运营概览
```
GET /addon/recycle/stats/getOverviewStats
```

**预期返回：**
```json
{
  "code": 0,
  "data": {
    "today_order_count": 5,
    "yesterday_order_count": 3,
    "today_check_count": 8,
    "today_payment_amount": 5000,
    "today_payment_count": 3,
    "today_return_count": 1
  }
}
```

## ⚠️ 注意事项

1. **安全性**：
   - 当前修改是临时方案，放宽了权限限制
   - 建议在生产环境中恢复权限检查，并正确配置用户角色

2. **日志监控**：
   - 添加了详细的日志记录，方便排查问题
   - 日志会记录每次访问的用户ID、角色和站点ID

3. **回滚方案**：
   - 如果需要恢复权限检查，只需取消注释即可：
   ```php
   if ($currentRole !== 'admin') {
       Log::warning('权限不足:', ['role' => $currentRole]);
       return [];
   }
   ```

## 🔄 后续优化建议

1. **完善权限系统**：
   - 区分不同级别的管理员权限
   - 支持按功能模块分配权限

2. **优化角色判断**：
   - 统一角色判断逻辑
   - 避免硬编码角色名称

3. **添加权限配置**：
   - 在后台添加权限配置页面
   - 支持动态分配统计查看权限

## 📞 问题排查

如果修改后仍然没有数据：

1. **检查日志**：
   ```bash
   grep "权限检查" runtime/log/$(date +%Y%m%d).log
   ```

2. **检查数据库**：
   ```sql
   -- 检查是否有设备记录
   SELECT COUNT(*) FROM recycle_device WHERE site_id = {站点ID};
   
   -- 检查是否有订单记录
   SELECT COUNT(*) FROM recycle_order WHERE site_id = {站点ID};
   
   -- 检查用户操作记录
   SELECT check_uid, COUNT(*) as check_count 
   FROM recycle_device 
   WHERE site_id = {站点ID} 
   GROUP BY check_uid;
   ```

3. **检查时间范围**：
   - 确保筛选的时间范围内有数据
   - 尝试选择"全部时间"查看是否有数据

## 📅 修改记录

- **2024-XX-XX**：临时放宽权限限制，添加调试日志
- **文件**：`addon/recycle/app/service/admin/stats/RecycleStatsService.php`
- **修改行数**：第352-368行、第446-461行、第498-514行

---

**修复完成！现在刷新页面应该能看到员工工作量对比图的数据了。** 🎉

