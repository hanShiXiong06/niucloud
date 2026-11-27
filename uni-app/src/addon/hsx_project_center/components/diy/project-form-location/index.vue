<template>
    <view v-if="diyComponent.viewFormDetail" class="form-item-frame">
        <view class="location-detail-view">
            <view class="location-detail-label">{{ diyComponent.field.name }}</view>
            <view v-if="hasLocation" class="location-detail-body">
                <view class="location-detail-name">{{ locationValue.name || '已采集位置' }}</view>
                <view class="location-detail-address">{{ locationValue.full_address || '暂未解析详细地址' }}</view>
                <view v-if="locationValue.address_status === 'failed'" class="location-resolve-error">{{ mapProviderText }}地址解析失败：{{ locationValue.address_error || '地图服务未返回有效地址' }}</view>
                <view class="location-detail-meta">{{ coordinateText }} · {{ sourceText }}</view>
            </view>
            <view v-else class="location-empty-text">暂无定位</view>
        </view>
    </view>

    <view v-else :style="warpCss" class="form-item-frame">
        <view v-if="diyGlobal.completeLayout === 'style-1'" class="base-layout-one">
            <view class="layout-one-label">
                <text
                    class="name"
                    :style="{
                        color: diyComponent.textColor,
                        fontSize: (diyComponent.fontSize * 2) + 'rpx',
                        fontWeight: diyComponent.fontWeight
                    }"
                >{{ diyComponent.field.name }}</text>
                <text class="required">{{ diyComponent.field.required ? '*' : '' }}</text>
                <text v-if="diyStore.mode === 'decorate' && diyComponent.isHidden" class="is-hidden">已隐藏</text>
            </view>

            <view
                v-if="diyComponent.field.remark.text"
                class="layout-one-remark"
                :style="{
                    color: diyComponent.field.remark.color,
                    fontSize: (diyComponent.field.remark.fontSize * 2) + 'rpx'
                }"
            >{{ diyComponent.field.remark.text }}</view>

            <view v-if="errorInfo && !errorInfo.code" class="layout-one-error-message">{{ errorInfo.message }}</view>

            <view class="location-card" :style="locationCardCss">
                <view v-if="hasLocation" class="location-selected">
                    <view class="location-pin">
                        <text class="nc-iconfont nc-icon-dizhiguanliV6xx"></text>
                    </view>
                    <view class="location-selected-main">
                        <view class="location-selected-name">{{ locationValue.name || '已采集位置' }}</view>
                        <view class="location-selected-address">{{ locationValue.full_address || '地址解析中，请稍后重试' }}</view>
                        <view v-if="locationValue.address_status === 'failed'" class="location-resolve-error">{{ mapProviderText }}地址解析失败：{{ locationValue.address_error || '地图服务未返回有效地址' }}</view>
                        <view class="location-selected-meta">
                            <text>{{ sourceText }}</text>
                            <text v-if="accuracyText">{{ accuracyText }}</text>
                            <text v-if="capturedAtText">{{ capturedAtText }}</text>
                        </view>
                        <view v-if="diyComponent.showCoordinates" class="location-coordinate">{{ coordinateText }}</view>
                    </view>
                </view>
                <view v-else class="location-empty">
                    <view class="location-empty-icon">
                        <text class="nc-iconfont nc-icon-dizhiguanliV6xx"></text>
                    </view>
                    <view>
                        <view class="location-empty-title">{{ diyComponent.placeholder || '请选择位置' }}</view>
                        <view class="location-empty-desc">定位结果会随本次表单一并提交</view>
                    </view>
                </view>

                <view class="location-actions">
                    <view
                        v-if="allowCurrent"
                        class="location-action location-action-primary"
                        :class="{ 'is-loading': loading }"
                        @click.stop="getCurrentLocation"
                    >
                        <text class="nc-iconfont nc-icon-dingweiV6xx"></text>
                        <text>{{ hasLocation ? '重新定位' : '获取当前位置' }}</text>
                    </view>
                    <view
                        v-if="allowMap"
                        class="location-action"
                        :class="{ 'is-loading': loading }"
                        @click.stop="chooseFromMap"
                    >
                        <text class="nc-iconfont nc-icon-dizhiguanliV6xx"></text>
                        <text>{{ hasLocation ? '地图校正' : '地图选择' }}</text>
                    </view>
                </view>
            </view>
        </view>

        <view v-if="diyGlobal.completeLayout === 'style-2'" class="base-layout-two">
            <text v-if="diyStore.mode === 'decorate' && diyComponent.isHidden" class="layout-two-is-hidden">已隐藏</text>
            <view class="layout-two-wrap location-layout-two" :class="{ 'no-border': !diyGlobal.borderControl }">
                <view
                    class="layout-two-label"
                    :class="{
                        'justify-start': diyGlobal.completeAlign === 'left',
                        'justify-end': diyGlobal.completeAlign === 'right'
                    }"
                >
                    <text v-if="diyComponent.field.required" class="required">*</text>
                    <text
                        class="name"
                        :style="{
                            color: diyComponent.textColor,
                            fontSize: (diyComponent.fontSize * 2) + 'rpx',
                            fontWeight: diyComponent.fontWeight
                        }"
                    >{{ diyComponent.field.name }}</text>
                </view>
                <view class="location-two-value" @click.stop="primaryAction">
                    <view class="location-two-main">
                        <view class="location-two-name">{{ hasLocation ? (locationValue.name || '已采集位置') : (diyComponent.placeholder || '请选择位置') }}</view>
                        <view v-if="hasLocation" class="location-two-address">{{ locationValue.full_address || coordinateText }}</view>
                    </view>
                    <text class="nc-iconfont nc-icon-youV6xx location-two-arrow"></text>
                </view>
            </view>

            <view class="location-two-actions">
                <view v-if="allowCurrent" class="location-two-action" @click.stop="getCurrentLocation">当前位置</view>
                <view v-if="allowMap" class="location-two-action" @click.stop="chooseFromMap">地图选择</view>
            </view>
            <view v-if="errorInfo && !errorInfo.code" class="layout-two-error-message">{{ errorInfo.message }}</view>
            <view
                v-if="diyComponent.field.remark.text"
                class="layout-two-remark"
                :style="{
                    color: diyComponent.field.remark.color,
                    fontSize: (diyComponent.field.remark.fontSize * 2) + 'rpx'
                }"
            >{{ diyComponent.field.remark.text }}</view>
        </view>

        <view v-if="diyStore.mode === 'decorate'" class="form-item-mask"></view>
    </view>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import useDiyStore from '@/app/stores/diy'
import useSystemStore from '@/stores/system'
import { getAddressByLatlng } from '@/app/api/system'
import { img, isWeixinBrowser, openMapSelector } from '@/utils/common'
import wechat from '@/utils/wechat'

type LocationSource = 'wechat_js_sdk' | 'native_gps' | 'manual_map'

interface ProjectLocationValue {
    latitude: number
    longitude: number
    full_address: string
    name: string
    province: string
    city: string
    district: string
    community: string
    accuracy: number | null
    coordinate_type: 'gcj02'
    source: LocationSource
    captured_at: number
    address_status: 'resolved' | 'failed'
    address_error: string
    map_provider: 'tianditu' | 'tencent' | string
}

const props = defineProps(['component', 'index', 'global'])
const diyStore = useDiyStore()
const systemStore = useSystemStore()
const loading = ref(false)
const errorInfo = ref<any>(null)
let locationTimeout: ReturnType<typeof setTimeout> | null = null

// DIY 组件在页面初始化、装修器切换组件时可能有一个短暂的未就绪窗口。
// 兜底对象只用于渲染这个窗口；真实组件就绪后仍直接返回原响应式对象，
// 确保定位结果可以正常写回 field.value。
const fallbackComponent: any = {
    viewFormDetail: false,
    textColor: '#303133',
    fontSize: 14,
    fontWeight: 'normal',
    isHidden: false,
    placeholder: '请选择门店位置',
    mode: 'both',
    requireAddress: true,
    showCoordinates: true,
    field: {
        name: '门店定位',
        required: false,
        value: {},
        default: {},
        remark: {
            text: '',
            color: '#999999',
            fontSize: 14
        }
    }
}

const diyComponent = computed<any>(() => {
    const current = diyStore.mode === 'decorate' ? diyStore.value?.[props.index] : props.component
    return current && typeof current === 'object' && current.field && typeof current.field === 'object'
        ? current
        : fallbackComponent
})

const diyGlobal = computed<any>(() => ({
    completeLayout: 'style-1',
    completeAlign: 'left',
    borderControl: true,
    ...(props.global || {})
}))

const allowCurrent = computed(() => ['both', 'current_only'].includes(diyComponent.value.mode || 'both'))
const allowMap = computed(() => ['both', 'map_only'].includes(diyComponent.value.mode || 'both'))

function normalizeLocation(raw: any): Partial<ProjectLocationValue> {
    if (typeof raw === 'string' && raw.trim()) {
        try {
            raw = JSON.parse(raw)
        } catch (_) {
            raw = {}
        }
    }
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return {}

    const rawLatitude = raw.latitude
    const rawLongitude = raw.longitude
    if (rawLatitude === null || rawLatitude === undefined || rawLatitude === ''
        || rawLongitude === null || rawLongitude === undefined || rawLongitude === '') return {}

    const latitude = Number(rawLatitude)
    const longitude = Number(rawLongitude)
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) return {}

    const accuracy = raw.accuracy === null || raw.accuracy === '' || raw.accuracy === undefined
        ? null
        : Number(raw.accuracy)

    return {
        latitude,
        longitude,
        full_address: String(raw.full_address || raw.address || ''),
        name: String(raw.name || ''),
        province: String(raw.province || ''),
        city: String(raw.city || ''),
        district: String(raw.district || ''),
        community: String(raw.community || ''),
        accuracy: Number.isFinite(accuracy as number) ? Number(accuracy) : null,
        coordinate_type: 'gcj02',
        source: ['wechat_js_sdk', 'native_gps', 'manual_map'].includes(raw.source) ? raw.source : 'native_gps',
        captured_at: Number(raw.captured_at || 0),
        address_status: raw.address_status === 'failed' ? 'failed' : (String(raw.full_address || raw.address || '') ? 'resolved' : raw.address_status),
        address_error: String(raw.address_error || ''),
        map_provider: String(raw.map_provider || '')
    }
}

const locationValue = computed<Partial<ProjectLocationValue>>(() => normalizeLocation(diyComponent.value?.field?.value))
const hasLocation = computed(() => {
    return Number.isFinite(Number(locationValue.value.latitude)) && Number.isFinite(Number(locationValue.value.longitude))
})

const coordinateText = computed(() => {
    if (!hasLocation.value) return ''
    return `${Number(locationValue.value.latitude).toFixed(6)}, ${Number(locationValue.value.longitude).toFixed(6)}`
})

const sourceText = computed(() => ({
    wechat_js_sdk: '微信定位',
    native_gps: '设备定位',
    manual_map: '地图选择'
} as Record<string, string>)[String(locationValue.value.source || '')] || '定位信息')

const accuracyText = computed(() => {
    if (locationValue.value.accuracy === null || locationValue.value.accuracy === undefined) return ''
    const accuracy = Number(locationValue.value.accuracy)
    return Number.isFinite(accuracy) ? `精度约 ${accuracy.toFixed(1)} 米` : ''
})

const capturedAtText = computed(() => {
    const timestamp = Number(locationValue.value.captured_at)
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    const pad = (value: number) => String(value).padStart(2, '0')
    return `${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
})

const mapProviderText = computed(() => {
    const provider = String(locationValue.value.map_provider || systemStore.mapConfig?.map_type || 'tianditu')
    return provider === 'tencent' ? '腾讯地图' : provider === 'tianditu' ? '天地图' : '地图服务'
})

const warpCss = computed(() => {
    let style = 'position:relative;'
    if (diyComponent.value.componentStartBgColor && diyComponent.value.componentEndBgColor) {
        style += `background:linear-gradient(${diyComponent.value.componentGradientAngle},${diyComponent.value.componentStartBgColor},${diyComponent.value.componentEndBgColor});`
    } else if (diyComponent.value.componentStartBgColor) {
        style += `background-color:${diyComponent.value.componentStartBgColor};`
    } else if (diyComponent.value.componentEndBgColor) {
        style += `background-color:${diyComponent.value.componentEndBgColor};`
    }

    if (diyComponent.value.componentBgUrl) {
        style += `background-image:url('${img(diyComponent.value.componentBgUrl)}');background-size:cover;background-repeat:no-repeat;`
    }
    if (diyComponent.value.topRounded) {
        style += `border-top-left-radius:${diyComponent.value.topRounded * 2}rpx;border-top-right-radius:${diyComponent.value.topRounded * 2}rpx;`
    }
    if (diyComponent.value.bottomRounded) {
        style += `border-bottom-left-radius:${diyComponent.value.bottomRounded * 2}rpx;border-bottom-right-radius:${diyComponent.value.bottomRounded * 2}rpx;`
    }
    return style
})

const locationCardCss = computed(() => {
    let style = ''
    if (diyComponent.value.elementBgColor) style += `background-color:${diyComponent.value.elementBgColor};`
    if (diyComponent.value.topElementRounded) {
        style += `border-top-left-radius:${diyComponent.value.topElementRounded * 2}rpx;border-top-right-radius:${diyComponent.value.topElementRounded * 2}rpx;`
    }
    if (diyComponent.value.bottomElementRounded) {
        style += `border-bottom-left-radius:${diyComponent.value.bottomElementRounded * 2}rpx;border-bottom-right-radius:${diyComponent.value.bottomElementRounded * 2}rpx;`
    }
    return style
})

function setLocation(value: ProjectLocationValue) {
    diyComponent.value.field.value = value
    errorInfo.value = { code: true, message: '' }
}

function clearLoading() {
    loading.value = false
    if (locationTimeout) {
        clearTimeout(locationTimeout)
        locationTimeout = null
    }
}

function showLocationError(error: any, fallback = '定位失败，请检查定位权限后重试') {
    clearLoading()
    const message = String(error?.errMsg || error?.message || fallback)
    const friendlyMessage = /deny|denied|auth|permission|authorize/i.test(message)
        ? '未获得定位权限，请在系统设置中允许定位后重试'
        : fallback
    uni.showToast({ title: friendlyMessage, icon: 'none', duration: 3500 })
}

function openMapFallback() {
    // #ifdef H5
    openExternalMapSelector()
    return
    // #endif
    chooseFromMap()
}

function handleCurrentLocationFailure(error: any, fallback = '浏览器暂时无法获取当前位置') {
    clearLoading()
    const rawMessage = String(error?.errMsg || error?.message || '')
    const permissionDenied = /deny|denied|auth|permission|authorize|PERMISSION_DENIED/i.test(rawMessage)
    const reason = permissionDenied
        ? '浏览器没有获得定位权限，请在浏览器或系统设置中允许定位。'
        : `${fallback}，可能是浏览器定位权限、系统定位服务或网络环境导致。`

    if (!allowMap.value) {
        uni.showModal({
            title: '当前位置获取失败',
            content: `${reason} 当前表单要求现场定位，也可以改用微信打开后重试。`,
            showCancel: false,
            confirmText: '知道了'
        })
        return
    }

    uni.showModal({
        title: '当前位置获取失败',
        content: `${reason} 是否改用地图选择准确位置？`,
        confirmText: '地图选择',
        cancelText: '稍后重试',
        success: result => { if (result.confirm) openMapFallback() }
    })
}

async function completeLocation(input: {
    latitude: number | string
    longitude: number | string
    accuracy?: number | null
    source: LocationSource
    name?: string
    address?: string
}) {
    const latitude = Number(input.latitude)
    const longitude = Number(input.longitude)
    if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
        showLocationError(null, '定位结果无效，请重新选择')
        return
    }

    loading.value = true
    let addressData: any = {}
    let addressStatus: 'resolved' | 'failed' = 'resolved'
    let addressError = ''
    const mapProvider = String(systemStore.mapConfig?.map_type || 'tianditu')
    try {
        const response: any = await getAddressByLatlng({ latlng: `${latitude},${longitude}` })
        addressData = response?.data || {}
        if (!addressData.full_address && !addressData.province && !addressData.city && !addressData.district) {
            addressStatus = 'failed'
            addressError = '地图服务未返回可识别的详细地址'
        }
    } catch (error:any) {
        addressData = {}
        addressStatus = 'failed'
        addressError = String(error?.msg || error?.message || error?.errMsg || '地图服务请求失败，请检查密钥、白名单、额度或网络')
    }

    const fullAddress = String(
        input.address
        || addressData.full_address
        || [
            addressData.province,
            addressData.city,
            addressData.district,
            addressData.community
        ].filter(Boolean).join('')
    )
    const hasAccuracy = input.accuracy !== null && input.accuracy !== undefined

    setLocation({
        latitude,
        longitude,
        full_address: fullAddress,
        name: String(input.name || addressData.community || ''),
        province: String(addressData.province || ''),
        city: String(addressData.city || ''),
        district: String(addressData.district || ''),
        community: String(addressData.community || ''),
        accuracy: hasAccuracy && Number.isFinite(Number(input.accuracy)) ? Number(input.accuracy) : null,
        coordinate_type: 'gcj02',
        source: input.source,
        captured_at: Math.floor(Date.now() / 1000),
        address_status: fullAddress ? 'resolved' : addressStatus,
        address_error: fullAddress ? '' : addressError,
        map_provider: mapProvider
    })
    clearLoading()

    if (!fullAddress && diyComponent.value.requireAddress) {
        uni.showModal({ title: '坐标获取成功，地址解析失败', content: `${mapProvider === 'tencent' ? '腾讯地图' : '天地图'}：${addressError || '未返回有效地址'}。坐标和失败原因已保留，可重试或改用地图选择。`, showCancel: false, confirmText: '知道了' })
    } else {
        uni.showToast({ title: '位置已更新', icon: 'success' })
    }
}

function getNativeLocation() {
    uni.getLocation({
        type: 'gcj02',
        isHighAccuracy: true,
        highAccuracyExpireTime: 5000,
        success: (result: any) => {
            completeLocation({
                latitude: result.latitude,
                longitude: result.longitude,
                accuracy: result.accuracy,
                source: 'native_gps'
            })
        },
        fail: (error: any) => handleCurrentLocationFailure(error)
    })
}

function getCurrentLocation() {
    if (diyStore.mode === 'decorate' || loading.value || !allowCurrent.value) return
    loading.value = true
    locationTimeout = setTimeout(() => {
        if (loading.value) handleCurrentLocationFailure(null, '定位请求超时')
    }, 16000)

    // #ifdef H5
    if (isWeixinBrowser()) {
        wechat.init(() => {
            wechat.getLocation((result: any) => {
                completeLocation({
                    latitude: result.latitude,
                    longitude: result.longitude,
                    accuracy: result.accuracy,
                    source: 'wechat_js_sdk'
                })
            })
        })
        return
    }
    // #endif

    // #ifdef H5
    if (!window.isSecureContext || !navigator.geolocation) {
        handleCurrentLocationFailure(null, '当前浏览器环境不支持安全定位')
        return
    }
    // #endif

    getNativeLocation()
}

function openExternalMapSelector() {
    // #ifdef H5
    uni.setStorageSync('project_form_location_map_pending', fieldIdentity())
    const url = new URL(location.href)
    ;['latng', 'name', 'poiaddress', 'poiname', 'cityname', 'module', 'coordtype'].forEach(key => url.searchParams.delete(key))
    const latitude = locationValue.value.latitude
    const longitude = locationValue.value.longitude
    const params = hasLocation.value ? `latng=${latitude},${longitude}` : ''
    openMapSelector(url.toString(), params)
    // #endif
}

function chooseFromMap() {
    if (diyStore.mode === 'decorate' || loading.value || !allowMap.value) return
    loading.value = true

    const options: any = {
        success: (result: any) => {
            completeLocation({
                latitude: result.latitude,
                longitude: result.longitude,
                accuracy: null,
                source: 'manual_map',
                name: result.name,
                address: result.address
            })
        },
        fail: (error: any) => {
            clearLoading()
            const message = String(error?.errMsg || '')
            if (/cancel/i.test(message)) return

            // #ifdef H5
            openExternalMapSelector()
            return
            // #endif

            showLocationError(error, '地图选择失败，请检查定位权限后重试')
        }
    }
    if (hasLocation.value) {
        options.latitude = Number(locationValue.value.latitude)
        options.longitude = Number(locationValue.value.longitude)
    }
    uni.chooseLocation(options)
}

function primaryAction() {
    if (allowCurrent.value) {
        getCurrentLocation()
    } else {
        chooseFromMap()
    }
}

function fieldIdentity() {
    return String(diyComponent.value?.id || diyComponent.value?.field?.name || props.index || 'project_location')
}

function safeDecode(value: any) {
    const text = String(value || '')
    try {
        return decodeURIComponent(text)
    } catch (_) {
        return text
    }
}

function handleMapReturn(options: any) {
    if (!options?.latng) return
    const pending = String(uni.getStorageSync('project_form_location_map_pending') || '')
    if (pending && pending !== fieldIdentity()) return

    const coordinates = safeDecode(options.latng).split(',')
    if (coordinates.length !== 2) return

    uni.removeStorageSync('project_form_location_map_pending')
    completeLocation({
        latitude: coordinates[0],
        longitude: coordinates[1],
        accuracy: null,
        source: 'manual_map',
        name: safeDecode(options.poiname || options.name || ''),
        address: safeDecode(options.poiaddress || options.address || '')
    })

    // #ifdef H5
    try {
        const url = new URL(location.href)
        ;['latng', 'name', 'poiaddress', 'poiname', 'cityname', 'module', 'coordtype'].forEach(key => url.searchParams.delete(key))
        history.replaceState({}, document.title, url.toString())
    } catch (_) {}
    // #endif
}

function readMapReturnFromUrl() {
    // #ifdef H5
    try {
        const query: Record<string, string> = {}
        const params = new URL(location.href).searchParams
        params.forEach((value, key) => { query[key] = value })
        handleMapReturn(query)
    } catch (_) {}
    // #endif
}

function verify() {
    const result = { code: true, message: '' }
    const value = locationValue.value
    const latitude = Number(value.latitude)
    const longitude = Number(value.longitude)

    if (!hasLocation.value) {
        if (diyComponent.value.field.required) {
            result.code = false
            result.message = `请选择${diyComponent.value.field.name || '位置'}`
        }
        errorInfo.value = result
        return result
    }

    if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
        result.code = false
        result.message = '定位坐标超出有效范围，请重新采集'
    } else if (diyComponent.value.requireAddress && !String(value.full_address || '').trim()) {
        result.code = false
        result.message = '未获取到详细地址，请改用地图选择'
    }

    const maxAccuracy = Number(diyComponent.value.maxAccuracyMeters || 0)
    const hasAccuracy = value.accuracy !== null && value.accuracy !== undefined
    const accuracy = hasAccuracy ? Number(value.accuracy) : Number.NaN
    if (
        result.code
        && maxAccuracy > 0
        && value.source !== 'manual_map'
        && (!Number.isFinite(accuracy) || accuracy > maxAccuracy)
    ) {
        result.code = false
        result.message = Number.isFinite(accuracy)
            ? `定位精度约 ${accuracy.toFixed(1)} 米，请移至开阔位置重新定位`
            : '当前定位未返回精度，请重新定位或改用地图选择'
    }

    const maxAgeMinutes = Number(diyComponent.value.maxAgeMinutes || 0)
    const capturedAt = Number(value.captured_at || 0)
    if (
        result.code
        && maxAgeMinutes > 0
        && (!capturedAt || Math.floor(Date.now() / 1000) - capturedAt > maxAgeMinutes * 60)
    ) {
        result.code = false
        result.message = '定位信息已过期，请重新采集'
    }

    errorInfo.value = result
    return result
}

function reset() {
    const defaultValue = normalizeLocation(diyComponent.value.field.default)
    diyComponent.value.field.value = Object.keys(defaultValue).length ? { ...defaultValue } : {}
    errorInfo.value = null
}

onLoad((options: any) => handleMapReturn(options))

onMounted(() => {
    if (!['both', 'current_only', 'map_only'].includes(diyComponent.value.mode)) {
        diyComponent.value.mode = 'both'
    }
    readMapReturnFromUrl()
})

defineExpose({ verify, reset })
</script>

<style lang="scss" scoped>
@import '@/styles/diy_form.scss';

.location-card {
    overflow: hidden;
    border: 1rpx solid #e4e8ef;
}

.location-selected,
.location-empty {
    display: flex;
    gap: 18rpx;
    padding: 24rpx;
}

.location-pin,
.location-empty-icon {
    display: flex;
    width: 62rpx;
    height: 62rpx;
    flex: none;
    align-items: center;
    justify-content: center;
    border-radius: 18rpx;
    background: #eaf1ff;
    color: var(--primary-color);
}

.location-pin .nc-iconfont,
.location-empty-icon .nc-iconfont {
    font-size: 34rpx;
}

.location-selected-main {
    min-width: 0;
    flex: 1;
}

.location-selected-name,
.location-empty-title {
    overflow: hidden;
    color: #303133;
    font-size: 28rpx;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.location-selected-address {
    margin-top: 8rpx;
    color: #667085;
    font-size: 24rpx;
    line-height: 36rpx;
    word-break: break-all;
}

.location-resolve-error {
    margin-top: 8rpx;
    padding: 10rpx 12rpx;
    border-radius: 10rpx;
    background: #fff1f0;
    color: #d92d20;
    font-size: 21rpx;
    line-height: 32rpx;
    word-break: break-all;
}

.location-selected-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 16rpx;
    margin-top: 12rpx;
    color: #98a2b3;
    font-size: 21rpx;
}

.location-coordinate {
    margin-top: 8rpx;
    color: #98a2b3;
    font-family: monospace;
    font-size: 20rpx;
}

.location-empty {
    align-items: center;
}

.location-empty-desc {
    margin-top: 7rpx;
    color: #98a2b3;
    font-size: 22rpx;
}

.location-actions {
    display: flex;
    border-top: 1rpx solid #e7ebf0;
    background: #fff;
}

.location-action {
    display: flex;
    min-width: 0;
    height: 78rpx;
    flex: 1;
    align-items: center;
    justify-content: center;
    gap: 8rpx;
    color: #475467;
    font-size: 25rpx;
}

.location-action + .location-action {
    border-left: 1rpx solid #e7ebf0;
}

.location-action-primary {
    color: var(--primary-color);
    font-weight: 600;
}

.location-action.is-loading {
    opacity: .55;
    pointer-events: none;
}

.location-layout-two {
    align-items: flex-start;
}

.location-two-value {
    display: flex;
    min-width: 0;
    flex: 1;
    align-items: center;
    justify-content: flex-end;
    gap: 8rpx;
}

.location-two-main {
    min-width: 0;
    text-align: right;
}

.location-two-name {
    overflow: hidden;
    color: #344054;
    font-size: 27rpx;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.location-two-address {
    display: -webkit-box;
    overflow: hidden;
    margin-top: 5rpx;
    color: #98a2b3;
    font-size: 21rpx;
    line-height: 30rpx;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.location-two-arrow {
    flex: none;
    color: #98a2b3;
    font-size: 28rpx;
}

.location-two-actions {
    display: flex;
    justify-content: flex-end;
    gap: 20rpx;
    padding: 12rpx 20rpx 0;
}

.location-two-action {
    color: var(--primary-color);
    font-size: 23rpx;
}

.location-detail-view {
    padding: 18rpx 20rpx;
}

.location-detail-label {
    color: #667085;
    font-size: 24rpx;
}

.location-detail-body,
.location-empty-text {
    margin-top: 10rpx;
}

.location-detail-name {
    color: #344054;
    font-size: 27rpx;
    font-weight: 600;
}

.location-detail-address {
    margin-top: 5rpx;
    color: #667085;
    font-size: 23rpx;
    line-height: 34rpx;
}

.location-detail-meta,
.location-empty-text {
    margin-top: 7rpx;
    color: #98a2b3;
    font-size: 21rpx;
}
</style>
