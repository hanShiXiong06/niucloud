<template>
    <view class="order-page" :style="themeColor()">
        <PhoneShopListHeader v-model="keyword" placeholder="订单号 / 客户 / 手机号" :tabs="tabs" :active="activeStatus" :is-mp="isMp" :custom-nav-style="customNavStyle" :can-go-back="canGoBack" :handle-nav-action="handleNavAction" show-scan @search="search" @clear="search" @scan="scanOrder" @refresh="reload" @change="changeStatus">
            <template #after><view class="channel-filter"><view v-for="item in channels" :key="item.value" :class="{ active: channel === item.value }" @click="changeChannel(item.value)">{{ item.label }}</view><text>共用同一订单流程</text></view></template>
        </PhoneShopListHeader>
        <z-paging ref="pagingRef" v-model="orders" :fixed="true" :default-page-size="12" :paging-style="pagingStyle" :scrollable="!actionSheetVisible && !panelVisible" @query="queryOrders">
            <template #empty><view class="empty-wrap"><u-empty mode="order" text="暂无匹配订单" /><text class="empty-hint">线上、线下订单都在这里；可切换类型和状态查看。</text></view></template>
            <view class="order-list"><OfflineOrderCard v-for="order in orders" :key="order.order_id" :order="order" @detail="toDetail" @operate="openActions" /></view>
        </z-paging>
        <u-action-sheet :show="actionSheetVisible" title="订单操作" :actions="availableActions" cancelText="取消" @close="actionSheetVisible = false" @select="selectAction" />
        <OrderActionPanel :show="panelVisible" :order="currentOrder" :action="action" @close="panelVisible = false" @changed="reload" />
    </view>
</template>
<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import OfflineOrderCard from '@/addon/phone_shop/components/OfflineOrderCard.vue'
import OrderActionPanel from '@/addon/phone_shop/components/OrderActionPanel.vue'
import PhoneShopListHeader from '@/addon/phone_shop/components/PhoneShopListHeader.vue'
import { getOrderDetail, getOrderList } from '@/addon/phone_shop/api/order'
import { usePhoneShopListHeader } from '@/addon/phone_shop/composables/usePhoneShopListHeader'
import { getOrderActions, orderQuery } from '@/addon/phone_shop/utils/orderWorkflow'
const pagingRef = ref<any>(null), orders = ref<any[]>([]), keyword = ref(''), activeStatus = ref(''), channel = ref('')
const actionSheetVisible = ref(false), panelVisible = ref(false), currentOrder = ref<any>(null), action = ref(''), opening = ref(false)
const hasShown = ref(false)
const { isMp, customNavStyle, listTop, canGoBack, handleNavAction } = usePhoneShopListHeader(76)
const pagingStyle = computed(() => ({ top: listTop.value, background: '#f5f7fa' }))
const channels = [{ label: '全部订单', value: '' }, { label: '线上', value: 'online' }, { label: '线下', value: 'offline' }]
const tabs = [{ value: '', label: '全部状态' }, { value: '1', label: '待支付/处理' }, { value: '2', label: '待发货/交付' }, { value: '3', label: '待收货' }, { value: '5', label: '已完成' }, { value: '-1', label: '已关闭' }]
const availableActions = computed(() => getOrderActions(currentOrder.value).map(item => ({ name: item.label, key: item.key, color: item.danger ? '#d14343' : '#263244' })))
onLoad((options: any) => {
    if (['online', 'offline'].includes(options?.order_channel)) channel.value = options.order_channel
    if (tabs.some(item => item.value === String(options?.status))) activeStatus.value = String(options.status)
    const orderId = Number(options?.order_id || options?.id || 0)
    if (orderId > 0) uni.navigateTo({ url: `/addon/phone_shop/pages/order/detail?order_id=${orderId}` })
})
onShow(() => { if (hasShown.value) reload(); hasShown.value = true })
function reload() { pagingRef.value?.reload() }
function search() { reload() }
function changeStatus(value: string) { if (activeStatus.value === value) return; activeStatus.value = value; reload() }
function changeChannel(value: string) { if (channel.value === value) return; channel.value = value; reload() }
async function queryOrders(page: number, limit: number) {
    try {
        const response: any = await getOrderList({ ...orderQuery(channel.value, activeStatus.value, keyword.value), page, limit })
        const data = response?.data
        if (!data || !Array.isArray(data.data)) throw new Error('订单数据格式异常')
        pagingRef.value?.complete(data.data)
    } catch { pagingRef.value?.complete(false) }
}
function toDetail(order: any) { uni.navigateTo({ url: `/addon/phone_shop/pages/order/detail?order_id=${Number(order.order_id)}` }) }
async function openActions(order: any) {
    if (opening.value) return
    opening.value = true
    try { const response: any = await getOrderDetail(order.order_id); currentOrder.value = response.data; actionSheetVisible.value = true }
    finally { opening.value = false }
}
function selectAction(item: any) { actionSheetVisible.value = false; action.value = item.key; panelVisible.value = true }
function scanOrder() {
    // #ifdef H5
    uni.showToast({ title: '浏览器请手动输入订单号', icon: 'none' })
    // #endif
    // #ifndef H5
    uni.scanCode({ success: result => { keyword.value = String(result.result || '').trim(); reload() } })
    // #endif
}
</script>
<style scoped lang="scss">
.order-page { min-height: 100vh; background: #f5f7fa; color: #263244; }.order-list { padding: 18rpx 22rpx 40rpx; }.channel-filter { height: 76rpx; display: flex; align-items: center; gap: 18rpx; box-sizing: border-box; }.channel-filter view { padding: 10rpx 20rpx; font-size: 25rpx; color: #64748b; border-radius: 8rpx; }.channel-filter .active { color: var(--primary-color); background: var(--primary-color-light, #eff5ff); font-weight: 600; }.channel-filter text { font-size: 21rpx; color: #9aa4b2; margin-left: auto; }.empty-wrap { padding-top: 150rpx; text-align: center; }.empty-hint { display: block; margin: 24rpx auto 0; max-width: 570rpx; color: #8590a2; font-size: 24rpx; line-height: 1.6; }
</style>
