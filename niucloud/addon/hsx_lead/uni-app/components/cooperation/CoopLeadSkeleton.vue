<template>
    <view class="coop-lead-skeleton" aria-busy="true" aria-label="正在读取">
        <view v-if="tiles > 0" class="coop-lead-skeleton__overview">
            <view v-for="n in tiles" :key="'tile' + n" class="coop-lead-skeleton__tile">
                <view class="coop-lead-skeleton__block is-icon" />
                <view class="coop-lead-skeleton__lines">
                    <view class="coop-lead-skeleton__block is-number" />
                    <view class="coop-lead-skeleton__block is-label" />
                </view>
            </view>
        </view>
        <view class="coop-lead-skeleton__list">
            <view v-for="n in cards" :key="'card' + n" class="coop-lead-skeleton__card">
                <view class="coop-lead-skeleton__row">
                    <view class="coop-lead-skeleton__block is-title" />
                    <view class="coop-lead-skeleton__block is-tag" />
                </view>
                <view class="coop-lead-skeleton__block is-line" />
                <view class="coop-lead-skeleton__block is-line is-short" />
                <view class="coop-lead-skeleton__foot">
                    <view class="coop-lead-skeleton__block is-time" />
                    <view class="coop-lead-skeleton__block is-button" />
                </view>
            </view>
        </view>
    </view>
</template>
<script setup lang="ts">
// Placeholder with the same geometry as CoopWorkOverview + CoopLeadCard, so content replaces it in place.
withDefaults(defineProps<{ tiles?: number; cards?: number }>(), { tiles: 0, cards: 3 })
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-lead-skeleton__overview {
    display: flex;
    gap: $hsx-space-2;
    padding: $hsx-space-3 $hsx-space-3 $hsx-space-2;
}
.coop-lead-skeleton__tile {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    gap: $hsx-space-2;
    min-height: 60px;
    padding: $hsx-space-2 $hsx-space-3;
    border-radius: $hsx-radius-lg;
    background: $hsx-surface-card;
}
.coop-lead-skeleton__lines { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
.coop-lead-skeleton__list { padding: $hsx-space-3 $hsx-space-3 0; }
.coop-lead-skeleton__card {
    @include hsx-card;
    display: flex;
    flex-direction: column;
    gap: $hsx-space-2;
    margin-bottom: $hsx-space-3;
}
.coop-lead-skeleton__row,
.coop-lead-skeleton__foot { display: flex; align-items: center; justify-content: space-between; gap: $hsx-space-3; }
.coop-lead-skeleton__foot { margin-top: $hsx-space-2; padding-top: $hsx-space-3; border-top: 1px solid $hsx-border; }

.coop-lead-skeleton__block {
    height: 12px;
    border-radius: $hsx-radius-sm;
    background: linear-gradient(90deg, $hsx-border 25%, $hsx-surface-sunken 50%, $hsx-border 75%);
    background-size: 400% 100%;
    animation: coop-skeleton-shimmer 1.4s $hsx-ease-out infinite;
    &.is-icon { flex: none; width: 36px; height: 36px; border-radius: $hsx-radius-md; }
    &.is-number { width: 40%; height: 16px; }
    &.is-label { width: 70%; }
    &.is-title { width: 45%; height: 16px; }
    &.is-tag { width: 52px; height: 20px; }
    &.is-line { width: 80%; }
    &.is-short { width: 55%; }
    &.is-time { width: 30%; }
    &.is-button { width: 84px; height: 36px; border-radius: $hsx-radius-pill; }
}
@keyframes coop-skeleton-shimmer {
    from { background-position: 100% 0; }
    to { background-position: 0 0; }
}
@media (prefers-reduced-motion: reduce) {
    .coop-lead-skeleton__block { animation: none; }
}
</style>
