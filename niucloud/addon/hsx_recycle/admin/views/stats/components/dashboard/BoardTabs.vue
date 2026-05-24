<template>
  <div class="flex flex-wrap gap-2">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      type="button"
      :class="[
        'rounded-md border px-4 py-2 text-sm font-medium transition',
        modelValue === tab.key
          ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
          : 'border-gray-200 bg-white text-gray-600 hover:border-blue-300 hover:text-blue-600',
      ]"
      @click="emit('update:modelValue', tab.key)"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
import type { DashboardTabItem } from "../../types/dashboard";

defineProps<{
  tabs: DashboardTabItem[];
  modelValue: string;
}>();

const emit = defineEmits<{
  (event: "update:modelValue", value: string): void;
}>();
</script>
