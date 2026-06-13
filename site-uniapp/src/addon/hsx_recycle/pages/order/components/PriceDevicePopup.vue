<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="price-popup">
            <!-- 头部 -->
            <view class="price-header">
                <view class="price-title">设备定价</view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 text-[32rpx]" @click="handleClose"></text>
            </view>

            <!-- 设备信息 -->
            <view class="device-info">
                <view class="device-info-row">
                    <text class="nc-iconfont nc-icon-huishouzhan device-icon"></text>
                    <view class="device-main">
                        <view class="device-model">{{ device.model || '未知型号' }}</view>
                        <view class="device-meta">
                            <text v-if="device.imei">IMEI: {{ device.imei }}</text>
                        </view>
                    </view>
                    <view v-if="device.status_name" class="device-status">
                        <u-tag :text="device.status_name" type="primary" size="mini"></u-tag>
                    </view>
                </view>
            </view>

            <!-- 表单内容 -->
            <scroll-view scroll-y class="price-content">
                <view class="price-content__inner">
                    <!-- 质检结果展示 -->
                    <view v-if="device.check_result || device.check_result_seller" class="form-section">
                        <view class="section-title">
                            <text>质检结果</text>
                            <text v-if="detailLoading" class="section-loading">加载中...</text>
                        </view>
                        <view class="check-result-box">
                            {{ device.check_result_seller || device.check_result }}
                        </view>
                    </view>

                    <view v-if="imageGroups.length" class="form-section">
                        <view class="section-title">质检图片</view>
                        <view v-for="group in imageGroups" :key="group.label" class="image-group">
                            <view v-if="imageGroups.length > 1" class="image-group__label">{{ group.label }}</view>
                            <scroll-view scroll-x class="image-scroll">
                                <view class="image-row">
                                    <image
                                        v-for="(item, imageIndex) in group.items"
                                        :key="item.url"
                                        class="preview-image"
                                        :src="item.thumb"
                                        mode="aspectFill"
                                        @click="previewImages(group.items, imageIndex)"
                                    />
                                </view>
                            </scroll-view>
                        </view>
                    </view>
                    <view v-else-if="detailLoading" class="form-section">
                        <view class="section-title">质检图片</view>
                        <view class="image-empty">正在加载质检图片...</view>
                    </view>

                    <!-- 价格对比 -->
                    <view class="form-section">
                        <view class="section-title">价格信息</view>
                        <view class="price-compare">
                            <view v-if="device.initial_price && Number(device.initial_price) > 0" class="price-item">
                                <text class="price-label">初始报价</text>
                                <text class="price-value price-value--initial">¥{{ amountText(device.initial_price) }}</text>
                            </view>
                            <view v-if="device.before_price && device.before_price != device.initial_price" class="price-item">
                                <text class="price-label">上次定价</text>
                                <text class="price-value price-value--before">¥{{ amountText(device.before_price) }}</text>
                            </view>
                        </view>
                    </view>

                    <!-- 最终定价 -->
                    <view class="form-section">
                        <view class="section-title">
                            <text>最终定价</text>
                            <text class="text-[#e6a23c] text-[24rpx] ml-[16rpx]">*必填</text>
                        </view>
                        <view class="price-input-wrapper">
                            <text class="price-symbol">¥</text>
                            <u-input
                                v-model="formData.final_price"
                                type="number"
                                placeholder="请输入回收价格"
                                class="price-input"
                                border="none"
                                clearable
                                inputAlign="right"
                                fontSize="34rpx"
                                placeholderClass="text-[var(--text-color-light9)] text-[26rpx]"
                            ></u-input>
                        </view>
                        <view v-if="device.initial_price && Number(device.initial_price) > 0" class="text-[22rpx] text-[#999] mt-[8rpx]">
                            参考预估：¥{{ amountText(device.initial_price) }}
                        </view>
                    </view>

                    <!-- 卖货价格 -->
                    <view class="form-section">
                        <view class="section-title">卖货价格 <text class="text-[22rpx] text-[#999]">（选填，内部使用）</text></view>
                        <view class="price-input-wrapper">
                            <text class="price-symbol">¥</text>
                            <u-input
                                v-model="formData.sell_price"
                                type="number"
                                placeholder="选填"
                                class="price-input"
                                border="none"
                                clearable
                                inputAlign="right"
                                fontSize="34rpx"
                                placeholderClass="text-[var(--text-color-light9)] text-[26rpx]"
                            ></u-input>
                        </view>
                    </view>

                    <!-- 调价说明 -->
                    <view class="form-section">
                        <view class="section-title">价格备注</view>
                        <u-textarea
                            v-model="formData.remark"
                            placeholder="请输入定价理由或扣费说明..."
                            :maxlength="200"
                            :height="120"
                            count
                        ></u-textarea>
                    </view>

                    <!-- 销售去向（对齐 PC：ERP 已连接走仓库级联自动定流向，否则渠道单选） -->
                    <view v-if="warehouseMode || saleDestinationOptions.length" class="form-section">
                        <view class="section-title">销售去向</view>

                        <template v-if="warehouseMode">
                            <view class="dest-hint">选择目标仓库即可（库位可不选，入库时再定）；流向按仓库类型自动确定。</view>
                            <view class="dest-chips">
                                <view
                                    v-for="w in erpWarehouses"
                                    :key="w.id"
                                    class="dest-chip"
                                    :class="{ 'dest-chip--active': Number(formData.target_warehouse_id) === Number(w.id) }"
                                    @click="selectWarehouse(w)"
                                >{{ w.warehouse_name }} · {{ WH_TYPE_LABEL[w.business_type || 'mall'] || '商城' }}</view>
                            </view>

                            <template v-if="currentWarehouseLocations.length">
                                <view class="dest-sub-label">库位（可选）</view>
                                <view class="dest-chips">
                                    <view
                                        v-for="loc in currentWarehouseLocations"
                                        :key="loc.id"
                                        class="dest-chip"
                                        :class="{ 'dest-chip--active': Number(formData.target_location_id) === Number(loc.id) }"
                                        @click="selectLocation(loc)"
                                    >{{ loc.location_name }}</view>
                                </view>
                            </template>

                            <view v-if="saleDestinationText" class="dest-current">
                                流向：{{ saleDestinationText }}<text v-if="saleDestinationDescription" class="dest-desc">（{{ saleDestinationDescription }}）</text>
                            </view>
                        </template>

                        <template v-else>
                            <view class="dest-chips">
                                <view
                                    v-for="item in saleDestinationOptions"
                                    :key="item.value"
                                    class="dest-chip"
                                    :class="{ 'dest-chip--active': formData.sale_destination === item.value }"
                                    @click="selectDestination(item.value)"
                                >{{ item.label }}</view>
                            </view>
                            <view v-if="saleDestinationDescription" class="dest-desc">{{ saleDestinationDescription }}</view>
                        </template>
                    </view>

                    <view class="form-section">
                        <view class="section-title">整备安排</view>
                        <view class="refurbish-toggle">
                            <view class="refurbish-toggle__text" @click="toggleRefurbishment">
                                <view class="refurbish-toggle__title">是否需要整备</view>
                                <view class="refurbish-toggle__desc">默认无需整备；开启后入库到 ERP 会自动生成整备工单。</view>
                            </view>
                            <view class="refurbish-toggle__switch" @tap.stop>
                                <u-switch v-model="formData.refurbishment_required" :activeValue="1" :inactiveValue="0" size="22"></u-switch>
                            </view>
                        </view>

                        <view v-if="formData.refurbishment_required === 1" class="refurbish-form">
                            <view class="form-label">整备负责人 <text class="required">*</text></view>
                            <picker :range="staffOptions" range-key="label" @change="handleStaffChange">
                                <view class="picker-field">
                                    <text :class="{ placeholder: !selectedStaffName }">{{ selectedStaffName || '请选择负责人' }}</text>
                                    <text class="nc-iconfont nc-icon-youV6xx"></text>
                                </view>
                            </picker>

                            <view class="form-label mt-24">建议整备项目</view>
                            <view class="preset-grid">
                                <view
                                    v-for="item in refurbishmentPresets"
                                    :key="item.key"
                                    :class="['preset-item', { active: formData.refurbishment_item_keys.includes(item.key) }]"
                                    @click="toggleRefurbishmentItem(item.key)"
                                >
                                    {{ item.name }}
                                </view>
                            </view>
                            <u-input
                                v-model="formData.refurbishment_custom_item"
                                placeholder="其他项目，例如：更换尾插、补胶"
                                border="surround"
                                class="mt-16"
                            ></u-input>

                            <view class="form-label mt-24">预估整备成本</view>
                            <view class="price-input-wrapper">
                                <text class="price-symbol">¥</text>
                                <u-input
                                    v-model="formData.refurbishment_estimated_cost"
                                    type="number"
                                    placeholder="选填"
                                    class="price-input"
                                    border="none"
                                    inputAlign="right"
                                    fontSize="30rpx"
                                ></u-input>
                            </view>

                            <view class="form-label mt-24">整备说明</view>
                            <u-textarea
                                v-model="formData.refurbishment_reason"
                                placeholder="例如：电池效率低，建议更换电池后销售"
                                :maxlength="300"
                                :height="100"
                                count
                            ></u-textarea>
                        </view>
                    </view>
                </view>
            </scroll-view>

            <!-- 底部按钮 -->
            <view class="price-footer">
                <u-button @click="handleClose" :customStyle="{flex: 1, marginRight: '20rpx'}">
                    取消
                </u-button>
                <u-button type="primary" @click="handleSubmit" :customStyle="{flex: 2}" :loading="submitting">
                    确认定价
                </u-button>
            </view>
        </view>
    </u-popup>

</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { confirmPrice, getDevice, getRefurbishmentOptions, getSaleDestinationOptions, getStaffOptions } from '@/addon/hsx_recycle/api/order'
import { img } from '@/utils/common'
import { previewImages as openPreview } from '@/addon/hsx_recycle/utils/preview'
import { useRecycleSubmit } from '@/addon/hsx_recycle/hooks/useRecycleSubmit'
import { confirmDanger } from '@/addon/hsx_recycle/utils/confirm'

interface Props {
    visible: boolean
    deviceData: any
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
// 提交守卫复用现有 submitting，模板 :loading 绑定无需改动
const submit = useRecycleSubmit(submitting)
const detailLoading = ref(false)
const deviceDetail = ref<any>(null)
const device = computed(() => deviceDetail.value || props.deviceData || {})
const staffOptions = ref<Array<{ uid: number, label: string }>>([])
const refurbishmentPresets = ref<Array<{ key: string, name: string, type: string }>>([])

const formData = ref({
    final_price: '',
    sell_price: '',
    remark: '',
    sale_destination: '',
    target_warehouse_id: 0,
    target_warehouse_name: '',
    target_location_id: 0,
    target_location_name: '',
    refurbishment_required: 0,
    refurbishment_assignee_uid: 0,
    refurbishment_reason: '',
    refurbishment_item_keys: [] as string[],
    refurbishment_custom_item: '',
    refurbishment_estimated_cost: ''
})

// ===== 销售去向（对齐 PC 端 PriceFormDialog） =====
interface ErpWarehouse {
    id: number
    warehouse_name: string
    business_type?: string
    is_default?: number
    locations?: Array<{ id: number; location_name: string }>
}
const saleDestinationOptions = ref<Array<{ value: string; label: string; description?: string }>>([])
const erpWarehouses = ref<ErpWarehouse[]>([])
const erpConnected = ref(false)
// 仓库业务类型 → 销售流向 / 中文标签（与后端 RecycleOrderDict 保持一致）
const WH_TYPE_DEST: Record<string, string> = { mall: 'mall', peer: 'peer', scrap: 'scrap', hold: 'hold' }
const WH_TYPE_LABEL: Record<string, string> = { mall: '商城', peer: '同行', scrap: '报废', hold: '暂存' }
// ERP 已连接且有可用仓库时，以仓库为主选项（选仓即定流向）
const warehouseMode = computed(() => erpConnected.value && erpWarehouses.value.length > 0)
const saleDestinationText = computed(() =>
    saleDestinationOptions.value.find((i) => i.value === formData.value.sale_destination)?.label || ''
)
const saleDestinationDescription = computed(() =>
    saleDestinationOptions.value.find((i) => i.value === formData.value.sale_destination)?.description || ''
)
const currentWarehouseLocations = computed(() => {
    const w = erpWarehouses.value.find((item) => Number(item.id) === Number(formData.value.target_warehouse_id))
    return w?.locations || []
})

const buildSaleDestForm = (device: Record<string, any> = {}) => ({
    sale_destination: device.sale_destination || '',
    target_warehouse_id: Number(device.target_warehouse_id || 0),
    target_warehouse_name: device.target_warehouse_name || '',
    target_location_id: Number(device.target_location_id || 0),
    target_location_name: device.target_location_name || ''
})

const loadSaleDestinationOptions = async () => {
    try {
        const res: any = await getSaleDestinationOptions()
        saleDestinationOptions.value = res?.data?.items || []
        erpWarehouses.value = res?.data?.warehouses || []
        erpConnected.value = !!res?.data?.erp_connected
        if (warehouseMode.value) {
            // 仓库模式：无已选仓时默认选中默认仓 / 首个仓（对齐 PC，不再为空）
            if (!Number(formData.value.target_warehouse_id)) {
                const def = erpWarehouses.value.find((w) => Number(w.is_default) === 1) || erpWarehouses.value[0]
                if (def) selectWarehouse(def)
            }
        } else if (!saleDestinationOptions.value.some((i) => i.value === formData.value.sale_destination)) {
            // 渠道模式：当前去向不在选项中则回退到第一个
            formData.value.sale_destination = saleDestinationOptions.value[0]?.value || ''
        }
    } catch (error) {
        saleDestinationOptions.value = []
        erpWarehouses.value = []
        erpConnected.value = false
    }
}

const selectWarehouse = (w: ErpWarehouse) => {
    formData.value.target_warehouse_id = Number(w.id)
    formData.value.target_warehouse_name = w.warehouse_name || ''
    // 选仓即定流向
    formData.value.sale_destination = WH_TYPE_DEST[w.business_type || 'mall'] || 'hold'
    // 切换仓库后清空已选库位
    formData.value.target_location_id = 0
    formData.value.target_location_name = ''
}

const selectLocation = (loc: { id: number; location_name: string }) => {
    if (Number(formData.value.target_location_id) === Number(loc.id)) {
        formData.value.target_location_id = 0
        formData.value.target_location_name = ''
    } else {
        formData.value.target_location_id = Number(loc.id)
        formData.value.target_location_name = loc.location_name || ''
    }
}

const selectDestination = (value: string) => {
    formData.value.sale_destination = value
}

const selectedStaffName = computed(() => {
    const uid = Number(formData.value.refurbishment_assignee_uid || 0)
    return staffOptions.value.find((item) => Number(item.uid) === uid)?.label || ''
})

const amountText = (value: any) => {
    const amount = Number(value || 0)
    if (!Number.isFinite(amount)) return '0'
    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2)
}

type ImageItem = {
    url: string
    thumb: string
}

const imageGroups = computed(() => {
    const groups: Array<{ label: string, items: ImageItem[] }> = []
    const current = device.value || {}
    const sellerImages = buildImageItems(current.check_images_seller || current.check_images, current.check_images_seller_thumb_small || current.check_images_thumb_small)
    const buyerImages = buildImageItems(current.check_images_buyer, current.check_images_buyer_thumb_small)
    const summaryImages = buildImageItems(current.check_images, current.check_images_thumb_small)

    if (sellerImages.length) groups.push({ label: '卖家质检图片', items: sellerImages })
    if (buyerImages.length) groups.push({ label: '买家质检图片', items: buyerImages })
    if (!groups.length && summaryImages.length) groups.push({ label: '质检图片', items: summaryImages })

    return groups
})

const buildImageItems = (rawValue: any, thumbs: any) => {
    const urls = String(rawValue || '')
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
        .map((item) => img(item))

    const thumbList = Array.isArray(thumbs)
        ? thumbs.map((item: string) => img(item))
        : String(thumbs || '')
            .split(',')
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => img(item))

    return urls.map((url, index) => ({
        url,
        thumb: thumbList[index] || url
    }))
}

watch(() => props.visible, (val) => {
    show.value = val
    if (val && props.deviceData) {
        deviceDetail.value = props.deviceData
        const fp = props.deviceData.final_price
        formData.value = {
            final_price: (fp && Number(fp) > 0) ? amountText(fp) : (props.deviceData.initial_price ? amountText(props.deviceData.initial_price) : ''),
            sell_price: props.deviceData.sell_price ? amountText(props.deviceData.sell_price) : '',
            remark: props.deviceData.remark || '',
            ...buildSaleDestForm(props.deviceData),
            ...buildRefurbishmentForm(props.deviceData)
        }
        loadStaffOptions()
        loadRefurbishmentOptions()
        loadSaleDestinationOptions()
        loadDeviceDetail()
    } else if (!val) {
        deviceDetail.value = null
        detailLoading.value = false
    }
})

const loadDeviceDetail = async () => {
    const id = props.deviceData?.id
    if (!id) return
    detailLoading.value = true
    try {
        const res: any = await getDevice(id)
        const detail = res?.data || null
        if (!detail) return
        deviceDetail.value = {
            ...(props.deviceData || {}),
            ...detail
        }
        const fp = deviceDetail.value.final_price
        formData.value = {
            final_price: (fp && Number(fp) > 0) ? amountText(fp) : (deviceDetail.value.initial_price ? amountText(deviceDetail.value.initial_price) : ''),
            sell_price: deviceDetail.value.sell_price ? amountText(deviceDetail.value.sell_price) : '',
            remark: deviceDetail.value.remark || '',
            ...buildSaleDestForm(deviceDetail.value),
            ...buildRefurbishmentForm(deviceDetail.value)
        }
    } catch (error) {
        // 图片只是辅助信息，加载失败不阻断定价。
    } finally {
        detailLoading.value = false
    }
}

const loadStaffOptions = async () => {
    try {
        const res: any = await getStaffOptions()
        const rows = Array.isArray(res?.data) ? res.data : []
        staffOptions.value = rows.map((item: any) => ({
            uid: Number(item.uid),
            label: item.real_name || item.username || `员工#${item.uid}`
        }))
        ensureAssigneeOption(device.value)
    } catch (error) {
        staffOptions.value = []
        ensureAssigneeOption(device.value)
    }
}

const ensureAssigneeOption = (source: any) => {
    const uid = Number(source?.refurbishment_assignee_uid || 0)
    if (uid <= 0 || staffOptions.value.some((item) => Number(item.uid) === uid)) return
    staffOptions.value.unshift({
        uid,
        label: source?.refurbishment_assignee_name || `员工#${uid}`
    })
}

const loadRefurbishmentOptions = async () => {
    try {
        const res: any = await getRefurbishmentOptions()
        refurbishmentPresets.value = res?.data?.items || []
        formData.value = {
            ...formData.value,
            ...buildRefurbishmentForm(device.value)
        }
    } catch (error) {
        refurbishmentPresets.value = []
    }
}

const parseRefurbishmentItems = (value: any) => {
    if (!value) return { keys: [] as string[], custom: '' }
    let items = value
    if (typeof value === 'string') {
        try { items = JSON.parse(value) } catch { items = [] }
    }
    if (!Array.isArray(items)) return { keys: [], custom: '' }
    const nameMap = new Map(refurbishmentPresets.value.map((item) => [item.name, item.key]))
    const keySet = new Set(refurbishmentPresets.value.map((item) => item.key))
    const keys: string[] = []
    const custom: string[] = []
    items.forEach((item: any) => {
        const rawKey = typeof item === 'object' && item ? String(item.item_key || item.key || '') : ''
        const name = typeof item === 'string' ? item : (item.item_name || item.name || '')
        const key = rawKey && keySet.has(rawKey) ? rawKey : nameMap.get(name)
        if (key) keys.push(key)
        else if (name) custom.push(name)
    })
    return { keys: Array.from(new Set(keys)), custom: custom.join('、') }
}

const buildRefurbishmentForm = (source: any) => {
    const parsed = parseRefurbishmentItems(source?.refurbishment_items)
    return {
        refurbishment_required: Number(source?.refurbishment_required || 0),
        refurbishment_assignee_uid: Number(source?.refurbishment_assignee_uid || 0),
        refurbishment_reason: source?.refurbishment_reason || '',
        refurbishment_item_keys: parsed.keys,
        refurbishment_custom_item: parsed.custom,
        refurbishment_estimated_cost: source?.refurbishment_estimated_cost ? amountText(source.refurbishment_estimated_cost) : ''
    }
}

const handleStaffChange = (event: any) => {
    const index = Number(event.detail.value || 0)
    formData.value.refurbishment_assignee_uid = Number(staffOptions.value[index]?.uid || 0)
}

const toggleRefurbishment = () => {
    formData.value.refurbishment_required = formData.value.refurbishment_required === 1 ? 0 : 1
}

const toggleRefurbishmentItem = (key: string) => {
    const list = formData.value.refurbishment_item_keys
    const index = list.indexOf(key)
    if (index >= 0) list.splice(index, 1)
    else list.push(key)
}

const buildRefurbishmentItems = () => {
    const items = formData.value.refurbishment_item_keys
        .map((key) => refurbishmentPresets.value.find((item) => item.key === key))
        .filter(Boolean)
        .map((item: any) => ({ item_key: item.key, item_name: item.name, item_type: item.type }))
    const custom = formData.value.refurbishment_custom_item.trim()
    if (custom) items.push({ item_name: custom, item_type: 'other' })
    return items
}

const handleClose = () => {
    emit('update:visible', false)
}

const previewImages = (items: ImageItem[], index: number) => {
    openPreview(items.map((item) => item.url), index)
}

const handleSubmit = async () => {
    if (!device.value?.id) {
        uni.showToast({ title: '请选择设备', icon: 'none' })
        return
    }

    if (!formData.value.final_price || Number(formData.value.final_price) <= 0) {
        uni.showToast({ title: '请输入有效的回收价格', icon: 'none' })
        return
    }

    if (formData.value.refurbishment_required === 1 && !formData.value.refurbishment_assignee_uid) {
        uni.showToast({ title: '请选择整备负责人', icon: 'none' })
        return
    }

    // 金额二次确认：定价不可轻率，高亮金额给店员复核
    const price = Number(formData.value.final_price)
    const confirmed = await confirmDanger(`确认以 ¥${ price.toFixed(2) } 对该设备定价？`, {
        title: '确认定价',
        confirmText: '确认定价'
    })
    if (!confirmed) return

    // 提交守卫：进行中忽略重复点击，成功后统一提示
    await submit.run(async () => {
        await confirmPrice(device.value.id, {
            final_price: formData.value.final_price,
            sell_price: formData.value.sell_price || 0,
            remark: formData.value.remark,
            sale_destination: formData.value.sale_destination,
            target_warehouse_id: formData.value.target_warehouse_id || 0,
            target_warehouse_name: formData.value.target_warehouse_name || '',
            target_location_id: formData.value.target_location_id || 0,
            target_location_name: formData.value.target_location_name || '',
            refurbishment_required: formData.value.refurbishment_required,
            refurbishment_assignee_uid: formData.value.refurbishment_required === 1 ? formData.value.refurbishment_assignee_uid : 0,
            refurbishment_reason: formData.value.refurbishment_required === 1 ? formData.value.refurbishment_reason : '',
            refurbishment_items: formData.value.refurbishment_required === 1 ? buildRefurbishmentItems() : [],
            refurbishment_estimated_cost: formData.value.refurbishment_required === 1 ? Number(formData.value.refurbishment_estimated_cost || 0) : 0
        })
        emit('success')
        handleClose()
    }, { success: '定价成功' })
}
</script>

<style scoped lang="scss">
.price-popup {
    background: #fff;
    height: 80vh;
    max-height: 80vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.price-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 30rpx;
    border-bottom: 1rpx solid #f5f5f5;
    flex-shrink: 0;
}

.price-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
}

.device-info {
    padding: 24rpx 30rpx;
    background: #f8f9fa;
    flex-shrink: 0;
}

.device-info-row {
    display: flex;
    align-items: center;
}

.device-icon {
    font-size: 44rpx;
    margin-right: 16rpx;
    color: var(--hsx-primary);
}

.device-main {
    flex: 1;
}

.device-model {
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
    margin-bottom: 8rpx;
}

.device-meta {
    font-size: 24rpx;
    color: #999;
}

.device-status {
    margin-left: 16rpx;
}

.price-content {
    flex: 1;
    min-height: 0;
    height: 0;
    box-sizing: border-box;
}

.price-content__inner {
    padding: 20rpx 30rpx;
    box-sizing: border-box;
}

.form-section {
    margin-bottom: 32rpx;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    font-size: 28rpx;
    font-weight: 500;
    color: #333;
    margin-bottom: 16rpx;
}

.section-loading {
    flex-shrink: 0;
    font-size: 22rpx;
    color: #909399;
    font-weight: 400;
}

.check-result-box {
    padding: 20rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    font-size: 26rpx;
    color: #666;
    line-height: 1.6;
}

.price-compare {
    display: flex;
    gap: 24rpx;
}

.price-item {
    flex: 1;
    padding: 20rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    text-align: center;
}

.price-label {
    display: block;
    font-size: 24rpx;
    color: #999;
    margin-bottom: 8rpx;
}

.price-value {
    display: block;
    font-size: 32rpx;
    font-weight: bold;
}

.price-value--initial {
    color: #909399;
}

.price-value--before {
    color: #e6a23c;
}

.price-input-wrapper {
    display: flex;
    align-items: center;
    padding: 20rpx 24rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
    border: 2rpx solid #e4e7ed;
}

.price-symbol {
    font-size: 36rpx;
    font-weight: bold;
    color: #e6a23c;
    margin-right: 12rpx;
}

.price-input {
    flex: 1;
    font-size: 36rpx;
    font-weight: bold;
    color: #333;
}

.dest-hint {
    font-size: 22rpx;
    color: var(--hsx-text-secondary);
    line-height: 32rpx;
    margin-bottom: 16rpx;
}

.dest-sub-label {
    margin: 18rpx 0 12rpx;
    font-size: 24rpx;
    color: var(--hsx-text-regular);
}

.dest-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.dest-chip {
    padding: 14rpx 24rpx;
    border-radius: 999rpx;
    background: var(--hsx-fill-light);
    border: 1rpx solid var(--hsx-border);
    color: var(--hsx-text-regular);
    font-size: 24rpx;
    line-height: 32rpx;
}

.dest-chip--active {
    background: var(--hsx-primary-50);
    border-color: var(--hsx-primary);
    color: var(--hsx-primary);
    font-weight: 500;
}

.dest-current {
    margin-top: 16rpx;
    font-size: 24rpx;
    color: var(--hsx-text-strong);
}

.dest-desc {
    font-size: 22rpx;
    color: var(--hsx-text-secondary);
}

.refurbish-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
    padding: 22rpx 24rpx;
    background: #f8f9fa;
    border-radius: 12rpx;
}

.refurbish-toggle__text {
    flex: 1;
    min-width: 0;
}

.refurbish-toggle__switch {
    flex-shrink: 0;
}

.refurbish-toggle__title {
    font-size: 27rpx;
    color: #333;
    font-weight: 500;
}

.refurbish-toggle__desc {
    margin-top: 6rpx;
    font-size: 22rpx;
    color: #909399;
    line-height: 1.4;
}

.refurbish-form {
    margin-top: 20rpx;
}

.form-label {
    margin-bottom: 12rpx;
    font-size: 25rpx;
    color: #333;
    font-weight: 500;
}

.required {
    color: #f56c6c;
}

.mt-16 {
    margin-top: 16rpx;
}

.mt-24 {
    margin-top: 24rpx;
}

.picker-field {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 76rpx;
    padding: 0 24rpx;
    border: 2rpx solid #e4e7ed;
    border-radius: 12rpx;
    background: #f8f9fa;
    font-size: 26rpx;
    color: #333;
}

.placeholder {
    color: #999;
}

.preset-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 14rpx;
}

.preset-item {
    padding: 12rpx 20rpx;
    border: 2rpx solid #e4e7ed;
    border-radius: 999rpx;
    background: #fff;
    color: #606266;
    font-size: 24rpx;
}

.preset-item.active {
    border-color: var(--hsx-primary);
    background: #ecf5ff;
    color: var(--hsx-primary);
}

.image-group + .image-group {
    margin-top: 16rpx;
}

.image-group__label {
    margin-bottom: 10rpx;
    font-size: 22rpx;
    color: #64748b;
}

.image-scroll {
    width: 100%;
    white-space: nowrap;
}

.image-row {
    display: flex;
    gap: 14rpx;
    padding-bottom: 4rpx;
}

.image-empty {
    padding: 22rpx;
    border-radius: 12rpx;
    background: #f8f9fa;
    font-size: 24rpx;
    color: #909399;
}

.preview-image {
    width: 132rpx;
    height: 132rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
    background: #f1f5f9;
}

.price-footer {
    display: flex;
    padding: 20rpx 30rpx;
    border-top: 1rpx solid #f5f5f5;
    background: #fff;
    flex-shrink: 0;
}
</style>
