<template>
    <view class="my-task-page">
        <!-- Tab切换 -->
        <view class="tab-bar">
            <view class="tab-item" :class="{ active: currentTab === 'publish' }" @click="switchTab('publish')">
                <text>我发布的</text>
            </view>
            <view class="tab-item" :class="{ active: currentTab === 'accept' }" @click="switchTab('accept')">
                <text>我接的</text>
            </view>
        </view>

        <!-- 状态筛选 -->
        <scroll-view scroll-x class="status-tabs">
            <view class="status-item" :class="{ active: currentStatus === '' }" @click="changeStatus('')">全部</view>
            <view class="status-item" :class="{ active: currentStatus === String(s.value) }" v-for="s in statusList" :key="s.value" @click="changeStatus(String(s.value))">{{ s.label }}</view>
        </scroll-view>

        <!-- 列表 -->
        <scroll-view scroll-y class="list-scroll" @scrolltolower="loadMore" refresher-enabled @refresherrefresh="onRefresh" :refresher-triggered="refreshing">
            <view class="task-card" v-for="item in taskList" :key="item.id" @click="goDetail(item.id)">
                <view class="card-top">
                    <view class="type-tag">{{ getTypeName(item.task_type) }}</view>
                    <view class="status-tag" :class="'s-' + item.status">{{ getStatusName(item.status) }}</view>
                </view>
                <text class="card-title">{{ item.title }}</text>
                <text class="card-content" v-if="item.content">{{ item.content }}</text>
                <view class="card-bottom">
                    <view class="reward">
                        <text class="label">赏金</text>
                        <text class="amount">¥{{ item.reward }}</text>
                    </view>
                    <text class="time">{{ formatTime(item.create_time) }}</text>
                </view>
            </view>

            <view class="empty" v-if="taskList.length === 0 && !loading">
                <u-icon name="list" size="120" color="#ccc"></u-icon>
                <text>暂无任务</text>
            </view>
            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && taskList.length > 0">
                <text>没有更多了</text>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getMyPublishTasks, getMyAcceptTasks } from '../../api/xiaoyuan'

const currentTab = ref('publish')
const currentStatus = ref('')
const taskList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)

const statusList = [
    { value: 0, label: '待支付' },
    { value: 1, label: '待接单' },
    { value: 2, label: '进行中' },
    { value: 3, label: '待确认' },
    { value: 4, label: '已完成' },
    { value: 5, label: '已取消' },
]

const typeMap: Record<string, string> = {
    EXPRESS: '快递代取', TAKEOUT: '外卖代拿', BUY: '帮我购买', QUEUE: '排队占座',
    PRINT: '打印服务', SEAT: '占座自习', ERRAND: '跑腿代办', OTHER: '其他'
}

onShow(() => loadTasks(true))

const loadTasks = async (refresh = false) => {
    if (loading.value) return
    if (refresh) { page.value = 1; hasMore.value = true }
    if (!hasMore.value) return

    loading.value = true
    try {
        const api = currentTab.value === 'publish' ? getMyPublishTasks : getMyAcceptTasks
        const res: any = await api({
            page: page.value,
            limit: 10,
            status: currentStatus.value
        })
        if (res.code === 1) {
            const list = res.data?.list || res.data?.data || []
            if (refresh) {
                taskList.value = list
            } else {
                taskList.value = [...taskList.value, ...list]
            }
            hasMore.value = list.length >= 10
            if (hasMore.value) page.value++
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        refreshing.value = false
    }
}

const switchTab = (tab: string) => {
    currentTab.value = tab
    currentStatus.value = ''
    loadTasks(true)
}

const changeStatus = (status: string) => {
    currentStatus.value = status
    loadTasks(true)
}

const loadMore = () => loadTasks()
const onRefresh = () => { refreshing.value = true; loadTasks(true) }

const getTypeName = (type: string) => typeMap[type] || type
const getStatusName = (s: number) => {
    const m: Record<number, string> = { 0: '待支付', 1: '待接单', 2: '进行中', 3: '待确认', 4: '已完成', 5: '已取消' }
    return m[s] || '未知'
}

const formatTime = (ts: number) => {
    if (!ts) return ''
    const diff = Date.now() / 1000 - ts
    if (diff < 60) return '刚刚'
    if (diff < 3600) return Math.floor(diff / 60) + '分钟前'
    if (diff < 86400) return Math.floor(diff / 3600) + '小时前'
    const d = new Date(ts * 1000)
    return `${d.getMonth() + 1}-${d.getDate()}`
}

const goDetail = (id: number) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/task/detail?id=${id}` })
}
</script>

<style lang="scss" scoped>
.my-task-page {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.tab-bar {
    display: flex;
    background: #fff;
    border-bottom: 1rpx solid #f0f0f0;
}

.tab-item {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 28rpx 0;
    position: relative;

    text { font-size: 30rpx; color: #666; }

    &.active {
        text { color: #333; font-weight: bold; }

        &::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60rpx;
            height: 6rpx;
            background: #c0fe95;
            border-radius: 3rpx;
        }
    }
}

.status-tabs {
    white-space: nowrap;
    background: #fff;
    padding: 16rpx 20rpx;
}

.status-item {
    display: inline-block;
    padding: 10rpx 24rpx;
    margin-right: 12rpx;
    border-radius: 24rpx;
    font-size: 24rpx;
    color: #666;
    background: #f5f5f5;

    &.active {
        background: #c0fe95;
        color: #333;
        font-weight: bold;
    }
}

.list-scroll {
    flex: 1;
    padding: 20rpx;
}

.task-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12rpx;

    .type-tag {
        padding: 6rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        background: #e6f7ff;
        color: #1890ff;
    }
}

.status-tag {
    padding: 6rpx 16rpx;
    border-radius: 20rpx;
    font-size: 22rpx;

    &.s-0 { background: #fff7e6; color: #fa8c16; }
    &.s-1 { background: #e6f7ff; color: #1890ff; }
    &.s-2 { background: #f6ffed; color: #52c41a; }
    &.s-3 { background: #fff2f0; color: #ff4d4f; }
    &.s-4 { background: #f5f5f5; color: #999; }
    &.s-5 { background: #f5f5f5; color: #999; }
}

.card-title {
    display: block;
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 8rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.card-content {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-size: 26rpx;
    color: #666;
    line-height: 1.5;
    margin-bottom: 16rpx;
}

.card-bottom {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16rpx;
    border-top: 1rpx solid #f0f0f0;
}

.reward {
    display: flex;
    align-items: center;
    gap: 8rpx;

    .label { font-size: 24rpx; color: #999; }
    .amount { font-size: 30rpx; color: #ff6b00; font-weight: bold; }
}

.time {
    font-size: 24rpx;
    color: #999;
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;

    text { font-size: 28rpx; color: #999; margin-top: 20rpx; }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;

    text { font-size: 24rpx; color: #999; }
}
</style>
