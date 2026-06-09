<template>
    <view class="asset-scan-page">
        <view class="hero">
            <view class="hero-title">资产拍照</view>
            <view class="hero-desc">扫描 PC 端资产二维码，或输入资产 ID 后进入补拍页面。</view>
        </view>

        <view class="panel">
            <button class="primary-btn" @click="scanCode">扫码绑定资产</button>
            <view class="divider">或</view>
            <view class="input-row">
                <input v-model="assetId" class="asset-input" type="number" placeholder="输入资产ID" />
                <button class="go-btn" @click="goCapture(assetId)">进入</button>
            </view>
        </view>

        <view class="tips">
            <view class="tips-title">操作说明</view>
            <view class="tips-item">1. 在 PC 端打开资产的“拍照/补图”弹窗。</view>
            <view class="tips-item">2. 手机扫描弹窗里的二维码。</view>
            <view class="tips-item">3. 拍照上传后，PC 端点击刷新即可同步查看。</view>
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
            goCapture(id)
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

const goCapture = (id: string) => {
    const value = String(id || '').trim()
    if (!value) {
        uni.showToast({ title: '请输入资产ID', icon: 'none' })
        return
    }
    uni.navigateTo({
        url: `/addon/hsx_device_asset/pages/photo/capture?id=${ value }`
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
    background: linear-gradient(135deg, #2563eb 0%, #14b8a6 100%);
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
    background: #2563eb;
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
    color: #2563eb;
    background: #dbeafe;
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
