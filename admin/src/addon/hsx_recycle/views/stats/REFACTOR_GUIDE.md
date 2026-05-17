# Dashboard 组件重构指南

## 📋 概述

已完成对 `dashboard.vue` 组件的重构，使用 **Hooks 模式** 将复杂的逻辑拆分为可复用的模块。

## 🎯 重构目标

1. ✅ **分离关注点**：将数据管理、图表管理、日期筛选等逻辑分离到独立的 hooks
2. ✅ **提高可维护性**：每个 hook 职责单一，易于理解和修改
3. ✅ **提升可复用性**：hooks 可以在其他组件中复用
4. ✅ **优化布局**：使用 `el-row` 和 `el-col` 实现分块展示

## 📁 文件结构

```
stats/
├── dashboard.vue                 # 主组件（需要更新 script 部分）
├── dashboard-refactored.vue      # 重构后的 script 参考
├── hooks/
│   ├── useStatsData.ts          # 数据管理 Hook
│   ├── useCharts.ts             # 图表管理 Hook
│   └── useDateFilter.ts         # 日期筛选 Hook
└── REFACTOR_GUIDE.md            # 本文档
```

## 🔧 Hooks 说明

### 1. useStatsData.ts - 数据管理

**职责**：管理所有统计数据的获取和状态

**导出内容**：
- **状态**：
  - `userRole` - 用户角色
  - `currentUserName` - 当前用户名
  - `currentUserId` - 当前用户ID
  - `queryParams` - 查询参数
  - `userWorkStats` - 普通用户工作统计
  - `overviewStats` - 管理员概况统计
  - `userDetailStats` - 员工详细统计
  - `userList` - 用户列表
  - `userLoading` - 加载状态

- **方法**：
  - `fetchUserRole()` - 获取用户角色
  - `fetchUserWorkStats()` - 获取用户工作统计
  - `fetchOverviewStats()` - 获取管理员概况
  - `fetchUserList()` - 获取用户列表
  - `fetchUserDetailStats(userId)` - 获取员工详细统计
  - `fetchData()` - 根据角色获取所有数据

### 2. useCharts.ts - 图表管理

**职责**：管理 ECharts 图表的初始化、更新和销毁

**导出内容**：
- **Refs**：
  - `userCategoryChart` - 用户分类图表 DOM 引用
  - `adminUserChart` - 管理员对比图表 DOM 引用

- **方法**：
  - `initUserCategoryChart()` - 初始化用户分类饼图
  - `updateUserCategoryChart(data)` - 更新用户分类饼图数据
  - `initAdminUserChart()` - 初始化管理员对比图表
  - `updateAdminUserChart(data)` - 更新管理员对比图表数据
  - `handleResize()` - 处理窗口大小变化
  - `disposeCharts()` - 销毁所有图表实例

### 3. useDateFilter.ts - 日期筛选

**职责**：管理日期范围选择和快速筛选逻辑

**导出内容**：
- **状态**：
  - `dateRange` - 日期范围
  - `activePeriod` - 当前激活的时间段
  - `quickPeriods` - 快速筛选选项

- **方法**：
  - `handleQuickPeriod(period, callback)` - 处理快速时间筛选
  - `handleDateChange(dates, callback)` - 处理日期范围变化
  - `getTimePeriodText(start, end)` - 获取时间段文本
  - `getPeriodLabel(type)` - 获取标签文字

## 🚀 使用方法

### 步骤 1：复制 Script 部分

将 `dashboard-refactored.vue` 中的 `<script>` 部分复制到 `dashboard.vue`，替换原有的 script 内容。

### 步骤 2：更新模板中的方法调用

确保模板中调用的方法名称与 hooks 导出的一致：

```vue
<!-- 正确 ✅ -->
<el-select @change="handleFetchUserDetailStats">

<!-- 错误 ❌ -->
<el-select @change="fetchUserDetailStats">
```

### 步骤 3：测试功能

1. **普通用户视图**：
   - 检查工作统计卡片显示
   - 检查设备分类饼图渲染
   - 测试日期筛选功能

2. **管理员视图**：
   - 检查运营概览卡片
   - 检查员工工作统计表格
   - 检查员工工作量对比图表
   - 测试员工筛选功能

## 📊 布局优化

### 管理员视图布局结构

```
第一行：运营概览（全宽）
├── 订单统计
├── 质检统计
├── 打款统计
└── 退货统计

第二行：员工工作统计（全宽）
├── 员工筛选器
├── 统计表格
└── 工作量对比图表
```

### 使用 el-row 和 el-col

```vue
<!-- 第一行：运营概览 -->
<div class="bg-white rounded-lg ...">
  <!-- 统计卡片 -->
</div>

<!-- 第二行：员工统计 -->
<el-row :gutter="16">
  <el-col :span="24">
    <!-- 表格和图表 -->
  </el-col>
</el-row>
```

## 🎨 样式说明

- 使用 Tailwind CSS 工具类
- 紧凑型设计，减少空白区域
- 响应式布局，适配不同屏幕尺寸
- 保持原有的渐变色和视觉风格

## 🔍 注意事项

1. **图表初始化时机**：
   - 使用 `watch` 监听数据变化
   - 使用 `nextTick` 确保 DOM 已渲染
   - 检查 DOM 元素是否存在

2. **内存泄漏防止**：
   - 在 `onUnmounted` 中清理事件监听
   - 调用 `disposeCharts()` 销毁图表实例

3. **类型安全**：
   - hooks 中使用 TypeScript 类型定义
   - 避免使用 `any` 类型（当前为快速开发使用）

## 📝 后续优化建议

1. **类型定义**：
   - 为统计数据创建 TypeScript 接口
   - 替换 `any` 类型为具体类型

2. **错误处理**：
   - 添加更详细的错误提示
   - 实现重试机制

3. **性能优化**：
   - 使用 `computed` 缓存计算结果
   - 实现数据缓存机制

4. **组件拆分**：
   - 将统计卡片提取为独立组件
   - 将图表提取为独立组件

## 🐛 问题排查

### 图表不显示

1. 检查 `ref` 是否正确绑定
2. 检查数据是否正确加载
3. 检查 DOM 元素是否已渲染
4. 查看浏览器控制台错误信息

### 数据不更新

1. 检查 API 调用是否成功
2. 检查 `queryParams` 是否正确设置
3. 检查 `watch` 是否正确触发

### 布局错乱

1. 检查 `el-row` 和 `el-col` 的 `span` 值
2. 检查 `gutter` 间距设置
3. 检查 Tailwind CSS 类名是否正确

## 📞 联系方式

如有问题，请查看代码注释或提出 issue。


