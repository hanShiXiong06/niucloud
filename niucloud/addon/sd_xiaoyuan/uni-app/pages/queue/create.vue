<template>
    <view class="queue-create-page">
        <u-navbar 
            title="代排队" 
            :safeAreaInsetTop="true"
            :placeholder="true"
            bgColor="#ff5722"
        ></u-navbar>

        <!-- 顶部装饰 -->
        <view class="header-decoration">
            <view class="decoration-text">
                <text class="sub">· 食堂·快递点·服务窗口 · · ·</text>
            </view>
            <image class="decoration-img" src="/static/images/queue-icon.png" mode="aspectFit"></image>
        </view>

        <view class="form-container">
            <!-- 排队地点 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="map-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">排队地点</text>
                </view>
                <view class="location-list">
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'CANTEEN' }"
                        @click="formData.location_type = 'CANTEEN'"
                    >食堂</view>
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'EXPRESS' }"
                        @click="formData.location_type = 'EXPRESS'"
                    >快递点</view>
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'SERVICE' }"
                        @click="formData.location_type = 'SERVICE'"
                    >服务窗口</view>
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'OTHER' }"
                        @click="formData.location_type = 'OTHER'"
                    >其他</view>
                </view>
            </view>

            <!-- 具体位置 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="bookmark-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">具体位置</text>
                </view>
                <input 
                    class="form-input" 
                    v-model="formData.queue_location" 
                    placeholder="请输入具体的排队位置"
                    maxlength="50"
                />
            </view>

            <!-- 排队事由 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="file-text-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">排队事由</text>
                </view>
                <input 
                    class="form-input" 
                    v-model="formData.queue_purpose" 
                    placeholder="请输入排队事由"
                    maxlength="50"
                />
            </view>

            <!-- 预计时长 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="clock-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">预计时长</text>
                </view>
                <view class="duration-list">
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.estimated_duration === '30' }"
                        @click="formData.estimated_duration = '30'"
                    >30分钟</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.estimated_duration === '60' }"
                        @click="formData.estimated_duration = '60'"
                    >1小时</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.estimated_duration === '120' }"
                        @click="formData.estimated_duration = '120'"
                    >2小时</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.estimated_duration === '180' }"
                        @click="formData.estimated_duration = '180'"
                    >3小时</view>
                </view>
            </view>

            <!-- 排队时间 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="calendar-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">期望排队时间</text>
                </view>
                <view class="time-input-group">
                    <input 
                        class="form-input" 
                        v-model="formData.queue_time" 
                        placeholder="请选择期望排队时间"
                        @click="showTimePicker = true"
                        readonly
                    />
                    <u-icon name="calendar" size="20" color="#999"></u-icon>
                </view>
            </view>

            <!-- 备注信息 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="chat-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">备注信息</text>
                </view>
                <textarea 
                    class="form-textarea" 
                    v-model="formData.remark" 
                    placeholder="请输入其他需要说明的信息"
                    maxlength="200"
                    :auto-height="true"
                />
            </view>

            <!-- 价格设置 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="currency-circle-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">服务价格</text>
                </view>
                <view class="price-input-group">
                    <text class="currency">¥</text>
                    <input 
                        class="price-input" 
                        v-model="formData.total_fee" 
                        type="digit" 
                        placeholder="0.00"
                    />
                    <text class="unit">元</text>
                </view>
            </view>
        </view>

        <!-- 底部提交按钮 -->
        <view class="submit-section">
            <button class="submit-btn" @click="submitOrder" :disabled="!canSubmit">
                立即发布
            </button>
        </view>

        <!-- 时间选择器 -->
        <u-datetime-picker
            :show="showTimePicker"
            v-model="selectedTime"
            mode="datetime"
            @confirm="onTimeConfirm"
            @cancel="showTimePicker = false"
        ></u-datetime-picker>
    </view>
</template>

<script setup lang="ts">
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'

const payRef = ref<any>(null)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())
const currentOrderId = ref(0)

const formData = ref({
    location_type: 'CANTEEN',
    queue_location: '',
    queue_purpose: '',
    estimated_duration: '60',
    queue_time: '',
    remark: '',
    total_fee: '',
    task_type: 'QUEUE'
})

const canSubmit = computed(() => {
    return formData.value.queue_location && 
           formData.value.queue_purpose && 
           formData.value.estimated_duration && 
           formData.value.queue_time && 
           parseFloat(formData.value.total_fee) > 0
})

onLoad(() => {
    tryBindFenxiao()
    // 设置默认时间为当前时间后1小时
    const now = new Date()
    now.setHours(now.getHours() + 1)
    selectedTime.value = now.getTime()
    formData.value.queue_time = formatDateTime(now)
})

const formatDateTime = (date: Date) => {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hours = String(date.getHours()).padStart(2, '0')
    const minutes = String(date.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day} ${hours}:${minutes}`
}

const onTimeConfirm = (e: any) => {
    selectedTime.value = e.value
    formData.value.queue_time = formatDateTime(new Date(e.value))
    showTimePicker.value = false
}

const submitOrder = async () => {
    if (!canSubmit.value) return

    try {
        uni.showLoading({ title: '提交中...' })
        
        const ext = {
            location_type: formData.value.location_type,
            queue_location: formData.value.queue_location,
            queue_purpose: formData.value.queue_purpose,
            estimated_duration: formData.value.estimated_duration,
            queue_time: formData.value.queue_time
        }

        const orderData = {
            task_type: 'QUEUE',
            goods_name: '代排队服务',
            task_desc: formData.value.queue_purpose,
            total_fee: parseFloat(formData.value.total_fee),
            remark: formData.value.remark,
            ext: JSON.stringify(ext)
        }
        
        const res: any = await createOrder(orderData)
        uni.hideLoading()

        if (res.code === 1) {
            currentOrderId.value = res.data.id
            // 使用框架支付组件
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.msg || '网络错误', icon: 'none' })
    }
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.queue-create-page {
    min-height: 100vh;
    background: linear-gradient(180deg, #fff5f5 0%, #ffffff 100%);
}

.header-decoration {
    position: relative;
    padding: 40rpx 30rpx 30rpx;
    text-align: center;
    
    .decoration-text {
        .sub {
            font-size: 24rpx;
            color: #ff5722;
            opacity: 0.8;
        }
    }
    
    .decoration-img {
        width: 120rpx;
        height: 120rpx;
        margin-top: 20rpx;
    }
}

.form-container {
    padding: 0 30rpx 150rpx;
}

.form-section {
    background: #fff;
    border-radius: 20rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 4rpx 20rpx rgba(255, 87, 34, 0.1);
    
    .section-header {
        display: flex;
        align-items: center;
        gap: 12rpx;
        margin-bottom: 24rpx;
        
        .section-title {
            font-size: 32rpx;
            font-weight: bold;
            color: #333;
        }
    }
}

.location-list, .duration-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20rpx;
    
    .location-item, .duration-item {
        padding: 20rpx;
        text-align: center;
        border: 2rpx solid #f0f0f0;
        border-radius: 16rpx;
        font-size: 28rpx;
        color: #666;
        transition: all 0.3s;
        
        &.active {
            border-color: #ff5722;
            background: #fff5f5;
            color: #ff5722;
            font-weight: bold;
        }
    }
}

.form-input, .form-textarea {
    width: 100%;
    padding: 24rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 16rpx;
    font-size: 28rpx;
    color: #333;
    background: #fafafa;
    transition: all 0.3s;
    
    &:focus {
        border-color: #ff5722;
        background: #fff;
    }
}

.form-textarea {
    min-height: 120rpx;
    resize: none;
}

.time-input-group {
    position: relative;
    
    .form-input {
        padding-right: 60rpx;
    }
    
    .u-icon {
        position: absolute;
        right: 24rpx;
        top: 50%;
        transform: translateY(-50%);
    }
}

.price-input-group {
    display: flex;
    align-items: center;
    gap: 12rpx;
    
    .currency {
        font-size: 32rpx;
        color: #ff5722;
        font-weight: bold;
    }
    
    .price-input {
        flex: 1;
        padding: 24rpx;
        border: 2rpx solid #f0f0f0;
        border-radius: 16rpx;
        font-size: 32rpx;
        color: #333;
        background: #fafafa;
        transition: all 0.3s;
        
        &:focus {
            border-color: #ff5722;
            background: #fff;
        }
    }
    
    .unit {
        font-size: 28rpx;
        color: #666;
    }
}

.submit-section {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.1);
    
    .submit-btn {
        width: 100%;
        height: 100rpx;
        background: linear-gradient(135deg, #ff5722, #ff8a65);
        color: #fff;
        font-size: 32rpx;
        font-weight: bold;
        border-radius: 50rpx;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
        
        &:active {
            transform: scale(0.98);
        }
        
        &:disabled {
            background: #ccc;
            color: #999;
        }
    }
}
</style>
