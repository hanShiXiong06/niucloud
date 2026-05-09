<template>
    <view :style="themeColor()" class="offline-pay-container">
        <view class="payment-wrapper" v-if="payInfo">
            <!-- 顶部头部 -->
            <view class="header-section">
                <view class="payment-title">线下支付</view>
                <view class="amount-section">
                    <view class="amount-label">支付金额</view>
                    <view class="amount-display">
                        <text class="currency">￥</text>
                        <text class="amount">{{ parseFloat(payInfo.money).toFixed(2) }}</text>
                    </view>
                </view>
            </view>

            <!-- 订单信息 -->
            <view class="order-info-section">
                <view class="section-title">订单信息</view>
                <view class="info-item">
                    <text class="label">订单描述：</text>
                    <text class="value">{{ payInfo.body }}</text>
                </view>
                <view class="info-item">
                    <text class="label">订单编号：</text>
                    <text class="value">{{ payInfo.out_trade_no }}</text>
                </view>
            </view>

            <!-- 支付说明 -->
            <view class="payment-guide-section">
                <view class="section-title">支付说明: 有任何问题请直接联系客服</view>
                <view class="guide-content">
                    <view class="guide-item">
                        <text class="step-number">1</text>
                        <text class="step-text">请通过银行转账、微信转账、支付宝转账等方式向商家付款 转账后联系客服</text>
                    </view>
                    
                    <view class="guide-item">
                        <text class="step-number">2</text>
                        <text class="step-text">提交后等待商家确认，确认后订单将自动完成支付</text>
                    </view>
                </view>
            </view>

            <!-- 凭证填写 -->
            <view class="voucher-section">
                <view class="section-title">支付凭证</view>
                <view class="voucher-form">
                    <view class="form-item">
                        <view class="form-label">支付方式</view>
                        <picker @change="onPayMethodChange" :value="payMethodIndex" :range="payMethods">
                            <view class="picker-display">
                                <text>{{ payMethods[payMethodIndex] }}</text>
                                <text class="picker-arrow">></text>
                            </view>
                        </picker>
                    </view>
                    
                </view>
            </view>
        </view>

        <!-- 底部按钮 -->
        <view class="bottom-actions" v-if="payInfo">
            <button class="submit-btn" :disabled="loading" :loading="loading" @click="submitPayment">
                提交支付凭证
            </button>
            <button class="cancel-btn" @click="goBack">取消支付</button>
        </view>

        <!-- 加载中 -->
        <view class="loading-wrapper" v-if="!payInfo && !error">
            <u-loading-icon mode="flower"></u-loading-icon>
            <text class="loading-text">正在加载支付信息...</text>
        </view>

        <!-- 错误信息 -->
        <view class="error-wrapper" v-if="error">
            <view class="error-content">
                <text class="error-text">{{ error }}</text>
                <button class="retry-btn" @click="loadPayInfo">重试</button>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import { getPayInfo, pay } from '@/app/api/pay'
import { redirect } from '@/utils/common'

const payInfo = ref<any>(null)
const voucher = ref('')
const loading = ref(false)
const error = ref('')
const payMethodIndex = ref(0)
const payMethods = ['银行转账', '微信转账', '支付宝转账', '现金支付', '其他方式']

// 页面参数
const trade_type = ref('')
const trade_id = ref(0)

onLoad((options: any) => {
    trade_type.value = options.trade_type || ''
    trade_id.value = parseInt(options.trade_id) || 0
    
    if (!trade_type.value || !trade_id.value) {
        error.value = '参数错误，请重新进入支付页面'
        return
    }
    
    loadPayInfo()
})

const loadPayInfo = async () => {
    try {
        error.value = ''
        const res: any = await getPayInfo(trade_type.value, trade_id.value, {})
        payInfo.value = res.data
        
        if (!payInfo.value) {
            error.value = '无法获取支付信息'
        }
    } catch (err: any) {
        error.value = err.message || '加载支付信息失败'
    }
}

const onPayMethodChange = (e: any) => {
    payMethodIndex.value = e.detail.value
}

const submitPayment = async () => {
    // if (!voucher.value.trim()) {
    //     uni.showToast({ title: '请填写支付凭证信息', icon: 'none' })
    //     return
    // }
    
    loading.value = true
    
    try {
        const voucherText = `支付方式：${payMethods[payMethodIndex.value]}\n凭证信息：${voucher.value.trim()}`
        
        await pay({
            trade_type: trade_type.value,
            trade_id: trade_id.value,
            type: 'offlinepay',
            voucher: voucherText,
            openid: uni.getStorageSync('openid') || ''
        })
        
        uni.showToast({ title: '提交成功，请等待商家确认', icon: 'success' })
        
        // 跳转到支付结果页面
        setTimeout(() => {
            redirect({
                url: '/app/pages/pay/result',
                param: { 
                    trade_type: trade_type.value, 
                    trade_id: trade_id.value 
                },
                mode: 'redirectTo'
            })
        }, 1500)
        
    } catch (err: any) {
        uni.showToast({ title: err.message || '提交失败，请重试', icon: 'none' })
    } finally {
        loading.value = false
    }
}

const goBack = () => {
    uni.navigateBack()
}
</script>

<style lang="scss" scoped>
.offline-pay-container {
    min-height: 100vh;
    background: #f8f9fa;
    padding-bottom: 200rpx;
}

.payment-wrapper {
    padding: 30rpx;
}

.header-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 20rpx;
    padding: 40rpx 30rpx;
    margin-bottom: 30rpx;
    color: white;
}

.payment-title {
    font-size: 32rpx;
    font-weight: bold;
    text-align: center;
    margin-bottom: 30rpx;
}

.amount-section {
    text-align: center;
}

.amount-label {
    font-size: 26rpx;
    opacity: 0.9;
    margin-bottom: 10rpx;
}

.amount-display {
    font-weight: bold;
}

.currency {
    font-size: 32rpx;
}

.amount {
    font-size: 48rpx;
}

.order-info-section, .payment-guide-section, .voucher-section {
    background: white;
    border-radius: 20rpx;
    padding: 30rpx;
    margin-bottom: 30rpx;
}

.section-title {
    font-size: 32rpx;
    font-weight: bold;
    color: #333;
    margin-bottom: 30rpx;
    padding-bottom: 15rpx;
    border-bottom: 2px solid #f0f0f0;
}

.info-item {
    display: flex;
    margin-bottom: 20rpx;
    font-size: 28rpx;
}

.label {
    color: #666;
    margin-right: 20rpx;
    flex-shrink: 0;
}

.value {
    color: #333;
    flex: 1;
    word-break: break-all;
}

.guide-content {
    .guide-item {
        display: flex;
        align-items: flex-start;
        margin-bottom: 25rpx;
        
        &:last-child {
            margin-bottom: 0;
        }
    }
    
    .step-number {
        width: 40rpx;
        height: 40rpx;
        background: #667eea;
        color: white;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24rpx;
        margin-right: 20rpx;
        flex-shrink: 0;
    }
    
    .step-text {
        font-size: 28rpx;
        color: #666;
        line-height: 1.6;
        flex: 1;
    }
}

.voucher-form {
    .form-item {
        margin-bottom: 30rpx;
        
        &:last-child {
            margin-bottom: 0;
        }
    }
    
    .form-label {
        font-size: 28rpx;
        color: #333;
        margin-bottom: 15rpx;
        
        .required {
            color: #ff4757;
        }
    }
    
    .picker-display {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 20rpx;
        background: #f8f9fa;
        border-radius: 10rpx;
        font-size: 28rpx;
    }
    
    .picker-arrow {
        color: #999;
    }
    
    .voucher-input {
        width: 100%;
        min-height: 200rpx;
        padding: 20rpx;
        border: 2px solid #e9ecef;
        border-radius: 10rpx;
        font-size: 28rpx;
        line-height: 1.5;
        resize: none;
        
        &:focus {
            border-color: #667eea;
        }
    }
    
    .input-tip {
        font-size: 24rpx;
        color: #999;
        margin-top: 10rpx;
    }
}

.bottom-actions {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: white;
    padding: 30rpx;
    border-top: 2rpx solid #f0f0f0;
    z-index: 100;
}

.submit-btn {
    width: 100%;
    height: 88rpx;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 44rpx;
    font-size: 32rpx;
    font-weight: bold;
    margin-bottom: 20rpx;
    border: none;
    
    &:disabled {
        opacity: 0.6;
    }
}

.cancel-btn {
    width: 100%;
    height: 88rpx;
    background: transparent;
    color: #999;
    border: 2rpx solid #e9ecef;
    border-radius: 44rpx;
    font-size: 28rpx;
}

.loading-wrapper, .error-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 60vh;
    padding: 30rpx;
}

.loading-text {
    margin-top: 30rpx;
    font-size: 28rpx;
    color: #666;
}

.error-content {
    text-align: center;
}

.error-text {
    font-size: 28rpx;
    color: #666;
    margin-bottom: 30rpx;
    display: block;
}

.retry-btn {
    padding: 20rpx 40rpx;
    background: #667eea;
    color: white;
    border-radius: 10rpx;
    font-size: 28rpx;
    border: none;
}
</style> 