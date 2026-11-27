<template>
    <scroll-view scroll-x class="coop-status-tabs" :class="{ 'is-disabled': disabled }">
        <view class="coop-status-tabs__inner" role="tablist" :aria-disabled="disabled">
            <button
                v-for="item in options"
                :key="item.value"
                class="coop-status-tabs__tab"
                :class="{ 'is-active': modelValue === item.value }"
                role="tab"
                :aria-selected="modelValue === item.value"
                :disabled="disabled"
                @tap="select(item.value)"
                >{{ item.label }}</button
            >
        </view>
    </scroll-view>
</template>
<script setup lang="ts">
// Horizontal status filter for the role lists (找客户 / 找门店).
const props = withDefaults(
    defineProps<{ options: { value: string; label: string }[]; modelValue: string; disabled?: boolean }>(),
    { disabled: false }
)
const emit = defineEmits<{ (event: 'change', value: string): void }>()
function select(value: string) {
    if (!props.disabled && value !== props.modelValue) emit('change', value)
}
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-status-tabs {
    width: 100%;
    white-space: nowrap;
    &.is-disabled { opacity: .55; }
}
.coop-status-tabs__inner {
    display: flex;
    align-items: stretch;
    gap: $hsx-space-1;
    width: max-content;
    padding: 0 $hsx-space-3;
}
.coop-status-tabs__tab {
    @include hsx-button-reset;
    position: relative;
    min-height: $hsx-tap-min;
    padding: 0 $hsx-space-2;
    line-height: $hsx-tap-min;
    font-size: $hsx-font-body-sm;
    color: $hsx-text-muted;
    white-space: nowrap;
    transition: color $hsx-duration-fast $hsx-ease-out;
    &::before {
        content: '';
        position: absolute;
        left: 50%;
        bottom: 4px;
        width: 16px;
        height: 3px;
        border-radius: $hsx-radius-pill;
        background: $hsx-accent;
        transform: translateX(-50%) scaleX(0);
        transition: transform $hsx-duration $hsx-ease-out;
    }
    // Native disabled buttons get a grey fill in mini programs; the group's opacity already shows the state.
    &[disabled] { background: transparent; color: $hsx-text-muted; }
    &.is-active {
        color: $hsx-text-primary;
        font-weight: $hsx-weight-bold;
        &::before { transform: translateX(-50%) scaleX(1); }
    }
}
</style>
