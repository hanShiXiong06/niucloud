<template>
    <view class="credit-log-page">
        <view class="header-card">
            <view class="score-display">
                <text class="score-value">{{ creditInfo.credit_score || 100 }}</text>
                <text class="score-label">当前信誉分</text>
            </view>
            <view class="stats-row">
                <view class="stat-item">
                    <text class="num">{{ creditInfo.total_complete || 0 }}</text>
                    <text class="label">完成订单</text>
                </view>
                <view class="stat-item">
                    <text class="num">{{ creditInfo.total_cancel || 0 }}</text>
                    <text class="label">取消订单</text>
                </view>
                <view class="stat-item">
                    <text class="num">{{ creditInfo.total_complaint || 0 }}</text>
                    <text class="label">被投诉</text>
                </view>
            </view>
        </view>

        <view class="list-section">
            <view class="section-title">变动记录</view>
            <view class="log-list">
                <view class="log-item" v-for="item in logList" :key="item.id">
                    <view class="log-info">
                        <text class="log-type">{{ getTypeName(item.type) }}</text>
                        <text class="log-remark">{{ item.remark }}</text>
                        <text class="log-time">{{ formatTime(item.create_time) }}</text>
                    </view>
                    <view class="log-score" :class="item.change_score > 0 ? 'positive' : 'negative'">
                        {{ item.change_score > 0 ? '+' : '' }}{{ item.change_score }}
                    </view>
                </view>
                <view class="empty-state" v-if="logList.length === 0 && !loading">
                    <u-icon name="file-text" size="60" color="#ccc"></u-icon>
                    <text>暂无变动记录</text>
                </view>
            </view>
            <view class="loading-more" v-if="loading">
                <u-loading-icon mode="circle" color="#52c41a"></u-loading-icon>
            </view>
            <view class="no-more" v-if="!hasMore && logList.length > 0">
                <text>—— 没有更多了 ——</text>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { getCreditInfo, getCreditLogList } from '../../api/xiaoyuan'

const creditInfo = ref<any>({})
const logList = ref<any[]>([])
const loading = ref(false)
const hasMore = ref(true)
const page = ref(1)
const limit = 20

onMounted(() => {
    loadCreditInfo()
    loadLogList()
})

const loadCreditInfo = async () => {
    const res: any = await getCreditInfo()
    if (res.code === 1 && res.data) {
        creditInfo.value = res.data
    }
}

const loadLogList = async (refresh = false) => {
    if (loading.value) return
    if (refresh) {
        page.value = 1
        hasMore.value = true
    }
    if (!hasMore.value) return

    loading.value = true
    const res: any = await getCreditLogList({ page: page.value, limit })
    loading.value = false

    if (res.code === 1) {
        const list = res.data.data || res.data.list || []
        if (refresh) {
            logList.value = list
        } else {
            logList.value = [...logList.value, ...list]
        }
        if (list.length < limit) {
            hasMore.value = false
        } else {
            page.value++
        }
    }
}

const getTypeName = (type: string) => {
    const map: Record<string, string> = {
        'COMPLETE': '完成订单',
        'CANCEL': '取消订单',
        'COMPLAINT': '投诉扣分',
        'ADMIN': '管理员调整'
    }
    return map[type] || type
}

const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
}
</script>

<style lang="scss" scoped>
.credit-log-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
}

.header-card {
    background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
    border-radius: 20rpx;
    padding: 40rpx;
    margin-bottom: 20rpx;

    .score-display {
        text-align: center;
        margin-bottom: 30rpx;

        .score-value {
            display: block;
            font-size: 80rpx;
            font-weight: bold;
            color: #fff;
        }

        .score-label {
            font-size: 26rpx;
            color: rgba(255, 255, 255, 0.8);
        }
    }

    .stats-row {
        display: flex;
        justify-content: space-around;

        .stat-item {
            text-align: center;

            .num {
                display: block;
                font-size: 36rpx;
                font-weight: bold;
                color: #fff;
            }

            .label {
                font-size: 24rpx;
                color: rgba(255, 255, 255, 0.8);
            }
        }
    }
}

.list-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;

    .section-title {
        font-size: 30rpx;
        font-weight: bold;
        color: #333;
        margin-bottom: 20rpx;
    }
}

.log-list {
    .log-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 24rpx 0;
        border-bottom: 1rpx solid #f0f0f0;

        &:last-child {
            border-bottom: none;
        }

        .log-info {
            flex: 1;

            .log-type {
                display: block;
                font-size: 28rpx;
                color: #333;
                font-weight: 500;
            }

            .log-remark {
                display: block;
                font-size: 24rpx;
                color: #999;
                margin-top: 8rpx;
            }

            .log-time {
                display: block;
                font-size: 22rpx;
                color: #ccc;
                margin-top: 8rpx;
            }
        }

        .log-score {
            font-size: 32rpx;
            font-weight: bold;

            &.positive {
                color: #52c41a;
            }

            &.negative {
                color: #ff4d4f;
            }
        }
    }
}

.empty-state {
    text-align: center;
    padding: 80rpx 0;

    text {
        display: block;
        margin-top: 20rpx;
        font-size: 28rpx;
        color: #999;
    }
}

.loading-more {
    text-align: center;
    padding: 30rpx 0;
}

.no-more {
    text-align: center;
    padding: 30rpx 0;

    text {
        font-size: 24rpx;
        color: #ccc;
    }
}
</style>
