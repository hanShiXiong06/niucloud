<template>
    <view class="payment-orders-page" :style="themeColor()">
        <CoopTaskSheet page :show="true" title="我的付款单" fixed-body :busy="!!busyId" @close="backCooperationTask">
            <template #filters><view class="order-filters"><view class="order-filters__inner"><button v-for="item in cooperationPaymentOrderStatuses" :key="item.value" :class="{ 'is-active': status === item.value }" :disabled="!!busyId" @tap="filter(item.value)">{{ item.label }}</button></view></view><CoopPaymentHelp :active="active && loggedIn" :inset="12" :spacing="12" /></template>
            <z-paging v-if="active && loggedIn" :key="revision" ref="pagingRef" :model-value="rows" class="orders-pager" :fixed="false" height="100%" :auto="true" :concat="false" :scrollable="!busyId" :refresher-enabled="!busyId" :default-page-size="20" :auto-clean-list-when-reload="false" :safe-area-inset-bottom="false" :call-network-reject="false" :show-loading-more-no-more-line="false" loading-more-fail-text="读取失败，点击重试" loading-more-no-more-text="已显示全部付款单" @query="pagingQuery">
                <view v-if="error && rows.length" class="order-error" role="alert">{{ error }}</view>
                <view v-for="row in rows" :key="row.id" class="payment-order">
                    <view class="payment-order__head"><view class="payment-order__title">{{ row.title || row.level_name || (row.purpose === 'membership' ? '会员开通 / 续费' : '成交协作付佣') }}</view><text class="payment-order__status" :class="'is-' + row.status">{{ row.status_label || orderStatusLabel(row.status) }}</text></view>
                    <view class="payment-order__number">{{ row.order_no }}</view>
                    <view class="payment-order__amount"><text>{{ row.status === 'unpaid' ? '待付金额' : row.status === 'paid' ? '已付金额' : '订单金额' }}</text><text>{{ coopMoney(row.amount) }}</text></view>
                    <view v-if="row.purpose === 'membership' && row.cost_schema" class="payment-order__costs"><text>服务费 {{ coopMoney(row.service_amount) }}</text><text>押金 {{ coopMoney(row.deposit_amount) }}</text><text>经验费 {{ coopMoney(row.experience_amount) }}</text></view>
                    <view class="payment-order__payment"><text>{{ row.payment_display_name || row.payment_type_name || (row.status === 'paid' ? '支付方式待核对' : '尚未支付') }}</text><text v-if="row.payment_channel_name">{{ row.payment_channel_name }}</text></view>
                    <view v-if="row.refund_status && row.refund_status !== 'none'" class="payment-order__refund"><text>{{ row.refund_status_name || '退款待核对' }}</text><text v-if="Number(row.refunded_amount) > 0">已退 {{ coopMoney(row.refunded_amount) }}</text></view>
                    <view class="payment-order__time">创建于 {{ coopDate(row.create_time) }}</view><view v-if="row.status === 'unpaid' && row.can_pay && row.expires_at" class="payment-order__expires">请于 {{ coopDate(row.expires_at) }} 前支付</view><view v-else-if="row.status === 'unpaid' && row.can_close && !row.can_pay" class="payment-order__expires">付款时限已过，可取消此单后重新核价</view><view v-else-if="row.status === 'closed' && row.closure_reason" class="payment-order__expires">{{ row.closure_reason }}</view>
                    <view v-if="row.requires_manual_review" class="payment-order__review">款项需平台核对，请勿重复支付</view>
                    <view v-if="row.status === 'paid' && row.can_request_refund !== true && row.refund_block_reason" class="payment-order__review">{{ row.refund_block_reason }}</view>
                    <view v-if="row.pricing_reason" class="payment-order__review">平台调价：{{ row.pricing_reason }}</view>
                    <view v-if="row.status === 'unpaid' || cooperationPaymentReplacementId(row)" class="payment-order__actions"><button v-if="row.can_close" :disabled="!!busyId" @tap="cancelOrder(row)">{{ row.close_action_label || '取消付款单' }}</button><button v-if="row.can_pay || cooperationPaymentReplacementId(row)" class="is-primary" :disabled="!!busyId" @tap="continuePayment(row)">{{ busyId === row.id ? '核对中…' : cooperationPaymentReplacementId(row) ? '查看调整后付款单' : '继续支付' }}</button><text v-if="!row.can_close && !row.can_pay && !cooperationPaymentReplacementId(row)">{{ row.close_reason || '请刷新核对原付款状态' }}</text></view>
                    <view v-else-if="row.can_request_refund === true || row.refund_status && row.refund_status !== 'none'" class="payment-order__actions"><button :disabled="!!busyId" @tap="openRefund(row)">{{ row.can_request_refund === true ? '申请退款' : '查看退款进度' }}</button></view>
                </view>
                <template #empty="{ isLoadFailed }"><view class="orders-empty"><CoopState :error="isLoadFailed ? error || '付款单读取失败，请重试' : ''" title="暂无这类付款单" icon="order" @retry="refresh" /></view></template>
            </z-paging>
            <view v-else-if="!loggedIn" class="orders-empty"><CoopState title="登录后查看付款单" icon="account" /><u-button :custom-style="cooperationButtonStyle" type="primary" shape="circle" text="登录" @click="login" /></view>
        </CoopTaskSheet>
        <PaymentRefundSheet :show="refundVisible" :order="refundOrder" :detail="refundDetail" :loading="refundLoading" :busy="refundBusy" :error="refundError" :read-required="refundReadRequired" @close="closeRefund" @reload="readRefund" @refresh="refreshRefund" @cancel="withdrawRefund" @submit="submitRefund" />
    </view>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { onHide, onLoad, onShow } from '@dcloudio/uni-app'
import { getSiteId, getToken } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import ZPaging from 'z-paging/components/z-paging/z-paging.vue'
import CoopTaskSheet from '../../components/cooperation/CoopTaskSheet.vue'
import CoopState from '../../components/cooperation/CoopState.vue'
import CoopPaymentHelp from '../../components/cooperation/CoopPaymentHelp.vue'
import PaymentRefundSheet from './components/PaymentRefundSheet.vue'
import { cooperationOrders, cooperationOrder, closeCooperationOrder, cooperationOrderRefund, requestCooperationOrderRefund, refreshCooperationOrderRefund, cancelCooperationOrderRefund, coopRequestId, coopDate, coopMoney, coopError, type CoopRow } from '../../api/cooperation'
import { cooperationPaymentOrderStatuses, cooperationPaymentOrderStatus, cooperationPaymentOrderResumeUrl, cooperationPaymentReplacementId, cooperationPaymentReplacementMatches, cooperationPaymentRefundDetail } from '../../utils/cooperation-payment-orders'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
import { backCooperationTask } from '../../utils/cooperation-navigation'
import { createCooperationPagingGuard } from '../../utils/cooperation-paging'
const rows = ref<CoopRow[]>([]), status = ref('unpaid'), error = ref(''), busyId = ref(0), active = ref(false), loggedIn = ref(!!getToken()), revision = ref(0), pagingRef = ref<any>(null)
let sequence = 0, owner = ''
const refundVisible = ref(false), refundOrder = ref<CoopRow>({}), refundDetail = ref<CoopRow>({}), refundLoading = ref(false), refundBusy = ref(false), refundReadRequired = ref(false), refundError = ref('')
let refundSequence = 0
const refundRequests = new Map<number, string>()
const pagingGuard = createCooperationPagingGuard()
const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
const current = (who: string) => active.value && !!getToken() && who === owner && who === identity()
const pagingQuery = computed(() => { const version = revision.value; return (page: number, limit: number) => query(page, limit, version) })
async function query(page: number, limit: number, version: number) {
    if (!active.value || !loggedIn.value || version !== revision.value) return
    if (!pagingGuard.begin(page)) { refresh(); return }
    const who = owner, turn = ++sequence, filterStatus = status.value
    try { const response: any = await cooperationOrders({ status: filterStatus, page, limit }), data = response?.data; if (!current(who) || turn !== sequence || version !== revision.value) return; if (!Array.isArray(data?.data) || !Number.isFinite(Number(data.total))) throw new Error('付款单资料未完整返回，请刷新核对'); rows.value = page > 1 ? [...new Map([...rows.value, ...data.data].map(row => [row.id, row])).values()] : data.data; error.value = ''; pagingGuard.succeed(page); void pagingRef.value?.completeByNoMore([...rows.value], rows.value.length >= Number(data.total)) }
    catch (cause) { if (current(who) && turn === sequence && version === revision.value) { error.value = coopError(cause, '付款单读取失败，请重试'); void pagingRef.value?.complete(false) } }
}
function refresh() { if (busyId.value) return; sequence++; pagingGuard.reset(); error.value = ''; revision.value++ }
function filter(value: string) { const next = cooperationPaymentOrderStatus(value); if (busyId.value || status.value === next) return; status.value = next; rows.value = []; refresh() }
function orderStatusLabel(value: string) { return cooperationPaymentOrderStatuses.find(item => item.value === value)?.label || '状态待核对' }
async function continuePayment(row: CoopRow) {
    if (busyId.value || (row.can_pay !== true && !cooperationPaymentReplacementId(row)) || !current(owner)) return
    const who = owner; busyId.value = Number(row.id)
    try {
        let id = Number(row.id), previous: CoopRow | null = null, order: CoopRow = {}; const seen = new Set<number>()
        for (let hop = 0; hop < 6; hop++) {
            if (seen.has(id)) throw new Error('付款单替换关系异常，请联系平台核对'); seen.add(id)
            const response: any = await cooperationOrder(id); if (!current(who)) return; order = response?.data || {}
            if (Number(order.id) !== id || previous && !cooperationPaymentReplacementMatches(previous, order)) throw new Error('调整后付款单与原业务不一致，请联系平台核对')
            const replacement = cooperationPaymentReplacementId(order); if (!replacement) break; previous = order; id = replacement
            if (hop === 5) throw new Error('付款单多次调整，请刷新核对最新订单')
        }
        const url = cooperationPaymentOrderResumeUrl(order); if (!url) throw new Error(order.status === 'paid' ? '这笔付款单已支付，无需重复付款' : order.close_reason || '这笔付款单当前不能继续支付，请刷新核对'); uni.navigateTo({ url })
    }
    catch (cause) { if (current(who)) { uni.showToast({ title: coopError(cause, '暂不能继续支付，请核对原单'), icon: 'none' }); revision.value++ } }
    finally { busyId.value = 0 }
}
async function cancelOrder(row: CoopRow) {
    if (busyId.value || row.can_close !== true || !current(owner)) return
    const who = owner; busyId.value = Number(row.id)
    try {
        const confirmed = await new Promise<boolean>(resolve => uni.showModal({ title: row.close_requires_check ? '核对支付后取消？' : '取消待支付付款单？', content: `${row.title || row.order_no}，金额 ${coopMoney(row.amount)}。${row.close_requires_check ? '系统先核对渠道结果，已收款不会取消，结果不明时保留原单。' : '只取消这笔付款单，不会撤销客户线索。'}`, confirmText: row.close_requires_check ? '核对后取消' : '确认取消', cancelText: '暂不取消', success: result => resolve(result.confirm), fail: () => resolve(false) }))
        if (!confirmed || !current(who)) return
        const response: any = await closeCooperationOrder(Number(row.id)), order = response?.data
        if (!current(who)) return
        if (Number(order?.id) !== Number(row.id) || order.status !== 'closed') throw new Error(order?.close_message || '取消结果尚未确认，请刷新核对原单，勿重复操作')
        uni.showToast({ title: '付款单已取消', icon: 'none' }); revision.value++
    } catch (cause) { if (current(who)) { uni.showToast({ title: coopError(cause, '取消结果待核对，请刷新原单'), icon: 'none' }); revision.value++ } }
    finally { busyId.value = 0 }
}
function closeRefund() { if (!refundBusy.value) { refundVisible.value = false; refundSequence++; refundLoading.value = false } }
async function openRefund(row: CoopRow) {
    if (busyId.value || !current(owner) || !Number.isSafeInteger(Number(row.id)) || Number(row.id) <= 0) return
    refundOrder.value = { ...row }; refundDetail.value = {}; refundError.value = ''; refundReadRequired.value = true; refundVisible.value = true
    await readRefund()
}
async function readRefund() {
    if (refundBusy.value || !refundVisible.value || !current(owner)) return
    const who = owner, id = Number(refundOrder.value.id), turn = ++refundSequence
    refundLoading.value = true; refundError.value = ''
    try {
        const response: any = await cooperationOrderRefund(id)
        if (!current(who) || turn !== refundSequence || !refundVisible.value) return
        refundDetail.value = cooperationPaymentRefundDetail(response?.data, id); refundReadRequired.value = false
        if (refundDetail.value.can_request === true && !refundRequests.has(id)) refundRequests.set(id, coopRequestId())
    } catch (cause) { if (current(who) && turn === refundSequence) { refundReadRequired.value = true; refundError.value = coopError(cause, '退款资料读取失败，请核对原申请') } }
    finally { if (turn === refundSequence) refundLoading.value = false }
}
async function submitRefund(reason: string) {
    if (busyId.value || refundLoading.value || refundBusy.value || refundReadRequired.value || refundDetail.value.can_request !== true || !refundVisible.value || !current(owner)) return
    const text = String(reason || '').trim(), who = owner, id = Number(refundOrder.value.id)
    if (text.length < 4 || text.length > 500) { refundError.value = '请填写4至500个字的退款原因'; return }
    const requestId = refundRequests.get(id); if (!requestId) { refundReadRequired.value = true; refundError.value = '请先重新读取原申请'; return }
    busyId.value = id; refundBusy.value = true; refundError.value = ''
    try {
        const confirmed = await new Promise<boolean>(resolve => uni.showModal({ title: '确认提交退款申请？', content: `${refundOrder.value.order_no}，申请金额 ${coopMoney(refundDetail.value.amount)}。平台审核后按原支付方式退回，不会立即到账。`, confirmText: '提交申请', cancelText: '继续填写', success: result => resolve(result.confirm), fail: () => resolve(false) }))
        if (!confirmed || !current(who) || !refundVisible.value) return
        refundReadRequired.value = true
        const response: any = await requestCooperationOrderRefund(id, { confirm: true, reason: text, request_id: requestId })
        if (!current(who) || !refundVisible.value) return
        const detail = cooperationPaymentRefundDetail(response?.data, id)
        if (detail.status === 'none' || detail.can_request === true) throw Error('申请结果尚未确认，请读取原申请，不要重复提交')
        refundDetail.value = detail; refundReadRequired.value = false
        refundRequests.delete(id); revision.value++
        uni.showToast({ title: detail.status === 'pending' ? '申请已提交，等待平台审核' : detail.status_name || '请查看原退款进度', icon: 'none' })
    } catch (cause) { if (current(who)) { refundReadRequired.value = true; refundError.value = coopError(cause, '申请结果待核对，请读取原申请，勿重复提交'); revision.value++ } }
    finally { refundBusy.value = false; busyId.value = 0 }
}
async function refreshRefund() {
    if (busyId.value || refundLoading.value || refundBusy.value || refundReadRequired.value || refundDetail.value.can_refresh !== true || !refundVisible.value || !current(owner)) return
    const who = owner, id = Number(refundOrder.value.id); busyId.value = id; refundBusy.value = true; refundError.value = ''
    try {
        const response: any = await refreshCooperationOrderRefund(id)
        if (!current(who) || !refundVisible.value) return
        refundDetail.value = cooperationPaymentRefundDetail(response?.data, id); revision.value++
        uni.showToast({ title: refundDetail.value.status === 'refunded' ? '渠道已确认退款' : refundDetail.value.status_name || '已读取原退款进度', icon: 'none' })
    } catch (cause) { if (current(who)) { refundReadRequired.value = true; refundError.value = coopError(cause, '原退款进度暂未核实，请重新读取，不要重复申请') } }
    finally { refundBusy.value = false; busyId.value = 0 }
}
async function withdrawRefund() {
    if (busyId.value || refundLoading.value || refundBusy.value || refundReadRequired.value || refundDetail.value.can_cancel !== true || !refundVisible.value || !current(owner)) return
    const who = owner, id = Number(refundOrder.value.id), version = refundDetail.value.request_version
    if (typeof version !== 'string' || !version) { refundReadRequired.value = true; refundError.value = '原申请版本未核实，请重新读取'; return }
    busyId.value = id; refundBusy.value = true; refundError.value = ''
    try {
        const confirmed = await new Promise<boolean>(resolve => uni.showModal({ title: '撤回退款申请？', content: `${refundOrder.value.order_no}。仅待平台审核的申请可以撤回；已发起的退款不会取消。`, confirmText: '撤回申请', cancelText: '保留申请', success: result => resolve(result.confirm), fail: () => resolve(false) }))
        if (!confirmed || !current(who) || !refundVisible.value) return
        refundReadRequired.value = true
        const response: any = await cancelCooperationOrderRefund(id, version)
        if (!current(who) || !refundVisible.value) return
        const detail = cooperationPaymentRefundDetail(response?.data, id)
        if (detail.can_cancel === true || !['none', 'rejected', 'cancelled'].includes(detail.status)) throw Error('撤回结果未确认，请重新读取原申请')
        refundDetail.value = detail; refundReadRequired.value = false; refundRequests.delete(id); revision.value++
        if (detail.can_request === true) refundRequests.set(id, coopRequestId())
        uni.showToast({ title: '退款申请已撤回', icon: 'none' })
    } catch (cause) { if (current(who)) { refundReadRequired.value = true; refundError.value = coopError(cause, '撤回结果待核对，请读取原申请') } }
    finally { refundBusy.value = false; busyId.value = 0 }
}
function login() { useLogin().setLoginBack({ url: '/addon/hsx_lead/pages/orders/index', param: { status: status.value }, mode: 'redirectTo' }) }
onLoad(options => { status.value = cooperationPaymentOrderStatus(options?.status) })
onShow(() => { loggedIn.value = !!getToken(); const next = identity(); if (next !== owner) { owner = next; rows.value = []; error.value = ''; refundVisible.value = false; refundOrder.value = {}; refundDetail.value = {}; refundRequests.clear(); refundSequence++ }; active.value = true; refresh(); if (loggedIn.value && refundVisible.value) void readRefund() })
onHide(() => { active.value = false; sequence++; refundSequence++; refundLoading.value = false })
</script>
<style scoped lang="scss">
.payment-orders-page { height:calc(100vh - var(--window-top,0px) - var(--window-bottom,0px));min-height:0;display:flex;flex-direction:column;overflow:hidden;background:#f5f6f8;color:#20252d; }
.order-filters { width:100%;white-space:nowrap; }.order-filters__inner { display:flex;padding:0 12px; }.order-filters button { flex:1;min-width:0;min-height:48px;line-height:48px;margin:0;padding:0 10px;font-size:14px;border:0;border-bottom:3px solid transparent;border-radius:0;background:transparent;color:#8b94a1; }.order-filters button::after { border:0; }.order-filters .is-active { color:var(--primary-color,#3978c2);border-bottom-color:var(--primary-color,#3978c2);font-weight:600; }
.orders-pager { flex:1 1 0;min-height:0;height:100%; }.orders-empty { padding:32px 16px; }.order-error { color:#a1604b;font-size:12px;padding:12px;line-height:1.7; }
.payment-order { margin:12px;background:#fff;padding:16px;border-radius:10px; }.payment-order__head { display:flex;align-items:flex-start;justify-content:space-between;gap:16px; }.payment-order__title { flex:1;min-width:0;font-size:15px;font-weight:600;line-height:1.6;word-break:break-word; }.payment-order__status { flex:none;max-width:40%;text-align:right;font-size:12px;color:#8591a0;line-height:1.8; }.payment-order__status.is-unpaid { color:#9b7e4d; }.payment-order__status.is-paid { color:#658573; }
.payment-order__number { margin-top:6px;font-size:11px;line-height:1.6;color:#9aa3af;word-break:break-all; }.payment-order__amount { display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px;font-size:13px;color:#828c9b; }.payment-order__amount>text:last-child { font-size:22px;font-weight:650;color:#293341;min-width:0;overflow-wrap:anywhere; }.payment-order__costs { display:flex;flex-wrap:wrap;gap:4px 12px;margin-top:8px;font-size:11px;color:#8a95a3;line-height:1.7; }.payment-order__time,.payment-order__expires { margin-top:8px;font-size:11px;line-height:1.6;color:#9aa3af; }.payment-order__review { margin-top:12px;color:#a1604b;font-size:12px;line-height:1.7; }
.payment-order__payment { display:flex;flex-wrap:wrap;gap:6px 12px;margin-top:12px;font-size:12px;color:#748295;line-height:1.7; }.payment-order__payment>text+text { color:#9aa3af; }.payment-order__refund { display:flex;justify-content:space-between;gap:12px;margin-top:10px;color:#9a7651;font-size:12px;line-height:1.7; }
.payment-order__actions { display:flex;flex-wrap:wrap;justify-content:flex-end;align-items:center;gap:12px;padding-top:14px;margin-top:14px;border-top:1px solid #f0f1f4; }.payment-order__actions button { margin:0;padding:0 16px;min-height:40px;line-height:40px;font-size:13px;border:1px solid #dce2eb;border-radius:20px;background:#fff;color:#65748a; }.payment-order__actions button::after { border:0; }.payment-order__actions button.is-primary { color:#fff;background:var(--primary-color,#3978c2);border-color:var(--primary-color,#3978c2); }.payment-order__actions>text { font-size:12px;color:#8b95a3;line-height:1.7; }
/* #ifdef H5 */
@supports(height:100dvh){.payment-orders-page{height:calc(100dvh - var(--window-top,0px) - var(--window-bottom,0px));}}
/* #endif */
</style>
