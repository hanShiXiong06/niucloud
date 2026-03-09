<template>
    <view class="record-page">
        <view class="header">
            <view class="total-points">
                <text class="label">我的积分</text>
                <text class="value">{{ myPoints }}</text>
            </view>
        </view>

        <view class="tabs">
            <view 
                class="tab-item" 
                :class="{ active: activeTab === 'all' }" 
                @click="activeTab = 'all'"
            >
                全部
            </view>
            <view 
                class="tab-item" 
                :class="{ active: activeTab === 'earn' }" 
                @click="activeTab = 'earn'"
            >
                获得
            </view>
            <view 
                class="tab-item" 
                :class="{ active: activeTab === 'spend' }" 
                @click="activeTab = 'spend'"
            >
                消费
            </view>
        </view>

        <view class="record-list">
            <view 
                class="record-item" 
                v-for="item in recordList" 
                :key="item.id"
            >
                <view class="record-info">
                    <text class="record-title">{{ item.title }}</text>
                    <text class="record-time">{{ item.create_time }}</text>
                </view>
                <view class="record-points" :class="{ earn: item.type === 'earn', spend: item.type === 'spend' }">
                    <text>{{ item.type === 'earn' ? '+' : '-' }}{{ item.points }}</text>
                </view>
            </view>

            <view class="empty" v-if="recordList.length === 0 && !loading">
                <u-empty description="暂无记录" />
            </view>

            <u-loading-icon v-if="loading" />
        </view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted, watch } from 'vue'
import { getMyPoints, getPointsRecord } from '../../api/xiaoyuan'

const loading = ref(false)
const activeTab = ref('all')
const myPoints = ref(0)
const recordList = ref<any[]>([])

onMounted(() => {
    loadMyPoints()
    loadRecords()
})

watch(activeTab, () => {
    loadRecords()
})

const loadMyPoints = async () => {
    try {
        const res: any = await getMyPoints()
        if (res.code === 1) {
            myPoints.value = res.data.points || 0
        }
    } catch (e) {
        console.error(e)
    }
}

const loadRecords = async () => {
    loading.value = true
    try {
        const params: any = {}
        if (activeTab.value === 'earn') {
            params.type = 'earn'
        } else if (activeTab.value === 'spend') {
            params.type = 'spend'
        }

        const res: any = await getPointsRecord(params)
        if (res.code === 1) {
            recordList.value = res.data.list || []
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss" scoped>
.record-page {
    min-height: 100vh;
    background: #f7f7f7;
}

.header {
    background: linear-gradient(135deg, #7ed957, #c0fe95);
    padding: 40rpx 30rpx;

    .total-points {
        text-align: center;
        color: #fff;

        .label {
            display: block;
            font-size: 28rpx;
            margin-bottom: 10rpx;
        }

        .value {
            font-size: 60rpx;
            font-weight: bold;
        }
    }
}

.tabs {
    display: flex;
    background: #fff;
    border-bottom: 1rpx solid #f5f5f5;

    .tab-item {
        flex: 1;
        text-align: center;
        padding: 30rpx 0;
        font-size: 28rpx;
        color: #666;
        position: relative;

        &.active {
            color: #7ed957;
            font-weight: 500;

            &::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 50%;
                transform: translateX(-50%);
                width: 60rpx;
                height: 4rpx;
                background: #7ed957;
                border-radius: 2rpx;
            }
        }
    }
}

.record-list {
    padding: 20rpx;

    .record-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fff;
        padding: 30rpx;
        border-radius: 12rpx;
        margin-bottom: 20rpx;

        .record-info {
            flex: 1;

            .record-title {
                display: block;
                font-size: 28rpx;
                color: #333;
                margin-bottom: 8rpx;
            }

            .record-time {
                font-size: 24rpx;
                color: #999;
            }
        }

        .record-points {
            font-size: 32rpx;
            font-weight: bold;

            &.earn {
                color: #52c41a;
            }

            &.spend {
                color: #ff4d4f;
            }
        }
    }

    .empty {
        text-align: center;
        padding: 100rpx 0;
    }
}
</style>
