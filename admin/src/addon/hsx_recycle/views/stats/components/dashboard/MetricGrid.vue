<template>
  <div v-if="cards.length" :class="gridClass">
    <MetricCard
      v-for="card in cards"
      :key="card.key"
      :card="card"
      :tone="tone"
      @drilldown="emit('drilldown', $event)"
    />
  </div>
  <DashboardEmptyState
    v-else
    title="当前看板暂无指标"
    description="可到首页配置中开启对应看板组件，或调整当前账号的数据权限。"
  />
</template>

<script setup lang="ts">
import { computed } from "vue";
import type { DashboardMetricCard } from "../../types/dashboard";
import DashboardEmptyState from "./DashboardEmptyState.vue";
import MetricCard from "./MetricCard.vue";

const props = withDefaults(
  defineProps<{
    cards: DashboardMetricCard[];
    tone?: "business" | "finance";
    columns?: "business" | "finance";
  }>(),
  {
    tone: "business",
    columns: "business",
  }
);

const emit = defineEmits<{
  (event: "drilldown", card: DashboardMetricCard): void;
}>();

const gridClass = computed(() => {
  if (props.columns === "finance") {
    return "grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4";
  }
  return "grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4";
});
</script>
