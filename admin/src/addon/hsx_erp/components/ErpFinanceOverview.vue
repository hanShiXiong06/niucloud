<template>
    <section class="finance-overview">
        <header>
            <div><span class="finance-overview__label">{{ partyLabel }}</span><h3>{{ row.party_name || '-' }}</h3></div>
            <el-tag :type="status.type" effect="plain">{{ status.label }}</el-tag>
        </header>
        <div class="finance-overview__amounts">
            <div><span>{{ direction === 'expense' ? '应付总额' : '应收总额' }}</span><strong>{{ money(row.amount) }}</strong></div>
            <div><span>已结算</span><strong>{{ money(row.settled_amount) }}</strong></div>
            <div class="finance-overview__remaining"><span>{{ direction === 'expense' ? '剩余待付' : '剩余待收' }}</span><strong>{{ money(remaining) }}</strong></div>
        </div>
        <HsxFold title="单据与来源" :reset-key="row.id || row.payable_no || row.receivable_no || row.party_id" class="finance-overview__source">
            <div v-if="row.payable_no || row.receivable_no" class="finance-overview__number"><span>{{ direction === 'expense' ? '应付单号' : '应收单号' }}</span><ErpCopyText :value="row.payable_no || row.receivable_no" title="单据编号" /></div>
            <ErpFinanceSourceMeta :row="row" :default-direction="direction" />
            <slot name="details" />
        </HsxFold>
    </section>
</template>

<script setup lang="ts">
import { HsxFold } from '@/addon/hsx_components/core'
import ErpCopyText from './ErpCopyText.vue'
import ErpFinanceSourceMeta from './ErpFinanceSourceMeta.vue'
defineProps<{
    row: Record<string, any>
    partyLabel: string
    direction: 'income' | 'expense'
    remaining: number
    status: { label: string; type?: 'success' | 'warning' | 'info' | 'primary' | 'danger' | '' }
}>()
const money = (value: any) => `¥${Number(value || 0).toFixed(2)}`
</script>

<style scoped>
.finance-overview { margin-bottom: 20px; color: var(--el-text-color-primary); }
.finance-overview header { display: flex; justify-content: space-between; align-items: center; gap: 16px; }
.finance-overview__label, .finance-overview__amounts span { color: var(--el-text-color-secondary); font-size: 12px; }
.finance-overview h3 { margin: 4px 0 0; font-size: 18px; font-weight: 600; overflow-wrap: anywhere; }
.finance-overview__amounts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin: 16px 0; padding-block: 14px; border-block: 1px solid var(--el-border-color-lighter); }
.finance-overview__amounts > div { display: flex; flex-direction: column; gap: 6px; }
.finance-overview__amounts strong { font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; }
.finance-overview__remaining strong { color: var(--el-color-warning); }
.finance-overview__source { border: 0; background: transparent; }
.finance-overview__source :deep(.hsx-fold__trigger) { padding: 4px 0; }
.finance-overview__source :deep(.hsx-fold__body) { margin-top: 8px; padding: 12px; background: var(--el-fill-color-light); border-radius: 6px; }
.finance-overview__number { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 12px; color: var(--el-text-color-secondary); font-size: 12px; }
</style>
