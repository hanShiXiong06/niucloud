<template>
    <view class="history-page">
        <!-- 固定搜索栏 -->
        <view class="search-header">
            <!-- 搜索框 -->
            <u-search v-model="filterState.keyword" placeholder="搜索序列号/IMEI" :clearabled="true" :show-action="false"
                @change="handleSearch" @clear="handleSearch">
                <template #prefix>
                    <u-icon name="search" size="30" color="#909399"></u-icon>
                </template>
            </u-search>

            <!-- 筛选栏 -->
            <view class="filter-bar">
                <view class="filter-item" @tap="showCategory">
                    <text :class="{ 'selected': filterState.pid }">
                        {{ getSelectedCategoryText }}
                    </text>
                    <u-icon name="arrow-down" size="24"
                        :color="filterState.pid ? 'var(--primary-color)' : '#666'"></u-icon>
                </view>
                <view class="filter-item" @tap="showDate">
                    <text :class="{ 'selected': filterState.dateRange.start }">
                        {{ getSelectedDateText }}
                    </text>
                    <u-icon name="calendar" size="24"
                        :color="filterState.dateRange.start ? 'var(--primary-color)' : '#666'"></u-icon>
                </view>
            </view>
        </view>

        <!-- 分类选择器 -->
        <u-popup :show="showCategoryPicker" mode="bottom" @close="showCategoryPicker = false">
            <view class="picker-content">
                <view class="picker-header">
                    <text>选择分类</text>
                    <u-icon name="close" size="28" @tap="showCategoryPicker = false"></u-icon>
                </view>
                <scroll-view scroll-y class="picker-list">
                    <view class="picker-item" v-for="item in categoryOptions" :key="item.value"
                        :class="{ active: selectedCategoryId === item.value }" @tap="handleCategorySelect(item)">
                        {{ item.text }}
                    </view>
                </scroll-view>
            </view>
        </u-popup>

        <!-- 日期选择器 -->
        <u-popup :show="showDatePicker" mode="bottom" @close="showDatePicker = false">
            <view class="picker-content">
                <view class="picker-header">
                    <text>选择时间范围</text>
                    <u-icon name="close" size="28" @tap="showDatePicker = false"></u-icon>
                </view>
                <scroll-view scroll-y class="picker-list">
                    <view class="picker-item" v-for="item in dateOptions" :key="item.value"
                        :class="{ active: selectedDateRange === item.value }" @tap="handleDateSelect(item)">
                        {{ item.text }}
                    </view>
                </scroll-view>
            </view>
        </u-popup>

        <!-- 列表区域 -->
        <view class="history-content">
            <view class="loading-state" v-if="loading && !modelList.length">
                <u-loading-icon size="36"></u-loading-icon>
                <text>正在读取查询记录...</text>
            </view>

            <view class="error-state" v-if="!loading && loadError">
                <text>{{ loadError }}</text>
                <view class="retry-btn" @tap="resetAndLoad()">重新加载</view>
            </view>

            <!-- 列表内容 -->
            <view class="list-content" v-if="modelList.length">
                <view class="history-item" v-for="(item, index ) in modelList" :key="item.order_id || item.id"
                    @click="handleItemClick(item)">
                    <!-- 顶部信息 -->
                    <view class="item-top">
                        <view class="left">
                            <text class="type-tag-index">{{ index + 1 }}</text>
                            <text class="type-tag">{{ item.type_name }}</text>
                            <text class="unread-dot" v-if="item.is_look === 0"></text>
                        </view>
                        <text class="status-pill" :class="getStatusClass(item.status)">
                            {{ item.status_name || getStatusText(item.status) }}
                        </text>
                    </view>

                    <!-- 分割线 -->
                    <view class="divider"></view>

                    <!-- 设备信息 -->
                    <view class="item-main" v-if="item.can_view_detail && getDeviceInfo(item.info)">
                        <view class="device-name">
                            <text class="model">{{ getDeviceModel(item.info) || '查询报告已生成' }}</text>
                            <view class="specs" v-if="getDeviceCapacity(item.info) || getDeviceColor(item.info)">
                                <text>{{ getDeviceCapacity(item.info) }}</text>
                                <text v-if="getDeviceColor(item.info)">· {{ getDeviceColor(item.info) }}</text>
                            </view>
                        </view>

                        <!-- 查询结果标签组 -->
                        <view class="status-tags">
                            <view class="tag" v-if="getDeviceInfo(item.info).保修到期"
                                :class="getWarrantyClass(getDeviceInfo(item.info).保修到期).class">
                                <u-icon :name="getWarrantyClass(getDeviceInfo(item.info).保修到期).icon"
                                    :color="getWarrantyClass(getDeviceInfo(item.info).保修到期).color" size="24" />
                                <text>{{ getDeviceInfo(item.info).保修到期 }}</text>
                            </view>
                            <view class="tag" v-if="getDeviceInfo(item.info).监管锁"
                                :class="getLockClass(getDeviceInfo(item.info).监管锁)">
                                <u-icon :name="getLockIcon(getDeviceInfo(item.info).监管锁)" size="24" />
                                <text>{{ getDeviceInfo(item.info).监管锁 }}</text>
                            </view>
                        </view>
                    </view>
                    <view class="item-main state-main" v-else>
                        <view class="state-title">{{ getOrderStateTitle(item) }}</view>
                        <view class="state-desc">{{ getOrderStateDesc(item) }}</view>
                        <view class="fail-reason" v-if="Number(item.status) === -1 && item.fail_reason">
                            {{ item.fail_reason }}
                        </view>
                    </view>

                    <!-- 底部信息 -->
                    <view class="item-bottom">
                        <view class="bottom-meta">
                            <text class="time">{{ formatTime(item.create_time) }}</text>
                            <text class="pay">{{ item.pay_text }} · {{ item.query_count || 1 }}次</text>
                        </view>
                        <u-icon v-if="item.can_view_detail" name="arrow-right" color="#c0c4cc" size="28"></u-icon>
                    </view>
                </view>
            </view>

            <!-- 空状态 -->
            <u-empty mode="data" text="暂无查询记录" v-if="!loading && !loadError && !modelList.length"></u-empty>

            <!-- 加载状态 -->
            <view class="load-more" v-if="modelList.length > 0">
                <u-loadmore :status="loadStatus" :loading-text="'加载中...'" :loadmore-text="'上拉加载更多'"
                    :nomore-text="'-- 没有更多数据了 --'" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { getModelList, getCategoryTree } from '@/addon/hsx_phone_query/api/index'
import { onReachBottom, onPullDownRefresh, onShow } from '@dcloudio/uni-app'


// 列表数据
const modelList = ref<any[]>([])
const page = ref(1)
const total = ref(0)
const loadStatus = ref<'loadmore' | 'loading' | 'nomore'>('loadmore')
const loading = ref(false)
const loadError = ref('')

// 搜索和筛选相关
const searchTimer = ref<any>(null)

// 筛选相关的状态
const filterState = ref({
    keyword: '',
    pid: '',
    dateRange: {
        start: '',
        end: ''
    }
})

// 分类选项
const categoryOptions = ref([
    { text: '全部分类', value: '' }
])

// 日期选项
const dateOptions = ref([
    { text: '全部时间', value: '' },
    { text: '最近7天', value: '7' },
    { text: '最近30天', value: '30' },
    { text: '最近90天', value: '90' }
])

// 加载分类数据
const loadCategories = async () => {
    try {
        const res = await getCategoryTree()
        if (res.code === 1) {
            const categories = res.data.map((item, index) => ({
                text: item.name,
                value: index
            }))
            categoryOptions.value = [{ text: '全部分类', value: '' }, ...categories]
        }
    } catch (error) {
        console.error('获取分类失败:', error)
    }
}

// 处理分类选择
const handleCategorySelect = (item: any) => {
    filterState.value.pid = item.value
    showCategoryPicker.value = false
    applyFilters()
}

// 处理日期选择
const handleDateSelect = (item: any) => {
    if (!item.value) {
        filterState.value.dateRange = { start: '', end: '' }
    } else {
        const days = parseInt(item.value)
        const endDate = new Date()
        const startDate = new Date(endDate.getTime() - days * 24 * 60 * 60 * 1000)
        filterState.value.dateRange = {
            start: formatDate(startDate),
            end: formatDate(endDate)
        }
    }
    showDatePicker.value = false
    applyFilters()
}

// 处理搜索
const handleSearch = () => {
    if (searchTimer.value) clearTimeout(searchTimer.value)
    searchTimer.value = setTimeout(() => {
        applyFilters()
    }, 300)
}

// 应用筛选条件
const applyFilters = () => {
    const params = {
        page: 1,
        limit: 10,
        keyword: filterState.value.keyword,
        pid: filterState.value.pid,
        start_time: filterState.value.dateRange.start,
        end_time: filterState.value.dateRange.end
    }
    resetAndLoad(params)
}

// 加载数据
const loadData = async (customParams?: any) => {
    if (loading.value) return
    try {
        loading.value = true
        loadStatus.value = 'loading'

        const params = customParams || {
            page: page.value,
            limit: 10,
            keyword: filterState.value.keyword,
            pid: filterState.value.pid,
            start_time: filterState.value.dateRange.start,
            end_time: filterState.value.dateRange.end
        }

        loadError.value = ''
        const res = await getModelList(params)

        if (res.code === 1) {
            const { data, total: totalCount } = res.data

            // 更新数据
            if (page.value === 1) {
                modelList.value = data
                // 重置滚动位置
                scrollTop.value = 0
            } else {
                modelList.value = [...modelList.value, ...data]
            }

            total.value = totalCount

            // 更新加载状态
            loadStatus.value = modelList.value.length >= totalCount ? 'nomore' : 'loadmore'
        } else {
            if (page.value > 1) {
                page.value--
            }
            loadError.value = res.msg || '加载失败'
            uni.showToast({
                title: res.msg || '加载失败',
                icon: 'none'
            })
        }
    } catch (error) {
        console.error('加载失败:', error)
        if (page.value > 1) {
            page.value--
        }
        loadError.value = '查询记录加载失败，请稍后重试'
        uni.showToast({
            title: '获取记录失败',
            icon: 'none'
        })
    } finally {
        loading.value = false
    }
}

// 重置筛选条件
const resetFilters = () => {
    filterState.value = {
        keyword: '',
        pid: '',
        dateRange: {
            start: '',
            end: ''
        }
    }
    applyFilters()
}

// 点击记录
const handleItemClick = (item: any) => {
    if (!item.can_view_detail || !item.id) {
        uni.showToast({
            title: getOrderStateDesc(item),
            icon: 'none'
        })
        return
    }

    uni.navigateTo({
        url: `/addon/hsx_phone_query/pages/detail?id=${item.id}`
    })
}

// 解析设备信息
const getDeviceInfo = (infoStr: string) => {
    try {
        return (infoStr)
    } catch (error) {
        return null
    }
}

const getDeviceModel = (info: any) => {
    const data = getDeviceInfo(info) || {}
    return data.机型 || data.model || data.device_model || data.product || data.product_name || ''
}

const getDeviceCapacity = (info: any) => {
    const data = getDeviceInfo(info) || {}
    return data.容量 || data.capacity || ''
}

const getDeviceColor = (info: any) => {
    const data = getDeviceInfo(info) || {}
    return data.颜色 || data.color || ''
}

const getStatusText = (status: number | string) => {
    const value = Number(status)
    if (value === 1) return '已支付'
    if (value === 2) return '查询中'
    if (value === 3) return '查询成功'
    if (value === -1) return '查询失败'
    return '处理中'
}

const getStatusClass = (status: number | string) => {
    const value = Number(status)
    if (value === 3) return 'success'
    if (value === -1) return 'fail'
    if (value === 1 || value === 2) return 'processing'
    return 'default'
}

const getOrderStateTitle = (item: any) => {
    const status = Number(item.status)
    if (status === -1) return item.refund_text || '查询失败，费用已处理'
    if (status === 1 || status === 2) return '系统正在查询，请稍后刷新'
    return '查询订单处理中'
}

const getOrderStateDesc = (item: any) => {
    const status = Number(item.status)
    if (status === -1) return item.refund_text || '查询失败，费用将自动退还'
    if (status === 1) return '支付已完成，正在进入查询队列'
    if (status === 2) return '查询通常很快完成，下拉刷新可查看最新状态'
    return '请稍后查看查询结果'
}

// 格式化时间
const formatTime = (timeStr: string | null) => {
    if (!timeStr) return ''
    try {
        const date = new Date(timeStr)
        const now = new Date()
        const diff = now.getTime() - date.getTime()

        // 检查是否是有效日期
        if (isNaN(date.getTime())) return timeStr

        if (diff < 24 * 60 * 60 * 1000) {
            const timeParts = timeStr.split(' ')
            return timeParts[1] || timeStr
        }
        const dateParts = timeStr.split(' ')
        return dateParts[0] || timeStr
    } catch (error) {
        console.error('时间格式化错误:', error)
        return timeStr || ''
    }
}

// 获取保修状态样式
const getWarrantyClass = (status: string) => {
    if (status === '过期') {
        return {
            class: 'expired',
            color: '#ff4d4f',
            icon: 'error-circle' // 使用错误图标
        }
    }
    return {
        class: 'valid',
        color: '#52c41a',
        icon: 'checkmark-circle' // 使用对勾图标
    }
}

// 获取监管锁状态
const getLockClass = (status: string) => {
    return status === 'OFF' ? 'unlocked' : 'locked'
}

const getLockIcon = (status: string) => {
    return status === 'OFF' ? 'unlock' : 'lock'
}

// 控制弹窗显示
const showCategoryPicker = ref(false)
const showDatePicker = ref(false)
const selectedCategoryId = computed(() => filterState.value.pid)
const selectedDateRange = computed(() => {
    if (!filterState.value.dateRange.start) return ''
    const selected = dateOptions.value.find(item => {
        if (!item.value) return false
        const endDate = new Date()
        const startDate = new Date(endDate.getTime() - parseInt(item.value) * 24 * 60 * 60 * 1000)
        return formatDate(startDate) === filterState.value.dateRange.start
    })
    return selected?.value || ''
})

// 显示分类选择器
const showCategory = () => {
    showCategoryPicker.value = true
}

// 显示日期选择器
const showDate = () => {
    showDatePicker.value = true
}

// 格化日期
const formatDate = (date: Date) => {
    // 如果date 为空 则返回空字符串
    if (!date) return ''
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

// 添加滚动相关的状态
const scrollTop = ref(0)

// 下拉刷新
const onRefresh = async () => {
    try {
        page.value = 1
        modelList.value = []
        loadStatus.value = 'loading'
        await loadData()
    } finally {
        uni.stopPullDownRefresh()
    }
}

// 触底加载更多
onReachBottom(() => {
    if (loading.value || loadStatus.value === 'nomore') {
        return
    }

    if (modelList.value.length < total.value) {
        page.value++
        loadData()
    } else {
        loadStatus.value = 'nomore'
    }
})

// 下拉刷新
onPullDownRefresh(() => {
    onRefresh()
})

// 筛选处理
const handleFilter = () => {
    resetAndLoad()
}

// 重置并加载数据
const resetAndLoad = (customParams?: any) => {
    page.value = 1
    modelList.value = []
    total.value = 0
    loadError.value = ''
    loadStatus.value = 'loading'
    loadData(customParams)
}

// 计算选中的分类文本
const getSelectedCategoryText = computed(() => {
    if (filterState.value.pid === '') return '选择分类'
    const selected = categoryOptions.value.find(item => item.value === filterState.value.pid)
    return selected ? selected.text : '选择分类'
})

// 计算选中的日期文本
const getSelectedDateText = computed(() => {
    if (!filterState.value.dateRange.start) return '选择时间'
    const days = dateOptions.value.find(item => {
        if (!item.value) return false
        const endDate = new Date()
        const startDate = new Date(endDate.getTime() - parseInt(item.value) * 24 * 60 * 60 * 1000)
        return formatDate(startDate) === filterState.value.dateRange.start
    })
    if (days) return days.text
    return `${filterState.value.dateRange.start} 至 ${filterState.value.dateRange.end}`
})

onMounted(() => {
    loadCategories()
})
onShow(() => {
    resetAndLoad()
})
</script>

<style lang="scss" scoped>
.history-page {
    min-height: 100vh;
    background: #f7f8fa;
}

.search-header {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
    background: #f7f8fa;
    padding: 20rpx 20rpx 0;
}

.history-content {
    padding: 20rpx;
    margin-top: 170rpx; // 调整顶部间距，适应筛选栏
}

.loading-state,
.error-state {
    min-height: 360rpx;
    background: #fff;
    border-radius: 16rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 20rpx;
    color: #666;
    font-size: 26rpx;
}

.retry-btn {
    height: 64rpx;
    line-height: 64rpx;
    padding: 0 32rpx;
    border-radius: 32rpx;
    background: var(--primary-color);
    color: #fff;
    font-size: 26rpx;
}

.type-tag-index {
    display: inline-block;
    width: 32rpx;
    height: 32rpx;
    background: #f5f5f5;
    border-radius: 50%;
    text-align: center;
    padding: 6rpx;
    line-height: 32rpx;
    margin-right: 12rpx;
}

.list-content {
    .history-item {
        margin-bottom: 20rpx;
        padding: 24rpx;
        background: #fff;
        border-radius: 16rpx;
        box-shadow: 0 2rpx 12rpx rgba(0, 0, 0, 0.03);

        .item-top {
            display: flex;
            align-items: center;
            justify-content: space-between;

            .left {
                display: flex;
                align-items: center;
                gap: 12rpx;
            }

            .unread-dot {
                width: 16rpx;
                height: 16rpx;
                background: var(--primary-color);
                border-radius: 50%;
                display: inline-block;
            }

            .type-tag {
                font-size: 24rpx;
                color: var(--primary-color);
                background: rgba(var(--primary-color-rgb), 0.08);
                padding: 4rpx 16rpx;
                border-radius: 20rpx;
            }

            .sn {
                font-size: 26rpx;
                color: #333;
                font-family: monospace;
            }

            .status-pill {
                flex-shrink: 0;
                padding: 8rpx 18rpx;
                border-radius: 999rpx;
                font-size: 24rpx;

                &.success {
                    color: #16a34a;
                    background: #eaf8ef;
                }

                &.processing {
                    color: #2563eb;
                    background: #eaf2ff;
                }

                &.fail {
                    color: #dc2626;
                    background: #fff1f1;
                }

                &.default {
                    color: #64748b;
                    background: #f1f5f9;
                }
            }
        }

        // 添加分割线样式
        .divider {
            height: 1px;
            background: #f5f5f5;
            margin: 16rpx 0;
        }

        .item-main {
            .device-name {
                margin-bottom: 16rpx;

                .model {
                    display: block;
                    font-size: 30rpx;
                    font-weight: 500;
                    color: #333;
                    margin-bottom: 8rpx;
                    line-height: 1.4;
                }

                .specs {
                    font-size: 26rpx;
                    color: #666;
                }
            }

            .status-tags {
                display: flex;
                flex-wrap: wrap;
                gap: 12rpx;
                margin-top: 16rpx;

                .tag {
                    display: inline-flex;
                    align-items: center;
                    gap: 6rpx;
                    padding: 6rpx 16rpx;
                    border-radius: 6rpx;
                    font-size: 24rpx;

                    &.valid {
                        color: #52c41a;
                        background: rgba(82, 196, 26, 0.1);
                    }

                    &.expired {
                        color: #ff4d4f;
                        background: rgba(255, 77, 79, 0.08);
                    }

                    &.locked {
                        color: #1890ff;
                        background: rgba(24, 144, 255, 0.1);
                    }

                    &.unlocked {
                        color: #666;
                        background: #f5f5f5;
                    }
                }
            }
        }

        .state-main {
            padding: 8rpx 0;

            .state-title {
                font-size: 30rpx;
                font-weight: 600;
                color: #222;
                line-height: 1.4;
            }

            .state-desc {
                margin-top: 8rpx;
                font-size: 25rpx;
                color: #667085;
                line-height: 1.5;
            }

            .fail-reason {
                margin-top: 14rpx;
                padding: 14rpx 18rpx;
                border-radius: 10rpx;
                background: #fff7ed;
                color: #9a3412;
                font-size: 24rpx;
                line-height: 1.5;
            }
        }

        .item-bottom {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20rpx;
            padding-top: 20rpx;
            border-top: 1px solid #f5f5f5;

            .time {
                font-size: 24rpx;
                color: #999;
            }

            .bottom-meta {
                display: flex;
                flex-direction: column;
                gap: 6rpx;
            }

            .pay {
                font-size: 24rpx;
                color: #666;
            }
        }

        &:active {
            transform: scale(0.995);
            transition: transform 0.2s;
        }
    }
}


.load-more {
    padding: 20rpx 0;
    text-align: center;
}

.filter-bar {
    display: flex;
    margin-top: 20rpx;
    background: #fff;
    border-radius: 12rpx;

    .filter-item {
        flex: 1;
        height: 80rpx;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 20rpx;
        font-size: 26rpx;
        color: #333;

        &:first-child {
            border-right: 1px solid #eee;
        }

        &:active {
            opacity: 0.7;
        }

        text {
            transition: color 0.3s;

            &.selected {
                color: var(--primary-color);
                font-weight: 500;
            }
        }

        // 选中状态的背景
        &.active {
            background: rgba(var(--primary-color-rgb), 0.05);
        }
    }
}

.picker-content {
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    overflow: hidden;
    max-height: 70vh;
    display: flex;
    flex-direction: column;
}

.picker-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24rpx;
    border-bottom: 1px solid #eee;

    text {
        font-size: 28rpx;
        color: #333;
        font-weight: 500;
    }
}

.picker-list {
    max-height: 60vh;
    padding: 20rpx 0;
}

.picker-item {
    padding: 24rpx;
    font-size: 28rpx;
    color: #333;
    display: flex;
    align-items: center;

    &:active {
        background: #f5f5f5;
    }

    &.active {
        position: relative;
        padding-right: 60rpx;

        &::after {
            content: '';
            position: absolute;
            right: 24rpx;
            top: 50%;
            transform: translateY(-50%);
            width: 32rpx;
            height: 32rpx;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;

            // 对勾图标
            &::before {
                content: '';
                width: 16rpx;
                height: 8rpx;
                border: 2px solid #fff;
                border-top: none;
                border-right: none;
                transform: rotate(-45deg);
                position: absolute;
                top: 10rpx;
                left: 8rpx;
            }
        }
    }
}
</style>
