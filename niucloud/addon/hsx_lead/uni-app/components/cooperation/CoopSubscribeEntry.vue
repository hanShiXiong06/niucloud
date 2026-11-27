<template>
    <button
        class="coop-subscribe-entry"
        hover-class="is-pressed"
        :aria-busy="loading"
        aria-label="订阅交付与成交进度通知"
        @tap="emit('subscribe')"
    >
        <view class="coop-subscribe-entry__icon"><u-icon name="bell" size="16" color="currentColor" /></view>
        <text class="coop-subscribe-entry__text">及时接收交付与成交进度</text>
        <text class="coop-subscribe-entry__action">{{ loading ? '准备中…' : '订阅通知' }}</text>
        <u-icon v-if="!loading" name="arrow-right" size="12" color="currentColor" />
    </button>
</template>
<script setup lang="ts">
// Subscription prompt for work-order notices. Accepting re-enables delivery of notices WeChat held back.
withDefaults(defineProps<{ loading?: boolean }>(), { loading: false })
const emit = defineEmits<{ (event: 'subscribe'): void }>()
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-subscribe-entry {
    @include hsx-button-reset;
    display: flex;
    align-items: center;
    gap: $hsx-space-2;
    width: auto;
    min-height: $hsx-tap-min;
    margin: 0 $hsx-space-3;
    padding: 0 $hsx-space-3;
    border-radius: $hsx-radius-md;
    background: $hsx-warning-soft;
    color: $hsx-warning-text;
    text-align: left;
    transition: opacity $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { opacity: .8; }
}
.coop-subscribe-entry__icon { flex: none; display: flex; }
.coop-subscribe-entry__text {
    @include hsx-ellipsis;
    flex: 1;
    min-width: 0;
    font-size: $hsx-font-body-sm;
    line-height: $hsx-line-height-tight;
}
.coop-subscribe-entry__action {
    flex: none;
    font-size: $hsx-font-body-sm;
    font-weight: $hsx-weight-bold;
    white-space: nowrap;
}
</style>
