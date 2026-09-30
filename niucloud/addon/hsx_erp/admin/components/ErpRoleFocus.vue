<template>
    <el-popover placement="bottom-end" trigger="click" :width="360">
        <template #reference><el-button :icon="InfoFilled" circle aria-label="岗位协作说明" title="岗位协作说明" /></template>
        <div class="erp-role-focus__items">
            <div v-for="item in items" :key="item.role" class="erp-role-focus__item">
                <span class="erp-role-focus__role">{{ item.role }}</span>
                <span class="erp-role-focus__text">{{ item.focus }}</span>
            </div>
        </div>
    </el-popover>
</template>

<script setup lang="ts">
import { InfoFilled } from '@element-plus/icons-vue'
defineProps<{
    items: Array<{ role: string; focus: string }>
}>()
</script>

<style scoped>
.erp-role-focus {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 16px;
    border: 1px solid var(--el-border-color);
    border-radius: 8px;
    background: var(--el-fill-color-light);
    padding: 10px 12px;
}
.erp-role-focus__title {
    flex: 0 0 auto;
    color: var(--el-text-color-secondary);
    font-size: 12px;
    font-weight: 600;
}
.erp-role-focus__items {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.erp-role-focus__item {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    background: var(--el-bg-color-overlay);
    font-size: 12px;
}
.erp-role-focus__role {
    color: var(--el-text-color-primary);
    font-weight: 650;
}
.erp-role-focus__text {
    padding-top: 4px;
    color: var(--el-text-color-regular);
}
@media (max-width: 768px) {
    .erp-role-focus {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }
}
</style>
