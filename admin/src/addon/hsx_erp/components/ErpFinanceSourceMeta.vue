<template>
    <div class="finance-source" :class="{ 'is-compact': compact }">
        <div class="finance-source__head">
            <el-tag size="small" effect="light" :type="directionMeta.type">{{ financeTypeName }}</el-tag>
            <el-tooltip v-if="showBusinessSource" :content="`业务来源：${businessSourceName}`" placement="top" :show-after="250">
                <span class="finance-source__business">业务来源：{{ businessSourceName }}</span>
            </el-tooltip>
            <el-tag v-if="channelName" size="small" effect="plain" type="info">{{ channelName }}</el-tag>
        </div>
        <div v-if="!compact" class="finance-source__meta">
            <span>业务场景：{{ bizSceneName }}</span>
            <span v-if="partyRoleLabel">对象：{{ partyRoleLabel }}</span>
            <span>方向：{{ directionMeta.label }}</span>
        </div>
        <el-tooltip :content="`来源单号：${sourceNo}`" placement="top" :show-after="250">
            <div class="finance-source__order">来源单号：<b>{{ sourceNo }}</b></div>
        </el-tooltip>
        <el-tooltip v-if="!compact && showInternalSourceNo" :content="`ERP关联单号：${internalSourceNo}`" placement="top" :show-after="250">
            <div class="finance-source__order finance-source__order--internal">ERP关联单号：<b>{{ internalSourceNo }}</b></div>
        </el-tooltip>
        <div v-if="!compact && (openingSettleMethod || settleSummary)" class="finance-source__settlement">
            <span v-if="openingSettleMethod">结算约定：{{ openingSettleMethod }}</span>
            <span v-if="settleSummary">结算进度：{{ settleSummary }}</span>
        </div>
        <el-tooltip v-if="!compact && showReason && businessReason" :content="businessReason" placement="top" :show-after="250">
            <div class="finance-source__reason"><span>{{ reasonLabel }}</span>{{ businessReason }}</div>
        </el-tooltip>
        <el-popover v-if="compact" placement="top-start" :width="340" trigger="click">
            <template #reference><el-button link type="primary" size="small" class="finance-source__explain">来源说明</el-button></template>
            <div class="finance-source__explanation">
                <div>{{ bizSceneName }} · {{ directionMeta.label }}<span v-if="partyRoleLabel"> · {{ partyRoleLabel }}</span></div>
                <div>来源单号：{{ sourceNo }}</div>
                <div v-if="showInternalSourceNo">ERP关联单号：{{ internalSourceNo }}</div>
                <div v-if="channelName">渠道：{{ channelName }}</div>
                <div v-if="showReason && businessReason">{{ reasonLabel }}：{{ businessReason }}</div>
            </div>
        </el-popover>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { erpNamedLabel } from '@/addon/hsx_erp/utils/display'

const props = withDefaults(defineProps<{
    row?: Record<string, any>
    compact?: boolean
    showReason?: boolean
    defaultDirection?: 'income' | 'expense'
}>(), {
    row: () => ({}),
    compact: false,
    showReason: true,
    defaultDirection: 'income'
})

const meta = computed<Record<string, any>>(() => props.row?.source_meta || {})

const financeTypeName = computed(() => readable(
    meta.value.finance_type_name,
    meta.value.finance_type_key,
    props.row?.source_label || (props.defaultDirection === 'expense' ? '应付款' : '应收款'),
    financeTypeMap
))
const businessSourceName = computed(() => readable(
    meta.value.business_source_name,
    meta.value.business_source_key || props.row?.source_type,
    props.row?.source_label || '业务单据',
    businessSourceMap
))
const showBusinessSource = computed(() => businessSourceName.value !== financeTypeName.value)
const bizSceneName = computed(() => bizSceneMap[text(meta.value.biz_scene || props.row?.biz_scene || props.row?.source_type)] || '其他业务')
const channelName = computed(() => erpNamedLabel(meta.value.channel_name || props.row?.sale_channel || props.row?.purchase_channel, meta.value.channel_code, ''))
const sourceNo = computed(() => text(meta.value.source_no || props.row?.source_no || props.row?.batch_no || props.row?.purchase_no || props.row?.sale_no || props.row?.receivable_no || props.row?.payable_no) || '-')
const internalSourceNo = computed(() => text(meta.value.internal_source_no || props.row?.internal_source_no))
const showInternalSourceNo = computed(() => Boolean(internalSourceNo.value && internalSourceNo.value !== sourceNo.value))
const partyRoleLabel = computed(() => text(meta.value.party_role_label))
const openingSettleMethod = computed(() => text(props.row?.opening_settle_method || props.row?.settle_method || props.row?.source_order?.settle_method))
const settleSummary = computed(() => text(props.row?.settle_summary))
const businessReason = computed(() => text(meta.value.business_reason || props.row?.business_reason || props.row?.return_remark || props.row?.payable_remark))
const reasonLabel = computed(() => props.defaultDirection === 'expense' ? '应付原因' : '应收原因')
const directionMeta = computed(() => {
    const direction = String(meta.value.direction || props.defaultDirection).toLowerCase()
    if (['expense', 'out', 'payable', 'decrease'].includes(direction)) return { label: '支出', type: 'danger' as const }
    return { label: '收入', type: 'success' as const }
})

const financeTypeMap: Record<string, string> = {
    sale_receivable: '销售应收', purchase_return_receivable: '采购退货应收',
    purchase_payable: '采购应付', sale_return_payable: '销售退货应付',
    sale_compensation_payable: '售后补差应付', refurbish_payable: '整备费用应付',
    receivable: '应收款', payable: '应付款'
}
const businessSourceMap: Record<string, string> = {
    sale: '销售出库', purchase_return: '采购退货', purchase: '采购入库',
    sale_return: '销售退货', sale_compensation: '售后补差', refurbish: '设备整备'
}
const bizSceneMap: Record<string, string> = {
    purchase: '采购入库', purchase_asset: '采购入库', sale: '销售出库',
    purchase_return: '采购退货', sale_return: '销售退货',
    after_sale_compensation: '售后补差', refurbish: '设备整备',
    operating: '经营收支', operating_expense: '经营支出', operating_income: '经营收入'
}

function text(value: any) {
    const result = String(value ?? '').trim()
    return result === '0' ? '' : result
}

function readable(name: any, key: any, fallback: string, map: Record<string, string>) {
    return erpNamedLabel(name, key, erpNamedLabel(fallback, '', '其他业务', map), map)
}
</script>

<style scoped>
.finance-source { min-width:0; color:#475569; font-size:12px; line-height:1.5; }
.finance-source__head { display:flex; min-width:0; align-items:center; flex-wrap:wrap; gap:6px; }
.finance-source__business { display:block; overflow:hidden; min-width:0; max-width:200px; color:#1e293b; font-size:13px; font-weight:650; text-overflow:ellipsis; white-space:nowrap; }
.finance-source__meta { display:flex; margin-top:7px; flex-wrap:wrap; gap:4px 12px; color:#64748b; }
.finance-source__order { overflow:hidden; margin-top:5px; color:#64748b; text-overflow:ellipsis; white-space:nowrap; }
.finance-source__order b { color:#334155; font-weight:550; }
.finance-source__order--internal { margin-top:2px; color:#94a3b8; }
.finance-source__order--internal b { color:#64748b; }
.finance-source__settlement { display:flex; margin-top:5px; flex-wrap:wrap; gap:4px 12px; color:#475569; }
.finance-source__reason { display:-webkit-box; overflow:hidden; margin-top:7px; padding:6px 8px; border-radius:5px; color:#92400e; background:#fffbeb; line-height:1.45; -webkit-box-orient:vertical; -webkit-line-clamp:2; }
.finance-source__reason span { margin-right:6px; color:#b45309; font-weight:650; }
.is-compact .finance-source__meta { margin-top:5px; }
.is-compact .finance-source__reason { padding:4px 7px; -webkit-line-clamp:1; }
.finance-source__explain { margin-top: 4px; }
.finance-source__explanation { display: grid; gap: 8px; line-height: 1.6; overflow-wrap: anywhere; color: var(--hsx-text-secondary); }
</style>
