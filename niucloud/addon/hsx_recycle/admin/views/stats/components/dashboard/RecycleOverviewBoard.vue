<template>
  <section class="overview-board" v-loading="loading">
    <div class="overview-hero">
      <div class="min-w-0">
        <div class="flex items-center gap-2 text-sm font-medium text-blue-100">
          <el-icon><DataBoard /></el-icon>
          <span>{{ dateLabel }}</span>
        </div>
        <h2 class="mt-2 text-2xl font-semibold text-white">回收经营总览</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-blue-50">
          {{ overviewText }}
        </p>
      </div>
      <div class="hero-actions">
        <button
          type="button"
          :class="['hero-action', isEditingLayout ? 'is-active' : '']"
          @click="toggleEditMode"
        >
          <el-icon><Rank /></el-icon>
          {{ isEditingLayout ? "完成编辑" : "编辑布局" }}
        </button>
        <button type="button" class="hero-action" @click="$emit('refresh')">
          <el-icon><Refresh /></el-icon>
          刷新数据
        </button>
        <button v-if="isEditingLayout" type="button" class="hero-action" @click="resetLayout">
          <el-icon><RefreshLeft /></el-icon>
          恢复布局
        </button>
      </div>
    </div>

    <div class="metric-strip">
      <button
        v-for="card in primaryCards"
        :key="card.key"
        type="button"
        class="metric-tile"
        @click="$emit('drilldown-card', card)"
      >
        <span class="text-xs text-gray-500">{{ card.title }}</span>
        <strong>{{ formatCardValue(card) }}</strong>
        <span class="text-xs text-gray-400">{{ card.caliber || card.description || card.scope_label }}</span>
      </button>
    </div>

    <div ref="boardRef" class="overview-grid">
      <article
        v-for="widget in visibleWidgets"
        :key="widget.key"
        class="overview-panel"
        :class="[widgetClass(widget), isEditingLayout ? 'is-editing' : '']"
        :data-widget-key="widget.key"
      >
        <div class="panel-header">
          <div>
            <h3>{{ widget.title }}</h3>
            <p>{{ widget.subtitle }}</p>
          </div>
          <div v-if="isEditingLayout" class="panel-tools">
            <el-select
              v-model="widget.size"
              size="small"
              class="size-select"
              @change="persistLayout"
            >
              <el-option label="1/3" value="third" />
              <el-option label="1/2" value="half" />
              <el-option label="2/3" value="wide" />
              <el-option label="整行" value="full" />
            </el-select>
            <span class="drag-handle" title="拖拽排序">
              <el-icon><Rank /></el-icon>
            </span>
          </div>
        </div>

        <div v-if="widget.key === 'ledger'" class="ledger-grid">
          <button
            v-for="item in ledgerStats"
            :key="item.label"
            type="button"
            :class="['ledger-cell', item.urgent ? 'is-urgent' : '']"
            @click="$emit('drilldown-ledger', item)"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <em>{{ item.unit }}</em>
          </button>
        </div>

        <VueChart v-else-if="widget.key === 'flow'" :option="flowChartOption" height="310px" class="chart" />
        <VueChart v-else-if="widget.key === 'trend'" :option="trendChartOption" height="310px" class="chart" />
        <VueChart v-else-if="widget.key === 'source'" :option="sourceChartOption" height="310px" class="chart" />
        <VueChart v-else-if="widget.key === 'status'" :option="statusChartOption" height="310px" class="chart" />
        <VueChart v-else-if="widget.key === 'category'" :option="categoryChartOption" height="310px" class="chart" />

        <div v-else-if="widget.key === 'price'" class="price-panel">
          <VueChart :option="priceChartOption" height="220px" class="chart compact" />
          <div class="price-summary">
            <div
              v-for="range in todayBusiness.price_ranges || []"
              :key="range.key"
              class="price-cell"
            >
              <span>{{ range.label }}</span>
              <strong>{{ range.count }} 台</strong>
              <em>¥{{ range.amount || "0.00" }}</em>
            </div>
          </div>
        </div>

        <div v-else-if="widget.key === 'responsibility'" class="task-panel">
          <el-table :data="responsibilityTasks" size="small" height="318" empty-text="暂无待办数据">
            <el-table-column prop="title" label="任务" min-width="128" fixed />
            <el-table-column label="数量" min-width="96">
              <template #default="{ row }">
                <div class="font-medium text-gray-900">{{ row.device_count || 0 }} 台</div>
                <div class="text-xs text-gray-400">{{ row.order_count || 0 }} 单</div>
              </template>
            </el-table-column>
            <el-table-column label="责任人" min-width="180">
              <template #default="{ row }">
                <div v-if="row.owners?.length" class="space-y-1">
                  <div
                    v-for="owner in row.owners.slice(0, 3)"
                    :key="`${row.key}-${owner.owner_id}-${owner.owner_name}`"
                    class="flex items-center justify-between gap-2 text-xs"
                  >
                    <span class="truncate text-gray-700">{{ owner.owner_name }}</span>
                    <span class="shrink-0 text-gray-900">{{ owner.device_count }} 台</span>
                  </div>
                </div>
                <span v-else class="text-xs text-gray-400">暂无</span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="74" align="right">
              <template #default="{ row }">
                <el-button
                  v-if="row.route_path || row.drilldown?.filter_key"
                  link
                  type="primary"
                  size="small"
                  @click="$emit('drilldown-task', row)"
                >
                  查看
                </el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="task-note">
            {{ responsibility.explain || "责任角色来自系统角色；未明确到人的任务只显示待处理数量。" }}
          </div>
        </div>

        <div v-else-if="widget.key === 'consignment'" class="consignment-grid">
          <button
            v-for="item in consignmentProgress"
            :key="item.label"
            type="button"
            class="consignment-cell"
            @click="$emit('consignment', item.status)"
          >
            <span>{{ item.label }}</span>
            <strong>{{ item.value }}</strong>
            <em>台</em>
          </button>
          <div class="consignment-amount">
            今日成交金额
            <strong>¥{{ todayBusiness.consignment?.sold_today_amount || "0.00" }}</strong>
          </div>
        </div>
      </article>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from "vue";
import Sortable from "sortablejs";
import { DataBoard, Rank, Refresh, RefreshLeft } from "@element-plus/icons-vue";
import type { BusinessDashboard, BusinessTrend, DashboardMetricCard } from "../../types/dashboard";
import VueChart from "./VueChart.vue";

type WidgetSize = "third" | "half" | "wide" | "full";

interface OverviewWidget {
  key: string;
  title: string;
  subtitle: string;
  size: WidgetSize;
  visible: boolean;
}

const props = defineProps<{
  dashboard: BusinessDashboard;
  trend: BusinessTrend;
  loading: boolean;
  dateLabel: string;
}>();

defineEmits<{
  (e: "refresh"): void;
  (e: "drilldown-card", card: DashboardMetricCard): void;
  (e: "drilldown-ledger", item: any): void;
  (e: "drilldown-task", task: any): void;
  (e: "consignment", status: string | number): void;
}>();

const STORAGE_KEY = "hsx_recycle_overview_board_layout_v1";
const chartColors = ["#2563EB", "#12B76A", "#F79009", "#7C3AED", "#F04438", "#13C2C2", "#667085"];
const lifecycleStatusOrder = [
  "待签收",
  "已签收",
  "待质检",
  "质检中",
  "已质检",
  "待确认",
  "已定价",
  "待打款",
  "已支付",
  "已回收",
  "已完成",
  "已退回",
  "已取消",
  "已关闭",
];

const boardRef = ref<HTMLElement>();
const isEditingLayout = ref(false);

let sortableInstance: Sortable | null = null;

const defaultWidgets: OverviewWidget[] = [
  { key: "ledger", title: "关键动作", subtitle: "签收、质检、打款、退货一屏下钻", size: "wide", visible: true },
  { key: "flow", title: "回收链路", subtitle: "关键节点数量，按后台返回字段统计", size: "third", visible: true },
  { key: "trend", title: "经营趋势", subtitle: "按所选时间范围展示关键指标", size: "wide", visible: true },
  { key: "source", title: "来源与履约", subtitle: "用户提交、代下单与配送方式占比", size: "third", visible: true },
  { key: "status", title: "状态分布", subtitle: "订单状态与设备状态对比", size: "half", visible: true },
  { key: "category", title: "分类排行", subtitle: "按设备数量、金额与占比排序", size: "half", visible: true },
  { key: "price", title: "价格区间", subtitle: "按最终回收价分布", size: "half", visible: true },
  { key: "responsibility", title: "待办与责任人", subtitle: "待处理任务、角色与人员", size: "half", visible: true },
  { key: "consignment", title: "代卖进度", subtitle: "代卖库存与结算节点", size: "third", visible: true },
];

const widgets = ref<OverviewWidget[]>(loadLayout());

const visibleWidgets = computed(() => widgets.value.filter((item) => item.visible));
const ledger = computed(() => props.dashboard.ledger || {});
const todayBusiness = computed(() => props.dashboard.today_business || {
  order_count: 0,
  device_count: 0,
  recycled_device_count: 0,
  sold_device_count: 0,
  category_breakdown: [],
  source_breakdown: [],
  delivery_breakdown: [],
  order_status_breakdown: [],
  device_status_breakdown: [],
  price_summary: { count: 0, min: "0.00", max: "0.00", avg: "0.00", label: "0.00 - 0.00" },
  price_ranges: [],
  consignment: {
    today_count: 0,
    pending_count: 0,
    selling_count: 0,
    pending_settlement_count: 0,
    sold_today_count: 0,
    sold_today_amount: "0.00",
  },
});

const responsibility = computed(() => props.dashboard.responsibility || {
  total_pending_devices: 0,
  explain: "",
  tasks: [],
});

const responsibilityTasks = computed(() => responsibility.value.tasks || []);

const primaryCards = computed(() => {
  const preferred = ["today_order_count", "today_device_count", "pending_check", "pending_pay_amount"];
  const cards = preferred
    .map((key) => props.dashboard.cards.find((card) => card.key === key))
    .filter((card): card is DashboardMetricCard => !!card);

  return cards.length ? cards : props.dashboard.cards.slice(0, 4);
});

const overviewText = computed(() => {
  const orderCount = Number(ledger.value.order_count || todayBusiness.value.order_count || 0);
  const deviceCount = Number(ledger.value.device_count || todayBusiness.value.device_count || 0);
  const pendingCheck = Number(ledger.value.pending_check_device_count || 0);
  const pendingPay = Number(ledger.value.pending_pay_device_count || 0);
  return `${props.dateLabel}新增订单 ${orderCount} 单、设备 ${deviceCount} 台；待质检 ${pendingCheck} 台，待打款 ${pendingPay} 台。所有金额、数量以后台接口实时返回为准。`;
});

const ledgerStats = computed(() => [
  { label: "签收手机", value: Number(ledger.value.signed_device_count || 0), unit: "台", filterKey: "signed_today", viewMode: "device_expand" },
  { label: "新增订单", value: Number(ledger.value.order_count || 0), unit: "单", filterKey: "today_created_orders" },
  { label: "待质检", value: Number(ledger.value.pending_check_device_count || 0), unit: "台", urgent: true, filterKey: "device_pending_check", viewMode: "device_expand" },
  { label: "质检中", value: Number(ledger.value.checking_device_count || 0), unit: "台", urgent: true, filterKey: "device_checking", viewMode: "device_expand" },
  { label: "待打款", value: Number(ledger.value.pending_pay_device_count || 0), unit: "台", urgent: true, filterKey: "pending_pay", viewMode: "device_expand" },
  { label: "已完成", value: Number(ledger.value.completed_device_count || 0), unit: "台", filterKey: "completed_today", viewMode: "device_expand" },
  { label: "退货", value: Number(ledger.value.return_device_count || 0), unit: "台", filterKey: "returned_devices", viewMode: "device_expand" },
  { label: "退货待处理", value: Number(ledger.value.pending_return_count || 0), unit: "台", urgent: true, filterKey: "pending_return", viewMode: "device_expand" },
]);

const consignmentProgress = computed(() => [
  { label: "今日转入", value: todayBusiness.value.consignment?.today_count || 0, status: "" },
  { label: "待上架", value: todayBusiness.value.consignment?.pending_count || 0, status: 0 },
  { label: "代卖中", value: todayBusiness.value.consignment?.selling_count || 0, status: 1 },
  { label: "待结算", value: todayBusiness.value.consignment?.pending_settlement_count || 0, status: 3 },
]);

function isWidgetSize(value: unknown): value is WidgetSize {
  return ["third", "half", "wide", "full"].includes(String(value));
}

function loadLayout() {
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    if (!stored) return defaultWidgets.map((item) => ({ ...item }));
    const rows = JSON.parse(stored);
    if (!Array.isArray(rows)) return defaultWidgets.map((item) => ({ ...item }));
    const defaults = new Map(defaultWidgets.map((item) => [item.key, item]));
    const merged = rows
      .map((item: Partial<OverviewWidget>) => {
        const base = item.key ? defaults.get(item.key) : null;
        return base ? { ...base, size: isWidgetSize(item.size) ? item.size : base.size, visible: item.visible !== false } : null;
      })
      .filter(Boolean) as OverviewWidget[];
    defaultWidgets.forEach((item) => {
      if (!merged.some((row) => row.key === item.key)) merged.push({ ...item });
    });
    return merged;
  } catch (error) {
    console.error("读取回收概览布局失败", error);
    return defaultWidgets.map((item) => ({ ...item }));
  }
}

function persistLayout() {
  try {
    window.localStorage.setItem(STORAGE_KEY, JSON.stringify(widgets.value.map((item) => ({
      key: item.key,
      size: item.size,
      visible: item.visible,
    }))));
  } catch (error) {
    console.error("保存回收概览布局失败", error);
  }
  nextTick(() => resizeCharts());
}

function resetLayout() {
  widgets.value = defaultWidgets.map((item) => ({ ...item }));
  persistLayout();
  nextTick(() => {
    initSortable();
  });
}

function toggleEditMode() {
  isEditingLayout.value = !isEditingLayout.value;
}

function widgetClass(widget: OverviewWidget) {
  return {
    "span-third": widget.size === "third",
    "span-half": widget.size === "half",
    "span-wide": widget.size === "wide",
    "span-full": widget.size === "full",
  };
}

function formatCardValue(card: DashboardMetricCard) {
  if (card.value_type === "money") return `¥${card.value || "0.00"}`;
  return `${card.value ?? 0}${card.unit || ""}`;
}

function emptyGraphic(text = "暂无数据") {
  return {
    type: "text",
    left: "center",
    top: "middle",
    style: { text, fill: "#98A2B3", fontSize: 14 },
  };
}

function initSortable() {
  if (!boardRef.value) return;
  sortableInstance?.destroy();
  sortableInstance = Sortable.create(boardRef.value, {
    handle: ".drag-handle",
    draggable: ".overview-panel",
    disabled: !isEditingLayout.value,
    animation: 220,
    ghostClass: "panel-ghost",
    onEnd: () => {
      const orderedKeys = Array.from(boardRef.value?.querySelectorAll<HTMLElement>(".overview-panel") || [])
        .map((el) => el.dataset.widgetKey)
        .filter((key): key is string => !!key);
      const map = new Map(widgets.value.map((item) => [item.key, item]));
      widgets.value = orderedKeys.map((key) => map.get(key)).filter(Boolean) as OverviewWidget[];
      persistLayout();
    },
  });
}

function statusWeight(label: string) {
  const index = lifecycleStatusOrder.indexOf(label);
  return index >= 0 ? index : lifecycleStatusOrder.length + 1;
}

function sortStatusLabels(labels: string[]) {
  return labels.sort((a, b) => {
    const weight = statusWeight(a) - statusWeight(b);
    if (weight !== 0) return weight;
    return a.localeCompare(b, "zh-CN");
  });
}

const flowChartOption = computed(() => {
  const rows = [
    { name: "新增设备", value: Number(ledger.value.device_count || 0) },
    { name: "已签收", value: Number(ledger.value.signed_device_count || 0) },
    { name: "待质检", value: Number(ledger.value.pending_check_device_count || 0) },
    { name: "待打款", value: Number(ledger.value.pending_pay_device_count || 0) },
    { name: "已完成", value: Number(ledger.value.completed_device_count || 0) },
  ];
  const hasData = rows.some((item) => item.value > 0);
  return {
    tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
    grid: { top: 24, left: 20, right: 28, bottom: 20, containLabel: true },
    xAxis: { type: "value", splitLine: { lineStyle: { color: "#EAECF0" } } },
    yAxis: { type: "category", data: rows.map((item) => item.name), axisLabel: { color: "#667085" } },
    graphic: hasData ? [] : emptyGraphic("暂无链路数据"),
    series: [{
      type: "bar",
      data: rows.map((item, index) => ({ value: item.value, itemStyle: { color: chartColors[index] } })),
      barMaxWidth: 22,
      label: { show: true, position: "right", formatter: "{c}", color: "#475467" },
    }],
  };
});

const trendChartOption = computed(() => {
  const xAxis = props.trend.x_axis || [];
  const series = Array.isArray(props.trend.series) ? props.trend.series : [];
  const displaySeries = series.slice(0, 5);
  const hasData = displaySeries.some((item) => Array.isArray(item.data) && item.data.some((value) => Number(value) > 0));
  return {
    tooltip: { trigger: "axis" },
    legend: { top: 0, type: "scroll" },
    grid: { top: 48, left: 34, right: 28, bottom: 28, containLabel: true },
    xAxis: { type: "category", data: xAxis, boundaryGap: false, axisLabel: { color: "#667085" } },
    yAxis: { type: "value", splitLine: { lineStyle: { color: "#EAECF0" } }, axisLabel: { color: "#667085" } },
    graphic: hasData ? [] : emptyGraphic("暂无趋势数据"),
    series: displaySeries.map((item, index) => ({
      name: item.name,
      type: item.type === "bar" ? "bar" : "line",
      smooth: item.type !== "bar",
      symbolSize: 6,
      data: item.data,
      areaStyle: item.type === "bar" ? undefined : { opacity: 0.08 },
      itemStyle: { color: chartColors[index % chartColors.length] },
      lineStyle: { width: 3 },
      barMaxWidth: 24,
    })),
  };
});

const sourceChartOption = computed(() => {
  const sourceRows = todayBusiness.value.source_breakdown || [];
  const deliveryRows = todayBusiness.value.delivery_breakdown || [];
  const hasData = [...sourceRows, ...deliveryRows].some((item: any) => Number(item.order_count || 0) > 0);
  return {
    tooltip: { trigger: "item", formatter: "{a}<br/>{b}: {c}单 ({d}%)" },
    legend: { bottom: 0, type: "scroll" },
    graphic: hasData ? [] : emptyGraphic("暂无来源数据"),
    series: [
      {
        name: "下单来源",
        type: "pie",
        radius: ["38%", "58%"],
        center: ["30%", "45%"],
        data: sourceRows.map((item: any, index: number) => ({ name: item.label, value: Number(item.order_count || 0), itemStyle: { color: chartColors[index % chartColors.length] } })),
      },
      {
        name: "配送方式",
        type: "pie",
        radius: ["38%", "58%"],
        center: ["72%", "45%"],
        data: deliveryRows.map((item: any, index: number) => ({ name: item.label, value: Number(item.order_count || 0), itemStyle: { color: chartColors[(index + 2) % chartColors.length] } })),
      },
    ],
  };
});

const statusChartOption = computed(() => {
  const orderRows = todayBusiness.value.order_status_breakdown || [];
  const deviceRows = todayBusiness.value.device_status_breakdown || [];
  const labels = sortStatusLabels(Array.from(new Set([...orderRows.map((item: any) => item.label), ...deviceRows.map((item: any) => item.label)])));
  const orderMap = orderRows.reduce((map: Record<string, number>, item: any) => {
    map[item.label] = Number(item.order_count || 0);
    return map;
  }, {});
  const deviceMap = deviceRows.reduce((map: Record<string, number>, item: any) => {
    map[item.label] = Number(item.device_count || 0);
    return map;
  }, {});
  const hasData = labels.some((label) => (orderMap[label] || 0) > 0 || (deviceMap[label] || 0) > 0);
  return {
    tooltip: { trigger: "axis", axisPointer: { type: "shadow" } },
    legend: { top: 0 },
    grid: { top: 44, left: 34, right: 20, bottom: 32, containLabel: true },
    xAxis: { type: "category", data: labels, axisLabel: { color: "#667085", interval: 0, rotate: labels.length > 6 ? 30 : 0 } },
    yAxis: { type: "value", splitLine: { lineStyle: { color: "#EAECF0" } }, axisLabel: { color: "#667085" } },
    graphic: hasData ? [] : emptyGraphic("暂无状态数据"),
    series: [
      { name: "订单", type: "bar", data: labels.map((label) => orderMap[label] || 0), itemStyle: { color: "#2563EB" }, barMaxWidth: 24 },
      { name: "设备", type: "bar", data: labels.map((label) => deviceMap[label] || 0), itemStyle: { color: "#12B76A" }, barMaxWidth: 24 },
    ],
  };
});

const categoryChartOption = computed(() => {
  const rows = [...(todayBusiness.value.category_breakdown || [])]
    .sort((a: any, b: any) => Number(b.count || 0) - Number(a.count || 0))
    .slice(0, 10)
    .reverse();
  const hasData = rows.some((item: any) => Number(item.count || 0) > 0);
  return {
    tooltip: {
      trigger: "axis",
      axisPointer: { type: "shadow" },
      formatter: (params: any[]) => {
        const item = rows[params?.[0]?.dataIndex] || {};
        return `${item.category_name || "-"}<br/>数量：${item.count || 0}台<br/>金额：¥${item.amount || "0.00"}<br/>占比：${item.rate || 0}%`;
      },
    },
    grid: { top: 18, left: 24, right: 42, bottom: 20, containLabel: true },
    xAxis: { type: "value", splitLine: { lineStyle: { color: "#EAECF0" } }, axisLabel: { color: "#667085" } },
    yAxis: { type: "category", data: rows.map((item: any) => item.category_name || "未分类"), axisLabel: { color: "#667085" } },
    graphic: hasData ? [] : emptyGraphic("暂无分类数据"),
    series: [{
      type: "bar",
      data: rows.map((item: any) => Number(item.count || 0)),
      itemStyle: { color: "#2563EB", borderRadius: [0, 6, 6, 0] },
      barMaxWidth: 20,
      label: { show: true, position: "right", formatter: "{c}台", color: "#475467" },
    }],
  };
});

const priceChartOption = computed(() => {
  const rows = todayBusiness.value.price_ranges || [];
  const hasData = rows.some((item: any) => Number(item.count || 0) > 0);
  return {
    tooltip: {
      trigger: "axis",
      axisPointer: { type: "shadow" },
      formatter: (params: any[]) => {
        const item = rows[params?.[0]?.dataIndex] || {};
        return `${item.label || "-"}<br/>数量：${item.count || 0}台<br/>金额：¥${item.amount || "0.00"}`;
      },
    },
    grid: { top: 18, left: 34, right: 20, bottom: 26, containLabel: true },
    xAxis: { type: "category", data: rows.map((item: any) => item.label), axisLabel: { color: "#667085" } },
    yAxis: { type: "value", splitLine: { lineStyle: { color: "#EAECF0" } }, axisLabel: { color: "#667085" } },
    graphic: hasData ? [] : emptyGraphic("暂无价格数据"),
    series: [{
      type: "bar",
      data: rows.map((item: any) => Number(item.count || 0)),
      itemStyle: { color: "#F79009", borderRadius: [6, 6, 0, 0] },
      barMaxWidth: 30,
    }],
  };
});

onMounted(() => {
  initSortable();
});

watch(isEditingLayout, (editing) => {
  nextTick(() => {
    if (!sortableInstance) initSortable();
    sortableInstance?.option("disabled", !editing);
  });
});

onUnmounted(() => {
  sortableInstance?.destroy();
});
</script>

<style scoped>
.overview-board {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.overview-hero {
  display: flex;
  justify-content: space-between;
  gap: 24px;
  padding: 22px;
  border-radius: 10px;
  background: linear-gradient(135deg, #1d4ed8, #0f172a);
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.16);
}

.hero-actions {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  flex-shrink: 0;
}

.hero-action {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border: 1px solid rgba(255, 255, 255, 0.28);
  border-radius: 8px;
  padding: 8px 12px;
  color: #fff;
  background: rgba(255, 255, 255, 0.12);
  transition: all 0.18s ease;
}

.hero-action:hover {
  background: rgba(255, 255, 255, 0.2);
}

.hero-action.is-active {
  border-color: rgba(255, 255, 255, 0.6);
  background: rgba(255, 255, 255, 0.24);
}

.metric-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
}

.metric-tile {
  min-height: 104px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 14px;
  text-align: left;
  background: #fff;
  box-shadow: 0 10px 24px rgba(15, 23, 42, 0.04);
  transition: all 0.18s ease;
}

.metric-tile:hover {
  border-color: #93c5fd;
  transform: translateY(-1px);
  box-shadow: 0 14px 30px rgba(37, 99, 235, 0.1);
}

.metric-tile strong {
  display: block;
  margin-top: 8px;
  color: #101828;
  font-size: 26px;
  line-height: 1.1;
}

.overview-grid {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 16px;
  align-items: start;
}

.overview-panel {
  min-width: 0;
  height: 430px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 12px 30px rgba(15, 23, 42, 0.05);
  overflow: hidden;
}

.overview-panel.is-editing {
  border-color: #93c5fd;
  box-shadow: 0 14px 34px rgba(37, 99, 235, 0.12);
}

.span-third { grid-column: span 4; }
.span-half { grid-column: span 6; }
.span-wide { grid-column: span 8; }
.span-full { grid-column: span 12; }

.panel-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border-bottom: 1px solid #f2f4f7;
}

.panel-header h3 {
  margin: 0;
  color: #101828;
  font-size: 15px;
  font-weight: 700;
}

.panel-header p {
  margin: 4px 0 0;
  color: #667085;
  font-size: 12px;
}

.panel-tools {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.size-select {
  width: 76px;
}

.drag-handle {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 8px;
  color: #667085;
  cursor: grab;
  background: #f2f4f7;
}

.panel-ghost {
  opacity: 0.45;
}

.chart {
  width: 100%;
  padding: 8px 12px 12px;
}

.ledger-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
  padding: 16px;
  max-height: 354px;
  overflow-y: auto;
}

.ledger-cell,
.consignment-cell {
  position: relative;
  min-height: 94px;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 12px;
  text-align: left;
  background: #f8fafc;
  transition: all 0.18s ease;
}

.ledger-cell:hover,
.consignment-cell:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.ledger-cell.is-urgent {
  border-color: #fed7aa;
  background: #fff7ed;
}

.ledger-cell span,
.consignment-cell span {
  display: block;
  color: #667085;
  font-size: 12px;
}

.ledger-cell strong,
.consignment-cell strong {
  display: inline-block;
  margin-top: 8px;
  color: #101828;
  font-size: 24px;
  line-height: 1;
}

.ledger-cell em,
.consignment-cell em {
  margin-left: 4px;
  color: #98a2b3;
  font-style: normal;
  font-size: 12px;
}

.price-panel {
  padding-bottom: 14px;
  max-height: 354px;
  overflow-y: auto;
}

.price-summary {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;
  padding: 0 14px;
}

.price-cell {
  border-radius: 8px;
  padding: 10px;
  background: #f8fafc;
}

.price-cell span,
.price-cell em {
  display: block;
  color: #667085;
  font-size: 12px;
  font-style: normal;
}

.price-cell strong {
  display: block;
  margin-top: 4px;
  color: #101828;
}

.task-note {
  border-top: 1px solid #f2f4f7;
  padding: 10px 14px;
  color: #667085;
  font-size: 12px;
}

.task-panel {
  max-height: 354px;
  overflow: hidden;
}

.consignment-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 16px;
  max-height: 354px;
  overflow-y: auto;
}

.consignment-amount {
  grid-column: 1 / -1;
  border-radius: 10px;
  padding: 12px;
  color: #475467;
  background: #f8fafc;
}

.consignment-amount strong {
  display: block;
  margin-top: 4px;
  color: #101828;
  font-size: 22px;
}

@media (max-width: 1280px) {
  .span-third,
  .span-half,
  .span-wide {
    grid-column: span 6;
  }
}

@media (max-width: 900px) {
  .overview-hero,
  .hero-actions {
    flex-direction: column;
  }

  .metric-strip,
  .ledger-grid,
  .consignment-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .span-third,
  .span-half,
  .span-wide,
  .span-full {
    grid-column: span 12;
  }
}
</style>
