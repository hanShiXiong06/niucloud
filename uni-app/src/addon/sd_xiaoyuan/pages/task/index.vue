<template>
    <view class="task-page">
        <!-- 类型筛选 -->
        <scroll-view scroll-x class="type-tabs">
            <view class="tab-item" :class="{ active: currentType === '' }" @click="changeType('')">全部</view>
            <view class="tab-item" :class="{ active: currentType === item.value }" v-for="item in typeList" :key="item.value" @click="changeType(item.value)">{{ item.label }}</view>
        </scroll-view>

        <!-- 列表 -->
        <scroll-view scroll-y class="list-scroll" @scrolltolower="loadMore" refresher-enabled @refresherrefresh="onRefresh" :refresher-triggered="refreshing">
            <view class="task-card" v-for="item in taskList" :key="item.id" @click="goDetail(item.id)">
                <view class="card-header">
                    <view class="publisher-info">
                        <image class="publisher-avatar" :src="img(item.publisher_headimg)" mode="aspectFill"></image>
                        <text class="publisher-name">{{ item.publisher_nickname || '匿名用户' }}</text>
                        <view class="credit-badge">
                            <text>信誉 {{ item.publisher_credit_score ?? 100 }}</text>
                        </view>
                    </view>
                    <text class="time">{{ formatTime(item.create_time) }}</text>
                </view>
                <view class="card-tags">
                    <view class="type-tag">{{ getTypeName(item.task_type) }}</view>
                    <view class="urgent-tag" v-if="item.is_urgent">加急</view>
                </view>
                <view class="card-body">
                    <view class="card-info">
                        <text class="title">{{ item.title }}</text>
                        <text class="content">{{ item.content }}</text>
                        <view class="address-row" v-if="item.pickup_address">
                            <u-icon name="map-fill" size="24" color="#999"></u-icon>
                            <text>{{ item.pickup_address }}</text>
                        </view>
                    </view>
                    <image v-if="parseImages(item.images)[0]" class="card-img" :src="img(parseImages(item.images)[0])" mode="aspectFill"></image>
                </view>
                <view class="card-footer">
                    <view class="reward">
                        <text class="reward-label">赏金</text>
                        <text class="reward-amount">¥{{ item.reward }}</text>
                        <text class="tip" v-if="item.tip > 0">+小费¥{{ item.tip }}</text>
                    </view>
                    <view class="status-tag" :class="'s-' + item.status">{{ getStatusName(item.status) }}</view>
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

        <!-- 发布按钮 -->
        <view class="publish-btn" @click="goPublish">
            <u-icon name="plus" size="28" color="#000"></u-icon>
            <text>发布任务</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getTaskList, getTaskTypeList, getCampusAuthInfo } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const currentType = ref('')
const currentSchoolId = ref('')
const taskList = ref<any[]>([])
const typeList = ref<any[]>([])
const loading = ref(false)
const refreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)

const typeMap: Record<string, string> = {
    BUY: '帮我买',
    EXPRESS: '代取快递',
    PRINT: '代打印',
    QUEUE: '代排队',
    SEAT: '代占座',
    TRASH: '扔垃圾',
    CARRY: '帮搬运',
    CLEAN: '代清洁',
    HELP: '帮帮忙',
    GROUP: '拼单',
    GAME: '游戏陪玩',
    ERRAND: '跑腿',
    OTHER: '其他'
}

onMounted(async () => {
    loadTypes()
    // 获取用户学校ID，按学校筛选任务
    try {
        const authRes: any = await getCampusAuthInfo()
        if (authRes.code === 1 && authRes.data?.school_id) {
            currentSchoolId.value = authRes.data.school_id
        }
    } catch (e) {}
    loadTasks()
})

onShow(() => {
    loadTasks(true)
})

const loadTypes = async () => {
    try {
        const res: any = await getTaskTypeList()
        if (res.code === 1 && res.data) {
            typeList.value = res.data
        }
    } catch (e) {
        // fallback
        typeList.value = Object.keys(typeMap).map(k => ({ value: k, label: typeMap[k] }))
    }
}

const loadTasks = async (refresh = false) => {
    if (loading.value) return
    if (refresh) { page.value = 1; hasMore.value = true }
    if (!hasMore.value) return

    loading.value = true
    try {
        const res: any = await getTaskList({
            page: page.value,
            limit: 10,
            task_type: currentType.value,
            school_id: currentSchoolId.value
        })
        if (res.code === 1) {
            const list = res.data?.list || res.data?.data || []
            if (refresh) {
                taskList.value = list
            } else {
                taskList.value = [...taskList.value, ...list]
            }
            if (list.length < 10) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        refreshing.value = false
    }
}

const changeType = (type: string) => {
    currentType.value = type
    loadTasks(true)
}

const loadMore = () => loadTasks()
const onRefresh = () => { refreshing.value = true; loadTasks(true) }

const getTypeName = (type: string) => typeMap[type] || type
const getStatusName = (status: number) => {
    const map: Record<number, string> = { 0: '待支付', 1: '待接单', 2: '进行中', 3: '待确认', 4: '已完成', 5: '已取消' }
    return map[status] || '未知'
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

const parseImages = (images: any) => {
    if (typeof images === 'string') {
        try {
            const parsed = JSON.parse(images)
            if (Array.isArray(parsed)) return parsed
            return images.split(',').filter((s: string) => s)
        } catch {
            return images.split(',').filter((s: string) => s)
        }
    }
    return images || []
}

const goDetail = (id: number) => {
    uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/task/detail?id=${id}` })
}

const goPublish = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/task/publish' })
}
</script>

<style lang="scss" scoped>
.task-page {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.type-tabs {
    white-space: nowrap;
    background: #fff;
    padding: 20rpx;
}

.tab-item {
    display: inline-block;
    padding: 14rpx 28rpx;
    margin-right: 16rpx;
    border-radius: 30rpx;
    font-size: 26rpx;
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
    padding-bottom: 150rpx;
}

.task-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16rpx;

    .publisher-info {
        display: flex;
        align-items: center;
        gap: 12rpx;
    }

    .publisher-avatar {
        width: 52rpx;
        height: 52rpx;
        border-radius: 50%;
        background: #f0f0f0;
    }

    .publisher-name {
        font-size: 26rpx;
        color: #333;
        max-width: 200rpx;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .credit-badge {
        padding: 4rpx 14rpx;
        border-radius: 20rpx;
        background: #f6ffed;
        text {
            font-size: 20rpx;
            color: #52c41a;
        }
    }

    .time {
        font-size: 24rpx;
        color: #999;
        flex-shrink: 0;
    }
}

.card-tags {
    display: flex;
    align-items: center;
    gap: 12rpx;
    margin-bottom: 16rpx;

    .type-tag {
        padding: 6rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        background: #e6f7ff;
        color: #1890ff;
    }

    .urgent-tag {
        padding: 6rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        background: #fff2f0;
        color: #ff4d4f;
    }
}

.card-body {
    display: flex;
    gap: 20rpx;
}

.card-info {
    flex: 1;
    overflow: hidden;

    .title {
        display: block;
        font-size: 30rpx;
        color: #333;
        font-weight: bold;
        margin-bottom: 10rpx;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .content {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        font-size: 26rpx;
        color: #666;
        line-height: 1.5;
    }

    .address-row {
        display: flex;
        align-items: center;
        gap: 6rpx;
        margin-top: 12rpx;
        font-size: 24rpx;
        color: #999;
    }
}

.card-img {
    width: 150rpx;
    height: 150rpx;
    border-radius: 12rpx;
    flex-shrink: 0;
}

.card-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20rpx;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
}

.reward {
    display: flex;
    align-items: center;
    gap: 8rpx;

    .reward-label {
        font-size: 24rpx;
        color: #999;
    }

    .reward-amount {
        font-size: 32rpx;
        color: #ff6b00;
        font-weight: bold;
    }

    .tip {
        font-size: 22rpx;
        color: #ff6b00;
    }
}

.status-tag {
    padding: 6rpx 20rpx;
    border-radius: 20rpx;
    font-size: 22rpx;

    &.s-0 { background: #fff7e6; color: #fa8c16; }
    &.s-1 { background: #e6f7ff; color: #1890ff; }
    &.s-2 { background: #f6ffed; color: #52c41a; }
    &.s-3 { background: #fff2f0; color: #ff4d4f; }
    &.s-4 { background: #f5f5f5; color: #999; }
    &.s-5 { background: #f5f5f5; color: #999; }
}

.publish-btn {
    position: fixed;
    bottom: 50rpx;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 12rpx;
    padding: 24rpx 60rpx;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    border-radius: 16rpx;
    box-shadow: 0 4rpx 20rpx rgba(170, 246, 155, 0.5);

    text {
        font-size: 30rpx;
        color: #000;
        font-weight: bold;
    }
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
