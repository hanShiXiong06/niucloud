<template>
    <view class="scan-check-page">
        <view class="hero">
            <view class="hero__title">{{ currentMode.title }}</view>
            <view class="hero__subtitle">{{ currentMode.desc }}</view>
        </view>

        <view class="scan-panel">
            <view class="mode-tabs">
                <view
                    v-for="item in scanModes"
                    :key="item.value"
                    class="mode-tab"
                    :class="{ 'mode-tab--active': scanMode === item.value }"
                    @click="switchMode(item.value)"
                >
                    {{ item.label }}
                </view>
            </view>

            <view class="scan-button" @click="scanDevice">
                <view class="scan-button__icon">扫</view>
                <view>
                    <view class="scan-button__title">扫描设备码 / IMEI</view>
                    <view class="scan-button__desc">支持设备 ID、IMEI、SN 或链接二维码</view>
                </view>
            </view>

            <view class="manual-box">
                <view class="manual-box__title">手动输入</view>
                <view class="manual-row">
                    <input
                        v-model="manualText"
                        class="manual-input"
                        placeholder="输入设备ID、IMEI、SN或粘贴二维码内容"
                        confirm-type="search"
                        @confirm="handleManualSubmit"
                    />
                    <view class="manual-btn" @click="handleManualSubmit">查询</view>
                </view>
            </view>
        </view>

        <view v-if="loading" class="state-card">正在查询设备...</view>

        <view v-if="candidateDevices.length > 1" class="candidate-card">
            <view class="candidate-card__head">
                <view>
                    <view class="candidate-card__title">选择设备记录</view>
                    <view class="candidate-card__desc">同一串码存在多条记录，请确认订单和时间后进入。</view>
                </view>
                <view class="candidate-card__count">{{ candidateDevices.length }} 条</view>
            </view>
            <view class="candidate-list">
                <view
                    v-for="item in candidateDevices"
                    :key="item.id"
                    class="candidate-item"
                    @click="selectCandidate(item)"
                >
                    <view class="candidate-item__main">
                        <view class="candidate-item__title">{{ item.model || '未知设备' }}</view>
                        <view class="candidate-item__meta">IMEI：{{ item.imei || item.user_sn || item.sn || '-' }}</view>
                        <view class="candidate-item__meta">订单：{{ item.order_no || item.order_id || '-' }}</view>
                        <view class="candidate-item__meta">客户：{{ formatCustomer(item) }}</view>
                    </view>
                    <view class="candidate-item__side">
                        <view class="candidate-item__status">{{ item.status_name || '-' }}</view>
                        <view v-if="item.milestone_label" class="candidate-item__time">
                            {{ item.milestone_label }} {{ formatScanTime(item.milestone_time) }}
                        </view>
                    </view>
                </view>
            </view>
        </view>

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
                <view class="device-card__btn device-card__btn--ghost" @click="openOrderDetail">查看订单</view>
                <view class="device-card__btn device-card__btn--ghost" @click="scanDevice">下一台</view>
            </view>
        </view>

        <view class="tips-card">
            <view class="tips-card__title">当前模式</view>
            <view class="tips-card__line">{{ currentMode.tip }}</view>
            <view class="tips-card__line">同一 IMEI 查到多台时，会先展示订单、客户和关键时间供选择。</view>
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
import { getDevice, scanSearchDevice } from '@/addon/hsx_recycle/api/order'
import { redirect } from '@/utils/common'
import CheckDevicePopup from '@/addon/hsx_recycle/pages/order/components/CheckDevicePopup.vue'
import PriceDevicePopup from '@/addon/hsx_recycle/pages/order/components/PriceDevicePopup.vue'
import { useRecyclePrintActions } from '@/addon/hsx_recycle/hooks/useRecyclePrintActions'

type ScanMode = 'process' | 'query' | 'label'

const manualText = ref('')
const deviceData = ref<any>(null)
const candidateDevices = ref<any[]>([])
const loading = ref(false)
const checkPopupVisible = ref(false)
const pricePopupVisible = ref(false)
const loadedDeviceId = ref('')
const scanMode = ref<ScanMode>('process')
let openedFromQuery = false

const {
    loadManualPrintActions,
    getVisiblePrintActions,
    executePrintAction
} = useRecyclePrintActions('device')

const scanModes: Array<{ value: ScanMode, label: string, title: string, desc: string, tip: string }> = [
    {
        value: 'process',
        label: '扫码处理',
        title: '扫码处理',
        desc: '扫码后按设备状态自动打开质检或定价。',
        tip: '待质检打开质检，已质检/待确认打开定价。'
    },
    {
        value: 'query',
        label: '扫码查询',
        title: '扫码查询',
        desc: '扫码后定位设备所在订单，适合核对历史记录。',
        tip: '单台设备直接进入订单详情，多台设备先选择记录。'
    },
    {
        value: 'label',
        label: '扫码打标',
        title: '扫码打标',
        desc: '扫码后按设备状态匹配可用打印/打标动作。',
        tip: '打标动作来自后台打印场景配置，没有可用动作时会进入订单详情。'
    }
]

const currentMode = computed(() => scanModes.find((item) => item.value === scanMode.value) || scanModes[0])

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
    if (['process', 'query', 'label'].includes(String(option?.mode || ''))) {
        scanMode.value = String(option.mode) as ScanMode
    }
    loadManualPrintActions()

    const keyword = parseScanKeyword(option?.id || option?.device_id || option?.imei || option?.scene || '')
    if (keyword) {
        openedFromQuery = true
        resolveScanKeyword(keyword, true)
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
            const keyword = parseScanKeyword(res.result || '')
            if (!keyword) {
                uni.showToast({ title: '未识别到设备码', icon: 'none' })
                return
            }
            resolveScanKeyword(keyword, true)
        },
        fail: (error: any) => {
            if (String(error?.errMsg || '').includes('cancel')) return
            uni.showToast({ title: '扫码失败', icon: 'none' })
        }
    })
}

const handleManualSubmit = () => {
    const keyword = parseScanKeyword(manualText.value)
    if (!keyword) {
        uni.showToast({ title: '请输入有效设备码', icon: 'none' })
        return
    }
    resolveScanKeyword(keyword, true)
}

const switchMode = (value: ScanMode) => {
    if (scanMode.value === value) return
    scanMode.value = value
    candidateDevices.value = []
}

const resolveScanKeyword = async (keyword: string, autoRun = false) => {
    loading.value = true
    candidateDevices.value = []
    deviceData.value = null
    manualText.value = keyword
    try {
        const res: any = await scanSearchDevice({ keyword, limit: 20 })
        const list = Array.isArray(res?.data) ? res.data : []
        if (!list.length) {
            uni.showToast({ title: '未找到设备记录', icon: 'none' })
            return
        }
        if (list.length > 1) {
            candidateDevices.value = list
            return
        }
        await selectCandidate(list[0], autoRun)
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '查询设备失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const selectCandidate = async (item: any, autoRun = true) => {
    candidateDevices.value = []
    await loadDevice(String(item.id || ''), false)
    if (autoRun) runCurrentMode()
}

const loadDevice = async (id: string, autoOpen = false) => {
    if (!id) return
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
        if (autoOpen) runCurrentMode()
    } catch (error: any) {
        deviceData.value = null
        uni.showToast({ title: error?.msg || error?.message || '获取设备失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const runCurrentMode = () => {
    if (scanMode.value === 'process') {
        openDeviceAction()
        return
    }
    if (scanMode.value === 'query') {
        openOrderDetail()
        return
    }
    openLabelAction()
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

const openOrderDetail = () => {
    const orderId = deviceData.value?.order_id || deviceData.value?.order?.id
    if (!orderId) {
        uni.showToast({ title: '未找到关联订单', icon: 'none' })
        return
    }
    redirect({
        url: '/addon/hsx_recycle/pages/order/detail',
        param: {
            id: orderId,
            device_keyword: deviceData.value?.imei || deviceData.value?.user_sn || deviceData.value?.sn || deviceData.value?.id
        }
    })
}

const openLabelAction = async () => {
    if (!deviceData.value?.id) {
        uni.showToast({ title: '请先选择设备', icon: 'none' })
        return
    }

    const actions = getVisiblePrintActions(deviceData.value)
    if (!actions.length) {
        uni.showToast({ title: '当前状态暂无打标动作', icon: 'none' })
        openOrderDetail()
        return
    }

    if (actions.length === 1) {
        await executeDevicePrint(actions[0])
        return
    }

    uni.showActionSheet({
        itemList: actions.map((action: any) => action.button_text || action.scene_name || '打印'),
        success: async (res) => {
            const action = actions[res.tapIndex]
            if (action) await executeDevicePrint(action)
        }
    })
}

const executeDevicePrint = async (action: any) => {
    await executePrintAction(action, {
        device_id: deviceData.value?.id,
        order_id: deviceData.value?.order_id || deviceData.value?.order?.id,
        biz_id: deviceData.value?.id
    })
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

const parseScanKeyword = (value: any) => {
    const text = decodeURIComponent(String(value || '').trim())
    if (!text) return ''

    const sceneId = parseSceneValue(text)
    if (sceneId) return sceneId

    const queryMatch = text.match(/[?&](?:imei|imei2|sn|user_sn|code|id|device_id)=([^&#]+)/i)
    if (queryMatch?.[1]) return decodeURIComponent(queryMatch[1]).trim()

    const pathMatch = text.match(/(?:recycle_device|device|check)[/=-]([a-zA-Z0-9_-]+)/i)
    if (pathMatch?.[1]) return pathMatch[1]

    return text
}

const parseSceneValue = (value: string) => {
    const sceneMatch = value.match(/(?:^|[?&])scene=([^&#]+)/i)
    if (!sceneMatch?.[1]) return ''
    const scene = decodeURIComponent(sceneMatch[1])
    const idMatch = scene.match(/(?:imei|imei2|sn|user_sn|code|id|device_id)[=:]([a-zA-Z0-9_-]+)/i)
    return idMatch?.[1] || scene
}

const formatCustomer = (item: any) => {
    const name = item.customer_name || ''
    const mobile = item.customer_mobile || ''
    if (name && mobile) return `${ name } / ${ mobile }`
    return name || mobile || '-'
}

const formatScanTime = (value: any) => {
    if (!value) return ''
    const numeric = Number(value)
    const date = numeric > 0
        ? new Date(numeric > 100000000000 ? numeric : numeric * 1000)
        : new Date(String(value).replace(/-/g, '/'))
    if (Number.isNaN(date.getTime())) return String(value)
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    const h = String(date.getHours()).padStart(2, '0')
    const i = String(date.getMinutes()).padStart(2, '0')
    return `${ y}-${ m}-${ d } ${ h }:${ i }`
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

.mode-tabs {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12rpx;
    margin-bottom: 20rpx;
}

.mode-tab {
    height: 64rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    color: #475569;
    border: 1rpx solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24rpx;
    font-weight: 600;
}

.mode-tab--active {
    background: #0f766e;
    border-color: #0f766e;
    color: #fff;
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

.candidate-card {
    margin-top: 24rpx;
    padding: 24rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 30rpx rgba(15, 23, 42, 0.05);
}

.candidate-card__head {
    display: flex;
    justify-content: space-between;
    gap: 18rpx;
    align-items: flex-start;
}

.candidate-card__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
}

.candidate-card__desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.5;
}

.candidate-card__count {
    flex-shrink: 0;
    padding: 8rpx 16rpx;
    border-radius: 999rpx;
    background: #ecfeff;
    color: #0f766e;
    font-size: 22rpx;
    font-weight: 700;
}

.candidate-list {
    margin-top: 18rpx;
    display: flex;
    flex-direction: column;
    gap: 14rpx;
}

.candidate-item {
    display: flex;
    justify-content: space-between;
    gap: 18rpx;
    padding: 20rpx;
    border-radius: 16rpx;
    background: #f8fafc;
    border: 1rpx solid #e2e8f0;
}

.candidate-item:active {
    opacity: 0.82;
}

.candidate-item__main {
    min-width: 0;
    flex: 1;
}

.candidate-item__title {
    font-size: 28rpx;
    font-weight: 700;
    color: #0f172a;
}

.candidate-item__meta {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.4;
}

.candidate-item__side {
    max-width: 220rpx;
    flex-shrink: 0;
    text-align: right;
}

.candidate-item__status {
    font-size: 22rpx;
    color: #2563eb;
    font-weight: 700;
}

.candidate-item__time {
    margin-top: 12rpx;
    font-size: 20rpx;
    color: #64748b;
    line-height: 1.4;
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
    grid-template-columns: repeat(3, minmax(0, 1fr));
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
