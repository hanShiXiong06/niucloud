<template>
    <view class="task-detail" v-if="task">
        <!-- 状态栏 -->
        <view class="status-bar" :class="'s-' + task.status">
            <text class="status-text">{{ getStatusName(task.status) }}</text>
            <text class="status-desc">{{ getStatusDesc(task.status) }}</text>
        </view>

        <!-- 基本信息 -->
        <view class="section">
            <view class="section-header">
                <view class="type-tag">{{ getTypeName(task.task_type) }}</view>
                <view class="urgent-tag" v-if="task.is_urgent">加急</view>
                <text class="task-no">{{ task.task_no }}</text>
            </view>
            <text class="task-title">{{ task.title }}</text>
            <text class="task-content" v-if="task.content">{{ task.content }}</text>

            <!-- 图片 -->
            <view class="image-list" v-if="images.length > 0">
                <image v-for="(imgUrl, idx) in images" :key="idx" :src="img(imgUrl)" mode="aspectFill" @click="previewImage(idx)"></image>
            </view>
        </view>

        <!-- 地址信息 -->
        <view class="section" v-if="task.pickup_address || task.delivery_address">
            <view class="addr-item" v-if="task.pickup_address">
                <view class="addr-dot pickup"></view>
                <view class="addr-info">
                    <text class="addr-label">取件地址</text>
                    <text class="addr-text">{{ task.pickup_address }}</text>
                </view>
            </view>
            <view class="addr-line" v-if="task.pickup_address && task.delivery_address"></view>
            <view class="addr-item" v-if="task.delivery_address">
                <view class="addr-dot delivery"></view>
                <view class="addr-info">
                    <text class="addr-label">送达地址</text>
                    <text class="addr-text">{{ task.delivery_address }}</text>
                </view>
            </view>
        </view>

        <!-- 快递信息 -->
        <view class="section" v-if="task.express_company || task.express_no || task.pickup_code">
            <view class="info-row" v-if="task.express_company">
                <text class="label">快递公司</text>
                <text class="value">{{ task.express_company }}</text>
            </view>
            <view class="info-row" v-if="task.express_no">
                <text class="label">快递单号</text>
                <text class="value">{{ task.express_no }}</text>
            </view>
            <view class="info-row" v-if="task.pickup_code">
                <text class="label">取件码</text>
                <text class="value highlight">{{ task.pickup_code }}</text>
            </view>
        </view>

        <!-- 联系信息 -->
        <view class="section" v-if="task.contact_name || task.contact_mobile">
            <view class="info-row" v-if="task.contact_name">
                <text class="label">联系人</text>
                <text class="value">{{ task.contact_name }}</text>
            </view>
            <view class="info-row" v-if="task.contact_mobile">
                <text class="label">联系电话</text>
                <text class="value" @click="callPhone(task.contact_mobile)">{{ task.contact_mobile }} 📞</text>
            </view>
        </view>

        <!-- 费用信息 -->
        <view class="section fee-section">
            <view class="info-row">
                <text class="label">赏金</text>
                <text class="value price">¥{{ task.reward }}</text>
            </view>
            <view class="info-row" v-if="task.tip > 0">
                <text class="label">小费</text>
                <text class="value price">¥{{ task.tip }}</text>
            </view>
            <view class="info-row total">
                <text class="label">合计</text>
                <text class="value price">¥{{ task.total_amount }}</text>
            </view>
        </view>

        <!-- 时间信息 -->
        <view class="section">
            <view class="info-row" v-if="task.deadline">
                <text class="label">截止时间</text>
                <text class="value">{{ formatFullTime(task.deadline) }}</text>
            </view>
            <view class="info-row">
                <text class="label">发布时间</text>
                <text class="value">{{ formatFullTime(task.create_time) }}</text>
            </view>
            <view class="info-row" v-if="task.accept_time">
                <text class="label">接单时间</text>
                <text class="value">{{ formatFullTime(task.accept_time) }}</text>
            </view>
            <view class="info-row" v-if="task.complete_time">
                <text class="label">完成时间</text>
                <text class="value">{{ formatFullTime(task.complete_time) }}</text>
            </view>
        </view>

        <!-- 底部操作 -->
        <view class="bottom-bar" v-if="showActions">
            <!-- 待接单：其他人可以接单 -->
            <view class="action-btn accept" v-if="task.status === 1 && !isOwner" @click="handleAccept">
                <text>接受任务</text>
            </view>
            <!-- 待支付：发布者支付 -->
            <view class="action-btn pay" v-if="task.status === 0 && isOwner" @click="handlePay">
                <text>去支付</text>
            </view>
            <!-- 进行中：接单员提交完成 -->
            <view class="action-btn complete" v-if="task.status === 2 && isRunner" @click="handleSubmitComplete">
                <text>提交完成</text>
            </view>
            <!-- 待确认：发布者确认完成 -->
            <view class="action-btn confirm" v-if="task.status === 3 && isOwner" @click="handleConfirmComplete">
                <text>确认完成</text>
            </view>
            <!-- 取消 -->
            <view class="action-btn cancel" v-if="(task.status === 0 || task.status === 1) && isOwner" @click="handleCancel">
                <text>取消任务</text>
            </view>
        </view>
    </view>

    <view class="loading-page" v-else>
        <u-loading-icon mode="circle" color="#c0fe95" size="60"></u-loading-icon>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getTaskInfo, acceptTask, payTask, submitCompleteTask, confirmCompleteTask, cancelTask } from '../../api/xiaoyuan'
import { img } from '@/utils/common'

const task = ref<any>(null)
const taskId = ref(0)
const currentMemberId = ref(0)

onLoad((options: any) => {
    taskId.value = parseInt(options?.id || '0')
    
    const memberInfo = uni.getStorageSync('member_info')
    if (memberInfo) {
        currentMemberId.value = memberInfo.member_id || 0
    }
    
    if (taskId.value) loadTask()
})

const loadTask = async () => {
    try {
        const res: any = await getTaskInfo(taskId.value)
        if (res.code === 1) {
            task.value = res.data
        }
    } catch (e) {
        console.error(e)
        uni.showToast({ title: '加载失败', icon: 'none' })
    }
}

const images = computed(() => {
    if (!task.value?.images) return []
    if (typeof task.value.images === 'string') {
        try {
            const parsed = JSON.parse(task.value.images)
            if (Array.isArray(parsed)) return parsed
            return task.value.images.split(',').filter((s: string) => s)
        } catch {
            return task.value.images.split(',').filter((s: string) => s)
        }
    }
    return task.value.images || []
})

const isOwner = computed(() => task.value && task.value.member_id === currentMemberId.value)
const isRunner = computed(() => task.value && task.value.runner_id === currentMemberId.value)
const showActions = computed(() => task.value && (task.value.status < 4))

const typeMap: Record<string, string> = {
    EXPRESS: '快递代取', TAKEOUT: '外卖代拿', BUY: '帮我购买', QUEUE: '排队占座',
    PRINT: '打印服务', SEAT: '占座自习', ERRAND: '跑腿代办', OTHER: '其他'
}
const getTypeName = (type: string) => typeMap[type] || type
const getStatusName = (s: number) => {
    const m: Record<number, string> = { 0: '待支付', 1: '待接单', 2: '进行中', 3: '待确认', 4: '已完成', 5: '已取消' }
    return m[s] || '未知'
}
const getStatusDesc = (s: number) => {
    const m: Record<number, string> = {
        0: '请尽快完成支付', 1: '等待接单员接单中...', 2: '接单员正在执行任务',
        3: '接单员已提交完成，请确认', 4: '任务已完成', 5: '任务已取消'
    }
    return m[s] || ''
}

const formatFullTime = (ts: number) => {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')} ${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
}

const previewImage = (idx: any) => {
    uni.previewImage({ urls: images.value.map((u: string) => img(u)), current: idx })
}

const callPhone = (phone: string) => {
    uni.makePhoneCall({ phoneNumber: phone })
}

const handleAccept = async () => {
    uni.showModal({
        title: '确认接单',
        content: '确定接受该任务吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const r: any = await acceptTask({ task_id: taskId.value })
                    if (r.code === 1) {
                        uni.showToast({ title: '接单成功' })
                        loadTask()
                    } else {
                        uni.showToast({ title: r.msg || '操作失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '操作失败', icon: 'none' })
                }
            }
        }
    })
}

const handlePay = () => {
    uni.showToast({ title: '支付功能开发中', icon: 'none' })
}

const handleSubmitComplete = async () => {
    uni.showModal({
        title: '提交完成',
        content: '确认已完成该任务？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const r: any = await submitCompleteTask({ task_id: taskId.value })
                    if (r.code === 1) {
                        uni.showToast({ title: '已提交' })
                        loadTask()
                    } else {
                        uni.showToast({ title: r.msg || '操作失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '操作失败', icon: 'none' })
                }
            }
        }
    })
}

const handleConfirmComplete = async () => {
    uni.showModal({
        title: '确认完成',
        content: '确认任务已完成？赏金将发放给接单员',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const r: any = await confirmCompleteTask({ task_id: taskId.value })
                    if (r.code === 1) {
                        uni.showToast({ title: '已确认完成' })
                        loadTask()
                    } else {
                        uni.showToast({ title: r.msg || '操作失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '操作失败', icon: 'none' })
                }
            }
        }
    })
}

const handleCancel = async () => {
    uni.showModal({
        title: '取消任务',
        content: '确定取消该任务吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    const r: any = await cancelTask({ task_id: taskId.value })
                    if (r.code === 1) {
                        uni.showToast({ title: '已取消' })
                        loadTask()
                    } else {
                        uni.showToast({ title: r.msg || '操作失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.showToast({ title: '操作失败', icon: 'none' })
                }
            }
        }
    })
}
</script>

<style lang="scss" scoped>
.task-detail {
    min-height: 100vh;
    background: #f5f5f5;
    padding-bottom: 140rpx;
}

.loading-page {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
}

.status-bar {
    padding: 40rpx 30rpx;
    color: #fff;

    &.s-0 { background: linear-gradient(135deg, #fa8c16, #faad14); }
    &.s-1 { background: linear-gradient(135deg, #1890ff, #40a9ff); }
    &.s-2 { background: linear-gradient(135deg, #52c41a, #73d13d); }
    &.s-3 { background: linear-gradient(135deg, #ff4d4f, #ff7875); }
    &.s-4 { background: linear-gradient(135deg, #999, #bbb); }
    &.s-5 { background: linear-gradient(135deg, #999, #bbb); }

    .status-text { display: block; font-size: 36rpx; font-weight: bold; }
    .status-desc { display: block; font-size: 26rpx; margin-top: 8rpx; opacity: 0.9; }
}

.section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
}

.section-header {
    display: flex;
    align-items: center;
    margin-bottom: 16rpx;

    .type-tag {
        padding: 6rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        background: #e6f7ff;
        color: #1890ff;
    }

    .urgent-tag {
        margin-left: 12rpx;
        padding: 6rpx 16rpx;
        border-radius: 6rpx;
        font-size: 22rpx;
        background: #fff2f0;
        color: #ff4d4f;
    }

    .task-no {
        margin-left: auto;
        font-size: 22rpx;
        color: #999;
    }
}

.task-title {
    display: block;
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 12rpx;
}

.task-content {
    display: block;
    font-size: 28rpx;
    color: #666;
    line-height: 1.6;
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-top: 20rpx;

    image {
        width: 200rpx;
        height: 200rpx;
        border-radius: 12rpx;
    }
}

.addr-item {
    display: flex;
    align-items: flex-start;
    gap: 16rpx;
}

.addr-dot {
    width: 20rpx;
    height: 20rpx;
    border-radius: 50%;
    margin-top: 8rpx;
    flex-shrink: 0;

    &.pickup { background: #1890ff; }
    &.delivery { background: #52c41a; }
}

.addr-line {
    width: 2rpx;
    height: 30rpx;
    background: #ddd;
    margin-left: 9rpx;
}

.addr-info {
    .addr-label { display: block; font-size: 24rpx; color: #999; }
    .addr-text { display: block; font-size: 28rpx; color: #333; margin-top: 4rpx; }
}

.info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12rpx 0;

    .label { font-size: 28rpx; color: #999; }
    .value { font-size: 28rpx; color: #333; }
    .value.price { color: #ff6b00; font-weight: bold; }
    .value.highlight { color: #ff4d4f; font-weight: bold; font-size: 32rpx; }

    &.total {
        border-top: 1rpx solid #f0f0f0;
        margin-top: 8rpx;
        padding-top: 16rpx;
        .label { font-weight: bold; color: #333; }
        .value { font-size: 34rpx; }
    }
}

.bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    gap: 20rpx;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -2rpx 10rpx rgba(0, 0, 0, 0.05);
}

.action-btn {
    flex: 1;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 24rpx;
    border-radius: 12rpx;
    
    text { font-size: 30rpx; font-weight: bold; }

    &.accept { background: linear-gradient(to top, #aaf69b, #d1ff7c); text { color: #000; } }
    &.pay { background: #1890ff; text { color: #fff; } }
    &.complete { background: #52c41a; text { color: #fff; } }
    &.confirm { background: linear-gradient(to top, #aaf69b, #d1ff7c); text { color: #000; } }
    &.cancel { background: #f5f5f5; text { color: #999; } }
}
</style>
