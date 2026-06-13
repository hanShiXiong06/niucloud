<template>
    <view class="recycle-empty" :class="{ 'recycle-empty--compact': compact }">
        <view class="recycle-empty__icon">
            <text class="nc-iconfont" :class="iconClass"></text>
        </view>
        <text class="recycle-empty__title">{{ title }}</text>
        <text v-if="description" class="recycle-empty__desc">{{ description }}</text>
        <view v-if="$slots.action" class="recycle-empty__action">
            <slot name="action"></slot>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

/**
 * 引导式空态——对齐 admin/src/addon/hsx_recycle/components/empty-state/index.vue。
 * 用「一句引导 + 可选操作」替代干巴巴的「暂无数据」。
 *
 * @example
 * <RecycleEmptyState title="还没有回收订单" description="客户下单后将在此显示" icon="document">
 *   <template #action><u-button type="primary" size="small">去签收</u-button></template>
 * </RecycleEmptyState>
 */
type EmptyIcon = 'box' | 'document' | 'search' | 'warning' | 'list'

const props = withDefaults(defineProps<{
    title?: string
    description?: string
    icon?: EmptyIcon
    compact?: boolean
}>(), {
    title: '暂无数据',
    description: '',
    icon: 'document',
    compact: false
})

// 预置图标 → 真实 nc-iconfont 类名（均为项目内已存在的图标）
const ICON_MAP: Record<EmptyIcon, string> = {
    box: 'nc-icon-cangkushangpinshuliang',
    document: 'nc-icon-dingdanliebiaoV6xx',
    search: 'nc-icon-sousuo-duanV6xx1',
    warning: 'nc-icon-jinggaoV6xx',
    list: 'nc-icon-liebiaoV6xx'
}

const iconClass = computed(() => ICON_MAP[props.icon] || ICON_MAP.document)
</script>

<style scoped lang="scss">
.recycle-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 96rpx 48rpx;
}

.recycle-empty--compact {
    padding: 48rpx 32rpx;
}

.recycle-empty__icon {
    width: 120rpx;
    height: 120rpx;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--hsx-primary-50);
    margin-bottom: 24rpx;

    .nc-iconfont {
        font-size: 60rpx;
        color: var(--hsx-primary);
    }
}

.recycle-empty--compact .recycle-empty__icon {
    width: 88rpx;
    height: 88rpx;
    margin-bottom: 16rpx;

    .nc-iconfont {
        font-size: 44rpx;
    }
}

.recycle-empty__title {
    font-size: 28rpx;
    font-weight: 600;
    color: var(--hsx-text-strong);
    line-height: 40rpx;
}

.recycle-empty__desc {
    margin-top: 12rpx;
    font-size: 24rpx;
    line-height: 36rpx;
    color: var(--hsx-text-secondary);
    max-width: 520rpx;
}

.recycle-empty__action {
    margin-top: 32rpx;
}
</style>
