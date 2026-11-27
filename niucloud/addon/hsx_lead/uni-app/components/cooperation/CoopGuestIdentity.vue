<template>
    <view class="coop-guest-identity">
        <button class="coop-guest-identity__top" hover-class="is-pressed" aria-label="登录或注册" @tap="emit('login')">
            <view class="coop-guest-identity__avatar"><u-icon name="account-fill" size="30" color="currentColor" /></view>
            <view class="coop-guest-identity__body">
                <text class="coop-guest-identity__title">{{ title }}</text>
                <text class="coop-guest-identity__hint">{{ hint }}</text>
            </view>
            <view class="coop-guest-identity__arrow"><u-icon name="arrow-right" size="14" color="currentColor" /></view>
        </button>
        <view class="coop-guest-identity__values" aria-label="登录后可以使用的功能">
            <view v-for="item in values" :key="item.label" class="coop-guest-identity__value">
                <view class="coop-guest-identity__value-icon"><u-icon :name="item.icon" size="20" color="currentColor" /></view>
                <text>{{ item.label }}</text>
            </view>
        </view>
        <button class="coop-guest-identity__cta" hover-class="is-pressed" @tap="emit('login')">立即登录</button>
        <text class="coop-guest-identity__note">登录后可创建门店，或用负责人分享的主体码加入已有门店</text>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
// Logged-out state of the "我的门店" identity block. Same position, padding and DIY colours as
// CoopMemberIdentity, so signing in swaps the content without the header jumping.
const props = withDefaults(defineProps<{ appearance?: Record<string, any> }>(), { appearance: () => ({}) })
const emit = defineEmits<{ (event: 'login'): void }>()
const text = (key: string, fallback: string) => (typeof props.appearance[key] === 'string' && props.appearance[key].trim()) || fallback
const title = computed(() => text('guestTitle', '登录 / 注册'))
const hint = computed(() => text('guestHint', '登录后管理门店、承接客户线索'))
const values = [
    { icon: 'edit-pen', label: '发布客户需求' },
    { icon: 'bag', label: '承接门店订单' },
    { icon: 'rmb-circle', label: '协作收益到账' }
]
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

// DIY variables come from the admin's appearance settings; tokens are the fallback (spec §1 exception for DIY).
.coop-guest-identity {
    padding: var(--lead-diy-content-padding, #{$hsx-space-4});
    color: var(--lead-diy-text, #{$hsx-text-secondary});
}
.coop-guest-identity__top {
    @include hsx-button-reset;
    display: flex;
    align-items: center;
    gap: $hsx-space-3;
    width: 100%;
    min-height: 64px;
    text-align: left;
    border-radius: $hsx-radius-md;
    transition: opacity $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { opacity: .72; }
}
.coop-guest-identity__avatar {
    flex: none;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: $hsx-radius-pill;
    background: $hsx-accent-soft;
    color: $hsx-accent;
}
.coop-guest-identity__body {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.coop-guest-identity__title {
    font-size: var(--lead-diy-title-size, #{$hsx-font-title});
    font-weight: $hsx-weight-bold;
    line-height: $hsx-line-height-tight;
    color: var(--lead-diy-title, #{$hsx-text-primary});
}
.coop-guest-identity__hint {
    font-size: $hsx-font-body-sm;
    line-height: $hsx-line-height;
    color: var(--lead-diy-subtle, #{$hsx-text-secondary});
}
.coop-guest-identity__arrow {
    flex: none;
    display: flex;
    color: var(--lead-diy-subtle, #{$hsx-text-muted});
}
.coop-guest-identity__values {
    display: flex;
    gap: $hsx-space-2;
    margin-top: $hsx-space-4;
    padding-top: $hsx-space-4;
    border-top: 1px solid var(--lead-diy-border, #{$hsx-border});
}
.coop-guest-identity__value {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    font-size: $hsx-font-caption;
    line-height: $hsx-line-height-tight;
    text-align: center;
    color: var(--lead-diy-text, #{$hsx-text-secondary});
}
.coop-guest-identity__value-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: $hsx-radius-pill;
    background: $hsx-accent-soft;
    color: $hsx-accent;
}
.coop-guest-identity__cta {
    @include hsx-button-primary;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    margin-top: $hsx-space-5;
    transition: transform $hsx-duration-fast $hsx-ease-out, opacity $hsx-duration-fast $hsx-ease-out;
    &.is-pressed { transform: scale(.98); opacity: .9; }
}
.coop-guest-identity__note {
    display: block;
    margin-top: $hsx-space-3;
    font-size: $hsx-font-caption;
    line-height: $hsx-line-height;
    text-align: center;
    color: var(--lead-diy-subtle, #{$hsx-text-muted});
}
</style>
