<template>
    <view class="cooperation-nav" :style="{ background }">
        <u-navbar
            :fixed="false"
            :placeholder="false"
            :safe-area-inset-top="safeAreaInsetTop"
            :auto-back="false"
            :height="navHeight"
            bg-color="transparent"
            left-icon=""
            @left-click="goBack"
        >
            <template #left>
                <view
                    v-if="back"
                    class="cooperation-nav__back"
                    :class="{ 'is-disabled': disabled }"
                    role="button"
                    :aria-label="backLabel"
                    :aria-disabled="disabled"
                >
                    <u-icon name="arrow-left" size="19" color="var(--lead-nav-text,#202124)" />
                </view>
            </template>
            <template #center>
                <view class="cooperation-nav__heading" :style="headingStyle">
                    <text class="cooperation-nav__title">{{ title }}</text>
                    <text v-if="subtitle" class="cooperation-nav__subtitle">{{ subtitle }}</text>
                </view>
            </template>
            <template #right>
                <view class="cooperation-nav__actions" :style="{ marginRight: capsuleSpace + 'px' }">
                    <slot name="actions" :disabled="disabled">
                        <button
                            v-for="item in actions"
                            :key="item.key"
                            class="cooperation-nav__action"
                            :class="{ 'has-label': !!item.label }"
                            :disabled="disabled || item.disabled || item.loading"
                            :aria-label="item.ariaLabel || item.label || item.key"
                            @tap.stop="runAction(item)"
                        >
                            <u-loading-icon v-if="item.loading" size="18" color="var(--lead-nav-text,#333)" />
                            <u-icon
                                v-else-if="item.icon"
                                :name="item.icon"
                                size="19"
                                color="var(--lead-nav-text,#333)"
                            />
                            <text v-if="item.label" class="cooperation-nav__action-label">{{ item.label }}</text>
                        </button>
                    </slot>
                </view>
            </template>
        </u-navbar>
    </view>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, useSlots } from 'vue'

interface NavAction { key: string; icon?: string; label?: string; ariaLabel?: string; disabled?: boolean; loading?: boolean }
const props = withDefaults(defineProps<{
    title: string
    subtitle?: string
    back?: boolean
    backLabel?: string
    disabled?: boolean
    actions?: NavAction[]
    /** Pixel width reserved for a custom actions slot. */
    actionsWidth?: number
    background?: string
    safeAreaInsetTop?: boolean
}>(), { subtitle: '', back: false, backLabel: '返回上一页', disabled: false, actions: () => [], actionsWidth: 44, background: 'var(--lead-nav-bg,#fff)', safeAreaInsetTop: true })
const emit = defineEmits<{ (event: 'back'): void; (event: 'action', key: string): void }>()
const slots = useSlots()
const capsuleSpace = ref(0), capsuleHeight = ref(44)
const navHeight = computed(() => Math.max(props.subtitle ? 50 : 44, capsuleHeight.value))
const actionWidth = computed(() => slots.actions ? Math.max(0, props.actionsWidth) : props.actions.reduce((width, action) => width + (action.label ? 68 : 44), 0))
const headingStyle = computed(() => ({ left: props.back ? '60px' : '16px', right: (capsuleSpace.value + (actionWidth.value ? actionWidth.value + 18 : 16)) + 'px' }))
function goBack() { if (props.back && !props.disabled) emit('back') }
function runAction(action: NavAction) { if (!props.disabled && !action.disabled && !action.loading) emit('action', action.key) }
function measureCapsule() {
    // #ifdef MP-WEIXIN
    try {
        const capsule = uni.getMenuButtonBoundingClientRect()
        const system = uni.getSystemInfoSync()
        if (capsule?.width > 0 && capsule.left > 0 && system.windowWidth >= capsule.right) {
            capsuleSpace.value = Math.max(0, system.windowWidth - capsule.left + 8)
            capsuleHeight.value = Math.max(44, capsule.bottom - (system.statusBarHeight || 0) + 4)
        }
    } catch (_) { /* No synthetic capsule on H5 or unsupported surfaces. */ }
    // #endif
}
onMounted(() => {
    measureCapsule()
    // #ifdef MP-WEIXIN
    uni.onWindowResize(measureCapsule)
    // #endif
})
onBeforeUnmount(() => {
    // #ifdef MP-WEIXIN
    uni.offWindowResize(measureCapsule)
    // #endif
})
</script>

<style scoped lang="scss">
.cooperation-nav { flex: none; width: 100%; border-bottom: 1rpx solid #eeeeee; box-sizing: border-box; }
.cooperation-nav__heading { position: absolute; min-width: 0; overflow: hidden; display: flex; flex-direction: column; justify-content: center; pointer-events: none; }
.cooperation-nav__title { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 20px; font-weight: 700; line-height: 28px; color: var(--lead-nav-text,#202124); }
.cooperation-nav__subtitle { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-top: 1px; font-size: 12px; line-height: 18px; color: #777; }
.cooperation-nav__back { width: 44px; height: 44px; display: flex; align-items: center; justify-content: flex-start; }
.cooperation-nav__back.is-disabled { opacity: .38; }
.cooperation-nav__actions { display: flex; align-items: center; flex: none; }
.cooperation-nav__action { margin: 0; padding: 0; min-width: 44px; height: 44px; border: 0; background: transparent; display: flex; align-items: center; justify-content: center; gap: 4px; border-radius: 8px; }
.cooperation-nav__action.has-label { width: 68px; max-width: 68px; padding: 0 4px; box-sizing: border-box; }
.cooperation-nav__action::after { border: 0; }
.cooperation-nav__action[disabled] { background: transparent; opacity: .45; }
.cooperation-nav__action-label { min-width: 0; max-width: 48px; color: var(--lead-nav-text,#333); font-size: 12px; line-height: 20px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden; }
</style>
