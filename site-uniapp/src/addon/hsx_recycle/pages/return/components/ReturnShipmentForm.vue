<template>
    <view class="return-shipment">
        <view class="summary-card">
            <view class="summary-card__row">
                <text class="summary-card__label">当前方式</text>
                <text class="summary-card__value">{{ currentModeName }}</text>
            </view>
            <view class="summary-card__row">
                <text class="summary-card__label">默认收件信息</text>
                <text class="summary-card__value">{{ receiverSummaryText }}</text>
            </view>
            <view v-if="shipmentMode === 'system'" class="summary-card__row">
                <text class="summary-card__label">报价状态</text>
                <text class="summary-card__value" :class="{ 'summary-card__value--active': Boolean(selectedQuoteSummary) }">
                    {{ selectedQuoteSummary || '未选择报价' }}
                </text>
            </view>
        </view>

        <view class="mode-grid">
            <view
                v-for="item in shipmentModes"
                :key="item.value"
                class="mode-item"
                :class="{ 'mode-item--active': shipmentMode === item.value }"
                @click="selectShipmentMode(item.value)"
            >
                <text class="mode-item__name">{{ item.name }}</text>
                <text class="mode-item__desc">{{ item.desc }}</text>
            </view>
        </view>

        <view v-if="shipmentMode === 'system'" class="shipment-panel">
            <view class="panel-head" @click="toggleSection('sender')">
                <view class="panel-mark panel-mark--sender">寄</view>
                <view class="panel-main">
                    <view class="panel-title">寄件信息</view>
                    <view class="panel-desc">{{ senderSummaryText }}</view>
                </view>
                <u-icon :name="expandedSections.sender ? 'arrow-up' : 'arrow-down'" size="18" color="#64748b"></u-icon>
            </view>
            <view v-show="expandedSections.sender" class="panel-body">
                <view class="quick-row">
                    <view class="quick-btn" @click.stop="useDefaultSender">使用默认</view>
                    <view class="quick-btn" @click.stop="fetchShopAddress">刷新地址库</view>
                </view>
                <view v-if="senderAddressOptions.length" class="sender-list">
                    <view
                        v-for="(item, index) in senderAddressOptions"
                        :key="senderAddressKey(item, index)"
                        class="sender-card"
                        :class="{ 'sender-card--active': isSenderSelected(item) }"
                        @click.stop="selectSenderAddress(item)"
                    >
                        <view class="sender-card__main">
                            <view class="sender-card__name">
                                <text>{{ item.contact_name || '未填写联系人' }}</text>
                                <text>{{ item.mobile || '-' }}</text>
                            </view>
                            <view class="sender-card__address">{{ formatShopAddress(item) || '-' }}</view>
                        </view>
                        <view class="sender-card__check">
                            <u-icon v-if="isSenderSelected(item)" name="checkmark" size="16" color="var(--hsx-primary)"></u-icon>
                        </view>
                    </view>
                </view>
                <view v-else class="tip-box">暂无可用寄件地址，请先在地址库维护默认发货地址。</view>
                <view v-if="senderAddressOptions.length && senderAddressNeedsArea" class="tip-box">
                    当前寄件地址缺少省/市/区信息，系统快递报价可能失败，请先到地址库补全。
                </view>
            </view>
        </view>

        <view v-if="shipmentMode === 'system'" class="shipment-panel">
            <view class="panel-head" @click="toggleSection('receiver')">
                <view class="panel-mark panel-mark--receiver">收</view>
                <view class="panel-main">
                    <view class="panel-title">退货地址</view>
                    <view class="panel-desc">{{ systemReceiverSummaryText }}</view>
                </view>
                <u-icon :name="expandedSections.receiver ? 'arrow-up' : 'arrow-down'" size="18" color="#64748b"></u-icon>
            </view>
            <view v-show="expandedSections.receiver" class="panel-body">
                <view class="field-row">
                    <text class="field-label">收件人</text>
                    <input v-model="shipmentForm.receiveName" class="field-input" placeholder="请输入收件人" />
                </view>
                <view class="field-row">
                    <text class="field-label">收件电话</text>
                    <input v-model="shipmentForm.receiveMobile" class="field-input" placeholder="请输入手机号" type="number" />
                </view>
                <view class="three-grid">
                    <input v-model="shipmentForm.receiveProvince" class="grid-input" placeholder="省" />
                    <input v-model="shipmentForm.receiveCity" class="grid-input" placeholder="市" />
                    <input v-model="shipmentForm.receiveDistrict" class="grid-input" placeholder="区县" />
                </view>
                <textarea v-model="shipmentForm.receiveAddress" class="field-textarea" placeholder="收件详细地址" />
            </view>
        </view>

        <view v-if="shipmentMode === 'system'" class="shipment-panel">
            <view class="panel-head" @click="toggleSection('quote')">
                <view class="panel-mark panel-mark--quote">价</view>
                <view class="panel-main">
                    <view class="panel-title">快递报价</view>
                    <view class="panel-desc">{{ selectedQuoteSummary || '填写信息后获取报价' }}</view>
                </view>
                <u-icon :name="expandedSections.quote ? 'arrow-up' : 'arrow-down'" size="18" color="#64748b"></u-icon>
            </view>
            <view v-show="expandedSections.quote" class="panel-body">
                <view class="field-row">
                    <text class="field-label">物品名称</text>
                    <input v-model="shipmentForm.goods" class="field-input" placeholder="退回设备" />
                </view>
                <view class="number-grid">
                    <view class="number-field">
                        <text>重量kg</text>
                        <input v-model="shipmentForm.weight" class="number-input" type="number" />
                    </view>
                    <view class="number-field">
                        <text>包裹数</text>
                        <input v-model="shipmentForm.packageCount" class="number-input" type="number" />
                    </view>
                    <view class="number-field">
                        <text>保价</text>
                        <input v-model="shipmentForm.guaranteeValueAmount" class="number-input" type="number" />
                    </view>
                </view>
                <view class="quote-btn" :class="{ 'quote-btn--disabled': quoteLoading }" @click.stop="runQuote">
                    {{ quoteLoading ? '报价中...' : '获取报价' }}
                </view>
                <view v-if="quoteList.length" class="quote-list">
                    <view
                        v-for="(item, index) in quoteList"
                        :key="quoteKey(item)"
                        class="quote-item"
                        :class="{ 'quote-item--active': selectedQuoteKey === quoteKey(item) }"
                        @click.stop="selectQuote(item)"
                    >
                        <view class="quote-item__main">
                            <view class="quote-item__name">
                                <text v-if="index === 0" class="quote-item__tag">最低</text>
                                <text>{{ item.productName || item.product_name || '快递产品' }}</text>
                            </view>
                            <text class="quote-item__channel">{{ item.channelName || item.channel_name || '系统快递' }}</text>
                        </view>
                        <text class="quote-item__price">¥{{ getQuotePrice(item) }}</text>
                    </view>
                </view>
                <view v-else class="tip-box">系统快递需要完整寄件和退货地址，获取报价后选择一个快递产品。</view>
            </view>
        </view>

        <view v-if="shipmentMode === 'manual'" class="shipment-panel">
            <view class="panel-title">手动快递</view>
            <view class="field-row">
                <text class="field-label">快递公司</text>
                <input v-model="confirmForm.express_company" class="field-input" placeholder="请输入快递公司" />
            </view>
            <view class="field-row">
                <text class="field-label">快递单号</text>
                <ScanCodeInput
                    v-model="confirmForm.express_no"
                    class="field-scan"
                    placeholder="请输入或扫码录入"
                    :maxlength="80"
                    input-align="right"
                    font-size="25rpx"
                    placeholder-class="text-[var(--text-color-light9)] text-[25rpx]"
                    :show-scan-text="false"
                />
            </view>
            <view class="tip-box">手动快递适合已经通过自己的快递发出的场景，快递公司和单号必填。</view>
        </view>

        <view v-if="isOfflineMode" class="shipment-panel">
            <view class="panel-title">{{ currentModeName }}</view>
            <view class="tip-box">{{ currentModeDesc || '线下退货无需快递单号，确认后会记录退货方式。' }}</view>
        </view>

        <view v-if="shipmentMode !== 'system'" class="shipment-panel">
            <view class="panel-title">收件信息</view>
            <view class="field-row">
                <text class="field-label">收件人</text>
                <input v-model="confirmForm.member_name" class="field-input" placeholder="请输入收件人姓名" />
            </view>
            <view class="field-row">
                <text class="field-label">手机号</text>
                <input v-model="confirmForm.member_mobile" class="field-input" placeholder="请输入手机号" type="number" />
            </view>
            <textarea v-model="confirmForm.return_address" class="field-textarea" placeholder="退回地址" />
        </view>

        <textarea v-model="confirmForm.remark" class="field-textarea" placeholder="备注（选填）" />
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { getReturnShipmentModes } from '@/addon/hsx_recycle/api/return-order'
import { createExpressOrderDirect, getExpressQuote } from '@/addon/hsx_recycle/api/express'
import { getShopAddressList, getShopDefaultDeliveryAddressInfo } from '@/addon/hsx_recycle/api/shop-address'
import ScanCodeInput from '@/addon/hsx_recycle/components/ScanCodeInput.vue'

type ShipmentMode = {
    value: string
    name: string
    desc?: string
    require_express_no?: boolean
    need_quote?: boolean
    express_company?: string
}

const props = defineProps<{
    detail?: any
    orderId?: number | string
    devices?: any[]
}>()

const DEFAULT_SHIPMENT_MODE = 'system'
const shipmentModes = ref<ShipmentMode[]>([])
const shipmentMode = ref(DEFAULT_SHIPMENT_MODE)
const quoteLoading = ref(false)
const quoteList = ref<any[]>([])
const selectedQuoteKey = ref('')
const lastQuoteSignature = ref('')
const shopAddressList = ref<any[]>([])
const selectedSenderId = ref<string>('')
const expandedSections = reactive({
    sender: false,
    receiver: false,
    quote: true
})

const confirmForm = reactive<Record<string, any>>({
    express_company: '',
    express_no: '',
    member_mobile: '',
    member_name: '',
    return_address: '',
    remark: ''
})

const shipmentForm = reactive<Record<string, any>>({
    deliveryType: '',
    senderName: '',
    senderMobile: '',
    senderProvince: '',
    senderCity: '',
    senderDistrict: '',
    senderAddress: '',
    receiveName: '',
    receiveMobile: '',
    receiveProvince: '',
    receiveCity: '',
    receiveDistrict: '',
    receiveAddress: '',
    goods: '退回设备',
    weight: 1,
    packageCount: 1,
    guaranteeValueAmount: 0,
    estimated_cost: 0,
    thirdOrderNo: ''
})

const currentMode = computed(() => shipmentModes.value.find(item => item.value === shipmentMode.value) || null)
const currentModeName = computed(() => currentMode.value?.name || shipmentMode.value || '退货方式')
const currentModeDesc = computed(() => currentMode.value?.desc || '')
const isOfflineMode = computed(() => !['system', 'manual'].includes(shipmentMode.value))
const senderAddressOptions = computed(() => {
    const list = shopAddressList.value.filter(item => Number(item.is_delivery_address) === 1)
    return list.length ? list : shopAddressList.value
})
const senderAddressNeedsArea = computed(() => {
    if (!shipmentForm.senderName && !shipmentForm.senderMobile && !shipmentForm.senderAddress) return false
    return !shipmentForm.senderProvince || !shipmentForm.senderCity || !shipmentForm.senderDistrict
})
const receiverSummaryText = computed(() => {
    const name = confirmForm.member_name || shipmentForm.receiveName || '-'
    const mobile = confirmForm.member_mobile || shipmentForm.receiveMobile || '-'
    const address = confirmForm.return_address || shipmentForm.receiveAddress || '-'
    return `${name} ${mobile} ${address}`.trim()
})
const senderSummaryText = computed(() => {
    const label = [shipmentForm.senderName, shipmentForm.senderMobile].filter(Boolean).join(' ')
    const address = [shipmentForm.senderProvince, shipmentForm.senderCity, shipmentForm.senderDistrict, shipmentForm.senderAddress].filter(Boolean).join('')
    return label || address ? `${label} ${address}`.trim() : '未填写寄件信息'
})
const systemReceiverSummaryText = computed(() => {
    const label = [shipmentForm.receiveName, shipmentForm.receiveMobile].filter(Boolean).join(' ')
    const address = [shipmentForm.receiveProvince, shipmentForm.receiveCity, shipmentForm.receiveDistrict, shipmentForm.receiveAddress].filter(Boolean).join('')
    return label || address ? `${label} ${address}`.trim() : '未填写退货地址'
})
const selectedQuoteSummary = computed(() => {
    const item = quoteList.value.find(row => quoteKey(row) === selectedQuoteKey.value)
    if (!item) return ''
    return `${item.productName || item.product_name || '快递产品'} ¥${getQuotePrice(item)}`
})

const normalizeModeList = (data: any): ShipmentMode[] => {
    const list = Array.isArray(data) ? data : Object.values(data || {})
    const normalized = list
        .map((item: any) => ({
            value: String(item.value || item.key || ''),
            name: String(item.name || item.label || ''),
            desc: item.desc || '',
            require_express_no: Boolean(item.require_express_no),
            need_quote: Boolean(item.need_quote),
            express_company: item.express_company || ''
        }))
        .filter((item: ShipmentMode) => item.value && item.name)
    return normalized
}

const getMemberAddress = (detail: any) => {
    const item = detail?.memberAddress || detail?.member_address || {}
    return [item.province_name, item.city_name, item.district_name, item.address].filter(Boolean).join('') || item.address || ''
}

const parseAddressText = (raw: string) => {
    let text = String(raw || '').trim().replace(/\s+/g, ' ')
    const mobileMatch = text.match(/1[3-9]\d{9}/)
    const mobile = mobileMatch ? mobileMatch[0] : ''
    if (mobile) text = text.replace(mobile, ' ').replace(/\s+/g, ' ').trim()
    const parts = text.split(' ').filter(Boolean)
    let name = ''
    if (parts.length > 1 && !/(省|市|区|县|州|盟|旗|镇|路|街|号|室)/.test(parts[0])) {
        name = parts.shift() || ''
        text = parts.join('')
    } else {
        text = text.replace(/\s/g, '')
    }
    const areaMatch = text.match(/^(.+?(?:省|自治区|市))(.+?(?:市|自治州|地区|盟))?(.+?(?:区|县|市|旗))?(.+)$/)
    return {
        name,
        mobile,
        province: areaMatch?.[1] || '',
        city: areaMatch?.[2] || '',
        district: areaMatch?.[3] || '',
        address: areaMatch?.[4] || text
    }
}

const fillSender = (item: any) => {
    if (!item) return
    const parsed = parseAddressText(`${item.contact_name || ''} ${item.mobile || ''} ${item.full_address || item.address || ''}`)
    selectedSenderId.value = String(item.id || '')
    shipmentForm.senderName = item.contact_name || parsed.name || shipmentForm.senderName
    shipmentForm.senderMobile = item.mobile || parsed.mobile || shipmentForm.senderMobile
    shipmentForm.senderProvince = item.province_name || parsed.province || shipmentForm.senderProvince
    shipmentForm.senderCity = item.city_name || parsed.city || shipmentForm.senderCity
    shipmentForm.senderDistrict = item.district_name || parsed.district || shipmentForm.senderDistrict
    shipmentForm.senderAddress = item.address || parsed.address || item.full_address || shipmentForm.senderAddress
}

const fillReceiverFromDetail = () => {
    const detail = props.detail || {}
    const rawAddress = detail.return_address || getMemberAddress(detail)
    const defaultName = detail.memberAddress?.name || detail.member_address?.name || ''
    const defaultMobile = detail.memberAddress?.mobile || detail.member_address?.mobile || ''
    const parsed = parseAddressText(`${detail.member_name || detail.member?.nickname || defaultName || ''} ${detail.member_mobile || detail.member?.mobile || defaultMobile || ''} ${rawAddress || ''}`)
    confirmForm.member_name = detail.member_name || detail.member?.nickname || defaultName || parsed.name || confirmForm.member_name
    confirmForm.member_mobile = detail.member_mobile || detail.member?.mobile || defaultMobile || parsed.mobile || confirmForm.member_mobile
    confirmForm.return_address = detail.return_address || rawAddress || confirmForm.return_address
    shipmentForm.receiveName = confirmForm.member_name || parsed.name
    shipmentForm.receiveMobile = confirmForm.member_mobile || parsed.mobile
    shipmentForm.receiveProvince = parsed.province || shipmentForm.receiveProvince
    shipmentForm.receiveCity = parsed.city || shipmentForm.receiveCity
    shipmentForm.receiveDistrict = parsed.district || shipmentForm.receiveDistrict
    shipmentForm.receiveAddress = parsed.address || rawAddress || shipmentForm.receiveAddress
}

const initFromDetail = () => {
    const detail = props.detail || {}
    confirmForm.express_company = detail.express_company || ''
    confirmForm.express_no = detail.express_no || ''
    confirmForm.remark = ''
    fillReceiverFromDetail()
    const firstDevice = Array.isArray(props.devices) ? props.devices[0] : null
    const model = firstDevice?.device?.model || firstDevice?.model || ''
    shipmentForm.goods = model ? `退回设备-${model}` : '退回设备'
    shipmentForm.thirdOrderNo = `return_${props.orderId || detail.id || Date.now()}`
}

const fetchShipmentModes = async () => {
    try {
        const res: any = await getReturnShipmentModes()
        shipmentModes.value = normalizeModeList(res.data)
        if (!shipmentModes.value.some(item => item.value === shipmentMode.value)) shipmentMode.value = shipmentModes.value[0]?.value || DEFAULT_SHIPMENT_MODE
    } catch (error) {
        shipmentModes.value = []
        shipmentMode.value = DEFAULT_SHIPMENT_MODE
    }
}

const fetchShopAddress = async () => {
    let defaultItem: any = null
    try {
        const defaultRes: any = await getShopDefaultDeliveryAddressInfo()
        if (defaultRes?.data) {
            defaultItem = defaultRes.data
            shopAddressList.value = mergeShopAddressList(shopAddressList.value, defaultItem)
        }
    } catch (error) {
    }

    try {
        const res: any = await getShopAddressList({})
        const list = res?.data?.data || res?.data || []
        shopAddressList.value = mergeShopAddressList(Array.isArray(list) ? list : [], defaultItem)
        const sender = senderAddressOptions.value.find(item => Number(item.is_default_delivery) === 1)
            || senderAddressOptions.value.find(item => String(item.id || '') === String(defaultItem?.id || ''))
            || senderAddressOptions.value[0]
        if (sender) fillSender(sender)
    } catch (error) {
        if (defaultItem) fillSender(defaultItem)
    }
}

const mergeShopAddressList = (list: any[], item: any) => {
    if (!item) return list
    const exists = list.some(row => String(row.id || '') === String(item.id || ''))
    return exists ? list : [item, ...list]
}

const senderAddressKey = (item: any, index: number) => {
    return String(item?.id || `${item?.contact_name || ''}-${item?.mobile || ''}-${item?.address || ''}-${index}`)
}

const formatShopAddress = (item: any) => {
    return [item.province_name, item.city_name, item.district_name, item.address].filter(Boolean).join('') || item.full_address || item.address || ''
}

const isSenderSelected = (item: any) => {
    if (selectedSenderId.value && item?.id) return String(item.id) === selectedSenderId.value
    return item?.contact_name === shipmentForm.senderName
        && item?.mobile === shipmentForm.senderMobile
        && formatShopAddress(item).includes(shipmentForm.senderAddress || '')
}

const selectSenderAddress = (item: any) => {
    fillSender(item)
    clearQuote()
}

const selectShipmentMode = (value: string) => {
    shipmentMode.value = value
}

const toggleSection = (key: 'sender' | 'receiver' | 'quote') => {
    expandedSections[key] = !expandedSections[key]
}

const clearQuote = () => {
    quoteList.value = []
    selectedQuoteKey.value = ''
    shipmentForm.deliveryType = ''
    shipmentForm.estimated_cost = 0
    confirmForm.express_no = ''
}

const useDefaultSender = async () => {
    await fetchShopAddress()
    if (!shipmentForm.senderName && !shipmentForm.senderMobile) {
        uni.showToast({ title: '暂无默认寄件地址', icon: 'none' })
    }
}

const clearSender = () => {
    selectedSenderId.value = ''
    shipmentForm.senderName = ''
    shipmentForm.senderMobile = ''
    shipmentForm.senderProvince = ''
    shipmentForm.senderCity = ''
    shipmentForm.senderDistrict = ''
    shipmentForm.senderAddress = ''
    clearQuote()
}

const quoteSignature = () => JSON.stringify({
    senderProvince: shipmentForm.senderProvince,
    senderCity: shipmentForm.senderCity,
    senderDistrict: shipmentForm.senderDistrict,
    senderAddress: shipmentForm.senderAddress,
    receiveProvince: shipmentForm.receiveProvince,
    receiveCity: shipmentForm.receiveCity,
    receiveDistrict: shipmentForm.receiveDistrict,
    receiveAddress: shipmentForm.receiveAddress,
    goods: shipmentForm.goods,
    weight: shipmentForm.weight,
    packageCount: shipmentForm.packageCount,
    guaranteeValueAmount: shipmentForm.guaranteeValueAmount
})

const getQuotePrice = (row: any) => {
    for (const field of ['estimatedCost', 'totalPrice', 'totalFee', 'totalAmount', 'price', 'fee', 'amount', 'prePrice', 'predictPrice', 'estimatedPrice', 'freight', 'freightFee', 'transportFee', 'channelFee']) {
        const value = Number(row[field])
        if (value > 0) return value.toFixed(2)
    }
    return ['channelFee', 'serviceCharge', 'serviceFee', 'guarantFee', 'guaranteeFee', 'incrementFee', 'otherFee']
        .reduce((total, field) => total + Number(row[field] || 0), 0)
        .toFixed(2)
}

const quoteKey = (row: any) => `${row.productCode || row.product_code || ''}-${row.productName || row.product_name || ''}-${getQuotePrice(row)}`

const selectQuote = (row: any) => {
    shipmentForm.deliveryType = String(row.productCode || row.product_code || '')
    shipmentForm.estimated_cost = Number(getQuotePrice(row))
    selectedQuoteKey.value = quoteKey(row)
    confirmForm.express_company = row.productName || row.product_name || currentModeName.value
    confirmForm.express_no = ''
}

const showRequired = (message: string) => {
    uni.showToast({ title: message, icon: 'none' })
    return false
}

const validateReceiver = () => {
    if (shipmentMode.value === 'system') {
        if (!String(shipmentForm.receiveName || '').trim()) return showRequired('请填写收件人')
        if (!String(shipmentForm.receiveMobile || '').trim()) return showRequired('请填写收件手机号')
        if (!String(shipmentForm.receiveAddress || '').trim()) return showRequired('请填写收件详细地址')
        return true
    }
    if (!String(confirmForm.member_name || '').trim()) return showRequired('请填写收件人')
    if (!String(confirmForm.member_mobile || '').trim()) return showRequired('请填写手机号')
    if (!String(confirmForm.return_address || '').trim()) return showRequired('请填写退回地址')
    return true
}

const validateShipmentAddress = () => {
    const fields = [
        ['senderName', '请填写寄件人'],
        ['senderMobile', '请填写寄件手机号'],
        ['senderProvince', '请填写寄件省份'],
        ['senderCity', '请填写寄件城市'],
        ['senderDistrict', '请填写寄件区县'],
        ['senderAddress', '请填写寄件详细地址'],
        ['receiveName', '请填写收件人'],
        ['receiveMobile', '请填写收件手机号'],
        ['receiveProvince', '请填写收件省份'],
        ['receiveCity', '请填写收件城市'],
        ['receiveDistrict', '请填写收件区县'],
        ['receiveAddress', '请填写收件详细地址']
    ]
    for (const [field, message] of fields) {
        if (!String(shipmentForm[field] || '').trim()) return showRequired(message)
    }
    if (!Number(shipmentForm.weight)) return showRequired('请填写包裹重量')
    if (!Number(shipmentForm.packageCount)) return showRequired('请填写包裹数')
    return true
}

const runQuote = async () => {
    if (quoteLoading.value) return
    if (!validateShipmentAddress()) return
    quoteLoading.value = true
    try {
        shipmentForm.deliveryType = ''
        const res: any = await getExpressQuote({ ...shipmentForm, deliveryType: '', productCode: '' })
        const list = Array.isArray(res.data) ? res.data : []
        quoteList.value = list.sort((left: any, right: any) => Number(getQuotePrice(left)) - Number(getQuotePrice(right)))
        lastQuoteSignature.value = quoteSignature()
        if (quoteList.value[0]) selectQuote(quoteList.value[0])
        uni.showToast({ title: `已获取${quoteList.value.length}个报价`, icon: 'none' })
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '获取报价失败', icon: 'none' })
    } finally {
        quoteLoading.value = false
    }
}

const buildBaseConfirmPayload = () => {
    if (shipmentMode.value === 'system') {
        confirmForm.member_name = shipmentForm.receiveName
        confirmForm.member_mobile = shipmentForm.receiveMobile
        confirmForm.return_address = `${shipmentForm.receiveProvince}${shipmentForm.receiveCity}${shipmentForm.receiveDistrict}${shipmentForm.receiveAddress}`
    }
    return {
        express_no: confirmForm.express_no,
        express_company: confirmForm.express_company,
        member_mobile: confirmForm.member_mobile,
        member_name: confirmForm.member_name,
        return_address: confirmForm.return_address,
        remark: confirmForm.remark
    }
}

const buildConfirmPayload = async () => {
    if (shipmentMode.value === 'system') {
        if (!validateShipmentAddress()) return null
        if (!shipmentForm.deliveryType || !selectedQuoteKey.value) {
            uni.showToast({ title: '请先获取报价并选择快递', icon: 'none' })
            return null
        }
        if (lastQuoteSignature.value && lastQuoteSignature.value !== quoteSignature()) {
            uni.showToast({ title: '报价已失效，请重新获取', icon: 'none' })
            clearQuote()
            return null
        }
        const res: any = await createExpressOrderDirect({
            ...shipmentForm,
            remark: confirmForm.remark,
            recycle_order_id: props.orderId || props.detail?.order_id || 0
        })
        const expressData = res.data || {}
        const expressNo = expressData.deliveryId || expressData.waybillNo || expressData.express_no || expressData.delivery_id || ''
        if (!expressNo) throw new Error('系统快递下单成功但未返回运单号')
        confirmForm.express_no = expressNo
        confirmForm.express_company = confirmForm.express_company || currentModeName.value
        return buildBaseConfirmPayload()
    }

    if (shipmentMode.value === 'manual') {
        if (!String(confirmForm.express_company || '').trim()) return showRequired('请填写快递公司') && null
        if (!String(confirmForm.express_no || '').trim()) return showRequired('请填写快递单号') && null
        if (!validateReceiver()) return null
        return buildBaseConfirmPayload()
    }

    if (!validateReceiver()) return null
    confirmForm.express_company = currentMode.value?.express_company || currentMode.value?.name || '线下退货'
    confirmForm.express_no = ''
    return buildBaseConfirmPayload()
}

watch(
    () => props.detail,
    () => initFromDetail(),
    { immediate: true }
)

watch(shipmentMode, (mode) => {
    clearQuote()
    if (mode === 'system') {
        expandedSections.sender = false
        expandedSections.receiver = false
        expandedSections.quote = true
        fillReceiverFromDetail()
        if (!shipmentForm.senderName && !shipmentForm.senderMobile) fetchShopAddress()
    } else if (mode !== 'manual') {
        confirmForm.express_company = currentMode.value?.express_company || currentMode.value?.name || ''
        confirmForm.express_no = ''
    } else {
        confirmForm.express_company = props.detail?.express_company || ''
    }
})

watch(
    () => quoteSignature(),
    (signature) => {
        if (lastQuoteSignature.value && signature !== lastQuoteSignature.value) clearQuote()
    }
)

onMounted(() => {
    fetchShipmentModes()
    fetchShopAddress()
})

defineExpose({
    buildConfirmPayload
})
</script>

<style scoped lang="scss">
.return-shipment {
    display: flex;
    flex-direction: column;
    gap: 16rpx;
}

.summary-card {
    padding: 18rpx;
    border-radius: 14rpx;
    background: #f8fafc;
}

.summary-card__row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
    font-size: 23rpx;
    color: #475569;
}

.summary-card__row + .summary-card__row {
    margin-top: 10rpx;
}

.summary-card__label {
    flex-shrink: 0;
    color: #64748b;
}

.summary-card__value {
    flex: 1;
    text-align: right;
    line-height: 1.45;
    color: #1f2937;
}

.summary-card__value--active {
    color: var(--hsx-primary);
    font-weight: 700;
}

.mode-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12rpx;
}

.mode-item {
    min-height: 112rpx;
    padding: 16rpx;
    border: 1rpx solid #e5e7eb;
    border-radius: 12rpx;
    background: #f8fafc;
}

.mode-item--active {
    border-color: var(--hsx-primary);
    background: var(--hsx-primary-50);
}

.mode-item__name {
    display: block;
    font-size: 25rpx;
    font-weight: 700;
    color: #1f2937;
}

.mode-item__desc {
    display: block;
    margin-top: 8rpx;
    font-size: 21rpx;
    line-height: 1.35;
    color: #64748b;
}

.shipment-panel {
    padding: 18rpx;
    border-radius: 14rpx;
    background: #f8fafc;
}

.panel-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
}

.panel-mark {
    width: 48rpx;
    height: 48rpx;
    flex-shrink: 0;
    border-radius: 12rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24rpx;
    font-weight: 800;
}

.panel-mark--sender {
    background: #ecfdf5;
    color: #047857;
}

.panel-mark--receiver {
    background: var(--hsx-primary-50);
    color: var(--hsx-primary-dark);
}

.panel-mark--quote {
    background: #fff7ed;
    color: #c2410c;
}

.panel-main {
    min-width: 0;
    flex: 1;
}

.panel-title {
    font-size: 25rpx;
    font-weight: 700;
    color: #1f2937;
}

.panel-desc {
    margin-top: 8rpx;
    font-size: 21rpx;
    line-height: 1.45;
    color: #64748b;
}

.panel-arrow {
    flex-shrink: 0;
    transform: rotate(0deg);
    transition: transform .2s ease;
}

.panel-arrow--open {
    transform: rotate(180deg);
}

.panel-body {
    margin-top: 16rpx;
}

.quick-row {
    display: flex;
    gap: 12rpx;
    margin-bottom: 8rpx;
}

.quick-btn {
    padding: 8rpx 14rpx;
    border-radius: 999rpx;
    border: 1rpx solid #cbd5e1;
    font-size: 22rpx;
    color: #475569;
    background: #fff;
}

.sender-list {
    display: flex;
    flex-direction: column;
    gap: 12rpx;
}

.sender-card {
    display: flex;
    align-items: center;
    gap: 14rpx;
    padding: 16rpx;
    border-radius: 12rpx;
    border: 1rpx solid #e2e8f0;
    background: #fff;
}

.sender-card--active {
    border-color: var(--hsx-primary);
    background: var(--hsx-primary-50);
}

.sender-card__main {
    min-width: 0;
    flex: 1;
}

.sender-card__name {
    display: flex;
    align-items: center;
    gap: 16rpx;
    font-size: 25rpx;
    font-weight: 700;
    color: #1f2937;
}

.sender-card__address {
    margin-top: 8rpx;
    font-size: 22rpx;
    line-height: 1.45;
    color: #64748b;
}

.sender-card__check {
    width: 36rpx;
    height: 36rpx;
    flex-shrink: 0;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1rpx solid #cbd5e1;
    background: #fff;
}

.sender-card--active .sender-card__check {
    border-color: var(--hsx-primary);
}

.field-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    min-height: 76rpx;
    border-bottom: 1rpx solid #e5e7eb;
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
    text-align: right;
    font-size: 25rpx;
    color: #1f2937;
}

.field-scan {
    flex: 1;
    min-width: 0;
    height: 76rpx;
}

.field-scan :deep(.u-input),
.field-scan :deep(.u-input__content) {
    height: 76rpx;
    min-height: 76rpx;
    padding: 0 !important;
    background: transparent !important;
}

.field-scan :deep(.u-input__content__field-wrapper__field) {
    height: 76rpx;
    line-height: 76rpx;
    color: #1f2937;
}

.three-grid,
.number-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12rpx;
    margin-top: 14rpx;
}

.grid-input {
    height: 68rpx;
    padding: 0 14rpx;
    border-radius: 10rpx;
    background: #fff;
    font-size: 24rpx;
    color: #1f2937;
}

.number-field {
    min-width: 0;
    padding: 12rpx;
    border-radius: 10rpx;
    background: #fff;
}

.number-field text {
    display: block;
    margin-bottom: 8rpx;
    font-size: 21rpx;
    color: #64748b;
}

.number-input {
    width: 100%;
    height: 42rpx;
    font-size: 25rpx;
    color: #1f2937;
}

.field-textarea {
    width: 100%;
    box-sizing: border-box;
    min-height: 112rpx;
    padding: 18rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    font-size: 25rpx;
}

.shipment-panel .field-textarea {
    margin-top: 14rpx;
    background: #fff;
}

.quote-btn {
    height: 70rpx;
    margin-top: 16rpx;
    border-radius: 999rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hsx-primary);
    color: #fff;
    font-size: 25rpx;
    font-weight: 700;
}

.quote-btn--disabled {
    opacity: .6;
}

.quote-list {
    margin-top: 14rpx;
    display: flex;
    flex-direction: column;
    gap: 12rpx;
}

.quote-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
    padding: 16rpx;
    border-radius: 12rpx;
    border: 1rpx solid #e2e8f0;
    background: #fff;
}

.quote-item--active {
    border-color: var(--hsx-primary);
    background: var(--hsx-primary-50);
}

.quote-item__main {
    min-width: 0;
}

.quote-item__name {
    display: flex;
    align-items: center;
    gap: 8rpx;
    font-size: 25rpx;
    font-weight: 700;
    color: #1f2937;
}

.quote-item__tag {
    flex-shrink: 0;
    padding: 2rpx 8rpx;
    border-radius: 999rpx;
    background: #dcfce7;
    color: #15803d;
    font-size: 18rpx;
}

.quote-item__channel {
    display: block;
    margin-top: 6rpx;
    font-size: 21rpx;
    color: #64748b;
}

.quote-item__price {
    flex-shrink: 0;
    font-size: 28rpx;
    font-weight: 800;
    color: #dc2626;
}

.tip-box {
    margin-top: 14rpx;
    padding: 14rpx;
    border-radius: 10rpx;
    background: #fff7ed;
    color: #9a3412;
    font-size: 22rpx;
    line-height: 1.45;
}
</style>
