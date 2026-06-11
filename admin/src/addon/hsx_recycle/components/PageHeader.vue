<template>
  <div class="recycle-page-header">
    <div class="recycle-page-header__main">
      <div class="recycle-page-header__title">{{ title }}</div>
      <div v-if="description" class="recycle-page-header__desc">{{ description }}</div>
    </div>
    <div v-if="$slots.actions" class="recycle-page-header__actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup lang="ts">
/**
 * 回收插件 PC 管理端统一页头。
 *
 * 统一所有列表/详情页的标题区写法：左侧标题 + 可选副标题说明，右侧操作按钮。
 * 用法：
 *   <PageHeader title="回收设备导出" description="按条件筛选后导出设备明细">
 *     <template #actions><el-button type="primary">导出</el-button></template>
 *   </PageHeader>
 */
defineProps<{
  title: string
  description?: string
}>()
</script>

<style lang="scss" scoped>
.recycle-page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;

  &__title {
    font-size: 18px;
    font-weight: 600;
    color: var(--el-text-color-primary);
    line-height: 1.4;
  }

  &__desc {
    margin-top: 4px;
    font-size: 13px;
    color: var(--el-text-color-secondary);
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }
}
</style>
