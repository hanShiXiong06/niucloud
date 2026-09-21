<template>
    <div :id="id" class="order-setting-row" :class="{ 'order-setting-row--stacked': stacked }" role="group" :aria-labelledby="id + '-label'">
        <div class="order-setting-row__label">
            <div :id="id + '-label'" class="order-setting-row__title">{{ label }}</div>
            <p v-if="hint">{{ hint }}</p>
        </div>
        <div class="order-setting-row__control"><slot /></div>
    </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{ id: string; label: string; hint?: string; stacked?: boolean }>(), {
    hint: '',
    stacked: false
})
</script>

<style scoped>
.order-setting-row { display: flex; justify-content: space-between; align-items: center; gap: 24px; padding: 16px 0; min-width: 0; scroll-margin-top: 100px; }
.order-setting-row + .order-setting-row { border-top: 1px solid var(--el-border-color-extra-light); }
.order-setting-row__label { min-width: 0; flex: 1; }
.order-setting-row__title { font-size: 14px; font-weight: 500; color: var(--el-text-color-primary); line-height: 22px; }
.order-setting-row__label p { margin: 4px 0 0; color: var(--el-text-color-secondary); font-size: 12px; line-height: 20px; }
.order-setting-row__control { display: flex; justify-content: flex-end; align-items: center; flex: 0 1 360px; min-width: 0; max-width: 100%; }
.order-setting-row__control :deep(.el-input-number) { width: 128px; }
.order-setting-row__control :deep(.el-select) { width: 100%; }
.order-setting-row--stacked { display: block; }
.order-setting-row--stacked .order-setting-row__control { margin-top: 12px; display: block; }
@media (max-width: 680px) {
    .order-setting-row { gap: 12px; flex-wrap: wrap; }
    .order-setting-row__label { flex-basis: 160px; }
    .order-setting-row__control { flex-basis: auto; }
    .order-setting-row__control:has(.el-input:not(.el-input-number .el-input)), .order-setting-row__control:has(.el-select), .order-setting-row__control:has(.el-radio-group) { width: 100%; flex-basis: 100%; justify-content: flex-start; }
}
</style>
