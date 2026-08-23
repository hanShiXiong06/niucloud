<script lang="ts">
export default { name: 'HsxSplitPane' }
</script>

<script setup lang="ts">
import { computed, watch } from 'vue'
import { useAdaptiveContext } from '../../hooks/useAdaptiveLayout'

type PaneName = 'primary' | 'secondary'

const props = withDefaults(
    defineProps<{
        modelValue?: PaneName
        splitAt?: number
        viewportWidth?: number
        primaryWidth?: string | number
        gap?: number
        hasSecondary?: boolean
        preserve?: boolean
        bordered?: boolean
    }>(),
    {
        modelValue: 'primary',
        splitAt: 840,
        primaryWidth: 360,
        gap: 20,
        hasSecondary: true,
        preserve: true,
        bordered: true
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: PaneName): void
    (event: 'mode-change', value: 'single' | 'split'): void
    (event: 'pane-change', value: PaneName): void
}>()

const layout = useAdaptiveContext()
const width = computed(() => props.viewportWidth || layout.windowWidth.value)
const isSplit = computed(() => width.value >= props.splitAt)
const activePane = computed<PaneName>(() => props.modelValue === 'secondary' && props.hasSecondary ? 'secondary' : 'primary')
const toSize = (value: string | number) => typeof value === 'number' ? `${value}px` : value
const rootStyle = computed(() => ({ gap: isSplit.value ? `${Math.max(0, props.gap)}px` : '0' }))
const primaryStyle = computed(() => isSplit.value
    ? { width: toSize(props.primaryWidth), flex: `0 0 ${toSize(props.primaryWidth)}` }
    : { width: '100%' })

const setPane = (pane: PaneName) => {
    if (pane === 'secondary' && !props.hasSecondary) return
    emit('update:modelValue', pane)
    emit('pane-change', pane)
}
const showPrimary = () => setPane('primary')
const showSecondary = () => setPane('secondary')

watch(isSplit, (value, previous) => {
    if (value !== previous) emit('mode-change', value ? 'split' : 'single')
})

defineExpose({ isSplit, showPrimary, showSecondary })
</script>

<template>
    <view
        class="hsx-split-pane"
        :class="[
            isSplit ? 'hsx-split-pane--split' : 'hsx-split-pane--single',
            bordered && isSplit ? 'hsx-split-pane--bordered' : ''
        ]"
        :style="rootStyle"
    >
        <view
            v-show="isSplit || activePane === 'primary'"
            class="hsx-split-pane__primary"
            :style="primaryStyle"
        >
            <slot
                name="primary"
                :is-split="isSplit"
                :active-pane="activePane"
                :show-secondary="showSecondary"
            />
        </view>

        <view
            v-if="preserve || isSplit || activePane === 'secondary'"
            v-show="isSplit || activePane === 'secondary'"
            class="hsx-split-pane__secondary"
        >
            <slot
                v-if="hasSecondary"
                name="secondary"
                :is-split="isSplit"
                :active-pane="activePane"
                :show-primary="showPrimary"
            />
            <slot v-else name="empty" :is-split="isSplit">
                <view class="hsx-split-pane__empty"><text>请选择一条记录查看详情</text></view>
            </slot>
        </view>
    </view>
</template>

<style scoped lang="scss">
.hsx-split-pane {
    display: flex;
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

.hsx-split-pane--single {
    flex-direction: column;
}

.hsx-split-pane__primary,
.hsx-split-pane__secondary {
    min-width: 0;
    box-sizing: border-box;
}

.hsx-split-pane__secondary {
    flex: 1;
}

.hsx-split-pane--bordered .hsx-split-pane__primary {
    padding-right: 20px;
    border-right: 1px solid var(--hsx-mobile-border, #e6ebf2);
}

.hsx-split-pane__empty {
    display: flex;
    min-height: 240px;
    align-items: center;
    justify-content: center;
    color: var(--hsx-mobile-text-secondary, #8a8f99);
    font-size: 14px;
}
</style>
