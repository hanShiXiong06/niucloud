<template>
    <feature-disabled :show="!isFeatureEnabled" :text="config?.close_text" />
    <view class="carry-page" v-if="isFeatureEnabled">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">搬运服务</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="main-title">帮搬运</text>
                    <view class="sub-tag">
                        <text>· 下单立享优质服务 ·</text>
                    </view>
                </view>
                <view class="header-right">
                    <u-icon name="car" size="80" color="#52c41a"></u-icon>
                </view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 起点地址 -->
            <view class="form-card" @click="selectPickupAddress">
                <view class="card-icon pickup">
                    <text>起</text>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!pickupAddress">去哪里搬运东西?</text>
                    <text class="value" v-else>{{ pickupAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 终点地址 -->
            <view class="form-card" @click="selectReceiveAddress">
                <view class="card-icon receive">
                    <text>送</text>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!receiveAddress">送到哪里给您呢?</text>
                    <text class="value" v-else>{{ receiveAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 物品类型 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon note">
                        <text>📦</text>
                    </view>
                    <text class="label">物品类型</text>
                </view>
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

            <!-- 任务描述 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon note">
                        <text>📝</text>
                    </view>
                    <text class="label">任务描述</text>
                </view>
                <textarea 
                    class="content-input" 
                    v-model="taskDesc" 
                    placeholder="请描述搬运物品的具体信息"
                    :maxlength="500"
                ></textarea>
            </view>

            <!-- 备注 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon note">
                        <text>✏️</text>
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

            <!-- 赏金 -->
            <view class="form-card">
                <view class="card-icon money">
                    <text>💰</text>
                </view>
                <view class="card-content">
                    <text class="label">赏金</text>
                </view>
                <input 
                    type="number" 
                    class="fee-input" 
                    v-model="tipFee" 
                    placeholder="输入金额"
                />
            </view>

            <xy-order-yinsi-field v-model="yinsiText" />

            <!-- 底部占位 -->
            <view style="height: 300rpx;"></view>
        </scroll-view>

        <!-- 底部提交栏 -->
        <view class="submit-bar">
            <view style="flex:1"></view>
            <button class="submit-btn2" @click="submitOrder">立即下单</button>
        </view>

        <!-- 支付组件 -->
        <pay ref="payRef" @success="onPaySuccess" @fail="onPayFail" />
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'
import XyOrderYinsiField from '../../components/xy-order-yinsi-field.vue'
import { useFeatureCheck } from '../../composables/useFeatureCheck'
import FeatureDisabled from '../../components/feature-disabled.vue'

const { config, isFeatureEnabled, loadConfig } = useFeatureCheck('enable_carry')

const statusBarHeight = ref(0)
const navBarHeight = ref(44)

const pickupAddress = ref('')
const receiveAddress = ref('')
const pickupLng = ref('')
const pickupLat = ref('')
const receiveLng = ref('')
const receiveLat = ref('')
const taskDesc = ref('')
const remark = ref('')
const yinsiText = ref('')
const tipFee = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const quickTags = ['书籍', '桌椅', '床板', '生活物品', '活动物品', '杂物', '简易帐篷']
const selectedTags = ref<string[]>([])

const onAddrSelect = (address: any) => {
    if (!address) return
    if (address.type === 'pickup') {
        pickupAddress.value = address.address
        pickupLng.value = address.lng || ''
        pickupLat.value = address.lat || ''
    } else if (address.type === 'receive') {
        receiveAddress.value = address.address
        receiveLng.value = address.lng || ''
        receiveLat.value = address.lat || ''
    }
}

onMounted(() => {
    loadConfig()
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
    uni.$on('onAddressSelect', onAddrSelect)
})

onUnmounted(() => {
    uni.$off('onAddressSelect', onAddrSelect)
})

const toggleTag = (tag: string) => {
    const index = selectedTags.value.indexOf(tag)
    if (index === -1) {
        selectedTags.value.push(tag)
    } else {
        selectedTags.value.splice(index, 1)
    }
}

const selectPickupAddress = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/address/select?type=pickup' })
}

const selectReceiveAddress = () => {
    uni.navigateTo({ url: '/addon/sd_xiaoyuan/pages/address/select?type=receive' })
}

const submitOrder = async () => {
    if (!pickupAddress.value) {
        uni.showToast({ title: '请选择起点地址', icon: 'none' })
        return
    }
    if (!receiveAddress.value) {
        uni.showToast({ title: '请选择终点地址', icon: 'none' })
        return
    }
    if (!tipFee.value || parseFloat(tipFee.value) <= 0) {
        uni.showToast({ title: '请输入赏金', icon: 'none' })
        return
    }

    const itemTypes = selectedTags.value.join('、')
    const ext = {
        item_type: itemTypes,
        task_desc: taskDesc.value,
        quick_tags: selectedTags.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'CARRY',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            pickup_address: pickupAddress.value,
            pickup_lng: pickupLng.value,
            pickup_lat: pickupLat.value,
            receive_address: receiveAddress.value,
            receive_lng: receiveLng.value,
            receive_lat: receiveLat.value,
            goods_name: itemTypes || '搬运物品',
            task_desc: taskDesc.value,
            total_fee: parseFloat(tipFee.value),
            remark: remark.value,
            yinsi_text: yinsiText.value,
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
.carry-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
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
        .main-title {
            display: block;
            font-size: 48rpx;
            font-weight: bold;
            color: #333;
            margin-bottom: 10rpx;
        }
        
        .sub-tag {
            display: inline-block;
            background: linear-gradient(135deg, #52c41a, #73d13d);
            padding: 8rpx 20rpx;
            border-radius: 30rpx;
            
            text {
                font-size: 22rpx;
                color: #fff;
            }
        }
    }
}

.form-content {
    height: calc(100vh - 350rpx);
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
        font-size: 24rpx;
        border-radius: 8rpx;
        
        &.pickup {
            background: #52c41a;
            color: #fff;
        }
        
        &.receive {
            background: #1890ff;
            color: #fff;
        }
        
        &.note, &.money {
            font-size: 28rpx;
        }
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
    }
}

.content-input {
    width: 94%;
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
            background: #e8f5e9;
            color: #52c41a;
            border-color: #52c41a;
        }
    }
}

.fee-input {
    width: 200rpx;
    text-align: right;
    font-size: 28rpx;
    color: #333;
}

.submit-bar {
    position: fixed;
    bottom: 0;z-index: 22;
    left: 0;
    right: 0;
    padding: 20rpx 30rpx;
    background: #fff;
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
