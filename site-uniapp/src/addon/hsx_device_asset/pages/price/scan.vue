<template>
    <view class="asset-scan-page">
        <view class="hero">
            <view class="hero-title">资产定价</view>
            <view class="hero-desc">扫描资产二维码，查看质检和拍摄图片后完成销售定价。</view>
        </view>

        <view class="panel">
            <button class="primary-btn" @click="scanCode">扫码进入定价</button>
            <view class="divider">或</view>
            <view class="input-row">
                <input v-model="assetId" class="asset-input" type="number" placeholder="输入资产ID" />
                <button class="go-btn" @click="goPrice(assetId)">进入</button>
            </view>
        </view>

        <view class="tips">
            <view class="tips-title">定价前确认</view>
            <view class="tips-item">1. 优先确认图片已经完成拍摄和复检。</view>
            <view class="tips-item">2. 对照回收质检信息、质检图片和当前拍摄图。</view>
            <view class="tips-item">3. 填写销售价、同行价和最低价后保存。</view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'

const assetId = ref('')

const scanCode = () => {
    uni.scanCode({
        onlyFromCamera: false,
        success: (res) => {
            const id = parseAssetId(res.result || '')
            if (!id) {
                uni.showToast({ title: '未识别到资产ID', icon: 'none' })
                return
            }
            goPrice(id)
        },
        fail: (error: any) => {
            if (error?.errMsg && !String(error.errMsg).includes('cancel')) {
                uni.showToast({ title: error.errMsg, icon: 'none' })
            }
        }
    })
}

const parseAssetId = (text: string) => {
    const value = String(text || '').trim()
    if (/^\d+$/.test(value)) return value
    const match = value.match(/[?&]id=(\d+)/)
    return match?.[1] || ''
}

const goPrice = (id: string) => {
    const value = String(id || '').trim()
    if (!value) {
        uni.showToast({ title: '请输入资产ID', icon: 'none' })
        return
    }
    uni.navigateTo({
        url: `/addon/hsx_device_asset/pages/price/detail?id=${ value }`
    })
}
</script>

<style lang="scss" scoped>
.asset-scan-page {
    min-height: 100vh;
    padding: 28rpx;
    background: #f6f7fb;
    box-sizing: border-box;
}

.hero {
    padding: 34rpx 30rpx;
    border-radius: 24rpx;
    color: #fff;
    background: linear-gradient(135deg, #0f766e 0%, var(--hsx-primary) 100%);
}

.hero-title {
    font-size: 40rpx;
    font-weight: 700;
}

.hero-desc {
    margin-top: 12rpx;
    font-size: 25rpx;
    line-height: 1.6;
    color: rgba(255, 255, 255, 0.86);
}

.panel,
.tips {
    margin-top: 24rpx;
    padding: 28rpx;
    border-radius: 20rpx;
    background: #fff;
    box-shadow: 0 8rpx 24rpx rgba(15, 23, 42, 0.05);
}

.primary-btn {
    height: 88rpx;
    line-height: 88rpx;
    border-radius: 44rpx;
    color: #fff;
    background: var(--hsx-primary);
    font-size: 30rpx;
}

.divider {
    margin: 24rpx 0;
    text-align: center;
    color: #9ca3af;
    font-size: 24rpx;
}

.input-row {
    display: grid;
    grid-template-columns: 1fr 150rpx;
    gap: 14rpx;
}

.asset-input {
    height: 82rpx;
    padding: 0 24rpx;
    border-radius: 16rpx;
    background: #f3f4f6;
    font-size: 28rpx;
}

.go-btn {
    height: 82rpx;
    line-height: 82rpx;
    border-radius: 16rpx;
    color: var(--hsx-primary);
    background: var(--hsx-primary-100);
    font-size: 28rpx;
}

.tips-title {
    margin-bottom: 14rpx;
    color: #111827;
    font-size: 30rpx;
    font-weight: 650;
}

.tips-item {
    color: #6b7280;
    font-size: 25rpx;
    line-height: 1.8;
}
</style>
