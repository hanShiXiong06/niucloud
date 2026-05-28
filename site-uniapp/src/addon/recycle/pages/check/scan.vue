<template>
    <view class="scan-check-page">
        <view class="hero">
            <view class="hero__title">扫码处理</view>
            <view class="hero__subtitle">扫码后按设备状态自动打开质检或定价，无需进入订单详情。</view>
        </view>

        <view class="scan-panel">
            <view class="scan-button" @click="scanDevice">
                <view class="scan-button__icon">扫</view>
                <view>
                    <view class="scan-button__title">扫描设备码</view>
                    <view class="scan-button__desc">支持链接二维码或纯设备 ID</view>
                </view>
            </view>

            <view class="manual-box">
                <view class="manual-box__title">手动输入</view>
                <view class="manual-row">
                    <input
                        v-model="manualText"
                        class="manual-input"
                        placeholder="输入设备ID或粘贴二维码内容"
                        confirm-type="search"
                        @confirm="handleManualSubmit"
                    />
                    <view class="manual-btn" @click="handleManualSubmit">进入</view>
                </view>
            </view>
        </view>

        <view v-if="loading" class="state-card">正在加载设备...</view>

        <view v-if="deviceData?.id" class="device-card">
            <view class="device-card__head">
                <view>
                    <view class="device-card__title">{{ deviceData.model || '未知设备' }}</view>
                    <view class="device-card__meta">IMEI：{{ deviceData.imei || deviceData.user_sn || '-' }}</view>
                </view>
                <view class="device-card__status">{{ deviceData.status_name || '-' }}</view>
            </view>
            <view class="device-card__info">
                <text>订单：{{ deviceData.order?.order_no || deviceData.order_id || '-' }}</text>
                <text>客户：{{ customerName }}</text>
            </view>
            <view class="device-card__actions">
                <view class="device-card__btn" @click="openDeviceAction">{{ primaryActionText }}</view>
                <view class="device-card__btn device-card__btn--ghost" @click="scanDevice">下一台</view>
            </view>
        </view>

        <view class="tips-card">
            <view class="tips-card__title">使用说明</view>
            <view class="tips-card__line">二维码内容可以是完整链接，例如 host + url + ?id=123。</view>
            <view class="tips-card__line">也可以直接是设备 ID，例如 123。</view>
            <view class="tips-card__line">设备待质检时打开质检；设备已质检或待确认时打开定价。</view>
            <view class="tips-card__line">提交成功后会保留在扫码处理台，可继续扫描下一台。</view>
        </view>

        <CheckDevicePopup
            v-model:visible="checkPopupVisible"
            :deviceData="deviceData"
            @success="handleActionSuccess('质检')"
        />

        <PriceDevicePopup
            v-model:visible="pricePopupVisible"
            :deviceData="deviceData"
            @success="handleActionSuccess('定价')"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getDevice } from '@/addon/recycle/api/order'
import CheckDevicePopup from '@/addon/recycle/pages/order/components/CheckDevicePopup.vue'
import PriceDevicePopup from '@/addon/recycle/pages/order/components/PriceDevicePopup.vue'

const manualText = ref('')
const deviceData = ref<any>(null)
const loading = ref(false)
const checkPopupVisible = ref(false)
const pricePopupVisible = ref(false)
const loadedDeviceId = ref('')
let openedFromQuery = false

const customerName = computed(() => {
    return deviceData.value?.order?.member?.nickname
        || deviceData.value?.order?.member?.username
        || deviceData.value?.order?.customer_name
        || deviceData.value?.order?.sender_name
        || '-'
})

const primaryActionText = computed(() => {
    const action = getDeviceActionType()
    if (action === 'check') {
        return deviceData.value?.check_result_seller || deviceData.value?.check_result ? '继续质检' : '开始质检'
    }
    if (action === 'price') return '扫码定价'
    return '查看设备'
})

onLoad((option: any) => {
    const id = parseDeviceId(option?.id || option?.device_id || option?.scene || '')
    if (id) {
        openedFromQuery = true
        loadDevice(id, true)
    }
})

onShow(() => {
    if (!openedFromQuery && !deviceData.value?.id) {
        openedFromQuery = true
    }
})

const scanDevice = () => {
    uni.scanCode({
        onlyFromCamera: false,
        scanType: ['qrCode', 'barCode'],
        success: (res) => {
            const id = parseDeviceId(res.result || '')
            if (!id) {
                uni.showToast({ title: '未识别到设备ID', icon: 'none' })
                return
            }
            loadDevice(id, true)
        },
        fail: (error: any) => {
            if (String(error?.errMsg || '').includes('cancel')) return
            uni.showToast({ title: '扫码失败', icon: 'none' })
        }
    })
}

const handleManualSubmit = () => {
    const id = parseDeviceId(manualText.value)
    if (!id) {
        uni.showToast({ title: '请输入有效设备ID', icon: 'none' })
        return
    }
    loadDevice(id, true)
}

const loadDevice = async (id: string, autoOpen = false) => {
    loading.value = true
    try {
        const res: any = await getDevice(id)
        deviceData.value = res?.data || null
        loadedDeviceId.value = String(deviceData.value?.id || id)
        manualText.value = loadedDeviceId.value
        if (!deviceData.value?.id) {
            uni.showToast({ title: '设备不存在', icon: 'none' })
            return
        }
        if (autoOpen) openDeviceAction()
    } catch (error: any) {
        deviceData.value = null
        uni.showToast({ title: error?.msg || error?.message || '获取设备失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const openDeviceAction = () => {
    if (!deviceData.value?.id) {
        uni.showToast({ title: '请先选择设备', icon: 'none' })
        return
    }
    const action = getDeviceActionType()
    if (action === 'check') {
        checkPopupVisible.value = true
        return
    }
    if (action === 'price') {
        pricePopupVisible.value = true
        return
    }
    uni.showToast({ title: getUnsupportedActionText(), icon: 'none' })
}

const handleActionSuccess = async (actionName: string) => {
    const id = loadedDeviceId.value || String(deviceData.value?.id || '')
    if (id) {
        await loadDevice(id, false)
    }
    uni.showModal({
        title: `${ actionName }已提交`,
        content: '是否继续扫描下一台设备？',
        confirmText: '继续扫码',
        cancelText: '留在当前',
        success: (res) => {
            if (res.confirm) {
                scanDevice()
            }
        }
    })
}

const getDeviceActionType = () => {
    const status = Number(deviceData.value?.status || 0)
    if ([1, 2].includes(status)) return 'check'
    if ([3, 4, 7, 8].includes(status) && status !== 6 && status !== 9) return 'price'
    return ''
}

const getUnsupportedActionText = () => {
    const statusName = deviceData.value?.status_name || '当前状态'
    if (Number(deviceData.value?.status || 0) === 5) return '设备已回收，无需定价'
    if (Number(deviceData.value?.status || 0) === 6) return '设备已退回，不能继续处理'
    if (Number(deviceData.value?.status || 0) === 9 || deviceData.value?.dispose_type === 'consign') return '设备已转代卖，请到代卖模块处理'
    return `${ statusName }暂不支持扫码处理`
}

const parseDeviceId = (value: any) => {
    const text = decodeURIComponent(String(value || '').trim())
    if (!text) return ''

    const sceneId = parseSceneValue(text)
    if (sceneId) return sceneId

    const directMatch = text.match(/^\d+$/)
    if (directMatch) return directMatch[0]

    const queryMatch = text.match(/[?&](?:id|device_id)=([^&#]+)/i)
    if (queryMatch?.[1]) return queryMatch[1].match(/\d+/)?.[0] || ''

    const pathMatch = text.match(/(?:recycle_device|device|check)[/=-](\d+)/i)
    if (pathMatch?.[1]) return pathMatch[1]

    const fallback = text.match(/\d+/)
    return fallback?.[0] || ''
}

const parseSceneValue = (value: string) => {
    const sceneMatch = value.match(/(?:^|[?&])scene=([^&#]+)/i)
    if (!sceneMatch?.[1]) return ''
    const scene = decodeURIComponent(sceneMatch[1])
    const idMatch = scene.match(/(?:id|device_id)[=:](\d+)/i)
    return idMatch?.[1] || scene.match(/\d+/)?.[0] || ''
}
</script>

<style scoped lang="scss">
.scan-check-page {
    min-height: 100vh;
    padding: 24rpx;
    background: #f5f7fa;
    box-sizing: border-box;
}

.hero {
    padding: 32rpx 28rpx;
    border-radius: 24rpx;
    color: #fff;
    background: linear-gradient(135deg, #0f766e, #14b8a6);
    box-shadow: 0 20rpx 50rpx rgba(20, 184, 166, 0.18);
}

.hero__title {
    font-size: 38rpx;
    font-weight: 700;
}

.hero__subtitle {
    margin-top: 12rpx;
    font-size: 24rpx;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.86);
}

.scan-panel,
.device-card,
.tips-card,
.state-card {
    margin-top: 24rpx;
    padding: 26rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 30rpx rgba(15, 23, 42, 0.05);
}

.scan-button {
    display: flex;
    align-items: center;
    gap: 20rpx;
    padding: 26rpx;
    border-radius: 18rpx;
    background: #ecfeff;
    border: 2rpx solid #99f6e4;
}

.scan-button__icon {
    width: 76rpx;
    height: 76rpx;
    border-radius: 20rpx;
    background: #0f766e;
    color: #fff;
    font-size: 30rpx;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.scan-button__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
}

.scan-button__desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
}

.manual-box {
    margin-top: 24rpx;
}

.manual-box__title {
    margin-bottom: 14rpx;
    font-size: 26rpx;
    font-weight: 600;
    color: #334155;
}

.manual-row {
    display: flex;
    align-items: center;
    gap: 14rpx;
}

.manual-input {
    flex: 1;
    height: 76rpx;
    padding: 0 20rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    border: 1rpx solid #e2e8f0;
    font-size: 24rpx;
    box-sizing: border-box;
}

.manual-btn,
.device-card__btn {
    min-width: 132rpx;
    height: 76rpx;
    border-radius: 14rpx;
    background: #2563eb;
    color: #fff;
    font-size: 24rpx;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.state-card {
    text-align: center;
    font-size: 24rpx;
    color: #64748b;
}

.device-card__head {
    display: flex;
    justify-content: space-between;
    gap: 20rpx;
}

.device-card__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
}

.device-card__meta,
.device-card__info,
.tips-card__line {
    margin-top: 10rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.6;
}

.device-card__status {
    color: #2563eb;
    font-size: 22rpx;
    flex-shrink: 0;
}

.device-card__info {
    display: flex;
    flex-direction: column;
}

.device-card__actions {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16rpx;
    margin-top: 24rpx;
}

.device-card__btn {
    min-width: 0;
}

.device-card__btn--ghost {
    background: #f8fafc;
    color: #334155;
    border: 1rpx solid #dbe2ea;
}

.tips-card__title {
    font-size: 26rpx;
    font-weight: 700;
    color: #334155;
}
</style>
