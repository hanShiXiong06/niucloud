import { computed, ref, type Ref } from 'vue'
import { getToken } from '@/utils/common'
import { cooperationOrder, coopError, coopRequestId, type CoopRow } from '../api/cooperation'
import { cooperationTaskLink } from '../utils/cooperation-navigation'
import {
    cooperationPaymentContext,
    cooperationOrderContext,
    sameCooperationPaymentContext,
    type CooperationPaymentContext
} from '../utils/cooperation-payment'
import { cooperationPaymentReplacementId, cooperationPaymentReplacementMatches } from '../utils/cooperation-payment-orders'
import type { useCooperation } from './useCooperation'

type CooperationUi = ReturnType<typeof useCooperation>

/** What the payment flow needs from the workbench that owns it. Functions are read at call time. */
export interface CommissionPaymentHost {
    ui: CooperationUi
    task: () => string
    initialId: () => number
    initialPaymentId: () => number
    /** Return URL for the main tabs (non-task pages). */
    pageUrl: string
    /** Page is shown (the workbench's pageActive). */
    active: () => boolean
    disposed: () => boolean
    /** A write or confirmation is in progress. */
    locked: () => boolean
    payRef: Ref<any>
    overlay: Ref<string>
    selectedId: Ref<number>
    refreshBusinessData: () => Promise<unknown>
    reloadDetail: () => Promise<unknown>
    markChanged: () => void
    openTask: (task: string, params?: CoopRow) => unknown
}

/**
 * Commission payment flow, moved out of CooperationWorkbench unchanged.
 *
 * Money rules kept as they were:
 * - one request id per purpose+lead, so a retried create never makes a second order;
 * - a returned order id is remembered (scoped to site/member/store/purpose/lead) before anything else,
 *   so the next visit resumes it instead of creating another;
 * - the cashier opens only after the order, its replacement chain, the lead and the payer all re-verify;
 * - a paid order that needs manual review is never offered for payment again.
 */
export function useCommissionPayment(host: CommissionPaymentHost) {
    const { ui } = host
    const paymentId = ref(0),
        paymentMessage = ref(''),
        paymentChecking = ref(false),
        paymentOrder = ref<CoopRow>({}),
        paymentCompleted = ref(false),
        paymentContextError = ref(''),
        paymentResuming = ref(false)
    const orderRequests = new Map<string, string>()
    let reviewNotifiedId = 0

    const expectedPaymentContext = computed(() =>
        host.task() === 'commission' ? cooperationPaymentContext('commission', host.initialId()) : null
    )
    const commissionPaymentClosed = computed(
        () => paymentOrder.value.status === 'closed' && Number(paymentOrder.value.lead_id) === Number(ui.detail.value.id)
    )
    const commissionLead = computed(() =>
        paymentOrder.value.status === 'unpaid' && Number(paymentOrder.value.lead_id) === Number(ui.detail.value.id)
            ? {
                  ...ui.detail.value,
                  payment_order_id: Number(paymentOrder.value.id),
                  commission_amount: paymentOrder.value.amount,
                  fee_amount: paymentOrder.value.fee_amount,
                  net_amount: paymentOrder.value.net_amount
              }
            : ui.detail.value
    )
    const cashierReturnUrl = computed(() =>
        host.task()
            ? cooperationTaskLink(host.task(), {
                  collaboration_id: host.initialId() || undefined,
                  payment_order_id: paymentId.value || paymentOrder.value.id || host.initialPaymentId() || undefined
              })
            : host.pageUrl
    )

    const storageKey = (context: CooperationPaymentContext | null = null) =>
        Number.isSafeInteger(ui.memberId.value) && ui.memberId.value > 0 && ui.siteId()
            ? `hsx_lead_coop_pay:${ui.siteId()}:${ui.memberId.value}:${ui.profile.value.id || 0}${context ? ':' + context.purpose + ':' + context.leadId : ''}`
            : ''
    function rememberPayment(context: CooperationPaymentContext, id: number) {
        const scoped = storageKey(context),
            latest = storageKey()
        if (!scoped || !latest) return
        uni.setStorageSync(scoped, id)
        uni.setStorageSync(latest, id) // Last-pending pointer is only used by the main tabs.
    }
    function releasePayment(context: CooperationPaymentContext, id: number) {
        for (const key of [storageKey(context), storageKey()]) if (key && Number(uni.getStorageSync(key)) === id) uni.removeStorageSync(key)
        orderRequests.delete(`${context.purpose}:${context.leadId}`)
    }
    function paymentIdentityIssue() {
        if (!Number.isSafeInteger(ui.memberId.value) || ui.memberId.value <= 0)
            return '当前登录信息未能核实，请重新进入后再试；本次未发起付款'
        if (!ui.siteId()) return '当前页面未识别到所属站点，请重新进入后再试；本次未发起付款'
        return ''
    }
    function validatePaymentOrder(order: CoopRow, id: number, expected = expectedPaymentContext.value) {
        const identityIssue = paymentIdentityIssue()
        if (identityIssue) {
            paymentContextError.value = identityIssue
            paymentMessage.value = identityIssue
            ui.writeError.value = identityIssue
            return null
        }
        const context = Number(order.id) === id ? cooperationOrderContext(order, ui.profile.value.id) : null
        if (!context || Number(order.member_id) !== ui.memberId.value || (expected && !sameCooperationPaymentContext(context, expected))) {
            paymentContextError.value = '原付款单与当前门店、业务或工单不一致，未打开收银台。请返回原业务核对付款链接。'
            paymentMessage.value = paymentContextError.value
            ui.writeError.value = paymentContextError.value
            return null
        }
        paymentContextError.value = ''
        return context
    }
    async function startOrder(purpose: string, amount?: string) {
        if (host.locked() || paymentChecking.value || paymentResuming.value || paymentContextError.value || paymentCompleted.value) return
        if (paymentId.value) {
            await resumePayment()
            return
        }
        const leadId = purpose === 'commission' ? Number(ui.detail.value.id) : 0
        const context = cooperationPaymentContext(purpose, leadId)
        if (!context || (expectedPaymentContext.value && !sameCooperationPaymentContext(context, expectedPaymentContext.value))) {
            ui.writeError.value = '付款用途或工单与当前页面不一致，请返回原业务重新打开'
            return
        }
        if (purpose === 'commission' && ui.detail.value.status === 'paid') {
            host.overlay.value = 'detail'
            return
        }
        if (!storageKey(context)) {
            ui.writeError.value = paymentIdentityIssue() || '付款信息已变化，请重新进入本单后再试；本次未发起付款'
            return
        }
        const key = `${purpose}:${leadId}`
        if (!orderRequests.has(key)) orderRequests.set(key, coopRequestId())
        const value = await ui.order({ purpose, ...(leadId ? { lead_id: leadId, amount } : {}), request_id: orderRequests.get(key) })
        if (!value?.id) return
        const id = Number(value.id)
        paymentId.value = id
        // A returned ID is protected from duplicate creation even while its context needs investigation.
        const pendingKey = storageKey(context)
        if (pendingKey) uni.setStorageSync(pendingKey, id)
        if (!validatePaymentOrder(value, id, context)) return
        paymentOrder.value = value
        rememberPayment(context, id)
        paymentMessage.value = '支付尚未确认，请勿重复创建订单'
        if (value.status !== 'unpaid') {
            await verifyPayment()
            return
        }
        await resumePayment()
    }
    function payCommission(amount: string) {
        return startOrder('commission', amount)
    }
    async function resumePayment() {
        if (!paymentId.value || paymentChecking.value || paymentResuming.value) return
        paymentResuming.value = true
        try {
            if (!(await verifyPayment())) return
            const id = paymentId.value
            if (host.disposed() || !id || paymentContextError.value || Number(paymentOrder.value.id) !== id || paymentOrder.value.status !== 'unpaid') return
            const context = validatePaymentOrder(paymentOrder.value, id)
            if (!context) return
            if (!expectedPaymentContext.value) {
                host.openTask('commission', { collaboration_id: context.leadId, payment_order_id: id })
                return
            }
            host.payRef.value?.open('hsx_lead_cooperation', id, cashierReturnUrl.value)
        } finally {
            paymentResuming.value = false
        }
    }
    async function verifyPayment() {
        if (!paymentId.value || paymentChecking.value || !getToken()) return false
        paymentChecking.value = true
        const who = ui.sessionScope(),
            id = paymentId.value
        try {
            let target = id,
                previous: CoopRow | null = null,
                order: CoopRow = {},
                context: CooperationPaymentContext | null = null
            const seen = new Set<number>()
            const current = () => !host.disposed() && host.active() && who === ui.sessionScope() && id === paymentId.value
            for (let hop = 0; hop < 6; hop++) {
                if (seen.has(target)) throw new Error('付佣订单替换关系异常，请查看付款单核对')
                seen.add(target)
                const response: any = await cooperationOrder(target)
                if (!current()) return false
                order = response.data || {}
                context = validatePaymentOrder(order, target)
                if (!context) return false
                if (previous && !cooperationPaymentReplacementMatches(previous, order))
                    throw new Error('调整后的付款单与原门店、工单或付款人不一致，未打开收银台')
                if (order.requires_manual_review || order.status === 'paid') break
                const loaded = await ui.loadDetail(context.leadId)
                if (!current()) return false
                if (!loaded || Number(ui.detail.value.id) !== context.leadId)
                    throw new Error(ui.detailError.value || '未能读取当前工单，暂不继续支付')
                let replacement = cooperationPaymentReplacementId(order)
                // The current lead pointer plus the replacement's reverse link can
                // prove the new trade even if an older response lacked its forward pointer.
                if (order.status === 'closed' && !replacement) {
                    const linked = Number(ui.detail.value.payment_order_id)
                    if (Number.isSafeInteger(linked) && linked > 0 && linked !== target) replacement = linked
                }
                if (replacement) {
                    previous = { ...order, replacement_order_id: replacement }
                    target = replacement
                    if (hop === 5) throw new Error('付款单多次调整，请查看付款单核对最新状态')
                    continue
                }
                if (order.status === 'unpaid' && (Number(ui.detail.value.payment_order_id) !== target || ui.detail.value.status !== 'unpaid'))
                    throw new Error('工单的当前付款单或成交状态已变化，未打开原收银台，请刷新核对')
                break
            }
            if (!context || !current()) return false
            paymentOrder.value = order
            if (target !== id) {
                releasePayment(context, id)
                paymentId.value = target
            }
            rememberPayment(context, target)
            if (order.status === 'paid') {
                if (order.requires_manual_review) {
                    paymentMessage.value = order.review_message || '款项已收到，但协作佣金入账待核对，请联系平台，勿重复支付'
                    await host.refreshBusinessData()
                    if (reviewNotifiedId !== target) {
                        reviewNotifiedId = target
                        uni.showModal({ title: '已收款，需平台核对', content: paymentMessage.value, showCancel: false })
                    }
                    return false
                }
                paymentMessage.value = '支付已确认'
                paymentCompleted.value = true
                releasePayment(context, target)
                paymentId.value = 0
                host.markChanged()
                await host.refreshBusinessData()
                if (host.selectedId.value) await host.reloadDetail()
                if (host.task() === 'commission') host.overlay.value = 'detail'
                uni.showToast({ title: '支付已确认，业务状态已更新', icon: 'none' })
            } else if (order.status === 'closed') {
                paymentCompleted.value = false
                paymentMessage.value = '原支付单已关闭。请到“我的付款单”核对是否有调整后的订单，不会按原金额重新下单。'
                if (host.task() === 'commission') ui.writeError.value = paymentMessage.value
            } else
                paymentMessage.value =
                    order.status === 'unpaid'
                        ? previous
                            ? '平台已调整付佣金额，请核对新付款单后继续支付。'
                            : '原单尚未确认支付，可刷新核对'
                        : '原单状态尚未核实，保留原单，请稍后核对，勿重复付款'
            return order.status === 'unpaid' && !order.requires_manual_review && order.can_pay === true
        } catch (cause) {
            if (!host.disposed() && who === ui.sessionScope() && id === paymentId.value) {
                paymentMessage.value = coopError(cause, '支付结果暂未确认，请稍后刷新，勿重复付款')
                ui.writeError.value = paymentMessage.value
            }
            return false
        } finally {
            paymentChecking.value = false
        }
    }
    /** New login identity: forget everything about the previous member's payment. */
    function reset() {
        paymentId.value = 0
        paymentMessage.value = ''
        paymentOrder.value = {}
        paymentCompleted.value = false
        paymentContextError.value = ''
        orderRequests.clear()
    }
    /** Component unmount. */
    function dispose() {
        orderRequests.clear()
    }
    return {
        paymentId,
        paymentMessage,
        paymentChecking,
        paymentOrder,
        paymentCompleted,
        paymentContextError,
        paymentResuming,
        expectedPaymentContext,
        commissionPaymentClosed,
        commissionLead,
        cashierReturnUrl,
        storageKey,
        startOrder,
        payCommission,
        resumePayment,
        verifyPayment,
        reset,
        dispose
    }
}
