<template>
  <div :class="['check-card', done && 'check-card--done']">
    <div class="card-header">
      <el-icon class="header-icon">
        <component :is="iconComponent" />
      </el-icon>
      <span class="card-title">{{ title }}</span>
      <span v-if="badge" class="card-badge">{{ badge }}</span>
      <span v-if="done" class="card-done-dot" title="已完成">✓</span>
    </div>
    <div class="card-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Lightning, Monitor, Picture, Setting, Tools, Iphone } from '@element-plus/icons-vue'

const props = defineProps<{
  title: string
  icon: 'Lightning' | 'Monitor' | 'Picture' | 'Setting' | 'Tools' | 'Iphone'
  done?: boolean
  badge?: string
}>()

const iconMap = { Lightning, Monitor, Picture, Setting, Tools, Iphone }
const iconComponent = computed(() => iconMap[props.icon])
</script>

<style lang="scss" scoped>
.check-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: white;
  transition: border-color 0.2s;

  &--done {
    border-color: #86efac;

    .card-header {
      background: #f0fdf4;
      border-bottom-color: #bbf7d0;
    }

    .header-icon { color: #16a34a; }
  }

  .card-header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 12px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    transition: background 0.2s, border-color 0.2s;

    .header-icon { color: #6366f1; font-size: 14px; }

    .card-title { flex: 1; min-width: 0; }

    .card-badge {
      font-size: 10px;
      font-weight: 500;
      color: #6366f1;
      background: #eef2ff;
      padding: 1px 6px;
      border-radius: 10px;
      white-space: nowrap;
    }

    .card-done-dot {
      font-size: 11px;
      color: #16a34a;
      font-weight: 700;
    }
  }

  .card-content {
    padding: 10px 12px;
  }
}
</style>
