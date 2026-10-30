<template>
    <view class="order-card">
        <view class="card-head" @click="emit('detail', order)">
            <view class="status-wrap"><text class="status-text">{{ orderStatusLabel(order) }}</text><text class="order-tag">{{ paymentLabel(order) }}</text></view>
            <text class="order-time">{{ formatOrderTime(order.create_time).slice(5) }}</text>
        </view>
        <view class="customer-row">
            <view class="customer-main"><text class="customer-name">{{ order.member?.nickname || order.taker_name || '商城客户' }}</text><text class="customer-mobile">{{ mobile || '未留联系电话' }}</text></view>
            <view v-if="mobile" class="call-button" @click.stop="callCustomer"><u-icon name="phone" color="var(--primary-color)" size="20" /></view>
        </view>
        <view class="goods-preview" @click="emit('detail', order)">
            <image v-if="cover" class="goods-cover" :src="img(cover)" mode="aspectFill" /><view v-else class="goods-cover goods-cover--empty"><u-icon name="photo" color="#b4bdc9" size="28" /></view>
            <view class="goods-main"><text class="goods-name">{{ firstGoods.goods_name || '订单商品' }}</text><text v-if="firstGoods.sku_name" class="goods-meta">{{ firstGoods.sku_name }}</text><text v-if="goodsIdentity(firstGoods)" class="goods-imei">IMEI {{ goodsIdentity(firstGoods) }}</text><text v-if="goods.length > 1" class="goods-meta">共 {{ goods.reduce((sum, item) => sum + Number(item.num || 1), 0) }} 件设备，查看全部 ›</text></view>
        </view>
        <view class="amount-line"><text>{{ order.delivery_type_name || '配送方式待核对' }}</text><text>订单金额 <text class="amount">¥{{ money(order.order_money) }}</text></text></view>
        <view v-if="order.return_context_error" class="order-warning">{{ order.return_context_error }}</view>
        <view class="card-foot"><view class="detail-button" @click="emit('detail', order)">查看详情</view><view v-if="primaryAction" class="primary-button" @click="emit('operate', order)">{{ primaryAction.label }}</view></view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'
import { formatOrderTime, getOrderActions, goodsIdentity, money, orderGoods, orderStatusLabel, paymentLabel } from '@/addon/phone_shop/utils/orderWorkflow'
const props = defineProps<{ order: Record<string, any> }>()
const emit = defineEmits(['detail', 'operate'])
const goods = computed(() => orderGoods(props.order))
const firstGoods = computed(() => goods.value[0] || {})
const cover = computed(() => firstGoods.value.sku_image || firstGoods.value.goods_image_thumb_small || firstGoods.value.goods_image || '')
const mobile = computed(() => String(props.order.taker_mobile || props.order.member?.mobile || ''))
const primaryAction = computed(() => getOrderActions(props.order).find(action => ['confirm_paid', 'delivery', 'finish', 'received'].includes(action.key)))
function callCustomer() { uni.makePhoneCall({ phoneNumber: mobile.value }) }
</script>
<style scoped lang="scss">
.order-card { margin-bottom: 20rpx; background: #fff; border-radius: 14rpx; overflow: hidden; }.card-head { display: flex; justify-content: space-between; align-items: center; gap: 14rpx; padding: 22rpx 24rpx 16rpx; }.status-wrap { min-width: 0; display: flex; align-items: center; flex-wrap: wrap; gap: 12rpx; }.status-text { font-size: 27rpx; font-weight: 600; color: #263244; }.order-tag { font-size: 21rpx; padding: 4rpx 9rpx; background: #f1f5fa; color: #64748b; border-radius: 6rpx; }.order-time { flex-shrink: 0; font-size: 21rpx; color: #8590a2; }.customer-row { padding: 0 24rpx 18rpx; display: flex; align-items: center; }.customer-main { flex: 1; min-width: 0; }.customer-name { font-size: 27rpx; color: #263244; }.customer-mobile { font-size: 23rpx; color: #8590a2; margin-left: 16rpx; }.call-button { padding: 12rpx 0 12rpx 20rpx; }.goods-preview { display: flex; gap: 18rpx; padding: 0 24rpx 20rpx; }.goods-cover { width: 112rpx; height: 112rpx; flex-shrink: 0; border-radius: 8rpx; background: #f2f4f7; }.goods-cover--empty { display: flex; justify-content: center; align-items: center; }.goods-main { flex: 1; min-width: 0; }.goods-main text { display: block; }.goods-name { color: #263244; font-size: 28rpx; line-height: 1.45; }.goods-meta { color: #8590a2; font-size: 23rpx; margin-top: 6rpx; }.goods-imei { color: #64748b; font-size: 23rpx; margin-top: 8rpx; font-family: monospace; overflow-wrap: anywhere; }.amount-line { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12rpx; padding: 0 24rpx 20rpx; font-size: 24rpx; color: #8590a2; }.amount { font-size: 31rpx; font-weight: 600; color: #263244; margin-left: 6rpx; }.card-foot { display: flex; justify-content: flex-end; gap: 16rpx; padding: 18rpx 24rpx; border-top: 1rpx solid #edf0f4; }.detail-button, .primary-button { min-height: 64rpx; padding: 0 24rpx; display: flex; align-items: center; justify-content: center; border-radius: 9rpx; border: 1rpx solid #dce2ec; font-size: 25rpx; color: #516174; box-sizing: border-box; }.primary-button { color: #fff; background: var(--primary-color); border-color: var(--primary-color); }.order-warning { padding: 12rpx 24rpx; color: #a86a19; font-size: 23rpx; line-height: 1.5; }
</style>
