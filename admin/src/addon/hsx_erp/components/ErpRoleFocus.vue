<template>
    <HsxNotice class="mt-4" title="本页协作说明" :reset-key="items.map(item => item.role + item.focus).join('|')">
        <div class="erp-role-focus__items">
            <div v-for="item in items" :key="item.role" class="erp-role-focus__item">
                <span class="erp-role-focus__role">{{ item.role }}</span>
                <span class="erp-role-focus__text">{{ item.focus }}</span>
            </div>
        </div>
    </HsxNotice>
</template>

<script setup lang="ts">
import { HsxNotice } from '@/addon/hsx_components/core'
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
    border: 1px solid #e8eef6;
    border-radius: 8px;
    background: #f8fafc;
    padding: 10px 12px;
}
.erp-role-focus__title {
    flex: 0 0 auto;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
}
.erp-role-focus__items {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}
.erp-role-focus__item {
    display: inline-flex;
    align-items: center;
    overflow: hidden;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    background: #fff;
    font-size: 12px;
}
.erp-role-focus__role {
    background: #eaf2ff;
    padding: 4px 8px;
    color: #2563eb;
    font-weight: 650;
}
.erp-role-focus__text {
    padding: 4px 9px;
    color: #475569;
}
@media (max-width: 768px) {
    .erp-role-focus {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }
}
</style>
