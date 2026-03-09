<template>
    <view class="notice-page">
        <u-navbar 
            title="公告" 
            :safeAreaInsetTop="true"
            :placeholder="true"
        ></u-navbar>

        <scroll-view scroll-y class="notice-list">
            <view class="notice-item" v-for="item in noticeList" :key="item.id">
                <view class="notice-header">
                    <text class="notice-title">{{ item.title }}</text>
                    <text class="notice-time">{{ item.create_time }}</text>
                </view>
                <view class="notice-content">{{ item.content }}</view>
            </view>

            <view class="empty" v-if="noticeList.length === 0 && !loading">
                <u-icon name="volume" size="80" color="#ccc"></u-icon>
                <text>暂无公告</text>
            </view>
        </scroll-view>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'

const loading = ref(false)
const noticeList = ref<any[]>([])

onMounted(() => {
    loadNoticeList()
})

const loadNoticeList = async () => {
    loading.value = true
    try {
        // TODO: 调用公告列表接口
        noticeList.value = [
            {
                id: 1,
                title: '欢迎使用校园帮',
                content: '校园帮是一款专为大学生打造的校园服务平台，提供代取快递、帮我买、代打印等多种便捷服务。',
                create_time: '2024-01-01'
            }
        ]
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}
</script>

<style lang="scss" scoped>
.notice-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.notice-list {
    padding: 20rpx 30rpx;
}

.notice-item {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;

    .notice-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16rpx;

        .notice-title {
            font-size: 30rpx;
            font-weight: bold;
            color: #333;
        }

        .notice-time {
            font-size: 24rpx;
            color: #999;
        }
    }

    .notice-content {
        font-size: 28rpx;
        color: #666;
        line-height: 1.6;
    }
}

.empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 100rpx 0;

    text {
        margin-top: 20rpx;
        font-size: 28rpx;
        color: #999;
    }
}
</style>
