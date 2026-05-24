<template>
  <aside class="rounded-lg border border-amber-200 bg-amber-50 p-4">
    <div class="flex items-center justify-between">
      <div class="text-sm font-medium text-amber-900">需要关注</div>
      <el-tag size="small" type="warning">{{ items.length }} 项</el-tag>
    </div>

    <div v-if="items.length" class="mt-3 space-y-2">
      <button
        v-for="item in items"
        :key="item.key"
        type="button"
        class="flex w-full items-center justify-between rounded-md border border-amber-200 bg-white px-3 py-2 text-left text-sm transition hover:border-amber-300 hover:bg-amber-100"
        @click="emit('drilldown', item)"
      >
        <span class="min-w-0 truncate text-amber-900">{{ item.title }}</span>
        <span class="ml-3 whitespace-nowrap font-semibold text-amber-700">
          {{ item.value }}{{ item.unit }}
        </span>
      </button>
    </div>

    <div v-else class="mt-8 text-center text-sm text-amber-700">
      当前没有待处理风险
    </div>

    <p class="mt-4 text-xs leading-5 text-amber-700">
      点击指标可进入对应订单或设备明细，便于追踪真实业务数据。
    </p>
  </aside>
</template>

<script setup lang="ts">
import type { DashboardMetricCard } from "../../types/dashboard";

defineProps<{
  items: DashboardMetricCard[];
}>();

const emit = defineEmits<{
  (event: "drilldown", card: DashboardMetricCard): void;
}>();
</script>
