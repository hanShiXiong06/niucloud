<template>
  <div class="empty-state" :style="{ padding: compact ? '24px 16px' : '48px 16px' }">
    <el-icon class="empty-state__icon" :size="compact ? 32 : 44">
      <component :is="iconComp" />
    </el-icon>
    <div class="empty-state__title">{{ title }}</div>
    <div v-if="description" class="empty-state__desc">{{ description }}</div>
    <!-- 操作区：放置「立即创建 / 去设置」等引导按钮 -->
    <div v-if="$slots.action" class="empty-state__action">
      <slot name="action" />
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Box, Document, Search, Warning, FolderOpened } from '@element-plus/icons-vue'

/**
 * 统一的「引导空态」组件。
 *
 * 用途：列表 / 表格无数据时，替代干巴巴的「暂无数据」，
 * 给出一句说明 + 可选的引导操作，降低新模块的上手成本。
 *
 * 加载态由 el-table 的 v-loading / el-skeleton 负责；
 * 错误态由请求拦截器统一弹提示，二者不在此组件职责内。
 *
 * 用法（表格空态槽）：
 *   <el-table :data="..."> ... <template #empty>
 *     <EmptyState title="还没有库存设备" description="..." >
 *       <template #action><el-button>手工建档入库</el-button></template>
 *     </EmptyState>
 *   </template></el-table>
 */
const props = withDefaults(defineProps<{
  title?: string
  description?: string
  /** 预置图标：box(库存) / document(单据) / search(搜索无结果) / warning / folder */
  icon?: 'box' | 'document' | 'search' | 'warning' | 'folder'
  /** 紧凑模式，用于弹窗/小区域 */
  compact?: boolean
}>(), {
  title: '暂无数据',
  description: '',
  icon: 'box',
  compact: false
})

const ICONS = { box: Box, document: Document, search: Search, warning: Warning, folder: FolderOpened }
const iconComp = computed(() => ICONS[props.icon] || Box)
</script>

<style lang="scss" scoped>
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center;
  color: var(--el-text-color-secondary);

  &__icon {
    color: var(--el-text-color-placeholder);
    margin-bottom: 12px;
  }

  &__title {
    font-size: 15px;
    font-weight: 500;
    color: var(--el-text-color-primary);
  }

  &__desc {
    margin-top: 6px;
    font-size: 13px;
    line-height: 1.6;
    max-width: 420px;
    color: var(--el-text-color-secondary);
  }

  &__action {
    margin-top: 16px;
  }
}
</style>
