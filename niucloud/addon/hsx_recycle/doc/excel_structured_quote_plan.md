# Excel 结构化报价单导入方案（待实现）

## 需求概述

用户上传 Excel，系统按 sheet 名匹配 recycle_category 分类，解析为结构化报价数据。
支持动态表头、跨行分组、SKU 级价格调整、按日期查看历史。

## 数据架构

### 新增字段

`recycle_category` 表新增：
- `quote_type` varchar(20) DEFAULT 'image' COMMENT '报价类型：image-图片 structured-结构化'

`recycle_category_quote_history` 表新增：
- `quote_type` varchar(20) DEFAULT 'image' COMMENT '报价类型'
- `sheet_id` int DEFAULT 0 COMMENT '结构化报价版本ID'

### 新增表

```sql
CREATE TABLE `{{prefix}}recycle_category_quote_sheet` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `category_id` int NOT NULL DEFAULT 0 COMMENT '分类ID',
  `columns` text NOT NULL COMMENT '动态表头 JSON数组 如["靓机","小花","内爆"]',
  `notice_text` varchar(500) DEFAULT '' COMMENT '报价提示文案',
  `remark` varchar(255) DEFAULT '' COMMENT '备注',
  `file_name` varchar(255) DEFAULT '' COMMENT '来源文件名',
  `operator_id` int DEFAULT 0,
  `operator_name` varchar(100) DEFAULT '',
  `create_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site_cat_time` (`site_id`,`category_id`,`create_time`)
) COMMENT='回收分类结构化报价版本表';

CREATE TABLE `{{prefix}}recycle_category_quote_row` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `site_id` int NOT NULL DEFAULT 0,
  `sheet_id` int NOT NULL DEFAULT 0 COMMENT '版本ID',
  `category_id` int NOT NULL DEFAULT 0,
  `group_name` varchar(100) DEFAULT '' COMMENT '分组名(跨行合并)',
  `model_name` varchar(200) NOT NULL DEFAULT '' COMMENT '型号',
  `capacity` varchar(100) DEFAULT '' COMMENT '容量/规格',
  `brand` varchar(100) DEFAULT '' COMMENT '品牌',
  `prices` text NOT NULL COMMENT '价格JSON数组,对应sheet.columns顺序',
  `remark` varchar(500) DEFAULT '' COMMENT '行备注',
  `sort` int DEFAULT 0,
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_site_sheet` (`site_id`,`sheet_id`),
  KEY `idx_site_cat` (`site_id`,`category_id`),
  KEY `idx_model` (`model_name`)
) COMMENT='回收分类结构化报价行表';
```

## 核心流程

1. 用户上传 Excel（.xls/.xlsx）
2. 解析所有 sheet，按 sheet 名匹配 recycle_category（一级/二级分类名）
3. 预览：展示匹配结果 + 每个 sheet 的表格数据 + 自动推断列映射
4. 确认导入：
   - 创建 quote_sheet（记录动态表头 columns）
   - 写入 quote_row（每行一条，prices 对应 columns 顺序）
   - 在 quote_history 写一条 type=structured 的记录
   - 更新 recycle_category.quote_type = 'structured'

## 匹配规则

- sheet 名精确匹配 category_name（优先）
- sheet 名模糊匹配 category_full_name
- 匹配不上的 sheet 跳过或让用户手动选择

## 动态表头处理

- 自动识别首行表头（参考 spider 的 inferHeaderField）
- 支持：型号、品牌、容量、分组/系列、备注、价格列（靓机/小花/内爆等）
- 价格列名存入 sheet.columns，行数据的 prices 按相同顺序存值
- 前端根据 columns 动态渲染 el-table-column

## 跨行分组

- Excel 中合并单元格或相同值的"分组/系列"列
- 解析时提取 group_name，前端用 span-method 合并展示

## SKU 级调整

- 支持单行编辑 prices 中的某个值
- 编辑后 update_time 更新，不影响其他行
- 可选：批量调价（固定加减 / 比例调整）

## 前端页面

### 分类列表页改造
- 报价列显示标签：图片 / 结构化
- 图片类型：点击放大
- 结构化类型：点击弹出表格预览

### 历史抽屉改造
- 统一时间轴，每条标注类型（图片/结构化）
- 图片类型：缩略图 + 点击放大
- 结构化类型：显示"N行 x M列" + 点击展开表格

### Excel 导入页（新路由）
- 上传区域
- Sheet 匹配预览（左侧 sheet 列表，右侧匹配的分类 + 表格预览）
- 列映射配置（自动推断 + 手动调整）
- 确认导入按钮

### 结构化报价管理页（新路由）
- 左侧分类树筛选
- 右侧表格展示（动态列）
- 支持：按日期切换版本、搜索型号、编辑价格、批量调价

## 参考代码

- `addon/recycle_quote_spider/app/service/admin/QuoteImportService.php`
- `addon/recycle_quote_spider/admin/views/index.vue`（Excel 预览 tab）
- `addon/hsx_recycle/views/recycle_category/recycle_excel.vue`（现有 Excel 管理页）
