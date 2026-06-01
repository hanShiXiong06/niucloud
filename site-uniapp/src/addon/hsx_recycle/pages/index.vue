<template>
    <view class="recycle-home min-h-[100vh] bg-[#f5f7fa]">
        <view class="hero px-[24rpx] pt-[24rpx]">
            <view class="hero-card">
                <view class="hero-title">回收业务工作台</view>
                <view class="hero-subtitle">订单处理和业务跟进统一收口到移动端</view>
            </view>
        </view>

        <view class="px-[24rpx] mt-[24rpx]">
            <view class="section-title">业务入口</view>
            <view class="module-grid">
                <view
                    v-for="item in modules"
                    :key="item.name"
                    class="module-card"
                    @click="redirect({ url: item.url })"
                >
                    <view class="module-icon" :style="{ background: item.color }">{{ item.short }}</view>
                    <view class="module-name">{{ item.name }}</view>
                    <view class="module-desc">{{ item.desc }}</view>
                </view>
            </view>
        </view>

        <view class="px-[24rpx] mt-[24rpx] pb-[40rpx]">
            <view class="section-title">处理范围</view>
            <view class="tips-card">
                <view class="tip-row">
                    <text class="tip-label">订单模块</text>
                    <text class="tip-value">签收、质检、定价、确认回收、退回、打款</text>
                </view>
                <view class="tip-row">
                    <text class="tip-label">代卖业务</text>
                    <text class="tip-value">上架、登记成交、结算客户、取消代卖</text>
                </view>
                <view class="tip-row">
                    <text class="tip-label">退回业务</text>
                    <text class="tip-value">确认退货、完成退货、删除异常退回单</text>
                </view>
                <view class="tip-row">
                    <text class="tip-label">快递业务</text>
                    <text class="tip-value">查看运单、核对重量费用、跟踪记录</text>
                </view>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { redirect } from '@/utils/common'

const modules = [
    {
        name: '扫码处理',
        short: '扫',
        desc: '扫码自动质检/定价',
        url: '/addon/hsx_recycle/pages/check/scan',
        color: 'linear-gradient(135deg, #0f766e 0%, #14b8a6 100%)'
    },
    {
        name: '订单管理',
        short: '单',
        desc: '订单处理主链路',
        url: '/addon/hsx_recycle/pages/order/list',
        color: 'linear-gradient(135deg, #2979ff 0%, #5aa2ff 100%)'
    },
    {
        name: '代卖订单',
        short: '卖',
        desc: '回收转代卖处理',
        url: '/addon/hsx_recycle/pages/consignment/list',
        color: 'linear-gradient(135deg, #18a058 0%, #36c275 100%)'
    },
    {
        name: '退回订单',
        short: '退',
        desc: '退货流程处理',
        url: '/addon/hsx_recycle/pages/return/list',
        color: 'linear-gradient(135deg, #f0a020 0%, #f7b955 100%)'
    },
    {
        name: '快递运单',
        short: '运',
        desc: '寄件记录和费用核对',
        url: '/addon/hsx_recycle/pages/express/list',
        color: 'linear-gradient(135deg, #7c3aed 0%, #9f67ff 100%)'
    },
    {
        name: '数据统计',
        short: '数',
        desc: '经营数据和业务概览',
        url: '/addon/hsx_recycle/pages/stats/index',
        color: 'linear-gradient(135deg, #0369a1 0%, #38bdf8 100%)'
    }
]
</script>

<style scoped lang="scss">
.hero-card {
    background: linear-gradient(135deg, #1f5eff 0%, #5287ff 100%);
    border-radius: 24rpx;
    padding: 32rpx 28rpx;
    color: #fff;
    box-shadow: 0 20rpx 50rpx rgba(41, 121, 255, 0.18);
}

.hero-title {
    font-size: 38rpx;
    font-weight: 600;
}

.hero-subtitle {
    margin-top: 12rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.82);
}

.section-title {
    margin-bottom: 18rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #222;
}

.module-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20rpx;
}

.module-card,
.tips-card {
    background: #fff;
    border-radius: 20rpx;
    padding: 26rpx;
    box-shadow: 0 8rpx 30rpx rgba(15, 23, 42, 0.05);
}

.module-icon {
    width: 72rpx;
    height: 72rpx;
    border-radius: 18rpx;
    color: #fff;
    font-size: 30rpx;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
}

.module-name {
    margin-top: 20rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #222;
}

.module-desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    line-height: 32rpx;
    color: #777;
}

.tip-row {
    display: flex;
    flex-direction: column;
    gap: 6rpx;
    padding: 18rpx 0;
    border-bottom: 1rpx solid #f1f3f5;
}

.tip-row:last-child {
    border-bottom: 0;
    padding-bottom: 0;
}

.tip-row:first-child {
    padding-top: 0;
}

.tip-label {
    font-size: 24rpx;
    font-weight: 600;
    color: #333;
}

.tip-value {
    font-size: 24rpx;
    line-height: 36rpx;
    color: #666;
}
</style>
