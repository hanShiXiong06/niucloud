<template>
    <MemberCardSheet
        :show="show"
        :title="mode === 'cancel' ? '取消未收款订单' : mode === 'retry' ? '重新处理本笔收款' : '开卡订单详情'"
        :busy="busy"
        @update:show="emit('update:show', $event)"
    >
        <MemberCardState v-if="loading || error" :loading="loading" :error="error" action="重新加载" @action="load" />
        <template v-else-if="order">
            <view class="mc-between">
                <view class="mc-grow">
                    <view class="mc-title">{{ order.holder_name }}</view>
                    <view class="mc-sub">{{ phone(order.holder_mobile) }} · {{ order.product_name }}</view>
                </view>
                <text class="mc-badge" :class="'mc-badge--' + financeTone(order.finance_status)">
                    {{ financeLabel(order.finance_status) }}
                </text>
            </view>
            <view class="mc-summary mc-grid">
                <view>
                    <view class="mc-small">开卡金额</view>
                    <view class="mc-money">¥{{ money(order.order_amount) }}</view>
                </view>
                <view>
                    <view class="mc-small">已收金额</view>
                    <view class="mc-money">¥{{ money(order.paid_amount) }}</view>
                </view>
            </view>
            <template v-if="mode === 'detail'">
                <MemberCardNotice
                    v-if="canRetry"
                    tone="warning"
                    title="收款处理未完成"
                    text="修正账户或相关设置后，可在本订单重试。无需重新开卡。"
                />
                <MemberCardNotice
                    v-else-if="['pending', 'partial'].includes(order.finance_status)"
                    text="本单仍有待收款项，请在 ERP 应收中按订单号处理；此处不重复登记收款。"
                />
                <view class="mc-detail-row">
                    <text>收款方式</text>
                    <text>{{ order.settlement_mode === 'immediate' ? '现场收款登记' : '记入应收' }}</text>
                </view>
                <view class="mc-detail-row">
                    <text>到账账户</text>
                    <text>{{ order.capital_account_name || '暂未记录' }}</text>
                </view>
                <view v-if="Number(order.refunded_amount) > 0" class="mc-detail-row">
                    <text>已退款</text>
                    <text>¥{{ money(order.refunded_amount) }}</text>
                </view>
                <view class="mc-detail-row">
                    <text>开卡人</text>
                    <text>{{ order.issuer_name || '—' }}</text>
                </view>
                <view class="mc-detail-row">
                    <text>开卡时间</text>
                    <text>{{ dateTime(order.create_at) }}</text>
                </view>
                <view class="mc-detail-row">
                    <text>订单号</text>
                    <text selectable @click="copyText(order.order_no)">{{ order.order_no }}</text>
                </view>
                <view v-if="order.remark" class="mc-sub">备注：{{ order.remark }}</view>
                <MemberCardCollapse v-if="order.last_error" title="查看异常原因">
                    <text class="mc-error-detail" selectable>{{ order.last_error }}</text>
                </MemberCardCollapse>
                <MemberCardCollapse title="卡权益与关联记录">
                    <view v-for="item in order.card_items || []" :key="item.id" class="mc-detail-row">
                        <text>{{ item.item_name }}</text>
                        <text>
                            {{ item.usage_mode === 'unlimited' ? '不限次' : '剩余 ' + item.remaining_times + ' 次' }}
                        </text>
                    </view>
                    <view v-if="order.card?.card_no" class="mc-detail-row">
                        <text>会员卡号</text>
                        <text selectable @click="copyText(order.card.card_no)">{{ order.card.card_no }}</text>
                    </view>
                    <view v-for="link in order.finance_links || []" :key="link.id" class="mc-detail-row">
                        <text>财务单号</text>
                        <text selectable @click="copyText(link.target_no)">{{ link.target_no || '尚未生成' }}</text>
                    </view>
                </MemberCardCollapse>
                <MemberCardCollapse v-if="order.operation_logs?.length" title="操作记录">
                    <view v-for="log in order.operation_logs" :key="log.id" class="mc-select">
                        <view class="mc-grow">
                            <view>{{ actionLabel(log.action) }}</view>
                            <view class="mc-sub">
                                {{ log.operator_name || '系统' }} · {{ dateTime(log.create_at) }}
                            </view>
                        </view>
                    </view>
                </MemberCardCollapse>
                <view
                    v-if="Number(order.paid_amount) > 0 && order.business_status !== 'refunded'"
                    class="mc-sub"
                    style="margin-top: 16rpx"
                >
                    已收款订单如需退卡，请在电脑管理端办理整卡退款。
                </view>
            </template>
            <template v-else-if="mode === 'retry'">
                <MemberCardNotice
                    tone="warning"
                    text="只重试原订单的财务处理，不向客户重复扣款。现场收款请先核实实际到账账户。"
                />
                <MemberCardState
                    v-if="accountLoading || accountError"
                    :loading="accountLoading"
                    :error="accountError"
                    action="重新加载账户"
                    @action="loadAccounts"
                />
                <template v-else-if="order.settlement_mode === 'immediate'">
                    <view
                        v-for="account in accounts"
                        :key="account.id"
                        class="mc-select"
                        @click="accountId = Number(account.id)"
                    >
                        <view class="mc-grow">{{ account.name }}</view>
                        <u-icon
                            :name="accountId === Number(account.id) ? 'checkmark-circle-fill' : 'checkmark-circle'"
                            color="#536e96"
                            size="22"
                        />
                    </view>
                    <MemberCardNotice
                        v-if="!accounts.length"
                        tone="warning"
                        text="没有可用收款账户，请先到收款与耗材设置中维护。"
                    />
                </template>
            </template>
            <template v-else>
                <MemberCardNotice
                    tone="warning"
                    text="仅适用于未实际收款的订单。取消后会员卡不可使用；有有效核销时，需先冲正。不会执行退款。"
                />
                <u-textarea v-model="reason" maxlength="255" placeholder="填写取消原因（必填）" count />
            </template>
            <MemberCardNotice v-if="actionError" tone="error" :text="actionError" />
        </template>
        <template #footer>
            <view v-if="mode === 'detail'" class="mc-sheet__actions">
                <view><MemberCardButton text="关闭" :disabled="busy" @click="emit('update:show', false)" /></view>
                <view v-if="canCancel">
                    <MemberCardButton text="取消订单" :disabled="loading || !!error" @click="setMode('cancel')" />
                </view>
                <view v-if="canRetry">
                    <MemberCardButton
                        type="primary"
                        text="处理收款"
                        :disabled="loading || !!error"
                        @click="openRetry"
                    />
                </view>
            </view>
            <view v-else class="mc-sheet__actions">
                <view>
                    <MemberCardButton text="返回详情" :disabled="busy" @click="setMode('detail')" />
                </view>
                <view>
                    <MemberCardButton
                        :type="mode === 'cancel' ? 'error' : 'primary'"
                        :text="mode === 'cancel' ? '确认取消' : '确认重试'"
                        :loading="busy"
                        :disabled="loading || accountLoading || (mode === 'retry' && !!accountError)"
                        @click="submit"
                    />
                </view>
            </view>
        </template>
    </MemberCardSheet>
</template>
<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue'
import { getCardOrder, getMemberCardConfig, retryCardOrderFinance, cancelCardOrder } from '../api'
import MemberCardSheet from './MemberCardSheet.vue'
import MemberCardButton from './MemberCardButton.vue'
import MemberCardState from './MemberCardState.vue'
import MemberCardNotice from './MemberCardNotice.vue'
import MemberCardCollapse from './MemberCardCollapse.vue'
import {
    money,
    accountOptions,
    phone,
    dateTime,
    copyText,
    financeLabel,
    financeTone,
    errorText,
    markMemberCardChanged
} from '../utils/presentation'
const props = defineProps<{ show: boolean; orderId: number }>()
const emit = defineEmits(['update:show', 'changed'])
const order = ref<any>(null),
    mode = ref('detail'),
    loading = ref(false),
    busy = ref(false)
const error = ref(''),
    actionError = ref(''),
    reason = ref('')
const accountLoading = ref(false),
    accountError = ref(''),
    accounts = ref<any[]>([]),
    accountId = ref(0)
let sequence = 0
const closed = computed(
    () =>
        !order.value || ['cancelled', 'refunded', 'refund_pending', 'processing'].includes(order.value.business_status)
)
const canRetry = computed(
    () => !closed.value && (order.value.finance_status === 'failed' || order.value.business_status === 'failed')
)
const canCancel = computed(
    () =>
        !closed.value &&
        Number(order.value.paid_amount) === 0 &&
        !order.value.card_items?.some((item: any) => Number(item.used_times) > 0)
)
const actionLabel = (action: string) =>
    (
        ({
            create: '创建订单',
            activate: '激活会员卡',
            cancel: '取消订单',
            retry_finance: '重试财务',
            finance_failed: '财务处理失败'
        }) as Record<string, string>
    )[action] || '订单更新'
const load = async () => {
    const ticket = ++sequence
    loading.value = true
    error.value = ''
    try {
        const result: any = await getCardOrder(props.orderId)
        if (ticket !== sequence) return
        if (!result?.data?.id) throw new Error('missing order')
        order.value = result.data
    } catch (e) {
        if (ticket === sequence) error.value = errorText(e, '订单详情加载失败，请重试')
    } finally {
        if (ticket === sequence) loading.value = false
    }
}
const loadAccounts = async () => {
    accountLoading.value = true
    accountError.value = ''
    try {
        const result: any = await getMemberCardConfig()
        accounts.value = accountOptions(result?.data)
        accountId.value = accounts.value.some((a) => Number(a.id) === Number(order.value?.capital_account_id))
            ? Number(order.value.capital_account_id)
            : 0
    } catch (e) {
        accountError.value = errorText(e, '账户加载失败')
    } finally {
        accountLoading.value = false
    }
}
const setMode = (value: string) => {
    if (busy.value) return
    mode.value = value
    actionError.value = ''
}
const openRetry = () => {
    mode.value = 'retry'
    actionError.value = ''
    if (order.value?.settlement_mode === 'immediate') void loadAccounts()
}
const submit = async () => {
    if (busy.value || !order.value || loading.value) return
    const cancel = mode.value === 'cancel'
    if (cancel && !reason.value.trim()) {
        actionError.value = '请填写取消原因'
        return
    }
    if (
        !cancel &&
        order.value.settlement_mode === 'immediate' &&
        (!accountId.value || accountLoading.value || accountError.value)
    ) {
        actionError.value = '请选择实际到账账户'
        return
    }
    busy.value = true
    actionError.value = ''
    try {
        const result: any = cancel
            ? await cancelCardOrder(order.value.id, reason.value.trim())
            : await retryCardOrderFinance(order.value.id, { capital_account_id: accountId.value })
        markMemberCardChanged()
        emit('changed')
        mode.value = 'detail'
        await load()
        if (!cancel && result?.data?.success === false)
            actionError.value = '本次仍未完成，请展开异常原因处理，不要重复开卡。'
        else uni.showToast({ title: cancel ? '订单已取消' : '本单处理完成', icon: 'success' })
    } catch (e) {
        actionError.value = errorText(e, '结果尚未确认，请返回详情刷新核对')
    } finally {
        busy.value = false
    }
}
watch(
    () => [props.show, props.orderId],
    () => {
        if (props.show && props.orderId) {
            mode.value = 'detail'
            order.value = null
            reason.value = ''
            actionError.value = ''
            void load()
        } else sequence++
    },
    { immediate: true }
)
onBeforeUnmount(() => {
    sequence++
})
</script>
