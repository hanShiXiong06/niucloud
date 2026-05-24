<template>
  <section class="rounded-lg border border-gray-200 bg-gray-50 p-4">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
      <div>
        <div class="text-sm font-semibold text-gray-900">{{ title }}</div>
        <p class="mt-1 text-sm leading-6 text-gray-600">{{ summaryText }}</p>
      </div>
      <div class="grid grid-cols-1 gap-2 sm:grid-cols-3 lg:min-w-[420px]">
        <div
          v-for="item in highlights"
          :key="item.label"
          class="rounded-md border border-gray-200 bg-white px-3 py-2"
        >
          <div class="text-xs text-gray-500">{{ item.label }}</div>
          <div :class="['mt-1 text-base font-semibold', item.className]">{{ item.value }}</div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { DashboardMetricCard } from "../../types/dashboard";

const props = defineProps<{
  board: string;
  cards: DashboardMetricCard[];
  todo: DashboardMetricCard[];
  dateLabel: string;
}>();

const title = computed(() => {
  if (props.board === "finance") return "财务摘要";
  if (props.board === "user") return "工作摘要";
  return "经营摘要";
});

const getCard = (key: string) => props.cards.find((card) => card.key === key);
const formatValue = (card?: DashboardMetricCard) => {
  if (!card) return "-";
  if (card.value_type === "money") return `¥${card.value || "0.00"}`;
  return `${card.value ?? 0}${card.unit || ""}`;
};

const summaryText = computed(() => {
  if (props.board === "finance") {
    return `${props.dateLabel}打款金额 ${formatValue(getCard("today_paid_amount"))}，当前待打款 ${formatValue(getCard("pending_pay_amount"))}，库存回收成本 ${formatValue(getCard("inventory_recovery_cost"))}。`;
  }

  if (props.board === "user") {
    return "当前看板聚焦员工处理效率、团队协作和会员增长，具体展示内容由首页配置控制。";
  }

  const orderCount = formatValue(getCard("today_order_count"));
  const deviceCount = formatValue(getCard("today_device_count"));
  const pendingCheck = formatValue(getCard("pending_check"));
  const checkTimeout = formatValue(getCard("check_timeout"));
  return `${props.dateLabel}新增 ${orderCount}，涉及设备 ${deviceCount}；当前待质检 ${pendingCheck}，质检超时 ${checkTimeout}。`;
});

const highlights = computed(() => {
  if (props.board === "finance") {
    return [
      { label: "已打款", value: formatValue(getCard("today_paid_amount")), className: "text-emerald-600" },
      { label: "待打款", value: formatValue(getCard("pending_pay_amount")), className: "text-amber-600" },
      { label: "库存成本", value: formatValue(getCard("inventory_recovery_cost")), className: "text-blue-600" },
    ];
  }

  if (props.board === "user") {
    return [
      { label: "数据范围", value: props.dateLabel, className: "text-blue-600" },
      { label: "可见组件", value: "按权限展示", className: "text-gray-900" },
      { label: "下钻能力", value: "订单/设备", className: "text-emerald-600" },
    ];
  }

  return [
    { label: "新增订单", value: formatValue(getCard("today_order_count")), className: "text-blue-600" },
    { label: "待办风险", value: `${props.todo.length}项`, className: props.todo.length ? "text-amber-600" : "text-emerald-600" },
    { label: "报价确认率", value: formatValue(getCard("quote_confirm_rate")), className: "text-purple-600" },
  ];
});
</script>
