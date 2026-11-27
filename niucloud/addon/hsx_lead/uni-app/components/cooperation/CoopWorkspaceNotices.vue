<template>
    <view v-if="hasAny" class="coop-workspace-notices">
        <view v-if="blocked" class="coop-workspace-notice is-danger" role="alert"
            >合作已停止。历史交付与已形成收益仍保留，请到“我的”查看原因。</view
        >
        <view v-else-if="!enabled" class="coop-workspace-notice is-warning" role="status"
            >本站尚未开启商户协作，发单前请联系平台完成配置。</view
        >
        <view v-else-if="needsMembership" class="coop-workspace-notice is-accent is-action" role="status">
            <text>当前会员尚无此项协作权限</text>
            <button class="coop-workspace-notice__action" @tap="emit('membership')">查看会员权益</button>
        </view>
        <view v-if="paymentPending" class="coop-workspace-notice is-accent is-action" role="status">
            <text>{{ paymentMessage || '上笔支付结果待核对，不要重复下单' }}</text>
            <button class="coop-workspace-notice__action" @tap="emit('resume-payment')">核对 / 继续支付</button>
        </view>
        <view v-if="writeError" class="coop-workspace-notice is-danger" role="alert">{{ writeError }}</view>
        <view v-if="error" class="coop-workspace-notice is-danger" role="alert">{{ error }}</view>
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
// One place for the workspace's page-level states (previously duplicated in the list and the panel views).
const props = withDefaults(
    defineProps<{
        blocked?: boolean
        enabled?: boolean
        needsMembership?: boolean
        paymentPending?: boolean
        paymentMessage?: string
        writeError?: string
        error?: string
    }>(),
    { blocked: false, enabled: true, needsMembership: false, paymentPending: false, paymentMessage: '', writeError: '', error: '' }
)
const emit = defineEmits<{ (event: 'membership'): void; (event: 'resume-payment'): void }>()
const hasAny = computed(
    () => props.blocked || !props.enabled || props.needsMembership || props.paymentPending || !!props.writeError || !!props.error
)
</script>
<style scoped lang="scss">
@import '../../styles/tokens.scss';

.coop-workspace-notices {
    display: flex;
    flex-direction: column;
    gap: $hsx-space-2;
    padding: $hsx-space-3 $hsx-space-3 0;
}
.coop-workspace-notice {
    &.is-danger { @include hsx-notice-bar(danger); }
    &.is-warning { @include hsx-notice-bar(warning); }
    &.is-accent { @include hsx-notice-bar(accent); }
    &.is-action {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: $hsx-space-3;
        padding-top: 0;
        padding-bottom: 0;
        > text { flex: 1; min-width: 0; padding: $hsx-space-2 0; }
    }
}
.coop-workspace-notice__action {
    @include hsx-button-reset;
    flex: none;
    min-height: $hsx-tap-min;
    line-height: $hsx-tap-min;
    font-size: $hsx-font-body-sm;
    font-weight: $hsx-weight-bold;
    color: $hsx-accent-text;
    white-space: nowrap;
}
</style>
