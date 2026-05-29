<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="payment-popup">
            <view class="payment-header">
                <view>
                    <view class="payment-title">确认打款</view>
                    <view class="payment-subtitle">{{ isDeviceMode ? '按设备打款' : '整单打款' }}</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <view class="summary-card">
                <view class="summary-row">
                    <text class="summary-label">订单号</text>
                    <text class="summary-value">{{ orderData?.order_no || '-' }}</text>
                </view>
                <view class="summary-row">
                    <text class="summary-label">客户</text>
                    <text class="summary-value">{{ orderData?.sender_name || orderData?.customer_name || '-' }}</text>
                </view>
                <view class="summary-row">
                    <text class="summary-label">设备数</text>
                    <text class="summary-value">{{ devices.length }} 台</text>
                </view>
                <view class="summary-row">
                    <text class="summary-label">本次金额</text>
                    <text class="summary-value summary-value--price">¥{{ currentPayAmount }}</text>
                </view>
            </view>

            <view v-if="isDeviceMode" class="tip-card">
                <text>按设备流转模式下，可只选择本次需要结算的设备；未选择设备会继续保留在待打款状态。</text>
            </view>

            <scroll-view scroll-y class="payment-content">
                <view class="section">
                    <view class="section-title">收款方式</view>
                    <view v-if="paymentMethods.length" class="method-list">
                        <view
                            v-for="(item, index) in paymentMethods"
                            :key="`${ item.pay_type || 'custom' }-${ index }`"
                            class="method-chip"
                            :class="{ 'method-chip--active': selectedMethodIndex === index }"
                            @click="selectedMethodIndex = index"
                        >
                            {{ item.pay_type || `方式${ index + 1 }` }}
                        </view>
                    </view>
                    <view v-else class="empty-card">
                        <text>客户未维护收款方式，请手动填写本次打款信息。</text>
                    </view>

                    <view v-if="selectedMethod" class="method-detail">
                        <view class="method-detail__row">
                            <text class="method-detail__label">账户</text>
                            <text class="method-detail__value">{{ selectedMethod.account || '未填写' }}</text>
                        </view>
                        <image
                            v-if="selectedMethod.qrcode_image"
                            class="method-qrcode"
                            :src="selectedMethod.qrcode_image"
                            mode="aspectFit"
                            @click="previewSingleImage(selectedMethod.qrcode_image)"
                        />
                    </view>

                    <view v-if="needCustomFields" class="custom-form">
                        <view class="field">
                            <view class="field-label">支付方式</view>
                            <input v-model="customPayType" class="field-input" placeholder="如：微信转账 / 银行卡" />
                        </view>
                        <view class="field">
                            <view class="field-label">收款账号</view>
                            <input v-model="customAccount" class="field-input" placeholder="请输入收款账号" />
                        </view>
                    </view>
                </view>

                <view v-if="isDeviceMode" class="section">
                    <view class="section-title">
                        <text>待打款设备</text>
                        <text class="section-suffix">已选 {{ selectedDeviceIds.length }}/{{ payableDevices.length }}</text>
                    </view>

                    <view v-if="!payableDevices.length" class="empty-card">
                        <text>当前没有可打款设备。</text>
                    </view>

                    <view
                        v-for="device in payableDevices"
                        :key="device.id"
                        class="device-card"
                        :class="{ 'device-card--active': isDeviceSelected(device.id) }"
                        @click="toggleDevice(device.id)"
                    >
                        <view class="device-card__header">
                            <view class="device-card__title-wrap">
                                <view class="selector" :class="{ 'selector--active': isDeviceSelected(device.id) }"></view>
                                <view class="device-card__title">{{ device.model || '未知型号' }}</view>
                            </view>
                            <view class="device-card__price">{{ getDevicePaymentLabel(device) }}</view>
                        </view>
                        <view class="device-card__meta">
                            <text>IMEI：{{ device.imei || device.user_sn || '-' }}</text>
                        </view>
                        <view class="device-card__tags">
                            <text class="tag tag--status">{{ device.status_name || '-' }}</text>
                            <text class="tag tag--success">{{ device.pay_status_name || '未打款' }}</text>
                        </view>
                    </view>

                    <view v-if="blockedDevices.length" class="device-section-muted">
                        <view class="device-section-muted__title">暂不可打款</view>
                        <view v-for="device in blockedDevices" :key="`blocked-${ device.id }`" class="device-card device-card--muted">
                            <view class="device-card__header">
                                <view class="device-card__title">{{ device.model || '未知型号' }}</view>
                                <view class="device-card__price">{{ getDevicePaymentLabel(device) }}</view>
                            </view>
                            <view class="device-card__meta">
                                <text>{{ device.imei || device.user_sn || '-' }}</text>
                            </view>
                            <view class="device-card__reason">{{ device.pay_disabled_reason || device.disabled_reason || '当前不可打款' }}</view>
                        </view>
                    </view>
                </view>

                <view class="section">
                    <view class="section-title">
                        <text>打款凭证</text>
                        <text class="section-suffix">{{ paymentImageCount }}/9</text>
                    </view>
                    <RecycleImageUploader
                        v-model="paymentImages"
                        add-text="上传凭证"
                        fail-text="凭证上传失败"
                        :max-count="9"
                        :multiple="true"
                        @uploading="imageUploading = $event"
                    />
                </view>

                <view class="section section--remark">
                    <view class="section-title">打款备注</view>
                    <u-textarea
                        v-model="remark"
                        placeholder="选填，如交易流水号、到账说明"
                        :maxlength="200"
                        :height="120"
                        count
                    ></u-textarea>
                </view>
            </scroll-view>

            <view class="payment-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1, marginRight: '20rpx' }">取消</u-button>
                <u-button type="primary" :loading="submitting" :customStyle="{ flex: 2 }" @click="handleSubmit">
                    确认打款 ¥{{ currentPayAmount }}
                </u-button>
            </view>
        </view>
    </u-popup>

    <ImagePreviewOverlay
        v-model:visible="previewVisible"
        :urls="previewUrls"
        :current="previewCurrent"
        @change="previewCurrent = $event"
    />
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { devicePaymentConfirm, getMerchantPayInfo, paymentConfirm } from '@/addon/hsx_recycle/api/order'
import { img } from '@/utils/common'
import ImagePreviewOverlay from '@/addon/hsx_recycle/components/ImagePreviewOverlay.vue'
import RecycleImageUploader from '@/addon/hsx_recycle/components/RecycleImageUploader.vue'
import { formatMoney } from '@/addon/hsx_recycle/utils/helper'
import { getDeviceSettlementAmount, isConsignedDevice } from '@/addon/hsx_recycle/utils/device'

interface Props {
    visible: boolean
    orderData: any
    devices: any[]
}

interface PaymentMethodItem {
    pay_type?: string
    account?: string
    qrcode_image?: string
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
const remark = ref('')
const paymentMethods = ref<PaymentMethodItem[]>([])
const selectedMethodIndex = ref(0)
const customPayType = ref('')
const customAccount = ref('')
const selectedDeviceIds = ref<Array<number | string>>([])
const paymentImages = ref('')
const imageUploading = ref(false)
const previewVisible = ref(false)
const previewUrls = ref<string[]>([])
const previewCurrent = ref(0)

const devices = computed(() => Array.isArray(props.devices) ? props.devices : [])
const isDeviceMode = computed(() => (props.orderData?.flow_mode || props.orderData?.payment_mode) === 'device')
const paymentImageCount = computed(() => paymentImages.value ? paymentImages.value.split(',').filter(Boolean).length : 0)

const selectedMethod = computed(() => {
    if (!paymentMethods.value.length) return null
    return paymentMethods.value[selectedMethodIndex.value] || paymentMethods.value[0]
})

const needCustomFields = computed(() => {
    return !selectedMethod.value || !selectedMethod.value.qrcode_image || selectedMethod.value.pay_type === '自定义'
})

const payableDevices = computed(() => {
    return devices.value.filter((device: any) => Boolean(device.can_pay))
})

const blockedDevices = computed(() => {
    return devices.value.filter((device: any) => !device.can_pay && Number(device.pay_status || 0) !== 1)
})

const currentPayAmount = computed(() => {
    const targetDevices = isDeviceMode.value
        ? payableDevices.value.filter((device: any) => selectedDeviceIds.value.includes(device.id))
        : devices.value.filter((device: any) => Boolean(device.can_pay))

    return formatMoney(targetDevices.reduce((sum: number, device: any) => {
        return sum + getDeviceSettlementAmount(device)
    }, 0))
})

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        initPopup()
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const initPopup = async () => {
    submitting.value = false
    remark.value = ''
    customPayType.value = ''
    customAccount.value = ''
    paymentImages.value = ''
    imageUploading.value = false
    selectedMethodIndex.value = 0
    selectedDeviceIds.value = payableDevices.value.map((device: any) => device.id)
    await loadPaymentMethods()
}

const loadPaymentMethods = async () => {
    paymentMethods.value = []
    const memberId = props.orderData?.member_id
    if (!memberId) return
    try {
        const res: any = await getMerchantPayInfo(memberId)
        const list = Array.isArray(res?.data) ? res.data : []
        paymentMethods.value = list.map((item: any) => ({
            ...item,
            qrcode_image: item.qrcode_image ? img(item.qrcode_image) : ''
        }))
    } catch (error) {
        paymentMethods.value = []
    }
}

const getPaymentPayload = () => {
    const payType = (needCustomFields.value ? customPayType.value : (selectedMethod.value?.pay_type || customPayType.value)).trim()
    const account = (needCustomFields.value ? customAccount.value : (selectedMethod.value?.account || customAccount.value)).trim()

    return {
        payType,
        account,
        paymentImages: paymentImages.value,
        payload: {
            pay_type: payType,
            pay_account: account,
            account,
            remark: remark.value.trim(),
            payment_images: paymentImages.value,
            payment_info: {
                pay_type: payType,
                account,
                remark: remark.value.trim(),
                payment_images: paymentImages.value
            }
        }
    }
}

const handleSubmit = () => {
    const { payType } = getPaymentPayload()
    if (!payType) {
        uni.showToast({ title: '请填写支付方式', icon: 'none' })
        return
    }

    if (imageUploading.value) {
        uni.showToast({ title: '打款凭证上传中，请稍候', icon: 'none' })
        return
    }

    if (isDeviceMode.value && !selectedDeviceIds.value.length) {
        uni.showToast({ title: '请选择需要打款的设备', icon: 'none' })
        return
    }

    uni.showModal({
        title: '确认打款',
        content: `确认本次已打款 ¥${ currentPayAmount.value } 吗？`,
        success: async (res) => {
            if (!res.confirm) return
            await submitPayment()
        }
    })
}

const submitPayment = async () => {
    if (!props.orderData?.id) {
        uni.showToast({ title: '订单数据异常，请刷新后重试', icon: 'none' })
        return
    }

    submitting.value = true
    try {
        const { payload } = getPaymentPayload()
        if (isDeviceMode.value) {
            await devicePaymentConfirm(props.orderData.id, {
                ...payload,
                device_ids: selectedDeviceIds.value
            })
        } else {
            await paymentConfirm(props.orderData.id, payload)
        }

        uni.showToast({ title: '打款确认成功', icon: 'success' })
        emit('success')
        handleClose()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '打款失败', icon: 'none' })
    } finally {
        submitting.value = false
    }
}

const isDeviceSelected = (deviceId: number | string) => selectedDeviceIds.value.includes(deviceId)

const toggleDevice = (deviceId: number | string) => {
    if (!isDeviceMode.value) return
    if (isDeviceSelected(deviceId)) {
        selectedDeviceIds.value = selectedDeviceIds.value.filter(id => id !== deviceId)
    } else {
        selectedDeviceIds.value = [...selectedDeviceIds.value, deviceId]
    }
}

const previewSingleImage = (url: string) => {
    if (!url) return
    previewUrls.value = [url]
    previewCurrent.value = 0
    previewVisible.value = true
}

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}

const getDevicePaymentLabel = (device: any) => {
    if (isConsignedDevice(device)) {
        return device.consignmentOrder?.status_name || device.dispose_status_name || '已转代卖'
    }

    const amount = getDeviceSettlementAmount(device)
    if (amount > 0) return `¥${ formatMoney(amount) }`
    return '待定价'
}
</script>

<style scoped lang="scss">
.payment-popup {
    background: #fff;
    height: 88vh;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.payment-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    padding: 30rpx;
    border-bottom: 1rpx solid #f2f3f5;
    flex-shrink: 0;
}

.payment-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1f2937;
}

.payment-subtitle {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}

.summary-card,
.tip-card {
    margin: 20rpx 30rpx 0;
    border-radius: 16rpx;
    flex-shrink: 0;
}

.summary-card {
    padding: 22rpx 24rpx;
    background: linear-gradient(135deg, #eff6ff, #eef2ff);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8rpx 0;
    font-size: 24rpx;
}

.summary-label {
    color: #6b7280;
}

.summary-value {
    color: #1f2937;
    font-weight: 500;
}

.summary-value--price {
    color: #ea580c;
    font-size: 30rpx;
}

.tip-card {
    padding: 18rpx 22rpx;
    background: #fff7ed;
    color: #c2410c;
    font-size: 24rpx;
    line-height: 1.6;
}

.payment-content {
    flex: 1;
    height: 0;
    min-height: 0;
    padding: 20rpx 30rpx 0;
    box-sizing: border-box;
    overflow: hidden;
}

.section {
    margin-bottom: 28rpx;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
}

.section-suffix {
    font-size: 22rpx;
    color: #8c8c8c;
    font-weight: 400;
}

.method-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.method-chip {
    padding: 14rpx 24rpx;
    border-radius: 999rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 24rpx;
    border: 2rpx solid transparent;
}

.method-chip--active {
    background: #eff6ff;
    color: #2563eb;
    border-color: #93c5fd;
}

.method-detail,
.custom-form,
.empty-card,
.device-card {
    border-radius: 16rpx;
    background: #f8fafc;
}

.method-detail,
.custom-form,
.empty-card {
    margin-top: 18rpx;
    padding: 20rpx 22rpx;
}

.method-detail__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    font-size: 24rpx;
}

.method-detail__label {
    color: #64748b;
}

.method-detail__value {
    color: #0f172a;
    flex: 1;
    text-align: right;
    word-break: break-all;
}

.method-qrcode {
    width: 240rpx;
    height: 240rpx;
    margin-top: 20rpx;
    border-radius: 12rpx;
    background: #fff;
}

.field + .field {
    margin-top: 18rpx;
}

.field-label {
    margin-bottom: 10rpx;
    font-size: 24rpx;
    color: #475569;
}

.field-input {
    height: 78rpx;
    padding: 0 20rpx;
    border-radius: 12rpx;
    background: #fff;
    border: 1rpx solid #dbe2ea;
    font-size: 26rpx;
    box-sizing: border-box;
}

.empty-card {
    font-size: 24rpx;
    color: #8c8c8c;
    line-height: 1.6;
}

.device-card {
    padding: 20rpx 22rpx;
}

.device-card + .device-card {
    margin-top: 16rpx;
}

.device-card--active {
    background: #eff6ff;
    box-shadow: inset 0 0 0 2rpx #93c5fd;
}

.device-card--muted {
    background: #f8fafc;
}

.device-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.device-card__title-wrap {
    display: flex;
    align-items: center;
    gap: 12rpx;
    flex: 1;
    min-width: 0;
}

.selector {
    width: 30rpx;
    height: 30rpx;
    border-radius: 50%;
    border: 2rpx solid #cbd5e1;
    background: #fff;
    flex-shrink: 0;
}

.selector--active {
    border-color: #2563eb;
    background: #2563eb;
    box-shadow: inset 0 0 0 6rpx #fff;
}

.device-card__title {
    font-size: 26rpx;
    font-weight: 600;
    color: #1f2937;
}

.device-card__price {
    font-size: 28rpx;
    font-weight: 700;
    color: #ea580c;
}

.device-card__meta {
    margin-top: 12rpx;
    font-size: 22rpx;
    color: #64748b;
    word-break: break-all;
}

.device-card__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 10rpx;
    margin-top: 14rpx;
}

.tag {
    padding: 6rpx 14rpx;
    border-radius: 999rpx;
    font-size: 20rpx;
}

.tag--status {
    background: #e0f2fe;
    color: #0369a1;
}

.tag--success {
    background: #ecfdf5;
    color: #047857;
}

.device-section-muted {
    margin-top: 20rpx;
}

.device-section-muted__title {
    margin-bottom: 12rpx;
    font-size: 24rpx;
    color: #8c8c8c;
}

.device-card__reason {
    margin-top: 12rpx;
    font-size: 22rpx;
    color: #ef4444;
    line-height: 1.5;
}

.section--remark {
    padding-bottom: 16rpx;
}

.payment-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
    background: #fff;
    flex-shrink: 0;
}
</style>
