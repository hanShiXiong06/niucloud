<template>
    <u-popup
        :show="show"
        mode="center"
        :round="20"
        :closeable="true"
        @close="handleClose"
    >
        <view class="download-config-dialog">
            <view class="dialog-header">
                <text class="header-title">下载设置</text>
                <text class="header-subtitle">首次下载需要配置分享信息</text>
            </view>

            <view class="dialog-content">
                <!-- 价格类型选择（仅会员显示） -->
                <view v-if="isMember()" class="config-section">
                    <view class="section-title">
                        <text class="title-icon">💰</text>
                        <text class="title-text">分享价格类型</text>
                    </view>
                    <view class="radio-group">
                        <view
                            class="radio-item"
                            :class="{ active: formData.priceType === 'retail' }"
                            @click="formData.priceType = 'retail'"
                        >
                            <view class="radio-icon">
                                <text v-if="formData.priceType === 'retail'" class="nc-iconfont nc-icon-xuanzhongV6xx"></text>
                                <text v-else class="nc-iconfont nc-icon-weixuanzhongV6xx"></text>
                            </view>
                            <view class="radio-content">
                                <text class="radio-label">零售价</text>
                                <text class="radio-desc">适合零售客户</text>
                            </view>
                        </view>
                        <view
                            class="radio-item"
                            :class="{ active: formData.priceType === 'wholesale' }"
                            @click="formData.priceType = 'wholesale'"
                        >
                            <view class="radio-icon">
                                <text v-if="formData.priceType === 'wholesale'" class="nc-iconfont nc-icon-xuanzhongV6xx"></text>
                                <text v-else class="nc-iconfont nc-icon-weixuanzhongV6xx"></text>
                            </view>
                            <view class="radio-content">
                                <text class="radio-label">批发价（会员价）</text>
                                <text class="radio-desc">适合批发客户</text>
                            </view>
                        </view>
                    </view>
                </view>

                <!-- 小程序链接选择 -->
                <view class="config-section">
                    <view class="section-title">
                        <text class="title-icon">🔗</text>
                        <text class="title-text">小程序分享链接</text>
                    </view>
                    <view class="switch-item">
                        <view class="switch-content">
                            <text class="switch-label">在文案中携带小程序链接</text>
                            <text class="switch-desc">链接会包含您的推广ID</text>
                        </view>
                        <u-switch
                            v-model="formData.includeLink"
                            size="24"
                            active-color="var(--primary-color)"
                        ></u-switch>
                    </view>
                </view>

                <!-- 提示信息 -->
                <view class="tip-box">
                    <text class="nc-iconfont nc-icon-tishiV6xx tip-icon"></text>
                    <text class="tip-text">这些设置会保存到本地，下次下载时自动使用</text>
                </view>
            </view>

            <view class="dialog-footer">
                <view class="footer-btn cancel-btn" @click="handleClose">
                    取消
                </view>
                <view class="footer-btn confirm-btn" @click="handleConfirm">
                    确定
                </view>
            </view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { reactive, watch } from 'vue'
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
    configured: false
})

// 监听弹窗显示，加载已保存的配置
watch(() => props.show, (newVal) => {
    if (newVal) {
        const config = getStoredConfig()
        formData.priceType = config.priceType
        formData.includeLink = config.includeLink
        formData.configured = config.configured
    }
})

// 关闭弹窗
const handleClose = () => {
    emit('close')
}

// 确认配置
const handleConfirm = () => {
    const config: DownloadConfig = {
        priceType: formData.priceType,
        includeLink: formData.includeLink,
        configured: true
    }
    emit('confirm', config)
}
</script>

<style lang="scss" scoped>
.download-config-dialog {
    width: 600rpx;
    background: #fff;
    border-radius: 20rpx;
    overflow: hidden;
}

.dialog-header {
    padding: 40rpx 30rpx 30rpx;
    text-align: center;
    border-bottom: 1rpx solid #f0f0f0;

    .header-title {
        display: block;
        font-size: 32rpx;
        font-weight: 600;
        color: #333;
        margin-bottom: 10rpx;
    }

    .header-subtitle {
        display: block;
        font-size: 24rpx;
        color: #999;
    }
}

.dialog-content {
    padding: 30rpx;
    max-height: 800rpx;
    overflow-y: auto;
}

.config-section {
    margin-bottom: 30rpx;

    &:last-child {
        margin-bottom: 0;
    }
}

.section-title {
    display: flex;
    align-items: center;
    margin-bottom: 20rpx;

    .title-icon {
        font-size: 32rpx;
        margin-right: 10rpx;
    }

    .title-text {
        font-size: 28rpx;
        font-weight: 600;
        color: #333;
    }
}

.radio-group {
    display: flex;
    flex-direction: column;
    gap: 20rpx;
}

.radio-item {
    display: flex;
    align-items: center;
    padding: 24rpx;
    background: #f8f8f8;
    border-radius: 12rpx;
    border: 2rpx solid transparent;
    transition: all 0.3s;

    &.active {
        background: var(--primary-color-light);
        border-color: var(--primary-color);
    }

    .radio-icon {
        margin-right: 16rpx;

        .nc-iconfont {
            font-size: 40rpx;
            color: var(--primary-color);
        }

        .nc-icon-weixuanzhongV6xx {
            color: #ddd;
        }
    }

    .radio-content {
        flex: 1;
        display: flex;
        flex-direction: column;
    }

    .radio-label {
        font-size: 28rpx;
        color: #333;
        font-weight: 500;
        margin-bottom: 6rpx;
    }

    .radio-desc {
        font-size: 24rpx;
        color: #999;
    }
}

.switch-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 24rpx;
    background: #f8f8f8;
    border-radius: 12rpx;

    .switch-content {
        flex: 1;
        display: flex;
        flex-direction: column;
        margin-right: 20rpx;
    }

    .switch-label {
        font-size: 28rpx;
        color: #333;
        font-weight: 500;
        margin-bottom: 6rpx;
    }

    .switch-desc {
        font-size: 24rpx;
        color: #999;
    }
}

.tip-box {
    display: flex;
    align-items: flex-start;
    padding: 20rpx;
    background: #fff7e6;
    border-radius: 12rpx;
    margin-top: 30rpx;

    .tip-icon {
        font-size: 28rpx;
        color: #faad14;
        margin-right: 10rpx;
        margin-top: 2rpx;
    }

    .tip-text {
        flex: 1;
        font-size: 24rpx;
        color: #d48806;
        line-height: 1.6;
    }
}

.dialog-footer {
    display: flex;
    border-top: 1rpx solid #f0f0f0;
}

.footer-btn {
    flex: 1;
    height: 100rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 30rpx;
    font-weight: 500;
    transition: all 0.3s;

    &:active {
        opacity: 0.7;
    }
}

.cancel-btn {
    color: #666;
    border-right: 1rpx solid #f0f0f0;
}

.confirm-btn {
    color: var(--primary-color);
    font-weight: 600;
}
</style>
