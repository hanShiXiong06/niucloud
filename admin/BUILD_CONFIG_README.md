# 构建配置说明

## 概述

本项目提供了可配置的构建脚本，允许您自定义构建输出路径和输出目录名称。

## 配置文件

配置文件位于：`build.config.json`

```json
{
  "outputPath": "../niucloud/public",
  "outputName": "admin"
}
```

### 配置项说明

- **outputPath**: 构建输出的目标路径（相对于项目根目录）
  - 默认值：`../niucloud/public`
  - 示例：`../../output`、`/Users/xxx/deploy`

- **outputName**: 输出的目录名称
  - 默认值：`admin`
  - 示例：`admin-v2`、`backend`

## 使用方法

### 方式一：使用配置文件构建（推荐）

```bash
npm run build:config
```

此命令会：
1. 读取 `build.config.json` 配置文件
2. 执行 Vite 构建
3. 将构建产物复制到配置的输出路径

### 方式二：使用原有构建方式

```bash
npm run build
```

此命令使用原有的 `publish.cjs` 脚本，输出到固定路径 `../niucloud/public/admin`

## 构建流程

1. **Vite 构建**：生成 `dist` 目录
2. **路径处理**：自动替换 `index.html` 中的资源路径
3. **文件复制**：将构建产物复制到目标位置

## 示例

### 示例 1：输出到默认位置

```json
{
  "outputPath": "../niucloud/public",
  "outputName": "admin"
}
```

最终输出：`../niucloud/public/admin/`

### 示例 2：输出到自定义位置

```json
{
  "outputPath": "../../deploy/static",
  "outputName": "admin-frontend"
}
```

最终输出：`../../deploy/static/admin-frontend/`

### 示例 3：使用绝对路径

```json
{
  "outputPath": "/var/www/html",
  "outputName": "admin"
}
```

最终输出：`/var/www/html/admin/`

## 注意事项

1. 确保输出路径有写入权限
2. 如果目标目录已存在，会先删除再复制
3. 如果配置文件不存在或格式错误，会使用默认配置
4. 构建过程会自动创建不存在的输出目录

## 脚本文件

- `publish-config.cjs`：可配置的构建脚本
- `publish.cjs`：原有的构建脚本（保持不变）
- `build.config.json`：构建配置文件

