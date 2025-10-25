# 会员统计模块功能说明

## 📊 功能概览

新增的**会员统计模块**为管理员提供了完整的用户数据分析功能，包括用户注册、拉新、活跃度等多维度统计。

---

## 🎯 核心功能

### 1. **会员统计概览卡片**
显示关键指标：
- **总用户数**：累计注册用户总数
- **新增用户**：选定时间段内的新注册用户
- **活跃用户**：选定时间段内有登录行为的用户
- **拉新用户**：通过推广链接注册的用户数量

### 2. **注册趋势图（折线图）**
- 展示用户注册数量随时间的变化趋势
- 支持按天或按周统计（根据时间跨度自动切换）
- 使用渐变填充区域图，视觉效果更佳

### 3. **注册渠道分布（环形图）**
- 展示不同注册渠道的用户占比
- 支持的渠道：H5、微信公众号、微信小程序、支付宝小程序、抖音小程序、PC端、APP
- 点击图例可筛选显示

### 4. **用户活跃度统计（双折线图）**
- 同时展示活跃用户和新增用户的对比趋势
- 帮助分析用户留存情况
- 支持按天或按周统计

### 5. **拉新排行榜 TOP10**
- 展示推广贡献最多的用户
- 显示用户昵称、手机号、拉新数量
- 前三名特殊标识（金、银、铜牌）

---

## 🔧 技术实现

### 后端接口（PHP）

在 `RecycleStatsService.php` 中新增了 5 个统计方法：

```php
// 1. 获取会员统计概览
public function getMemberStatsOverview(array $params): array

// 2. 获取会员注册趋势
public function getMemberRegisterTrend(array $params): array

// 3. 获取会员注册渠道分布
public function getMemberChannelStats(array $params): array

// 4. 获取拉新排行榜
public function getMemberInviteRank(array $params): array

// 5. 获取会员活跃度统计
public function getMemberActivityStats(array $params): array
```

### 控制器接口（PHP）

在 `Stats.php` 控制器中新增了 5 个接口：

- `GET /recycle/stats/getMemberStatsOverview`
- `GET /recycle/stats/getMemberRegisterTrend`
- `GET /recycle/stats/getMemberChannelStats`
- `GET /recycle/stats/getMemberInviteRank`
- `GET /recycle/stats/getMemberActivityStats`

### 前端 API（TypeScript）

在 `stats.ts` 中新增了 5 个 API 调用函数：

```typescript
export function getMemberStatsOverview(params)
export function getMemberRegisterTrend(params)
export function getMemberChannelStats(params)
export function getMemberInviteRank(params)
export function getMemberActivityStats(params)
```

### Hooks 扩展（TypeScript）

**useStatsData.ts**：
- 新增会员统计状态管理
- 新增会员统计数据获取方法
- 在 `fetchData` 中自动加载会员统计数据（仅管理员）

```typescript
// 新增状态
memberStatsOverview
memberRegisterTrend
memberChannelStats
memberInviteRank
memberActivityStats
memberLoading

// 新增方法
fetchMemberStatsOverview()
fetchMemberRegisterTrend()
fetchMemberChannelStats()
fetchMemberInviteRank()
fetchMemberActivityStats()
fetchAllMemberStats()
```

**useCharts.ts**：
- 新增 3 个图表实例：注册趋势、渠道分布、活跃度
- 新增图表初始化和更新方法

```typescript
// 新增 refs
memberRegisterTrendChart
memberChannelChart
memberActivityChart

// 新增方法
initMemberRegisterTrendChart()
updateMemberRegisterTrendChart()
initMemberChannelChart()
updateMemberChannelChart()
initMemberActivityChart()
updateMemberActivityChart()
```

### UI 组件（Vue 3）

在 `dashboard.vue` 中新增了完整的会员统计区块：
- 使用了现有的 `StatCard`、`ChartCard`、`SectionHeader` 组件
- 响应式布局：左侧 16 列（趋势图 + 活跃度图），右侧 8 列（渠道图 + 排行榜）
- 添加了 `watch` 监听器自动更新图表

---

## 📦 数据结构

### 会员统计概览

```json
{
  "total_members": 1000,
  "new_members": 50,
  "active_members": 300,
  "invite_members": 20
}
```

### 注册趋势

```json
[
  { "date": "01-20", "count": 10 },
  { "date": "01-21", "count": 15 },
  ...
]
```

### 渠道分布

```json
[
  { "channel": "H5", "channel_name": "H5网页", "count": 100 },
  { "channel": "weapp", "channel_name": "微信小程序", "count": 200 },
  ...
]
```

### 拉新排行榜

```json
[
  {
    "member_id": 123,
    "nickname": "张三",
    "mobile": "138****1234",
    "headimg": "...",
    "invite_count": 50
  },
  ...
]
```

### 活跃度统计

```json
[
  {
    "date": "01-20",
    "active_count": 100,
    "new_count": 10
  },
  ...
]
```

---

## 🎨 UI 设计特点

1. **渐变标题卡**：紫色到粉色渐变，与其他区块区分
2. **彩色统计卡**：
   - 总用户：紫色（purple）
   - 新增用户：蓝色（blue）
   - 活跃用户：绿色（green）
   - 拉新用户：橙色（orange）
3. **图表样式**：
   - 折线图：平滑曲线 + 渐变填充
   - 环形图：多彩配色 + 图例
   - 排行榜：前三名金银铜勋章标识
4. **响应式布局**：支持大屏（2/3 + 1/3）和移动端（垂直堆叠）

---

## 🔄 数据流程

```
用户选择时间范围
    ↓
触发 fetchData()
    ↓
fetchAllMemberStats()
    ↓
并行请求 5 个接口
    ↓
更新 reactive 状态
    ↓
watch 监听器触发
    ↓
初始化并更新 ECharts 图表
    ↓
页面实时展示
```

---

## 🚀 使用方式

### 查看会员统计

1. 以**管理员身份**登录系统
2. 进入**数据概览**页面
3. 滚动到页面下方，即可看到**会员统计**区块
4. 使用顶部的**时间筛选器**切换统计周期

### 时间筛选支持

- 今日
- 昨日
- 近7天
- 近30天
- 本月
- 上月
- 自定义日期范围

---

## 🎯 业务价值

1. **用户增长分析**：通过注册趋势图，了解用户增长速度
2. **渠道效果评估**：识别最有效的注册渠道，优化推广策略
3. **用户活跃度监控**：对比活跃用户和新增用户，分析留存情况
4. **拉新激励**：通过排行榜激励用户推广
5. **数据驱动决策**：基于多维度数据制定运营策略

---

## 📝 注意事项

1. **权限控制**：会员统计模块仅对管理员可见
2. **性能优化**：
   - 时间跨度大于 31 天时，自动切换为按周统计
   - 排行榜默认只显示 TOP10
3. **数据来源**：统计数据来自 `member` 表
4. **实时性**：数据根据选择的时间范围实时计算
5. **空数据处理**：所有图表都支持零数据展示，避免空白页面

---

## 🔗 相关文件

### 后端
- `niucloud/addon/recycle/app/service/admin/stats/RecycleStatsService.php`
- `niucloud/addon/recycle/app/adminapi/controller/Stats.php`

### 前端
- `admin/src/addon/recycle/api/stats.ts`
- `admin/src/addon/recycle/views/stats/hooks/useStatsData.ts`
- `admin/src/addon/recycle/views/stats/hooks/useCharts.ts`
- `admin/src/addon/recycle/views/stats/dashboard.vue`

---

## ✅ 测试建议

1. **数据准备**：
   - 确保 `member` 表中有测试数据
   - 包含不同注册渠道的用户
   - 包含有推广关系（`pid > 0`）的用户

2. **功能测试**：
   - 切换不同时间范围，验证数据变化
   - 验证图表交互（hover、legend点击等）
   - 测试响应式布局（缩放浏览器窗口）

3. **边界测试**：
   - 零数据情况
   - 单数据点情况
   - 大数据量情况（100+ 条记录）

---

## 🎉 完成状态

✅ 后端服务实现（5 个统计方法）  
✅ 控制器接口暴露（5 个 REST API）  
✅ 前端 API 封装（5 个调用函数）  
✅ Hooks 数据管理扩展  
✅ Hooks 图表管理扩展  
✅ UI 组件完整实现  
✅ 响应式布局适配  
✅ 数据监听和图表自动更新  

---

**开发完成时间**：2025-10-25  
**版本**：v1.0.0  
**作者**：AI Assistant

