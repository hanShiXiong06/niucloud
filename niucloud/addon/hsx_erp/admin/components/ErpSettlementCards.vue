<template>
    <div class="settlement-cards" v-loading="loading">
        <el-empty v-if="!loading && !rows.length" :description="emptyText" :image-size="72" />
        <article v-for="row in rows" :key="settlementKey(row)" class="settlement-card" :class="`is-${row.settlement_type || 'other'}`">
            <header class="card-head">
                <div class="head-main">
                    <el-tag :type="typeMeta(row.settlement_type).tag" effect="light">{{ row.settlement_type_text || typeMeta(row.settlement_type).label }}</el-tag>
                    <div class="party-wrap">
                        <strong>{{ row.party_name || '未填写往来单位' }}</strong>
                        <span>{{ resultSentence(row) }}</span>
                    </div>
                </div>
                <div class="amount-wrap">
                    <span>{{ typeMeta(row.settlement_type).amountLabel }}</span>
                    <strong :class="typeMeta(row.settlement_type).amountClass">{{ money(primaryAmount(row)) }}</strong>
                </div>
            </header>

            <div class="summary-grid">
                <div><span>怎么结</span><b>{{ row.pay_method_text || typeMeta(row.settlement_type).methodFallback }}</b></div>
                <div><span>涉及范围</span><b>{{ scopeText(row) }}</b></div>
                <div><span>经手与时间</span><b>{{ row.operator_name || '-' }} · {{ formatTime(row.confirmed_at || row.create_at) }}</b></div>
            </div>

            <el-collapse v-model="expanded">
                <el-collapse-item :name="settlementKey(row)">
                    <template #title>
                        <span class="expand-title">查看核销账款{{ deviceCount(row) > 0 ? '、设备' : '' }}和资金凭证</span>
                    </template>
                    <div class="detail-grid">
                        <section class="detail-section">
                            <div class="section-title">核销了哪些账</div>
                            <div v-if="targets(row).length" class="target-list">
                                <article v-for="target in targets(row)" :key="`${target.target_type}_${target.target_id}`" class="target-card">
                                    <div class="target-head">
                                        <div><span>{{ erpNamedLabel(target.target_type_text, target.target_type, '账款') }}</span><b>{{ target.target_no || target.source_no || '未登记业务单号' }}</b></div>
                                        <strong>核销 {{ money(target.applied_amount) }}</strong>
                                    </div>
                                    <div v-if="target.devices?.length" class="device-grid">
                                        <div v-for="device in target.devices" :key="`${target.target_id}_${device.asset_id}_${device.imei}`" class="device-item">
                                            <b>{{ device.model || '未填写设备名称' }}</b>
                                            <span>{{ erpSerialText(device) }}</span>
                                            <small>{{ deviceSummary(device) }}</small>
                                        </div>
                                    </div>
                                    <div v-else class="no-device">{{ isOperatingTarget(target) ? '经营性账款不关联设备，按费用事项直接结算。' : '该账款没有设备级明细' }}</div>
                                </article>
                            </div>
                            <div v-else class="empty-detail">暂无关联账款明细</div>
                        </section>

                        <section class="detail-section">
                            <div class="section-title">真实资金与凭证</div>
                            <div v-if="row.money_ledgers?.length" class="money-list">
                                <article v-for="ledger in row.money_ledgers" :key="ledger.ledger_no" class="money-card">
                                    <div class="money-head"><b>{{ ledger.capital_account_name || row.capital_account_name || '-' }}</b><strong :class="ledger.direction === 'in' ? 'text-green-600' : 'text-red-600'">{{ ledger.direction === 'in' ? '收入' : '支出' }} {{ money(ledger.amount) }}</strong></div>
                                    <div class="money-meta">资金流水 {{ ledger.ledger_no || '-' }} · 记账后余额 {{ money(ledger.balance_after) }}</div>
                                    <ErpImageGallery v-if="ledger.voucher_urls" class="mt-2" :value="ledger.voucher_urls" :size="52" :limit="3" />
                                    <div v-else class="no-voucher">未上传资金凭证</div>
                                </article>
                            </div>
                            <div v-else class="offset-note">本次为账款相互抵扣，没有发生真实资金收入或支出。</div>

                            <dl class="document-meta">
                                <div><dt>结算单号</dt><dd>{{ row.settlement_no || '-' }}</dd></div>
                                <div><dt>确认时间</dt><dd>{{ formatTime(row.confirmed_at || row.create_at) }}</dd></div>
                                <div><dt>经手人</dt><dd>{{ row.operator_name || '-' }}</dd></div>
                                <div><dt>备注</dt><dd>{{ row.remark || '未填写' }}</dd></div>
                            </dl>
                        </section>
                    </div>
                </el-collapse-item>
            </el-collapse>
        </article>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { erpNamedLabel, erpSerialText } from '@/addon/hsx_erp/utils/display'
import ErpImageGallery from '@/addon/hsx_erp/components/ErpImageGallery.vue'

withDefaults(defineProps<{ rows?: any[]; loading?: boolean; emptyText?: string }>(), { rows: () => [], loading: false, emptyText: '暂无结算记录' })
const expanded = ref<string[]>([])

function settlementKey(row: any) { return String(row.id || row.settlement_id || row.settlement_no) }
function targets(row: any) { return Array.isArray(row.links) ? row.links : (Array.isArray(row.targets) ? row.targets : []) }
function primaryAmount(row: any) { return Number(row.applied_amount ?? row.amount ?? 0) }
function deviceCount(row: any) {
    const ids = new Set<string>()
    targets(row).forEach((target: any) => (target.devices || []).forEach((device: any) => ids.add(String(device.asset_id || device.imei || device.asset_no))))
    return ids.size
}
function isOperatingTarget(target: any) { return String(target?.biz_scene || target?.source_meta?.biz_scene || '').includes('operating_') || String(target?.category_statement_group || target?.source_meta?.statement_group || '').includes('operating_') }
function scopeText(row: any) {
    const count = targets(row).length
    const devices = deviceCount(row)
    if (devices > 0) return `核销 ${count} 笔账 · ${devices} 台设备`
    if (targets(row).some(isOperatingTarget)) return `核销 ${count} 笔经营性账款 · 无设备`
    return `核销 ${count} 笔账款`
}
function typeMeta(type: string) {
    const map: Record<string, any> = {
        payment: { label: '付款', tag: 'primary', amountLabel: '公司本次付出', amountClass: 'text-red-600', methodFallback: '资金付款' },
        receipt: { label: '收款', tag: 'success', amountLabel: '公司本次收到', amountClass: 'text-green-600', methodFallback: '资金收款' },
        offset: { label: '折账', tag: 'warning', amountLabel: '本次互相抵扣', amountClass: 'text-amber-600', methodFallback: '不走现金' },
    }
    return map[type] || { label: '其他结算', tag: 'info', amountLabel: '本次结算', amountClass: 'text-gray-900', methodFallback: '-' }
}
function resultSentence(row: any) {
    const amount = money(primaryAmount(row))
    if (row.settlement_type === 'payment') return `公司已向对方支付 ${amount}`
    if (row.settlement_type === 'receipt') return `公司已收到对方款项 ${amount}`
    if (row.settlement_type === 'offset') return `双方账款互相抵扣 ${amount}，不走现金`
    return `完成结算 ${amount}`
}
function deviceSummary(device: any) {
    return [device.spec, [device.warehouse_name, device.location_name].filter(Boolean).join(' / '), Number(device.sale_price || 0) > 0 ? `售价 ${money(device.sale_price)}` : '', Number(device.cost || 0) > 0 ? `成本 ${money(device.cost)}` : ''].filter(Boolean).join(' · ') || '-'
}
function money(value: any) { return `¥${Number(value || 0).toFixed(2)}` }
function formatTime(value: any) { return Number(value || 0) ? new Date(Number(value) * 1000).toLocaleString() : '时间未记录' }
</script>

<style scoped>
.settlement-cards { display:flex; max-width:1040px; min-height:180px; margin:0 auto; flex-direction:column; gap:12px; }
.settlement-card { overflow:hidden; border:1px solid #e5e7eb; border-left:4px solid #94a3b8; border-radius:8px; background:#fff; }
.settlement-card.is-payment { border-left-color:#ef4444; }
.settlement-card.is-receipt { border-left-color:#22c55e; }
.settlement-card.is-offset { border-left-color:#f59e0b; }
.card-head { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:16px 18px 12px; }
.head-main { display:flex; min-width:0; align-items:center; gap:12px; }
.party-wrap { display:flex; min-width:0; flex-direction:column; gap:4px; }
.party-wrap strong { overflow:hidden; color:#111827; font-size:16px; text-overflow:ellipsis; white-space:nowrap; }
.party-wrap span { color:#64748b; font-size:12px; }
.amount-wrap { display:flex; flex:none; flex-direction:column; align-items:flex-end; gap:3px; }
.amount-wrap span { color:#94a3b8; font-size:11px; }
.amount-wrap strong { font-size:20px; }
.summary-grid { display:grid; margin:0 18px 10px; grid-template-columns:repeat(3,minmax(0,1fr)); gap:1px; overflow:hidden; border-radius:6px; background:#e5e7eb; }
.summary-grid > div { display:flex; min-width:0; flex-direction:column; gap:4px; padding:10px 12px; background:#f8fafc; }
.summary-grid span { color:#94a3b8; font-size:11px; }
.summary-grid b { overflow:hidden; color:#334155; font-size:12px; font-weight:600; text-overflow:ellipsis; white-space:nowrap; }
.settlement-card :deep(.el-collapse) { border:0; }
.settlement-card :deep(.el-collapse-item__header) { height:42px; padding:0 18px; border-top:1px solid #f1f5f9; border-bottom:0; color:var(--el-color-primary); background:#fff; }
.settlement-card :deep(.el-collapse-item__wrap) { border:0; }
.settlement-card :deep(.el-collapse-item__content) { padding:0 18px 18px; }
.expand-title { font-size:12px; font-weight:600; }
.detail-grid { display:grid; grid-template-columns:minmax(0,1.35fr) minmax(300px,.65fr); gap:14px; padding-top:12px; }
.detail-section { min-width:0; padding:14px; border:1px solid #e5e7eb; border-radius:7px; background:#fbfcfe; }
.section-title { margin-bottom:10px; color:#334155; font-size:13px; font-weight:700; }
.target-list,.money-list { display:flex; flex-direction:column; gap:9px; }
.target-card,.money-card { padding:11px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; }
.target-head,.money-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.target-head > div { display:flex; min-width:0; flex-direction:column; gap:3px; }
.target-head span { color:#64748b; font-size:11px; }
.target-head b { overflow:hidden; color:#334155; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.target-head strong,.money-head strong { flex:none; color:#334155; font-size:12px; }
.device-grid { display:grid; margin-top:9px; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:7px; }
.device-item { display:flex; min-width:0; flex-direction:column; gap:3px; padding:8px 9px; border-left:2px solid #cbd5e1; background:#f8fafc; }
.device-item b { overflow:hidden; color:#334155; font-size:12px; text-overflow:ellipsis; white-space:nowrap; }
.device-item span,.device-item small { color:#64748b; font-size:11px; line-height:1.45; }
.money-meta,.no-voucher,.no-device,.empty-detail { margin-top:6px; color:#94a3b8; font-size:11px; }
.offset-note { padding:12px; border:1px solid #fde68a; border-radius:6px; color:#92400e; background:#fffbeb; font-size:12px; line-height:1.6; }
.document-meta { margin:12px 0 0; }
.document-meta div { display:flex; justify-content:space-between; gap:14px; padding:7px 0; border-top:1px solid #eef2f7; font-size:11px; }
.document-meta dt { color:#94a3b8; }
.document-meta dd { margin:0; color:#475569; text-align:right; word-break:break-all; }
@media (max-width:900px) { .summary-grid,.detail-grid { grid-template-columns:1fr; } .card-head { align-items:flex-start; } }
</style>
