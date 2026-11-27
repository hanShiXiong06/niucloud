<template>
  <view class="order-overview">
    <view class="order-overview__amount">
      <view><text class="amount-label">设备报价合计</text><text class="amount-note">以实际确认及结算为准</text></view>
      <text class="amount-value">{{ Number(totalPrice) > 0 ? '¥' + totalPrice : '待定价' }}</text>
    </view>
    <view class="order-overview__toggle" role="button" @tap="expanded = !expanded">
      <text>订单资料</text>
      <view class="order-overview__summary"><text>{{ deviceCount }} 台明细 · {{ expanded ? '收起' : '展开' }}</text><up-icon :name="expanded ? 'arrow-up' : 'arrow-down'" size="13" color="var(--recycle-text-sub)" /></view>
    </view>
    <view v-if="expanded" class="order-metadata">
      <view class="metadata-row"><text>订单号</text><view class="metadata-value"><text selectable>{{ orderNo }}</text><OrderUiButton variant="text" @click="copyOrderNo(orderNo)">复制</OrderUiButton></view></view>
      <view v-if="createTime" class="metadata-row"><text>下单时间</text><text>{{ createTime }}</text></view>
      <view v-if="deliveryName" class="metadata-row"><text>交付方式</text><text>{{ deliveryName }}</text></view>
      <view v-if="remark" class="metadata-row"><text>备注</text><text>{{ remark }}</text></view>
    </view>
    <view v-if="cancelReason" class="order-cancel-reason"><text>取消原因</text><text>{{ cancelReason }}</text></view>
    <view v-if="expressNo" class="express-entry" @tap="showExpressModal = true">
      <DeliveryIcon type="1" /><view class="express-entry__copy"><text>查看物流</text><text class="express-entry__number">{{ expressNo }}</text></view><up-icon name="arrow-right" size="15" color="var(--recycle-text-sub)" />
    </view>
    <ExpressTrackingModal v-model:visible="showExpressModal" :expressNo="expressNo || ''" :mobile="mobile" />
  </view>
</template>
<script setup lang="ts">
import { ref } from 'vue'
import { copyOrderNo } from '../../../utils/clipboard'
import ExpressTrackingModal from './ExpressTrackingModal.vue'
import OrderUiButton from './OrderUiButton.vue'
import DeliveryIcon from './DeliveryIcon.vue'
defineProps<{ orderNo: string; deviceCount: number; totalPrice: string; expressNo?: string; mobile?: string; createTime?: string; deliveryName?: string; remark?: string; cancelReason?: string }>()
const expanded = ref(false)
const showExpressModal = ref(false)
</script>
<style scoped lang="scss">
.order-overview { padding: 0 var(--recycle-order-gutter, 24px); margin-bottom: var(--recycle-order-section-gap, 12px); background: var(--recycle-bg-card); }
.order-overview__amount { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16rpx; padding: 24rpx 0; border-top: 1rpx solid var(--recycle-line); }
.amount-label { display: block; font-size: 26rpx; line-height: 38rpx; }
.amount-note { display: block; font-size: 22rpx; line-height: 34rpx; color: var(--recycle-text-sub); }
.amount-value { font-size: 38rpx; font-weight: 600; line-height: 48rpx; color: var(--recycle-price); overflow-wrap: anywhere; }
.order-overview__toggle { display: flex; align-items: center; justify-content: space-between; gap: 16rpx; min-height: 88rpx; border-top: 1rpx solid var(--recycle-line); font-size: 26rpx; }
.order-overview__summary { display: flex; align-items: center; gap: 12rpx; font-size: 24rpx; color: var(--recycle-text-sub); }
.order-metadata { padding-bottom: 20rpx; }
.order-cancel-reason { display: flex; align-items: flex-start; gap: 20rpx; padding: 16rpx 0; font-size: 25rpx; line-height: 38rpx; color: var(--recycle-notice-text); }
.order-cancel-reason > text:first-child { flex-shrink: 0; }
.order-cancel-reason > text:last-child { min-width: 0; overflow-wrap: anywhere; }
.metadata-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; font-size: 24rpx; line-height: 38rpx; padding: 8rpx 0; }
.metadata-row > text:first-child { flex-shrink: 0; color: var(--recycle-text-sub); }
.metadata-row > text:last-child { text-align: right; overflow-wrap: anywhere; }
.metadata-value { display: flex; align-items: center; min-width: 0; gap: 8rpx; }
.metadata-value > text { min-width: 0; overflow-wrap: anywhere; }
.express-entry { display: flex; align-items: center; gap: 14rpx; padding: 22rpx 0; border-top: 1rpx solid var(--recycle-line); color: var(--recycle-brand); }
.express-entry__copy { min-width: 0; flex: 1; font-size: 26rpx; }
.express-entry__number { display: block; color: var(--recycle-text-sub); font-size: 24rpx; line-height: 36rpx; overflow-wrap: anywhere; }
</style>
