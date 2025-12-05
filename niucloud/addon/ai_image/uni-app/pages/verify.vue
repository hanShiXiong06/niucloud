<template>
    <view class="min-h-screen relative" :style="themeColor()">
        <!-- 背景渐变光晕 -->
        <view class="bg-layer"
            style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 0; background: linear-gradient(to bottom, #0f1419 0%, #0a0e1a 50%, #0f1419 100%);">
        </view>
        <view class="glow-layer-1"
            style="position: fixed; top: -200rpx; left: 25%; width: 600rpx; height: 600rpx; background: radial-gradient(circle, rgba(59, 130, 246, 0.15) 0%, transparent 70%); border-radius: 50%; z-index: 0; filter: blur(80rpx);">
        </view>
        <view class="glow-layer-2"
            style="position: fixed; bottom: -200rpx; right: 25%; width: 600rpx; height: 600rpx; background: radial-gradient(circle, rgba(99, 102, 241, 0.15) 0%, transparent 70%); border-radius: 50%; z-index: 0; filter: blur(80rpx);">
        </view>

        <view class="relative" style="position: relative; z-index: 10;">
            <!-- 顶部标题区域 -->
            <view class="px-6 pt-10 pb-6">
                <view class="text-white text-2xl font-normal mb-2" style="font-weight: 400;">卡密兑换</view>
                <view class="text-sm text-[#94a3b8]" style="line-height: 1.6;">输入卡密进行激活，激活后立即生效</view>
            </view>

            <!-- 主卡片：输入与提交 -->
            <view class="px-6 mb-5">
                <view class="card-container"
                    style="background: rgba(19, 23, 34, 0.95); border-radius: 20rpx; border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.4); overflow: hidden;">

                    <!-- 输入区域 -->
                    <view class="p-6">
                        <view class="text-[#e4e7eb] text-sm mb-4" style="font-weight: 500;">请输入卡密</view>
                        <view class="input-wrapper"
                            style="background: rgba(13, 17, 23, 0.8); border-radius: 12rpx; border: 1px solid rgba(59, 130, 246, 0.2); overflow: hidden;">
                            <u-input v-model="cardNum" placeholder="请输入有效兑换码" border="none" clearable
                                placeholder-style="color:#64748b" class="product-name-input" color="#ffffff"
                                :custom-style="{
                                    backgroundColor: 'transparent',
                                    borderRadius: '12rpx',
                                    padding: '28rpx 24rpx',
                                    color: '#ffffff',
                                    fontSize: '15px'
                                }" />
                        </view>
                    </view>

                    <!-- 按钮区域 -->
                    <view class="px-6 pb-6 flex gap-3">
                        <u-button type="primary" plain custom-style="
                                border-color:rgba(59, 130, 246, 0.3);
                                color:#94a3b8;
                                background:rgba(15, 23, 42, 0.5);
                                border-radius:12rpx;
                                height:92rpx;
                                font-size:15px;
                                font-weight:400;
                            " @click="cardNum = ''">
                            清空
                        </u-button>
                        <u-button type="primary" custom-style="
                                background:#3b82f6;
                                color:#ffffff;
                                border-color:transparent;
                                border-radius:12rpx;
                                height:92rpx;
                                font-size:15px;
                                font-weight:500;
                                box-shadow:0 8rpx 20rpx rgba(59,130,246,0.4);
                            " @click="submit">
                            立即激活
                        </u-button>
                    </view>
                </view>
            </view>

            <!-- 说明卡片 -->
            <view class="px-6 mb-6">
                <view class="card-container"
                    style="background: rgba(19, 23, 34, 0.95); border-radius: 20rpx; border: 1px solid rgba(59, 130, 246, 0.15); box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.4); overflow: hidden;">

                    <view class="p-6">
                        <view class="text-[#e4e7eb] text-sm mb-4" style="font-weight: 500;">使用说明</view>
                        <view class="space-y-3">
                            <view class="flex items-start">
                                <view class="w-1.5 h-1.5 rounded-full bg-[#3b82f6] mt-2 mr-3 flex-shrink-0"></view>
                                <text class="text-[#94a3b8] text-sm flex-1" style="line-height: 1.8;">卡密一经激活，立即生效</text>
                            </view>
                            <view class="flex items-start">
                                <view class="w-1.5 h-1.5 rounded-full bg-[#3b82f6] mt-2 mr-3 flex-shrink-0"></view>
                                <text class="text-[#94a3b8] text-sm flex-1"
                                    style="line-height: 1.8;">激活后无法退换，不可重复使用</text>
                            </view>
                            <view class="flex items-start">
                                <view class="w-1.5 h-1.5 rounded-full bg-[#3b82f6] mt-2 mr-3 flex-shrink-0"></view>
                                <text class="text-[#94a3b8] text-sm flex-1"
                                    style="line-height: 1.8;">如遇问题，请联系在线客服</text>
                            </view>
                        </view>
                    </view>
                </view>
            </view>

            <!-- 底部导航 -->
            <view class="mt-auto">
                <tabbar addon="tk_sora" />
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { img, redirect } from '@/utils/common'
import { verifyNum } from '@/addon/ai_image/api/aiimage'

const cardNum = ref('')

const submit = () => {
    if (!cardNum.value) {
        return uni.showToast({ title: '请输入卡密码', icon: 'none' })
    }
    verifyNum(cardNum.value)
        .then(res => {
            uni.showToast({ title: '激活成功', icon: 'success' })
            cardNum.value = ''
            // redirect({ url: '/addon/tk_sora/pages/log' })
        })
        .catch(err => {
            uni.showToast({ title: err.msg || '激活失败', icon: 'none' })
        })
}
</script>

<style lang="scss" scoped>
/* 卡片容器样式 */
.card-container {
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
}

/* 输入框样式 */
.input-wrapper {
    transition: border-color 0.3s ease;
}

/* 优化文字渲染 */
text {
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
}

/* 优化列表项间距 */
.space-y-3>view:not(:last-child) {
    margin-bottom: 16rpx;
}
</style>