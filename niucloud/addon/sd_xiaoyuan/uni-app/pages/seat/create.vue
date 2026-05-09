<template>
    <view class="seat-create-page">
        <u-navbar 
            title="代占座位" 
            :safeAreaInsetTop="true"
            :placeholder="true"
            bgColor="#c0fe95"
        ></u-navbar>

        <!-- 顶部装饰 -->
        <view class="header-decoration">
            <view class="decoration-text">
                <text class="sub">· 图书馆·自习室·教室 · · ·</text>
            </view>
            <image class="decoration-img" src="/static/images/seat-icon.png" mode="aspectFit"></image>
        </view>

        <view class="form-container">
            <!-- 占座地点 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="map-fill" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">占座地点</text>
                </view>
                <view class="location-list">
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'LIBRARY' }"
                        @click="formData.location_type = 'LIBRARY'"
                    >图书馆</view>
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'STUDY_ROOM' }"
                        @click="formData.location_type = 'STUDY_ROOM'"
                    >自习室</view>
                    <view 
                        class="location-item" 
                        :class="{ active: formData.location_type === 'CLASSROOM' }"
                        @click="formData.location_type = 'CLASSROOM'"
                    >教室</view>
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
                    <u-icon name="edit-pen" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">具体位置</text>
                </view>
                <input 
                    v-model="formData.location_detail" 
                    placeholder="如：图书馆3楼A区靠窗位置"
                    class="text-input"
                />
            </view>

            <!-- 占座数量 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="account-fill" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">占座数量</text>
                </view>
                <view class="count-selector">
                    <view class="count-btn" @click="decreaseCount">-</view>
                    <text class="count-value">{{ formData.seat_count }}</text>
                    <view class="count-btn plus" @click="increaseCount">+</view>
                </view>
            </view>

            <!-- 占座时间 -->
            <view class="form-section" @click="showTimePicker = true">
                <view class="section-header">
                    <u-icon name="clock-fill" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">占座时间</text>
                </view>
                <view class="time-display">
                    <text v-if="formData.start_time">{{ formData.start_time }}</text>
                    <text v-else class="placeholder">选择开始占座时间</text>
                    <u-icon name="arrow-right" size="16" color="#999"></u-icon>
                </view>
            </view>

            <!-- 占座时长 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="hourglass" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">占座时长</text>
                </view>
                <view class="duration-list">
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.duration === 30 }"
                        @click="formData.duration = 30"
                    >30分钟</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.duration === 60 }"
                        @click="formData.duration = 60"
                    >1小时</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.duration === 120 }"
                        @click="formData.duration = 120"
                    >2小时</view>
                    <view 
                        class="duration-item" 
                        :class="{ active: formData.duration === 180 }"
                        @click="formData.duration = 180"
                    >3小时</view>
                </view>
            </view>

            <!-- 图片上传 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="photo" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">位置图片</text>
                    <text class="optional">非必填</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="3" />
            </view>

            <!-- 备注说明 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="chat" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">备注说明</text>
                </view>
                <textarea 
                    v-model="formData.remark" 
                    placeholder="其他需要说明的事项，如：需要带书本占座等"
                    :maxlength="200"
                    class="remark-input"
                ></textarea>
            </view>

            <!-- 赏金 -->
            <view class="form-section reward-section">
                <view class="section-header">
                    <u-icon name="red-packet" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">赏金</text>
                </view>
                <view class="reward-input">
                    <text class="symbol">¥</text>
                    <input 
                        type="digit" 
                        v-model="formData.reward" 
                        placeholder="输入金额"
                        class="amount-input"
                    />
                </view>
            </view>

            <!-- 底部占位 -->
            <view style="height: 200rpx;"></view>
        </view>

        <!-- 底部占位符 -->
        <view style="height: 200rpx;"></view>

        <!-- 底部提交 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder">发布占座</button>
        </view>

        <!-- 时间选择器 -->
        <u-datetime-picker
            :show="showTimePicker"
            v-model="pickerTime"
            mode="datetime"
            @confirm="onTimeConfirm"
            @cancel="showTimePicker = false"
        ></u-datetime-picker>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { createSeatOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import xyUpload from '../../components/xy-upload.vue'
import pay from '@/components/pay/pay.vue'

const imageStr = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)
const showTimePicker = ref(false)
const pickerTime = ref(Date.now())

onMounted(() => {
    tryBindFenxiao()
})

const formData = ref({
    location_type: 'LIBRARY',
    location_detail: '',
    seat_count: 1,
    start_time: '',
    duration: 60,
    remark: '',
    reward: ''
})

const increaseCount = () => {
    if (formData.value.seat_count < 5) {
        formData.value.seat_count++
    }
}

const decreaseCount = () => {
    if (formData.value.seat_count > 1) {
        formData.value.seat_count--
    }
}

const onTimeConfirm = (e: any) => {
    const date = new Date(e.value)
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    const hour = String(date.getHours()).padStart(2, '0')
    const minute = String(date.getMinutes()).padStart(2, '0')
    formData.value.start_time = `${year}-${month}-${day} ${hour}:${minute}`
    showTimePicker.value = false
}

const submitOrder = async () => {
    if (!formData.value.location_detail) {
        uni.showToast({ title: '请填写具体位置', icon: 'none' })
        return
    }
    if (!formData.value.start_time) {
        uni.showToast({ title: '请选择占座时间', icon: 'none' })
        return
    }
    if (!formData.value.reward || parseFloat(formData.value.reward) <= 0) {
        uni.showToast({ title: '请输入赏金金额', icon: 'none' })
        return
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createSeatOrder({
            location_type: formData.value.location_type,
            location_detail: formData.value.location_detail,
            seat_count: formData.value.seat_count,
            start_time: formData.value.start_time,
            duration: formData.value.duration,
            images: imageStr.value,
            remark: formData.value.remark,
            reward: parseFloat(formData.value.reward),
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || ''
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '发布失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.message || '发布失败', icon: 'none' })
    }
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: `/addon/sd_xiaoyuan/pages/order/detail?id=${currentOrderId.value}`
        })
    }, 1500)
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.seat-create-page {
    min-height: 100vh;
    background: linear-gradient(to bottom, #c0fe95 0%, #f5f5f5 30%);
    padding-bottom: 120rpx;
}

.header-decoration {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20rpx 30rpx;
    
    .decoration-text {
        .sub {
            font-size: 26rpx;
            color: #666;
        }
    }
    
    .decoration-img {
        width: 120rpx;
        height: 120rpx;
    }
}

.form-container {
    padding: 0 30rpx;
}

.form-section {
    background: #fff;
    border-radius: 16rpx;
    padding: 24rpx;
    margin-bottom: 20rpx;
    width: auto;
    
    .section-header {
        display: flex;
        align-items: center;
        margin-bottom: 20rpx;
        
        .section-title {
            font-size: 28rpx;
            color: #333;
            margin-left: 12rpx;
            font-weight: 500;
        }
        
        .optional {
            margin-left: auto;
            font-size: 24rpx;
            color: #1890ff;
        }
    }
}

.location-list, .duration-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
    
    .location-item, .duration-item {
        padding: 16rpx 32rpx;
        background: #f5f5f5;
        border-radius: 8rpx;
        font-size: 26rpx;
        color: #666;
        
        &.active {
            background: #1890ff;
            color: #fff;
        }
    }
}

.text-input {
    width: 100%;
    height: 80rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 20rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.count-selector {
    display: flex;
    align-items: center;
    gap: 30rpx;
    
    .count-btn {
        width: 60rpx;
        height: 60rpx;
        background: #f5f5f5;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 36rpx;
        color: #666;
        
        &.plus {
            background: #1890ff;
            color: #fff;
        }
    }
    
    .count-value {
        font-size: 36rpx;
        font-weight: bold;
        color: #333;
        min-width: 60rpx;
        text-align: center;
    }
}

.time-display {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    
    text {
        font-size: 28rpx;
        color: #333;
    }
    
    .placeholder {
        color: #999;
    }
}

.remark-input {
    width: 94%;
    height: 200rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.reward-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    .section-header {
        margin-bottom: 0;
    }
    
    .reward-input {
        display: flex;
        align-items: center;
        background: #f8f8f8;
        border-radius: 12rpx;
        padding: 0 20rpx;
        
        .symbol {
            font-size: 32rpx;
            color: #ff6b00;
            font-weight: bold;
        }
        
        .amount-input {
            width: 150rpx;
            height: 70rpx;
            font-size: 32rpx;
            color: #ff6b00;
            font-weight: bold;
            text-align: center;
        }
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    padding: 20rpx 30rpx;
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    
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
    }
}
</style>
