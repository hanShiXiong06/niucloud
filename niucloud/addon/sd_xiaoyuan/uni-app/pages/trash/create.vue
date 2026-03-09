<template>
    <view class="trash-page">
        <!-- 顶部背景 -->
        <view class="header-bg">
            <view class="status-bar" :style="{ height: statusBarHeight + 'px' }"></view>
            <view class="navbar">
                <view class="back-btn" @click="goBack">
                    <u-icon name="arrow-left" size="20" color="#333"></u-icon>
                </view>
                <text class="title">扔垃圾</text>
                <view class="placeholder"></view>
            </view>
            <view class="header-content">
                <view class="header-left">
                    <text class="main-title">扔垃圾</text>
                    <view class="sub-tag">
                        <text>· 下享优质服务 ·</text>
                    </view>
                </view>
                <view class="header-right">
                    <u-icon name="trash" size="80" color="#9c27b0"></u-icon>
                </view>
            </view>
        </view>

        <!-- 表单内容 -->
        <scroll-view scroll-y class="form-content">
            <!-- 取垃圾地址 -->
            <view class="form-card" @click="selectAddress">
                <view class="card-icon">
                    <text>🗑️</text>
                </view>
                <view class="card-content">
                    <text class="placeholder" v-if="!pickupAddress">去哪里取垃圾</text>
                    <text class="value" v-else>{{ pickupAddress }}</text>
                </view>
                <u-icon name="arrow-right" size="16" color="#ccc"></u-icon>
            </view>

            <!-- 垃圾描述 -->
            <view class="form-card column">
                <view class="card-header">
                    <view class="card-icon">
                        <text>📝</text>
                    </view>
                    <text class="label">垃圾描述</text>
                </view>
                <textarea 
                    class="content-input" 
                    v-model="trashDesc" 
                    placeholder="请大概描述垃圾类型及数量"
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

            <!-- 赏金 -->
            <view class="form-card">
                <view class="card-icon">
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
import '@/addon/sd_xiaoyuan/css/base.css'
import { ref, onMounted } from 'vue'
import { createOrder } from '../../api/xiaoyuan'
import { tryBindFenxiao } from '../../utils/bindFenxiao'
import pay from '@/components/pay/pay.vue'

const statusBarHeight = ref(0)
const navBarHeight = ref(44)

const pickupAddress = ref('')
const trashDesc = ref('')
const tipFee = ref('')
const payRef = ref<any>(null)
const currentOrderId = ref(0)

const quickTags = ['垃圾袋', '纸箱', '厨房用品', '家电', '床', '沙发', '柜子', '衣服', '其他请说明']
const selectedTags = ref<string[]>([])

onMounted(() => {
    tryBindFenxiao()
    const sysInfo = uni.getSystemInfoSync()
    statusBarHeight.value = sysInfo.statusBarHeight || 0
    
    // #ifdef MP-WEIXIN
    const menuButton = uni.getMenuButtonBoundingClientRect()
    navBarHeight.value = (menuButton.top - statusBarHeight.value) * 2 + menuButton.height
    // #endif
})

const toggleTag = (tag: string) => {
    const index = selectedTags.value.indexOf(tag)
    if (index === -1) {
        selectedTags.value.push(tag)
        if (!trashDesc.value.includes(tag)) {
            trashDesc.value += (trashDesc.value ? '、' : '') + tag
        }
    } else {
        selectedTags.value.splice(index, 1)
    }
}

const selectAddress = () => {
    uni.navigateTo({
        url: '/addon/sd_xiaoyuan/pages/address/select?type=pickup',
        success: (res) => {
            res.eventChannel.on('selectAddress', (address: any) => {
                pickupAddress.value = address.address
            })
        }
    })
}

const submitOrder = async () => {
    if (!pickupAddress.value) {
        uni.showToast({ title: '请选择取垃圾地址', icon: 'none' })
        return
    }
    if (!trashDesc.value) {
        uni.showToast({ title: '请描述垃圾类型', icon: 'none' })
        return
    }
    if (!tipFee.value || parseFloat(tipFee.value) <= 0) {
        uni.showToast({ title: '请输入赏金', icon: 'none' })
        return
    }

    const ext = {
        trash_desc: trashDesc.value,
        quick_tags: selectedTags.value
    }

    uni.showLoading({ title: '提交中...' })
    try {
        const cachedSchool = uni.getStorageSync('current_school')
        const res: any = await createOrder({
            task_type: 'TRASH',
            school_id: cachedSchool?.id || 0,
            campus: cachedSchool?.campus || '',
            pickup_address: pickupAddress.value,
            goods_name: '垃圾清理',
            task_desc: trashDesc.value,
            total_fee: parseFloat(tipFee.value),
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
.trash-page {
    min-height: 100vh;
    background: #f5f5f5;
}

.header-bg {
    background: linear-gradient(135deg, #f3e5f5, #e1bee7);
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
            background: linear-gradient(135deg, #9c27b0, #ba68c8);
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
    padding: 20rpx;
}

.form-card {
    background: #fff;
    border-radius: 16rpx;
    padding: 30rpx;
    margin-bottom: 20rpx;
    display: flex;
    align-items: center;
    
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
            background: #f3e5f5;
            color: #9c27b0;
            border-color: #9c27b0;
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
    bottom: 0;
    left: 0;
    right: 0;z-index: 22;
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
