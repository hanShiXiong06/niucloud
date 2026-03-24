<template>
    <view class="send-page">
        <!-- 功能关闭提示 -->
        <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
        
        <!-- 正常内容 -->
        <view v-if="isFeatureEnabled">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar" :style="{ height: navBarHeight + 'px' }">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">帮我送</text>
                <view class="placeholder"></view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 取货地址 -->
            <view class="form-card" @click="selectPickupAddress">
                <view class="card-icon">
                    <u-icon name="map" size="24" color="#52c41a"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!pickupAddress">从哪里取货?</text>
                    <view v-else>
                        <text class="addr-tag pickup">起</text>
                        <text class="value">{{ pickupAddress }}</text>
                    </view>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 送货地址 -->
            <view class="form-card" @click="selectReceiveAddress">
                <view class="card-icon">
                    <u-icon name="map-fill" size="24" color="#1890ff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">送到哪里?</text>
                    <view v-else>
                        <text class="addr-tag receive">终</text>
                        <text class="value">{{ receiveAddress }}</text>
                    </view>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 任务描述 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="bag" size="24" color="#ff9500"></u-icon>
                    </view>
                    <text class="label">任务描述</text>
                </view>
                <textarea 
                    class="content-input" 
                    v-model="goodsDesc" 
                    placeholder="请描述需要送的物品，如：文件、快递、外卖等"
                    :maxlength="500"
                ></textarea>
                
                <!-- 快捷标签 -->
                <view class="quick-tags">
                    <view 
                        class="tag" 
                        v-for="tag in quickTags" 
                        :key="tag"
                        :class="{ active: selectedTags.includes(tag) }"
                        @click="toggleTag(tag)"
                    >{{ tag }}</view>
                </view>
            </view>

            <!-- 物品图片 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="photo" size="24" color="#13c2c2"></u-icon>
                    </view>
                    <text class="label">物品图片</text>
                    <text class="hint">可选，方便接单员识别</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="6" />
            </view>

            <!-- 时间 -->
            <view class="form-card" @click="showTimePicker = true">
                <view class="card-icon">
                    <u-icon name="clock" size="24" color="#e91e63"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">期望送达时间</text>
                </view>
                <text class="time-value">{{ expectTime || '选择时间' }}</text>
            </view>

            <!-- 是否加急 -->
            <view class="form-card">
                <view class="card-icon">
                    <u-icon name="bell" size="24" color="#f44336"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">加急服务</text>
                    <text class="hint">加急费用+2元</text>
                </view>
                <u-switch v-model="isUrgent" activeColor="#13c2c2"></u-switch>
            </view>

            <!-- 跑腿费 -->
            <view class="form-card">
                <view class="card-icon">
                    <u-icon name="red-packet" size="24" color="#ff6b00"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">跑腿费</text>
                </view>
                <view class="fee-input-wrap">
                    <text class="fee-unit">¥</text>
                    <input class="fee-input" type="digit" v-model="tipFee" placeholder="0" />
                </view>
            </view>

            <!-- 备注 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="edit-pen" size="24" color="#666"></u-icon>
                    </view>
                    <text class="label">备注</text>
                    <text class="hint">可选</text>
                </view>
                <textarea 
                    class="content-input small" 
                    v-model="remark" 
                    placeholder="其他需要说明的事项"
                    :maxlength="200"
                ></textarea>
            </view>

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view class="price-info">
                <view class="fee-detail">
                    <text class="label">基础费用 ¥{{ baseFee }}</text>
                    <text class="label urgent" v-if="urgentFee > 0"> + 加急 ¥{{ urgentFee }}</text>
                </view>
                <text class="price">¥{{ totalFee }}</text>
            </view>
            <button class="submit-btn2" @click="submitOrder">立即下单</button>
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
    </view>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import xyUpload from '../../components/xy-upload.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_send')

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())

const pickupAddress = ref('')
const pickupAddressId = ref(0)
const receiveAddress = ref('')
const receiveAddressId = ref(0)
const goodsDesc = ref('')
const imageStr = ref('')
const expectTime = ref('')
const isUrgent = ref(false)
const tipFee = ref(3)
const remark = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const quickTags = ['文件', '快递', '外卖', '钥匙', '证件', '书本', '衣物', '其他']
const selectedTags = ref<string[]>([])

const baseFee = computed(() => tipFee.value)
const urgentFee = computed(() => isUrgent.value ? 2 : 0)
const totalFee = computed(() => baseFee.value + urgentFee.value)

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    uni.$on('onAddressSelect', onAddressSelect)
})

onUnmounted(() => {
    uni.$off('onAddressSelect', onAddressSelect)
})

const onAddressSelect = (address: any) => {
    if (address.type === 'pickup') {
        pickupAddress.value = address.address
        pickupAddressId.value = address.id
    } else {
        receiveAddress.value = address.address
        receiveAddressId.value = address.id
    }
}

const toggleTag = (tag: string) => {
    const index = selectedTags.value.indexOf(tag)
    if (index === -1) {
        selectedTags.value.push(tag)
        if (!goodsDesc.value.includes(tag)) {
            goodsDesc.value += (goodsDesc.value ? '、' : '') + tag
        }
    } else {
        selectedTags.value.splice(index, 1)
    }
}

const selectPickupAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=pickup'
    })
}

const selectReceiveAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=receive'
    })
}

const onTimeConfirm = (e: any) => {
    const date = new Date(e.value)
    expectTime.value = `${date.getMonth() + 1}-${date.getDate()} ${date.getHours()}:${String(date.getMinutes()).padStart(2, '0')}`
    showTimePicker.value = false
}

const increaseFee = () => {
    tipFee.value++
}

const decreaseFee = () => {
    if (tipFee.value > 1) {
        tipFee.value--
    }
}

const submitOrder = async () => {
    if (!pickupAddress.value) {
        uni.showToast({ title: '请选择取货地址', icon: 'none' })
        return
    }
    if (!receiveAddress.value) {
        uni.showToast({ title: '请选择送货地址', icon: 'none' })
        return
    }
    if (!goodsDesc.value) {
        uni.showToast({ title: '请描述物品信息', icon: 'none' })
        return
    }
    if (!expectTime.value) {
        uni.showToast({ title: '请选择期望送达时间', icon: 'none' })
        return
    }

    const ext = {
        expect_time: expectTime.value,
        quick_tags: selectedTags.value,
        images: imageStr.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'SEND',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            pickup_name: '',
            pickup_mobile: '',
            pickup_address: pickupAddress.value,
            receive_name: '',
            receive_mobile: '',
            receive_address: receiveAddress.value,
            goods_name: goodsDesc.value,
            task_desc: goodsDesc.value,
            images: imageStr.value,
            is_urgent: isUrgent.value ? 1 : 0,
            total_fee: totalFee.value,
            base_fee: baseFee.value,
            urgent_fee: urgentFee.value,
            remark: remark.value,
            ext: JSON.stringify(ext)
        })
        uni.hideLoading()
        if (res.code === 1) {
            currentOrderId.value = res.data.id
            payRef.value?.open('sd_xiaoyuan_order', res.data.id, '/addon/sd_xiaoyuan/pages/order/detail?id=' + res.data.id)
        } else {
            uni.showToast({ title: res.msg || '下单失败', icon: 'none' })
        }
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const goBack = () => {
    uni.navigateBack()
}

const onPaySuccess = () => {
    uni.showToast({ title: '支付成功', icon: 'success' })
    setTimeout(() => {
        uni.redirectTo({
            url: '/addon/sd_xiaoyuan/pages/order/list'
        })
    }, 1500)
}

const onPayFail = () => {
    uni.showToast({ title: '支付失败', icon: 'none' })
}
</script>

<style lang="scss" scoped>
.send-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #e6fffb, #b5f5ec);
    padding-bottom: 20rpx;
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

.form-content {
    height: calc(100vh - 200rpx);
    width: auto;
    padding: 20rpx;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    display: flex;
    align-items: center;
    width: auto;
    
    &.column {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .card-icon {
        width: 50rpx;
        height: 50rpx;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 20rpx;
    }
    
    .card-content {
        flex: 1;
        
        .placeholder {
            color: #999;
            font-size: 28rpx;
        }
        
        .value, .label {
            color: #333;
            font-size: 28rpx;
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
            margin-left: 16rpx;
        }
        
        .addr-tag {
            display: inline-block;
            width: 36rpx;
            height: 36rpx;
            border-radius: 50%;
            font-size: 20rpx;
            color: #fff;
            text-align: center;
            line-height: 36rpx;
            margin-right: 12rpx;
            
            &.pickup { background: #52c41a; }
            &.receive { background: #1890ff; }
        }
    }
    
    .card-header {
        display: flex;
        align-items: center;
        width: 100%;
        margin-bottom: 20rpx;
        
        .label {
            flex: 1;
            font-size: 28rpx;
            color: #333;
            font-weight: bold;
        }
        
        .hint {
            font-size: 24rpx;
            color: #999;
        }
    }
    
    .time-value {
        color: #13c2c2;
        font-size: 28rpx;
    }
}

.content-input {
    width: 100%;
    min-height: 150rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    padding: 20rpx;
    font-size: 28rpx;
    margin-bottom: 20rpx;
    
    &.small {
        min-height: 100rpx;
        margin-bottom: 0;
    }
}

.quick-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    
    .tag {
        padding: 12rpx 24rpx;
        background: #f5f5f5;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        border: 2rpx solid transparent;
        
        &.active {
            background: #e6fffb;
            color: #13c2c2;
            border-color: #13c2c2;
        }
    }
}

.weight-options {
    display: flex;
    gap: 12rpx;
    
    .weight-btn {
        padding: 10rpx 20rpx;
        background: #f5f5f5;
        border-radius: 20rpx;
        font-size: 24rpx;
        color: #666;
        
        &.active {
            background: #13c2c2;
            color: #fff;
        }
    }
}

.fee-input-wrap {
    display: flex;
    align-items: center;
    background: #f5f5f5;
    border-radius: 8rpx;
    padding: 8rpx 16rpx;
    
    .fee-unit {
        font-size: 28rpx;
        color: #ff6b00;
        font-weight: bold;
    }
    
    .fee-input {
        width: 120rpx;
        text-align: center;
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
        background: transparent;
    }
}

.fee-control {
    display: flex;
    align-items: center;
    
    .fee-btn {
        width: 50rpx;
        height: 50rpx;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28rpx;
        
        &.minus {
            background: #f5f5f5;
            color: #666;
        }
        
        &.plus {
            background: #13c2c2;
            color: #fff;
        }
    }
    
    .fee-value {
        width: 80rpx;
        text-align: center;
        font-size: 32rpx;
        color: #333;
        font-weight: bold;
    }
}

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;z-index: 22;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    background: #fff;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    
    .price-info {
        flex: 1;
        
        .label {
            font-size: 26rpx;
            color: #666;
        }
        
        .price {
            font-size: 40rpx;
            color: #ff6b00;
            font-weight: bold;
        }
    }
    
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
    
    .fee-detail {
        display: flex;
        align-items: center;
        margin-bottom: 4rpx;
        
        .urgent {
            color: #f97316;
        }
    }
}
</style>
