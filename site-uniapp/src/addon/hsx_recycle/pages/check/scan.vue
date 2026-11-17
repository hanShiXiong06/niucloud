<template>
    <view class="scan-check-page">
        <view class="hero">
            <view class="hero__content">
                <view class="hero__icon">
                    <text :class="['nc-iconfont', currentMode.icon]"></text>
                </view>
                <view class="hero__main">
                    <view class="hero__title">{{ currentMode.title }}</view>
                    <view class="hero__subtitle">{{ currentMode.desc }}</view>
                </view>
            </view>
        </view>

        <view class="scan-panel">
            <view class="mode-tabs">
                <view
                    v-for="item in scanModes"
                    :key="item.value"
                    class="mode-tab"
                    :class="{ 'mode-tab--active': scanMode === item.value, 'mode-tab--busy': loading || scanning }"
                    @click="switchMode(item.value)"
                >
                    <text :class="['nc-iconfont', item.icon, 'mode-tab__icon']"></text>
                    <text class="mode-tab__label">{{ item.label }}</text>
                </view>
            </view>

            <view class="scan-button" @click="scanDevice">
                <view class="scan-button__icon">
                    <text class="nc-iconfont nc-icon-saoyisaoV6xx"></text>
                </view>
                <view class="scan-button__main">
                    <view class="scan-button__title">{{ scanning ? '扫描中…' : (deviceData?.id ? '扫描下一台' : `开始${currentMode.label}`) }}</view>
                    <view class="scan-button__desc">支持设备 ID、IMEI、SN 或链接二维码</view>
                </view>
                <text class="nc-iconfont nc-icon-youV6xx1 scan-button__arrow"></text>
            </view>

            <view class="manual-box">
                <view class="manual-box__title">
                    <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 manual-box__icon"></text>
                    <text>手动输入</text>
                </view>
                <view class="manual-row">
                    <input
                        v-model="manualText"
                        class="manual-input"
                        placeholder="输入设备ID、IMEI、SN或粘贴二维码内容"
                        confirm-type="search"
                        @confirm="handleManualSubmit"
                    />
                    <view class="manual-btn" @click="handleManualSubmit">{{ scanMode === 'query' ? '查询' : '进入' }}</view>
                </view>
            </view>
        </view>

        <view v-if="loading" class="state-card">
            <view class="state-card__icon">
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1"></text>
            </view>
            <text>正在查询设备...</text>
        </view>

        <view v-if="candidateDevices.length > 1" class="candidate-card">
            <view class="candidate-card__head">
                <view class="candidate-card__head-main">
                    <view class="candidate-card__icon">
                        <text class="nc-iconfont nc-icon-dingdanliebiaoV6xx"></text>
                    </view>
                    <view>
                        <view class="candidate-card__title">选择设备记录</view>
                        <view class="candidate-card__desc">同一串码存在多条记录，请确认订单和时间后进入。</view>
                    </view>
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
                        <view class="candidate-item__meta">
                            <text class="candidate-item__meta-label">IMEI</text>
                            <text>{{ item.imei || item.user_sn || item.sn || '-' }}</text>
                        </view>
                        <view class="candidate-item__meta">
                            <text class="candidate-item__meta-label">订单</text>
                            <text>{{ item.order_no || item.order_id || '-' }}</text>
                        </view>
                        <view class="candidate-item__meta">
                            <text class="candidate-item__meta-label">客户</text>
                            <text>{{ formatCustomer(item) }}</text>
                        </view>
                    </view>
                    <view class="candidate-item__side">
                        <view class="candidate-item__status">{{ item.status_name || '-' }}</view>
                        <view v-if="item.milestone_label" class="candidate-item__time">
                            {{ item.milestone_label }} {{ formatScanTime(item.milestone_time) }}
                        </view>
                        <text class="nc-iconfont nc-icon-youV6xx1 candidate-item__arrow"></text>
                    </view>
                </view>
            </view>
        </view>

        <view v-if="deviceData?.id" class="device-card">
            <view class="device-card__head">
                <view class="device-card__head-main">
                    <view class="device-card__icon">
                        <text class="nc-iconfont nc-icon-erweimaV6xx"></text>
                    </view>
                    <view class="device-card__main">
                        <view class="device-card__title">{{ deviceData.model || '未知设备' }}</view>
                        <view class="device-card__meta">IMEI：{{ deviceData.imei || deviceData.user_sn || '-' }}</view>
                    </view>
                </view>
                <view class="device-card__status">{{ deviceData.status_name || '-' }}</view>
            </view>
            <view class="device-card__info">
                <view class="device-card__info-row">
                    <text class="nc-iconfont nc-icon-dingdanbianhaoV6xx device-card__info-icon"></text>
                    <text>订单：{{ deviceData.order?.order_no || deviceData.order_id || '-' }}</text>
                </view>
                <view class="device-card__info-row">
                    <text class="nc-iconfont nc-icon-dianhuaV6xx device-card__info-icon"></text>
                    <text>客户：{{ customerName }}</text>
                </view>
            </view>
            <view class="device-card__actions">
                <view class="device-card__btn" @click="runCurrentMode">
                    <text :class="['nc-iconfont', currentMode.icon, 'device-card__btn-icon']"></text>
                    <text>{{ primaryActionText }}</text>
                </view>
                <view class="device-card__btn device-card__btn--ghost" @click="openOrderDetail">
                    <text class="nc-iconfont nc-icon-dingdanliebiaoV6xx device-card__btn-icon"></text>
                    <text>查看订单</text>
                </view>
                <view class="device-card__btn device-card__btn--ghost" @click="scanDevice">
                    <text class="nc-iconfont nc-icon-saoyisaoV6xx device-card__btn-icon"></text>
                    <text>下一台</text>
                </view>
            </view>
        </view>

        <view class="tips-card">
            <view class="tips-card__title">
                <text :class="['nc-iconfont', currentMode.icon, 'tips-card__icon']"></text>
                <text>当前模式</text>
            </view>
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

        <u-popup :show="labelEditVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="closeLabelEdit">
            <view class="label-edit-popup">
                <view class="label-edit-popup__header">
                    <view>
                        <view class="label-edit-popup__title">编辑打印标签</view>
                        <view class="label-edit-popup__subtitle">仅修改本次标签内容，不修改设备资料、价格或业务状态</view>
                    </view>
                    <text class="nc-iconfont nc-icon-guanbiV6xx1 label-edit-popup__close" @click="closeLabelEdit"></text>
                </view>

                <scroll-view scroll-y class="label-edit-popup__body">
                    <view class="label-edit-tip">
                        <view>建议单行最多放 {{ labelLineLimit }} 个字符。</view>
                        <view>中文约按 {{ labelCnCharWidth }} 个字符计算，英文、数字、符号按 {{ labelAsciiCharWidth }} 个字符计算。</view>
                    </view>

                    <view v-for="field in labelFieldConfigs" :key="field.key" class="label-edit-field">
                        <view class="label-edit-field__head">
                            <text class="label-edit-field__label">{{ field.label }}</text>
                            <text
                                class="label-edit-field__count"
                                :class="{ 'label-edit-field__count--warning': getLabelFieldWidth(field.key) > labelLineLimit }"
                            >
                                {{ formatLabelFieldWidth(field.key) }}/{{ labelLineLimit }}
                            </text>
                        </view>
                        <textarea
                            v-model="labelEditForm[field.key]"
                            class="label-edit-field__textarea"
                            :placeholder="field.placeholder"
                            maxlength="120"
                            auto-height
                        />
                        <view
                            v-if="getLabelFieldWidth(field.key) > labelLineLimit"
                            class="label-edit-field__warning"
                        >
                            该字段可能超过单行宽度，建议手动删减或换成短名称。
                        </view>
                    </view>
                </scroll-view>

                <view class="label-edit-popup__footer">
                    <u-button @click="closeLabelEdit" :customStyle="{ flex: 1 }">取消</u-button>
                    <u-button
                        type="primary"
                        @click="confirmLabelPrint"
                        :customStyle="{ flex: 1, marginLeft: '16rpx' }"
                    >
                        确认打印
                    </u-button>
                </view>
            </view>
        </u-popup>

    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getDevice, scanSearchDevice } from '@/addon/hsx_recycle/api/order'
import { redirect, getToken } from '@/utils/common'
import { useLogin } from '@/hooks/useLogin'
import { parseDevicePrintTarget } from '@/addon/hsx_recycle/utils/devicePrintLink'
import type { DevicePrintTarget } from '@/addon/hsx_recycle/utils/devicePrintLink'
import CheckDevicePopup from '@/addon/hsx_recycle/pages/order/components/CheckDevicePopup.vue'
import PriceDevicePopup from '@/addon/hsx_recycle/pages/order/components/PriceDevicePopup.vue'
import { useRecyclePrintActions } from '@/addon/hsx_recycle/hooks/useRecyclePrintActions'
import { getPrintScenePlan } from '@/addon/hsx_recycle/api/printer'

type ScanMode = 'process' | 'query' | 'label'

const manualText = ref('')
const deviceData = ref<any>(null)
const candidateDevices = ref<any[]>([])
const loading = ref(false)
const scanning = ref(false)
const checkPopupVisible = ref(false)
const pricePopupVisible = ref(false)
const labelEditVisible = ref(false)
const loadedDeviceId = ref('')
const scanMode = ref<ScanMode>('process')
const pendingPrintAction = ref<any>(null)
const pendingPrintTarget = ref<any>(null)
let labelPlanRequestId = 0
const labelLineLimit = ref(17)
const labelCnCharWidth = ref(2.5)
const labelAsciiCharWidth = ref(1)
let openedFromQuery = false

const defaultLabelFieldConfigs = [
    { key: 'model', label: '产品名称/型号', placeholder: '请输入产品名称或型号' },
    { key: 'capacity', label: '规格/容量', placeholder: '请输入规格或容量' },
    { key: 'color', label: '颜色', placeholder: '请输入颜色' },
    { key: 'imei', label: 'IMEI', placeholder: '请输入 IMEI' },
    { key: 'sn', label: 'SN', placeholder: '请输入 SN' },
    { key: 'order_no', label: '订单号', placeholder: '请输入订单号' },
    { key: 'customer_name', label: '客户姓名', placeholder: '请输入客户姓名' }
] as Array<{ key: string, label: string, placeholder: string, value?: string }>

type LabelEditForm = Record<string, string>

const labelFieldConfigs = ref<Array<{ key: string, label: string, placeholder: string, value?: string }>>([...defaultLabelFieldConfigs])

const createEmptyLabelForm = (): LabelEditForm => {
    return labelFieldConfigs.value.reduce((form, field) => {
        form[field.key] = ''
        return form
    }, {} as LabelEditForm)
}

const labelEditForm = ref<LabelEditForm>(createEmptyLabelForm())
const labelEditOriginalForm = ref<LabelEditForm>(createEmptyLabelForm())

const {
    loadManualPrintActions,
    getVisiblePrintActions,
    executePrintAction
} = useRecyclePrintActions('device')

// 页面入口预加载；立即点打标时复用同一请求，不把“还在加载”误判为未配置。
let printActionsPromise: Promise<void> | null = null
const ensureManualPrintActions = () => {
    if (!printActionsPromise) printActionsPromise = loadManualPrintActions()
    return printActionsPromise
}

const scanModes: Array<{ value: ScanMode, label: string, title: string, desc: string, tip: string, icon: string }> = [
    {
        value: 'process',
        label: '扫码处理',
        title: '扫码处理',
        desc: '点击即扫码，识别后直接进入质检或定价。',
        tip: '待质检打开质检，已质检/待确认打开定价。',
        icon: 'nc-icon-saoyisaoV6xx'
    },
    {
        value: 'query',
        label: '扫码查询',
        title: '扫码查询',
        desc: '点击即查询，识别后直接进入设备所在订单。',
        tip: '单台设备直接进入订单详情，多台设备先选择记录。',
        icon: 'nc-icon-sousuo-duanV6xx1'
    },
    {
        value: 'label',
        label: '扫码打标',
        title: '扫码打标',
        desc: '识别后直接打开标签编辑，修改核对后再确认打印。',
        tip: '仅调整本次标签，不改变设备状态；打印模板和打印机沿用后台场景配置。',
        icon: 'nc-icon-dayinjiV6xx'
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
    if (scanMode.value === 'label') return '编辑打印标签'
    if (scanMode.value === 'query') return '查看订单'
    const action = getDeviceActionType()
    if (action === 'check') {
        return deviceData.value?.check_result_seller || deviceData.value?.check_result ? '继续质检' : '开始质检'
    }
    if (action === 'price') return '扫码定价'
    return '查看设备'
})

onLoad((option: any) => {
    if (!getToken()) {
        useLogin().setLoginBack({ url: '/addon/hsx_recycle/pages/check/scan', param: option || {} })
        return
    }
    const hasExplicitMode = ['process', 'query', 'label'].includes(String(option?.mode || ''))
    if (hasExplicitMode) {
        scanMode.value = String(option.mode) as ScanMode
    }
    void ensureManualPrintActions()

    // A4/标签直达码使用记录 ID，而非 IMEI 模糊检索；同串号多次回收也能定位本次记录。
    const directTarget = parseDevicePrintTarget(option)
    if (directTarget) {
        openedFromQuery = true
        // 从外部打开裸设备链接只查询；在本页点扫码按钮时才沿用用户选定的处理动作。
        if (!hasExplicitMode) scanMode.value = 'query'
        void openPrintedDevice(directTarget, hasExplicitMode)
        return
    }

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
    if (rejectBusyInput()) return
    closeLabelEdit()
    scanning.value = true
    try {
        uni.scanCode({
            onlyFromCamera: false,
            scanType: ['qrCode', 'barCode'],
            success: (res) => {
                scanning.value = false
                const directTarget = parseDevicePrintTarget(res.result)
                if (directTarget) { void openPrintedDevice(directTarget); return }
                const keyword = parseScanKeyword(res.result || '')
                if (!keyword) {
                    uni.showToast({ title: '未识别到设备码', icon: 'none' })
                    return
                }
                resolveScanKeyword(keyword, true)
            },
            fail: (error: any) => {
                scanning.value = false
                if (String(error?.errMsg || '').includes('cancel')) return
                uni.showToast({ title: '扫码失败', icon: 'none' })
            },
            complete: () => { scanning.value = false }
        })
    } catch (_) {
        scanning.value = false
        uni.showToast({ title: '暂时无法打开扫码，请重试或手动输入', icon: 'none' })
    }
}

const rejectBusyInput = () => {
    if (!loading.value && !scanning.value) return false
    uni.showToast({ title: scanning.value ? '正在扫码，请完成或取消后重试' : '正在查询设备，请稍候', icon: 'none' })
    return true
}

const handleManualSubmit = async () => {
    if (rejectBusyInput()) return
    const directTarget = parseDevicePrintTarget(manualText.value)
    if (directTarget) { await openPrintedDevice(directTarget); return }
    const keyword = parseScanKeyword(manualText.value)
    if (!keyword) {
        uni.showToast({ title: '请输入有效设备码', icon: 'none' })
        return
    }
    await resolveScanKeyword(keyword, true)
}

const switchMode = async (value: ScanMode) => {
    if (rejectBusyInput()) return
    closeLabelEdit()
    scanMode.value = value
    // 多次回收的同一串号不能猜记录；切换动作后保留候选，选中即执行。
    if (candidateDevices.value.length > 1) return
    if (deviceData.value?.id) {
        // 已输入另一台串号时先查新设备，不能把旧设备卡片当作本次操作对象。
        if (manualText.value.trim() && manualText.value.trim() !== loadedDeviceId.value) {
            await handleManualSubmit()
            return
        }
        await runCurrentMode()
        return
    }
    scanDevice()
}

const resolveScanKeyword = async (keyword: string, autoRun = false) => {
    if (rejectBusyInput()) return
    closeLabelEdit()
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
        await loadDevice(String(list[0].id || ''), autoRun)
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '查询设备失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const selectCandidate = async (item: any, autoRun = true) => {
    if (rejectBusyInput()) return
    candidateDevices.value = []
    await loadDevice(String(item.id || ''), autoRun)
}

const openPrintedDevice = async (target: DevicePrintTarget, autoRun = true) => {
    if (rejectBusyInput()) return
    closeLabelEdit()
    candidateDevices.value = []
    deviceData.value = null
    const currentSiteId = Number(uni.getStorageSync('siteId') || 0)
    if (target.siteId && currentSiteId && target.siteId !== currentSiteId) {
        uni.showModal({ title: '站点不匹配', content: '该设备属于其他站点，请登录对应门店的管理账号后重新扫码。', showCancel: false })
        return
    }
    // 二维码只提供设备身份，不覆盖本页当前动作，也不会直接提交或打印。
    await loadDevice(target.deviceId, autoRun)
}

const loadDevice = async (id: string, autoOpen = false) => {
    if (!id) return
    closeLabelEdit()
    loading.value = true
    deviceData.value = null
    loadedDeviceId.value = ''
    try {
        const res: any = await getDevice(id)
        deviceData.value = res?.data || null
        loadedDeviceId.value = String(deviceData.value?.id || id)
        manualText.value = loadedDeviceId.value
        if (!deviceData.value?.id) {
            uni.showToast({ title: '设备不存在', icon: 'none' })
            return
        }
        if (autoOpen) await runCurrentMode()
    } catch (error: any) {
        deviceData.value = null
        uni.showToast({ title: error?.msg || error?.message || '获取设备失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const runCurrentMode = async () => {
    if (scanMode.value === 'process') {
        openDeviceAction()
        return
    }
    if (scanMode.value === 'query') {
        openOrderDetail()
        return
    }
    await openLabelAction()
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

    const deviceId = String(deviceData.value.id)
    const orderId = String(deviceData.value.order_id || deviceData.value.order?.id || '')
    const requestId = labelPlanRequestId
    await ensureManualPrintActions()
    if (requestId !== labelPlanRequestId || scanMode.value !== 'label' || String(deviceData.value?.id || '') !== deviceId
        || String(deviceData.value?.order_id || deviceData.value?.order?.id || '') !== orderId) return

    const actions = getVisiblePrintActions(deviceData.value, { ignoreBusinessStatus: true })
        .filter((action: any) => action.template_type === 'device_label')
    if (!actions.length) {
        uni.showToast({ title: '未配置可用的设备标签场景，请联系管理员', icon: 'none' })
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
    const requestId = ++labelPlanRequestId
    const target = {
        device_id: deviceData.value?.id,
        order_id: deviceData.value?.order_id || deviceData.value?.order?.id,
        biz_id: deviceData.value?.id,
        entry: 'scan_label' as const
    }
    try {
        const config = await loadLabelEditConfig(action, target)
        if (requestId !== labelPlanRequestId || scanMode.value !== 'label'
            || String(deviceData.value?.id || '') !== String(target.device_id || '')
            || String(deviceData.value?.order_id || deviceData.value?.order?.id || '') !== String(target.order_id || '')) return
        applyLabelEditConfig(config)
        const form = buildLabelEditForm()
        pendingPrintAction.value = action
        pendingPrintTarget.value = target
        labelEditForm.value = { ...form }
        labelEditOriginalForm.value = { ...form }
        labelEditVisible.value = true
    } catch (error: any) {
        if (requestId !== labelPlanRequestId) return
        uni.showToast({ title: error?.data?.message || error?.msg || error?.message || '标签加载失败，请重试', icon: 'none' })
    }
}

const confirmLabelPrint = async () => {
    const action = pendingPrintAction.value
    const target = pendingPrintTarget.value
    if (!action || !target) {
        closeLabelEdit()
        return
    }
    if (scanMode.value !== 'label' || String(deviceData.value?.id || '') !== String(target.device_id || '')
        || String(deviceData.value?.order_id || deviceData.value?.order?.id || '') !== String(target.order_id || '')) {
        closeLabelEdit()
        uni.showToast({ title: '设备已切换，请重新打开标签', icon: 'none' })
        return
    }
    const override = buildPrintDataOverride()
    closeLabelEdit()
    await executePrintAction(action, {
        ...target,
        print_data_override: override
    })
}

const closeLabelEdit = () => {
    labelPlanRequestId++
    labelEditVisible.value = false
    pendingPrintAction.value = null
    pendingPrintTarget.value = null
}

const buildLabelEditForm = (): LabelEditForm => {
    return labelFieldConfigs.value.reduce((form, field) => {
        form[field.key] = String((field as any).value || '')
        return form
    }, {} as LabelEditForm)
}

const buildPrintDataOverride = () => {
    const data: Record<string, string> = {}
    labelFieldConfigs.value.forEach((field) => {
        const value = String(labelEditForm.value[field.key] || '').trim()
        const originalValue = String(labelEditOriginalForm.value[field.key] || '').trim()
        if (value !== originalValue) {
            data[field.key] = value
        }
    })
    return data
}

const loadLabelEditConfig = async (action: any, payload: Record<string, any>) => {
    const res: any = await getPrintScenePlan(action.scene_key, payload)
    if (res?.code !== 1 || !res?.data?.can_print) throw new Error(res?.data?.message || res?.msg || '打印计划不可用')
    return res.data.label_edit || {}
}

const applyLabelEditConfig = (config: any = {}) => {
    const fields = Array.isArray(config.fields) && config.fields.length
        ? config.fields
        : defaultLabelFieldConfigs
    labelFieldConfigs.value = fields
        .map((field: any) => ({
            key: String(typeof field === 'string' ? field : field.key || ''),
            label: String(typeof field === 'string' ? field : field.label || field.key || ''),
            placeholder: String(typeof field === 'string' ? '' : field.placeholder || `请输入${ field.label || field.key || '' }`),
            value: String(typeof field === 'string' ? '' : field.value || '')
        }))
        .filter((field: any) => field.key)
    if (!labelFieldConfigs.value.length) {
        labelFieldConfigs.value = [...defaultLabelFieldConfigs]
    }

    const limit = Number(config.line_width_limit || 0)
    labelLineLimit.value = limit > 0 ? limit : 17
    labelCnCharWidth.value = Number(config.cn_char_width || 2.5) || 2.5
    labelAsciiCharWidth.value = Number(config.ascii_char_width || 1) || 1

    return {
        enabled: Number(config.enabled ?? 1) === 1
    }
}

const getLabelFieldWidth = (key: string) => {
    return calcLabelTextWidth(labelEditForm.value[key])
}

const formatLabelFieldWidth = (key: string) => {
    const width = getLabelFieldWidth(key)
    return Number.isInteger(width) ? String(width) : width.toFixed(1)
}

const calcLabelTextWidth = (value: any) => {
    return Array.from(String(value || '')).reduce((total, char) => {
        return total + (/[\u4e00-\u9fff]/.test(char) ? labelCnCharWidth.value : labelAsciiCharWidth.value)
    }, 0)
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
    padding: 24rpx 24rpx 40rpx;
    background: #f4f6f8;
    box-sizing: border-box;
}

.hero {
    padding: 30rpx;
    border-radius: 18rpx;
    color: #fff;
    background: #0f766e;
    box-shadow: 0 14rpx 34rpx rgba(15, 118, 110, 0.18);
}

.hero__content {
    display: flex;
    align-items: center;
}

.hero__icon {
    width: 92rpx;
    height: 92rpx;
    margin-right: 22rpx;
    border-radius: 18rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.16);
    flex-shrink: 0;
}

.hero__icon .nc-iconfont {
    font-size: 48rpx;
}

.hero__main {
    min-width: 0;
    flex: 1;
}

.hero__title {
    font-size: 36rpx;
    font-weight: 700;
    line-height: 1.2;
}

.hero__subtitle {
    margin-top: 10rpx;
    font-size: 24rpx;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.82);
}

.scan-panel,
.device-card,
.tips-card,
.state-card {
    margin-top: 24rpx;
    padding: 24rpx;
    border-radius: 16rpx;
    background: #fff;
    border: 1rpx solid #e9eef3;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.mode-tabs {
    display: flex;
    margin-bottom: 20rpx;
}

.mode-tab {
    flex: 1;
    min-width: 0;
    height: 112rpx;
    margin-right: 12rpx;
    border-radius: 16rpx;
    background: #f7fafc;
    color: #475569;
    border: 1rpx solid #e2e8f0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.mode-tab:last-child {
    margin-right: 0;
}

.mode-tab__icon {
    font-size: 36rpx;
    line-height: 1;
}

.mode-tab__label {
    margin-top: 10rpx;
    font-size: 23rpx;
    font-weight: 600;
    line-height: 1;
}

.mode-tab--active {
    background: #0f766e;
    border-color: #0f766e;
    color: #fff;
}
.mode-tab--busy { opacity: 0.65; }

.scan-button {
    display: flex;
    align-items: center;
    padding: 26rpx;
    border-radius: 16rpx;
    background: #ecfdf5;
    border: 1rpx solid #b7ead8;
}

.scan-button__icon {
    width: 84rpx;
    height: 84rpx;
    margin-right: 20rpx;
    border-radius: 18rpx;
    background: #0f766e;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.scan-button__icon .nc-iconfont {
    font-size: 44rpx;
}

.scan-button__main {
    flex: 1;
    min-width: 0;
}

.scan-button__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.scan-button__desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.4;
}

.scan-button__arrow {
    margin-left: 16rpx;
    color: #0f766e;
    font-size: 28rpx;
    flex-shrink: 0;
}

.manual-box {
    margin-top: 24rpx;
}

.manual-box__title {
    margin-bottom: 14rpx;
    display: flex;
    align-items: center;
    font-size: 26rpx;
    font-weight: 600;
    color: #334155;
}

.manual-box__icon {
    margin-right: 10rpx;
    color: #0f766e;
    font-size: 28rpx;
}

.manual-row {
    display: flex;
    align-items: center;
}

.manual-input {
    flex: 1;
    height: 76rpx;
    min-width: 0;
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
    margin-left: 14rpx;
    border-radius: 14rpx;
    background: #0f766e;
    color: #fff;
    font-size: 24rpx;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.state-card {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24rpx;
    color: #64748b;
}

.state-card__icon {
    width: 54rpx;
    height: 54rpx;
    margin-right: 14rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ecfdf5;
    color: #0f766e;
}

.candidate-card {
    margin-top: 24rpx;
    padding: 24rpx;
    border-radius: 16rpx;
    background: #fff;
    border: 1rpx solid #e9eef3;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.candidate-card__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.candidate-card__head-main {
    display: flex;
    align-items: flex-start;
    min-width: 0;
    flex: 1;
}

.candidate-card__icon {
    width: 64rpx;
    height: 64rpx;
    margin-right: 16rpx;
    border-radius: 14rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hsx-primary-50);
    color: var(--hsx-primary);
    flex-shrink: 0;
}

.candidate-card__icon .nc-iconfont {
    font-size: 34rpx;
}

.candidate-card__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.25;
}

.candidate-card__desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.5;
}

.candidate-card__count {
    flex-shrink: 0;
    margin-left: 18rpx;
    padding: 8rpx 16rpx;
    border-radius: 999rpx;
    background: #ecfdf5;
    color: #0f766e;
    font-size: 22rpx;
    font-weight: 700;
}

.candidate-list {
    margin-top: 18rpx;
}

.candidate-item {
    display: flex;
    justify-content: space-between;
    margin-top: 14rpx;
    padding: 20rpx;
    border-radius: 16rpx;
    background: #f8fafc;
    border: 1rpx solid #e2e8f0;
    box-sizing: border-box;
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
    line-height: 1.35;
}

.candidate-item__meta {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.4;
    display: flex;
    align-items: flex-start;
}

.candidate-item__meta-label {
    width: 58rpx;
    margin-right: 10rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.candidate-item__side {
    max-width: 230rpx;
    margin-left: 18rpx;
    flex-shrink: 0;
    text-align: right;
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.candidate-item__status {
    padding: 6rpx 12rpx;
    border-radius: 999rpx;
    background: var(--hsx-primary-50);
    font-size: 22rpx;
    color: var(--hsx-primary);
    font-weight: 700;
    line-height: 1.2;
}

.candidate-item__time {
    margin-top: 12rpx;
    font-size: 20rpx;
    color: #64748b;
    line-height: 1.4;
}

.candidate-item__arrow {
    margin-top: 12rpx;
    color: #94a3b8;
    font-size: 24rpx;
}

.device-card__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.device-card__head-main {
    display: flex;
    align-items: flex-start;
    min-width: 0;
    flex: 1;
}

.device-card__icon {
    width: 72rpx;
    height: 72rpx;
    margin-right: 16rpx;
    border-radius: 16rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #0f766e;
    background: #ecfdf5;
    flex-shrink: 0;
}

.device-card__icon .nc-iconfont {
    font-size: 38rpx;
}

.device-card__main {
    min-width: 0;
    flex: 1;
}

.device-card__title {
    font-size: 30rpx;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
}

.device-card__meta,
.tips-card__line {
    margin-top: 10rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.6;
}

.device-card__status {
    margin-left: 16rpx;
    padding: 8rpx 14rpx;
    border-radius: 999rpx;
    background: var(--hsx-primary-50);
    color: var(--hsx-primary);
    font-size: 22rpx;
    font-weight: 700;
    flex-shrink: 0;
    line-height: 1.2;
}

.device-card__info {
    margin-top: 20rpx;
    padding: 16rpx;
    border-radius: 14rpx;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
}

.device-card__info-row {
    display: flex;
    align-items: center;
    min-height: 38rpx;
    font-size: 23rpx;
    color: #475569;
    line-height: 1.5;
}

.device-card__info-row + .device-card__info-row {
    margin-top: 8rpx;
}

.device-card__info-icon {
    width: 34rpx;
    margin-right: 10rpx;
    color: #0f766e;
    font-size: 24rpx;
    flex-shrink: 0;
}

.device-card__actions {
    display: flex;
    margin-top: 24rpx;
}

.device-card__btn {
    flex: 1;
    min-width: 0;
    margin-left: 12rpx;
    padding: 0 8rpx;
    box-sizing: border-box;
}

.device-card__btn:first-child {
    margin-left: 0;
}

.device-card__btn-icon {
    margin-right: 8rpx;
    font-size: 26rpx;
    flex-shrink: 0;
}

.device-card__btn--ghost {
    background: #f8fafc;
    color: #334155;
    border: 1rpx solid #dbe2ea;
}

.tips-card__title {
    display: flex;
    align-items: center;
    font-size: 26rpx;
    font-weight: 700;
    color: #334155;
}

.tips-card__icon {
    margin-right: 10rpx;
    color: #0f766e;
    font-size: 28rpx;
}

.label-edit-popup {
    max-height: 86vh;
    background: #fff;
    display: flex;
    flex-direction: column;
}

.label-edit-popup__header {
    padding: 30rpx;
    border-bottom: 1rpx solid #eef2f7;
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.label-edit-popup__title {
    font-size: 32rpx;
    font-weight: 700;
    color: #111827;
    line-height: 1.25;
}

.label-edit-popup__subtitle {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.4;
}

.label-edit-popup__close {
    flex-shrink: 0;
    font-size: 32rpx;
    color: #64748b;
}

.label-edit-popup__body {
    height: 58vh;
    max-height: 58vh;
    padding: 24rpx;
    box-sizing: border-box;
    background: #f6f7fb;
}

.label-edit-tip {
    padding: 18rpx 20rpx;
    border-radius: 14rpx;
    background: #ecfdf5;
    border: 1rpx solid #b7ead8;
    color: #0f766e;
    font-size: 22rpx;
    line-height: 1.6;
}

.label-edit-field {
    margin-top: 18rpx;
    padding: 20rpx;
    border-radius: 16rpx;
    background: #fff;
    border: 1rpx solid #e9eef3;
}

.label-edit-field__head {
    margin-bottom: 12rpx;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}

.label-edit-field__label {
    font-size: 25rpx;
    font-weight: 700;
    color: #334155;
}

.label-edit-field__count {
    flex-shrink: 0;
    font-size: 22rpx;
    color: #64748b;
}

.label-edit-field__count--warning {
    color: #dc2626;
    font-weight: 700;
}

.label-edit-field__textarea {
    width: 100%;
    min-height: 72rpx;
    padding: 18rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    border: 1rpx solid #dbe2ea;
    color: #0f172a;
    font-size: 25rpx;
    line-height: 1.45;
    box-sizing: border-box;
}

.label-edit-field__warning {
    margin-top: 10rpx;
    font-size: 21rpx;
    color: #dc2626;
    line-height: 1.4;
}

.label-edit-popup__footer {
    padding: 20rpx 24rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #eef2f7;
    display: flex;
    background: #fff;
}

</style>
