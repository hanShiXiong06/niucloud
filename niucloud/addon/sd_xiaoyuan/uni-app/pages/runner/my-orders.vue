<template>
    <view class="my-orders">
        <!-- 状态筛选 -->
        <view class="status-tabs">
            <view 
                class="tab-item" 
                :class="{ active: currentStatus === '' }"
                @click="changeStatus('')"
            >全部</view>
            <view 
                class="tab-item" 
                :class="{ active: currentStatus === '20' }"
                @click="changeStatus('20')"
            >待取货</view>
            <view 
                class="tab-item" 
                :class="{ active: currentStatus === '40' }"
                @click="changeStatus('40')"
            >配送中</view>
            <view
                class="tab-item"
                :class="{ active: currentStatus === '45' }"
                @click="changeStatus('45')"
            >用户确认</view>
            <view 
                class="tab-item" 
                :class="{ active: currentStatus === '50' }"
                @click="changeStatus('50')"
            >已完成</view>
        </view>

        <!-- 订单列表 -->
        <scroll-view 
            scroll-y 
            class="order-scroll"
            @scrolltolower="loadMore"
            refresher-enabled
            :refresher-triggered="isRefreshing"
            @refresherrefresh="onRefresh"
        >
            <sd-order-item 
                    v-for="order in orderList" 
                    :key="order.id" 
                    :order="order"
                    :show-user="true"
                    :runner-income-mode="true"
                    @click="goToDetail(order.id)"
                >
                    <template #actions>
                        <button class="btn-reject" v-if="order.status === 20" @click.stop="rejectOrder(order.id)">拒绝</button>
                        <button class="btn-action" v-if="order.status === 20" @click.stop="pickupOrder(order.id)">确认取货</button>
                        <button class="btn-action" v-if="order.status === 30" @click.stop="deliveryOrder(order.id)">开始配送</button>
                        <button class="btn-action" v-if="order.status === 40" @click.stop="openProofPopup(order.id)">确认送达</button>
                        <button class="btn-detail" @click.stop="goToDetail(order.id)">查看详情</button>
                    </template>
                </sd-order-item>

            <view class="empty" v-if="orderList.length === 0 && !loading">
                <u-icon name="order" size="120" color="#ccc"></u-icon>
                <text>暂无订单</text>
            </view>

            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
            </view>

            <view class="no-more" v-if="!hasMore && orderList.length > 0">
                <text>没有更多了</text>
            </view>
        </scroll-view>

        <!-- 完成凭证弹窗 -->
        <view class="proof-mask" v-if="showProofPopup" @click="showProofPopup = false"></view>
        <view class="proof-popup" v-if="showProofPopup">
            <view class="proof-title">上传完成凭证</view>
            <view class="proof-desc">请上传送达/完成凭证图片（必填）</view>
            <xy-upload v-model="proofImages" :maxCount="4" />
            <view class="proof-btns">
                <button class="proof-btn-cancel" @click="showProofPopup = false">取消</button>
                <button class="proof-btn-confirm" @click="confirmComplete" :disabled="proofImages.length === 0">确认提交</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getMyOrders, pickupOrder as pickupOrderApi, deliveryOrder as deliveryOrderApi, completeOrder as completeOrderApi, rejectOrder as rejectOrderApi } from '../../api/runner'
import xyUpload from '../../components/xy-upload.vue'
import sdOrderItem from '../../components/sd-order-item.vue'

const currentStatus = ref('')
const orderList = ref<any[]>([])
const loading = ref(false)
const isRefreshing = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 10

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '代买服务',
    'SEND': '帮我送',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'CLASS': '代上课',
    'PRINT': '帮打印',
    'SEAT': '代占座',
    'CLEAN': '代清洁',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'HELP': '帮帮忙',
    'GAME': '游戏陪练',
    'GROUP': '拼单',
    'PARTTIME': '兼职招聘',
    'COMPANION': '约伴组局'
}

const statusMap: Record<number, string> = {
    20: '待取货',
    30: '取货中',
    40: '配送中',
    45: '用户确认',
    50: '已完成'
}

onMounted(() => {
    loadOrders()
})

const loadOrders = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getMyOrders({
            status: currentStatus.value,
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            if (refresh) {
                orderList.value = res.data.list
            } else {
                orderList.value = [...orderList.value, ...res.data.list]
            }
            
            if (res.data.list.length < limit) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
        isRefreshing.value = false
    }
}

const changeStatus = (status: string) => {
    currentStatus.value = status
    loadOrders(true)
}

const onRefresh = () => {
    isRefreshing.value = true
    loadOrders(true)
}

const loadMore = () => {
    loadOrders()
}

const getTaskTypeName = (type: string) => {
    return taskTypeMap[type] || type
}

const getStatusText = (status: number) => {
    return statusMap[status] || '未知'
}

const calculateIncome = (fee: number) => {
    return (fee * 0.8).toFixed(2)
}

const pickupOrder = async (id: number) => {
    try {
        uni.showLoading({ title: '操作中...' })
        const res: any = await pickupOrderApi({ id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '已确认取货', icon: 'success' })
            loadOrders(true)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const deliveryOrder = async (id: number) => {
    try {
        uni.showLoading({ title: '操作中...' })
        const res: any = await deliveryOrderApi({ id })
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '已开始配送', icon: 'success' })
            loadOrders(true)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const showProofPopup = ref(false)
const proofImages = ref<string[]>([])
const proofOrderId = ref(0)

const openProofPopup = (id: number) => {
    proofOrderId.value = id
    proofImages.value = []
    showProofPopup.value = true
}

const confirmComplete = async () => {
    if (proofImages.value.length === 0) {
        uni.showToast({ title: '请上传凭证图片', icon: 'none' })
        return
    }
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await completeOrderApi({ id: proofOrderId.value, proof_images: proofImages.value })
        uni.hideLoading()
        showProofPopup.value = false
        if (res.code === 1) {
            uni.showToast({ title: '已提交，待用户确认', icon: 'success' })
            loadOrders(true)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const rejectOrder = (id: number) => {
    uni.showModal({
        title: '拒绝订单',
        content: '确定要拒绝这个订单吗？',
        success: async (res) => {
            if (res.confirm) {
                try {
                    uni.showLoading({ title: '操作中...' })
                    const result: any = await rejectOrderApi({ id, reason: '骑手拒绝接单' })
                    uni.hideLoading()
                    
                    if (result.code === 1) {
                        uni.showToast({ title: '已拒绝订单', icon: 'success' })
                        loadOrders(true)
                    } else {
                        uni.showToast({ title: result.msg || '操作失败', icon: 'none' })
                    }
                } catch (e) {
                    uni.hideLoading()
                    uni.showToast({ title: '网络错误', icon: 'none' })
                }
            }
        }
    })
}

const goToDetail = (id: number) => {
    uni.navigateTo({
        url: `/addon/sd_xiaoyuan/pages/runner/order-detail?id=${id}`
    })
}
</script>

<style lang="scss" scoped>
.my-orders {
    min-height: 100vh;
    background: #f5f5f5;
    display: flex;
    flex-direction: column;
}

.status-tabs {
    display: flex;
    background: #fff;
    padding: 20rpx 0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.tab-item {
    flex: 1;
    text-align: center;
    font-size: 28rpx;
    color: #666;
    padding: 16rpx 0;
    position: relative;
    
    &.active {
        color: #333;
        font-weight: bold;
        
        &::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 40rpx;
            height: 6rpx;
            background: #c0fe95;
            border-radius: 3rpx;
        }
    }
}

.order-scroll {
    flex: 1;
    padding: 20rpx;
}

.order-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20rpx;
    
    .task-type {
        display: flex;
        align-items: center;
        gap: 12rpx;
        
        .type-tag {
            padding: 6rpx 16rpx;
            background: #c0fe95;
            color: #333;
            font-size: 24rpx;
            border-radius: 6rpx;
            font-weight: bold;
        }
        
        .urgent-tag {
            padding: 6rpx 16rpx;
            background: #fff2f0;
            color: #ff4d4f;
            font-size: 24rpx;
            border-radius: 6rpx;
        }
    }
    
    .order-status {
        font-size: 26rpx;
        font-weight: bold;
        
        &.status-20, &.status-30 { color: #ff9500; }
        &.status-40 { color: #52c41a; }
        &.status-50 { color: #999; }
    }
}

.order-content {
    .address-info {
        .address-item {
            display: flex;
            align-items: center;
            margin-bottom: 12rpx;
            
            .dot {
                width: 12rpx;
                height: 12rpx;
                border-radius: 50%;
                margin-right: 16rpx;
                
                &.pickup { background: #c0fe95; }
                &.receive { background: #000; }
            }
            
            .address {
                font-size: 28rpx;
                color: #333;
                flex: 1;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
        }
    }
}

.order-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20rpx;
    padding-top: 20rpx;
    border-top: 1rpx solid #f0f0f0;
    
    .price-info {
        .label {
            font-size: 24rpx;
            color: #999;
            margin-right: 8rpx;
        }
        
        .price {
            font-size: 28rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
    .action-btns {
        display: flex;
        gap: 16rpx;
        
        button {
            padding: 0 24rpx;
            height: 60rpx;
            line-height: 60rpx;
            font-size: 26rpx;
            border-radius: 30rpx;
        }
        
        .btn-reject {
            background: #fff;
            color: #666;
            border: 1rpx solid #ddd;
        }
        
        .btn-action {
            background: linear-gradient(135deg, #52c41a, #389e0d);
            color: #fff;
            border: none;
        }
        
        .btn-detail {
            background: #fff;
            color: #52c41a;
            border: 1rpx solid #52c41a;
        }
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 100rpx 0;
    
    text {
        font-size: 28rpx;
        color: #999;
        margin-top: 20rpx;
    }
}

.loading-more, .no-more {
    padding: 24rpx 0;
    text-align: center;
    display: flex;
    justify-content: center;
    
    text {
        font-size: 24rpx;
        color: #999;
    }
}

.proof-mask {
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.5);
    z-index: 100;
}

.proof-popup {
    position: fixed;
    left: 0; right: 0; bottom: 0;
    background: #fff;
    border-radius: 24rpx 24rpx 0 0;
    padding: 40rpx 30rpx calc(40rpx + env(safe-area-inset-bottom));
    z-index: 101;

    .proof-title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 12rpx;
    }

    .proof-desc {
        font-size: 26rpx;
        color: #999;
        margin-bottom: 30rpx;
    }

    .proof-images {
        display: flex;
        flex-wrap: wrap;
        gap: 16rpx;
        margin-bottom: 30rpx;
    }

    .proof-img-item {
        width: 150rpx;
        height: 150rpx;
        border-radius: 12rpx;
        overflow: hidden;
        position: relative;

        image { width: 100%; height: 100%; }

        .proof-img-del {
            position: absolute;
            top: 0; right: 0;
            width: 40rpx; height: 40rpx;
            background: rgba(0,0,0,0.5);
            color: #fff;
            font-size: 28rpx;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0 0 0 12rpx;
        }
    }

    .proof-img-add {
        width: 150rpx;
        height: 150rpx;
        border: 2rpx dashed #ddd;
        border-radius: 12rpx;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .proof-btns {
        display: flex;
        gap: 20rpx;

        button {
            flex: 1;
            height: 80rpx;
            line-height: 80rpx;
            border-radius: 40rpx;
            font-size: 28rpx;
        }

        .proof-btn-cancel {
            background: #f5f5f5;
            color: #666;
        }

        .proof-btn-confirm {
            background: linear-gradient(135deg, #52c41a, #389e0d);
            color: #fff;

            &[disabled] {
                opacity: 0.5;
            }
        }
    }
}
</style>
