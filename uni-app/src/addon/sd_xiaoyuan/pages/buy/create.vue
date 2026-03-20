<template>
    <view class="buy-page">
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
                <text class="title">帮我买</text>
                <view class="placeholder"></view>
            </view>
        </view>

        <!-- 占位，防止内容被固定导航遮挡 -->
        <view :style="{ height: (statusBarHeight + navBarHeight + 10) + 'px' }"></view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 送货地址 -->
            <view class="form-card" @click="selectAddress">
                <view class="card-icon">
                    <u-icon name="gift" size="24" color="#1890ff"></u-icon>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">买完东西送到哪里给您呢?</text>
                    <text class="value" v-else>{{ receiveAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 物品名称 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="shopping-cart" size="24" color="#ff9500"></u-icon>
                    </view>
                    <text class="label">物品名称</text>
                </view>
                <textarea 
                    class="content-input" 
                    v-model="goodsName" 
                    placeholder="请输入需要购买的内容"
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

            <!-- 参照图 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <u-icon name="photo" size="24" color="#52c41a"></u-icon>
                    </view>
                    <text class="label">参照图</text>
                    <text class="hint">不需要可忽略</text>
                </view>
                <xy-upload v-model="imageStr" :maxCount="10" />
            </view>

            <!-- 性别限制 -->
            <!-- <view class="form-card">
                <view class="card-icon">
                    <u-icon name="account" size="24" color="#e91e63"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">性别限制</text>
                </view>
                <view class="gender-options">
                    <view 
                        class="gender-btn" 
                        :class="{ active: genderLimit === 'male' }"
                        @click="genderLimit = 'male'"
                    >男生</view>
                    <view 
                        class="gender-btn" 
                        :class="{ active: genderLimit === 'female' }"
                        @click="genderLimit = 'female'"
                    >女生</view>
                    <view 
                        class="gender-btn" 
                        :class="{ active: genderLimit === 'none' }"
                        @click="genderLimit = 'none'"
                    >不限</view>
                </view>
            </view> -->

            <!-- 时间 -->
            <view class="form-card" @click="showTimePicker = true">
                <view class="card-icon">
                    <u-icon name="clock" size="24" color="#9c27b0"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">时间</text>
                </view>
                <text class="time-value">{{ expectTime || '选择送达时间' }}</text>
            </view>

            <!-- 跑腿费 -->
            <view class="form-card">
                <view class="card-icon">
                    <u-icon name="red-packet" size="24" color="#f44336"></u-icon>
                </view>
                <view class="card-content">
                    <text class="label">跑腿费</text>
                </view>
                <view class="fee-input-wrap">
                    <text class="fee-unit">¥</text>
                    <input class="fee-input" type="digit" v-model="tipFee" placeholder="0" />
                </view>
            </view>

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
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
import { ref, onMounted, onUnmounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import '../../css/base.css'
import xyUpload from '../../components/xy-upload.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_buy')

const statusBarHeight = ref(0)
const navBarHeight = ref(44)
const showTimePicker = ref(false)
const selectedTime = ref(Date.now())

const receiveAddress = ref('')
const receiveAddressId = ref(0)
const goodsName = ref('')
const imageStr = ref('')
const genderLimit = ref('none')
const expectTime = ref('')
const tipFee = ref(2)
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const quickTags = ['牛奶', '面包', '水果', '辣条', '泡面', '饮料', '烟酒', '日用品']
const selectedTags = ref<string[]>([])

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    
    // 监听全局地址选择事件
    uni.$on('onAddressSelect', onAddressSelect)
})

onUnmounted(() => {
    uni.$off('onAddressSelect', onAddressSelect)
})

const onAddressSelect = (address: any) => {
    receiveAddress.value = address.address
    receiveAddressId.value = address.id
}

const toggleTag = (tag: string) => {
    const index = selectedTags.value.indexOf(tag)
    if (index === -1) {
        selectedTags.value.push(tag)
        if (!goodsName.value.includes(tag)) {
            goodsName.value += (goodsName.value ? '、' : '') + tag
        }
    } else {
        selectedTags.value.splice(index, 1)
    }
}

const selectAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=receive',
        success: (res) => {
            res.eventChannel.on('selectAddress', (address: any) => {
                receiveAddress.value = address.address
            })
        }
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
    if (!receiveAddress.value) {
        uni.showToast({ title: '请选择送货地址', icon: 'none' })
        return
    }
    if (!goodsName.value) {
        uni.showToast({ title: '请输入物品名称', icon: 'none' })
        return
    }

    const ext = {
        goods_name: goodsName.value,
        gender_limit: genderLimit.value,
        expect_time: expectTime.value,
        quick_tags: selectedTags.value,
        images: imageStr.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'BUY',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            // 对于代买订单，取货地址应该是商家地址（这里暂时留空，由接单员确认）
            pickup_name: '',
            pickup_mobile: '',
            pickup_address: '',
            // 收货地址是用户选择的地址
            receive_name: '用户', // 简化处理
            receive_mobile: '', // 用户手机号可以为空
            receive_address: receiveAddress.value,
            goods_name: goodsName.value,
            task_desc: goodsName.value,
            images: imageStr.value,
            total_fee: tipFee.value,
            remark: '',
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
    } catch (e) {
        uni.hideLoading()
        uni.showToast({ title: '网络错误', icon: 'none' })
    }
}

const goBack = () => {
    uni.navigateBack()
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
.buy-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #e3f2fd, #bbdefb);
    padding-bottom: 20rpx;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 100;
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
    height: calc(100vh - 200rpx);width: auto;
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
        font-size: 28rpx;
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
        color: #1890ff;
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
            background: #e6f7ff;
            color: #1890ff;
            border-color: #1890ff;
        }
    }
}

.image-list {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    width: 100%;
}

.image-item {
    width: 140rpx;
    height: 140rpx;
    border-radius: 12rpx;
    overflow: hidden;
    position: relative;
    
    image {
        width: 100%;
        height: 100%;
    }
    
    .delete-btn {
        position: absolute;
        top: 0;
        right: 0;
        width: 36rpx;
        height: 36rpx;
        background: rgba(0,0,0,0.5);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24rpx;
    }
    
    &.add {
        background: #f5f5f5;
        border: 2rpx dashed #ddd;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

.gender-options {
    display: flex;
    gap: 16rpx;
    
    .gender-btn {
        padding: 12rpx 32rpx;
        background: #f5f5f5;
        border-radius: 30rpx;
        font-size: 26rpx;
        color: #666;
        
        &.active {
            background: #1890ff;
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

.submit-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20rpx 30rpx;
    background: #fff;z-index: 22;
    box-shadow: 0 -4rpx 20rpx rgba(0,0,0,0.05);
    padding-bottom: calc(20rpx + env(safe-area-inset-bottom));
    
    .price-info {
        flex:1;
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
}
</style>
