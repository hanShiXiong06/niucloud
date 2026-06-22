<template>
    <u-popup :show="show" mode="center" :round="28" :closeable="false" @close="handleClose">
        <view class="dl-dialog">
            <!-- 渐变头图 -->
            <view class="dl-hero">
                <view class="dl-hero-icon">
                    <text class="nc-iconfont nc-icon-fenxiangV6xx"></text>
                </view>
                <text class="dl-hero-title">分享下载设置</text>
                <text class="dl-hero-sub">下载商品图到相册,一键转发朋友圈</text>
                <text class="dl-close nc-iconfont nc-icon-guanbiV6xx" @click="handleClose"></text>
            </view>

            <view class="dl-body">
                <!-- 价格类型(仅会员) -->
                <view v-if="isMember()" class="dl-block">
                    <view class="dl-block-title">
                        <text class="dl-dot"></text>
                        <text>分享价格</text>
                    </view>
                    <view class="dl-price-grid">
                        <view class="dl-price-card" :class="{ active: formData.priceType === 'retail' }" @click="formData.priceType = 'retail'">
                            <text class="dl-check nc-iconfont nc-icon-wanchengV6xx" v-if="formData.priceType === 'retail'"></text>
                            <text class="dl-price-name">零售价</text>
                            <text class="dl-price-desc">面向零售客户</text>
                        </view>
                        <view class="dl-price-card" :class="{ active: formData.priceType === 'wholesale' }" @click="formData.priceType = 'wholesale'">
                            <text class="dl-check nc-iconfont nc-icon-wanchengV6xx" v-if="formData.priceType === 'wholesale'"></text>
                            <text class="dl-price-name">批发价</text>
                            <text class="dl-price-desc">会员价 · 面向批发</text>
                        </view>
                    </view>
                </view>

                <!-- 加价转发(仅零售价) -->
                <view v-if="formData.priceType === 'retail'" class="dl-block">
                    <view class="dl-block-title">
                        <text class="dl-dot"></text>
                        <text>加价转发</text>
                    </view>
                    <view class="dl-switch-card">
                        <view class="dl-switch-text">
                            <text class="dl-switch-label">在我看到的价上加价</text>
                            <text class="dl-switch-desc">例:看到¥1000,加¥300 → 转发卖¥1300</text>
                        </view>
                        <u-switch v-model="formData.markupEnabled" size="22" active-color="var(--primary-color)" @change="onMarkupToggle"></u-switch>
                    </view>
                    <view v-if="formData.markupEnabled" class="dl-price-input">
                        <text class="dl-price-symbol">+￥</text>
                        <input class="dl-price-field" type="digit" v-model="formData.markupAmount" placeholder="每件加价金额" placeholder-class="dl-ph" />
                    </view>
                    <view v-if="formData.markupEnabled" class="dl-warn">
                        <text class="nc-iconfont nc-icon-tishiV6xx"></text>
                        <text>加价后链接自动禁用,仅复制文字转发。</text>
                    </view>
                </view>

                <!-- 小程序链接 -->
                <view class="dl-block">
                    <view class="dl-block-title">
                        <text class="dl-dot"></text>
                        <text>小程序链接</text>
                    </view>
                    <view class="dl-switch-card" :class="{ 'dl-disabled': linkDisabled }">
                        <view class="dl-switch-text">
                            <text class="dl-switch-label">文案携带商城链接</text>
                            <text class="dl-switch-desc">{{ linkDisabled ? '加价已开启,链接不可用' : '开启后复制的商品信息会带上小程序链接' }}</text>
                        </view>
                        <u-switch v-model="formData.includeLink" size="22" active-color="var(--primary-color)" :disabled="linkDisabled"></u-switch>
                    </view>
                </view>

                <view class="dl-tip">
                    <text class="nc-iconfont nc-icon-tishiV6xx"></text>
                    <text>设置会保存在本地,下次下载自动沿用</text>
                </view>
            </view>

            <view class="dl-footer">
                <view class="dl-btn ghost" @click="handleClose">取消</view>
                <view class="dl-btn primary" @click="handleConfirm">确定并下载</view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { reactive, watch, computed } from 'vue'
import { useDownloadConfig, type DownloadConfig } from '@/addon/phone_shop/hooks/useDownloadConfig'

interface Props {
    show: boolean
}

interface Emits {
    (e: 'close'): void
    (e: 'confirm', config: DownloadConfig): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const { getStoredConfig, isMember } = useDownloadConfig()

// 表单数据
const formData = reactive<DownloadConfig>({
    priceType: 'retail',
    includeLink: false,
    configured: false,
    markupEnabled: false,
    markupAmount: ''
})

// 加价开启时禁用"携带链接"
const linkDisabled = computed(() => !!formData.markupEnabled)

// 开/关加价:开启时强制关闭链接
const onMarkupToggle = (val: boolean) => {
    if (val) formData.includeLink = false
}

// 监听弹窗显示，加载已保存的配置
watch(() => props.show, (newVal) => {
    if (newVal) {
        const config = getStoredConfig()
        formData.priceType = config.priceType
        formData.includeLink = config.includeLink
        formData.configured = config.configured
        formData.markupEnabled = !!config.markupEnabled
        formData.markupAmount = config.markupAmount || ''
    }
})

// 关闭弹窗
const handleClose = () => {
    emit('close')
}

// 确认配置
const handleConfirm = () => {
    const enabled = !!formData.markupEnabled && parseFloat(String(formData.markupAmount || 0)) > 0
    const config: DownloadConfig = {
        priceType: formData.priceType,
        includeLink: enabled ? false : formData.includeLink,
        configured: true,
        markupEnabled: enabled,
        markupAmount: enabled ? (formData.markupAmount || '') : ''
    }
    emit('confirm', config)
}
</script>

<style lang="scss" scoped>
.dl-dialog {
    width: 640rpx;
    background: #fff;
    border-radius: 28rpx;
    overflow: hidden;
}

/* 渐变头图 */
.dl-hero {
    position: relative;
    padding: 48rpx 40rpx 40rpx;
    text-align: center;
    background-image: linear-gradient(135deg, var(--primary-color) 0%, rgba(0, 0, 0, 0.12) 140%);
    color: #fff;
}
.dl-hero-icon {
    width: 96rpx;
    height: 96rpx;
    margin: 0 auto 18rpx;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.22);
    display: flex;
    align-items: center;
    justify-content: center;

    .nc-iconfont {
        font-size: 48rpx;
        color: #fff;
    }
}
.dl-hero-title {
    display: block;
    font-size: 34rpx;
    font-weight: 700;
    letter-spacing: 1rpx;
}
.dl-hero-sub {
    display: block;
    margin-top: 10rpx;
    font-size: 24rpx;
    color: rgba(255, 255, 255, 0.85);
}
.dl-close {
    position: absolute;
    top: 28rpx;
    right: 30rpx;
    font-size: 36rpx;
    color: rgba(255, 255, 255, 0.85);
}

/* 主体 */
.dl-body {
    padding: 36rpx 40rpx 10rpx;
    max-height: 820rpx;
    overflow-y: auto;
}
.dl-block {
    margin-bottom: 34rpx;
}
.dl-block-title {
    display: flex;
    align-items: center;
    margin-bottom: 20rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #1d2129;

    .dl-dot {
        width: 8rpx;
        height: 28rpx;
        border-radius: 6rpx;
        background: var(--primary-color);
        margin-right: 14rpx;
    }
}

/* 价格卡片 */
.dl-price-grid {
    display: flex;
    gap: 20rpx;
}
.dl-price-card {
    position: relative;
    flex: 1;
    padding: 28rpx 24rpx;
    border-radius: 18rpx;
    background: #f6f8fa;
    border: 2rpx solid transparent;
    transition: all 0.25s;

    &.active {
        background: var(--primary-color-light, rgba(0, 0, 0, 0.03));
        border-color: var(--primary-color);
    }
}
.dl-check {
    position: absolute;
    top: 16rpx;
    right: 18rpx;
    font-size: 30rpx;
    color: var(--primary-color);
}
.dl-price-name {
    display: block;
    font-size: 30rpx;
    font-weight: 700;
    color: #1d2129;
}
.dl-price-desc {
    display: block;
    margin-top: 10rpx;
    font-size: 22rpx;
    color: #86909c;
}

/* 开关卡片 */
.dl-switch-card {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 28rpx 24rpx;
    border-radius: 18rpx;
    background: #f6f8fa;
}
.dl-switch-text {
    flex: 1;
    margin-right: 20rpx;
    display: flex;
    flex-direction: column;
}
.dl-switch-label {
    font-size: 28rpx;
    font-weight: 500;
    color: #1d2129;
}
.dl-switch-desc {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #86909c;
    line-height: 1.5;
}

/* 自定义价格输入 */
.dl-price-input {
    display: flex;
    align-items: center;
    margin-top: 18rpx;
    padding: 0 24rpx;
    height: 88rpx;
    border-radius: 16rpx;
    border: 2rpx solid var(--primary-color);
    background: #fff;
}
.dl-price-symbol {
    font-size: 34rpx;
    font-weight: 700;
    color: var(--primary-color);
    margin-right: 10rpx;
}
.dl-price-field {
    flex: 1;
    height: 88rpx;
    font-size: 34rpx;
    font-weight: 700;
    color: #1d2129;
}
.dl-ph {
    font-size: 26rpx;
    font-weight: 400;
    color: #c0c4cc;
}
.dl-warn {
    display: flex;
    align-items: flex-start;
    margin-top: 16rpx;
    padding: 16rpx 20rpx;
    background: #fff7e8;
    border-radius: 14rpx;
    font-size: 22rpx;
    color: #d48806;
    line-height: 1.5;

    .nc-iconfont {
        font-size: 26rpx;
        margin-right: 8rpx;
        margin-top: 2rpx;
        flex-shrink: 0;
    }
}
.dl-disabled {
    opacity: 0.5;
}

/* 提示 */
.dl-tip {
    display: flex;
    align-items: center;
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #a9aeb8;

    .nc-iconfont {
        font-size: 26rpx;
        margin-right: 8rpx;
    }
}

/* 底部按钮 */
.dl-footer {
    display: flex;
    gap: 20rpx;
    padding: 20rpx 40rpx 40rpx;
}
.dl-btn {
    height: 88rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9999rpx;
    font-size: 30rpx;
    font-weight: 600;
    transition: all 0.2s;

    &:active {
        opacity: 0.85;
        transform: scale(0.98);
    }
}
.dl-btn.ghost {
    flex: 0 0 200rpx;
    color: #4e5969;
    background: #f2f3f5;
}
.dl-btn.primary {
    flex: 1;
    color: #fff;
    background: var(--primary-color);
    box-shadow: 0 10rpx 24rpx var(--primary-color-light, rgba(0, 0, 0, 0.12));
}
</style>
