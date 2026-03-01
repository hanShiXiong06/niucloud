<template>
  <div class="check-card">
    <div class="card-header">
      <el-icon class="header-icon">
        <component :is="iconComponent" />
      </el-icon>
      <span>{{ title }}</span>
    </div>
    <div class="card-content">
      <slot />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
  Lightning, Monitor, Picture, Setting, Tools, Iphone
} from '@element-plus/icons-vue'

const props = defineProps<{
  title: string
  icon: 'Lightning' | 'Monitor' | 'Picture' | 'Setting' | 'Tools' | 'Iphone'
}>()

const iconMap = {
  Lightning,
  Monitor,
  Picture,
  Setting,
  Tools,
  Iphone
}

const iconComponent = computed(() => iconMap[props.icon])
</script>

<style lang="scss" scoped>
.check-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
  background: white;

  .card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    font-weight: 500;
    color: #374151;

    .header-icon {
      color: #6366f1;
    }
  }

  .card-content {
    padding: 12px;
  }
}
</style>
