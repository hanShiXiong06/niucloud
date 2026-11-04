<template><CoopTaskSheet :show="show" title="确认协作佣金" :busy="busy" @close="$emit('close')"><view class="coop-form-section"><view class="coop-section-title">{{ lead.customer_name }}的交付</view><view class="coop-muted">客户货款由你与客户直接结算，此处仅支付协作佣金。</view><view class="coop-amount-input"><text>¥</text><input v-model="amount" type="digit" :disabled="busy || locked" maxlength="15" placeholder="50.00" /></view><view v-if="!locked" class="coop-amount-presets"><text v-for="value in presets" :key="value" :class="{ active: Number(amount) === value }" @tap="amount = String(value)">¥{{ value }}</text></view><view class="coop-muted">成交最低付佣 {{ coopMoney(minimum) }}，金额自行填写，不设业务上限。</view></view><view class="coop-form-section"><view class="coop-detail-shop"><text>发布方预计获得</text><text>{{ net }}</text></view><view class="coop-detail-shop"><text>平台维护费</text><text>{{ fee }}</text></view><view class="coop-muted">{{ config.fee_confirmed ? `按本站规则：佣金的 ${config.fee_percent}% ，最低 ${coopMoney(config.fee_minimum)}` : '本站尚未确认收费规则，暂不能创建支付单，请联系平台。' }}</view><view v-if="locked" class="coop-muted">已有待支付单，金额以原单为准，不重复建单。</view></view><view v-if="localError || error" class="coop-inline-error coop-form-error">{{ localError || error }}</view><template #footer><u-button type="primary" shape="circle" :text="locked ? '继续支付原单' : '确认金额，去支付'" :loading="busy" :disabled="busy || (!locked && !config.fee_confirmed)" @click="submit" /></template></CoopTaskSheet></template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { coopMoney, type CoopRow } from '../../api/cooperation'
import CoopTaskSheet from './CoopTaskSheet.vue'
const props = defineProps<{ show: boolean; lead: CoopRow; config: CoopRow; busy?: boolean; error?: string }>()
const emit = defineEmits<{ (event: 'close'): void; (event: 'submit', amount: string): void }>()
const amount = ref('50'), localError = ref(''), minimum = computed(() => Math.max(50, Number(props.config.commission_minimum || 50))), locked = computed(() => Number(props.lead.payment_order_id) > 0)
const presets = computed(() => [...new Set([minimum.value, 100, 200].filter(value => value >= minimum.value))])
const feeCents = computed(() => { const cents = Math.round(Number(amount.value) * 100); if (!Number.isFinite(cents) || !props.config.fee_confirmed) return null; return Math.min(cents, Math.max(Math.round(cents * Number(props.config.fee_percent || 0) / 100), Math.round(Number(props.config.fee_minimum || 0) * 100))) })
const fee = computed(() => locked.value ? coopMoney(props.lead.fee_amount) : feeCents.value == null ? '待规则确认' : coopMoney(feeCents.value / 100)), net = computed(() => locked.value ? coopMoney(props.lead.net_amount) : feeCents.value == null ? '待规则确认' : coopMoney((Math.round(Number(amount.value) * 100) - feeCents.value) / 100))
watch(() => props.show, show => { if (show) { amount.value = String(Number(props.lead.commission_amount) >= minimum.value ? props.lead.commission_amount : minimum.value); localError.value = '' } })
function submit() { localError.value = ''; if (!/^\d{1,12}(\.\d{1,2})?$/.test(amount.value) || Number(amount.value) < minimum.value) { localError.value = `请输入至少 ${coopMoney(minimum.value)} 的佣金，最多两位小数`; return }; emit('submit', Number(amount.value).toFixed(2)) }
</script>
<style scoped lang="scss">
@import './cooperation-shared';
@include hsx-coop-form-section;
@include hsx-coop-section-title;
@include hsx-coop-muted;
@include hsx-coop-detail-shop;
@include hsx-coop-form-error;
.coop-amount-input { display: flex; align-items: center; gap: 8rpx; border-bottom: 1rpx solid #dce2e9; height: 156rpx; margin: 20rpx 0 24rpx; }
.coop-amount-input>text { font-size: 47rpx; line-height: 1.4; padding-top: 18rpx; }
.coop-amount-input>input { flex: 1; min-width: 0; font-size: 82rpx; font-weight: 650; height: 130rpx; line-height: 130rpx; }
.coop-amount-presets { display: flex; gap: 18rpx; margin-bottom: 23rpx; }
.coop-amount-presets>text { flex: 1; text-align: center; border: 1rpx solid #dce3eb; color: #778493; border-radius: 8rpx; font-size: 25rpx; line-height: 70rpx; }
.coop-amount-presets>text.active { color: var(--primary-color, #d93833); border-color: var(--primary-color, #d93833); }
</style>
