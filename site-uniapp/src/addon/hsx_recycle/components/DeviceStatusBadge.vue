<template>
    <view class="device-status-badge-wrap">
        <view class="device-status-badge" :style="badgeStyle">
            <text class="device-status-badge__dot" :style="dotStyle"></text>
            <text class="device-status-badge__text">{{ text }}</text>
        </view>
        <text v-if="showHint && hint" class="device-status-badge__hint">{{ hint }}</text>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import {
    getDeviceStatusText,
    getDeviceNextStep,
    getDeviceStatusTokens
} from '@/addon/hsx_recycle/utils/deviceStatus'

/**
 * 设备状态徽章——对齐 admin DeviceStatusBadge。
 * 柔和药丸（圆点 + 浅底同色字），可选展示「下一步动作」提示。
 * 配色与文案统一走 utils/deviceStatus.ts，保证 PC / 移动端语义一致。
 *
 * @example
 * <DeviceStatusBadge :status="device.status" :status-name="device.status_name" show-hint />
 */
const props = withDefaults(defineProps<{
    status: number | string
    /** 后端返回的状态名，优先于内置兜底文案。 */
    statusName?: string
    /** 是否展示「下一步」动作提示（默认关闭，列表密集场景可不显示）。 */
    showHint?: boolean
}>(), {
    statusName: '',
    showHint: false
})

const tokens = computed(() => getDeviceStatusTokens(props.status))
const text = computed(() => getDeviceStatusText(props.status, props.statusName))
const hint = computed(() => getDeviceNextStep(props.status))

const badgeStyle = computed(() => `color:${ tokens.value.color };background:${ tokens.value.bg };`)
const dotStyle = computed(() => `background:${ tokens.value.color };`)
</script>

<style scoped lang="scss">
.device-status-badge-wrap {
    display: inline-flex;
    flex-direction: column;
    align-items: flex-start;
    gap: 6rpx;
}

.device-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 8rpx;
    padding: 4rpx 16rpx 4rpx 12rpx;
    border-radius: var(--hsx-radius-pill);
    font-size: 20rpx;
    line-height: 32rpx;
    white-space: nowrap;
}

.device-status-badge__dot {
    width: 10rpx;
    height: 10rpx;
    border-radius: 50%;
    flex-shrink: 0;
}

.device-status-badge__text {
    font-weight: 500;
}

.device-status-badge__hint {
    font-size: 20rpx;
    line-height: 28rpx;
    color: var(--hsx-text-placeholder);
}
</style>
