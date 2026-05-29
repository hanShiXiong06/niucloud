<template>
    <view class="detail-page">
        <view v-if="loading" class="loading-box">加载中...</view>

        <template v-else-if="detail">
            <view class="hero-card">
                <view class="hero-card__top">
                    <view>
                        <view class="hero-card__status">{{ detail.status_name || '-' }}</view>
                        <view class="hero-card__no" @click="copyNo(detail.order_no)">
                            <text>{{ detail.order_no || '-' }}</text>
                            <text class="nc-iconfont nc-icon-fuzhiV6xx1 hero-card__copy"></text>
                        </view>
                    </view>
                    <view class="hero-card__count">{{ returnDevices.length }} 台设备</view>
                </view>
                <view class="hero-card__meta">
                    <text>创建 {{ formatTime(detail.create_at) }}</text>
                    <text v-if="detail.over_at">完成 {{ formatTime(detail.over_at) }}</text>
                </view>
            </view>

            <view class="section-card">
                <view class="section-card__title">退回信息</view>
                <view class="info-grid">
                    <view class="info-item">
                        <text class="info-label">快递公司</text>
                        <text class="info-value">{{ detail.express_company || '-' }}</text>
                    </view>
                    <view class="info-item" @click="copyNo(detail.express_no)">
                        <text class="info-label">快递单号</text>
                        <view class="info-value info-value--inline">
                            <text>{{ detail.express_no || '-' }}</text>
                            <text v-if="detail.express_no" class="nc-iconfont nc-icon-fuzhiV6xx1 info-copy"></text>
                        </view>
                    </view>
                    <view class="info-item">
                        <text class="info-label">收件人</text>
                        <text class="info-value">{{ receiverName }}</text>
                    </view>
                    <view class="info-item" @click="makePhoneCall(receiverMobile)">
                        <text class="info-label">手机号</text>
                        <view class="info-value info-value--inline">
                            <text>{{ receiverMobile }}</text>
                            <text v-if="receiverMobile !== '-'" class="nc-iconfont nc-icon-dianhuaV6xx info-phone"></text>
                        </view>
                    </view>
                </view>
                <view class="address-row">
                    <text class="info-label">{{ detail.return_address ? '本次退回地址' : '默认退货地址' }}</text>
                    <text class="address-value">{{ returnAddress }}</text>
                </view>
                <view v-if="defaultReceiverName !== '-' || defaultReceiverMobile !== '-'" class="address-row">
                    <text class="info-label">默认联系人</text>
                    <text class="address-value">{{ defaultReceiverName }} {{ defaultReceiverMobile }}</text>
                </view>
                <view v-if="detail.comment || detail.remark" class="address-row">
                    <text class="info-label">备注</text>
                    <text class="address-value">{{ detail.comment || detail.remark }}</text>
                </view>
                <view v-if="detail.express_no" class="info-link-row" @click="openExpressTrack">
                    <text class="info-link-row__label">物流轨迹</text>
                    <view class="info-link-row__value">
                        <text>查看物流</text>
                        <text class="nc-iconfont nc-icon-youV6xx1 info-link-row__icon"></text>
                    </view>
                </view>
            </view>

            <view class="section-card">
                <view class="section-card__header">
                    <view class="section-card__title">退回设备</view>
                    <view class="section-card__suffix">{{ returnDevices.length }} 台</view>
                </view>
                <view v-if="!returnDevices.length" class="empty-state">暂无退回设备</view>
                <view v-for="item in returnDevices" :key="item.id || item.device_id" class="device-card">
                    <view class="device-card__top">
                        <view class="device-card__model">{{ item.device?.model || item.model || '-' }}</view>
                        <text class="device-card__status">{{ item.status_name || item.device?.status_name || '-' }}</text>
                    </view>
                    <view class="device-card__meta">
                        <text>IMEI：{{ item.device?.imei || item.imei || '-' }}</text>
                        <text v-if="item.device?.final_price || item.final_price">报价：¥{{ formatMoney(item.device?.final_price || item.final_price) }}</text>
                    </view>
                    <view v-if="item.remark" class="device-card__remark">{{ item.remark }}</view>
                </view>
            </view>

            <view v-if="statusValue === 0" class="section-card">
                <view class="section-card__title">确认退货信息</view>
                <ReturnShipmentForm
                    ref="shipmentFormRef"
                    :detail="detail"
                    :order-id="detail.order_id || orderId"
                    :devices="returnDevices"
                />
            </view>

            <view class="bottom-space"></view>

            <view v-if="footerActions.length" class="action-footer">
                <view
                    v-for="action in footerActions"
                    :key="action.type"
                    class="footer-btn"
                    :class="{ 'footer-btn--primary': action.primary, 'footer-btn--danger': action.danger, 'footer-btn--disabled': submitting }"
                    @click="handleFooterAction(action.type)"
                >
                    {{ action.label }}
                </view>
            </view>
        </template>

        <CancelReturnPopup
            v-model:visible="cancelPopupVisible"
            ref="cancelPopupRef"
            @submit="handleCancelSubmit"
        />

        <CompleteReturnPopup
            v-model:visible="completePopupVisible"
            ref="completePopupRef"
            @submit="handleCompleteSubmit"
        />

        <ExpressTrackPopup
            v-model:visible="expressTrackVisible"
            :express-no="detail?.express_no || ''"
            :mobile="receiverMobile === '-' ? '' : receiverMobile"
            :company-name="detail?.express_company || ''"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import {
    cancelReturnOrder,
    confirmReturnOrder,
    deleteReturnOrder,
    getReturnOrderDetail,
    updateReturnOrderStatus
} from '@/addon/hsx_recycle/api/return-order'
import { formatMoney, formatTime, makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import { copy } from '@/utils/common'
import { useRecyclePrintActions } from '@/addon/hsx_recycle/hooks/useRecyclePrintActions'
import ReturnShipmentForm from '@/addon/hsx_recycle/pages/return/components/ReturnShipmentForm.vue'
import CancelReturnPopup from '@/addon/hsx_recycle/pages/return/components/CancelReturnPopup.vue'
import CompleteReturnPopup from '@/addon/hsx_recycle/pages/return/components/CompleteReturnPopup.vue'
import ExpressTrackPopup from '@/addon/hsx_recycle/components/ExpressTrackPopup.vue'

const loading = ref(true)
const detail = ref<any>(null)
const orderId = ref<number | string>('')
const submitting = ref(false)
const shipmentFormRef = ref<InstanceType<typeof ReturnShipmentForm> | null>(null)
const cancelPopupRef = ref<InstanceType<typeof CancelReturnPopup> | null>(null)
const completePopupRef = ref<InstanceType<typeof CompleteReturnPopup> | null>(null)
const cancelPopupVisible = ref(false)
const completePopupVisible = ref(false)
const expressTrackVisible = ref(false)
const {
    loadManualPrintActions,
    getVisiblePrintActions,
    executePrintAction
} = useRecyclePrintActions('return')

const statusValue = computed(() => Number(detail.value?.status || 0))
const returnDevices = computed(() => detail.value?.returnDevices || detail.value?.return_devices || [])
const memberAddressInfo = computed(() => {
    const item = detail.value?.memberAddress || detail.value?.member_address || {}
    return {
        name: item.name || '',
        mobile: item.mobile || '',
        address: [item.province_name, item.city_name, item.district_name, item.address].filter(Boolean).join('') || item.address || ''
    }
})
const receiverName = computed(() => detail.value?.member_name || detail.value?.member?.nickname || memberAddressInfo.value.name || '-')
const receiverMobile = computed(() => detail.value?.member_mobile || detail.value?.member?.mobile || memberAddressInfo.value.mobile || '-')
const defaultReceiverName = computed(() => memberAddressInfo.value.name || '-')
const defaultReceiverMobile = computed(() => memberAddressInfo.value.mobile || '-')
const returnAddress = computed(() => detail.value?.return_address || memberAddressInfo.value.address || '-')
const printActions = computed(() => getVisiblePrintActions(detail.value))
const printTarget = computed(() => ({
    return_order_id: detail.value?.id || orderId.value,
    order_id: detail.value?.order_id || '',
    biz_id: detail.value?.id || orderId.value
}))
const footerActions = computed(() => {
    const actions: Array<{ type: string, label: string, primary?: boolean, danger?: boolean }> = []
    if (statusValue.value === 0) actions.push({ type: 'confirm', label: '确认退货', primary: true })
    if (statusValue.value === 1) actions.push({ type: 'complete', label: '完成退货', primary: true })
    if (printActions.value.length) actions.push({ type: 'print', label: '打印' })
    if ([0, 1].includes(statusValue.value)) actions.push({ type: 'cancel', label: '取消退回单', danger: true })
    if (statusValue.value === 3) actions.push({ type: 'delete', label: '删除退回单', danger: true })
    return actions
})

const loadDetail = async () => {
    loading.value = true
    try {
        await loadManualPrintActions()
        const res: any = await getReturnOrderDetail(orderId.value)
        detail.value = res.data || null
    } finally {
        loading.value = false
    }
}

const handleFooterAction = async (type: string) => {
    if (submitting.value) return
    if (type === 'print') return openPrintActions()
    if (type === 'confirm') return submitConfirm()
    if (type === 'complete') return submitComplete()
    if (type === 'cancel') return submitCancel()
    if (type === 'delete') return submitDelete()
}

const handlePrintAction = async (action: any) => {
    if (submitting.value) return
    if (!action?.scene_key) {
        uni.showToast({ title: '打印场景不可用', icon: 'none' })
        return
    }
    await executePrintAction(action, printTarget.value)
}

const openPrintActions = async () => {
    const actions = printActions.value
    if (!actions.length) {
        uni.showToast({ title: '暂无可用打印场景', icon: 'none' })
        return
    }
    if (actions.length === 1) {
        await handlePrintAction(actions[0])
        return
    }

    uni.showActionSheet({
        itemList: actions.map((action: any) => action.button_text || action.scene_name || '打印'),
        success: async (res) => {
            const action = actions[res.tapIndex]
            if (!action) return
            await handlePrintAction(action)
        }
    })
}

const submitConfirm = async () => {
    uni.showModal({
        title: '确认退货',
        content: '确认后退回单进入退货中，后续可完成退货。',
        success: async (res) => {
            if (!res.confirm) return
            await runAction(async () => {
                const payload = await shipmentFormRef.value?.buildConfirmPayload()
                if (!payload) return
                await confirmReturnOrder(orderId.value, payload)
                uni.showToast({ title: '退货已确认', icon: 'success' })
                await loadDetail()
            })
        }
    })
}

const submitComplete = async () => {
    completePopupVisible.value = true
}

const handleCompleteSubmit = async (payload: { remark: string }) => {
    completePopupRef.value?.setSubmitting(true)
    await runAction(async () => {
        await updateReturnOrderStatus(orderId.value, {
            status: 2,
            comment: payload.remark
        })
        completePopupVisible.value = false
        uni.showToast({ title: '退货已完成', icon: 'success' })
        await loadDetail()
    })
    completePopupRef.value?.setSubmitting(false)
}

const submitCancel = async () => {
    cancelPopupVisible.value = true
}

const handleCancelSubmit = async (payload: { remark: string }) => {
    cancelPopupRef.value?.setSubmitting(true)
    await runAction(async () => {
        await cancelReturnOrder(orderId.value, payload.remark)
        cancelPopupVisible.value = false
        uni.showToast({ title: '退回单已取消', icon: 'success' })
        await loadDetail()
    })
    cancelPopupRef.value?.setSubmitting(false)
}

const submitDelete = async () => {
    uni.showModal({
        title: '删除退回单',
        content: '删除后不可恢复，确认删除？',
        confirmColor: '#dc2626',
        success: async (res) => {
            if (!res.confirm) return
            await runAction(async () => {
                await deleteReturnOrder(orderId.value)
                uni.showToast({ title: '退回单已删除', icon: 'success' })
                setTimeout(() => {
                    uni.navigateBack()
                }, 500)
            })
        }
    })
}

const runAction = async (handler: () => Promise<void>) => {
    submitting.value = true
    try {
        await handler()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '操作失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}

const copyNo = (value: string) => {
    if (!value) return
    copy(value)
}

const openExpressTrack = () => {
    if (!detail.value?.express_no) {
        uni.showToast({ title: '暂无快递单号', icon: 'none' })
        return
    }
    expressTrackVisible.value = true
}

onLoad((option: any) => {
    orderId.value = option?.id || ''
    if (orderId.value) loadDetail()
})
</script>

<style scoped lang="scss">
.detail-page {
    min-height: 100vh;
    background: #f5f7fa;
    padding: 20rpx;
}

.loading-box {
    padding: 160rpx 0;
    text-align: center;
    color: #909399;
    font-size: 26rpx;
}

.hero-card {
    padding: 28rpx;
    border-radius: 18rpx;
    background: #2563eb;
    color: #fff;
}

.hero-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.hero-card__status {
    font-size: 36rpx;
    font-weight: 800;
}

.hero-card__no {
    display: flex;
    align-items: center;
    margin-top: 10rpx;
    font-size: 23rpx;
    color: rgba(255, 255, 255, 0.82);
}

.hero-card__copy {
    margin-left: 8rpx;
}

.hero-card__count {
    flex-shrink: 0;
    padding: 10rpx 16rpx;
    border-radius: 999rpx;
    background: rgba(255, 255, 255, 0.18);
    font-size: 22rpx;
}

.hero-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10rpx 20rpx;
    margin-top: 18rpx;
    font-size: 22rpx;
    color: rgba(255, 255, 255, 0.78);
}

.section-card {
    margin-top: 18rpx;
    padding: 22rpx;
    border-radius: 16rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.section-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
}

.section-card__title {
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 700;
    color: #1f2937;
}

.section-card__header .section-card__title {
    margin-bottom: 0;
}

.section-card__suffix {
    font-size: 22rpx;
    color: #8c8c8c;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14rpx;
}

.info-item {
    min-width: 0;
    padding: 14rpx;
    border-radius: 12rpx;
    background: #f8fafc;
}

.info-label {
    display: block;
    font-size: 21rpx;
    color: #8c8c8c;
}

.info-value {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.info-value--inline {
    display: flex;
    align-items: center;
    gap: 8rpx;
}

.info-copy,
.info-phone {
    flex-shrink: 0;
    color: #2563eb;
    font-size: 24rpx;
}

.address-row {
    margin-top: 14rpx;
    padding: 14rpx;
    border-radius: 12rpx;
    background: #f8fafc;
}

.address-value {
    display: block;
    margin-top: 8rpx;
    font-size: 24rpx;
    line-height: 1.5;
    color: #1f2937;
}

.info-link-row {
    margin-top: 14rpx;
    padding: 18rpx 14rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.info-link-row__label {
    font-size: 23rpx;
    color: #8c8c8c;
}

.info-link-row__value {
    display: flex;
    align-items: center;
    gap: 8rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #2563eb;
}

.info-link-row__icon {
    font-size: 22rpx;
}

.empty-state {
    padding: 50rpx 0;
    text-align: center;
    color: #8c8c8c;
    font-size: 24rpx;
}

.device-card {
    padding: 18rpx 0;
    border-top: 1rpx solid #f1f5f9;
}

.device-card:first-of-type {
    border-top: 0;
    padding-top: 0;
}

.device-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.device-card__model {
    min-width: 0;
    font-size: 26rpx;
    font-weight: 700;
    color: #1f2937;
}

.device-card__status {
    flex-shrink: 0;
    font-size: 22rpx;
    color: #d97706;
}

.device-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 18rpx;
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
}

.device-card__remark {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.field-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    min-height: 78rpx;
    border-bottom: 1rpx solid #f1f5f9;
}

.field-label {
    width: 132rpx;
    flex-shrink: 0;
    font-size: 24rpx;
    color: #64748b;
}

.field-input {
    flex: 1;
    min-width: 0;
    font-size: 25rpx;
    color: #1f2937;
}

.field-scan {
    flex: 1;
    min-width: 0;
    height: 78rpx;
}

.field-scan :deep(.u-input),
.field-scan :deep(.u-input__content) {
    height: 78rpx;
    min-height: 78rpx;
    padding: 0 !important;
    background: transparent !important;
}

.field-scan :deep(.u-input__content__field-wrapper__field) {
    height: 78rpx;
    line-height: 78rpx;
    color: #1f2937;
}

.field-textarea {
    width: 100%;
    box-sizing: border-box;
    min-height: 118rpx;
    margin-top: 16rpx;
    padding: 18rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    font-size: 25rpx;
}

.bottom-space {
    height: 130rpx;
}

.action-footer {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 20;
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12rpx;
    padding: 16rpx 20rpx calc(16rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #e5e7eb;
    background: rgba(255, 255, 255, 0.98);
}

.footer-btn {
    min-height: 72rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1rpx solid #dbe2ea;
    color: #475569;
    font-size: 24rpx;
    font-weight: 600;
}

.footer-btn--primary {
    background: #2563eb;
    border-color: #2563eb;
    color: #fff;
}

.footer-btn--danger {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #dc2626;
}

.footer-btn--disabled {
    opacity: .55;
}
</style>
