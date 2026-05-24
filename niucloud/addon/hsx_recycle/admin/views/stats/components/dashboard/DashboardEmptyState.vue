<template>
  <div class="rounded-lg border border-gray-200 bg-gray-50 px-5 py-8 text-center">
    <div class="text-sm font-medium text-gray-700">{{ title }}</div>
    <div v-if="description" class="mt-2 text-sm leading-6 text-gray-500">
      {{ description }}
    </div>
  </div>
</template>

<script setup lang="ts">
withDefaults(
  defineProps<{
    title?: string;
    description?: string;
  }>(),
  {
    title: "暂无可展示数据",
    description: "",
  }
);
</script>
