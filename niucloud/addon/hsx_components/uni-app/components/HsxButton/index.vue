<script lang="ts">
export default { name: 'HsxButton' }
</script>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useHaptics } from '../../hooks/useFeedback'
import type { HsxHapticType } from '../../types'

const props = withDefaults(
    defineProps<{
        action?: (event: any) => any | Promise<any>
        autoLoading?: boolean
        debounce?: number
        loading?: boolean
        disabled?: boolean
        type?: string
        size?: string
        shape?: string
        color?: string
        plain?: boolean
        hairline?: boolean
        block?: boolean
        haptic?: HsxHapticType
    }>(),
    {
        autoLoading: true,
        debounce: 500,
        loading: false,
        disabled: false,
        type: 'default',
        size: 'normal',
        shape: 'square',
        color: '',
        plain: false,
        hairline: true,
        block: false,
        haptic: 'none'
    }
)

const emit = defineEmits<{
    (event: 'click', value: any): void
    (event: 'error', error: unknown): void
}>()

const innerLoading = ref(false)
const pressed = ref(false)
const actualLoading = computed(() => props.loading || innerLoading.value)
const actualDisabled = computed(() => props.disabled || actualLoading.value)
let lastClickAt = 0
const haptics = useHaptics()

async function handleClick(event: any) {
    const now = Date.now()
    if (actualDisabled.value || now - lastClickAt < props.debounce) return
    lastClickAt = now
    if (props.haptic !== 'none') void haptics.trigger(props.haptic)
    emit('click', event)
    if (!props.action) return

    try {
        const result = props.action(event)
        if (props.autoLoading && result instanceof Promise) {
            innerLoading.value = true
            await result
        }
    } catch (error) {
        emit('error', error)
    } finally {
        innerLoading.value = false
    }
}

function handlePressStart() {
    if (!actualDisabled.value) pressed.value = true
}

function handlePressEnd() {
    pressed.value = false
}
</script>

<template>
    <view
        class="hsx-button"
        :class="[
            `hsx-button--${type}`,
            `hsx-button--${size}`,
            {
                'hsx-button--block': block,
                'is-pressed': pressed,
                'is-disabled': actualDisabled,
                'is-loading': actualLoading
            }
        ]"
        :hover-class="actualDisabled ? 'none' : 'hsx-button--hover'"
        :hover-stay-time="80"
        role="button"
        :aria-disabled="actualDisabled"
        @click="handleClick"
        @touchstart="handlePressStart"
        @touchend="handlePressEnd"
        @touchcancel="handlePressEnd"
        @mousedown="handlePressStart"
        @mouseup="handlePressEnd"
        @mouseleave="handlePressEnd"
    >
        <u-button
            :loading="actualLoading"
            :disabled="actualDisabled"
            :type="type"
            :size="size"
            :shape="shape"
            :color="color"
            :plain="plain"
            :hairline="hairline"
        >
            <slot />
        </u-button>
    </view>
</template>

<style scoped lang="scss">
.hsx-button {
    display: inline-flex;
    max-width: 100%;
    border-radius: 10px;
    transition: opacity 120ms ease, transform 120ms ease, filter 120ms ease;
    vertical-align: middle;
}

.hsx-button :deep(.u-button) {
    width: auto;
    margin: 0;
    pointer-events: none;
    transition: box-shadow 150ms ease, filter 150ms ease;
}

.hsx-button--primary :deep(.u-button),
.hsx-button--success :deep(.u-button),
.hsx-button--warning :deep(.u-button),
.hsx-button--error :deep(.u-button) {
    box-shadow: 0 6px 14px rgba(37, 99, 235, .18);
}

.hsx-button--hover,
.hsx-button.is-pressed {
    transform: translateY(1px) scale(.975);
    filter: brightness(.96);
}

.hsx-button.is-disabled { opacity: .52; transform: none; filter: none; }

.hsx-button--block {
    display: flex;
    width: 100%;
}

.hsx-button--block :deep(.u-button) {
    width: 100%;
}
</style>
