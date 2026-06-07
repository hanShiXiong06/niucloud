<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="device-detail-popup">
            <view class="detail-header">
                <view class="detail-header__main">
                    <view class="detail-title">{{ device.model || device.device_name || '设备详情' }}</view>
                    <view class="detail-subtitle">{{ device.imei || device.user_sn || '暂无串号' }}</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <scroll-view scroll-y class="detail-content">
                <view v-if="loading" class="loading-state">设备详情加载中...</view>
                <view v-else-if="!device.id" class="loading-state">暂无设备详情</view>

                <template v-else>
                <view class="section">
                    <view class="section-title">基础信息</view>
                    <view class="info-list">
                        <view v-for="item in basicRows" :key="item.label" class="info-row" :class="{ 'info-row--top': item.long }">
                            <text class="info-label">{{ item.label }}</text>
                            <text class="info-value">{{ item.value }}</text>
                        </view>
                    </view>
                </view>

                <view class="section">
                    <view class="section-title">价格与状态</view>
                    <view class="info-list">
                        <view v-for="item in statusRows" :key="item.label" class="info-row" :class="{ 'info-row--top': item.long }">
                            <text class="info-label">{{ item.label }}</text>
                            <text class="info-value" :class="{ 'info-value--price': item.price }">{{ item.value }}</text>
                        </view>
                    </view>
                </view>

                <view v-if="hasCostAdjustment || canAdjustCost" class="section">
                    <view class="section-title-row">
                        <view>
                            <view class="section-title">成本调整</view>
                            <view class="section-subtitle">已打款后修改设备成本会留痕，提交后请同步进销存成本。</view>
                        </view>
                        <view v-if="canAdjustCost" class="small-action" @click="openCostAdjust">调整</view>
                    </view>
                    <view v-if="hasCostAdjustment" class="info-list">
                        <view v-for="item in costAdjustRows" :key="item.label" class="info-row">
                            <text class="info-label">{{ item.label }}</text>
                            <text class="info-value" :class="{ 'info-value--price': item.price }">{{ item.value }}</text>
                        </view>
                    </view>
                    <view v-else class="cost-tip">当前暂无成本调整记录。</view>
                </view>

                <view v-if="checkRows.length || checkResultRows.length || checkMetaItems.length" class="section">
                    <view class="section-title">质检信息</view>
                    <view v-if="checkRows.length" class="info-list">
                        <view v-for="item in checkRows" :key="item.label" class="info-row" :class="{ 'info-row--top': item.long }">
                            <text class="info-label">{{ item.label }}</text>
                            <text class="info-value">{{ item.value }}</text>
                        </view>
                    </view>
                    <view v-if="checkResultRows.length" class="check-result-list">
                        <view v-for="item in checkResultRows" :key="item.label" class="check-result">
                            <view class="check-result__label">{{ item.label }}</view>
                            <view class="check-result__text">{{ item.value }}</view>
                        </view>
                    </view>
                    <view v-if="checkMetaItems.length" class="meta-grid">
                        <view v-for="item in checkMetaItems" :key="item.key" class="meta-item">
                            <text class="meta-item__label">{{ item.label }}</text>
                            <text class="meta-item__value">{{ item.value }}</text>
                        </view>
                    </view>
                </view>

                <view v-if="imageGroups.length" class="section">
                    <view class="section-title">设备图片</view>
                    <view v-for="group in imageGroups" :key="group.label" class="image-group">
                        <view class="image-group__title">{{ group.label }}</view>
                        <view class="image-group__list">
                            <view
                                v-for="(item, index) in group.items"
                                :key="`${ group.label }-${ index }`"
                                class="image-item"
                                @click="previewGroup(group.items, index)"
                            >
                                <image class="image-item__img" :src="item.thumb || item.url" mode="aspectFill" />
                            </view>
                        </view>
                    </view>
                </view>

                <view v-if="consignmentRows.length" class="section">
                    <view class="section-title">代卖信息</view>
                    <view class="info-list">
                        <view v-for="item in consignmentRows" :key="item.label" class="info-row" :class="{ 'info-row--top': item.long }">
                            <text class="info-label">{{ item.label }}</text>
                            <text class="info-value" :class="{ 'info-value--price': item.price }">{{ item.value }}</text>
                        </view>
                    </view>
                </view>

                <view class="section">
                    <view class="section-title">完整流转</view>
                    <view v-if="logs.length" class="timeline">
                        <view v-for="(log, index) in logs" :key="log.id || `${ log.source_type || 'log' }-${ index }`" class="timeline__item">
                            <view class="timeline__line-wrap">
                                <view class="timeline__dot"></view>
                                <view v-if="index < logs.length - 1" class="timeline__line"></view>
                            </view>
                            <view class="timeline__content">
                                <view class="timeline__top">
                                    <text class="timeline__status">{{ log.status_name || log.operation_type || log.action || '状态更新' }}</text>
                                    <text class="timeline__time">{{ formatTime(log.create_at) }}</text>
                                </view>
                                <view class="timeline__operator">{{ log.operator_name || '系统' }}</view>
                                <view v-if="log.remark" class="timeline__remark">{{ log.remark }}</view>
                            </view>
                        </view>
                    </view>
                    <view v-else class="empty-state">暂无完整流转记录</view>
                </view>
                </template>
            </scroll-view>

            <view class="detail-footer">
                <u-button v-if="canAdjustCost" type="warning" @click="openCostAdjust" :customStyle="{ flex: 1 }">调整成本</u-button>
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">关闭</u-button>
            </view>
        </view>
    </u-popup>

    <u-popup :show="costAdjustVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="closeCostAdjust">
        <view class="cost-adjust-popup">
            <view class="detail-header">
                <view class="detail-header__main">
                    <view class="detail-title">设备成本调整</view>
                    <view class="detail-subtitle">当前成本 ¥{{ formatMoney(device.final_price || 0) }}</view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="closeCostAdjust"></text>
            </view>
            <scroll-view scroll-y class="cost-adjust-content">
                <view class="warning-box">
                    此操作会修改设备当前成本，不会修改历史打款记录。提交后请同步修改进销存软件中的库存成本。
                </view>

                <view class="form-block">
                    <view class="form-label">调整类型</view>
                    <view class="type-grid">
                        <view
                            v-for="item in adjustTypes"
                            :key="item.value"
                            class="type-item"
                            :class="{ 'type-item--active': costAdjustForm.adjust_type === item.value }"
                            @click="costAdjustForm.adjust_type = item.value"
                        >
                            {{ item.label }}
                        </view>
                    </view>
                </view>

                <view v-if="costAdjustForm.adjust_type === 'cost_correction'" class="form-block">
                    <view class="form-label">修正方向</view>
                    <view class="type-grid type-grid--two">
                        <view class="type-item" :class="{ 'type-item--active': costAdjustForm.direction === 'decrease' }" @click="costAdjustForm.direction = 'decrease'">成本减少</view>
                        <view class="type-item" :class="{ 'type-item--active': costAdjustForm.direction === 'increase' }" @click="costAdjustForm.direction = 'increase'">成本增加</view>
                    </view>
                </view>

                <view class="form-block">
                    <view class="form-label">调整金额</view>
                    <input v-model="costAdjustForm.adjust_amount" class="form-input" type="digit" placeholder="请输入金额" />
                    <view class="form-help">预计调整后成本：{{ previewAfterCost }}</view>
                </view>

                <view class="form-block">
                    <view class="form-label">调整原因</view>
                    <textarea v-model="costAdjustForm.reason" class="form-textarea" maxlength="200" placeholder="例如：已打款后发现主板维修，客户同意退回200元" />
                </view>

                <view class="confirm-row" @click="costAdjustForm.customer_handled = costAdjustForm.customer_handled ? 0 : 1">
                    <view class="checkbox" :class="{ 'checkbox--checked': costAdjustForm.customer_handled }"></view>
                    <text>与客户差额已沟通/已处理</text>
                </view>
                <view class="confirm-row" @click="costAdjustForm.inventory_tip_confirmed = costAdjustForm.inventory_tip_confirmed ? 0 : 1">
                    <view class="checkbox" :class="{ 'checkbox--checked': costAdjustForm.inventory_tip_confirmed }"></view>
                    <text>我已知晓：提交后需要同步修改进销存软件成本</text>
                </view>

                <view v-if="costAdjustLogs.length" class="history-block">
                    <view class="form-label">历史调整</view>
                    <view v-for="item in costAdjustLogs" :key="item.id" class="history-item">
                        <view class="history-top">
                            <text>{{ item.adjust_type_name }}</text>
                            <text class="history-amount">{{ formatSignedMoney(item.adjust_delta) }}</text>
                        </view>
                        <view class="history-desc">调整后 ¥{{ formatMoney(item.after_cost) }} · {{ item.operator_name || '系统' }} · {{ formatTimeValue(item.create_at) }}</view>
                    </view>
                </view>
            </scroll-view>
            <view class="detail-footer">
                <u-button @click="closeCostAdjust" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button type="warning" :loading="costAdjustSubmitting" @click="submitCostAdjust" :customStyle="{ flex: 1 }">确认调整</u-button>
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
import { img } from '@/utils/common'
import { adjustDeviceCost, getDevice, getDeviceCostAdjustAbility, getDeviceCostAdjustLogs } from '@/addon/hsx_recycle/api/order'
import ImagePreviewOverlay from '@/addon/hsx_recycle/components/ImagePreviewOverlay.vue'
import { formatMoney, formatTime } from '@/addon/hsx_recycle/utils/helper'
import { isConsignedDevice } from '@/addon/hsx_recycle/utils/device'

interface RowItem {
    label: string
    value: string
    price?: boolean
    long?: boolean
}

interface ImageItem {
    url: string
    thumb: string
}

const props = defineProps<{
    visible: boolean
    deviceData: any
}>()

const emit = defineEmits(['update:visible', 'updated'])

const show = ref(false)
const loading = ref(false)
const latestDeviceData = ref<any>(null)
const previewVisible = ref(false)
const previewUrls = ref<string[]>([])
const previewCurrent = ref(0)
const costAdjustVisible = ref(false)
const costAdjustSubmitting = ref(false)
const costAdjustLogs = ref<any[]>([])
const costAdjustAllowed = ref(false)
const costAdjustForm = ref({
    adjust_type: 'refund_from_customer',
    direction: 'decrease',
    adjust_amount: '',
    reason: '',
    customer_handled: 0,
    inventory_tip_confirmed: 0
})
const adjustTypes = [
    { label: '客户退回差额', value: 'refund_from_customer' },
    { label: '补款给客户', value: 'pay_to_customer' },
    { label: '内部修正', value: 'cost_correction' }
]

const device = computed(() => latestDeviceData.value || props.deviceData || {})
const info = computed(() => normalizeObject(device.value.info))
const checkMeta = computed(() => normalizeObject(info.value.check_meta))
const logs = computed(() => Array.isArray(device.value.logs) ? device.value.logs : [])
const isConsigned = computed(() => isConsignedDevice(device.value))
const hasCostAdjustment = computed(() => Number(device.value.cost_adjust_count || 0) > 0)
const canAdjustCost = computed(() => costAdjustAllowed.value && Number(device.value.pay_status || 0) === 1 && Number(device.value.status || 0) !== 6)

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        loadDeviceDetail()
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const loadDeviceDetail = async () => {
    const deviceId = props.deviceData?.id
    if (!deviceId) {
        latestDeviceData.value = null
        return
    }

    latestDeviceData.value = props.deviceData || null
    costAdjustAllowed.value = false
    costAdjustLogs.value = []
    loading.value = true
    try {
        const res: any = await getDevice(deviceId)
        latestDeviceData.value = res?.data || props.deviceData || null
        await loadCostAdjustAbility()
        await loadCostAdjustLogs()
    } catch (error: any) {
        latestDeviceData.value = props.deviceData || null
        uni.showToast({ title: error?.msg || error?.message || '获取设备详情失败', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const basicRows = computed<RowItem[]>(() => compactRows([
    row('设备型号', device.value.model || device.value.device_name || device.value.model_name),
    row('IMEI', device.value.imei),
    row('IMEI2', device.value.imei2),
    row('用户串号', device.value.user_sn),
    row('SN', device.value.sn || info.value.serial_number),
    row('分类', device.value.category_name),
    row('容量', device.value.capacity || info.value.capacity || checkMeta.value.capacity),
    row('颜色', device.value.color || info.value.color || checkMeta.value.color),
    row('系统版本', device.value.system_version || info.value.system_version || checkMeta.value.system_version),
    row('保修信息', device.value.warranty_info || info.value.warranty_info || checkMeta.value.warranty_info, true),
    row('创建时间', formatTimeValue(device.value.create_at)),
    row('更新时间', formatTimeValue(device.value.update_at || device.value.update_time))
]))

const statusRows = computed<RowItem[]>(() => compactRows([
    row('设备状态', resolveBackendText(device.value.status_name, device.value.status)),
    row('确认状态', isConsigned.value ? '' : resolveBackendText(device.value.confirm_status_name, device.value.confirm_status)),
    row('打款状态', isConsigned.value ? '' : resolveBackendText(device.value.pay_status_name, device.value.pay_status)),
    row('处置类型', isConsigned.value ? '' : resolveBackendText(device.value.dispose_type_name, '')),
    row('处置状态', isConsigned.value ? '' : resolveBackendText(device.value.dispose_status_name, '')),
    priceRow('预估价', device.value.initial_price),
    priceRow(isConsigned.value ? '转代卖前报价' : '回收报价', device.value.final_price),
    priceRow(isConsigned.value ? '代卖参考价' : '代卖/卖货价', device.value.sell_price),
    priceRow('已打款金额', isConsigned.value ? '' : device.value.pay_amount),
    row('确认时间', isConsigned.value ? '' : formatTimeValue(device.value.confirm_time)),
    row('打款时间', isConsigned.value ? '' : formatTimeValue(device.value.pay_time)),
    row('确认备注', isConsigned.value ? '' : device.value.confirm_remark, true),
    row('备注', device.value.remark, true)
]))

const costAdjustRows = computed<RowItem[]>(() => compactRows([
    row('累计调整', formatSignedMoney(device.value.cost_adjust_amount), false),
    row('调整次数', `${ device.value.cost_adjust_count || 0 } 次`),
    row('最后调整', formatTimeValue(device.value.last_cost_adjust_time)),
    row('调整单号', device.value.last_cost_adjust_no)
]))

const previewAfterCost = computed(() => {
    const current = Number(device.value.final_price || 0)
    const amount = Number(costAdjustForm.value.adjust_amount || 0)
    let delta = 0
    if (costAdjustForm.value.adjust_type === 'refund_from_customer') delta = -amount
    else if (costAdjustForm.value.adjust_type === 'pay_to_customer') delta = amount
    else delta = costAdjustForm.value.direction === 'increase' ? amount : -amount
    return `¥${ formatMoney(Math.max(current + delta, 0)) }`
})

const checkRows = computed<RowItem[]>(() => compactRows([
    row('质检模板', device.value.check_template_name || device.value.check_template_id),
    row('质检员', device.value.checkUser?.real_name || device.value.checkUser?.username),
    row('质检时间', formatTimeValue(device.value.check_at)),
    row('电池健康', checkMeta.value.battery ? `${ checkMeta.value.battery }%` : ''),
    row('循环次数', checkMeta.value.battery_num),
    row('激活锁', hasValue(checkMeta.value.activation_lock) ? formatBooleanText(checkMeta.value.activation_lock) : ''),
    row('监管锁', hasValue(checkMeta.value.mdm_lock) ? formatBooleanText(checkMeta.value.mdm_lock) : '')
]))

const checkResultRows = computed<RowItem[]>(() => compactRows([
    row('卖家质检', device.value.check_result_seller, true),
    row('买家质检', device.value.check_result_buyer, true),
    row('质检摘要', shouldShowLegacyCheckResult.value ? device.value.check_result : '', true)
]))

const shouldShowLegacyCheckResult = computed(() => {
    const current = String(device.value.check_result || '').trim()
    if (!current) return false
    return current !== String(device.value.check_result_seller || '').trim()
        && current !== String(device.value.check_result_buyer || '').trim()
})

const checkMetaItems = computed(() => {
    const items = Array.isArray(checkMeta.value.result_items) ? checkMeta.value.result_items : []
    return items
        .map((item: any, index: number) => ({
            key: item.field_key || String(index),
            label: item.field_name || item.field_key || '质检项',
            value: formatCheckMetaItemValue(item)
        }))
        .filter((item: any) => item.value !== undefined && item.value !== null && String(item.value).trim() !== '')
})

const imageGroups = computed(() => {
    const groups: Array<{ label: string, items: ImageItem[] }> = []
    const sellerImages = buildImageItems(device.value.check_images_seller || device.value.check_images, device.value.check_images_seller_thumb_small || device.value.check_images_thumb_small)
    const buyerImages = buildImageItems(device.value.check_images_buyer, device.value.check_images_buyer_thumb_small)
    const paymentImages = buildImageItems(device.value.payment_images, device.value.payment_images_thumb_small)
    const summaryImages = buildImageItems(device.value.check_images, device.value.check_images_thumb_small)

    if (sellerImages.length) groups.push({ label: '卖家质检图片', items: sellerImages })
    if (buyerImages.length) groups.push({ label: '买家质检图片', items: buyerImages })
    if (!groups.length && summaryImages.length) groups.push({ label: '质检图片', items: summaryImages })
    if (paymentImages.length) groups.push({ label: '打款凭证', items: paymentImages })
    return groups
})

const consignmentRows = computed<RowItem[]>(() => {
    if (!isConsigned.value) return []
    const order = device.value.consignmentOrder || {}
    return compactRows([
        row('代卖单号', order.consignment_no || device.value.consignment_order_id),
        row('代卖状态', resolveBackendText(order.status_name || device.value.dispose_status_name, '')),
        priceRow('挂牌价', order.listing_price),
        priceRow('成交价', order.sold_price),
        priceRow('结算金额', order.settlement_amount),
        row('结算状态', resolveBackendText(order.pay_status_name, order.pay_status)),
        row('创建时间', formatTimeValue(order.create_at)),
        row('备注', order.remark, true)
    ])
})

const row = (label: string, value: any, long = false): RowItem => ({
    label,
    value: formatValue(value),
    long
})

const priceRow = (label: string, value: any): RowItem => ({
    label,
    value: Number(value || 0) > 0 ? `¥${ formatMoney(value) }` : '',
    price: true
})

const compactRows = (rows: RowItem[]) => {
    // 等于 0 不渲染
    return rows.filter((item) => item.value !== '' && Number(item.value) !== 0)
}

const formatValue = (value: any) => {
    if (value === undefined || value === null || value === '') return ''
    if (Array.isArray(value)) return value.filter(Boolean).join('、')
    return String(value)
}

const hasValue = (value: any) => value !== undefined && value !== null && value !== ''

const normalizeBoolean = (value: any) => {
    return value === true || value === 1 || value === '1' || value === 'true' || value === '开启' || value === 'On'
}

const formatBooleanText = (value: any) => normalizeBoolean(value) ? '开启' : '关闭'

const formatCheckMetaItemValue = (item: any) => {
    if (item?.component === 'switch') {
        return formatBooleanText(item.value)
    }

    if (Array.isArray(item?.labels) && item.labels.length) {
        return item.labels
            .map((label: any) => String(label))
            .filter((label: string) => label && label !== 'false' && label !== 'true')
            .join('、')
    }

    if (Array.isArray(item?.option_items) && item.option_items.length) {
        return item.option_items
            .map((option: any) => option.label || option.name || option.value)
            .filter(Boolean)
            .join('、')
    }

    if (Array.isArray(item?.value)) {
        return item.value.filter(Boolean).join('、')
    }

    if (hasValue(item?.value)) return String(item.value)
    return item?.text || ''
}

const resolveBackendText = (name: any, rawValue: any) => {
    if (name !== undefined && name !== null && String(name).trim() !== '') return String(name)
    if (rawValue === undefined || rawValue === null || rawValue === '') return ''
    return String(rawValue)
}

const formatTimeValue = (value: any) => {
    if (value === undefined || value === null || value === '') return ''
    return formatTime(value)
}

const formatSignedMoney = (value: any) => {
    const num = Number(value || 0)
    if (!Number.isFinite(num) || num === 0) return '¥0.00'
    return `${ num > 0 ? '+' : '-' }¥${ Math.abs(num).toFixed(2) }`
}

const normalizeObject = (value: any) => {
    if (!value) return {}
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
        } catch (error) {
            return {}
        }
    }
    if (Array.isArray(value)) return {}
    return typeof value === 'object' ? value : {}
}

const buildImageItems = (rawValue: any, thumbs: any) => {
    const urls = String(rawValue || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .map((item) => img(item))

    const thumbList = Array.isArray(thumbs)
        ? thumbs.map((item) => img(item))
        : []

    return urls.map((url, index) => ({
        url,
        thumb: thumbList[index] || url
    }))
}

const previewGroup = (items: ImageItem[], index: number) => {
    previewUrls.value = items.map((item) => item.url)
    previewCurrent.value = index
    previewVisible.value = true
}

const openCostAdjust = () => {
    costAdjustForm.value = {
        adjust_type: 'refund_from_customer',
        direction: 'decrease',
        adjust_amount: '',
        reason: '',
        customer_handled: 0,
        inventory_tip_confirmed: 0
    }
    costAdjustVisible.value = true
    loadCostAdjustLogs()
}

const closeCostAdjust = () => {
    costAdjustVisible.value = false
}

const loadCostAdjustLogs = async () => {
    const deviceId = device.value?.id || props.deviceData?.id
    if (!deviceId || !costAdjustAllowed.value) return
    try {
        const res: any = await getDeviceCostAdjustLogs(deviceId)
        costAdjustLogs.value = Array.isArray(res?.data) ? res.data : []
    } catch (error) {
        costAdjustLogs.value = []
    }
}

const loadCostAdjustAbility = async () => {
    const deviceId = device.value?.id || props.deviceData?.id
    if (!deviceId) {
        costAdjustAllowed.value = false
        return
    }
    try {
        const res: any = await getDeviceCostAdjustAbility(deviceId)
        costAdjustAllowed.value = Boolean(res?.data?.allowed)
    } catch (error) {
        costAdjustAllowed.value = false
    }
}

const submitCostAdjust = async () => {
    const deviceId = device.value?.id
    if (!deviceId) return
    if (!costAdjustForm.value.adjust_amount || Number(costAdjustForm.value.adjust_amount) <= 0) {
        uni.showToast({ title: '请输入大于0的调整金额', icon: 'none' })
        return
    }
    if (!String(costAdjustForm.value.reason || '').trim()) {
        uni.showToast({ title: '请填写调整原因', icon: 'none' })
        return
    }
    if (Number(costAdjustForm.value.inventory_tip_confirmed) !== 1) {
        uni.showToast({ title: '请先确认进销存成本同步提醒', icon: 'none' })
        return
    }

    costAdjustSubmitting.value = true
    try {
        const res: any = await adjustDeviceCost(deviceId, {
            ...costAdjustForm.value,
            adjust_amount: Number(costAdjustForm.value.adjust_amount)
        })
        const data = res?.data || {}
        latestDeviceData.value = {
            ...device.value,
            final_price: data.after_cost,
            cost_adjust_amount: Number(device.value.cost_adjust_amount || 0) + Number(data.adjust_delta || 0),
            cost_adjust_count: data.cost_adjust_count,
            last_cost_adjust_time: data.create_at,
            last_cost_adjust_no: data.adjust_no
        }
        await loadCostAdjustLogs()
        closeCostAdjust()
        uni.showToast({ title: '成本已调整，请同步进销存', icon: 'none' })
        emit('updated', latestDeviceData.value)
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '调整失败', icon: 'none' })
    } finally {
        costAdjustSubmitting.value = false
    }
}

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}
</script>

<style scoped lang="scss">
.device-detail-popup {
    height: 88vh;
    max-height: 88vh;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.detail-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    padding: 30rpx;
    border-bottom: 1rpx solid #f2f3f5;
    flex-shrink: 0;
}

.detail-header__main {
    flex: 1;
    min-width: 0;
}

.detail-title {
    font-size: 32rpx;
    font-weight: 600;
    color: #1f2937;
    line-height: 1.35;
}

.detail-subtitle {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
    word-break: break-all;
}

.detail-content {
    flex: 1;
    height: 0;
    min-height: 0;
    padding: 24rpx 30rpx 0;
    box-sizing: border-box;
    overflow: hidden;
}

.section {
    margin-bottom: 28rpx;
}

.section-title {
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
}

.section-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    margin-bottom: 16rpx;

    .section-title {
        margin-bottom: 6rpx;
    }
}

.section-subtitle {
    font-size: 22rpx;
    color: #94a3b8;
    line-height: 1.5;
}

.small-action {
    flex-shrink: 0;
    padding: 10rpx 20rpx;
    border-radius: 999rpx;
    background: #fff7ed;
    color: #ea580c;
    font-size: 22rpx;
    font-weight: 600;
}

.cost-tip {
    padding: 20rpx;
    border-radius: 14rpx;
    background: #fffbeb;
    color: #a16207;
    font-size: 22rpx;
    line-height: 1.6;
}

.info-list,
.check-result,
.meta-item,
.empty-state {
    border-radius: 14rpx;
    background: #f8fafc;
}

.info-list {
    padding: 6rpx 20rpx;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    padding: 16rpx 0;
    border-bottom: 1rpx solid #edf2f7;
    font-size: 24rpx;
}

.info-row:last-child {
    border-bottom: 0;
}

.info-row--top {
    align-items: flex-start;
}

.info-label {
    width: 132rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.info-value {
    flex: 1;
    color: #334155;
    line-height: 1.6;
    word-break: break-all;
}

.info-value--price {
    color: #ea580c;
    font-weight: 700;
}

.check-result-list {
    margin-top: 18rpx;
}

.check-result {
    padding: 20rpx;
}

.check-result + .check-result {
    margin-top: 14rpx;
}

.check-result__label {
    font-size: 22rpx;
    color: #64748b;
}

.check-result__text {
    margin-top: 10rpx;
    font-size: 24rpx;
    color: #334155;
    line-height: 1.7;
    white-space: pre-wrap;
}

.meta-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14rpx;
    margin-top: 18rpx;
}

.meta-item {
    padding: 18rpx;
}

.meta-item__label {
    display: block;
    font-size: 20rpx;
    color: #94a3b8;
}

.meta-item__value {
    display: block;
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #334155;
    line-height: 1.5;
    word-break: break-all;
}

.image-group + .image-group {
    margin-top: 20rpx;
}

.image-group__title {
    margin-bottom: 12rpx;
    font-size: 22rpx;
    color: #475569;
}

.image-group__list {
    display: flex;
    flex-wrap: wrap;
    gap: 14rpx;
}

.image-item {
    width: 132rpx;
    height: 132rpx;
    border-radius: 12rpx;
    overflow: hidden;
    background: #e5e7eb;
}

.image-item__img {
    width: 100%;
    height: 100%;
}

.timeline {
    padding-top: 4rpx;
}

.timeline__item {
    display: flex;
    gap: 18rpx;
}

.timeline__item + .timeline__item {
    margin-top: 20rpx;
}

.timeline__line-wrap {
    position: relative;
    width: 24rpx;
    flex-shrink: 0;
    display: flex;
    justify-content: center;
}

.timeline__dot {
    width: 16rpx;
    height: 16rpx;
    margin-top: 6rpx;
    border-radius: 50%;
    background: #2563eb;
}

.timeline__line {
    position: absolute;
    top: 28rpx;
    bottom: -20rpx;
    width: 2rpx;
    background: #dbeafe;
}

.timeline__content {
    flex: 1;
    min-width: 0;
    padding-bottom: 2rpx;
}

.timeline__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.timeline__status {
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
}

.timeline__time {
    font-size: 20rpx;
    color: #94a3b8;
    flex-shrink: 0;
}

.timeline__operator {
    margin-top: 6rpx;
    font-size: 20rpx;
    color: #64748b;
}

.timeline__remark,
.empty-state {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #475569;
    line-height: 1.6;
}

.empty-state {
    padding: 36rpx 20rpx;
    text-align: center;
    color: #94a3b8;
}

.loading-state {
    padding: 140rpx 0;
    text-align: center;
    font-size: 24rpx;
    color: #94a3b8;
}

.detail-footer {
    display: flex;
    gap: 20rpx;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
    background: #fff;
    flex-shrink: 0;
}

.cost-adjust-popup {
    height: 82vh;
    max-height: 82vh;
    background: #fff;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.cost-adjust-content {
    flex: 1;
    height: 0;
    min-height: 0;
    padding: 24rpx 30rpx;
    box-sizing: border-box;
    overflow: hidden;
}

.warning-box {
    padding: 20rpx;
    border-radius: 16rpx;
    background: #fffbeb;
    border: 1rpx solid #fde68a;
    color: #92400e;
    font-size: 24rpx;
    line-height: 1.6;
}

.form-block {
    margin-top: 24rpx;
}

.form-label {
    margin-bottom: 12rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #334155;
}

.type-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14rpx;
}

.type-grid--two {
    grid-template-columns: repeat(2, minmax(0, 1fr));
}

.type-item {
    min-height: 72rpx;
    padding: 0 12rpx;
    border-radius: 14rpx;
    border: 1rpx solid #e5e7eb;
    background: #f8fafc;
    color: #475569;
    font-size: 22rpx;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
}

.type-item--active {
    border-color: #f97316;
    background: #fff7ed;
    color: #ea580c;
}

.form-input,
.form-textarea {
    width: 100%;
    box-sizing: border-box;
    border-radius: 14rpx;
    background: #f8fafc;
    border: 1rpx solid #e5e7eb;
    color: #1f2937;
    font-size: 26rpx;
}

.form-input {
    height: 82rpx;
    padding: 0 22rpx;
}

.form-textarea {
    min-height: 150rpx;
    padding: 18rpx 22rpx;
    line-height: 1.6;
}

.form-help {
    margin-top: 10rpx;
    font-size: 22rpx;
    color: #ea580c;
}

.confirm-row {
    display: flex;
    align-items: flex-start;
    gap: 14rpx;
    margin-top: 22rpx;
    color: #475569;
    font-size: 23rpx;
    line-height: 1.5;
}

.checkbox {
    width: 30rpx;
    height: 30rpx;
    margin-top: 2rpx;
    border-radius: 8rpx;
    border: 2rpx solid #cbd5e1;
    background: #fff;
    flex-shrink: 0;
}

.checkbox--checked {
    border-color: #f97316;
    background: #f97316;
    box-shadow: inset 0 0 0 6rpx #fff;
}

.history-block {
    margin-top: 28rpx;
}

.history-item {
    padding: 18rpx 0;
    border-bottom: 1rpx solid #edf2f7;
}

.history-top {
    display: flex;
    justify-content: space-between;
    gap: 20rpx;
    font-size: 24rpx;
    font-weight: 600;
    color: #1f2937;
}

.history-amount {
    color: #ea580c;
}

.history-desc {
    margin-top: 8rpx;
    font-size: 21rpx;
    color: #94a3b8;
    line-height: 1.5;
}
</style>
