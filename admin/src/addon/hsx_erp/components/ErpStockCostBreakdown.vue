<template>
    <div class="cost-breakdown">
        <dl>
            <div><dt>原始采购成本</dt><dd>{{ money(summary.purchase_cost) }}</dd></div>
            <div><dt>回收／采购调价</dt><dd>{{ money(summary.supplier_adjust_cost) }}</dd></div>
            <div><dt>整备维修成本</dt><dd>{{ money(summary.refurbish_cost) }}</dd></div>
            <div><dt>内部账面修正</dt><dd>{{ money(summary.internal_adjust_cost) }}</dd></div>
            <div class="cost-breakdown__total"><dt>当前总成本</dt><dd>{{ money(summary.total_cost) }}</dd></div>
        </dl>
        <p>采购结算只对应货款 {{ money(summary.supplier_amount) }}，不含整备费和内部修正。</p>
        <p v-if="Number(summary.internal_adjust_cost) !== 0" class="cost-breakdown__warning">内部修正不生成应付，也不代表已付款。真实维修费请核对服务商及整备应付，勿重复增加成本。</p>
    </div>
</template>

<script setup lang="ts">
defineProps<{ summary: Record<string, number | string> }>()
const money = (value: unknown) => `¥${Number(value || 0).toFixed(2)}`
</script>

<style scoped>
.cost-breakdown{color:#334155;font-size:13px;text-align:left}
dl{margin:0}dl>div{display:flex;justify-content:space-between;gap:24px;padding:6px 0}dt{color:#64748b}dd{margin:0;font-variant-numeric:tabular-nums}
.cost-breakdown__total{margin-top:5px;border-top:1px solid #e2e8f0;font-weight:600}.cost-breakdown__total dt,.cost-breakdown__total dd{color:#0f172a}
p{margin:10px 0 0;font-size:12px;line-height:1.7;color:#64748b}.cost-breakdown__warning{padding:9px 12px;border-radius:6px;background:#fffbeb;color:#92400e}
</style>
