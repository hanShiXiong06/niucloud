<template>
    <view class="queue-create-page">
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">排队服务</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="main-title">帮排队</text>
                    <view class="sub-tag">
                        <text>· 食堂·快递点·服务窗口 ·</text>
                    </view>
                </view>
                <view class="header-right">
                    <u-icon name="list" size="80" color="#ff9800"></u-icon>
                </view>
            </view>
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
                <view class="input-wrap">
                    <u-input
                        v-model="formData.queue_location"
                        border="surround"
                        clearable
                        maxlength="50"
                        placeholder="请输入具体的排队位置"
                        fontSize="28rpx"
                    ></u-input>
                </view>
            </view>

            <!-- 排队事由 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="file-text-fill" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">排队事由</text>
                </view>
                <view class="input-wrap">
                    <u-input
                        v-model="formData.queue_purpose"
                        border="surround"
                        clearable
                        maxlength="50"
                        placeholder="请输入排队事由"
                        fontSize="28rpx"
                    ></u-input>
                </view>
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
                <view class="time-input-group" @click="showTimePicker = true">
                    <view class="time-display">
                        <text class="time-txt" :class="{ isPh: !formData.queue_time }">{{ formData.queue_time || '请选择期望排队时间' }}</text>
                    </view>
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
                    <u-icon name="red-packet" size="20" color="#ff5722"></u-icon>
                    <text class="section-title">服务价格</text>
                </view>
                <view class="price-input-group">
                    <text class="currency">¥</text>
                    <view class="input-wrap price-wrap">
                        <u-input
                            v-model="formData.total_fee"
                            type="digit"
                            border="surround"
                            placeholder="0.00"
                            fontSize="30rpx"
                        ></u-input>
                    </view>
                    <text class="unit">元</text>
                </view>
            </view>

            <xy-order-yinsi-field v-model="yinsiText" />
            
            <!-- 底部占位 -->
            <view style="height: 200rpx;"></view>
        </view>

        <!-- 底部提交按钮 -->
        <view class="submit-section">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder" :disabled="!canSubmit">
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
        
        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'

const statusBarHeight = ref(0)

const payRef = ref<any>(null)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())
const currentOrderId = ref(0)
const yinsiText = ref('')

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

onMounted(() => {
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
})

onLoad(() => {
    tryBindFenxiao()
    // 设置默认时间为当前时间后1小时
    const now = new Date()
    now.setHours(now.getHours() + 1)
    selectedTime.value = now.getTime()
    formData.value.queue_time = formatDateTime(now)
})

const goBack = () => {
    uni.navigateBack()
}

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
        school_id: uni.getStorageSync('current_school')?.id || 0,
        campus: uni.getStorageSync('current_school')?.campus || '',
        goods_name: '代排队服务',
        task_desc: formData.value.queue_purpose,
        total_fee: parseFloat(formData.value.total_fee),
        remark: formData.value.remark,
        yinsi_text: yinsiText.value,
        ext: JSON.stringify(ext)
    }

    const res: any = await createOrder(orderData)
    uni.hideLoading()

    if (res.code === 1) {
        currentOrderId.value = res.data.id
        payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
    } else {
        uni.showToast({ title: res.msg || '操作失败', icon: 'none' })
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
    width: 100%;
    box-sizing: border-box;
    background: #f5f5f5;
    padding-bottom: calc(140rpx + env(safe-area-inset-bottom));
}

.header-bg {
    background: linear-gradient(135deg, #fff3e0, #ffe0b2);
    padding-bottom: 40rpx;
}

.navbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    
    .back-btn, .placeholder {
        width: 60rpx;
    }
    
    .title {
        font-size: 32rpx;
        font-weight: bold;
        color: #333;
    }
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 30rpx;
    
    .header-left {
        flex: 1;
        min-width: 0;
        
        .main-title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 10rpx;
        }
        
        .sub-tag {
            display: inline-block;
            background: linear-gradient(135deg, #ff9800, #ffb74d);
            padding: 8rpx 20rpx;
            border-radius: 30rpx;
            
            text {
                font-size: 22rpx;
                color: #fff;
            }
        }
    }
    
    .header-right {
        flex-shrink: 0;
        margin-left: 20rpx;
    }
}

.form-container {
    width: 100%;
    box-sizing: border-box;
    padding: 20rpx;
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.06);
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    
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
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20rpx;
    width: 100%;
    box-sizing: border-box;
    
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

.input-wrap {
    width: 100%;
    box-sizing: border-box;
    :deep(.u-input) {
        min-height: 88rpx;
    }
    :deep(.u-border) {
        border-color: #f0f0f0 !important;
    }
}

.form-textarea {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    padding: 24rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 16rpx;
    font-size: 28rpx;
    color: #333;
    background: #fafafa;
    min-height: 120rpx;
    resize: none;
    &:focus {
        border-color: #ff5722;
        background: #fff;
    }
}

.time-input-group {
    display: flex;
    align-items: center;
    gap: 16rpx;
    width: 100%;
    box-sizing: border-box;
}

.time-display {
    flex: 1;
    min-width: 0;
    min-height: 88rpx;
    padding: 0 24rpx;
    border: 2rpx solid #f0f0f0;
    border-radius: 16rpx;
    background: #fafafa;
    display: flex;
    align-items: center;
    box-sizing: border-box;
    .time-txt {
        font-size: 28rpx;
        color: #333;
        &.isPh {
            color: #c0c0c0;
        }
    }
}

.price-input-group {
    display: flex;
    align-items: center;
    gap: 12rpx;
    width: 100%;
    box-sizing: border-box;
    min-width: 0;
    
    .currency {
        font-size: 32rpx;
        color: #ff5722;
        font-weight: bold;
    }
    
    .price-wrap {
        flex: 1;
        min-width: 0;
        max-width: 100%;
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
    width: 100%;
    box-sizing: border-box;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0, 0, 0, 0.05);
    display: flex;
    align-items: center;
    z-index: 22;
    
    .submit-btn2 {
        background: linear-gradient(to top, #aaf69b, #d1ff7c);
        color: #000;
        font-size: 28rpx;
        padding: 16rpx 60rpx;
        border-radius: 40rpx;
        border: none;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 80rpx;
        
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
