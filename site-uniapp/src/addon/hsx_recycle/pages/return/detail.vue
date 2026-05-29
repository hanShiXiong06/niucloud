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
                    <text class="info-label">退回地址</text>
                    <text class="address-value">{{ returnAddress }}</text>
                </view>
                <view v-if="detail.comment || detail.remark" class="address-row">
                    <text class="info-label">备注</text>
                    <text class="address-value">{{ detail.comment || detail.remark }}</text>
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
                <view class="field-row">
                    <text class="field-label">快递公司</text>
                    <input v-model="confirmForm.express_company" class="field-input" placeholder="请输入快递公司" />
                </view>
                <view class="field-row">
                    <text class="field-label">快递单号</text>
                    <input v-model="confirmForm.express_no" class="field-input" placeholder="请输入快递单号" />
                </view>
                <view class="field-row">
                    <text class="field-label">收件人</text>
                    <input v-model="confirmForm.member_name" class="field-input" placeholder="请输入收件人姓名" />
                </view>
                <view class="field-row">
                    <text class="field-label">手机号</text>
                    <input v-model="confirmForm.member_mobile" class="field-input" placeholder="请输入收件人手机号" />
                </view>
                <textarea v-model="confirmForm.return_address" class="field-textarea" placeholder="退回地址" />
                <textarea v-model="confirmForm.remark" class="field-textarea" placeholder="备注（选填）" />
            </view>

            <view v-if="statusValue === 1" class="section-card">
                <view class="section-card__title">完成退货</view>
                <textarea v-model="completeComment" class="field-textarea" placeholder="备注（选填）" />
            </view>

            <view v-if="[0, 1].includes(statusValue)" class="section-card">
                <view class="section-card__title">取消退回单</view>
                <textarea v-model="cancelComment" class="field-textarea" placeholder="请输入取消原因" />
            </view>

            <view class="bottom-space"></view>

            <view v-if="footerActions.length" class="action-footer">
                <view
                    v-for="action in footerActions"
                    :key="action.type"
                    class="footer-btn"
                    :class="{ 'footer-btn--primary': action.primary, 'footer-btn--danger': action.danger }"
                    @click="handleFooterAction(action.type)"
                >
                    {{ action.label }}
                </view>
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
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

const loading = ref(true)
const detail = ref<any>(null)
const orderId = ref<number | string>('')
const completeComment = ref('')
const cancelComment = ref('')

const confirmForm = reactive({
    express_company: '',
    express_no: '',
    member_mobile: '',
    member_name: '',
    return_address: '',
    remark: ''
})

const statusValue = computed(() => Number(detail.value?.status || 0))
const returnDevices = computed(() => detail.value?.returnDevices || detail.value?.return_devices || [])
const receiverName = computed(() => detail.value?.member_name || detail.value?.member?.nickname || '-')
const receiverMobile = computed(() => detail.value?.member_mobile || detail.value?.member?.mobile || '-')
const memberAddress = computed(() => {
    const item = detail.value?.memberAddress || detail.value?.member_address || {}
    return [item.province_name, item.city_name, item.district_name, item.address].filter(Boolean).join('')
})
const returnAddress = computed(() => detail.value?.return_address || memberAddress.value || '-')
const footerActions = computed(() => {
    const actions: Array<{ type: string, label: string, primary?: boolean, danger?: boolean }> = []
    if (statusValue.value === 0) actions.push({ type: 'confirm', label: '确认退货', primary: true })
    if (statusValue.value === 1) actions.push({ type: 'complete', label: '完成退货', primary: true })
    if ([0, 1].includes(statusValue.value)) actions.push({ type: 'cancel', label: '取消退回单', danger: true })
    if (statusValue.value === 3) actions.push({ type: 'delete', label: '删除退回单', danger: true })
    return actions
})

const loadDetail = async () => {
    loading.value = true
    try {
        const res: any = await getReturnOrderDetail(orderId.value)
        detail.value = res.data || null
        confirmForm.express_company = detail.value?.express_company || ''
        confirmForm.express_no = detail.value?.express_no || ''
        confirmForm.member_mobile = detail.value?.member_mobile || detail.value?.member?.mobile || ''
        confirmForm.member_name = detail.value?.member_name || detail.value?.member?.nickname || ''
        confirmForm.return_address = detail.value?.return_address || memberAddress.value
    } finally {
        loading.value = false
    }
}

const handleFooterAction = async (type: string) => {
    if (type === 'confirm') return submitConfirm()
    if (type === 'complete') return submitComplete()
    if (type === 'cancel') return submitCancel()
    if (type === 'delete') return submitDelete()
}

const submitConfirm = async () => {
    await confirmReturnOrder(orderId.value, confirmForm)
    uni.showToast({ title: '退货已确认', icon: 'none' })
    loadDetail()
}

const submitComplete = async () => {
    await updateReturnOrderStatus(orderId.value, {
        status: 2,
        comment: completeComment.value
    })
    uni.showToast({ title: '退货已完成', icon: 'none' })
    loadDetail()
}

const submitCancel = async () => {
    await cancelReturnOrder(orderId.value, cancelComment.value)
    uni.showToast({ title: '退回单已取消', icon: 'none' })
    loadDetail()
}

const submitDelete = async () => {
    await deleteReturnOrder(orderId.value)
    uni.showToast({ title: '退回单已删除', icon: 'none' })
    setTimeout(() => {
        uni.navigateBack()
    }, 500)
}

const copyNo = (value: string) => {
    if (!value) return
    copy(value)
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
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
</style>
