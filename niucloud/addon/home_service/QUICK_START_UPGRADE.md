# 🚀 快速升级指南 - 5分钟版

> 如需详细说明，请查看 `GIT_UPGRADE_MERGE_PLAN.md`

---

## 📝 升级前准备

```bash
# 1. 进入插件目录
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service

# 2. 备份整个项目
cd ../../../../
tar -czf niucloud_backup_$(date +%Y%m%d_%H%M%S).tar.gz niucloud/
```

---

## 🔧 核心操作步骤

### 步骤1: 提交当前代码
```bash
cd /Users/a123/Documents/1-work/niucloud/niucloud/niucloud/addon/home_service
git add -A
git commit -m "feat: 跑腿功能完整版 - 升级前保存"
```

### 步骤2: 创建跑腿功能保护分支
```bash
git checkout -b feature/errand-business
git tag -a v1.0.0-errand -m "跑腿功能完整版本"
git checkout main
```

### 步骤3: 升级官方代码（二选一）

#### 方式A: 官方有Git仓库
```bash
git remote add official <官方仓库地址>
git fetch official
git merge official/main --allow-unrelated-histories
```

#### 方式B: 官方只有压缩包（推荐）
```bash
# ⚠️ 先手动下载官方最新版到临时目录

# 备份.git目录
cp -r .git ../home_service_git_backup/

# 删除所有文件（除了.git）
find . -mindepth 1 -maxdepth 1 ! -name '.git' -exec rm -rf {} +

# 复制新版本文件
cp -r <官方新版本路径>/* .

# 提交官方新版本
git add -A
git commit -m "upgrade: 升级到官方最新版本"
```

### 步骤4: 合并跑腿功能
```bash
# 查看跑腿功能的提交ID
git log feature/errand-business --oneline -5

# Cherry-pick跑腿功能（逐个，便于处理冲突）
git cherry-pick 714a775  # 第一个提交
git cherry-pick 43ada35  # 第二个提交
git cherry-pick 437a0b2  # 第三个提交
```

### 步骤5: 处理冲突（如有）
```bash
# 查看冲突文件
git status

# 编辑冲突文件，手动合并代码
# 保留跑腿功能的关键代码：
# - handleErrandBusinessGoods() 方法
# - errand相关的条件判断
# - errand-order-form组件

# 标记冲突已解决
git add <文件名>
git cherry-pick --continue
```

### 步骤6: 验证完整性
```bash
# 检查关键文件
test -f "errand_business_migration.sql" && echo "✅ 迁移脚本存在"
test -f "uni-app/user/components/errand-order-form/errand-order-form.vue" && echo "✅ 组件存在"
grep -q "handleErrandBusinessGoods" app/service/core/order/CoreOrderCreateService.php && echo "✅ 核心方法存在"

# 查看最终状态
git log --oneline --graph -10
```

---

## ✅ 升级完成检查清单

- [ ] 已完整备份
- [ ] 已创建feature/errand-business分支
- [ ] 已合并官方代码
- [ ] 已cherry-pick跑腿功能
- [ ] 已解决所有冲突
- [ ] 核心文件完整性检查通过
- [ ] 数据库迁移脚本存在

---

## 🧪 测试步骤

```bash
# 1. 执行数据库迁移
# 在数据库中执行: errand_business_migration.sql

# 2. 启动测试环境

# 3. 测试跑腿功能
# - 进入跑腿商品详情页
# - 添加多个包裹
# - 填写取件码
# - 创建订单
# - 查看订单详情
# - 确认pickup_code正确保存

# 4. 测试原有功能
# - 普通家政服务下单
# - 订单流转
# - 技师接单
```

---

## ⚠️ 遇到问题？

### 场景1: 冲突太多，无法处理
```bash
# 放弃当前合并
git cherry-pick --abort

# 回到升级前
git reset --hard v1.0.0-errand

# 联系技术支持或查看详细文档
```

### 场景2: 跑腿功能丢失
```bash
# 检查feature分支
git checkout feature/errand-business
git log

# 重新cherry-pick
git checkout main
git cherry-pick <commit-id>
```

### 场景3: 需要完全回滚
```bash
# 使用备份恢复
cd /Users/a123/Documents/1-work/niucloud
rm -rf niucloud/
tar -xzf niucloud_backup_<时间戳>.tar.gz
```

---

## 📋 关键文件对照表

| 文件 | 状态 | 说明 |
|------|-----|------|
| `ERRAND_BUSINESS_GUIDE.md` | 必须存在 | 业务文档 |
| `errand_business_migration.sql` | 必须存在 | 数据库脚本 |
| `uni-app/user/components/errand-order-form/` | 必须存在 | 前端组件 |
| `CoreOrderCreateService.php` | 必须包含跑腿逻辑 | 后端核心 |
| `goods/detail.vue` | 必须引入errand组件 | 前端页面 |

---

## 🔍 快速验证命令

```bash
#!/bin/bash
echo "=== 跑腿功能完整性检查 ==="

# 检查文档
[ -f "ERRAND_BUSINESS_GUIDE.md" ] && echo "✅ 业务文档" || echo "❌ 业务文档缺失"

# 检查SQL
[ -f "errand_business_migration.sql" ] && echo "✅ 迁移脚本" || echo "❌ 迁移脚本缺失"

# 检查组件
[ -f "uni-app/user/components/errand-order-form/errand-order-form.vue" ] && echo "✅ 订单表单组件" || echo "❌ 组件缺失"

# 检查后端方法
grep -q "handleErrandBusinessGoods" app/service/core/order/CoreOrderCreateService.php && echo "✅ 核心方法" || echo "❌ 核心方法缺失"

# 检查模型字段
grep -q "is_errand" app/model/order/Order.php && echo "✅ 模型字段" || echo "❌ 模型字段缺失"

echo "=== 检查完成 ==="
```

保存为 `check_errand.sh`，然后运行：
```bash
chmod +x check_errand.sh
./check_errand.sh
```

---

## 📞 获取帮助

- 详细文档: `GIT_UPGRADE_MERGE_PLAN.md`
- 业务说明: `ERRAND_BUSINESS_GUIDE.md`
- 配置示例: `docs/ERRAND_CONFIG_EXAMPLE.md`

---

**预计耗时**: 30-60分钟（含测试）  
**难度**: ⭐⭐⭐☆☆  
**风险**: 中（已有完整备份，可随时回滚）

祝升级顺利！🎉


