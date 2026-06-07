<template>
    <view class="detail-page">
        <!-- #ifdef MP -->
        <RecyclePageHeader title="订单详情" class="detail-page-header">
            <view class="detail-search-box" @tap.stop>
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 detail-search-icon" @click="applyDeviceKeyword"></text>
                <ScanCodeInput
                    v-model="deviceKeyword"
                    class="detail-search-input"
                    placeholder="搜索本订单设备 IMEI/型号"
                    :maxlength="80"
                    input-align="left"
                    font-size="24rpx"
                    placeholder-class="text-[rgba(255,255,255,.68)] text-[24rpx]"
                    :show-scan-text="false"
                    @update:modelValue="handleDeviceKeywordInput"
                    @confirm="applyDeviceKeyword"
                    @scan="applyDeviceKeyword"
                />
            </view>
        </RecyclePageHeader>
        <!-- #endif -->

        <view v-if="loading" class="loading-state">
            <text class="loading-state__text">订单数据加载中...</text>
        </view>

        <template v-else-if="order">
            <view class="status-hero">
                <view class="status-hero__main">
                    <view>
                        <view class="status-hero__title">{{ order.status_name || '订单处理中' }}</view>
                        <view class="status-hero__subtitle">
                            订单号：{{ order.order_no }}
                            <text class="nc-iconfont nc-icon-fuzhiV6xx1 ml-[10rpx]" @click.stop="copyNo(order.order_no)"></text>
                        </view>
                    </view>
                    <view class="status-hero__tag">{{ order.flow_mode_name || '整单流转' }}</view>
                </view>
                <view class="status-hero__summary">
                    <view
                        v-for="item in detailSummaryItems"
                        :key="item.key"
                        class="summary-item"
                        :class="{ 'summary-item--active': deviceFilter === item.filter, 'summary-item--important': item.important }"
                        @click="toggleDeviceFilter(item.filter)"
                    >
                        <text class="summary-item__label">{{ item.label }}</text>
                        <text class="summary-item__value">{{ item.value }}</text>
                    </view>
                </view>
                <view v-if="deviceFilter" class="status-filter-bar">
                    <text>{{ currentFilterLabel }}：{{ filteredDevices.length }} 台</text>
                    <text class="status-filter-bar__clear" @click="clearDeviceFilter">清除</text>
                </view>
                <!-- #ifndef MP -->
                <view class="status-search-bar">
                    <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 status-search-bar__icon" @click="applyDeviceKeyword"></text>
                    <ScanCodeInput
                        v-model="deviceKeyword"
                        class="status-search-bar__input"
                        placeholder="搜索本订单设备 IMEI/型号"
                        :maxlength="80"
                        input-align="left"
                        font-size="24rpx"
                        placeholder-class="text-[rgba(255,255,255,.68)] text-[24rpx]"
                        :show-scan-text="false"
                        @update:modelValue="handleDeviceKeywordInput"
                        @confirm="applyDeviceKeyword"
                        @scan="applyDeviceKeyword"
                    />
                    <text
                        v-if="deviceKeyword"
                        class="nc-iconfont nc-icon-cuohaoV6xx1 status-search-bar__clear"
                        @click="clearDeviceKeyword"
                    ></text>
                </view>
                <!-- #endif -->
            </view>

            <view class="card">
                <view class="card__title">客户信息</view>
                <view class="info-row">
                    <text class="info-row__label">姓名</text>
                    <text class="info-row__value">{{ customerName }}</text>
                </view>
                <view class="info-row">
                    <text class="info-row__label">手机号</text>
                    <view class="info-row__value info-row__value--inline">
                        <text>{{ customerPhone }}</text>
                        <text
                            v-if="customerPhone && customerPhone !== '-'"
                            class="nc-iconfont nc-icon-dianhuaV6xx1 ml-[10rpx] text-[#2563eb]"
                            @click.stop="makePhoneCall(customerPhone)"
                        ></text>
                    </view>
                </view>
                <view v-if="order.sender_address" class="info-row info-row--top">
                    <text class="info-row__label">地址</text>
                    <text class="info-row__value">{{ order.sender_address }}</text>
                </view>
            </view>

            <view v-if="order.express_no || order.express_company" class="card">
                <view class="card__title">物流信息</view>
                <view class="info-row">
                    <text class="info-row__label">快递公司</text>
                    <text class="info-row__value">{{ order.express_company || '-' }}</text>
                </view>
                <view class="info-row">
                    <text class="info-row__label">快递单号</text>
                    <view class="info-row__value info-row__value--inline">
                        <text>{{ order.express_no || '-' }}</text>
                        <text
                            v-if="order.express_no"
                            class="nc-iconfont nc-icon-fuzhiV6xx1 ml-[10rpx] text-[#2563eb]"
                            @click.stop="copyNo(order.express_no)"
                        ></text>
                    </view>
                </view>
                <view v-if="order.express_no" class="info-row info-row--link" @click="openExpressTrack">
                    <text class="info-row__label">物流轨迹</text>
                    <view class="info-row__value info-row__value--inline">
                        <text>查看物流</text>
                        <text class="nc-iconfont nc-icon-youV6xx1 ml-[10rpx] text-[#94a3b8]"></text>
                    </view>
                </view>
            </view>

            <view class="card">
                <view class="card__header">
                    <view class="card__title">设备列表</view>
                    <view class="card__subtitle">
                        {{ hasDeviceListFilter ? `${ filteredDevices.length }/${ devices.length }` : devices.length }} 台设备
                    </view>
                </view>
                <view v-if="activeDeviceKeyword" class="device-search-result">
                    <text>设备搜索：{{ activeDeviceKeyword }}</text>
                    <text class="device-search-result__clear" @click="clearDeviceKeyword">清除</text>
                </view>

                <view v-if="!devices.length" class="empty-state">
                    <text>暂无设备信息</text>
                </view>
                <view v-else-if="hasDeviceListFilter && !filteredDevices.length" class="empty-state">
                    <text>当前筛选下暂无设备</text>
                </view>

                <view
                    v-for="device in filteredDevices"
                    :key="device.id"
                    class="device-card-wrap"
                >
                    <DeviceFlowCard
                        :device="device"
                        :summary-items="getDeviceSummary(device)"
                        :actions="getDeviceActions(device)"
                        :selectable="canBatchSelect(device)"
                        :selected="isBatchSelected(device.id)"
                        @toggle-select="toggleBatchDevice(device.id)"
                        @action="handleDeviceAction($event, device)"
                    />
                </view>
            </view>

            <view class="card">
                <view class="card__title">订单信息</view>
                <view class="info-row">
                    <text class="info-row__label">创建时间</text>
                    <text class="info-row__value">{{ order.create_at || '-' }}</text>
                </view>
                <view class="info-row">
                    <text class="info-row__label">配送方式</text>
                    <text class="info-row__value">{{ order.delivery_type_name || '-' }}</text>
                </view>
                <view class="info-row">
                    <text class="info-row__label">流转模式</text>
                    <text class="info-row__value">{{ order.flow_mode_name || '-' }}</text>
                </view>
                <view v-if="order.remark" class="info-row info-row--top">
                    <text class="info-row__label">备注</text>
                    <text class="info-row__value">{{ order.remark }}</text>
                </view>
                <view class="info-row info-row--link" @click="openPaymentLogs">
                    <text class="info-row__label">打款记录</text>
                    <view class="info-row__value info-row__value--inline">
                        <text>{{ getPaymentLogText() }}</text>
                        <text class="nc-iconfont nc-icon-youV6xx1 ml-[10rpx] text-[#94a3b8]"></text>
                    </view>
                </view>
                <view class="info-row info-row--link" @click="openNoticeLogs">
                    <text class="info-row__label">通知记录</text>
                    <view class="info-row__value info-row__value--inline">
                        <text>查看</text>
                        <text class="nc-iconfont nc-icon-youV6xx1 ml-[10rpx] text-[#94a3b8]"></text>
                    </view>
                </view>
            </view>

            <view v-if="footerActions.length" class="order-footer">
                <view v-if="selectedBatchDevices.length" class="order-footer__summary">
                    已选择 {{ selectedBatchDevices.length }} 台设备
                </view>
                <view
                    v-for="action in footerActions"
                    :key="action.type"
                    class="order-footer__btn"
                    :class="{ 'order-footer__btn--primary': action.primary, 'order-footer__btn--danger': action.danger }"
                    @click="handleAction(action.type)"
                >
                    {{ action.label }}
                </view>
            </view>
        </template>

        <CheckDevicePopup
            v-model:visible="checkPopupVisible"
            :deviceData="currentDevice"
            @success="handleCheckSuccess"
        />

        <PriceDevicePopup
            v-model:visible="pricePopupVisible"
            :deviceData="currentDevice"
            @success="handlePriceSuccess"
        />

        <SignOrderPopup
            v-model:visible="signPopupVisible"
            :orderId="orderId"
            :deviceList="devices"
            @success="handleSignSuccess"
        />

        <PaymentConfirmPopup
            v-model:visible="paymentPopupVisible"
            :orderData="order"
            :devices="devices"
            @success="handlePaymentSuccess"
        />

        <ConsignmentPopup
            v-model:visible="consignmentPopupVisible"
            :deviceData="currentDevice"
            @success="handleConsignmentSuccess"
        />

        <ReturnDevicePopup
            v-model:visible="returnPopupVisible"
            :devices="returnDevices"
            :orderId="orderId"
            @success="handleReturnSuccess"
        />

        <DeviceDetailPopup
            v-model:visible="deviceDetailVisible"
            :deviceData="currentDevice"
            @updated="loadDetail"
        />

        <OrderLogPopup
            v-model:visible="paymentLogsVisible"
            title="打款记录"
            type="payment"
            :records="paymentLogs"
            :loading="paymentLogsLoading"
        />

        <OrderLogPopup
            v-model:visible="noticeLogsVisible"
            title="通知记录"
            type="notice"
            :records="noticeLogs"
            :loading="noticeLogsLoading"
        />

        <ExpressTrackPopup
            v-model:visible="expressTrackVisible"
            :express-no="order?.express_no || ''"
            :mobile="customerPhone === '-' ? '' : customerPhone"
            :company-name="order?.express_company || ''"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import {
    batchRecycleDevices,
    getOrderBusinessStageOptions,
    getDevicePaymentLogs,
    getOrderDetail,
    getOrderNoticeLogs,
    pushOrderNotify,
    updateOrder
} from '@/addon/hsx_recycle/api/order'
import { generateOrderShortLink } from '@/addon/hsx_recycle/api/shortlink'
import { copy } from '@/utils/common'
import { makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import RecyclePageHeader from '@/addon/hsx_recycle/components/RecyclePageHeader.vue'
import ScanCodeInput from '@/addon/hsx_recycle/components/ScanCodeInput.vue'
import ExpressTrackPopup from '@/addon/hsx_recycle/components/ExpressTrackPopup.vue'
import CheckDevicePopup from './components/CheckDevicePopup.vue'
import PriceDevicePopup from './components/PriceDevicePopup.vue'
import SignOrderPopup from './components/SignOrderPopup.vue'
import PaymentConfirmPopup from './components/PaymentConfirmPopup.vue'
import ConsignmentPopup from './components/ConsignmentPopup.vue'
import ReturnDevicePopup from './components/ReturnDevicePopup.vue'
import DeviceDetailPopup from './components/DeviceDetailPopup.vue'
import DeviceFlowCard from './components/DeviceFlowCard.vue'
import OrderLogPopup from './components/OrderLogPopup.vue'
import { isConsignedDevice, shouldShowConfirmStatus } from '@/addon/hsx_recycle/utils/device'
import { useRecyclePrintActions } from '@/addon/hsx_recycle/hooks/useRecyclePrintActions'

const loading = ref(true)
const order = ref<any>(null)
const currentDevice = ref<any>(null)
const checkPopupVisible = ref(false)
const pricePopupVisible = ref(false)
const signPopupVisible = ref(false)
const paymentPopupVisible = ref(false)
const consignmentPopupVisible = ref(false)
const returnPopupVisible = ref(false)
const deviceDetailVisible = ref(false)
const paymentLogsVisible = ref(false)
const noticeLogsVisible = ref(false)
const expressTrackVisible = ref(false)
const selectedBatchIds = ref<Array<number | string>>([])
const paymentLogs = ref<any[]>([])
const noticeLogs = ref<any[]>([])
const returnDevices = ref<any[]>([])
const paymentLogsLoading = ref(false)
const noticeLogsLoading = ref(false)
const deviceFilter = ref('')
const deviceKeyword = ref('')
const activeDeviceKeyword = ref('')
let pendingRouteDeviceKeyword = ''
let orderId = ''

const {
    loadManualPrintActions: loadDevicePrintActions,
    getVisiblePrintActions: getVisibleDevicePrintActions,
    executePrintAction: executeDevicePrintAction
} = useRecyclePrintActions('device')
const {
    loadManualPrintActions: loadOrderPrintActions,
    getVisiblePrintActions: getVisibleOrderPrintActions,
    executePrintAction: executeOrderPrintAction
} = useRecyclePrintActions('order')

const devices = computed(() => Array.isArray(order.value?.devices) ? order.value.devices : [])
const flowSummary = computed(() => order.value?.flow_summary || {})
const businessStageOptions = ref<Array<{ key: string, label: string, important?: boolean }>>([])
const filteredDevices = computed(() => {
    return devices.value.filter((device: any) => {
        const stageMatched = !deviceFilter.value || matchDeviceFilter(device, deviceFilter.value)
        const keywordMatched = !activeDeviceKeyword.value || matchDeviceKeyword(device, activeDeviceKeyword.value)
        return stageMatched && keywordMatched
    })
})
const hasDeviceListFilter = computed(() => Boolean(deviceFilter.value || activeDeviceKeyword.value))
const detailSummaryItems = computed(() => businessStageOptions.value.map((option) => ({
    key: option.key,
    label: option.label,
    value: getBusinessStageCount(option.key),
    filter: option.key,
    important: Boolean(option.important)
})))
const currentFilterLabel = computed(() => {
    return businessStageOptions.value.find(item => item.key === deviceFilter.value)?.label || ''
})
const customerName = computed(() => {
    return order.value?.member?.nickname
        || order.value?.member?.username
        || order.value?.recycleUserAddress?.name
        || order.value?.sender_name
        || order.value?.customer_name
        || '-'
})
const customerPhone = computed(() => {
    return order.value?.member?.mobile
        || order.value?.recycleUserAddress?.mobile
        || order.value?.sender_mobile
        || order.value?.customer_phone
        || '-'
})
const orderActions = computed(() => getActions(order.value))
const selectedBatchDevices = computed(() => {
    return filteredDevices.value.filter((device: any) => selectedBatchIds.value.includes(device.id) && canBatchSelect(device))
})
const footerActions = computed(() => {
    const actions: Array<{ label: string, type: string, primary?: boolean, danger?: boolean }> = []

    if (selectedBatchDevices.value.length) {
        actions.push(
            { label: '批量退回', type: 'batch_reject', danger: true },
            { label: '批量确认回收', type: 'batch_confirm', primary: true }
        )
    }

    return [...actions, ...orderActions.value]
})

const normalizeBusinessStageOptions = (data: any) => {
    const rows = Array.isArray(data) ? data : Object.values(data || {})
    const normalized = rows
        .map((item: any) => ({
            key: String(item?.key || item?.value || ''),
            label: String(item?.name || item?.label || item?.key || ''),
            important: Boolean(item?.important)
        }))
        .filter((item: any) => item.key && item.label)
    return normalized.length ? normalized : businessStageOptions.value
}

const loadBusinessStageOptions = async () => {
    try {
        const res: any = await getOrderBusinessStageOptions()
        businessStageOptions.value = normalizeBusinessStageOptions(res?.data)
    } catch (error) {
    }
}

onLoad((option: any) => {
    orderId = option?.id || ''
    deviceFilter.value = option?.filter || ''
    pendingRouteDeviceKeyword = normalizeKeyword(option?.device_keyword || option?.imei || option?.keyword || '')
    loadDevicePrintActions()
    loadOrderPrintActions()
    loadBusinessStageOptions()
    if (orderId) {
        loadDetail()
    }
})

const loadDetail = async () => {
    loading.value = true
    try {
        const res: any = await getOrderDetail(orderId)
        order.value = res?.data || null
        applyRouteDeviceKeyword()
        syncBatchSelection()
    } finally {
        loading.value = false
    }
}

const syncBatchSelection = () => {
    const validIds = filteredDevices.value.filter((device: any) => canBatchSelect(device)).map((device: any) => device.id)
    selectedBatchIds.value = selectedBatchIds.value.filter(id => validIds.includes(id))
}

const toggleDeviceFilter = (filter: string) => {
    deviceFilter.value = deviceFilter.value === filter ? '' : filter
    syncBatchSelection()
}

const clearDeviceFilter = () => {
    deviceFilter.value = ''
    syncBatchSelection()
}

const applyDeviceKeyword = () => {
    const keyword = normalizeKeyword(deviceKeyword.value)
    if (!keyword) {
        activeDeviceKeyword.value = ''
        syncBatchSelection()
        return
    }
    activeDeviceKeyword.value = keyword
    deviceKeyword.value = keyword
    syncBatchSelection()
}

const handleDeviceKeywordInput = (value: string) => {
    const keyword = normalizeKeyword(value)
    deviceKeyword.value = keyword
    activeDeviceKeyword.value = keyword
    pendingRouteDeviceKeyword = ''
    syncBatchSelection()
}

const clearDeviceKeyword = () => {
    deviceKeyword.value = ''
    activeDeviceKeyword.value = ''
    pendingRouteDeviceKeyword = ''
    syncBatchSelection()
}

const applyRouteDeviceKeyword = () => {
    const keyword = normalizeKeyword(pendingRouteDeviceKeyword)
    if (!keyword) return

    const matched = devices.value.some((device: any) => matchDeviceKeyword(device, keyword))
    if (!matched) return

    deviceKeyword.value = keyword
    activeDeviceKeyword.value = keyword
}

const matchDeviceFilter = (device: any, filter: string) => {
    return matchBusinessStage(device, filter)
}

const matchDeviceKeyword = (device: any, keyword: string) => {
    const normalizedKeyword = normalizeSearchText(keyword)
    if (!normalizedKeyword) return true
    return getDeviceSearchTexts(device).some((value) => normalizeSearchText(value).includes(normalizedKeyword))
}

const getDeviceSearchTexts = (device: any) => {
    const info = normalizeObject(device.info)
    return [
        device.id,
        device.imei,
        device.imei2,
        device.sn,
        device.user_sn,
        device.model,
        device.device_name,
        device.capacity,
        device.color,
        info.imei,
        info.imei2,
        info.sn,
        info.user_sn,
        info.model,
        info.capacity,
        info.color
    ].filter((value) => value !== undefined && value !== null && String(value).trim() !== '')
}

const normalizeKeyword = (value: any) => String(value || '').trim()

const normalizeSearchText = (value: any) => {
    return String(value || '')
        .trim()
        .toLowerCase()
        .replace(/[\s\-_:：]/g, '')
}

const getBusinessStageCount = (stage: string) => {
    return devices.value.filter((device: any) => matchBusinessStage(device, stage)).length
}

const matchBusinessStage = (device: any, stage: string) => {
    const status = Number(device.status || 0)
    const confirmStatus = Number(device.confirm_status || 0)
    const payStatus = Number(device.pay_status || 0)

    if (stage === 'processing') {
        return [1, 2, 3].includes(status)
    }

    if (stage === 'pending_confirm') {
        return Boolean(device.can_confirm)
            || status === 4
            || ([4, 7, 8].includes(status) && confirmStatus === 0)
    }

    if (stage === 'payable') {
        return Boolean(device.can_pay)
            || ([5, 7, 8].includes(status) && payStatus !== 1 && !isConsignedDevice(device))
    }

    if (stage === 'completed') {
        return !isConsignedDevice(device)
            && status !== 9
            && status !== 6
            && confirmStatus !== 2
            && (payStatus === 1 || Number(device.pay_amount || 0) > 0)
    }

    if (stage === 'exception') {
        return status === 6 || confirmStatus === 2 || status === 9 || isConsignedDevice(device)
    }

    return true
}

const getActions = (item: any) => {
    if (!item) return []
    const status = Number(item.status || 0)
    const available = item.available_actions || {}
    const actions: Array<{ label: string, type: string, primary?: boolean, danger?: boolean }> = []

    if (status === 1) actions.push({ label: '签收订单', type: 'receive', primary: true })
    if (available.can_push_confirm_notice) actions.push({ label: '推送通知', type: 'notify' })
    if (available.can_order_payment || available.can_pay_devices) actions.push({ label: '确认打款', type: 'payment', primary: true })
    if (available.can_complete_order && status !== 7) actions.push({ label: '刷新完成状态', type: 'complete' })

    // 添加分享订单功能
    actions.push({ label: '分享订单', type: 'share' })

    if ([1, 2].includes(status)) {
        actions.push({ label: '取消订单', type: 'cancel', danger: true })
    }

    getVisibleOrderPrintActions(item).forEach((action: any) => {
        actions.push({
            label: action.button_text || action.scene_name || '打印',
            type: `print:${ action.scene_key }`
        })
    })

    return actions
}

const getDeviceActions = (device: any) => {
    const status = Number(device.status || 0)
    const actions: Array<{ label: string, type: string, primary?: boolean, danger?: boolean }> = [
        { label: '详情', type: 'detail' ,  primary:true }
    ]

    if ([1, 2].includes(status)) {
        actions.push({ label: device.check_result_seller || device.check_result ? '继续质检' : '开始质检', type: 'check', primary: true })
    }

    if ([3, 4, 7, 8].includes(status) && status !== 6 && status !== 9) {
        actions.push({ label: status === 3 ? '定价' : '重新定价', type: 'price', primary: status === 3 })
    }

    if (device.can_confirm) {
        actions.push({ label: '确认回收', type: 'confirm', primary: true })
    }

    if (isRejectable(device)) {
        actions.push({ label: '退回设备', type: 'reject', danger: true })
    }

    if (Number(device.return_order_id || 0) > 0 || Number(device.status || 0) === 6) {
        actions.push({ label: '查看退回单', type: 'view_return' })
    }

    if (canTransferConsignment(device)) {
        actions.push({ label: '转代卖', type: 'consignment' })
    }

    if (Number(device.consignment_order_id || 0) > 0) {
        actions.push({ label: '查看代卖单', type: 'view_consignment' })
    }

    getVisibleDevicePrintActions(device).forEach((action: any) => {
        actions.push({
            label: action.button_text || action.scene_name || '打印',
            type: `print:${ action.scene_key }`
        })
    })

    return actions
}

const handleAction = async (type: string) => {
    if (type.startsWith('print:')) {
        const sceneKey = type.replace('print:', '')
        const action = getVisibleOrderPrintActions(order.value).find((item: any) => item.scene_key === sceneKey)
        if (!action) {
            uni.showToast({ title: '打印场景不可用', icon: 'none' })
            return
        }
        await executeOrderPrintAction(action, { order_id: order.value?.id || orderId, biz_id: order.value?.id || orderId })
        return
    }

    if (type === 'receive') {
        signPopupVisible.value = true
        return
    }

    if (type === 'payment') {
        paymentPopupVisible.value = true
        return
    }

    if (type === 'notify') {
        try {
            await pushOrderNotify(order.value.id)
            uni.showToast({ title: '通知已推送', icon: 'success' })
            loadDetail()
        } catch (error: any) {
            uni.showToast({ title: error?.msg || error?.message || '推送失败', icon: 'none' })
        }
        return
    }

    if (type === 'share') {
        await handleShareOrder()
        return
    }

    if (type === 'batch_confirm') {
        await handleBatchConfirm()
        return
    }

    if (type === 'batch_reject') {
        await handleBatchReject()
        return
    }

    if (type === 'complete') {
        await submitOrderAction({ action: 'order_complete' }, '订单状态已刷新')
        return
    }

    if (type === 'cancel') {
        uni.showModal({
            title: '取消订单',
            content: '请输入取消原因',
            editable: true,
            placeholderText: '如：客户取消、信息错误',
            success: async (res) => {
                if (!res.confirm) return
                await submitOrderAction({
                    action: 'order_cancel',
                    cancel_reason: res.content || '',
                    reason: res.content || ''
                }, '订单已取消')
            }
        })
    }
}

const submitOrderAction = async (payload: Record<string, any>, successText: string) => {
    try {
        await updateOrder(orderId, payload)
        uni.showToast({ title: successText, icon: 'success' })
        await loadDetail()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '操作失败', icon: 'none' })
    }
}

const handleShareOrder = async () => {
    uni.showLoading({ title: '生成分享链接...' })
    try {
        const res: any = await generateOrderShortLink({
            order_id: order.value.id,
            order_no: order.value.order_no || ''
        })

        uni.hideLoading()

        if (res.code !== 1) {
            uni.showToast({ title: res.msg || '生成分享链接失败', icon: 'none' })
            return
        }

        const shortLink = res.data?.short_link
        if (!shortLink) {
            uni.showToast({ title: '生成分享链接失败', icon: 'none' })
            return
        }

        const userName = customerName.value === '-' ? '客户' : customerName.value
        const shareText = `${userName}的回收订单 ${order.value.order_no} ${shortLink}`

        copy(shareText)
        uni.showToast({ title: '分享链接已复制', icon: 'success' })
    } catch (error: any) {
        uni.hideLoading()
        uni.showToast({ title: error?.msg || '生成分享链接失败', icon: 'none' })
    }
}

const openPaymentLogs = async () => {
    if (!order.value?.id) return
    paymentLogsVisible.value = true
    paymentLogsLoading.value = true
    try {
        const res: any = await getDevicePaymentLogs(order.value.id)
        paymentLogs.value = Array.isArray(res?.data) ? res.data : []
    } finally {
        paymentLogsLoading.value = false
    }
}

const openNoticeLogs = async () => {
    if (!order.value?.id) return
    noticeLogsVisible.value = true
    noticeLogsLoading.value = true
    try {
        const res: any = await getOrderNoticeLogs(order.value.id)
        noticeLogs.value = Array.isArray(res?.data) ? res.data : []
    } finally {
        noticeLogsLoading.value = false
    }
}

const openExpressTrack = () => {
    if (!order.value?.express_no) {
        uni.showToast({ title: '暂无快递单号', icon: 'none' })
        return
    }
    expressTrackVisible.value = true
}

const getPaymentLogText = () => {
    const paidCount = Number(order.value?.device_payment_summary?.paid_count || 0)
    return paidCount > 0 ? `${ paidCount } 条` : '查看'
}

const handleDeviceAction = async (type: string, device: any) => {
    if (type.startsWith('print:')) {
        const sceneKey = type.replace('print:', '')
        const action = getVisibleDevicePrintActions(device).find((item: any) => item.scene_key === sceneKey)
        if (!action) {
            uni.showToast({ title: '打印场景不可用', icon: 'none' })
            return
        }
        await executeDevicePrintAction(action, { device_id: device.id })
        return
    }

    if (type === 'detail') {
        currentDevice.value = device
        deviceDetailVisible.value = true
        return
    }

    if (type === 'check') {
        currentDevice.value = device
        checkPopupVisible.value = true
        return
    }

    if (type === 'price') {
        currentDevice.value = device
        pricePopupVisible.value = true
        return
    }

    if (type === 'confirm') {
        await handleConfirmDevices([device.id])
        return
    }

    if (type === 'reject') {
        openReturnPopup([device])
        return
    }

    if (type === 'consignment') {
        currentDevice.value = device
        consignmentPopupVisible.value = true
        return
    }

    if (type === 'view_consignment') {
        uni.navigateTo({
            url: `/addon/hsx_recycle/pages/consignment/detail?id=${ device.consignment_order_id }`
        })
        return
    }

    if (type === 'view_return') {
        const returnOrderId = Number(device.return_order_id || 0)
        if (!returnOrderId) {
            uni.showToast({ title: '暂无关联退回单', icon: 'none' })
            return
        }
        uni.navigateTo({
            url: `/addon/hsx_recycle/pages/return/detail?id=${ returnOrderId }`
        })
    }
}

const handleConfirmDevices = async (deviceIds: Array<number | string>) => {
    if (!deviceIds.length) return
    uni.showModal({
        title: '确认回收',
        content: `确认回收选中的 ${ deviceIds.length } 台设备？`,
        success: async (res) => {
            if (!res.confirm) return
            try {
                await batchRecycleDevices({
                    ids: deviceIds.join(','),
                    remark: '移动端确认回收'
                })
                uni.showToast({ title: '确认成功', icon: 'success' })
                selectedBatchIds.value = []
                await loadDetail()
            } catch (error: any) {
                uni.showToast({ title: error?.msg || error?.message || '操作失败', icon: 'none' })
            }
        }
    })
}

const handleRejectDevices = async (deviceIds: Array<number | string>) => {
    if (!deviceIds.length) return
    const selected = devices.value.filter((device: any) => deviceIds.includes(device.id))
    openReturnPopup(selected)
}

const openReturnPopup = (selected: any[]) => {
    returnDevices.value = selected.filter((device: any) => device?.id)
    if (!returnDevices.value.length) {
        uni.showToast({ title: '请选择退回设备', icon: 'none' })
        return
    }
    returnPopupVisible.value = true
}

const handleReturnSuccess = async (result: { returnOrderId: number | string }) => {
    selectedBatchIds.value = []
    await loadDetail()
    if (result.returnOrderId) {
        uni.showModal({
            title: '退回单已生成',
            content: '是否立即查看并处理退回物流？',
            confirmText: '查看',
            cancelText: '留在订单',
            success: (res) => {
                if (!res.confirm) return
                uni.navigateTo({
                    url: `/addon/hsx_recycle/pages/return/detail?id=${ result.returnOrderId }`
                })
            }
        })
    }
}

const handleBatchConfirm = async () => {
    await handleConfirmDevices(selectedBatchDevices.value.map((device: any) => device.id))
}

const handleBatchReject = async () => {
    await handleRejectDevices(selectedBatchDevices.value.map((device: any) => device.id))
}

const canBatchSelect = (device: any) => {
    return (order.value?.flow_mode || 'order') === 'device' && Boolean(device.can_confirm)
}

const toggleBatchDevice = (deviceId: number | string) => {
    if (isBatchSelected(deviceId)) {
        selectedBatchIds.value = selectedBatchIds.value.filter(id => id !== deviceId)
    } else {
        selectedBatchIds.value = [...selectedBatchIds.value, deviceId]
    }
}

const isBatchSelected = (deviceId: number | string) => selectedBatchIds.value.includes(deviceId)

const isRejectable = (device: any) => {
    const status = Number(device.status || 0)
    if (isConsignedDevice(device)) return false
    return [4, 7, 8].includes(status) && status !== 6 && status !== 9
}

const canTransferConsignment = (device: any) => {
    const status = Number(device.status || 0)
    if (![3, 4, 7, 8].includes(status)) return false
    if (Number(device.consignment_order_id || 0) > 0) return false
    return !isConsignedDevice(device) && status !== 6 && status !== 9
}

const isConfirmStage = (device: any) => {
    return shouldShowConfirmStatus(device)
}

const getDeviceSummary = (device: any) => {
    const info = normalizeObject(device.info)
    const meta = normalizeObject(info.check_meta)
    const summary: Array<{ label: string, value: string }> = []
    const candidates = [
        ['内存', device.capacity || info.capacity || meta.capacity],
        ['颜色', device.color || info.color || meta.color],
        ['系统', device.system_version || info.system_version || meta.system_version],
        ['保修', device.warranty_info || info.warranty_info || meta.warranty_info],
        ['电池', meta.battery ? `${ meta.battery }%` : '']
    ]
    candidates.forEach(([label, value]) => {
        if (value && summary.length < 4) {
            summary.push({ label: String(label), value: String(value) })
        }
    })
    return summary
}

const normalizeObject = (value: any) => {
    if (!value) return {}
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            return parsed && typeof parsed === 'object' ? parsed : {}
        } catch (error) {
            return {}
        }
    }
    return typeof value === 'object' ? value : {}
}

const copyNo = (value: string) => copy(value)

const handleCheckSuccess = () => loadDetail()
const handlePriceSuccess = () => loadDetail()
const handleSignSuccess = () => loadDetail()
const handlePaymentSuccess = () => loadDetail()
const handleConsignmentSuccess = () => loadDetail()
</script>

<style scoped lang="scss">
.detail-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding-bottom: 300rpx;
}

.detail-page-header {
    z-index: 30;
}

.detail-search-box {
    flex: 1;
    min-width: 0;
    height: 64rpx;
    border-radius: 32rpx;
    background: rgba(255, 255, 255, 0.16);
    display: flex;
    align-items: center;
    padding: 0 18rpx;
    margin-left: 8rpx;
    margin-right: 22rpx;
}

.detail-search-icon,
.detail-search-clear {
    flex-shrink: 0;
    font-size: 26rpx;
    color: rgba(255, 255, 255, 0.82);
}

.detail-search-icon {
    margin-right: 10rpx;
}

.detail-search-clear {
    margin-left: 10rpx;
}

.detail-search-input {
    flex: 1;
    min-width: 0;
    height: 64rpx;
    color: #fff;
}

.detail-search-input :deep(.u-input),
.detail-search-input :deep(.u-input__content),
.status-search-bar__input :deep(.u-input),
.status-search-bar__input :deep(.u-input__content) {
    height: 64rpx;
    min-height: 64rpx;
    padding: 0 !important;
    background: transparent !important;
}

.detail-search-input :deep(.u-input__content__field-wrapper__field),
.status-search-bar__input :deep(.u-input__content__field-wrapper__field) {
    height: 64rpx;
    line-height: 64rpx;
    color: #fff;
}

.detail-search-input :deep(.scan-code-input__scan),
.status-search-bar__input :deep(.scan-code-input__scan) {
    color: rgba(255, 255, 255, 0.88);
}

.loading-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 60vh;
}

.loading-state__text {
    font-size: 28rpx;
    color: #8c8c8c;
}

.status-hero {
    padding: 32rpx 24rpx 28rpx;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
}

.status-hero__main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.status-hero__title {
    font-size: 38rpx;
    font-weight: 700;
    color: #fff;
}

.status-hero__subtitle {
    margin-top: 12rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.82);
}

.status-hero__tag {
    padding: 10rpx 18rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.18);
    color: #fff;
    font-size: 22rpx;
}

.status-hero__summary {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 16rpx;
    margin-top: 26rpx;
}

.summary-item {
    padding: 20rpx 12rpx;
    border-radius: 16rpx;
    background: rgba(255, 255, 255, 0.12);
    text-align: center;
    border: 2rpx solid transparent;
}

.summary-item--active {
    background: rgba(255, 255, 255, 0.24);
    border-color: rgba(255, 255, 255, 0.72);
}

.summary-item__label {
    display: block;
    font-size: 20rpx;
    color: rgba(255, 255, 255, 0.75);
}

.summary-item__value {
    display: block;
    margin-top: 10rpx;
    font-size: 34rpx;
    font-weight: 700;
    color: #fff;
}

.status-filter-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    margin-top: 18rpx;
    padding: 14rpx 18rpx;
    border-radius: 14rpx;
    background: rgba(255, 255, 255, 0.16);
    color: rgba(255, 255, 255, 0.92);
    font-size: 24rpx;
}

.status-filter-bar__clear {
    color: #fff;
    font-weight: 600;
    flex-shrink: 0;
}

.status-search-bar {
    height: 64rpx;
    margin-top: 18rpx;
    padding: 0 18rpx;
    border-radius: 32rpx;
    background: rgba(255, 255, 255, 0.16);
    display: flex;
    align-items: center;
}

.status-search-bar__icon,
.status-search-bar__clear {
    flex-shrink: 0;
    font-size: 26rpx;
    color: rgba(255, 255, 255, 0.84);
}

.status-search-bar__icon {
    margin-right: 10rpx;
}

.status-search-bar__clear {
    margin-left: 10rpx;
}

.status-search-bar__input {
    flex: 1;
    min-width: 0;
    height: 64rpx;
}

.card {
    margin: 20rpx;
    padding: 24rpx;
    border-radius: 18rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
}

.card__title {
    margin-bottom: 16rpx;
    font-size: 30rpx;
    font-weight: 700;
    color: #1f2937;
}

.card__subtitle {
    font-size: 22rpx;
    color: #8c8c8c;
}

.device-search-result {
    margin-bottom: 16rpx;
    padding: 12rpx 16rpx;
    border-radius: 12rpx;
    background: #eff6ff;
    color: #2563eb;
    font-size: 22rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}

.device-search-result__clear {
    flex-shrink: 0;
    font-weight: 600;
}

.info-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24rpx;
    padding: 14rpx 0;
    font-size: 24rpx;
}

.info-row + .info-row {
    border-top: 1rpx solid #f1f5f9;
}

.info-row--top {
    align-items: flex-start;
}

.info-row--link {
    cursor: pointer;
}

.info-row__label {
    color: #64748b;
    flex-shrink: 0;
}

.info-row__value {
    flex: 1;
    text-align: right;
    color: #0f172a;
    word-break: break-all;
}

.info-row__value--inline {
    display: flex;
    align-items: center;
    justify-content: flex-end;
}

.empty-state {
    padding: 60rpx 0;
    text-align: center;
    color: #8c8c8c;
    font-size: 24rpx;
}

.device-card-wrap + .device-card-wrap {
    border-top: 1rpx solid #f1f5f9;
}

.order-footer {
    position: fixed;
    left: 0;
    right: 0;
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(10rpx);
    z-index: 20;
}

.order-footer__btn {
    min-height: 68rpx;
    padding: 0 12rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22rpx;
    line-height: 1.3;
    text-align: center;
    border: 1rpx solid #dbe2ea;
    background: #fff;
    color: #475569;
}

.order-footer__btn--primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.order-footer__btn--danger {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #dc2626;
}

.order-footer {
    bottom: 0;
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12rpx;
    padding: 16rpx 20rpx calc(16rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #e5e7eb;
}

.order-footer__summary {
    grid-column: 1 / -1;
    font-size: 22rpx;
    color: #334155;
}
</style>
