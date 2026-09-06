<template>
    <view class="order-list-page">
        <!-- #ifdef MP -->
        <RecyclePageHeader title="回收订单" :fill="false" class="order-list-header">
            <view class="nav-search-box" @tap.stop>
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 nav-search-icon" @click="onSearch()"></text>
                <ScanCodeInput
                    v-model="keyword"
                    class="nav-search-input"
                    placeholder="搜索订单号、客户、手机号、IMEI"
                    :maxlength="80"
                    input-align="left"
                    font-size="24rpx"
                    placeholder-class="text-[var(--text-color-light9)] text-[24rpx]"
                    :show-scan-text="false"
                    @confirm="onSearch"
                    @scan="onScanSearch"
                />
                <view class="nav-filter-btn" @click="openFilter">
                    <text class="nc-iconfont nc-icon-shaixuanV6xx"></text>
                    <text v-if="activeFilterCount" class="filter-dot">{{ activeFilterCount }}</text>
                </view>
            </view>
        </RecyclePageHeader>
        <!-- #endif -->

        <view class="page-header" :style="pageHeaderStyle">
            <!-- #ifndef MP -->
            <view class="search-box">
                <text class="nc-iconfont nc-icon-sousuo-duanV6xx1 search-icon" @click="onSearch()"></text>
                <ScanCodeInput
                    v-model="keyword"
                    class="search-input"
                    placeholder="搜索订单号、客户、手机号、IMEI"
                    :maxlength="80"
                    input-align="left"
                    font-size="26rpx"
                    placeholder-class="text-[var(--text-color-light9)] text-[26rpx]"
                    :show-scan-text="false"
                    @confirm="onSearch"
                    @scan="onScanSearch"
                />
                <view class="search-filter-btn" @click="openFilter">
                    <text class="nc-iconfont nc-icon-shaixuanV6xx"></text>
                    <text v-if="activeFilterCount" class="filter-dot">{{ activeFilterCount }}</text>
                </view>
            </view>
            <!-- #endif -->

            <RecycleStatusTabs :model-value="currentStatus" :options="statusList" @change="switchStatus" />
        </view>

        <z-paging
            ref="pagingRef"
            v-model="list"
            @query="queryList"
            :fixed="true"
            :auto="true"
            :default-page-size="15"
            :paging-style="pagingStyle"
        >
            <template #empty>
                <RecycleEmptyState
                    title="暂无回收订单"
                    description="客户下单或扫码签收后，订单会显示在这里"
                    icon="document"
                />
            </template>
            <view class="list-content">
                <view v-if="dashboardFilterTitle" class="dashboard-filter-card">
                    <view class="dashboard-filter-card__main">
                        <u-tag text="来自看板" type="primary" plain size="mini" :customStyle="{ marginRight: '12rpx' }"></u-tag>
                        <text class="dashboard-filter-card__title">{{ dashboardFilterTitle }}</text>
                        <text v-if="dashboardDateRangeText" class="dashboard-filter-card__date">{{ dashboardDateRangeText }}</text>
                    </view>
                    <view class="dashboard-filter-card__clear" @click.stop="clearDashboardFilter">清除</view>
                </view>
                <view
                    v-for="item in list"
                    :key="item.id"
                    class="order-card"
                    @click="toDetail(item)"
                >
                    <view class="order-card__header">
                        <view class="order-card__no" @click.stop="copyNo(item.order_no)">
                            <text>{{ item.order_no }}</text>
                            <text class="nc-iconfont nc-icon-fuzhiV6xx1 ml-[8rpx]"></text>
                        </view>
                        <view class="order-card__status-wrap">
                            <u-tag :text="item.status_name" :type="getStatusTagType(item.status)" plain plainFill size="mini"></u-tag>
                            <!-- <u-tag :text="item.flow_mode_name || '整单流转'" type="info" plain plainFill size="mini" :customStyle="{ marginLeft: '12rpx' }"></u-tag> -->
                        </view>
                    </view>

                    <view class="order-card__body">
                        <view class="member-row">
                            <image v-if="getMemberAvatar(item)" class="member-avatar" :src="getMemberAvatar(item)" mode="aspectFill" />
                            <view v-else class="member-avatar member-avatar--empty">
                                <text>{{ getMemberInitial(item) }}</text>
                            </view>
                            <view class="member-main">
                                <view class="member-name">{{ getMemberName(item) }}</view>
                                <view class="member-mobile" >
                                    <text class="nc-iconfont nc-icon-dianhuaV6xx member-mobile__icon"></text>
                                    <text @click.stop="makePhoneCall(getMemberMobile(item))">{{ getMemberMobile(item) }}</text>
                                </view>
                            </view>
                        </view>
                        <view class="order-card__meta">
                            <text>{{ getDeviceCount(item) }} 台设备</text>
                            <text>{{ item.delivery_type_name || '邮寄' }}</text>
                            <!-- 快递单号 -->
                             <text v-if="item.express_no">{{ item.express_no }}</text>
                            
                            <text>{{ item.create_at || '-' }}</text>
                        </view>
                    </view>

                    <view class="flow-overview">
                        <view class="summary-strip">
                            <view
                                v-for="summary in getListSummaryItems(item)"
                                :key="summary.key"
                                class="summary-node"
                                :class="{ 'summary-node--important': summary.important }"
                                @click.stop="toDetail(item, summary.filter)"
                            >
                                <text class="summary-node__label">{{ summary.label }}</text>
                                <text class="summary-node__value">{{ summary.value }}</text>
                            </view>
                        </view>
                        <view v-if="hasPaymentAmount(item)" class="amount-line">
                            <text v-if="Number(item.flow_summary?.payable_amount || 0) > 0">{{ getBusinessStageLabel('payable') || 'payable' }} ¥{{ formatMoney(item.flow_summary?.payable_amount) }}</text>
                            <text v-if="Number(item.flow_summary?.paid_amount || 0) > 0">已打款 ¥{{ formatMoney(item.flow_summary?.paid_amount) }}</text>
                        </view>
                    </view>

                    <view v-if="(item.devices || []).length" class="device-preview">
                        <view
                            v-for="device in (item.devices || []).slice(0, 1)"
                            :key="device.id"
                            class="device-preview__item"
                        >
                            <view class="device-preview__top">
                                <text class="device-preview__model">{{ device.model || '未知型号' }}</text>
                                <view class="device-preview__price-wrap">
                                    <text
                                        class="device-preview__price"
                                        :class="getDevicePriceClass(device)"
                                    >{{ getDeviceListPriceMeta(device).label }}</text>
                                    <text v-if="getDeviceListPriceMeta(device).subLabel" class="device-preview__price-sub">
                                        {{ getDeviceListPriceMeta(device).subLabel }}
                                    </text>
                                </view>
                            </view>
                            <view class="device-preview__bottom">
                                <view
                                    class="device-preview__serial"
                                    @click.stop="copyDeviceCode(device)"
                                >
                                    <text>{{ device.imei || device.user_sn || '-' }}</text>
                                    <text
                                        v-if="device.imei || device.user_sn"
                                        class="nc-iconfont nc-icon-fuzhiV6xx1 device-preview__copy"
                                    ></text>
                                </view>
                                <text>{{ getDeviceSecondaryStatus(device) }}</text>
                            </view>
                        </view>
                        <view v-if="(item.devices || []).length > 1" class="device-preview__more">
                            还有 {{ item.devices.length - 1 }} 台设备
                        </view>
                    </view>

                    <view v-if="getActions(item).length" class="order-card__actions">
                        <view
                            v-for="action in getActions(item)"
                            :key="action.type"
                            class="action-btn"
                            :class="{ 'action-btn--primary': action.primary, 'action-btn--danger': action.danger }"
                            @click.stop="handleAction(action.type, item)"
                        >
                            {{ action.label }}
                        </view>
                    </view>
                </view>
            </view>
        </z-paging>

        <OrderFilterDrawer
            ref="filterDrawerRef"
            v-model:visible="filterVisible"
            v-model="filterParams"
            :status-options="filterStatusOptions"
            @confirm="onFilterConfirm"
            @reset="onFilterReset"
            @open-calendar="onOpenCalendar"
        />

        <!-- 时间范围日历：挂在页面层（抽屉外），打开时抽屉先收起，日历独占便于全屏选择 -->
        <u-calendar
            :show="calendarShow"
            mode="range"
            :title="calendarTitle"
            :defaultDate="calendarDefault"
            :minDate="calendarMinDate"
            :maxDate="calendarMaxDate"
            :monthNum="12"
            @confirm="onCalendarConfirm"
            @close="onCalendarClose"
        />
    </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad, onShow } from '@dcloudio/uni-app'
import { getOrderBusinessStageOptions, getOrderList, getOrderStatus, pushOrderNotify, updateOrder } from '@/addon/hsx_recycle/api/order'
import { redirect } from '@/utils/common'
import { copyOrderNo, copyIMEI } from '@/addon/hsx_recycle/utils/clipboard'
import RecyclePageHeader from '@/addon/hsx_recycle/components/RecyclePageHeader.vue'
import ScanCodeInput from '@/addon/hsx_recycle/components/ScanCodeInput.vue'
import RecycleEmptyState from '@/addon/hsx_recycle/components/RecycleEmptyState.vue'
import RecycleStatusTabs from '@/addon/hsx_recycle/components/RecycleStatusTabs.vue'
import OrderFilterDrawer from './components/OrderFilterDrawer.vue'
import { getDeviceListPriceMeta, isConsignedDevice, shouldShowConfirmStatus } from '@/addon/hsx_recycle/utils/device'
import { makePhoneCall } from '@/addon/hsx_recycle/utils/helper'
import { useRecycleListHeader } from '@/addon/hsx_recycle/hooks/useRecycleListHeader'
import { useRecyclePaging } from '@/addon/hsx_recycle/hooks/useRecyclePaging'
import { getRecycleMemberAvatar, getRecycleMemberInitial, getRecycleMemberMobile, getRecycleMemberName } from '@/addon/hsx_recycle/hooks/useRecycleMember'

const { pagingRef, list, reload, refresh, complete } = useRecyclePaging()
const keyword = ref('')
const currentStatus = ref('')
const filterVisible = ref(false)
const filterParams = ref<Record<string, any>>({})
const needRefresh = ref(false) // 标记是否需要刷新

// 时间范围日历（挂在页面层，供筛选抽屉调用）
const filterDrawerRef = ref<any>(null)
const calendarShow = ref(false)
const calendarTitle = ref('选择日期范围')
const calendarKey = ref('')
const calendarDefault = ref<string[]>([])
const formatYmd = (ts: number) => {
    const d = new Date(ts)
    return `${ d.getFullYear() }-${ String(d.getMonth() + 1).padStart(2, '0') }-${ String(d.getDate()).padStart(2, '0') }`
}
const todayStr = formatYmd(Date.now())
const calendarMaxDate = todayStr                                   // 今天，禁选未来
// 最近 12 个月（含本月）。范围越短，小程序里滚动定位越可靠
const calendarMinDate = (() => {
    const d = new Date()
    d.setMonth(d.getMonth() - 11)
    d.setDate(1)
    return formatYmd(d.getTime())
})()

const onOpenCalendar = (payload: { key: string, title: string, start: string, end: string }) => {
    calendarKey.value = payload.key
    calendarTitle.value = payload.title ? `${ payload.title }范围` : '选择日期范围'
    // 传有效 defaultDate（已选范围或今天），让日历滚到对应月份而非停在最早月
    calendarDefault.value = (payload.start && payload.end) ? [payload.start, payload.end] : [todayStr]
    // 先收起抽屉，日历独占（避免被抽屉遮罩拦截点击、被抽屉宽度裁切）
    filterVisible.value = false
    calendarShow.value = true
}

const onCalendarConfirm = (selected: string[]) => {
    const dates = (selected || []).filter(Boolean)
    if (dates.length) {
        const start = dates[0]
        const end = dates[dates.length - 1]
        filterDrawerRef.value?.applyCalendarRange(calendarKey.value, start, end)
    } else {
        filterDrawerRef.value?.keepFormOnReopen()
    }
    calendarShow.value = false
    filterVisible.value = true // 弹回抽屉（已填条件保留）
}

const onCalendarClose = () => {
    calendarShow.value = false
    filterDrawerRef.value?.keepFormOnReopen()
    filterVisible.value = true // 取消也弹回抽屉
}
const initialized = ref(false)
const lastStatusCounts = ref<Record<string, any>>({})

// 胶囊筛选行高度上调，让设备列表落在胶囊按钮下方、留出间距
const { pageHeaderStyle, pagingStyle } = useRecycleListHeader(104)

const statusList = ref<Array<{ label: string, value: string, count?: number }>>([
    { label: '全部', value: '', count: 0 }
])
const businessStageOptions = ref<Array<{ key: string, label: string, important?: boolean }>>([])

const loadStatusTabs = async () => {
    try {
        const res: any = await getOrderStatus()
        const rows = Object.values(res?.data || {})
        statusList.value = [
            { label: '全部', value: '', count: 0 },
            ...rows.map((item: any) => ({
                label: item?.name || item?.label || String(item?.status ?? ''),
                value: String(item?.status ?? item?.value ?? ''),
                count: 0
            }))
        ]
        if (Object.keys(lastStatusCounts.value).length) {
            syncStatusCounts(lastStatusCounts.value)
        }
    } catch (error) {
        statusList.value = [{ label: '全部', value: '', count: 0 }]
    }
}

const normalizeBusinessStageOptions = (data: any) => {
    const rows = Array.isArray(data) ? data : Object.values(data || {})
    const normalized = rows
        .map((item: any) => ({
            key: String(item?.key || item?.value || ''),
            label: String(item?.name || item?.label || item?.key || ''),
            important: Boolean(item?.important)
        }))
        .filter((item: any) => item.key && item.label)
    return normalized.length ? normalized : businessStageOptions.value
}

const loadBusinessStageOptions = async () => {
    try {
        const res: any = await getOrderBusinessStageOptions()
        businessStageOptions.value = normalizeBusinessStageOptions(res?.data)
    } catch (error) {
    }
}

const filterStatusOptions = computed(() => statusList.value.map((item) => ({
    label: item.label,
    value: item.value
})))

const activeFilterCount = computed(() => Object.keys(filterParams.value).filter((key) => {
    return String(filterParams.value[key] ?? '').trim() !== ''
}).length)

const dashboardFilterTitle = computed(() => String(filterParams.value.dashboard_title || '').trim())
const dashboardDateRangeText = computed(() => {
    const start = String(filterParams.value.start_time || '').trim()
    const end = String(filterParams.value.end_time || '').trim()
    if (!start && !end) return ''
    if (start && end && start !== end) return `${start} 至 ${end}`
    return start || end
})

onLoad((option: any) => {
    if (option?.status) currentStatus.value = option.status
    const routeFilters = buildRouteFilters(option || {})
    if (Object.keys(routeFilters).length) {
        filterParams.value = routeFilters
        if (routeFilters.status) currentStatus.value = String(routeFilters.status)
    }
})

onShow(async () => {
    await loadStatusTabs()
    await loadBusinessStageOptions()
    if (!initialized.value) {
        initialized.value = true
    } else if (needRefresh.value) {
        // refresh 会重新获取当前已经加载的全部分页，但不会像 reload 一样
        // 清空分页并滚回顶部。这样详情中的状态变化能及时回显，同时保留
        // 用户进入详情前的列表位置、筛选条件和已加载页数。
        refresh()
    }
    needRefresh.value = false
})

const queryList = async (pageNo: number, pageSize: number) => {
    const params: Record<string, any> = {
        page: pageNo,
        limit: pageSize,
        keyword: keyword.value.trim(),
        search: keyword.value.trim()
    }
    Object.assign(params, filterParams.value)
    if (currentStatus.value) params.status = currentStatus.value

    try {
        const res: any = await getOrderList(params)
        const pageData = res?.data || {}
        lastStatusCounts.value = pageData.status_counts || {}
        syncStatusCounts(lastStatusCounts.value)
        complete(pageData.data || [])
    } catch (error) {
        complete(false)
    }
}

const syncStatusCounts = (statusCounts: Record<string, any>) => {
    statusList.value = statusList.value.map((item) => {
        if (item.value === '') {
            return { ...item, count: Number(statusCounts.all || 0) }
        }
        return { ...item, count: Number(statusCounts[item.value] || 0) }
    })
}

const onSearch = () => reload()

const onScanSearch = () => {
    reload()
}

const switchStatus = (value: string) => {
    currentStatus.value = value
    filterParams.value = { ...filterParams.value, status: value }
    if (!value) {
        const next = { ...filterParams.value }
        delete next.status
        filterParams.value = next
    }
    reload()
}

const openFilter = () => {
    filterParams.value = { ...filterParams.value, status: currentStatus.value || filterParams.value.status || '' }
    filterVisible.value = true
}

const onFilterConfirm = (params: Record<string, any>) => {
    filterParams.value = { ...params }
    currentStatus.value = String(params.status || '')
    reload()
}

const onFilterReset = () => {
    filterParams.value = {}
    currentStatus.value = ''
    keyword.value = ''
    reload()
}

const clearDashboardFilter = () => {
    const next = { ...filterParams.value }
    ;['filter_key', 'view_mode', 'start_time', 'end_time', 'dashboard_title'].forEach((key) => delete next[key])
    filterParams.value = next
    reload()
}

const buildRouteFilters = (option: Record<string, any>) => {
    const allowKeys = [
        'status',
        'order_no',
        'express_no',
        'delivery_type',
        'user_mobile',
        'user_nickname',
        'device_imei',
        'device_model',
        'create_time_start',
        'create_time_end',
        'update_time_start',
        'update_time_end',
        'start_time',
        'end_time',
        'filter_key',
        'view_mode',
        'dashboard_title'
    ]
    const filters: Record<string, any> = {}
    allowKeys.forEach((key) => {
        const value = option[key]
        if (value !== undefined && value !== null && String(value).trim() !== '') filters[key] = String(value).trim()
    })
    if (!filters.device_imei && option.imei) filters.device_imei = String(option.imei).trim()
    return filters
}

const toDetail = (item: any, filter = '') => {
    // 返回列表后做原位刷新；不得使用 reload，否则长列表会回到顶部。
    needRefresh.value = true
    const deviceKeyword = String(filterParams.value.device_imei || keyword.value || '').trim()
    redirect({
        url: '/addon/hsx_recycle/pages/order/detail',
        param: {
            id: item.id,
            filter,
            device_keyword: deviceKeyword
        }
    })
}

const copyNo = (value: string) => copyOrderNo(value)

const copyDeviceCode = (device: any) => {
    copyIMEI(device?.imei || device?.user_sn || '')
}

const getActions = (item: any) => {
    const status = Number(item.status || 0)
    const available = item.available_actions || {}
    const actions: Array<{ label: string, type: string, primary?: boolean, danger?: boolean }> = []

    if (status === 1) actions.push({ label: '签收订单', type: 'receive', primary: true })
    if (available.can_push_confirm_notice) actions.push({ label: '推送通知', type: 'notify' })
    if (available.can_order_payment || available.can_pay_devices) actions.push({ label: '确认打款', type: 'payment', primary: true })
    if (available.can_complete_order && status !== 7) actions.push({ label: '刷新完成', type: 'complete' })
    if ([1, 2].includes(status)) actions.push({ label: '取消订单', type: 'cancel', danger: true })
    return actions
}

const handleAction = async (type: string, item: any) => {
    if (type === 'payment') {
        toDetail(item)
        return
    }

    if (type === 'receive') {
        toDetail(item)
        return
    }

    if (type === 'notify') {
        try {
            await pushOrderNotify(item.id)
            uni.showToast({ title: '通知已推送', icon: 'success' })
            reload()
        } catch (error: any) {
            uni.showToast({ title: error?.msg || error?.message || '推送失败', icon: 'none' })
        }
        return
    }

    if (type === 'complete') {
        await submitAction(item.id, { action: 'order_complete' }, '订单状态已刷新')
        return
    }

    if (type === 'cancel') {
        uni.showModal({
            title: '取消订单',
            content: '请输入取消原因',
            editable: true,
            placeholderText: '如：客户取消、信息错误',
            success: async (res) => {
                if (!res.confirm) return
                await submitAction(item.id, {
                    action: 'order_cancel',
                    cancel_reason: res.content || '',
                    reason: res.content || ''
                }, '订单已取消')
            }
        })
    }
}

const submitAction = async (id: number | string, payload: Record<string, any>, successText: string) => {
    try {
        await updateOrder(id, payload)
        uni.showToast({ title: successText, icon: 'success' })
        reload()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '操作失败', icon: 'none' })
    }
}

const getStatusClass = (status: number) => {
    if (status === 7) return 'is-success'
    if (status === 8 || status === 9) return 'is-muted'
    if (status === 6) return 'is-warning'
    return 'is-primary'
}

// 订单状态 → u-tag 类型（uview 标准配色）
const getStatusTagType = (status: number) => {
    if (status === 7) return 'success'
    if (status === 8 || status === 9) return 'info'
    if (status === 6) return 'warning'
    return 'primary'
}

const getDeviceCount = (item: any) => {
    if (Array.isArray(item.devices) && item.devices.length) return item.devices.length
    return Number(item.count || 0)
}

const getMemberName = (item: any) => {
    return getRecycleMemberName(item)
}

const getMemberMobile = (item: any) => {
    return getRecycleMemberMobile(item)
}

const getMemberAvatar = (item: any) => {
    return getRecycleMemberAvatar(item)
}

const getMemberInitial = (item: any) => {
    return getRecycleMemberInitial(item)
}

const getDeviceSecondaryStatus = (device: any) => {
    if (isConsignedDevice(device)) {
        return device.consignmentOrder?.status_name || device.dispose_status_name || '已转代卖'
    }
    if (shouldShowConfirmStatus(device) && device.confirm_status_name) {
        return device.confirm_status_name
    }
    return device.status_name || '-'
}

const getDevicePriceClass = (device: any) => {
    return `device-preview__price--${ getDeviceListPriceMeta(device).tone }`
}

const getSummaryValue = (item: any, key: string) => {
    return Number(item.flow_summary?.[key] || 0)
}

const getClosedCount = (item: any) => {
    return getBusinessStageCount(item, 'completed')
}

const getProgressPercent = (item: any) => {
    const total = getSummaryValue(item, 'total') || getDeviceCount(item)
    if (!total) return 0
    return Math.min(100, Math.round((getClosedCount(item) / total) * 100))
}

const getListSummaryItems = (item: any) => {
    return businessStageOptions.value.map((option) => ({
        key: option.key,
        label: option.label,
        value: getBusinessStageCount(item, option.key),
        filter: option.key,
        important: Boolean(option.important)
    }))
}

const getBusinessStageLabel = (key: string) => {
    return businessStageOptions.value.find((item) => item.key === key)?.label || ''
}

const getBusinessStageCount = (item: any, stage: string) => {
    const devices = Array.isArray(item.devices) ? item.devices : []
    if (devices.length) return devices.filter((device: any) => matchBusinessStage(device, stage)).length

    const summary = item.flow_summary || {}
    if (stage === 'processing') {
        return Number(summary.pending_check || 0) + Number(summary.checking || 0) + Number(summary.checked || 0)
    }
    if (stage === 'pending_confirm') return Number(summary.pending_confirm || 0)
    if (stage === 'payable') return Number(summary.payable_count || 0)
    if (stage === 'exception') return Number(summary.returned || 0) + Number(summary.consigned || 0)
    if (stage === 'completed') {
        return Math.max(0, Number(summary.paid || 0) - Number(summary.returned || 0) - Number(summary.consigned || 0))
    }
    return 0
}

const matchBusinessStage = (device: any, stage: string) => {
    const status = Number(device.status || 0)
    const confirmStatus = Number(device.confirm_status || 0)
    const payStatus = Number(device.pay_status || 0)

    if (stage === 'processing') return [1, 2, 3].includes(status)
    if (stage === 'pending_confirm') return Boolean(device.can_confirm) || status === 4 || ([4, 7, 8].includes(status) && confirmStatus === 0)
    if (stage === 'payable') return Boolean(device.can_pay) || ([5, 7, 8].includes(status) && payStatus !== 1 && !isConsignedDevice(device))
    if (stage === 'completed') {
        return !isConsignedDevice(device)
            && status !== 9
            && status !== 6
            && confirmStatus !== 2
            && (payStatus === 1 || Number(device.pay_amount || 0) > 0)
    }
    if (stage === 'exception') return status === 6 || confirmStatus === 2 || status === 9 || isConsignedDevice(device)
    return false
}

const hasPaymentAmount = (item: any) => {
    return Number(item.flow_summary?.payable_amount || 0) > 0 || Number(item.flow_summary?.paid_amount || 0) > 0
}

const formatMoney = (value: number | string) => Number(value || 0).toFixed(2)
</script>

<style scoped lang="scss">
.order-list-page {
    min-height: 100vh;
    background: #f5f7fa;
}

.page-header {
    position: fixed;
    left: 0;
    top: 0;
    right: 0;
    z-index: 10;
    background: #fff;
    box-shadow: 0 6rpx 18rpx rgba(15, 23, 42, 0.04);
}

.order-list-header {
    z-index: 20;
}

.order-list-header :deep(.recycle-page-header__center) {
    /* width: min(560rpx, 72vw); */
}

.nav-search-box {
    flex: 1;
    min-width: 0;
    height: 64rpx;
    border-radius: 32rpx;
    background: #f3f6fb;
    display: flex;
    align-items: center;
    padding: 0 18rpx;
    margin-left: 8rpx;
    margin-right: 22rpx;
}

.nav-search-input {
    flex: 1;
    min-width: 0;
    height: 64rpx;
    font-size: 24rpx;
    color: #1f2937;
}

.nav-search-input :deep(.u-input),
.nav-search-input :deep(.u-input__content) {
    height: 64rpx;
    min-height: 64rpx;
    padding: 0 !important;
    background: transparent !important;
}

.nav-search-input :deep(.u-input__content__field-wrapper__field) {
    height: 64rpx;
    line-height: 64rpx;
}

.nav-search-icon,
.nav-search-clear {
    font-size: 26rpx;
    color: #8c8c8c;
    flex-shrink: 0;
}

.nav-search-icon {
    margin-right: 10rpx;
}

.nav-search-clear {
    margin-left: 10rpx;
}

.nav-filter-btn,
.search-filter-btn {
    position: relative;
    flex-shrink: 0;
    width: 52rpx;
    height: 52rpx;
    margin-left: 10rpx;
    border-radius: 26rpx;
    background: #e8eef8;
    color: var(--hsx-primary);
    font-size: 25rpx;
    display: flex;
    align-items: center;
    justify-content: center;
}

.filter-dot {
    position: absolute;
    right: -6rpx;
    top: -8rpx;
    min-width: 28rpx;
    height: 28rpx;
    padding: 0 6rpx;
    border-radius: 14rpx;
    background: #ef4444;
    color: #fff;
    font-size: 18rpx;
    line-height: 28rpx;
    text-align: center;
    box-sizing: border-box;
}

.search-box {
    margin: 18rpx 20rpx 14rpx;
    height: 72rpx;
    border-radius: 999rpx;
    background: #f6f7fb;
    display: flex;
    align-items: center;
    padding: 0 20rpx;
}

.search-input {
    flex: 1;
    min-width: 0;
    font-size: 26rpx;
}

.search-input :deep(.u-input),
.search-input :deep(.u-input__content) {
    height: 72rpx;
    min-height: 72rpx;
    padding: 0 !important;
    background: transparent !important;
}

.search-input :deep(.u-input__content__field-wrapper__field) {
    height: 72rpx;
    line-height: 72rpx;
}

.search-clear,
.search-icon {
    font-size: 28rpx;
    color: #8c8c8c;
}

.search-clear {
    margin-right: 10rpx;
}

.search-filter-btn {
    margin-left: 14rpx;
}

.status-scroll {
    border-top: 1rpx solid #f1f5f9;
}

.status-row {
    display: flex;
    align-items: center;
    gap: 18rpx;
    padding: 24rpx 20rpx 20rpx;
    white-space: nowrap;
}

.status-chip {
    display: inline-flex;
    align-items: center;
    gap: 8rpx;
    padding: 12rpx 20rpx;
    border-radius: 999rpx;
    background: #f5f7fa;
    color: #64748b;
    font-size: 24rpx;
}

.status-chip--active {
    background: var(--hsx-primary-50);
    color: var(--hsx-primary);
}

.status-chip__count {
    min-width: 32rpx;
    height: 32rpx;
    border-radius: 16rpx;
    background: rgba(37, 99, 235, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 20rpx;
}

.list-content {
    padding: 14rpx 18rpx;
}

.dashboard-filter-card {
    margin-bottom: 14rpx;
    padding: 18rpx 20rpx;
    border-radius: 14rpx;
    background: var(--hsx-primary-50);
    border: 1rpx solid rgba(37, 99, 235, 0.18);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18rpx;
}

.dashboard-filter-card__main {
    min-width: 0;
    flex: 1;
    display: flex;
    flex-direction: row;
    align-items: center;
    flex-wrap: wrap;
}

.dashboard-filter-card__label {
    font-size: 20rpx;
    color: var(--hsx-primary);
}

.dashboard-filter-card__title {
    margin-top: 4rpx;
    font-size: 26rpx;
    font-weight: 700;
    color: #1f2937;
}

.dashboard-filter-card__date {
    margin-top: 4rpx;
    font-size: 22rpx;
    color: #64748b;
}

.dashboard-filter-card__clear {
    flex-shrink: 0;
    height: 52rpx;
    line-height: 52rpx;
    padding: 0 22rpx;
    border-radius: 26rpx;
    background: #fff;
    color: var(--hsx-primary);
    font-size: 24rpx;
}

.order-card {
    margin-bottom: 14rpx;
    padding: 18rpx 20rpx;
    border-radius: 14rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.04);
}

.order-card__header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16rpx;
}

.order-card__no {
    font-size: 22rpx;
    color: #64748b;
    display: flex;
    align-items: center;
}

.order-card__status-wrap {
    text-align: right;
    // position: absolute;
    // right: 20rpx;
}

.order-card__status {
    display: block;
    font-size: 24rpx;
    font-weight: 700;
}

.order-card__flow {
    display: block;
    margin-top: 4rpx;
    font-size: 20rpx;
    color: #8c8c8c;
}

.is-primary {
    color: var(--hsx-primary);
}

.is-warning {
    color: #d97706;
}

.is-success {
    color: #16a34a;
}

.is-muted {
    color: #94a3b8;
}

.order-card__body {
    margin-top: 12rpx;
}

.member-row {
    display: flex;
    align-items: center;
    min-width: 0;
}

.member-avatar {
    width: 58rpx;
    height: 58rpx;
    border-radius: 29rpx;
    flex-shrink: 0;
    background: #e8eef8;
}

.member-avatar--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--hsx-primary);
    font-size: 24rpx;
    font-weight: 700;
}

.member-main {
    flex: 1;
    min-width: 0;
    margin-left: 12rpx;
}

.member-name {
    font-size: 27rpx;
    font-weight: 600;
    color: #1f2937;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.member-mobile {
    display: flex;
    align-items: center;
    min-width: 0;
    margin-top: 4rpx;
    font-size: 22rpx;
    color: #64748b;
}

.member-mobile__icon {
    flex-shrink: 0;
    margin-right: 8rpx;
    font-size: 24rpx;
    color: #94a3b8;
}

.order-card__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 18rpx;
    margin-top: 8rpx;
    font-size: 21rpx;
    color: #64748b;
}

.flow-overview {
    margin-top: 12rpx;
    padding: 10rpx;
    border-radius: 12rpx;
    background: #f8fafc;
}

.flow-overview__head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20rpx;
}

.flow-overview__title {
    display: block;
    font-size: 24rpx;
    font-weight: 700;
    color: #1f2937;
}

.flow-overview__desc {
    display: block;
    margin-top: 6rpx;
    font-size: 20rpx;
    color: #8c8c8c;
}

.flow-overview__rate {
    flex-shrink: 0;
    font-size: 28rpx;
    font-weight: 800;
    color: var(--hsx-primary);
}

.flow-progress {
    height: 10rpx;
    margin-top: 14rpx;
    border-radius: 999rpx;
    background: #e5eaf2;
    overflow: hidden;
}

.flow-progress__bar {
    height: 100%;
    border-radius: 999rpx;
    background: var(--hsx-primary);
}

.summary-strip {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 6rpx;
    margin-top: 0;
}

.summary-node {
    min-width: 0;
    padding: 8rpx 4rpx;
    border-radius: 10rpx;
    background: #fff;
    text-align: center;
}

.summary-node--important {
    background: var(--hsx-primary-50);
}

.summary-node__label {
    display: block;
    font-size: 19rpx;
    color: #8c8c8c;
}

.summary-node__value {
    display: block;
    margin-top: 2rpx;
    font-size: 26rpx;
    font-weight: 700;
    color: #1f2937;
}

.amount-line {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx 18rpx;
    margin-top: 8rpx;
    padding: 0 4rpx;
    font-size: 20rpx;
    color: #d97706;
}

.device-preview {
    margin-top: 10rpx;
}

.device-preview__item {
    padding: 10rpx 0;
}

.device-preview__item + .device-preview__item {
    border-top: 1rpx solid #f1f5f9;
}

.device-preview__top,
.device-preview__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20rpx;
}

.device-preview__model {
    font-size: 23rpx;
    color: #334155;
    font-weight: 500;
    flex: 1;
    min-width: 0;
}

.device-preview__price-wrap {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    flex-shrink: 0;
    max-width: 260rpx;
}

.device-preview__price {
    font-size: 22rpx;
    font-weight: 700;
    line-height: 1.25;
    text-align: right;
}

.device-preview__serial {
    display: flex;
    align-items: center;
    gap: 8rpx;
    min-width: 0;
    color: #64748b;
    text-align: left;
}

.device-preview__serial text:first-child {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.device-preview__copy {
    flex-shrink: 0;
    color: var(--hsx-primary);
    font-size: 22rpx;
}

.device-preview__price--strong {
    color: #ea580c;
}

.device-preview__price--info {
    color: var(--hsx-primary);
}

.device-preview__price--muted {
    color: #64748b;
    font-weight: 600;
}

.device-preview__price-sub {
    margin-top: 2rpx;
    font-size: 18rpx;
    line-height: 1.2;
    color: #94a3b8;
    text-align: right;
}

.device-preview__bottom {
    margin-top: 6rpx;
    font-size: 21rpx;
    color: #8c8c8c;
}

.device-preview__more {
    margin-top: 4rpx;
    font-size: 21rpx;
    color: var(--hsx-primary);
}

.order-card__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: flex-end;
    gap: 8rpx;
    margin-top: 10rpx;
    padding-top: 10rpx;
    border-top: 1rpx solid #f1f5f9;
}

.action-btn {
    padding: 9rpx 18rpx;
    border-radius: 999rpx;
    background: #fff;
    border: 1rpx solid #dbe2ea;
    color: #475569;
    font-size: 21rpx;
}

.action-btn--primary {
    background: var(--hsx-primary);
    border-color: var(--hsx-primary);
    color: #fff;
}

.action-btn--danger {
    background: #fff1f2;
    border-color: #fecdd3;
    color: #dc2626;
}
</style>
