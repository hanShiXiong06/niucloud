<template>
    <view class="income-page">
        <!-- 收益统计 -->
        <view class="stat-section">
            <view class="stat-header">
                <text class="label">累计收益(元)</text>
                <text class="total">{{ totalIncome }}</text>
            </view>
            <view class="stat-tabs">
                <view class="tab-item" :class="{ active: currentType === 'week' }" @click="changeType('week')">近7天</view>
                <view class="tab-item" :class="{ active: currentType === 'month' }" @click="changeType('month')">近30天</view>
                <view class="tab-item" :class="{ active: currentType === 'all' }" @click="changeType('all')">全部</view>
            </view>
        </view>

        <!-- 收益列表 -->
        <view class="list-section">
            <view class="section-title">收益明细</view>
            <scroll-view 
                scroll-y 
                class="income-scroll"
                @scrolltolower="loadMore"
            >
                <view class="income-item" v-for="item in incomeList" :key="item.id + '-' + item.income_type">
                    <view class="item-left">
                        <text class="order-no">{{ item.order_no ? '订单 ' + item.order_no : '' }}</text>
                        <text class="task-type" v-if="item.task_type">{{ getTaskTypeName(item.task_type) }}</text>
                        <text class="income-tag" :class="item.income_type">{{ item.remark || '订单收益' }}</text>
                        <text class="time">{{ formatTime(item.time) }}</text>
                    </view>
                    <view class="item-right">
                        <text class="amount" :class="{ tip: item.income_type === 'tip' }">+¥{{ item.amount }}</text>
                    </view>
                </view>

                <view class="empty" v-if="incomeList.length === 0 && !loading">
                    <u-icon name="rmb-circle" size="120" color="#ccc"></u-icon>
                    <text>暂无收益记录</text>
                </view>

                <view class="loading-more" v-if="loading">
                    <u-loading-icon mode="circle" color="#c0fe95"></u-loading-icon>
                </view>

                <view class="no-more" v-if="!hasMore && incomeList.length > 0">
                    <text>没有更多了</text>
                </view>
            </scroll-view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getRunnerIncome } from '../../api/runner'

const currentType = ref('week')
const totalIncome = ref('0.00')
const incomeList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 20

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '帮我买',
    'SEND': '帮我送',
    'ERRAND': '跑腿代办',
    'QUEUE': '代排队',
    'PRINT': '帮打印',
    'SEAT': '代占座',
    'CLEAN': '代清洁',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'HELP': '帮帮忙',
    'GAME': '游戏陪练',
    'GROUP': '拼单'
}

onMounted(() => {
    loadIncome()
})

const loadIncome = async (refresh = false) => {
    if (loading.value) return
    
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    if (!hasMore.value) return
    
    loading.value = true
    
    try {
        const res: any = await getRunnerIncome({
            type: currentType.value,
            page: page.value,
            limit: limit
        })
        
        if (res.code === 1) {
            if (refresh) {
                incomeList.value = res.data.list
            } else {
                incomeList.value = [...incomeList.value, ...res.data.list]
            }
            
            totalIncome.value = res.data.total_income
            
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
    }
}

const changeType = (type: string) => {
    currentType.value = type
    loadIncome(true)
}

const loadMore = () => {
    loadIncome()
}

const getTaskTypeName = (type: string) => {
    return taskTypeMap[type] || type
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getMonth() + 1}-${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}
</script>

<style lang="scss" scoped>
.income-page {
    min-height: 100vh;
    background: #f7f7f7;
    display: flex;
    flex-direction: column;
}

.stat-section {
    background: linear-gradient(135deg, #c0fe95, #88f78d);
    padding: 40rpx 30rpx;
    
    .stat-header {
        text-align: center;
        margin-bottom: 20rpx;
        
        .label {
            display: block;
            font-size: 28rpx;
            color: #333;
            margin-bottom: 16rpx;
        }
        
        .total {
            font-size: 64rpx;
            color: #333;
            font-weight: bold;
        }
    }
    
    .stat-tabs {
        display: flex;
        background: rgba(255, 255, 255, 0.5);
        border-radius: 30rpx;
        padding: 6rpx;
        
        .tab-item {
            flex: 1;
            text-align: center;
            padding: 16rpx 0;
            font-size: 26rpx;
            color: #666;
            border-radius: 24rpx;
            
            &.active {
                background: #fff;
                color: #333;
                font-weight: bold;
            }
        }
    }
}

.list-section {
    flex: 1;
    background: #fff;
    margin: 20rpx;
    border-radius: 16rpx;
    padding: 24rpx;
    display: flex;
    flex-direction: column;
}

.section-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.income-scroll {
    flex: 1;
}

.income-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    &:last-child {
        border-bottom: none;
    }
    
    .item-left {
        .order-no {
            display: block;
            font-size: 28rpx;
            color: #333;
            margin-bottom: 8rpx;
        }
        
        .task-type {
            display: inline-block;
            font-size: 22rpx;
            color: #52c41a;
            background: #f0f7ff;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
            margin-right: 12rpx;
        }
        
        .income-tag {
            display: inline-block;
            font-size: 22rpx;
            padding: 4rpx 12rpx;
            border-radius: 4rpx;
            margin-right: 12rpx;
            
            &.order {
                color: #52c41a;
                background: #f0fdf4;
            }
            
            &.tip {
                color: #f97316;
                background: #fff7ed;
            }
        }
        
        .time {
            display: block;
            font-size: 24rpx;
            color: #999;
            margin-top: 8rpx;
        }
    }
    
    .item-right {
        .amount {
            font-size: 28rpx;
            color: #ff6b00;
            font-weight: bold;
            
            &.tip {
                color: #f97316;
            }
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
</style>
