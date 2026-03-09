# Git 工作流程指南

## 分支说明

- **official-base** - 官方框架代码快照（只记录官方更新）
- **dev** - 主开发分支（包含所有自定义代码）
- **main** - 生产环境分支
- **feature/xxx** - 功能开发分支

## 日常开发流程

### 1. 开发新功能

```bash
# 在 dev 分支开发
git checkout dev

# 开发代码...

# 提交
git add .
git commit -m "feat(插件名): 功能描述"
git push origin dev
```

### 2. 官方框架更新（重要）

当通过系统按钮下载官方更新后：

```bash
# 步骤1: 切换到 official-base 分支
git checkout official-base

# 步骤2: 在系统中点击"更新框架"按钮，下载官方更新

# 步骤3: 提交官方更新
git add .
git commit -m "chore(framework): 官方框架更新 - $(date +%Y-%m-%d)"
git tag official-v版本号  # 例如: official-v1.2.3
git push origin official-base --tags

# 步骤4: 合并到开发分支
git checkout dev
git merge official-base
# 如果有冲突，解决冲突后：
git add .
git commit -m "chore: 合并官方框架更新"
git push origin dev
```

### 3. 安装官方插件

```bash
git checkout dev
# 在系统中安装官方插件...
git add niucloud/addon/插件名/
git commit -m "chore(addon): 安装官方插件 [插件名] v版本号"
git push origin dev
```

### 4. 开发自定义插件

```bash
git checkout dev
# 开发插件代码...
git add niucloud/addon/你的插件名/
git commit -m "feat(你的插件名): 功能描述"
git push origin dev
```

## 提交信息规范

```bash
# 新功能
feat(模块名): 功能描述

# Bug 修复
fix(模块名): 问题描述

# 代码样式调整
style(模块名): 样式调整描述

# 重构
refactor(模块名): 重构描述

# 官方更新
chore(framework): 官方框架更新
chore(addon): 安装官方插件

# 修改官方代码（不推荐，但如果必须）
fix(framework): 修复描述 [CUSTOM]
```

## 查看历史

```bash
# 查看官方更新历史
git checkout official-base
git log --oneline

# 查看所有标签
git tag -l

# 对比官方代码和开发代码
git diff official-base dev

# 查看某个文件的官方版本
git show official-base:文件路径
```

## 回滚操作

```bash
# 回滚到某个官方版本
git checkout official-base
git reset --hard official-v版本号

# 回滚开发分支到某个提交
git checkout dev
git reset --hard 提交hash
```

## 当前状态

- 已创建 `official-base` 分支
- 已打标签 `official-snapshot-2026-03-09`
- 当前在 `dev` 分支开发

---
创建时间: 2026-03-09
