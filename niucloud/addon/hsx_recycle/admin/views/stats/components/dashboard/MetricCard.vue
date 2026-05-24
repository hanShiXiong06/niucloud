<template>
  <button
    type="button"
    :class="[
      'min-h-[148px] rounded-lg border border-gray-200 bg-white p-4 text-left transition',
      canDrilldown ? 'hover:shadow-sm' : 'cursor-default',
      hoverClass,
    ]"
    :aria-disabled="!canDrilldown"
    @click="handleClick"
  >
    <div class="flex h-full flex-col justify-between gap-3">
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <div class="truncate text-sm font-medium text-gray-600">{{ card.title }}</div>
          <div class="mt-1 text-xs text-gray-400">{{ card.scope_label }}</div>
          <div class="mt-2 flex items-end gap-1">
            <span :class="['break-all text-2xl font-semibold leading-none', metricTextClass]">
              {{ displayValue }}
            </span>
            <span class="pb-0.5 text-xs text-gray-500">{{ card.unit }}</span>
          </div>
        </div>
                    <el-tooltip placement="top" :content="tooltipContent" :disabled="!tooltipContent">
                      <el-tag size="small" :type="metricTagType">
                        {{ card.category || "指标" }}
                      </el-tag>
                    </el-tooltip>
      </div>

      <div>
        <div v-if="card.secondary" class="mb-2 text-xs text-gray-500">
          {{ card.secondary.label }}：{{ card.secondary.value }}{{ card.secondary.unit }}
        </div>
        <div class="flex items-end justify-between gap-3">
          <div class="line-clamp-2 text-xs leading-5 text-gray-500">
            {{ card.description }}
          </div>
          <span
            :class="[
              'shrink-0 text-xs',
              canDrilldown ? actionTextClass : 'text-gray-400',
            ]"
          >
            {{ canDrilldown ? "查看明细" : "仅统计" }}
          </span>
        </div>
      </div>
    </div>
  </button>
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { DashboardMetricCard } from "../../types/dashboard";

const props = withDefaults(
  defineProps<{
    card: DashboardMetricCard;
    tone?: "business" | "finance";
  }>(),
  {
    tone: "business",
  }
);

const emit = defineEmits<{
  (event: "drilldown", card: DashboardMetricCard): void;
}>();

const canDrilldown = computed(() => Boolean(props.card.drilldown?.filter_key));

const displayValue = computed(() => {
  if (props.card.value_type === "money") return `¥${props.card.value || "0.00"}`;
  return props.card.value ?? 0;
});

const metricTagType = computed(() => {
  if (props.card.category === "异常" || props.card.category === "风险") return "danger";
  if (props.card.category === "待办") return "warning";
  if (props.card.category === "资金" || props.card.category === "库存") return "success";
  return "info";
});

const metricTextClass = computed(() => {
  if (props.card.category === "异常" || props.card.category === "风险") return "text-red-600";
  if (props.card.category === "待办") return "text-amber-600";
  if (props.card.category === "资金" || props.card.category === "库存") return "text-emerald-600";
  return "text-blue-600";
});

const hoverClass = computed(() => {
  if (!canDrilldown.value) return "";
  if (props.tone === "finance") return "hover:border-emerald-300 hover:bg-emerald-50";
  return "hover:border-blue-300 hover:bg-blue-50";
});

const actionTextClass = computed(() => {
  if (props.tone === "finance") return "text-emerald-600";
  return "text-blue-600";
});

const tooltipContent = computed(() => props.card.caliber || props.card.description || "");

const handleClick = () => {
  if (!canDrilldown.value) return;
  emit("drilldown", props.card);
};
</script>
