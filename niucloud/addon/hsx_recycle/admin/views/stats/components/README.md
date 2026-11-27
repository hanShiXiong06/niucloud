# 统计页面组件库

## 📦 组件列表

### 1. StatCard - 统计卡片组件

展示单个统计指标的卡片组件，支持自定义颜色、图标和额外信息。

#### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| title | string | 必填 | 卡片标题 |
| mainValue | string \| number | 必填 | 主要数值 |
| subValue | string | - | 副标题/补充说明 |
| color | 'blue' \| 'green' \| 'orange' \| 'red' \| 'purple' \| 'indigo' | 'blue' | 卡片主题色 |
| icon | Component | - | 图标组件 |
| showIndicator | boolean | false | 是否显示活动指示器 |
| extraInfo | string[] | [] | 额外信息标签数组 |

#### 使用示例

```vue
<StatCard 
    title="今日订单" 
    :mainValue="100"
    subValue="昨日 80"
    color="blue" 
    :icon="Document"
    :showIndicator="true"
/>

<StatCard 
    title="今日质检" 
    :mainValue="50"
    color="green" 
    :icon="Search"
    :extraInfo="['手机 20', '电脑 30']"
/>
```

### 2. ChartCard - 图表卡片组件

图表容器组件，提供统一的卡片样式和头部布局。

#### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| title | string | 必填 | 卡片标题 |
| icon | Component | - | 标题图标 |
| iconColor | string | '#3B82F6' | 图标颜色 |
| contentClass | string | '' | 内容区域自定义类名 |

#### Slots

- `default` - 卡片内容区域
- `header-right` - 头部右侧区域（如筛选器、操作按钮等）

#### 使用示例

```vue
<ChartCard title="员工工作统计" :icon="UserFilled">
    <template #header-right>
        <el-select v-model="userId">
            <el-option label="全部员工" value="" />
        </el-select>
    </template>
    
    <!-- 图表内容 -->
    <div ref="chartRef" class="w-full h-96"></div>
</ChartCard>
```

### 3. SectionHeader - 区块头部组件

区块标题组件，提供渐变背景和图标展示。

#### Props

| 属性 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| title | string | 必填 | 标题 |
| subtitle | string | - | 副标题 |
| icon | Component | - | 图标组件 |
| bgClass | string | 'bg-gradient-to-r from-blue-600 to-purple-600' | 背景渐变类 |
| iconBgClass | string | 'bg-white/20' | 图标背景类 |
| subtitleClass | string | 'text-blue-100 text-xs' | 副标题样式类 |

#### Slots

- `right` - 头部右侧区域

#### 使用示例

```vue
<SectionHeader 
    title="运营概览" 
    subtitle="今日业务数据" 
    :icon="DataBoard"
>
    <template #right>
        <div class="text-white">总计：100</div>
    </template>
</SectionHeader>
```

## 🎨 布局优化

### 优化前 vs 优化后

#### 优化前（dashboard.vue）
```
┌─────────────────────────────────────┐
│ 运营概览                            │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐        │
│ │卡片│ │卡片│ │卡片│ │卡片│        │
│ └────┘ └────┘ └────┘ └────┘        │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ 员工工作统计                        │
│ ┌─────────────────────────────────┐ │
│ │       表格（占满宽度）           │ │
│ └─────────────────────────────────┘ │
│ ┌─────────────────────────────────┐ │
│ │       图表（占满宽度）           │ │  ← 浪费空间
│ └─────────────────────────────────┘ │
└─────────────────────────────────────┘
```

#### 优化后（dashboard-optimized.vue）
```
┌─────────────────────────────────────┐
│ 运营概览                            │
│ ┌────┐ ┌────┐ ┌────┐ ┌────┐        │
│ │卡片│ │卡片│ │卡片│ │卡片│        │
│ └────┘ └────┘ └────┘ └────┘        │
└─────────────────────────────────────┘

┌─────────────────────────────────────┐
│ 员工工作统计           [筛选器]     │
│ ┌──────────────┬────────────────┐   │
│ │   表格 60%   │   图表 40%     │   │  ← 充分利用空间
│ │              │                │   │
│ └──────────────┴────────────────┘   │
└─────────────────────────────────────┘
```

### 布局特点

1. **响应式设计**：
   - PC端：表格和图表左右布局
   - 平板/手机：自动切换为上下布局

2. **空间利用**：
   - 管理员视图表格占 58%，图表占 42%
   - 避免单行占满造成的空间浪费

3. **模块化结构**：
   - 使用组件封装减少代码嵌套
   - 统一的视觉风格
   - 易于维护和扩展

## 🔧 使用新版本

### 方式一：直接替换（推荐）

```bash
# 备份当前版本
mv dashboard.vue dashboard-old.vue

# 使用优化版本
mv dashboard-optimized.vue dashboard.vue
```

### 方式二：渐进式迁移

1. 先替换部分模块（如统计卡片）
2. 测试无误后继续替换其他部分
3. 最后替换整体布局

## 📋 迁移检查清单

- [ ] 安装了所需的 Element Plus 图标
- [ ] 确认 hooks 文件路径正确
- [ ] 测试所有统计数据正常显示
- [ ] 测试图表正常渲染
- [ ] 测试响应式布局（PC/平板/手机）
- [ ] 测试日期筛选功能
- [ ] 测试员工筛选功能

## 🎯 优势总结

### 1. 组件化

✅ **StatCard** - 统计卡片可在任何需要展示指标的页面使用  
✅ **ChartCard** - 统一的图表容器样式  
✅ **SectionHeader** - 统一的区块标题样式

### 2. 布局优化

✅ 更好的空间利用率（特别是宽屏）  
✅ 数据展示更直观（表格+图表同屏）  
✅ 响应式设计，适配各种屏幕

### 3. 代码质量

✅ 减少嵌套层级（从5-6层减少到3-4层）  
✅ 更易维护和修改  
✅ 组件可在其他页面复用

### 4. 性能

✅ 组件级优化  
✅ 更少的 DOM 节点  
✅ 更快的渲染速度

## 💡 扩展建议

### 1. 添加更多统计卡片样式

```vue
<!-- 带趋势箭头的卡片 -->
<StatCard 
    title="本月订单"
    :mainValue="1000"
    subValue="↑ 12%"
    trend="up"
/>
```

### 2. 支持卡片点击

```vue
<StatCard 
    @click="handleCardClick"
    clickable
/>
```

### 3. 添加加载状态

```vue
<StatCard 
    :loading="isLoading"
/>
```

## 🐛 常见问题

### 1. 组件样式不生效

确保项目已安装并配置 Tailwind CSS。

### 2. 图表不显示

检查 ECharts 是否正确初始化，确认 ref 绑定正确。

### 3. 响应式布局异常

检查 Element Plus 的 `el-row` 和 `el-col` 的 `span` 配置。

## 📞 支持

如有问题，请查看代码注释或提issue。

