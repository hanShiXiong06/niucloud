<script lang="ts">export default { name: 'HsxProgress' }</script>
<script setup lang="ts">
import { computed } from 'vue'
type Tone = 'primary' | 'success' | 'warning' | 'danger'
const props = withDefaults(defineProps<{ percentage?: number, label?: string, description?: string, tone?: Tone, autoTone?: boolean, height?: number, showValue?: boolean }>(), { percentage: 0, label: '', description: '', tone: 'primary', autoTone: false, height: 12, showValue: true })
const normalized = computed(() => Math.max(0, Math.min(100, Number(props.percentage) || 0)))
const actualTone = computed<Tone>(() => props.autoTone ? (normalized.value >= 80 ? 'success' : normalized.value >= 40 ? 'warning' : 'danger') : props.tone)
</script>
<template>
    <view class="hsx-mobile-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="normalized">
        <view v-if="label || description || showValue" class="hsx-mobile-progress__meta"><view><text v-if="label" class="hsx-mobile-progress__label">{{ label }}</text><text v-if="description" class="hsx-mobile-progress__desc">{{ description }}</text></view><text v-if="showValue" class="hsx-mobile-progress__value">{{ normalized }}%</text></view>
        <view class="hsx-mobile-progress__track" :style="{ height: `${height}rpx` }"><view class="hsx-mobile-progress__bar" :class="`hsx-mobile-progress__bar--${actualTone}`" :style="{ width: `${normalized}%` }" /></view>
    </view>
</template>
<style scoped lang="scss">
.hsx-mobile-progress__meta { display: flex; align-items: flex-end; justify-content: space-between; gap: 20rpx; margin-bottom: 12rpx; } .hsx-mobile-progress__meta > view { display: flex; min-width: 0; flex-direction: column; }
.hsx-mobile-progress__label { color: var(--hsx-mobile-text-primary); font-size: 26rpx; font-weight: 600; } .hsx-mobile-progress__desc { overflow: hidden; margin-top: 4rpx; color: var(--hsx-mobile-text-secondary); font-size: 22rpx; text-overflow: ellipsis; white-space: nowrap; } .hsx-mobile-progress__value { color: var(--hsx-mobile-text-regular); font-size: 22rpx; font-variant-numeric: tabular-nums; }
.hsx-mobile-progress__track { overflow: hidden; border-radius: 999rpx; background: var(--hsx-mobile-bg-muted); } .hsx-mobile-progress__bar { height: 100%; border-radius: inherit; background: var(--hsx-mobile-primary); transition: width var(--hsx-mobile-motion-normal) var(--hsx-mobile-ease); }
.hsx-mobile-progress__bar--success { background: var(--hsx-mobile-success); } .hsx-mobile-progress__bar--warning { background: var(--hsx-mobile-warning); } .hsx-mobile-progress__bar--danger { background: var(--hsx-mobile-danger); }
</style>

