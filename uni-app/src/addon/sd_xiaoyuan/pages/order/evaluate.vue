<template>
    <view class="evaluate-page">
        <!-- 评分 -->
        <view class="score-section">
            <view class="section-title">评分</view>
            <view class="score-item">
                <text class="label">综合评分</text>
                <u-rate v-model="formData.score" :count="5" size="28" active-color="#ff9500"></u-rate>
            </view>
            <view class="score-item">
                <text class="label">服务态度</text>
                <u-rate v-model="formData.service_score" :count="5" size="28" active-color="#ff9500"></u-rate>
            </view>
            <view class="score-item">
                <text class="label">配送速度</text>
                <u-rate v-model="formData.speed_score" :count="5" size="28" active-color="#ff9500"></u-rate>
            </view>
        </view>

        <!-- 评价内容 -->
        <view class="content-section">
            <view class="section-title">评价内容</view>
            <textarea 
                v-model="formData.content" 
                placeholder="分享您的配送体验，帮助接单员改进服务"
                maxlength="200"
            ></textarea>
            <text class="count">{{ formData.content.length }}/200</text>
        </view>

        <!-- 上传图片 -->
        <view class="image-section">
            <view class="section-title">上传图片(选填)</view>
            <xy-upload v-model="formData.images" :maxCount="3" />
        </view>

        <!-- 匿名评价 -->
        <view class="anonymous-section" @click="formData.is_anonymous = formData.is_anonymous ? 0 : 1">
            <text>匿名评价</text>
            <switch :checked="formData.is_anonymous === 1" color="#c0fe95" />
        </view>

        <!-- 提交按钮 -->
        <button class="submit-btn" @click="submitEvaluate">提交评价</button>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getOrderDetail, addEvaluate } from '../../api/xiaoyuan'
import xyUpload from '../../components/xy-upload.vue'

const order = ref<any>({})
const formData = ref({
    order_id: 0,
    score: 5,
    service_score: 5,
    speed_score: 5,
    content: '',
    images: [] as string[],
    is_anonymous: 0
})

onLoad((options: any) => {
    if (options?.id) {
        formData.value.order_id = parseInt(options.id)
        loadOrder(parseInt(options.id))
    }
})

const loadOrder = async (orderId: number) => {
    try {
        const res: any = await getOrderDetail(orderId)
        if (res.code === 1) {
            order.value = res.data || {}
        }
    } catch (e: any) {
        // Error already handled by request interceptor
        console.error(e)
        // Navigate back after a delay to allow user to see the error message
        setTimeout(() => {
            uni.navigateBack()
        }, 1500)
    }
}

const submitEvaluate = async () => {
    try {
        uni.showLoading({ title: '提交中...' })
        const res: any = await addEvaluate(formData.value)
        uni.hideLoading()
        
        if (res.code === 1) {
            uni.showToast({ title: '评价成功', icon: 'success' })
            setTimeout(() => {
                uni.navigateBack()
            }, 1500)
        } else {
            uni.showModal({
                title: '提示',
                content: res.msg || '评价失败',
                showCancel: false,
                success: () => {
                    if (res.msg && res.msg.includes('已评价')) {
                        uni.navigateBack()
                    }
                }
            })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}
</script>

<style lang="scss" scoped>
.evaluate-page {
    min-height: 100vh;
    background: #f5f5f5;
    padding: 20rpx;
    padding-bottom: 150rpx;
}

.section-title {
    font-size: 28rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 20rpx;
}

.score-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.score-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 0;
    
    .label {
        font-size: 28rpx;
        color: #333;
    }
}

.content-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    position: relative;
    
    textarea {
        width: 100%;
        height: 200rpx;
        font-size: 28rpx;
        line-height: 1.6;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 20rpx;
        box-sizing: border-box;
    }
    
    .count {
        text-align: right;
        display: block;
        font-size: 24rpx;
        color: #999;
        margin-top: 8rpx;
    }
}

.image-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
}

.anonymous-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20rpx;
    
    text {
        font-size: 28rpx;
        color: #333;
    }
    
    switch {
        transform: scale(0.8);
    }
}

.submit-btn {
    position: fixed;
    bottom: 30rpx;
    left: 30rpx;
    right: 30rpx;
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(to top, #aaf69b, #d1ff7c);
    color: #000000;
    border: none;
    border-radius: 44rpx;
    font-size: 28rpx;
    font-weight: bold;
    box-shadow: 0 4rpx 12rpx rgba(170, 246, 155, 0.5);

    &::after {
        border: none;
    }
}
</style>
