<script lang="ts">
export default { name: 'HsxActionBar' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxButton from '../HsxButton/index.vue'
import HsxIcon from '../HsxIcon/index.vue'
import type { AnyRecord, MobileActionItem } from '../../types'
import { useActionSheet } from '../../hooks/useFeedback'

const props = withDefaults(defineProps<{
    actions?: MobileActionItem[]
    context?: AnyRecord
    fixed?: boolean
    safeArea?: boolean
    bordered?: boolean
    elevated?: boolean
    gap?: string | number
    zIndex?: string | number
    maxVisible?: number
    moreText?: string
}>(), {
    actions: () => [],
    context: () => ({}),
    fixed: false,
    safeArea: true,
    bordered: true,
    elevated: false,
    gap: 10,
    zIndex: 80,
    maxVisible: 3,
    moreText: '更多'
})

const emit = defineEmits<{
    (event: 'action', action: MobileActionItem, context: AnyRecord): void
    (event: 'error', error: unknown, action: MobileActionItem): void
}>()

const visibleActions = computed(() => (props.actions || []).filter((item) => typeof item.visible === 'function' ? item.visible(props.context || {}) : item.visible !== false))
const directActions = computed(() => visibleActions.value.slice(0, Math.max(1, props.maxVisible || 3)))
const overflowActions = computed(() => visibleActions.value.slice(Math.max(1, props.maxVisible || 3)))
const toneType: Record<string, string> = { primary: 'primary', success: 'success', warning: 'warning', danger: 'error', info: 'info', default: 'default' }
const actionSheet = useActionSheet()

function isDisabled(action: MobileActionItem) {
    return Boolean(typeof action.disabled === 'function' ? action.disabled(props.context || {}) : action.disabled)
}

async function run(action: MobileActionItem) {
    if (isDisabled(action)) return
    try {
        emit('action', action, props.context || {})
        await action.action?.(props.context || {})
    } catch (error) {
        emit('error', error, action)
    }
}

async function openMore() {
    const enabledActions = overflowActions.value.filter((item) => !isDisabled(item))
    const index = await actionSheet.show(enabledActions.map((item) => item.label))
    if (index !== null && enabledActions[index]) await run(enabledActions[index])
}

function addUnit(value: string | number) {
    return typeof value === 'number' ? `${value}px` : value
}
</script>

<template>
    <view
        class="hsx-action-bar"
        :class="{
            'is-fixed': fixed,
            'has-safe-area': safeArea,
            'is-bordered': bordered,
            'is-elevated': elevated
        }"
        :style="{ gap: addUnit(gap), zIndex }"
    >
        <view v-if="$slots.left" class="hsx-action-bar__left"><slot name="left" /></view>
        <view class="hsx-action-bar__main"><slot>
            <template v-for="action in directActions" :key="String(action.key)">
                <slot :name="`action-${action.key}`" :action="action" :context="context" :run="() => run(action)">
                    <HsxButton
                        v-bind="action.props"
                        :type="action.type || toneType[action.tone || 'default']"
                        :plain="action.plain"
                        :block="action.block !== false"
                        :loading="action.loading"
                        :disabled="isDisabled(action)"
                        :haptic="action.haptic || 'light'"
                        :action="() => run(action)"
                    >
                        <HsxIcon v-if="action.icon" :name="action.icon" :size="16" />{{ action.label }}
                    </HsxButton>
                </slot>
            </template>
            <slot v-if="overflowActions.length" name="more" :actions="overflowActions" :run="run">
                <HsxButton :block="false" :action="openMore" haptic="light">
                    <HsxIcon name="more-dot-fill" :size="17" />{{ moreText }}
                </HsxButton>
            </slot>
        </slot></view>
        <view v-if="$slots.right" class="hsx-action-bar__right"><slot name="right" /></view>
    </view>
</template>

<style scoped>
.hsx-action-bar { display: flex; width: 100%; box-sizing: border-box; align-items: center; gap: 10px; padding: 12px 16px; background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-action-bar.is-fixed { position: fixed; right: 0; bottom: 0; left: 0; }
.hsx-action-bar.has-safe-area { padding-bottom: calc(12px + env(safe-area-inset-bottom)); }
.hsx-action-bar.is-bordered { border-top: 1px solid var(--hsx-mobile-border, #e6ebf2); }
.hsx-action-bar.is-elevated { box-shadow: 0 -10px 28px rgba(22, 42, 83, .1); }
.hsx-action-bar__left,
.hsx-action-bar__right { display: flex; flex: none; align-items: center; gap: inherit; }
.hsx-action-bar__main { display: flex; min-width: 0; flex: 1; align-items: stretch; justify-content: flex-end; gap: inherit; }
.hsx-action-bar__main :deep(.hsx-button) { min-width: 0; min-height: 42px; flex: 1; }
.hsx-action-bar__main :deep(.u-button) { min-height: 42px; border-radius: 11px; font-weight: 650; }
.hsx-action-bar__main :deep(.u-button__text) { display: flex; min-width: 0; align-items: center; justify-content: center; gap: 5px; white-space: nowrap; }
</style>
