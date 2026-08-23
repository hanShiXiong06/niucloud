<template>
    <view class="offline-page" :style="themeColor()">
        <PhoneShopListHeader
            v-model="keyword"
            placeholder="订单号 / 客户 / 手机号"
            :tabs="tabs"
            :active="activeTab"
            :is-mp="isMp"
            :custom-nav-style="customNavStyle"
            :can-go-back="canGoBack"
            :handle-nav-action="handleNavAction"
            show-scan
            @search="reload"
            @clear="clearKeyword"
            @scan="scanOrder"
            @refresh="reload"
            @change="changeTab"
        />

        <z-paging ref="pagingRef" v-model="orders" :fixed="true" :default-page-size="12" :paging-style="pagingStyle" @query="queryOrders">
            <template #empty>
                <view class="empty-wrap">
                    <u-empty mode="order" :text="emptyText" />
                    <text class="empty-hint">客户选择线下支付并提交后，订单会自动进入这里。</text>
                </view>
            </template>
            <view class="order-list">
                <OfflineOrderCard
                    v-for="order in orders"
                    :key="order.order_id"
                    :order="order"
                    :expanded="expandedId === Number(order.order_id)"
                    @toggle="toggleOrder"
                    @contact="markContacted"
                    @operate="openActions"
                    @deliver="confirmDelivery"
                />
            </view>
        </z-paging>

        <u-action-sheet
            :show="actionSheetVisible"
            title="处理线下订单"
            description="客户到店后选择真实成交方式"
            :actions="availableOrderActions"
            cancelText="取消"
            @close="actionSheetVisible = false"
            @select="selectAction"
        />

        <OfflineOrderProcessPopup
            :show="processVisible"
            :order="currentOrder"
            :action="processAction"
            @close="processVisible = false"
            @success="handleProcessSuccess"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import OfflineOrderCard from '@/addon/phone_shop/components/OfflineOrderCard.vue'
import OfflineOrderProcessPopup from '@/addon/phone_shop/components/OfflineOrderProcessPopup.vue'
import PhoneShopListHeader from '@/addon/phone_shop/components/PhoneShopListHeader.vue'
import { getOfflineOrderList, processOfflineOrder } from '@/addon/phone_shop/api/order'
import { usePhoneShopListHeader } from '@/addon/phone_shop/composables/usePhoneShopListHeader'

const pagingRef = ref<any>(null)
const orders = ref<any[]>([])
const keyword = ref('')
const activeTab = ref('pending')
const expandedId = ref(0)
const actionSheetVisible = ref(false)
const processVisible = ref(false)
const currentOrder = ref<any>(null)
const processAction = ref<'confirm_paid' | 'confirm_credit' | 'close_unreachable'>('confirm_paid')
const initialOrderId = ref(0)
const hasShown = ref(false)
const { isMp, customNavStyle, listTop, canGoBack, handleNavAction } = usePhoneShopListHeader()
const pagingStyle = computed(() => ({ top: listTop.value, background: '#f5f7fa' }))

const tabs = [
    { value: 'pending', label: '待处理' },
    { value: 'delivery', label: '待交付' },
    { value: 'complete', label: '已完成' },
    { value: 'closed', label: '已关闭' },
    { value: 'all', label: '全部' },
]

const orderActions = [
    { name: '确认线下收款', action: 'confirm_paid', color: '#16a34a', subname: '核对到账；凭证可由客户或员工上传' },
    { name: '确认客户挂账', action: 'confirm_credit', color: '#d97706', subname: '生成 ERP 应收，后续由财务跟进' },
    { name: '联系不上并关闭', action: 'close_unreachable', color: '#ef4444', subname: '填写原因并解除设备锁定' },
]
const availableOrderActions = computed(() => {
    const actions: any[] = [...orderActions]
    if (currentOrder.value?.offline_record?.status === 'voucher_submitted') {
        actions.push({ name: '驳回付款凭证', action: 'reject_voucher', color: '#d97706', subname: '填写原因并通知客户重新提交' })
    }
    return actions
})

const emptyText = computed(() => activeTab.value === 'pending' ? '暂无待处理线下订单' : activeTab.value === 'delivery' ? '暂无待交付订单' : '暂无相关订单')

onLoad((options: any) => {
    initialOrderId.value = Number(options?.order_id || 0)
    if (initialOrderId.value) activeTab.value = 'all'
})

onShow(() => {
    if (hasShown.value) pagingRef.value?.reload()
    hasShown.value = true
})

function queryParams() {
    const params: Record<string, any> = { offline_keyword: keyword.value.trim() }
    if (initialOrderId.value) params.order_id = initialOrderId.value
    if (activeTab.value === 'pending') { params.status = 1; params.payment_mode = 'offline_pending' }
    if (activeTab.value === 'delivery') params.status = 2
    if (activeTab.value === 'complete') params.status = 5
    if (activeTab.value === 'closed') params.status = -1
    return params
}

async function queryOrders(page: number, limit: number) {
    try {
        const response: any = await getOfflineOrderList({ ...queryParams(), page, limit })
        const payload = response?.data || {}
        const rows = Array.isArray(payload?.data) ? payload.data : Array.isArray(payload?.list) ? payload.list : []
        pagingRef.value?.complete(rows)
        if (initialOrderId.value && rows.length) {
            expandedId.value = Number(rows[0].order_id)
            initialOrderId.value = 0
        }
    } catch {
        pagingRef.value?.complete(false)
    }
}

function reload() { pagingRef.value?.reload() }
function clearKeyword() { initialOrderId.value = 0; reload() }
function changeTab(key: string) { activeTab.value = key; initialOrderId.value = 0; expandedId.value = 0; reload() }
function toggleOrder(order: any) { expandedId.value = expandedId.value === Number(order.order_id) ? 0 : Number(order.order_id) }

function scanOrder() {
    // #ifdef H5
    uni.showToast({ title: '请在小程序中使用扫码', icon: 'none' })
    // #endif
    // #ifndef H5
    uni.scanCode({
        success: result => {
            keyword.value = String(result.result || '').trim()
            reload()
        },
    })
    // #endif
}

function openActions(order: any) { currentOrder.value = order; actionSheetVisible.value = true }
function selectAction(item: any) {
    actionSheetVisible.value = false
    if (item.action === 'reject_voucher') {
        rejectVoucher()
        return
    }
    processAction.value = item.action
    processVisible.value = true
}

function rejectVoucher() {
    if (!currentOrder.value) return
    uni.showModal({
        title: '驳回付款凭证', content: '', editable: true,
        placeholderText: '请输入金额不符、截图不清晰等原因', confirmText: '确认驳回',
        success: async result => {
            if (!result.confirm) return
            const reason = String(result.content || '').trim()
            if (!reason) return uni.showToast({ title: '请填写驳回原因', icon: 'none' })
            await processOfflineOrder({ order_id: Number(currentOrder.value.order_id), action: 'reject_voucher', close_reason: reason })
            reload()
        }
    })
}

function markContacted(order: any) {
    const name = order.member?.nickname || order.taker_name || '该客户'
    uni.showModal({
        title: '确认已联系',
        content: `确认已经联系 ${ name }，并沟通了到店安排吗？`,
        confirmText: '已联系',
        success: async result => {
            if (!result.confirm) return
            await processOfflineOrder({ order_id: Number(order.order_id), action: 'contacted', remark: '已主动联系客户并确认到店安排' })
            reload()
        },
    })
}

function confirmDelivery(order: any) {
    uni.showModal({
        title: '确认到店交付',
        content: `请核对客户身份及设备后，确认订单 ${ order.order_no || '' } 已当面交付。`,
        confirmText: '确认交付',
        success: async result => {
            if (!result.confirm) return
            await processOfflineOrder({ order_id: Number(order.order_id), action: 'confirm_delivery', remark: '已当面核对客户并完成设备交付' })
            reload()
        },
    })
}

function handleProcessSuccess() { processVisible.value = false; actionSheetVisible.value = false; reload() }
</script>

<style scoped lang="scss">
.offline-page { min-height: 100vh; background: #f5f7fa; color: #1e293b; }
.order-list { padding: 20rpx 24rpx 42rpx; }
.empty-wrap { padding-top: 160rpx; text-align: center; }
.empty-hint { display: block; margin: 18rpx auto 0; max-width: 520rpx; color: #94a3b8; font-size: 21rpx; line-height: 1.55; }
</style>
