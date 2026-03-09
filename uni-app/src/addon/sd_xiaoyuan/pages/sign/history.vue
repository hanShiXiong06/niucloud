<template>
    <view class="history-page">
        <view class="stat-card">
            <view class="stat-item">
                <text class="value">{{ totalDays }}</text>
                <text class="label">累计签到(天)</text>
            </view>
            <view class="stat-item">
                <text class="value">{{ totalPoints }}</text>
                <text class="label">累计获得积分</text>
            </view>
        </view>

        <view class="calendar-card">
            <view class="calendar-header">
                <text class="arrow" @click="prevMonth">‹</text>
                <text class="month">{{ currentYear }}年{{ currentMonth }}月</text>
                <text class="arrow" @click="nextMonth">›</text>
            </view>
            <view class="calendar-weekdays">
                <text v-for="day in weekDays" :key="day">{{ day }}</text>
            </view>
            <view class="calendar-days">
                <view 
                    class="day-item" 
                    v-for="(day, index) in calendarDays" 
                    :key="index"
                    :class="{ 
                        empty: !day.date,
                        signed: day.signed,
                        today: day.isToday
                    }"
                >
                    <text v-if="day.date">{{ day.date }}</text>
                    <text class="dot" v-if="day.signed">✓</text>
                </view>
            </view>
        </view>

        <view class="history-list">
            <view class="list-title">签到记录</view>
            <view class="list-item" v-for="item in historyList" :key="item.id">
                <view class="item-left">
                    <text class="date">{{ item.sign_date }}</text>
                    <text class="days">连续{{ item.continuous_days }}天</text>
                </view>
                <view class="item-right">
                    <text class="points">+{{ item.reward_points }}积分</text>
                </view>
            </view>
            <view class="empty" v-if="historyList.length === 0">
                <text>暂无签到记录</text>
            </view>
            <view class="load-more" v-if="hasMore" @click="loadMore">
                <text>加载更多</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted, computed } from 'vue'
import { getSignHistory } from '../../api/xiaoyuan'

const weekDays = ['日', '一', '二', '三', '四', '五', '六']
const currentYear = ref(new Date().getFullYear())
const currentMonth = ref(new Date().getMonth() + 1)
const historyList = ref<any[]>([])
const signedDates = ref<string[]>([])
const page = ref(1)
const hasMore = ref(true)

const totalDays = computed(() => historyList.value.length)
const totalPoints = computed(() => historyList.value.reduce((sum, item) => sum + (item.reward_points || 0), 0))

const calendarDays = computed(() => {
    const year = currentYear.value
    const month = currentMonth.value
    const firstDay = new Date(year, month - 1, 1).getDay()
    const daysInMonth = new Date(year, month, 0).getDate()
    const today = new Date()
    const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`
    
    const days: any[] = []
    
    for (let i = 0; i < firstDay; i++) {
        days.push({ date: null })
    }
    
    for (let i = 1; i <= daysInMonth; i++) {
        const dateStr = `${year}-${String(month).padStart(2, '0')}-${String(i).padStart(2, '0')}`
        days.push({
            date: i,
            signed: signedDates.value.includes(dateStr),
            isToday: dateStr === todayStr
        })
    }
    
    return days
})

onMounted(() => {
    loadHistory()
})

const loadHistory = async (refresh = false) => {
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    
    try {
        const res: any = await getSignHistory({ page: page.value, limit: 20 })
        if (res.code === 1) {
            const list = res.data.list || []
            if (refresh) {
                historyList.value = list
            } else {
                historyList.value = [...historyList.value, ...list]
            }
            
            list.forEach((item: any) => {
                if (item.sign_date && !signedDates.value.includes(item.sign_date)) {
                    signedDates.value.push(item.sign_date)
                }
            })
            
            if (list.length < 20) {
                hasMore.value = false
            } else {
                page.value++
            }
        }
    } catch (e) {
        console.error(e)
    }
}

const loadMore = () => {
    loadHistory()
}

const prevMonth = () => {
    if (currentMonth.value === 1) {
        currentMonth.value = 12
        currentYear.value--
    } else {
        currentMonth.value--
    }
}

const nextMonth = () => {
    if (currentMonth.value === 12) {
        currentMonth.value = 1
        currentYear.value++
    } else {
        currentMonth.value++
    }
}
</script>

<style lang="scss" scoped>
.history-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
}

.stat-card {
    display: flex;
    background: linear-gradient(135deg, #c0fe95, #88f78d);
    border-radius: 16rpx;
    padding: 40rpx;
    margin-bottom: 20rpx;
    
    .stat-item {
        flex: 1;
        text-align: center;
        
        .value {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
        }
        
        .label {
            font-size: 26rpx;
            color: rgba(0,0,0,0.6);
        }
    }
}

.calendar-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24rpx;
    
    .month {
        font-size: 28rpx;
        font-weight: bold;
        color: #333;
    }
    
    .arrow {
        font-size: 28rpx;
        color: #666;
        padding: 10rpx 20rpx;
    }
}

.calendar-weekdays {
    display: flex;
    margin-bottom: 16rpx;
    
    text {
        flex: 1;
        text-align: center;
        font-size: 26rpx;
        color: #999;
    }
}

.calendar-days {
    display: flex;
    flex-wrap: wrap;
}

.day-item {
    width: calc(100% / 7);
    height: 80rpx;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    position: relative;
    
    text {
        font-size: 28rpx;
        color: #333;
    }
    
    .dot {
        font-size: 20rpx;
        color: #ff6b00;
    }
    
    &.empty text { color: transparent; }
    
    &.signed {
        text { color: #333; font-weight: bold; }
    }
    
    &.today {
        background: #c0fe95;
        border-radius: 8rpx;
        text { color: #333; font-weight: bold; }
    }
}

.history-list {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
}

.list-title {
    font-size: 30rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.list-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 0;
    border-bottom: 1rpx solid #f0f0f0;
    
    .item-left {
        .date {
            display: block;
            font-size: 28rpx;
            color: #333;
        }
        
        .days {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .item-right {
        .points {
            font-size: 28rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
}

.empty {
    text-align: center;
    padding: 60rpx;
    
    text { font-size: 28rpx; color: #999; }
}

.load-more {
    text-align: center;
    padding: 24rpx;
    
    text {
        font-size: 26rpx;
        color: #333;
    }
}
</style>
