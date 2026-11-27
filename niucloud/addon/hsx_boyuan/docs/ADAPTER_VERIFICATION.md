# 博远适配器验证指南

## 已完成的工作

✅ **BoyuanErpAdapter.php** - 实现了 ErpAdapterInterface 接口
✅ **Addon.php** - 添加了自动注册逻辑
✅ **测试脚本** - 创建了验证脚本

## 验证步骤

### 步骤1：检查代码文件

运行以下命令检查文件：

```bash
# 检查适配器文件是否存在
ls -la /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_boyuan/app/adapter/

# 检查Addon.php是否已修改
grep -n "registerAdapter" /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_boyuan/Addon.php
```

### 步骤2：代码语法检查

```bash
cd /Users/a123/Documents/1-work/niucloud/niucloud

# 检查适配器语法
php -l niucloud/addon/hsx_boyuan/app/adapter/BoyuanErpAdapter.php

# 检查Addon.php语法
php -l niucloud/addon/hsx_boyuan/Addon.php
```

### 步骤3：通过 niucloud 后台操作

#### 方式A：如果有 niucloud 后台权限

1. 登录 niucloud 后台
2. 进入 **应用 → 插件管理**
3. 找到 **hsx_boyuan** (博远ERP)
4. 点击 **升级** 按钮
5. 升级过程中会自动执行 `registerAdapter()` 方法

#### 方式B：通过命令行模拟注册

如果想手动测试注册逻辑，可以创建一个测试脚本。

### 步骤4：验证注册是否成功

创建一个简单的验证脚本来检查适配器是否已注册：

```php
<?php
// test-registry.php

// 模拟注册
require_once 'path/to/AdapterRegistry.php';
require_once 'path/to/BoyuanErpAdapter.php';

$registry = new \addon\hsx_erp_integration\app\service\AdapterRegistry();
$adapter = new \addon\hsx_boyuan\app\adapter\BoyuanErpAdapter();

// 注册
$registry->register($adapter);

// 验证
$list = $registry->getAdapterList();
print_r($list);

// 获取适配器
$boyuan = $registry->getAdapter('boyuan');
echo "适配器名称: " . $boyuan->getAdapterName() . "\n";
echo "支持业务: " . implode(', ', $boyuan->getSupportedBusinessTypes()) . "\n";
```

## 验证检查清单

运行每项检查，确认结果：

- [ ] **文件存在** - BoyuanErpAdapter.php 文件已创建
- [ ] **语法正确** - PHP 语法检查通过
- [ ] **Addon已修改** - Addon.php 包含 registerAdapter 方法
- [ ] **接口实现** - 实现了所有必需的接口方法
- [ ] **可实例化** - 能够成功创建适配器实例
- [ ] **注册成功** - 能够注册到 AdapterRegistry

## 预期结果

如果一切正常，你应该看到：

```
✓ 适配器代码: boyuan
✓ 适配器名称: 博远ERP
✓ 适配器版本: 2.0.0
✓ 支持业务类型: purchase, sale, payment, receipt
✓ 配置字段: 6个字段 (api_url, gateway_path, app_id, app_secret, account_code, mode)
✓ 映射需求: product, warehouse, party, staff
```

## 常见问题

### Q1: 找不到 ErpAdapterInterface 类

**原因:** hsx_erp_integration 插件未安装或未加载

**解决:** 
```bash
# 检查集成框架是否存在
ls -la /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/hsx_erp_integration/
```

### Q2: 注册时报错

**原因:** 可能是命名空间或依赖问题

**解决:** 检查所有 use 语句是否正确

### Q3: 适配器列表为空

**原因:** registerAdapter 方法未被调用

**解决:** 确保通过后台升级插件，或手动调用注册方法

## 下一步

验证通过后，就可以进入第二阶段：**开发PC管理界面**

届时可以通过界面：
1. 配置博远连接
2. 测试连接
3. 查看适配器信息
4. 配置资源映射
