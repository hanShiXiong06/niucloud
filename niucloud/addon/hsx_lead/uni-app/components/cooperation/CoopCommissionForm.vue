<template>
    <CoopTaskSheet :page="page" :show="show" title="确认协作佣金" :busy="busy" @close="$emit('close')">
        <view class="coop-form-section">
            <view class="coop-section-title">{{ lead.customer_name }}的交付</view>
            <CoopPaymentSummary v-if="locked" :amount="lead.commission_amount" order-created embedded />
            <view v-else class="coop-muted">客户货款另行结算，此处仅支付协作佣金。</view>
            <view v-if="!locked" class="coop-amount-input"
                ><text>¥</text
                ><input
                    v-model="amount"
                    type="digit"
                    :disabled="busy || blocked"
                    maxlength="15"
                    aria-label="协作佣金金额"
                    :placeholder="snapshot.known ? minimum.toFixed(2) : '待规则核对'"
            /></view>
            <view v-if="!locked" class="coop-amount-presets"
                ><text
                    v-for="value in presets"
                    :key="value"
                    role="button"
                    :aria-disabled="busy || blocked"
                    :class="{ active: Number(amount) === value }"
                    @tap="!busy && !blocked && (amount = String(value))"
                    >¥{{ value }}</text
                ></view
            >
            <view
                class="coop-muted"
                >{{ snapshot.known ? '成交最低付佣 ' + coopMoney(minimum) + '，金额自行填写，不设业务上限。' : '本单收费规则待核对，暂不能创建新支付单。' }}</view
            >
        </view>
        <view class="coop-form-section">
            <view class="coop-section-title">本笔分配</view>
            <view class="coop-detail-shop"
                ><text>发布方预计获得</text><text>{{ net }}</text></view
            >
            <view class="coop-detail-shop"
                ><text>平台维护费</text><text>{{ fee }}</text></view
            >
            <view v-if="locked" class="coop-payment-locked">已有待支付单，按原单金额继续支付。</view>
            <u-collapse :border="false"
                ><u-collapse-item title="查看本单收费规则" name="fee-rule"
                    ><view
                        class="coop-muted"
                        >{{ snapshot.known ? `按本单发布时规则：佣金的 ${snapshot.percent}% ，最低 ${coopMoney(snapshot.feeMinimum)}` : '本单收费快照不完整，请联系平台核对。' }}</view
                    ></u-collapse-item
                ></u-collapse
            >
        </view>
        <CoopPaymentHelp
            ref="paymentHelpRef"
            :active="show"
            :order="order || {}"
            :payment-issue="paymentHelpRequested"
            :inset="16"
            :spacing="12"
            @ready="handlePaymentHelpReady"
        />
        <view
            v-if="localError || error"
            class="coop-inline-error coop-form-error"
            role="alert"
            >{{ localError || error }}</view
        >
        <template #footer
            ><u-button
                :custom-style="cooperationButtonStyle"
                type="primary"
                shape="circle"
                :text="blocked ? '付款信息需核对' : locked ? '继续支付原单' : '确认金额，去支付'"
                :loading="busy"
                :disabled="busy || blocked || (!locked && !snapshot.known)"
                @click="submit"
        /></template>
    </CoopTaskSheet>
</template>
<script lang="ts">
// Match Vue's transparent layout in native Mini Programs: an extra custom
// component host otherwise breaks the page's definite-height flex chain.
export default { options: { virtualHost: true } }
</script>
<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { coopMoney, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
import CoopPaymentSummary from './CoopPaymentSummary.vue'
import CoopPaymentHelp from './CoopPaymentHelp.vue'
import { leadFeeSnapshot } from '../../utils/cooperation-business'
import { cooperationButtonStyle } from '../../utils/mobile-ui'
const props = defineProps<{ show: boolean; page?: boolean; lead: CoopRow; order?: CoopRow; config: CoopRow; busy?: boolean; blocked?: boolean; error?: string }>()
const emit = defineEmits<{ (event: 'close'): void; (event: 'submit', amount: string): void; (event: 'payment-help-ready', value: boolean): void }>()
const paymentHelpRef = ref<any>(null), paymentHelpReady = ref(false), paymentHelpRequested = ref(false)
function handlePaymentHelpReady(ready: boolean) { paymentHelpReady.value = props.show && ready === true; emit('payment-help-ready', paymentHelpReady.value) }
function openPaymentHelp(): boolean {
    if (!props.show || props.busy || !paymentHelpReady.value || paymentHelpRef.value?.open?.() !== true) return false
    paymentHelpRequested.value = true
    return true
}
watch(() => props.show, show => { if (!show) { paymentHelpRequested.value = false; handlePaymentHelpReady(false) } })
onBeforeUnmount(() => handlePaymentHelpReady(false))
defineExpose({ openPaymentHelp })
const amount = ref(''), localError = ref(''), snapshot = computed(() => leadFeeSnapshot(props.lead)), minimum = computed(() => snapshot.value.minimum), locked = computed(() => Number(props.lead.payment_order_id) > 0)
const presets = computed(() => [...new Set([minimum.value, minimum.value * 2, minimum.value * 3].filter(value => value > 0 && value >= minimum.value))])
const feeCents = computed(() => { const cents = Math.round(Number(amount.value) * 100); if (!Number.isFinite(cents) || !snapshot.value.known) return null; return Math.min(cents, Math.max(Math.round(cents * snapshot.value.percent / 100), Math.round(snapshot.value.feeMinimum * 100))) })
const fee = computed(() => locked.value ? coopMoney(props.lead.fee_amount) : feeCents.value == null ? '待规则确认' : coopMoney(feeCents.value / 100)), net = computed(() => locked.value ? coopMoney(props.lead.net_amount) : feeCents.value == null ? '待规则确认' : coopMoney((Math.round(Number(amount.value) * 100) - feeCents.value) / 100))
watch(() => [props.show, props.lead.payment_order_id, props.lead.commission_amount] as const, ([show]) => { if (show) { amount.value = locked.value ? String(props.lead.commission_amount || '') : snapshot.value.known && minimum.value > 0 ? String(minimum.value) : ''; localError.value = '' } }, { immediate: true })
function submit() { if (props.busy || props.blocked) return; localError.value = ''; if (!locked.value && !snapshot.value.known) { localError.value = '本单收费快照尚未核实，请返回详情刷新'; return }; if (!/^\d{1,12}(\.\d{1,2})?$/.test(amount.value) || Number(amount.value) <= 0 || Number(amount.value) < minimum.value) { localError.value = minimum.value > 0 ? `请输入至少 ${coopMoney(minimum.value)} 的佣金，最多两位小数` : '请输入大于零的佣金，最多两位小数'; return }; emit('submit', Number(amount.value).toFixed(2)) }
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-detail-shop;
@include hsx-coop-form-error;
.coop-amount-input { display: flex; align-items: center; gap: 8px; border-bottom: 1px solid #dce2e9; min-height: 80px; margin: 12px 0 16px; }
.coop-amount-input>text { font-size: 24px; line-height: 1.4; padding-top: 8px; }
.coop-amount-input>input { flex: 1; min-width: 0; font-size: 40px; font-weight: 650; height: 68px; line-height: 68px; }
.coop-amount-presets { display: flex; gap: 8px; margin-bottom: 12px; flex-wrap: wrap; }
.coop-amount-presets>text { flex: 1; min-width: 72px; display: flex; align-items: center; justify-content: center; min-height: 44px; padding: 4px 8px; box-sizing: border-box; border: 1px solid #dce3eb; color: #667a94; border-radius: 8px; font-size: 14px; line-height: 22px; }
.coop-amount-presets>text.active { color: var(--primary-color, #d93833); border-color: var(--primary-color, #d93833); }
.coop-payment-locked { padding: 12px; margin: 8px 0; background: #f2f6fc; border-radius: 8px; font-size: 12px; color: #587393; line-height: 1.7; }
</style>
