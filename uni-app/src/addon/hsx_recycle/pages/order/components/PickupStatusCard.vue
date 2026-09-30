<template>
  <view class="pickup-card" :class="[`pickup-card--${pickup.state}`, { 'pickup-card--compact': compact, 'pickup-card--conflict': pickup.conflict }]">
    <view class="pickup-heading">
      <view class="pickup-heading__title"><up-icon name="car" size="17" color="var(--recycle-brand)" /><text>{{ pickup.title }}</text></view>
      <text v-if="compact" class="pickup-link" @tap.stop="$emit('view-detail')">查看详情</text>
    </view>
    <view class="pickup-row"><text class="pickup-label">承运商</text><text>{{ pickup.carrier_name || '待确认' }}</text></view>
    <view v-if="pickup.state !== 'manual'" class="pickup-row"><text class="pickup-label">申请取件时段</text><text>{{ pickup.pickup_time || '待确认' }}</text></view>
    <view v-if="pickup.state !== 'manual'" class="pickup-row">
      <text class="pickup-label">取件员</text><text>{{ pickup.courier_name || '待分配' }}</text>
    </view>
    <view v-if="pickup.state !== 'manual'" class="pickup-row">
      <text class="pickup-label">取件电话</text>
      <text :class="{ 'pickup-link': pickup.courier_phone }" @tap.stop="callCourier">{{ pickup.courier_phone || '待分配' }}</text>
    </view>
    <view v-if="pickup.tracking_no && !compact" class="pickup-row">
      <text class="pickup-label">运单号</text><text class="pickup-link" @tap.stop="copyText(pickup.tracking_no)">{{ pickup.tracking_no }} · 复制</text>
    </view>
    <text class="pickup-message">{{ pickup.message }}</text>
    <text v-if="pickup.state === 'failed' && pickup.can_manual && pickup.failure_message && pickup.failure_message !== pickup.message" class="pickup-failure">{{ pickup.failure_message }}</text>
    <text v-if="isUncertain" class="pickup-safety">请勿重复叫件或自行另叫快递，请先联系门店核实。</text>

    <view v-if="!compact && receiverText" class="pickup-receiver">
      <view class="pickup-row"><text class="pickup-label">门店收件信息</text><text class="pickup-link" @tap="copyText(receiverText)">复制</text></view>
      <text>{{ receiverText }}</text>
    </view>
    <view v-if="!compact" class="pickup-actions">
      <up-button v-if="pickup.can_refresh" size="small" plain :loading="refreshing" :disabled="refreshing || saving" @click="refreshPickup">刷新取件状态</up-button>
      <up-button v-if="pickup.can_manual" type="primary" size="small" :disabled="saving || refreshing" @click="showManual = !showManual">{{ showManual ? '收起补单' : '自行寄件 · 补运单' }}</up-button>
      <up-button v-if="needsContact" size="small" plain @click="$emit('contact')">联系门店核实</up-button>
      <!-- #ifdef MP-WEIXIN -->
      <up-button v-if="canSubscribe" size="small" plain @click="subscribePickup">订阅取件通知</up-button>
      <!-- #endif -->
    </view>

    <view v-if="!compact && showManual && pickup.can_manual" class="pickup-manual">
      <text class="pickup-manual__title">补充当前订单的寄件运单</text>
      <text class="pickup-message">自行寄出后填写，保存后仍是本回收订单，不会再次预约快递。</text>
      <text v-if="!receiverText" class="pickup-safety">暂未取得门店收件地址，请先联系门店确认收件信息后再寄出。</text>
      <up-input v-model="manualCompany" placeholder="已寄出快递的公司名称" maxlength="40" border="surround" />
      <up-input v-model="manualTracking" placeholder="输入或扫描已寄出的运单号" maxlength="50" border="surround">
        <template #suffix><up-icon name="scan" size="22" @click="scanTracking" /></template>
      </up-input>
      <up-button type="primary" :loading="saving" :disabled="saving || refreshing" @click="saveManual">保存运单到本订单</up-button>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { useSubscribeMessage } from '@/hooks/useSubscribeMessage'
import { refreshOrderPickup, submitManualPickup } from '../../../api/order'
import type { PickupInfo } from '../../../types/order'
import { normalizePickup, pickupReceiverText, pickupRefreshFeedback } from '../../../utils/pickup'

const props = withDefaults(defineProps<{ orderId: number; info?: PickupInfo; compact?: boolean }>(), { compact: false })
const emit = defineEmits<{ updated: []; 'view-detail': []; contact: [] }>()
const pickup = computed(() => normalizePickup(props.info))
const receiverText = computed(() => pickupReceiverText(pickup.value.receiver))
const isUncertain = computed(() => ['unknown', 'submitting', 'accepted'].includes(pickup.value.state))
const needsContact = computed(() => pickup.value.conflict || isUncertain.value || ['exception', 'failed', 'cancelled'].includes(pickup.value.state))
const canSubscribe = computed(() => !pickup.value.conflict && !['not_requested', 'manual', 'delivered', 'cancelled', 'failed'].includes(pickup.value.state))
const refreshing = ref(false)
const saving = ref(false)
const showManual = ref(false)
const manualCompany = ref('')
const manualTracking = ref('')
let nextRefreshAt = 0

watch(() => [props.orderId, pickup.value.can_manual], () => {
  showManual.value = false
  manualCompany.value = ''
  manualTracking.value = ''
})
watch(() => props.orderId, () => { nextRefreshAt = 0 })

const copyText = (value: string) => {
  if (value) uni.setClipboardData({ data: value })
}
const callCourier = () => {
  if (pickup.value.courier_phone) uni.makePhoneCall({ phoneNumber: pickup.value.courier_phone })
}
const refreshPickup = async () => {
  if (refreshing.value || saving.value || !pickup.value.can_refresh) return
  if (Date.now() < nextRefreshAt) {
    uni.showToast({ title: `请${Math.ceil((nextRefreshAt - Date.now()) / 1000)}秒后再刷新`, icon: 'none' })
    return
  }
  refreshing.value = true
  nextRefreshAt = Date.now() + 10000
  try {
    const result: any = await refreshOrderPickup(props.orderId)
    if (result.code !== 1) {
      uni.showToast({ title: result.msg || '暂未取得新的取件状态，请稍后核实', icon: 'none' })
      return
    }
    const feedback = pickupRefreshFeedback(result.data)
    nextRefreshAt = Date.now() + feedback.retryAfter * 1000
    uni.showToast({ title: feedback.message, icon: 'none', duration: 3000 })
    emit('updated')
  } catch (error: any) {
    uni.showToast({ title: [0, 400].includes(Number(error?.code)) ? (error.msg || '暂不能刷新，请稍后再试') : '暂未取得新的状态，请联系门店核实', icon: 'none' })
  } finally {
    refreshing.value = false
  }
}
const scanTracking = () => {
  uni.scanCode({ onlyFromCamera: true, success: (result) => { manualTracking.value = result.result.trim() } })
}
const saveManual = async () => {
  if (saving.value || refreshing.value || !pickup.value.can_manual) return
  const express_company = manualCompany.value.trim()
  const express_no = manualTracking.value.trim()
  if (!express_company || !express_no) {
    uni.showToast({ title: '请填写快递公司和运单号', icon: 'none' })
    return
  }
  if (!/^[a-zA-Z0-9-]{6,50}$/.test(express_no)) {
    uni.showToast({ title: '运单号需为6至50位字母、数字或短横线', icon: 'none' })
    return
  }
  saving.value = true
  try {
    const result: any = await submitManualPickup(props.orderId, { express_company, express_no })
    if (result.code !== 1) {
      uni.showToast({ title: result.msg || '保存未完成，请刷新订单核实', icon: 'none' })
      emit('updated')
      return
    }
    showManual.value = false
    uni.showToast({ title: '运单已补充', icon: 'success' })
    emit('updated')
  } catch (error: any) {
    uni.showToast({ title: [0, 400].includes(Number(error?.code)) ? (error.msg || '暂不能保存，请刷新原订单核实') : '保存结果待核实，请刷新原订单查看', icon: 'none' })
    emit('updated')
  } finally {
    saving.value = false
  }
}
const subscribePickup = async () => {
  try {
    await useSubscribeMessage().request('hsx_recycle_pickup_update')
  } catch (_) {
    uni.showToast({ title: '暂未完成订阅，仍可在订单中查看进度', icon: 'none' })
  }
}
</script>

<style scoped lang="scss">
.pickup-card { margin: 20rpx 0; padding: 24rpx; border: 1rpx solid var(--recycle-line); border-radius: 18rpx; background: var(--recycle-bg-card); color: var(--recycle-text-main); font-size: 25rpx; line-height: 1.6; }
.pickup-card--compact { margin: 18rpx 0 0; padding: 20rpx; background: var(--recycle-bg-soft); }
.pickup-card--unknown, .pickup-card--failed, .pickup-card--conflict { border-color: var(--recycle-notice-text); }
.pickup-heading, .pickup-heading__title, .pickup-row { display: flex; align-items: center; gap: 12rpx; }
.pickup-heading { justify-content: space-between; margin-bottom: 12rpx; }
.pickup-heading__title { font-weight: 700; font-size: 28rpx; }
.pickup-row { align-items: flex-start; justify-content: space-between; margin-top: 8rpx; }
.pickup-row > text:last-child { flex: 1; text-align: right; word-break: break-all; }
.pickup-label { flex-shrink: 0; color: var(--recycle-text-sub); }
.pickup-link { color: var(--recycle-brand); }
.pickup-message, .pickup-failure, .pickup-safety { display: block; margin-top: 12rpx; color: var(--recycle-text-sub); }
.pickup-failure, .pickup-safety { color: var(--recycle-notice-text); }
.pickup-receiver { margin-top: 18rpx; padding-top: 12rpx; border-top: 1rpx solid var(--recycle-line); word-break: break-all; }
.pickup-actions { display: flex; flex-wrap: wrap; gap: 12rpx; margin-top: 20rpx; }
.pickup-actions :deep(.u-button) { width: auto; margin: 0; flex: 1; min-width: 210rpx; }
.pickup-manual { display: flex; flex-direction: column; gap: 16rpx; margin-top: 20rpx; padding-top: 20rpx; border-top: 1rpx solid var(--recycle-line); }
.pickup-manual__title { font-weight: 700; }
</style>
