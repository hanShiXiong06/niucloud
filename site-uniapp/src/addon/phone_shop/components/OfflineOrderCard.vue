<template>
    <view class="order-card">
        <view class="card-head">
            <view class="status-wrap">
                <view class="status-dot" :class="`status-dot--${ statusTone }`" />
                <text class="status-text">{{ statusLabel }}</text>
                <text v-if="order.delivery_type_name" class="delivery-tag">{{ order.delivery_type_name }}</text>
            </view>
            <text class="order-time">{{ formatTime(order.create_time) }}</text>
        </view>

        <view class="customer-row">
            <view class="customer-avatar"><u-icon name="account" color="#2563eb" size="19" /></view>
            <view class="customer-info">
                <text class="customer-name">{{ customerName }}</text>
                <text class="customer-mobile">{{ mobile || '未留联系电话' }}</text>
            </view>
            <view v-if="mobile" class="call-button" @click.stop="callCustomer"><u-icon name="phone" color="#16a34a" size="19" /></view>
        </view>

        <view class="goods-preview" @click="emit('toggle', order)">
            <image v-if="cover(firstGoods)" class="goods-cover" :src="imageUrl(cover(firstGoods))" mode="aspectFill" />
            <view v-else class="goods-cover goods-cover--empty"><u-icon name="photo" color="#cbd5e1" size="26" /></view>
            <view class="goods-info">
                <text class="goods-name">{{ firstGoods?.goods_name || '订单商品' }}</text>
                <text v-if="firstGoods?.sku_name" class="goods-sku">{{ firstGoods.sku_name }}</text>
                <text v-if="imei(firstGoods)" class="goods-imei">IMEI {{ imei(firstGoods) }}</text>
                <text v-if="goodsCount > 1" class="goods-count">共 {{ goodsCount }} 件，点击查看全部</text>
            </view>
            <view class="amount-wrap">
                <text class="amount">¥{{ money(order.order_money) }}</text>
                <u-icon :name="expanded ? 'arrow-up' : 'arrow-down'" color="#94a3b8" size="13" />
            </view>
        </view>

        <view v-if="expanded" class="goods-detail">
            <view v-for="(goods, index) in goodsList" :key="goods.order_goods_id || index" class="goods-line">
                <view class="goods-line__copy">
                    <text class="goods-line__name">{{ goods.goods_name || '商品' }}</text>
                    <text class="goods-line__meta">{{ [goods.sku_name, imei(goods) ? `IMEI ${ imei(goods) }` : ''].filter(Boolean).join(' · ') }}</text>
                </view>
                <text class="goods-line__price">¥{{ money(goods.price) }} × {{ goods.num || 1 }}</text>
            </view>
            <view class="detail-row"><text>订单编号</text><text class="detail-value" selectable>{{ order.order_no || '-' }}</text></view>
            <view v-if="order.offline_record?.handler_name" class="detail-row"><text>当前负责人</text><text class="detail-value">{{ order.offline_record.handler_name }}</text></view>
            <view v-if="order.member_remark" class="detail-row detail-row--remark"><text>客户留言</text><text class="detail-value">{{ order.member_remark }}</text></view>
        </view>

        <view class="card-foot">
            <view class="progress-copy">
                <text>{{ workflowHint }}</text>
                <text v-if="order.offline_record?.handler_name" class="handler">负责人 {{ order.offline_record.handler_name }}</text>
            </view>
            <view v-if="isPending" class="action-group">
                <view v-if="order.offline_record?.status !== 'contacted'" class="minor-button" @click.stop="emit('contact', order)">已联系</view>
                <view class="primary-button" @click.stop="emit('operate', order)">处理订单</view>
            </view>
            <view v-else-if="isReadyForDelivery" class="primary-button primary-button--deliver" @click.stop="emit('deliver', order)">确认交付</view>
            <view v-else class="done-label">{{ Number(order.status) === -1 ? '订单已关闭' : '流程已完成' }}</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { img } from '@/utils/common'

const props = withDefaults(defineProps<{ order: Record<string, any>, expanded?: boolean }>(), { expanded: false })
const emit = defineEmits(['toggle', 'contact', 'operate', 'deliver'])

const goodsList = computed<any[]>(() => Array.isArray(props.order.order_goods) ? props.order.order_goods.filter((item: any) => Number(item.is_gift || 0) !== 1) : [])
const firstGoods = computed(() => goodsList.value[0] || null)
const goodsCount = computed(() => goodsList.value.reduce((total, item) => total + Number(item.num || 1), 0))
const mobile = computed(() => String(props.order.taker_mobile || props.order.member?.mobile || ''))
const customerName = computed(() => props.order.member?.nickname || props.order.taker_name || '到店客户')
const isPending = computed(() => Number(props.order.status) === 1 && props.order.payment_mode === 'offline_pending')
const isReadyForDelivery = computed(() => Number(props.order.status) === 2 && props.order.delivery_type === 'store' && ['offline_cash', 'offline_credit'].includes(props.order.payment_mode))
const statusLabel = computed(() => {
    const workflow = String(props.order.offline_record?.status || '')
    const map: Record<string, string> = { pending: '待联系', contacted: '已联系待到店', paid: '已收款待交付', credit: '已挂账待交付', delivered: '已交付', closed: '已关闭' }
    if (map[workflow]) return map[workflow]
    if (isPending.value) return '待处理'
    if (isReadyForDelivery.value) return props.order.payment_mode === 'offline_credit' ? '已挂账待交付' : '已收款待交付'
    return Number(props.order.status) === -1 ? '已关闭' : Number(props.order.status) === 5 ? '已完成' : '处理中'
})
const statusTone = computed(() => {
    if (['delivered'].includes(String(props.order.offline_record?.status)) || Number(props.order.status) === 5) return 'success'
    if (Number(props.order.status) === -1 || props.order.offline_record?.status === 'closed') return 'muted'
    if (props.order.offline_record?.status === 'credit') return 'warning'
    return 'primary'
})
const workflowHint = computed(() => {
    if (isPending.value) return props.order.offline_record?.status === 'contacted' ? '等待客户到店付款' : '请先联系客户确认到店安排'
    if (isReadyForDelivery.value) return props.order.payment_mode === 'offline_credit' ? '挂账已入 ERP，请核对客户后交付' : '收款已留痕，请核对客户后交付'
    if (Number(props.order.status) === -1) return props.order.close_remark || '订单已关闭并解除锁定'
    return '订单处理已完成'
})

function cover(goods: any) { return goods?.sku_image || goods?.goods_image_thumb_small || goods?.goods_image || '' }
function imageUrl(value: string) { return img(value) }
function money(value: any) { return Number(value || 0).toFixed(2) }
function imei(goods: any) { return String(goods?.sku?.sku_no || goods?.extend?.imei || goods?.extend?.sku_no || '') }
function formatTime(value: any) {
    const timestamp = Number(value || 0)
    if (!timestamp) return '-'
    const date = new Date(timestamp * 1000)
    const pad = (num: number) => String(num).padStart(2, '0')
    return `${ pad(date.getMonth() + 1) }-${ pad(date.getDate()) } ${ pad(date.getHours()) }:${ pad(date.getMinutes()) }`
}
function callCustomer() { if (mobile.value) uni.makePhoneCall({ phoneNumber: mobile.value }) }
</script>

<style scoped lang="scss">
.order-card { margin-bottom: 20rpx; overflow: hidden; border-radius: 24rpx; background: #fff; }
.card-head { min-height: 66rpx; padding: 0 24rpx; border-bottom: 2rpx solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; gap: 14rpx; }
.status-wrap { min-width: 0; display: flex; align-items: center; gap: 9rpx; }
.status-dot { width: 13rpx; height: 13rpx; flex-shrink: 0; border-radius: 50%; background: #2563eb; }
.status-dot--success { background: #16a34a; }
.status-dot--warning { background: #d97706; }
.status-dot--muted { background: #94a3b8; }
.status-text { color: #334155; font-size: 23rpx; font-weight: 650; }
.delivery-tag { padding: 4rpx 10rpx; border-radius: 11rpx; background: #f1f5f9; color: #64748b; font-size: 18rpx; }
.order-time { flex-shrink: 0; color: #94a3b8; font-size: 20rpx; }
.customer-row { padding: 20rpx 24rpx 14rpx; display: flex; align-items: center; }
.customer-avatar { width: 64rpx; height: 64rpx; flex-shrink: 0; border-radius: 17rpx; background: #eff6ff; display: flex; align-items: center; justify-content: center; }
.customer-info { min-width: 0; flex: 1; margin-left: 14rpx; }
.customer-name, .customer-mobile { display: block; }
.customer-name { color: #1e293b; font-size: 27rpx; font-weight: 700; }
.customer-mobile { margin-top: 4rpx; color: #94a3b8; font-size: 21rpx; }
.call-button { width: 64rpx; height: 64rpx; border-radius: 50%; background: #f0fdf4; display: flex; align-items: center; justify-content: center; }
.goods-preview { margin: 0 24rpx; padding: 16rpx; border-radius: 16rpx; background: #f7f9fc; display: flex; align-items: center; gap: 15rpx; }
.goods-cover { width: 96rpx; height: 96rpx; flex-shrink: 0; border-radius: 13rpx; background: #eef2f7; }
.goods-cover--empty { display: flex; align-items: center; justify-content: center; }
.goods-info { min-width: 0; flex: 1; }
.goods-name, .goods-sku, .goods-imei, .goods-count { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.goods-name { color: #334155; font-size: 25rpx; font-weight: 650; }
.goods-sku { margin-top: 5rpx; color: #64748b; font-size: 20rpx; }
.goods-imei, .goods-count { margin-top: 5rpx; color: #94a3b8; font-size: 19rpx; }
.goods-imei { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
.amount-wrap { flex-shrink: 0; display: flex; flex-direction: column; align-items: flex-end; gap: 10rpx; }
.amount { color: #ef4444; font-size: 27rpx; font-weight: 750; }
.goods-detail { margin: 14rpx 24rpx 0; padding: 4rpx 16rpx; border-radius: 14rpx; border: 2rpx solid #eef2f7; }
.goods-line { padding: 14rpx 0; border-bottom: 1rpx solid #eef2f7; display: flex; align-items: center; justify-content: space-between; gap: 18rpx; }
.goods-line__copy { min-width: 0; flex: 1; }
.goods-line__name, .goods-line__meta { display: block; }
.goods-line__name { color: #334155; font-size: 22rpx; }
.goods-line__meta { margin-top: 4rpx; color: #94a3b8; font-size: 18rpx; }
.goods-line__price { flex-shrink: 0; color: #475569; font-size: 21rpx; }
.detail-row { padding: 13rpx 0; display: flex; align-items: flex-start; justify-content: space-between; gap: 20rpx; color: #94a3b8; font-size: 19rpx; }
.detail-value { max-width: 72%; color: #475569; text-align: right; word-break: break-all; }
.detail-row--remark { border-top: 1rpx solid #eef2f7; }
.card-foot { min-height: 90rpx; margin-top: 18rpx; padding: 16rpx 24rpx; border-top: 2rpx solid #f3f4f6; display: flex; align-items: center; justify-content: space-between; gap: 18rpx; box-sizing: border-box; }
.progress-copy { min-width: 0; flex: 1; color: #64748b; font-size: 20rpx; line-height: 1.4; }
.progress-copy text { display: block; }
.progress-copy .handler { margin-top: 4rpx; color: #94a3b8; font-size: 18rpx; }
.action-group { flex-shrink: 0; display: flex; align-items: center; gap: 10rpx; }
.minor-button, .primary-button { height: 58rpx; padding: 0 20rpx; border-radius: 29rpx; display: flex; align-items: center; justify-content: center; font-size: 22rpx; font-weight: 650; box-sizing: border-box; }
.minor-button { border: 1rpx solid #bfdbfe; color: #2563eb; background: #fff; }
.primary-button { color: #fff; background: var(--primary-color); }
.primary-button--deliver { padding: 0 25rpx; background: #16a34a; }
.done-label { flex-shrink: 0; color: #94a3b8; font-size: 21rpx; }
</style>
