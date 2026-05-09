<template>
    <view class="runner-index">
        <!-- 未申请/审核中状态 -->
        <view class="apply-section" v-if="!runnerInfo.id || runnerInfo.status === 0 || runnerInfo.status === 2">
            <view class="apply-header">
                <view class="apply-bg"></view>
            </view>
            <view class="apply-content" v-if="!runnerInfo.id">
                <text class="title">成为校园接单员</text>
                <text class="desc">加入我们，利用空闲时间赚取收益</text>
                <view class="benefits">
                    <view class="benefit-item">
                        <u-icon name="red-packet" size="48" color="#52c41a"></u-icon>
                        <text>收益丰厚</text>
                    </view>
                    <view class="benefit-item">
                        <u-icon name="clock" size="48" color="#1890ff"></u-icon>
                        <text>时间自由</text>
                    </view>
                    <view class="benefit-item">
                        <u-icon name="checkmark-circle" size="48" color="#ff9500"></u-icon>
                        <text>安全保障</text>
                    </view>
                </view>
                <button class="apply-btn" @click="goToApply">立即申请</button>
            </view>
            <view class="apply-content pending-review-card" v-else-if="runnerInfo.status === 0">
                <view class="pending-badge">审核中</view>
                <view class="status-icon pending">
                    <u-icon name="clock" size="60" color="#ff9500"></u-icon>
                </view>
                <text class="title">资料审核中</text>
                <text class="desc">您已提交申请，请耐心等待工作人员审核，无需重复填写</text>
                <button class="back-btn" @click="uni.navigateBack()">返回</button>
            </view>
            <view class="apply-content" v-else-if="runnerInfo.status === 2">
                <view class="status-icon rejected">
                    <u-icon name="close-circle" size="60" color="#ff4d4f"></u-icon>
                </view>
                <text class="title">审核未通过</text>
                <text class="desc">{{ runnerInfo.refuse_reason || '您的申请未通过审核' }}</text>
                <button class="apply-btn" @click="goToApply">重新申请</button>
            </view>
        </view>

        <!-- 已通过审核 -->
        <view class="runner-main" v-else-if="runnerInfo.status === 1">
            <!-- 顶部状态栏 -->
            <view class="top-header">
                <view class="back-btn" @click="goToMemberCenter">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                    <text>返回</text>
                </view>
                <view class="user-info">
                    <view class="avatar-wrapper">
                        <image class="avatar" :src="img(runnerAvatar)" mode="aspectFill"></image>
                        <view class="online-toggle" :class="{ online: runnerInfo.is_online === 1 }" @click.stop="toggleOnline">
                            <view class="toggle-dot"></view>
                            <text>{{ runnerInfo.is_online === 1 ? '接单中' : '已离线' }}</text>
                        </view>
                    </view>
                    <view class="info">
                        <text class="name">{{ runnerInfo.real_name }}</text>
                        <view class="level">
                            <text class="level-tag" :class="'level-' + runnerInfo.level" @click.stop="showLevelRules = true">{{ runnerInfo.level_name || '普通接单员' }}</text>
                            <view class="score">
                                <u-icon name="star-fill" size="14" color="#ff9500"></u-icon>
                                <text>{{ runnerInfo.avg_score || '5.0' }}</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 数据统计 -->
            <view class="stat-section">
                <view class="stat-item" @click="goToIncome">
                    <text class="value">¥{{ stat.balance }}</text>
                    <text class="label">可提现</text>
                </view>
                <view class="stat-item">
                    <text class="value">¥{{ stat.today_income }}</text>
                    <text class="label">今日收益</text>
                </view>
                <view class="stat-item">
                    <text class="value">{{ stat.today_orders }}</text>
                    <text class="label">今日订单</text>
                </view>
                <view class="stat-item">
                    <text class="value">{{ stat.complete_orders }}</text>
                    <text class="label">累计完成</text>
                </view>
            </view>

            <!-- 等级进度条 -->
            <view class="level-progress-section" v-if="nextLevel">
                <view class="lp-header">
                    <text class="lp-current">{{ runnerInfo.level_name || 'Lv.' + runnerInfo.level }}</text>
                    <text class="lp-next">{{ nextLevel.name }}</text>
                </view>
                <view class="lp-bar">
                    <view class="lp-bar-fill" :style="{ width: levelProgress + '%' }"></view>
                </view>
                <text class="lp-desc">还需完成 {{ Math.max(0, nextLevel.min_orders - (runnerInfo.complete_orders || 0)) }} 单即可升级</text>
            </view>

            <!-- 功能入口 -->
            <view class="quick-menu">
                <view class="qm-item" @click="goToIncome">
                    <view class="qm-icon" style="background: linear-gradient(135deg, #ff9500, #ff6b00);">
                        <u-icon name="red-packet" size="26" color="#fff"></u-icon>
                    </view>
                    <text>收益明细</text>
                </view>
                <view class="qm-item" @click="goToCashOut">
                    <view class="qm-icon" style="background: linear-gradient(135deg, #52c41a, #389e0d);">
                        <u-icon name="account" size="26" color="#fff"></u-icon>
                    </view>
                    <text>提现申请</text>
                </view>
                <view class="qm-item" @click="goToEvaluates">
                    <view class="qm-icon" style="background: linear-gradient(135deg, #ff9500, #faad14);">
                        <u-icon name="star" size="26" color="#fff"></u-icon>
                    </view>
                    <text>我的评价</text>
                </view>
                <view class="qm-item" @click="goToSettings">
                    <view class="qm-icon" style="background: linear-gradient(135deg, #999, #666);">
                        <u-icon name="setting" size="26" color="#fff"></u-icon>
                    </view>
                    <text>接单设置</text>
                </view>
            </view>

            <!-- 接单列表 -->
            <view class="order-section">
                <view class="order-section-title">
                    <text>我的接单</text>
                    <text class="to-hall-link" @click.stop="goToOrderHall">去接单</text>
                </view>

                <view class="order-list">
                    <sd-order-item 
                        v-for="order in myOrders" 
                        :key="order.id" 
                        :order="order"
                        :show-user="true"
                        @click="goToOrderDetail(order.id)"
                    >
                        <template #actions>
                            <button class="btn-action" v-if="order.status === 20" @click.stop="handleStatus20Action(order)">{{ getStatus20Text(order.task_type) }}</button>
                            <button class="btn-action" v-if="order.status === 30" @click.stop="handleDelivery(order)">{{ getDeliveryText(order.task_type) }}</button>
                            <button class="btn-action" v-if="order.status === 40" @click.stop="handleComplete(order)">{{ getCompleteText(order.task_type) }}</button>
                        </template>
                    </sd-order-item>

                    <view class="empty-orders" v-if="myOrders.length === 0 && !orderLoading">
                        <u-icon name="order" size="80" color="#ccc"></u-icon>
                        <text>暂无订单</text>
                    </view>

                    <view class="loading-more" v-if="orderLoading">
                        <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
                    </view>

                    <view class="load-more-btn" v-if="orderHasMore && !orderLoading && myOrders.length > 0" @click="loadMoreOrders">
                        <text>加载更多</text>
                    </view>

                    <view class="no-more" v-if="!orderHasMore && myOrders.length > 0">
                        <text>没有更多了</text>
                    </view>
                </view>
            </view>
        </view>

        <!-- 凭证图片上传弹窗 -->
        <view class="proof-mask" v-if="showProofPopup" @click="showProofPopup = false"></view>
        <view class="proof-popup" v-if="showProofPopup">
            <view class="proof-title">{{ proofAction === 'complete' ? '上传完成凭证' : '上传任务凭证' }}</view>
            <view class="proof-desc">{{ proofAction === 'complete' ? '请上传完成凭证图片（必填）' : '可上传任务凭证图片（选填）' }}</view>
            
            <xy-upload v-model="proofImages" :maxCount="4" />
            
            <view class="proof-btns"> 
                <button class="proof-btn-cancel" @click="showProofPopup = false">取消</button>
                <button class="proof-btn-confirm" @click="confirmProofAction" :disabled="proofAction === 'complete' && proofImages.length === 0">确认提交</button>
            </view>
        </view>

        <!-- 等级规则弹窗 -->
        <view class="proof-mask" v-if="showLevelRules" @click="showLevelRules = false"></view>
        <view class="level-rules-popup" v-if="showLevelRules">
            <view class="proof-title">接单员等级规则</view>
            <view class="level-rule-item" v-for="lv in levelList" :key="lv.level">
                <view class="lr-header">
                    <text class="lr-name" :class="{ 'lr-current': lv.level === runnerInfo.level }">{{ lv.name }}</text>
                    <text class="lr-rate">佣金比例 {{ lv.commission_rate }}%</text>
                </view>
                <text class="lr-desc">累计完成 {{ lv.min_orders }} 单即可升级</text>
            </view>
            <button class="proof-btn-confirm" style="margin-top:24rpx;" @click="showLevelRules = false">我知道了</button>
        </view>

        <!-- 已禁用状态（单独判断，避免与弹窗层打断 v-else 链） -->
        <view class="disabled-section" v-if="runnerInfo.status === 3">
            <view class="disabled-icon">
                <u-icon name="close-circle" size="80" color="#ff4d4f"></u-icon>
            </view>
            <text class="title">账号已被禁用</text>
            <text class="desc">如有疑问，请联系客服</text>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import { getRunnerInfo, setOnline, getRunnerStat, getMyOrders, pickupOrder, deliveryOrder, completeOrder } from '../../api/runner'
import { getRunnerLevels } from '../../api/xiaoyuan'
import useMemberStore from '@/stores/member'
import { img } from '@/utils/common'
import sdOrderItem from '../../components/sd-order-item.vue'
import { getStatus20Text, getDeliveryText, getCompleteText, needPickupStep } from '../../utils/order-status'
import xyUpload from '../../components/xy-upload.vue'

const memberStore = useMemberStore()
const runnerInfo = ref<any>({})

const runnerAvatar = computed(() => {
    if (runnerInfo.value.avatar) return runnerInfo.value.avatar
    if (memberStore.info?.headimg) return memberStore.info.headimg
    return '/static/resource/images/default_headimg.png'
})
const stat = ref({
    balance: '0.00',
    today_income: '0.00',
    today_orders: 0,
    complete_orders: 0
})
// 等级相关
const levelList = ref<any[]>([])
const showLevelRules = ref(false)
const nextLevel = computed(() => {
    if (!levelList.value.length || !runnerInfo.value.level) return null
    return levelList.value.find((lv: any) => lv.level > runnerInfo.value.level) || null
})
const levelProgress = computed(() => {
    if (!nextLevel.value || !runnerInfo.value.level) return 0
    const currentLv = levelList.value.find((lv: any) => lv.level === runnerInfo.value.level)
    const currentMin = currentLv?.min_orders || 0
    const nextMin = nextLevel.value.min_orders
    const completed = runnerInfo.value.complete_orders || 0
    if (nextMin <= currentMin) return 100
    return Math.min(100, Math.round(((completed - currentMin) / (nextMin - currentMin)) * 100))
})

// 凭证上传相关
const showProofPopup = ref(false)
const proofAction = ref<'delivery' | 'complete'>('complete')
const proofOrderId = ref(0)
const proofImages = ref<string[]>([])
const myOrders = ref<any[]>([])
const orderLoading = ref(false)
const orderHasMore = ref(true)
const myPage = ref(1)

onMounted(() => {
    loadRunnerInfo()
    
    // 监听申请成功事件
    uni.$on('runnerApplySuccess', () => {
        loadRunnerInfo()
    })
})

onShow(() => {
    // 每次页面显示都重新加载接单员信息
    loadRunnerInfo()
})

const loadRunnerInfo = async () => {
    try {
        const res: any = await getRunnerInfo()
        if (res.code === 1) {
            runnerInfo.value = res.data || {}
            if (runnerInfo.value.status === 1) {
                loadStat()
                loadOrders(true)
                loadLevels()
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const loadLevels = async () => {
    try {
        const res: any = await getRunnerLevels()
        if (res.code === 1) {
            levelList.value = res.data || []
        }
    } catch (e) {
        console.error(e)
    }
}

const loadStat = async () => {
    try {
        const res: any = await getRunnerStat()
        if (res.code === 1) {
            stat.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const loadOrders = async (refresh = false) => {
    if (orderLoading.value) return
    
    if (refresh) {
        myPage.value = 1
        myOrders.value = []
        orderHasMore.value = true
    }
    
    if (!orderHasMore.value && !refresh) return

    orderLoading.value = true
    try {
        const res: any = await getMyOrders({ page: myPage.value, limit: 10 })
        
        if (res.code === 1) {
            const list = res.data?.list || res.data?.data || []
            
            if (refresh) {
                myOrders.value = list
            } else {
                const existingIds = new Set(myOrders.value.map(o => o.id))
                const newOrders = list.filter((o: any) => !existingIds.has(o.id))
                myOrders.value = [...myOrders.value, ...newOrders]
            }
            
            if (list.length < 10) {
                orderHasMore.value = false
            } else {
                myPage.value++
            }
        }
    } catch (e) {
        console.error('加载订单失败:', e)
    } finally {
        orderLoading.value = false
    }
}

const loadMoreOrders = () => loadOrders()

const toggleOnline = async () => {
    const newStatus = runnerInfo.value.is_online === 1 ? 0 : 1
    try {
        const res: any = await setOnline({ is_online: newStatus })
        if (res.code === 1) {
            runnerInfo.value.is_online = newStatus
            uni.showToast({ title: newStatus === 1 ? '已开始接单' : '已停止接单', icon: 'success' })
            if (newStatus === 1) loadOrders(true)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

// 状态20时的操作处理
const handleStatus20Action = (order: any) => {
    const type = order.task_type
    // 需要取货步骤的类型
    if (needPickupStep(type)) {
        handlePickup(order)
    } else {
        // 其他类型直接进入下一步
        handleDelivery(order)
    }
}

const getExtTags = (order: any) => {
    const tags: string[] = []
    let ext: any = {}
    if (order.ext) {
        try { ext = typeof order.ext === 'string' ? JSON.parse(order.ext) : order.ext } catch {}
    }
    const taskType = order.task_type || ''
    if (taskType === 'GAME') {
        if (ext.game_type) tags.push(ext.game_type)
        if (ext.service_type) tags.push(ext.service_type)
        if (ext.rank_level) tags.push(ext.rank_level)
        if (ext.voice_chat) tags.push('语音陪玩')
    } else if (taskType === 'PRINT') {
        if (ext.print_side) tags.push(ext.print_side === 'single' ? '单面' : '双面')
        if (ext.print_color) tags.push(ext.print_color === 'color' ? '彩印' : '黑白')
        if (ext.paper_size) tags.push(ext.paper_size)
        if (ext.page_count) tags.push(ext.page_count + '页')
        if (ext.files && ext.files.length > 0) tags.push(ext.files.length + '个文件')
    } else if (taskType === 'EXPRESS') {
        if (ext.express_company) tags.push(ext.express_company)
        if (ext.weight) tags.push(ext.weight + 'kg')
    } else if (taskType === 'BUY') {
        if (ext.shop_name) tags.push(ext.shop_name)
    }
    return tags.slice(0, 4)
}

const formatTime = (timestamp: any) => {
    if (!timestamp) return ''
    const ts = typeof timestamp === 'number' ? timestamp : parseInt(timestamp)
    if (isNaN(ts) || ts <= 0) return String(timestamp)
    const date = new Date(ts * 1000)
    return `${date.getMonth() + 1}-${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}

const goToApply = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/apply' })
const goToOrderDetail = (id: number) => uni.navigateTo({ url: `/addon/sd_xiaoyuan/pages/runner/order-detail?id=${id}` })
const goToOrderHall = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/order-hall' })
const goToIncome = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/income' })
const goToCashOut = () => uni.navigateTo({ url: '/app/pages/member/apply_cash_out' })
const goToEvaluates = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/evaluates' })
const goToSettings = () => uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/runner/settings' })
const goToMemberCenter = () => uni.navigateTo({ url: '/app/pages/member/index' })

const refreshAll = async () => {
    loadStat()
    
    myPage.value = 1
    orderHasMore.value = true
    myOrders.value = []
    
    try {
        const myRes: any = await getMyOrders({ page: 1, limit: 10 })
        
        if (myRes?.code === 1) {
            const list = myRes.data?.list || myRes.data?.data || []
            myOrders.value = list
            myPage.value = list.length >= 10 ? 2 : 1
        }
    } catch (e) {
        console.error('刷新订单失败:', e)
    }
}

const handlePickup = async (order: any) => {
    try {
        uni.showLoading({ title: '操作中...', mask: true })
        const res: any = await pickupOrder({ id: order.id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '已确认取货', icon: 'success' })
            // 延迟刷新，确保后端数据已更新
            setTimeout(() => {
                refreshAll()
            }, 500)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const handleDelivery = (order: any) => {
    proofAction.value = 'delivery'
    proofOrderId.value = order.id
    proofImages.value = []
    showProofPopup.value = true
}

const handleComplete = (order: any) => {
    proofAction.value = 'complete'
    proofOrderId.value = order.id
    proofImages.value = []
    showProofPopup.value = true
}

const confirmProofAction = async () => {
    if (proofAction.value === 'complete' && proofImages.value.length === 0) {
        uni.showToast({ title: '请上传凭证图片', icon: 'none' })
        return
    }
    
    try {
        uni.showLoading({ title: '提交中...', mask: true })
        const api = proofAction.value === 'complete' ? completeOrder : deliveryOrder
        const res: any = await api({ id: proofOrderId.value, proof_images: proofImages.value })
        uni.hideLoading()
        
        if (res.code === 1) {
            showProofPopup.value = false
            proofImages.value = []
            
            const successMsg = proofAction.value === 'complete' ? '订单已完成' : '已开始任务'
            uni.showToast({ title: successMsg, icon: 'success' })
            
            // 延迟刷新，确保后端数据已更新
            setTimeout(() => {
                refreshAll()
            }, 500)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.runner-index {
    min-height: 100vh;
    background: #f5f5f5;
}

.apply-section {
    .apply-header {
        .apply-bg {
            width: 100%;
            height: 400rpx;
            background: linear-gradient(135deg, #c0fe95, #88f78d);
        }
    }
    
    .apply-content {
        background: #fff;
        margin: -60rpx 30rpx 0;
        border-radius: 16rpx;
        padding: 40rpx;
        text-align: center;
        position: relative;
        
        .title {
            display: block;
            font-size: 40rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 16rpx;
        }
        
        .desc {
            display: block;
            font-size: 28rpx;
            color: #666;
            margin-bottom: 40rpx;
        }
    }
    
    .benefits {
        display: flex;
        justify-content: space-around;
        margin-bottom: 40rpx;
        
        .benefit-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16rpx;
            
            text {
                font-size: 26rpx;
                color: #666;
            }
        }
    }
    
    .apply-btn {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        border: none;
        border-radius: 16rpx;
        padding: 0 40rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 28rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

        &::after {
            border: none;
        }
    }
    
    .back-btn {
        background: #f5f5f5;
        color: #666;
        border: none;
        border-radius: 16rpx;
        padding: 0 40rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 28rpx;
        font-weight: bold;
        margin-top: 20rpx;

        &::after {
            border: none;
        }
    }
    
    .status-icon {
        width: 120rpx;
        height: 120rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 30rpx;
        
        &.pending { background: #fff7e6; }
        &.rejected { background: #fff2f0; }
    }

    .pending-review-card {
        padding-top: 20rpx;
        .pending-badge {
            display: inline-block;
            padding: 10rpx 28rpx;
            border-radius: 999rpx;
            background: linear-gradient(135deg, #fff7e6, #ffe7ba);
            color: #d46b08;
            font-size: 24rpx;
            font-weight: bold;
            margin-bottom: 28rpx;
        }
    }
}

.runner-main {
    .top-header {
        background: linear-gradient(135deg, #c0fe95, #e8ffcc);
        padding: 24rpx 30rpx 40rpx;
        padding-top: calc(var(--status-bar-height) + 20rpx);
        position: relative;
        
        .back-btn {
            position: absolute;
            top: calc(var(--status-bar-height) + 30rpx);
            left: 30rpx;
            display: flex;
            align-items: center;
            gap: 6rpx;
            padding: 10rpx 20rpx;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 30rpx;
            z-index: 10;
            
            text {
                font-size: 24rpx;
                color: #333;
            }
        }
    }
    
    .user-info {
        display: flex;
        align-items: center;
        margin-top: 60rpx;
        
        .avatar-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-right: 20rpx;
            
            .avatar {
                width: 90rpx;
                height: 90rpx;
                border-radius: 50%;
                border: 4rpx solid rgba(255, 255, 255, 0.5);
                margin-bottom: 12rpx;
            }
            
            .online-toggle {
                display: flex;
                align-items: center;
                gap: 8rpx;
                padding: 8rpx 16rpx;
                border-radius: 20rpx;
                background: rgba(0, 0, 0, 0.06);
                transition: all 0.3s;

                .toggle-dot {
                    width: 12rpx;
                    height: 12rpx;
                    border-radius: 50%;
                    background: #999;
                }

                text {
                    font-size: 20rpx;
                    color: #666;
                    font-weight: bold;
                }

                &.online {
                    background: rgba(82, 196, 26, 0.15);

                    .toggle-dot { background: #52c41a; }
                    text { color: #52c41a; }
                }
            }
        }
        
        .info {
            .name {
                font-size: 32rpx;
                color: #333;
                font-weight: bold;
            }
            
            .level {
                display: flex;
                align-items: center;
                margin-top: 8rpx;
                
                .level-tag {
                    font-size: 22rpx;
                    padding: 4rpx 12rpx;
                    border-radius: 20rpx;
                    margin-right: 12rpx;
                    
                    &.level-1 { background: rgba(0, 0, 0, 0.06); color: #666; }
                    &.level-2 { background: #ff9500; color: #fff; }
                }
                
                .score {
                    display: flex;
                    align-items: center;
                    gap: 4rpx;
                    color: #333;
                    font-size: 24rpx;
                }
            }
        }
    }
}

.stat-section {
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 30rpx 24rpx;
    display: flex;
    
    .stat-item {
        flex: 1;
        text-align: center;
        
        .value {
            display: block;
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 8rpx;
        }
        
        .label {
            font-size: 24rpx;
            color: #999;
        }
    }
}

.quick-menu {
    background: #fff;
    margin: 0 20rpx 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
    display: flex;

    .qm-item {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12rpx;

        .qm-icon {
            width: 80rpx;
            height: 80rpx;
            border-radius: 20rpx;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        text {
            font-size: 24rpx;
            color: #333;
        }
    }
}

.order-section {
    margin: 0 20rpx;
    border-radius: 16rpx;
    overflow: hidden;
}

/* 原「订单大厅 / 我的订单」Tab 已关闭，仅保留我的订单列表 */

.order-section-title {
    padding: 8rpx 4rpx 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1rpx solid #f5f5f5;

    text {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
    }

    .to-hall-link {
        font-size: 24rpx;
        color: #1890ff;
        font-weight: normal;
    }
}

.order-list {
    padding:0rpx;
}

.order-card {
    background: #f8fafc;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 16rpx;

    &:active { background: #f0f0f0; }
}

.oc-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12rpx;

    .oc-type {
        display: flex;
        align-items: center;
        gap: 8rpx;
    }
}

.ext-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8rpx;
    margin-bottom: 12rpx;

    .ext-tag {
        display: inline-block;
        padding: 4rpx 12rpx;
        background: #e8e8e8;
        color: #666;
        font-size: 22rpx;
        border-radius: 6rpx;
    }
}

.oc-header .oc-type .type-tag {
    font-size: 24rpx;
    padding: 4rpx 14rpx;
    background: #e6f7ff;
    color: #1890ff;
    border-radius: 6rpx;
}

.oc-header .oc-type .urgent-tag {
    font-size: 22rpx;
    padding: 4rpx 12rpx;
    background: #fff2f0;
    color: #ff4d4f;
    border-radius: 6rpx;
}

.oc-status {
    font-size: 24rpx;
    font-weight: bold;

    &.s-10 { color: #1890ff; }
    &.s-20 { color: #52c41a; }
    &.s-30 { color: #ff9500; }
    &.s-40 { color: #722ed1; }
    &.s-50 { color: #999; }
    &.s-60 { color: #999; }
}

.oc-body {
    margin-bottom: 16rpx;

    .addr-row {
        display: flex;
        align-items: center;
        gap: 12rpx;
        margin-bottom: 8rpx;

        .addr-dot {
            width: 14rpx;
            height: 14rpx;
            border-radius: 50%;
            flex-shrink: 0;

            &.pickup { background: #1890ff; }
            &.receive { background: #52c41a; }
        }

        text {
            font-size: 26rpx;
            color: #333;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    }
}

.oc-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16rpx;
    border-top: 1rpx solid #f0f0f0;

    .oc-time { font-size: 24rpx; color: #999; }
    .oc-price { font-size: 30rpx; color: #ff6b00; font-weight: bold; }
}

.oc-actions {
    display: flex;
    justify-content: flex-end;
    gap: 16rpx;
    margin-top: 16rpx;

    button {
        margin: 0;
        padding: 0 36rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 26rpx;
        font-weight: bold;
        border-radius: 32rpx;
        border: none;

        &::after { border: none; }
    }

    .btn-accept2 {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        border: none;
        border-radius: 16rpx;
        padding: 0 40rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 28rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

        &::after {
            border: none;
        }
    }

   
}
 .btn-action {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        border: none;
        border-radius: 16rpx;
        padding: 0 40rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 28rpx;
        font-weight: bold;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

        &::after {
            border: none;
        }
    }
.empty-orders {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 60rpx 0;

    text { font-size: 26rpx; color: #999; margin-top: 16rpx; }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;

    text { font-size: 24rpx; color: #999; }
}

.load-more-btn {
    display: flex;
    justify-content: center;
    padding: 16rpx 0;

    text {
        font-size: 26rpx;
        color: #1890ff;
        padding: 14rpx 50rpx;
        background: #e6f7ff;
        border-radius: 30rpx;
    }
}

// 订单号和下单人
.oc-order-no {
    font-size: 22rpx;
    color: #94a3b8;
    margin-bottom: 6rpx;
}

.oc-member {
    font-size: 24rpx;
    color: #64748b;
    margin-bottom: 12rpx;
}

// 等级进度条
.level-progress-section {
    background: #fff;
    margin: 0 20rpx 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;

    .lp-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12rpx;

        .lp-current {
            font-size: 26rpx;
            font-weight: bold;
            color: #333;
        }

        .lp-next {
            font-size: 24rpx;
            color: #999;
        }
    }

    .lp-bar {
        height: 16rpx;
        background: #f0f0f0;
        border-radius: 8rpx;
        overflow: hidden;
        margin-bottom: 12rpx;

        .lp-bar-fill {
            height: 100%;
            background: linear-gradient(to right, #c0fe95, #52c41a);
            border-radius: 8rpx;
            transition: width 0.5s ease;
        }
    }

    .lp-desc {
        font-size: 22rpx;
        color: #999;
    }
}

// 凭证上传弹窗
.proof-mask {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 998;
}

.proof-popup, .level-rules-popup {
    position: fixed;
    left: 40rpx;
    right: 40rpx;
    top: 50%;
    transform: translateY(-50%);
    background: #fff;
    border-radius: 24rpx;
    padding: 40rpx;
    z-index: 999;
    max-height: 80vh;
    overflow-y: auto;
}

.proof-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    text-align: center;
    margin-bottom: 16rpx;
}

.proof-desc {
    font-size: 26rpx;
    color: #999;
    text-align: center;
    margin-bottom: 30rpx;
}

.proof-images {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-bottom: 30rpx;

    .proof-img-item {
        width: 160rpx;
        height: 160rpx;
        border-radius: 12rpx;
        overflow: hidden;
        position: relative;

        image {
            width: 100%;
            height: 100%;
        }

        .proof-img-del {
            position: absolute;
            top: 0;
            right: 0;
            width: 40rpx;
            height: 40rpx;
            background: rgba(0, 0, 0, 0.5);
            color: #fff;
            text-align: center;
            line-height: 40rpx;
            font-size: 28rpx;
            border-radius: 0 12rpx 0 12rpx;
        }
    }

    .proof-img-add {
        width: 160rpx;
        height: 160rpx;
        background: #f8f8f8;
        border: 2rpx dashed #ddd;
        border-radius: 12rpx;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8rpx;

        text {
            font-size: 22rpx;
            color: #999;
        }
    }
}

.proof-btns {
    display: flex;
    gap: 20rpx;
    margin-top: 30rpx;

    button {
        flex: 1;
        border: none;
        border-radius: 16rpx;
        padding: 0 40rpx;
        height: 64rpx;
        line-height: 64rpx;
        font-size: 28rpx;
        font-weight: bold;

        &::after { border: none; }
    }

    .proof-btn-cancel {
        background: #f5f5f5;
        color: #666;
    }

    .proof-btn-confirm {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

        &[disabled] {
            opacity: 0.5;
        }
    }
}

// 等级规则弹窗
.level-rule-item {
    background: #f8fafc;
    border-radius: 12rpx;
    padding: 20rpx;
    margin-bottom: 16rpx;

    .lr-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8rpx;

        .lr-name {
            font-size: 28rpx;
            font-weight: bold;
            color: #333;

            &.lr-current {
                color: #52c41a;
            }
        }

        .lr-rate {
            font-size: 24rpx;
            color: #ff9500;
            font-weight: bold;
        }
    }

    .lr-desc {
        font-size: 24rpx;
        color: #999;
    }
}

.disabled-section {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
    padding: 60rpx;
    
    .title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin: 30rpx 0 16rpx;
    }
    
    .desc {
        font-size: 28rpx;
        color: #999;
    }
}
</style>
