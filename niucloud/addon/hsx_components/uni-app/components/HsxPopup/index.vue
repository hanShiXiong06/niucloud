<script lang="ts">
export default { name: 'HsxPopup' }
</script>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useHaptics } from '../../hooks/useFeedback'
import { useAdaptiveContext } from '../../hooks/useAdaptiveLayout'

type PopupMode = 'center' | 'top' | 'right' | 'bottom' | 'left' | 'fullscreen'
type PopupAdaptiveMode = 'none' | 'dialog' | 'side'

const props = withDefaults(
    defineProps<{
        modelValue: boolean
        mode?: PopupMode
        title?: string
        height?: string | number
        width?: string | number
        round?: string | number
        closeable?: boolean
        closeOnClickOverlay?: boolean
        safeAreaInsetBottom?: boolean
        haptic?: boolean
        adaptive?: PopupAdaptiveMode
        adaptiveAt?: number
        adaptiveWidth?: number
        adaptiveHeight?: string | number
        zIndex?: string | number
        bodyPadding?: string
        bodyScroll?: boolean
    }>(),
    {
        mode: 'bottom',
        title: '',
        height: 'auto',
        width: 'auto',
        round: 16,
        closeable: true,
        closeOnClickOverlay: true,
        safeAreaInsetBottom: true,
        haptic: false,
        adaptive: 'none',
        adaptiveAt: 600,
        adaptiveWidth: 560,
        adaptiveHeight: 'auto',
        zIndex: 10075,
        bodyPadding: '28rpx 32rpx',
        bodyScroll: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'open'): void
    (event: 'close'): void
    (event: 'confirm'): void
}>()

const show = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

const layout = useAdaptiveContext()
const isAdaptive = computed(() => props.adaptive !== 'none' && layout.windowWidth.value >= props.adaptiveAt)
const resolvedMode = computed<PopupMode>(() => {
    if (!isAdaptive.value) return props.mode
    return props.adaptive === 'side' ? 'right' : 'center'
})
const popupMode = computed(() => (resolvedMode.value === 'fullscreen' ? 'center' : resolvedMode.value))
const toSize = (value: string | number) => typeof value === 'number' ? `${value}rpx` : value
const popupStyle = computed(() => {
    const adaptiveWidth = `${Math.min(props.adaptiveWidth, Math.max(280, layout.windowWidth.value - 48))}px`
    return {
        height: toSize(isAdaptive.value ? props.adaptiveHeight : props.height),
        width: isAdaptive.value && props.width === 'auto' ? adaptiveWidth : toSize(props.width),
        '--hsx-popup-safe-bottom': `${props.safeAreaInsetBottom ? layout.safeAreaInsets.value.bottom : 0}px`
    }
})
const overlayStyle = computed(() => ({ zIndex: Math.max(0, Number(props.zIndex) - 1) }))
const bodyStyle = computed(() => ({ padding: props.bodyPadding }))
const haptics = useHaptics()

watch(show, (value, previous) => {
    if (value && !previous) {
        if (props.haptic) void haptics.selection()
        emit('open')
    }
})

function close() {
    show.value = false
    emit('close')
}

function confirm() {
    if (props.haptic) void haptics.success()
    emit('confirm')
}

defineExpose({ close, confirm })
</script>

<template>
    <u-overlay v-if="resolvedMode === 'fullscreen'" :show="show" :z-index="zIndex" @click="closeOnClickOverlay && close()">
        <view class="hsx-popup hsx-popup--fullscreen" @click.stop>
            <view v-if="title || closeable || $slots.header" class="hsx-popup__header">
                <slot name="header" :title="title" :close="close">
                    <text class="hsx-popup__title">{{ title }}</text>
                    <text v-if="closeable" class="hsx-popup__close" @click="close">×</text>
                </slot>
            </view>
            <scroll-view :scroll-y="bodyScroll" enable-flex class="hsx-popup__body hsx-popup__body--fullscreen" :class="{ 'is-static': !bodyScroll }" :style="bodyStyle">
                <slot />
                <view v-if="safeAreaInsetBottom && !$slots.footer" class="hsx-popup__safe-spacer" />
            </scroll-view>
            <view v-if="$slots.footer" class="hsx-popup__footer"><slot name="footer" :close="close" :confirm="confirm" /></view>
        </view>
    </u-overlay>

    <u-popup
        v-else
        :show="show"
        :mode="popupMode"
        :round="round"
        :closeable="closeable"
        :close-on-click-overlay="closeOnClickOverlay"
        :safe-area-inset-bottom="false"
        :z-index="zIndex"
        :overlay-style="overlayStyle"
        @close="close"
    >
        <view class="hsx-popup" :class="{ 'hsx-popup--adaptive': isAdaptive }" :style="popupStyle">
            <view v-if="title || $slots.header" class="hsx-popup__header">
                <slot name="header" :title="title" :close="close">
                    <text class="hsx-popup__title">{{ title }}</text>
                </slot>
            </view>
            <scroll-view :scroll-y="bodyScroll" enable-flex class="hsx-popup__body" :class="{ 'is-static': !bodyScroll }" :style="bodyStyle">
                <slot />
                <view v-if="safeAreaInsetBottom && !$slots.footer" class="hsx-popup__safe-spacer" />
            </scroll-view>
            <view v-if="$slots.footer" class="hsx-popup__footer"><slot name="footer" :close="close" :confirm="confirm" /></view>
        </view>
    </u-popup>
</template>

<style scoped lang="scss">
.hsx-popup {
    display: flex;
    max-height: 90vh;
    flex-direction: column;
    color: var(--hsx-mobile-text-primary, #202124);
    background: var(--hsx-mobile-bg-surface, #fff);
}

.hsx-popup--fullscreen {
    position: fixed;
    inset: 0;
    width: 100vw;
    height: 100vh;
    max-height: none;
}

.hsx-popup--adaptive {
    max-width: calc(100vw - 48px);
}

.hsx-popup__header {
    position: relative;
    display: flex;
    min-height: 96rpx;
    flex: none;
    align-items: center;
    justify-content: center;
    border-bottom: 1rpx solid var(--hsx-mobile-border, #f1f2f4);
}

.hsx-popup__title {
    color: var(--hsx-mobile-text-primary, #202124);
    font-size: 32rpx;
    font-weight: 600;
}

.hsx-popup__close {
    position: absolute;
    right: 32rpx;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: 48rpx;
    font-weight: 300;
}

.hsx-popup__body {
    width: 100%;
    height: 100%;
    min-height: 0;
    flex: 1;
    box-sizing: border-box;
}

.hsx-popup__body.is-static {
    overflow: hidden;
}

.hsx-popup__body--fullscreen {
    height: calc(100vh - 96rpx);
}

.hsx-popup__footer {
    flex: none;
    padding: 20rpx 32rpx calc(20rpx + var(--hsx-popup-safe-bottom, 0px));
    border-top: 1rpx solid var(--hsx-mobile-border, #f1f2f4);
}

.hsx-popup__safe-spacer {
    width: 100%;
    height: var(--hsx-popup-safe-bottom, 0px);
    flex: none;
}
</style>
