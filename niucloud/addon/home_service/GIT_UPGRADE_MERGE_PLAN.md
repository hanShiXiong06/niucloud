# Home Service 插件升级与代码合并方案

> **项目**: niucloud - home_service 插件  
> **目的**: 从官方下载新版本并合并本地二次开发的跑腿功能  
> **创建时间**: 2025-11-02

---

## 📋 目录

- [一、当前状态分析](#一当前状态分析)
- [二、跑腿功能概述](#二跑腿功能概述)
- [三、Git合并方案](#三git合并方案)
- [四、详细操作步骤](#四详细操作步骤)
- [五、冲突处理策略](#五冲突处理策略)
- [六、测试验证清单](#六测试验证清单)
- [七、回滚方案](#七回滚方案)

---

## 一、当前状态分析

### 1.1 Git仓库状态

```bash
分支: main
本地领先远程: 3 个提交
未暂存修改: 多个文件
未跟踪文件: 多个新文件
```

### 1.2 跑腿功能提交历史

| 提交ID | 提交信息 | 说明 |
|--------|---------|------|
| `437a0b2` | feat: 新增跑腿代取件业务功能 | 后端核心逻辑 |
| `43ada35` | feat: 添加校园跑腿功能 - 前端部分 | 前端UI组件 |
| `714a775` | feat: 校园跑腿功能开发前保存 | 基础准备 |

### 1.3 跑腿功能涉及的文件（11个文件，+3215行，-1676行）

#### 📄 新增文档
- `ERRAND_BUSINESS_GUIDE.md` - 跑腿业务集成指南（306行）
- `docs/ERRAND_CONFIG_EXAMPLE.md` - 跑腿配置示例（189行）
- `docs/WxUtil.java` - 微信工具类
- `docs/微信公众号推广文案.md`
- `docs/微信支付V2签名错误排查.md`
- `docs/微信支付参数说明.md`

#### 🗄️ 数据库
- `errand_business_migration.sql` - 跑腿业务数据库迁移脚本（58行）
  - 添加字段：`is_errand`, `errand_items`, `pickup_code`
  - 添加索引：`idx_is_errand`
- `sql/install.sql` - 安装脚本更新

#### 🔧 后端核心文件
- `app/service/core/order/CoreOrderCreateService.php` - 订单创建核心服务（144行修改）
  - 新增 `handleErrandBusinessGoods()` 方法
  - 修改 `calculate()` 方法
  - 修改 `getGoodsData()` 方法
  - 修改 `create()` 方法
- `app/service/api/order/OrderService.php` - 订单服务（1052行重构）
- `app/service/api/order/OrderCreateService.php` - 订单创建服务（1行）
- `app/api/controller/order/OrderCreate.php` - 订单创建控制器
- `app/model/order/Order.php` - 订单模型
- `app/service/admin/goods_category/GoodsCategoryService.php` - 商品分类服务
- `app/service/api/goods/GoodsCategoryService.php` - 商品分类API服务
- `app/service/api/goods/GoodsService.php` - 商品服务
- `app/adminapi/controller/category/Category.php` - 分类控制器

#### 🎨 前端核心文件
- `uni-app/user/components/errand-order-form/errand-order-form.vue` - 跑腿订单表单组件（新增，785行）
- `uni-app/user/pages/goods/detail.vue` - 商品详情页（2247行重构）
- `uni-app/user/pages/order/detail.vue` - 订单详情页（26行修改）
- `uni-app/user/pages/order/payment.vue` - 支付页面（82行修改）

#### ⚙️ 配置文件
- `admin/views/goods/components/category-edit.vue` - 后台分类编辑
- `info.json` - 插件信息

---

## 二、跑腿功能概述

### 2.1 核心特性

✅ **多包裹支持**: 一个订单可以包含多个包裹，每个包裹独立定价  
✅ **取件码系统**: 每个包裹有独立的取件码，方便骑手识别  
✅ **收货地址**: 需要填写收货地址（骑手配送目的地）  
✅ **优惠兼容**: 完全兼容原系统的优惠券、次卡、会员折扣等功能  
✅ **服务类型**: 支持不同快递公司和包裹大小（如"邮政｜小件"、"中通｜大件"）

### 2.2 技术实现要点

#### 数据库改动
```sql
-- 订单表新增字段
ALTER TABLE `home_service_order` 
ADD COLUMN `is_errand` TINYINT(1) DEFAULT 0 COMMENT '是否为跑腿业务',
ADD COLUMN `errand_items` TEXT NULL COMMENT '跑腿包裹信息JSON',
ADD INDEX `idx_is_errand` (`is_errand`);

-- 订单项表新增字段
ALTER TABLE `home_service_order_item` 
ADD COLUMN `pickup_code` VARCHAR(100) NULL COMMENT '取件码或快递单号';
```

#### 核心业务逻辑
1. **订单计算** (`CoreOrderCreateService::calculate`)
   - 检测 `type === 'errand'` 识别跑腿订单
   - 调用 `handleErrandBusinessGoods()` 处理多包裹
   - 正常计算优惠（优惠按比例分摊）

2. **订单创建** (`CoreOrderCreateService::create`)
   - 为每个包裹创建独立的订单项
   - 保存 `errand_items` JSON到订单表
   - 保存 `pickup_code` 到订单项表

3. **前端组件** (`errand-order-form.vue`)
   - 支持动态添加/删除包裹
   - 每个包裹选择服务类型、填写取件码
   - 实时计算总价

---

## 三、Git合并方案

### 3.1 推荐方案：分支隔离 + Cherry-pick

**核心思路**: 使用Git分支管理，将跑腿功能独立到特性分支，升级官方代码后再合并。

```
官方新版本 (official-new)
    ↓
合并到主分支 (main)
    ↓
Cherry-pick 跑腿功能 (errand-feature)
    ↓
最终版本 (main + 跑腿功能)
```

### 3.2 方案对比

| 方案 | 优点 | 缺点 | 推荐度 |
|-----|------|------|--------|
| **方案A: 分支隔离** | 清晰、可控、易回滚 | 需要手动解决冲突 | ⭐⭐⭐⭐⭐ |
| 方案B: Stash暂存 | 简单快速 | 容易丢失代码 | ⭐⭐⭐ |
| 方案C: 补丁文件 | 便于分享 | 不利于版本管理 | ⭐⭐ |

---

## 四、详细操作步骤

### 📍 阶段一：准备工作（备份）

#### 步骤1：备份当前代码

```bash
# 进入插件目录
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service

# 创建完整备份
cd ../../../../
cp -r niucloud niucloud_backup_$(date +%Y%m%d_%H%M%S)

# 或使用压缩备份
tar -czf niucloud_backup_$(date +%Y%m%d_%H%M%S).tar.gz niucloud/

# 确认备份
ls -lh niucloud_backup*
```

#### 步骤2：提交当前所有修改

```bash
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service

# 查看当前状态
git status

# 添加所有修改（包括未跟踪文件）
git add -A

# 提交（包含详细说明）
git commit -m "feat: 跑腿功能完整版本 - 升级前保存

包含以下功能：
- 多包裹订单支持
- 取件码系统
- 前端errand-order-form组件
- 后端订单创建逻辑
- 数据库迁移脚本
- 完整文档

修改文件：11个
新增代码：+3215行
删除代码：-1676行
"

# 确认提交成功
git log -1
```

### 📍 阶段二：创建特性分支（保护跑腿功能）

#### 步骤3：创建跑腿功能分支

```bash
# 创建并切换到跑腿功能分支
git checkout -b feature/errand-business

# 确认当前分支
git branch

# 标记这个提交点（便于后续查找）
git tag -a v1.0.0-errand -m "跑腿功能完整版本"

# 查看标签
git tag -l
```

#### 步骤4：切回主分支，重置到官方版本

```bash
# 切回主分支
git checkout main

# 如果需要，可以重置到升级前的某个干净提交
# git reset --hard <官方最后一次提交的hash>

# 或者保留当前状态，准备接收官方新版本
```

### 📍 阶段三：升级官方代码

#### 步骤5：备份现有插件并下载新版本

```bash
# 方式A：如果官方有Git仓库
# 添加官方仓库为远程源
git remote add official https://github.com/niucloud/home_service.git
git fetch official

# 查看官方分支
git branch -r

# 合并官方最新版本（这里会产生冲突）
git merge official/main --allow-unrelated-histories

# 方式B：如果官方只有压缩包
# 1. 手动下载官方最新版本到临时目录
# 2. 删除本地插件（除了.git目录）
# 3. 复制新版本文件到插件目录
# 4. 提交新版本

# 假设官方新版本在 /path/to/official_home_service_new/
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon

# 备份.git目录
cp -r home_service/.git home_service_git_backup/

# 删除旧文件（保留.git）
find home_service -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# 复制新版本文件（假设下载到了这个路径）
# cp -r /path/to/official_home_service_new/* home_service/

# 提交官方新版本
cd home_service
git add -A
git commit -m "upgrade: 升级到官方最新版本

官方版本号: v2.x.x
下载时间: $(date +%Y-%m-%d)
来源: 官方应用市场

注意：本次升级会覆盖所有文件，跑腿功能将在后续步骤中合并
"
```

### 📍 阶段四：合并跑腿功能

#### 步骤6：识别跑腿功能的提交

```bash
# 查看跑腿功能分支的提交
git log feature/errand-business --oneline -10

# 查看跑腿功能的具体提交（从第一个到最后一个）
# 437a0b2 feat: 新增跑腿代取件业务功能
# 43ada35 feat: 添加校园跑腿功能 - 前端部分
# 714a775 feat: 校园跑腿功能开发前保存

# 或者查看特性分支和主分支的差异
git log main..feature/errand-business --oneline
```

#### 步骤7：Cherry-pick跑腿功能（推荐）

```bash
# 确保在main分支
git checkout main

# 方式A: 逐个cherry-pick（更安全，便于处理冲突）
git cherry-pick 714a775  # 第一个跑腿相关提交
# 如有冲突，解决后继续
git cherry-pick 43ada35  # 第二个提交
git cherry-pick 437a0b2  # 第三个提交

# 方式B: 一次性cherry-pick（如果确定没有冲突）
git cherry-pick 714a775^..437a0b2

# 方式C: 使用merge（如果想保留完整历史）
git merge feature/errand-business -m "merge: 合并跑腿功能到升级后的版本"
```

### 📍 阶段五：解决冲突

#### 步骤8：处理合并冲突

当出现冲突时，Git会提示类似：
```
CONFLICT (content): Merge conflict in app/service/core/order/CoreOrderCreateService.php
```

**冲突处理流程：**

```bash
# 1. 查看冲突文件列表
git status

# 2. 对于每个冲突文件，打开编辑器处理
# 冲突标记格式：
# <<<<<<< HEAD
# [官方新版本的代码]
# =======
# [你的跑腿功能代码]
# >>>>>>> 437a0b2

# 3. 常见冲突类型及处理策略（见下一节）

# 4. 标记冲突已解决
git add <解决后的文件>

# 5. 继续cherry-pick
git cherry-pick --continue

# 如果想放弃本次cherry-pick
# git cherry-pick --abort
```

#### 步骤9：验证代码完整性

```bash
# 检查跑腿功能的关键文件是否存在
ls -la uni-app/user/components/errand-order-form/
cat app/service/core/order/CoreOrderCreateService.php | grep "handleErrandBusinessGoods"
cat app/model/order/Order.php | grep "is_errand"

# 检查数据库迁移脚本
cat errand_business_migration.sql

# 查看最终的文件状态
git status
```

---

## 五、冲突处理策略

### 5.1 核心原则

1. **优先保留跑腿功能**：跑腿功能是你的核心二次开发，必须保留
2. **兼容官方新特性**：尽量保留官方新增的功能和修复
3. **测试驱动**：每解决一个冲突，立即测试该功能

### 5.2 按文件类型处理

#### 📄 类型1：后端PHP文件冲突

**文件**: `app/service/core/order/CoreOrderCreateService.php`

**可能冲突点**:
- `calculate()` 方法
- `getGoodsData()` 方法
- `create()` 方法

**处理策略**:
```php
// ❌ 错误：直接选择一方
<<<<<<< HEAD
public function calculate() {
    // 官方新版本代码
}
=======
public function calculate() {
    // 你的跑腿功能代码
}
>>>>>>> 437a0b2

// ✅ 正确：合并两者逻辑
public function calculate() {
    // 保留官方的新增逻辑（如果有）
    // + 添加跑腿功能的检测
    
    // 检测跑腿业务
    if (isset($this->params['sku']['type']) && $this->params['sku']['type'] === 'errand') {
        // 跑腿业务专属逻辑
        $this->handleErrandBusinessGoods();
    } else {
        // 原有逻辑
    }
}
```

**关键点**:
- 确保 `handleErrandBusinessGoods()` 方法完整保留
- 跑腿业务的条件判断要清晰
- 不要破坏官方的原有逻辑流程

#### 📄 类型2：前端Vue组件冲突

**文件**: `uni-app/user/pages/goods/detail.vue`

**可能冲突点**:
- `<template>` 部分的HTML结构
- `<script>` 部分的data和methods
- 组件引入

**处理策略**:
```vue
<!-- 1. 保留跑腿订单表单组件的引入 -->
<script>
import ErrandOrderForm from '@/addon/home_service/uni-app/user/components/errand-order-form/errand-order-form.vue'

export default {
  components: {
    ErrandOrderForm,  // 必须保留
    // 官方新增的其他组件
  }
}
</script>

<!-- 2. 合并template，添加条件渲染 -->
<template>
  <view>
    <!-- 官方原有UI -->
    
    <!-- 跑腿功能：条件显示 -->
    <errand-order-form 
      v-if="isErrandBusiness" 
      :sku-list="detail.skuList"
      @submit="handleErrandSubmit"
    />
    
    <!-- 原有的预约表单 -->
    <view v-else>
      <!-- 官方的预约UI -->
    </view>
  </view>
</template>
```

#### 📄 类型3：数据库脚本冲突

**文件**: `sql/install.sql`

**处理策略**:
```sql
-- 如果官方也修改了install.sql，确保包含跑腿字段

-- 方式1: 在官方的表结构中添加跑腿字段
CREATE TABLE `home_service_order` (
  -- 官方原有字段
  `id` int(11) NOT NULL AUTO_INCREMENT,
  -- ...
  
  -- 跑腿功能字段（添加到合适位置）
  `is_errand` tinyint(1) DEFAULT 0 COMMENT '是否为跑腿业务',
  `errand_items` text COMMENT '跑腿包裹信息JSON',
  
  PRIMARY KEY (`id`),
  KEY `idx_is_errand` (`is_errand`)  -- 添加索引
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 方式2: 保留独立的迁移脚本
-- 保持 errand_business_migration.sql 独立存在
-- 在安装时执行两个脚本
```

#### 📄 类型4：配置文件冲突

**文件**: `info.json`

**处理策略**:
```json
{
  "name": "home_service",
  "title": "上门家政服务",
  "version": "2.0.0",  // 使用官方的版本号
  "description": "上门家政服务系统（含跑腿功能）",  // 添加说明
  
  // 合并官方和你的配置
  "features": [
    // 官方的功能列表
    "订单管理",
    "技师管理",
    
    // 你的跑腿功能
    "校园跑腿",
    "快递代取"
  ]
}
```

### 5.3 智能冲突解决工具

```bash
# 使用图形化工具解决冲突（推荐）
# VS Code内置了很好的合并工具
code .

# 或使用专业的合并工具
# Mac上推荐：
# - Kaleidoscope
# - Beyond Compare
# - Meld

# 配置Git使用外部合并工具
git config --global merge.tool vscode
git config --global mergetool.vscode.cmd 'code --wait $MERGED'

# 使用合并工具
git mergetool
```

### 5.4 具体文件冲突处理建议

| 文件 | 冲突可能性 | 处理建议 |
|------|-----------|---------|
| `CoreOrderCreateService.php` | ⚠️ 高 | 仔细合并，保留跑腿逻辑 |
| `OrderService.php` | ⚠️ 高 | 检查方法签名变化 |
| `goods/detail.vue` | ⚠️ 中 | 保留errand-order-form组件 |
| `order/payment.vue` | ⚠️ 中 | 保留跑腿订单数据处理 |
| `errand-order-form.vue` | ✅ 无 | 新增文件，无冲突 |
| `ERRAND_*.md` | ✅ 无 | 文档文件，无冲突 |
| `errand_business_migration.sql` | ✅ 无 | 独立脚本，无冲突 |

---

## 六、测试验证清单

### 6.1 代码完整性检查

```bash
# 1. 检查关键文件是否存在
test -f "ERRAND_BUSINESS_GUIDE.md" && echo "✅ 文档存在" || echo "❌ 文档缺失"
test -f "errand_business_migration.sql" && echo "✅ 迁移脚本存在" || echo "❌ 脚本缺失"
test -f "uni-app/user/components/errand-order-form/errand-order-form.vue" && echo "✅ 组件存在" || echo "❌ 组件缺失"

# 2. 检查关键代码是否存在
grep -q "handleErrandBusinessGoods" app/service/core/order/CoreOrderCreateService.php && echo "✅ 方法存在" || echo "❌ 方法缺失"
grep -q "is_errand" app/model/order/Order.php && echo "✅ 字段定义存在" || echo "❌ 字段缺失"

# 3. 统计代码行数变化
echo "当前目录文件统计："
find . -type f \( -name "*.php" -o -name "*.vue" -o -name "*.sql" \) | wc -l
```

### 6.2 数据库验证

```sql
-- 连接数据库后执行

-- 1. 检查订单表字段
SHOW FULL COLUMNS FROM `home_service_order` WHERE `Field` IN ('is_errand', 'errand_items');

-- 2. 检查订单项表字段
SHOW FULL COLUMNS FROM `home_service_order_item` WHERE `Field` = 'pickup_code';

-- 3. 检查索引
SHOW INDEX FROM `home_service_order` WHERE `Key_name` = 'idx_is_errand';

-- 预期结果：3条记录
```

### 6.3 功能测试清单

#### ✅ 基础功能测试

- [ ] **安装测试**: 在测试环境全新安装插件，检查数据库表是否正确创建
- [ ] **官方功能**: 测试官方原有的家政服务功能是否正常
  - [ ] 商品列表加载
  - [ ] 商品详情查看
  - [ ] 普通订单创建
  - [ ] 订单支付流程
  - [ ] 订单状态流转
  - [ ] 技师接单
  - [ ] 评价系统

#### ✅ 跑腿功能测试

- [ ] **商品识别**: 进入跑腿商品详情页，是否正确显示跑腿订单表单
- [ ] **包裹管理**: 
  - [ ] 添加包裹
  - [ ] 删除包裹
  - [ ] 修改包裹信息
  - [ ] 填写取件码
- [ ] **价格计算**:
  - [ ] 单包裹价格正确
  - [ ] 多包裹总价正确
  - [ ] 使用优惠券后价格正确
  - [ ] 会员折扣生效
- [ ] **订单创建**:
  - [ ] 跑腿订单创建成功
  - [ ] 订单数据完整（is_errand=1）
  - [ ] errand_items JSON正确保存
  - [ ] 订单项中pickup_code正确保存
- [ ] **订单详情**:
  - [ ] 用户端显示包裹列表
  - [ ] 显示取件码
  - [ ] 显示收货地址
- [ ] **骑手端**:
  - [ ] 接单后可以看到所有包裹
  - [ ] 取件码清晰显示
  - [ ] 完成服务流程

#### ✅ 兼容性测试

- [ ] **优惠系统**:
  - [ ] 跑腿订单可以使用优惠券
  - [ ] 优惠金额正确分摊到各包裹
  - [ ] 次卡支持（如果适用）
- [ ] **支付系统**:
  - [ ] 微信支付
  - [ ] 支付宝支付
  - [ ] 余额支付
- [ ] **退款系统**:
  - [ ] 跑腿订单可以申请退款
  - [ ] 退款金额计算正确
  - [ ] 退款流程完整

### 6.4 性能测试

```bash
# 1. 代码质量检查（如果有PHP CodeSniffer）
phpcs app/service/core/order/CoreOrderCreateService.php

# 2. 查看日志是否有错误
tail -f runtime/log/*.log

# 3. 前端构建测试
cd uni-app
npm run build:h5
# 检查是否有编译错误
```

---

## 七、回滚方案

### 7.1 快速回滚

如果合并后发现严重问题，可以快速回滚：

```bash
# 方案A: 使用备份恢复（最安全）
cd /Users/a123/Documents/1-work/niucloud
rm -rf niucloud/
cp -r niucloud_backup_YYYYMMDD_HHMMSS niucloud/

# 方案B: Git回滚到合并前
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service
git reflog  # 查看操作历史
git reset --hard HEAD@{n}  # n是合并前的位置

# 方案C: 回滚到特定提交
git reset --hard <升级前的commit_id>

# 方案D: 回滚到标签
git reset --hard v1.0.0-errand
```

### 7.2 数据库回滚

```sql
-- 如果需要移除跑腿功能的数据库字段
ALTER TABLE `home_service_order` 
DROP COLUMN `is_errand`,
DROP COLUMN `errand_items`,
DROP INDEX `idx_is_errand`;

ALTER TABLE `home_service_order_item` 
DROP COLUMN `pickup_code`;
```

### 7.3 保留日志

```bash
# 保存操作日志，便于问题追踪
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service

# 导出合并日志
git log --oneline --graph --all > merge_log_$(date +%Y%m%d).txt

# 导出冲突解决记录
git diff HEAD~1 HEAD > merge_diff_$(date +%Y%m%d).txt
```

---

## 八、注意事项与最佳实践

### ⚠️ 重要注意事项

1. **不要在生产环境直接操作**
   - 先在开发环境完成合并和测试
   - 测试通过后再部署到生产环境

2. **备份是必须的**
   - 代码备份
   - 数据库备份
   - 配置文件备份

3. **分步进行，逐步验证**
   - 每完成一个阶段就测试
   - 不要一口气做完所有操作

4. **保留原始版本**
   - 保留官方原始包
   - 保留你的原始代码
   - 至少保留3个版本

### 💡 最佳实践

1. **使用分支管理**
   ```
   main            - 稳定版本
   develop         - 开发版本
   feature/*       - 功能分支
   hotfix/*        - 紧急修复
   official/*      - 官方版本
   ```

2. **语义化提交信息**
   ```
   feat:     新功能
   fix:      修复bug
   docs:     文档更新
   style:    代码格式
   refactor: 重构
   test:     测试
   chore:    构建/工具
   ```

3. **定期同步官方更新**
   ```bash
   # 每月检查一次官方更新
   git fetch official
   git log official/main
   ```

4. **文档同步更新**
   - 每次修改都更新README
   - 记录版本变更
   - 保留升级日志

---

## 九、常见问题FAQ

### Q1: 合并时出现大量冲突怎么办？

**A**: 分文件逐个处理，优先处理核心文件：
1. 先处理数据模型层（Model）
2. 再处理服务层（Service）
3. 最后处理控制器和视图

### Q2: 如何确保跑腿功能没有被覆盖？

**A**: 使用检查脚本：
```bash
#!/bin/bash
# check_errand_feature.sh

echo "检查跑腿功能完整性..."

# 检查关键文件
files=(
  "ERRAND_BUSINESS_GUIDE.md"
  "errand_business_migration.sql"
  "uni-app/user/components/errand-order-form/errand-order-form.vue"
)

for file in "${files[@]}"; do
  if [ -f "$file" ]; then
    echo "✅ $file"
  else
    echo "❌ $file 缺失！"
  fi
done

# 检查关键代码
if grep -q "handleErrandBusinessGoods" app/service/core/order/CoreOrderCreateService.php; then
  echo "✅ 跑腿核心方法存在"
else
  echo "❌ 跑腿核心方法缺失！"
fi
```

### Q3: 官方新版本和跑腿功能冲突很大，难以合并？

**A**: 考虑重构跑腿功能：
1. 将跑腿功能做成独立模块
2. 使用事件监听的方式集成
3. 最小化对核心文件的修改

### Q4: 如何追踪哪些代码是跑腿功能的？

**A**: 使用代码注释标记：
```php
// ========== START: ERRAND BUSINESS ==========
public function handleErrandBusinessGoods() {
    // 跑腿业务专属代码
}
// ========== END: ERRAND BUSINESS ==========
```

### Q5: 合并后性能下降怎么办？

**A**: 性能优化检查清单：
1. 检查数据库索引是否正确添加
2. 检查是否有N+1查询问题
3. 使用缓存优化频繁查询
4. 前端组件是否按需加载

---

## 十、后续维护建议

### 10.1 版本管理

建议使用以下版本号规则：
```
官方版本 + 自定义功能标识

示例：
v2.0.0-errand.1   - 基于官方2.0.0，跑腿功能第1版
v2.0.0-errand.2   - 基于官方2.0.0，跑腿功能第2版（修复bug）
v2.1.0-errand.1   - 升级到官方2.1.0，跑腿功能第1版
```

### 10.2 代码同步策略

```bash
# 1. 每季度检查官方更新
# 2. 评估更新内容
# 3. 在测试环境合并
# 4. 完整测试后发布

# 创建定期任务
# crontab -e
# 0 0 1 */3 * /path/to/check_official_update.sh
```

### 10.3 团队协作

如果是团队开发：
1. 指定专人负责官方版本追踪
2. 跑腿功能独立为子模块
3. 使用Git Submodule或Subtree
4. 定期Code Review

---

## 📞 支持与帮助

- **文档位置**: `/addon/home_service/GIT_UPGRADE_MERGE_PLAN.md`
- **相关文档**: 
  - `ERRAND_BUSINESS_GUIDE.md` - 跑腿业务集成指南
  - `docs/ERRAND_CONFIG_EXAMPLE.md` - 配置示例
- **问题反馈**: 记录在项目Issues中

---

## 📋 检查清单

升级完成前，请确认：

- [ ] ✅ 已完整备份代码和数据库
- [ ] ✅ 已创建跑腿功能分支 (feature/errand-business)
- [ ] ✅ 已下载官方最新版本
- [ ] ✅ 已合并官方代码到main分支
- [ ] ✅ 已cherry-pick跑腿功能
- [ ] ✅ 已解决所有代码冲突
- [ ] ✅ 已执行数据库迁移脚本
- [ ] ✅ 已通过所有功能测试
- [ ] ✅ 已在测试环境验证
- [ ] ✅ 已更新相关文档
- [ ] ✅ 已通知团队成员

---

**最后更新**: 2025-11-02  
**文档版本**: v1.0  
**作者**: AI Assistant  
**审核**: 待审核

---

**祝升级顺利！🎉**

如有任何问题，请参考本文档或查看相关技术文档。

