<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="clean-create-page" v-if="isFeatureEnabled">
        <u-navbar 
            title="代清洁" 
            :safeAreaInsetTop="true"
            :placeholder="true"
            bgColor="#c0fe95"
        ></u-navbar>

        <view class="form-container">
            <!-- 清洁类型 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="grid" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">清洁类型</text>
                </view>
                <view class="type-list">
                    <view 
                        class="type-item" 
                        :class="{ active: formData.clean_type === 'DORM' }"
                        @click="formData.clean_type = 'DORM'"
                    >宿舍清洁</view>
                    <view 
                        class="type-item" 
                        :class="{ active: formData.clean_type === 'OFFICE' }"
                        @click="formData.clean_type = 'OFFICE'"
                    >办公室清洁</view>
                    <view 
                        class="type-item" 
                        :class="{ active: formData.clean_type === 'OTHER' }"
                        @click="formData.clean_type = 'OTHER'"
                    >其他</view>
                </view>
            </view>

            <!-- 清洁面积 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="home" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">清洁面积(平方米)</text>
                </view>
                <input 
                    type="digit" 
                    v-model="formData.area" 
                    placeholder="请输入清洁面积"
                    class="form-input"
                />
            </view>

            <!-- 地址选择 -->
            <view class="form-section address-section" @click="selectAddress">
                <view class="section-header">
                    <u-icon name="map" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">清洁地址</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#999"></u-icon>
            </view>
            <view class="address-info" v-if="selectedAddress">
                <text>{{ selectedAddress.name }} {{ selectedAddress.mobile }}</text>
                <text class="address-detail">{{ selectedAddress.address }}</text>
            </view>

            <!-- 预约时间 -->
            <view class="form-section" @click="showTimePicker = true">
                <view class="section-header">
                    <u-icon name="clock" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">预约时间</text>
                </view>
                <view class="time-value">
                    <text v-if="formData.appointment_time">{{ formData.appointment_time }}</text>
                    <text class="placeholder" v-else>请选择时间</text>
                    <u-icon name="arrow-right" size="16" color="#999"></u-icon>
                </view>
            </view>

            <!-- 任务描述 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="edit-pen" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">任务描述</text>
                </view>
                <textarea 
                    v-model="formData.task_desc" 
                    placeholder="请描述清洁需求"
                    :maxlength="200"
                    class="remark-input"
                ></textarea>
            </view>

            <!-- 备注 -->
            <view class="form-section">
                <view class="section-header">
                    <u-icon name="edit-pen" size="20" color="#999"></u-icon>
                    <text class="section-title">备注</text>
                    <text class="hint">可选</text>
                </view>
                <textarea 
                    v-model="formData.remark" 
                    placeholder="其他需要说明的事项"
                    :maxlength="200"
                    class="remark-input small"
                ></textarea>
            </view>

            <!-- 费用 -->
            <view class="form-section reward-section">
                <view class="section-header">
                    <u-icon name="red-packet" size="20" color="#1890ff"></u-icon>
                    <text class="section-title">服务费用</text>
                </view>
                <view class="reward-input">
                    <input 
                        type="digit" 
                        v-model="formData.fee" 
                        placeholder="输入金额"
                        class="amount-input"
                    />
                </view>
            </view>
        </view>

        <!-- 底部提交 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder">发布订单</button>
        </view>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />

        <!-- 时间选择器 -->
        <u-datetime-picker 
            :show="showTimePicker" 
            v-model="pickerTime"
            mode="datetime"
            @confirm="onTimeConfirm"
            @cancel="showTimePicker = false"
        ></u-datetime-picker>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_clean')

const formData = ref({
    clean_type: 'DORM',
    area: '',
    address_id: 0,
    appointment_time: '',
    task_desc: '',
    remark: '',
    fee: ''
})

const selectedAddress = ref<any>(null)
const showTimePicker = ref(false)
const pickerTime = ref(Date.now())
const payRef = ref<any>(null)
const currentOrderId = ref(0)

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    uni.$on('onAddressSelect', (address: any) => {
        selectedAddress.value = address
        formData.value.address_id = address.id
    })
})

const selectAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select'
    })
}

const onTimeConfirm = (e: any) => {
    const date = new Date(e.value)
    formData.value.appointment_time = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
    showTimePicker.value = false
}

const submitOrder = async () => {
    if (!formData.value.address_id) {
        uni.showToast({ title: '请选择清洁地址', icon: 'none' })
        return
    }
    if (!formData.value.fee || parseFloat(formData.value.fee) <= 0) {
        uni.showToast({ title: '请输入服务费用', icon: 'none' })
        return
    }

    const ext = {
        clean_type: formData.value.clean_type,
        area: formData.value.area,
        appointment_time: formData.value.appointment_time,
        task_desc: formData.value.task_desc
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'CLEAN',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            clean_type: formData.value.clean_type,
            area: formData.value.area,
            address_id: formData.value.address_id,
            receive_address: selectedAddress.value?.address || '',
            receive_name: selectedAddress.value?.name || '',
            receive_mobile: selectedAddress.value?.mobile || '',
            appointment_time: formData.value.appointment_time,
            goods_name: '清洁服务',
            task_desc: formData.value.task_desc,
            total_fee: parseFloat(formData.value.fee),
            remark: formData.value.remark,
            ext: JSON.stringify(ext)
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            // 使用框架支付组件
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
        }
    } catch (e: any) {
        uni.hideLoading()
        uni.showToast({ title: e.message || '下单失败', icon: 'none' })
    }
}

// 支付成功回调
const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: '/addon/sd_xiaoyuan/pages/order/list'
        })
    }, 1500)
}

// 支付失败回调
const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.clean-create-page {
    min-height: 100vh;
    background: linear-gradient(to bottom, #c0fe95 0%, #f5f5f5 30%);
    padding-bottom: 120rpx;
}

.form-container {
    padding: 20rpx 30rpx;
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
    }
}

.type-list {
    display: flex;
    flex-wrap: wrap;
    gap: 20rpx;
    
    .type-item {
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

.form-input {
    width: 100%;
    height: 72rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 0 24rpx;
    font-size: 28rpx;
    box-sizing: border-box;
}

.address-section {
    display: flex;
    align-items: center;
    justify-content: space-between;
    
    .section-header {
        margin-bottom: 0;
    }
}

.address-info {
    background: #fff;
    border-radius: 16rpx;
    padding: 20rpx 24rpx;
    margin-top: -10rpx;
    margin-bottom: 20rpx;
    
    text {
        display: block;
        font-size: 28rpx;
        color: #333;
    }
    
    .address-detail {
        font-size: 24rpx;
        color: #999;
        margin-top: 8rpx;
    }
}

.time-value {
    display: flex;
    align-items: center;
    
    text {
        font-size: 28rpx;
        color: #333;
        margin-right: 10rpx;
        
        &.placeholder {
            color: #999;
        }
    }
}

.remark-input {
    width: 100%;
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
        .amount-input {
            width: 200rpx;
            text-align: right;
            font-size: 28rpx;
            color: #333;
        }
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    background: #fff;z-index: 22;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    display: flex;
    align-items: center;
    justify-content: flex-end;
    
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
