<template>
    <div class="return-detail-page" :class="{ 'is-embedded': embedded }" v-loading="loading">
        <HsxTitle v-if="!embedded" size="page" collapsible-subtitle class="mb-4">
            <template #prefix><el-button link type="primary" class="back-button" @click="backToList">← 返回销售退货</el-button></template>
            <template #default>{{ isCompensation ? '售后补差详情' : '销售退货详情' }}</template>
            <template #subtitle>{{ isCompensation ? '核对按设备记录的售后补差、毛利变化和付款结果。' : '核对客户退回的设备、退款事实和财务处理结果。' }}</template>
            <template #extra><el-tag v-if="detail.id" :type="statusTagType(detail.status)" effect="plain">{{ statusLabel(detail.status) }}</el-tag><div v-if="detail.id && detail.status === 'pending'" class="head-actions">
                    <el-button type="danger" plain @click="cancelReturn">取消退货单</el-button>
                    <el-button type="primary" :loading="confirming" @click="openConfirm">确认收货</el-button>
                </div></template>
        </HsxTitle>

        <div v-else-if="detail.id" class="drawer-action-bar">
            <div class="drawer-status">
                <span>{{ detail.return_no || '-' }}</span>
                <el-tag :type="statusTagType(detail.status)" effect="plain">{{ statusLabel(detail.status) }}</el-tag>
            </div>
            <div v-if="detail.status === 'pending'" class="head-actions">
                <el-button type="danger" plain @click="cancelReturn">取消退货单</el-button>
                <el-button type="primary" :loading="confirming" @click="openConfirm">确认收货</el-button>
            </div>
        </div>

        <template v-if="detail.id">
            <section class="identity-card">
                <div>
                    <span class="eyebrow">{{ isCompensation ? '补差客户' : '退货客户' }}</span>
                    <strong>{{ detail.party_name || '-' }}</strong>
                    <small>{{ detail.return_no || '-' }}</small>
                </div>
                <div class="source-order">
                    <span>关联销售单</span>
                    <b>{{ detail.sale_no || '-' }}</b>
                </div>
            </section>

            <section class="metric-grid">
                <div class="metric"><span>{{ isCompensation ? '补差设备' : '退货设备' }}</span><b>{{ detail.items?.length || 0 }} 台</b></div>
                <div class="metric money"><span>{{ isCompensation ? '补差金额' : '退货金额' }}</span><b>¥{{ money(detail.total_amount) }}</b></div>
                <div class="metric"><span>退款方式</span><b>{{ refundModeLabel(detail.refund_mode) }}</b></div>
                <div class="metric"><span>当前处理</span><b>{{ processSummary }}</b></div>
            </section>

            <div class="content-grid">
                <main>
                    <div class="section-head">
                        <div><h2>{{ isCompensation ? '补差设备' : '退货设备' }}</h2><p>设备名称、规格和 IMEI 为主要核对信息。</p></div>
                    </div>
                    <div class="device-list">
                        <article v-for="item in detail.items || []" :key="item.id" class="device-card">
                            <div class="device-head">
                                <div class="device-title">
                                    <h3>{{ item.model || '未填写设备名称' }}</h3>
                                    <p>{{ item.spec || '未填写规格' }}</p>
                                </div>
                                <el-tag :type="financeTagType(item)" size="small" effect="light">{{ financeText(item) }}</el-tag>
                            </div>
                            <div class="device-identifiers">
                                <span class="imei">IMEI {{ item.imei || item.sn || '-' }}</span>
                                <span>原仓位 {{ item.warehouse_name || '-' }} / {{ item.location_name || '-' }}</span>
                            </div>
                            <div class="device-money">
                                <div><span>原销售价</span><b>¥{{ money(item.sale_price) }}</b></div>
                                <div><span>{{ isCompensation ? '本次补差' : '本次退货价' }}</span><b class="refund">¥{{ money(item.return_price) }}</b></div>
                                <div><span>该设备已收</span><b>¥{{ money(item.received_amount) }}</b></div>
                                <div><span>{{ refundPayableLabel }}</span><b class="refund">¥{{ money(detail.status === 'cancelled' ? 0 : item.refund_payable_amount) }}</b></div>
                            </div>
                            <div v-if="isCompensation" class="device-settlement is-confirmed">设备保持已售；销售毛利减少 ¥{{ money(item.return_price) }}；补差应付 {{ item.refund_payable_no || '-' }}，已支付 ¥{{ money(item.refund_settled_amount) }}，待支付 ¥{{ money(item.refund_remain_amount) }}</div>
                            <div v-else-if="detail.status === 'cancelled'" class="device-settlement is-cancelled">本设备不处理：不回库、不冲销应收、不生成退款应付。</div>
                            <div v-else-if="detail.status === 'confirmed'" class="device-settlement is-confirmed">
                                应付单 {{ item.refund_payable_no || '-' }} · 应退 ¥{{ money(item.refund_payable_amount) }} · 已退 ¥{{ money(item.refund_settled_amount) }} · 待退 ¥{{ money(item.refund_remain_amount) }}
                            </div>
                            <div v-else class="device-settlement">确认收货后：冲销未收应收 ¥{{ money(item.unreceived_offset_amount) }}；生成退款应付 ¥{{ money(item.refund_payable_amount) }}</div>
                            <div class="device-reason"><span>{{ isCompensation ? '补差原因' : '退货原因' }}</span><p>{{ item.reason || '未填写' }}</p></div>
                        </article>
                    </div>
                </main>

                <aside>
                    <section class="side-card result-card" :class="`is-${detail.status}`">
                        <span class="eyebrow">处理结论</span>
                        <h2>{{ processSummary }}</h2>
                        <p>{{ refundModeTip }}</p>
                    </section>
                    <section class="side-card">
                        <h3>单据信息</h3>
                        <dl>
                            <div><dt>{{ isCompensation ? '补差单号' : '退货单号' }}</dt><dd>{{ detail.return_no || '-' }}</dd></div>
                            <div><dt>原销售单</dt><dd>{{ detail.sale_no || '-' }}</dd></div>
                            <div><dt>业务操作人</dt><dd>{{ detail.operator_name || '-' }}</dd></div>
                            <div><dt>创建时间</dt><dd>{{ formatTime(detail.occurred_at || detail.create_at) }}</dd></div>
                            <div><dt>最后更新</dt><dd>{{ formatTime(detail.update_at) }}</dd></div>
                        </dl>
                    </section>
                    <section class="side-card">
                        <h3>备注</h3>
                        <p class="remark">{{ detail.remark || '暂无备注' }}</p>
                    </section>
                </aside>
            </div>
        </template>

        <el-empty v-else-if="!loading" description="未找到销售退货单">
            <el-button type="primary" @click="backToList">返回列表</el-button>
        </el-empty>
        <HsxDialog :confirm-loading="confirming" v-model="confirmVisible" title="确认收到退货设备" width="520px" :destroy-on-close="false">
            <div class="confirm-summary">确认 {{ detail.items?.length || 0 }} 台设备已实际交回，退款合计 ¥{{ money(detail.total_amount) }}</div>
            <el-form label-width="90px">
                <el-form-item v-if="requiresCashRefund" label="退款账户" required>
                    <el-select v-model="confirmAccountId" class="w-full" placeholder="选择实际退款账户">
                        <el-option v-for="account in accounts" :key="account.id" :label="`${account.account_name}（余额 ¥${money(account.balance)}）`" :value="account.id" />
                    </el-select>
                    <div class="account-tip">现场退款将立即扣减该账户余额并完成设备级退款核销。</div>
                </el-form-item>
                <el-form-item v-if="requiresCashRefund" label="付款凭证"><ErpFinanceVoucherUpload v-model="confirmVoucherUrls" /></el-form-item>
            </el-form>
            <template #footer><el-button :disabled="confirming" @click="confirmVisible=false">返回检查</el-button><el-button :disabled="confirming" type="primary" :loading="confirming" @click="confirmReturn">{{ confirmActionLabel }}</el-button></template>
        </HsxDialog>
    </div>
</template>

<script setup lang="ts">
import { HsxDialog, useFeedback, HsxTitle } from '@/addon/hsx_components/core'
import { erpEnumLabel } from '@/addon/hsx_erp/utils/display'
import { computed, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessageBox } from 'element-plus'
import { cancelErpSaleReturn, confirmErpSaleReturn, getErpSaleReturnInfo } from '@/addon/hsx_erp/api/erp'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { useErpPageRefresh } from '@/addon/hsx_erp/hooks/useErpPageRefresh'
import ErpFinanceVoucherUpload from '@/addon/hsx_erp/components/ErpFinanceVoucherUpload.vue'
const hsxFeedback = useFeedback()


const route = useRoute()
const router = useRouter()
const props = withDefaults(defineProps<{ id?: number; embedded?: boolean }>(), { id: 0, embedded: false })
const emit = defineEmits<{ (e: 'updated'): void; (e: 'close'): void }>()
const loading = ref(false)
const confirming = ref(false)
const confirmVisible = ref(false)
const confirmAccountId = ref(0)
const confirmVoucherUrls = ref('')
const accounts = ref<any[]>([])
const detail = reactive<any>({ items: [] })
const returnId = computed(() => Number(props.id || route.query.id || 0))

const money = (value: any) => Number(value || 0).toFixed(2)
const isCompensation = computed(() => detail.business_type === 'after_sale_compensation')
const compensationPaid = computed(() => isCompensation.value && (detail.items || []).length > 0 && (detail.items || []).every((item: any) => Number(item.refund_remain_amount || 0) <= 0.001))
const expectedRefundPayable = computed(() => (detail.items || []).reduce((sum: number, item: any) => sum + Number(item.refund_payable_amount || 0), 0))
const requiresCashRefund = computed(() => detail.refund_mode === 'cash' && expectedRefundPayable.value > 0.001)
const confirmActionLabel = computed(() => expectedRefundPayable.value > 0.001 ? (detail.refund_mode === 'cash' ? '确认收货并现场退款' : '确认收货并生成退款应付') : '确认收货并冲销应收')

function statusLabel(status: string) {
    if (isCompensation.value) return status === 'cancelled' ? '补差已取消' : '补差已确认'
    return erpEnumLabel(status, { pending: '待确认收货', confirmed: '已完成退货', cancelled: '已取消' })
}

function statusTagType(status: string) {
    return ({ pending: 'warning', confirmed: 'success', cancelled: 'info' } as Record<string, any>)[status] || ''
}

function refundModeLabel(mode: string) {
    return erpEnumLabel(mode, { cash: '现场退款', payable: '转财务退款', offset: '往来折抵', balance: '余额退回' }, '退款方式待确认')
}

const processSummary = computed(() => {
    if (isCompensation.value) return compensationPaid.value ? '公司已向客户支付补差款' : '补差已确认，公司待向客户付款'
    if (detail.status === 'cancelled') return '退货已取消'
    if (detail.status === 'confirmed') return '设备已回库，账务已处理'
    return '等待确认收到设备'
})
const refundPayableLabel = computed(() => isCompensation.value ? '补差应付' : detail.status === 'confirmed' ? '退款应付' : detail.status === 'cancelled' ? '退款应付' : '预计退款应付')

const refundModeTip = computed(() => {
    if (isCompensation.value) return compensationPaid.value
        ? '设备仍由客户持有，不发生回库；补差已逐台减少销售毛利，财务付款已经完成。'
        : '设备仍由客户持有，不发生回库；补差已逐台减少销售毛利，并生成待支付的客户补差应付。'
    if (detail.status === 'cancelled') return '本单未执行设备回库，也未产生后续退款处理。'
    if (detail.refund_mode === 'offset') return '确认收到设备后，未收款部分冲销原应收；已收款部分逐台进入应付冲减。'
    if (detail.refund_mode === 'cash') return '现场退款已从所选资金账户直接支付并完成设备级核销，无需财务再次处理。'
    return '确认收到设备后，未收款部分冲销原应收；已收款部分逐台形成客户退款应付，由财务后续付款。'
})

function financeText(item: any) {
    if (isCompensation.value) return item.refund_payable_status === 'settled' ? '公司已支付补差' : '公司待支付补差'
    if (detail.status === 'cancelled') return '无需处理'
    const received = Number(item.received_amount || 0)
    const returned = Number(item.return_price || 0)
    if (detail.status === 'confirmed') {
        if (item.refund_payable_status === 'settled') return '退款已结清'
        if (Number(item.refund_remain_amount || 0) > 0) return '退款待支付'
        return '已冲销应收'
    }
    if (received <= 0) return '冲销应收'
    if (received >= returned - 0.01) return '预计全额退款'
    return '预计冲销并退款'
}

function financeTagType(item: any) {
    if (isCompensation.value) return item.refund_payable_status === 'settled' ? 'success' : 'warning'
    if (detail.status === 'cancelled') return 'info'
    if (detail.status === 'confirmed' && item.refund_payable_status === 'settled') return 'success'
    const received = Number(item.received_amount || 0)
    const returned = Number(item.return_price || 0)
    if (received <= 0) return 'info'
    if (received >= returned - 0.01) return 'warning'
    return 'primary'
}

function formatTime(value: any) {
    const ts = Number(value || 0)
    if (!ts) return '-'
    const date = new Date(ts * 1000)
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

async function loadDetail() {
    if (!returnId.value) return
    loading.value = true
    try {
        const res = await getErpSaleReturnInfo(returnId.value)
        Object.assign(detail, res.data || {}, { items: res.data?.items || [] })
    } finally {
        loading.value = false
    }
}
async function loadPage() {
    await Promise.all([loadDetail(), getCapitalAccounts().then((res: any) => { accounts.value = Array.isArray(res?.data) ? res.data : (res?.data?.list || []) })])
}

function backToList() {
    if (props.embedded) {
        emit('close')
        return
    }
    router.back()
}

function openConfirm() {
    if (detail.refund_mode === 'cash') {
        confirmAccountId.value = Number(detail.capital_account_id || accounts.value[0]?.id || 0)
        confirmVoucherUrls.value = ''
        confirmVisible.value = true
        return
    }
    confirmReturn()
}

async function confirmReturn() {
    if (requiresCashRefund.value && !confirmAccountId.value) return hsxFeedback.warning('请选择实际退款账户')
    const confirmed = await ElMessageBox.confirm(
        `确认销售退货 ${detail.items?.length || 0} 台，金额 ¥${money(detail.total_amount)}？确认后设备回到原仓位，并同步处理客户账款。`,
        '确认收到退货设备',
        { type: 'warning', confirmButtonText: confirmActionLabel.value, cancelButtonText: '返回检查' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    confirming.value = true
    try {
        await confirmErpSaleReturn(returnId.value, { capital_account_id: confirmAccountId.value, voucher_urls: confirmVoucherUrls.value })
        hsxFeedback.success('销售退货已确认')
        confirmVisible.value = false
        await loadDetail()
        emit('updated')
    } finally {
        confirming.value = false
    }
}

async function cancelReturn() {
    const confirmed = await ElMessageBox.confirm(
        '取消后设备不回库、不生成退款应付，视为客户继续持有设备，是否继续？',
        '取消销售退货单',
        { type: 'warning', confirmButtonText: '确认取消', cancelButtonText: '暂不取消' }
    ).then(() => true).catch(() => false)
    if (!confirmed) return
    await cancelErpSaleReturn(returnId.value)
    hsxFeedback.success('销售退货单已取消')
    await loadDetail()
    emit('updated')
}

useErpPageRefresh(loadPage)
watch(returnId, () => loadPage())
</script>

<style scoped>
.return-detail-page { min-height:100%; padding:20px; background:#fff; }
.return-detail-page.is-embedded { padding:0 4px 16px; }
.drawer-action-bar { display:flex; align-items:center; justify-content:space-between; gap:16px; margin-bottom:14px; padding:0 0 14px; border-bottom:1px solid #e5e7eb; }
.drawer-status { display:flex; min-width:0; align-items:center; gap:10px; color:#64748b; font-size:13px; }
.drawer-status span { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.page-head { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; margin-bottom:16px; padding:0 0 16px; border-bottom:1px solid #e5e7eb; background:#fff; }
.back-button { margin:0 0 8px; padding:0; }
.title-row { display:flex; align-items:center; gap:12px; }
.title-row h1 { margin:0; color:#111827; font-size:22px; font-weight:700; }
.page-head p { margin:6px 0 0; color:#64748b; font-size:13px; }
.head-actions { display:flex; gap:8px; }
.identity-card { display:flex; align-items:center; justify-content:space-between; gap:20px; padding:14px 16px; border:1px solid #e5e7eb; border-radius:6px; background:#f8fafc; }
.identity-card > div:first-child { display:flex; min-width:0; flex-direction:column; gap:4px; }
.eyebrow { color:#94a3b8; font-size:12px; }
.identity-card strong { overflow:hidden; color:#111827; font-size:18px; text-overflow:ellipsis; white-space:nowrap; }
.identity-card small { color:#64748b; font-size:13px; }
.source-order { display:flex; flex-direction:column; align-items:flex-end; gap:5px; }
.source-order span { color:#94a3b8; font-size:12px; }
.source-order b { color:#475569; font-size:14px; }
.metric-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:10px; margin:10px 0 16px; }
.metric { display:flex; min-height:70px; flex-direction:column; justify-content:center; gap:5px; padding:11px 14px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; }
.metric span { color:#64748b; font-size:12px; }
.metric b { color:#1e293b; font-size:16px; }
.metric.money b { color:#ea580c; }
.content-grid { display:grid; grid-template-columns:minmax(0,1fr) 330px; gap:16px; align-items:start; }
.content-grid > main { min-width:0; padding:16px; border:1px solid #e5e7eb; border-radius:6px; background:#fff; }
.section-head { margin-bottom:14px; }
.section-head h2 { margin:0; color:#111827; font-size:17px; }
.section-head p { margin:5px 0 0; color:#94a3b8; font-size:12px; }
.device-list { display:grid; grid-template-columns:repeat(auto-fit,minmax(360px,1fr)); gap:12px; }
.device-card { min-width:0; padding:16px; border:1px solid #e2e8f0; border-radius:6px; background:#fff; }
.device-head { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.device-title { min-width:0; }
.device-title h3 { overflow:hidden; margin:0; color:#111827; font-size:16px; text-overflow:ellipsis; white-space:nowrap; }
.device-title p { overflow:hidden; margin:5px 0 0; color:#64748b; font-size:13px; text-overflow:ellipsis; white-space:nowrap; }
.device-identifiers { display:flex; flex-wrap:wrap; gap:6px 12px; margin:13px 0; color:#64748b; font-size:12px; }
.device-identifiers span { padding:4px 8px; border-radius:4px; background:#f1f5f9; }
.device-identifiers .imei { color:#334155; font-weight:650; }
.device-money { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:8px; padding:12px 0; border-top:1px solid #eef2f7; border-bottom:1px solid #eef2f7; }
.device-money div { display:flex; flex-direction:column; gap:5px; }
.device-money span { color:#94a3b8; font-size:11px; }
.device-money b { color:#334155; font-size:13px; }
.device-money .refund { color:#ea580c; }
.device-settlement { margin-top:10px; padding:8px 10px; border-radius:4px; color:#475569; background:#f8fafc; font-size:12px; }
.device-settlement.is-cancelled { color:#64748b; background:#f1f5f9; }
.device-settlement.is-confirmed { color:#166534; background:#f0fdf4; }
.device-reason { display:flex; gap:12px; margin-top:12px; font-size:12px; }
.device-reason span { flex:0 0 auto; color:#94a3b8; }
.device-reason p { margin:0; color:#475569; }
.content-grid > aside { display:flex; flex-direction:column; gap:12px; }
.side-card { padding:17px; border:1px solid #e2e8f0; background:#fff; }
.side-card h3 { margin:0 0 13px; color:#1e293b; font-size:15px; }
.result-card { border-color:#dbeafe; background:#eff6ff; }
.result-card h2 { margin:6px 0 8px; color:#1d4ed8; font-size:17px; }
.result-card.is-confirmed { border-color:#bbf7d0; background:#f0fdf4; }
.result-card.is-confirmed h2 { color:#166534; }
.result-card.is-cancelled { border-color:#e2e8f0; background:#f8fafc; }
.result-card.is-cancelled h2 { color:#475569; }
.result-card p, .remark { margin:0; color:#64748b; font-size:12px; line-height:1.7; }
.side-card dl { margin:0; }
.side-card dl div { display:flex; justify-content:space-between; gap:16px; padding:9px 0; border-bottom:1px solid #f1f5f9; font-size:12px; }
.side-card dl div:last-child { border-bottom:0; }
.side-card dt { flex:0 0 auto; color:#94a3b8; }
.side-card dd { margin:0; color:#334155; text-align:right; word-break:break-all; }
.confirm-summary { margin-bottom:16px; padding:12px; border-radius:6px; color:#334155; background:#f8fafc; }
.account-tip { margin-top:6px; color:#64748b; font-size:12px; line-height:1.5; }
@media (max-width: 980px) {
    .metric-grid { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .content-grid { grid-template-columns:1fr; }
}
@media (max-width: 640px) {
    .return-detail-page { padding:12px; }
    .page-head, .identity-card { align-items:stretch; flex-direction:column; }
    .head-actions { justify-content:flex-end; }
    .source-order { align-items:flex-start; }
    .metric-grid, .device-list { grid-template-columns:1fr; }
    .device-money { grid-template-columns:repeat(2,minmax(0,1fr)); }
}
</style>
