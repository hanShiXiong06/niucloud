<script lang="ts">export default { name: 'HsxActionBar' }</script>

<script setup lang="ts">
import { computed } from 'vue'
import HsxButton from '../HsxButton/index.vue'
import HsxIcon from '../HsxIcon/index.vue'
import { useFeedback } from '../../hooks/useFeedback'
import type { AnyRecord, HsxActionConfirm, HsxActionItem } from '../../types'

const props = withDefaults(defineProps<{
    actions?: HsxActionItem[]
    context?: AnyRecord
    align?: 'left' | 'center' | 'right' | 'between'
    size?: 'large' | 'default' | 'small'
    maxVisible?: number
    moreText?: string
    wrap?: boolean
    bordered?: boolean
    sticky?: boolean
    loading?: boolean
}>(), {
    actions: () => [],
    context: () => ({}),
    align: 'right',
    size: 'default',
    maxVisible: 4,
    moreText: '更多',
    wrap: true,
    bordered: false,
    sticky: false,
    loading: false
})

const emit = defineEmits<{
    (event: 'action', action: HsxActionItem, context: AnyRecord): void
    (event: 'error', error: unknown, action: HsxActionItem): void
}>()
const feedback = useFeedback()

const visibleActions = computed(() => props.actions.filter((item) => typeof item.visible === 'function' ? item.visible(props.context) : item.visible !== false))
const directActions = computed(() => visibleActions.value.slice(0, Math.max(0, props.maxVisible)))
const moreActions = computed(() => visibleActions.value.slice(Math.max(0, props.maxVisible)))

function isDisabled(item: HsxActionItem) {
    return props.loading || item.loading || (typeof item.disabled === 'function' ? item.disabled(props.context) : item.disabled)
}

function confirmOptions(action: HsxActionItem): Required<HsxActionConfirm> {
    const configured = typeof action.confirm === 'object' ? action.confirm : {}
    return {
        title: configured.title || '确认操作',
        message: typeof action.confirm === 'string' ? action.confirm : configured.message || `确认执行“${action.label}”吗？`,
        confirmText: configured.confirmText || '确认',
        cancelText: configured.cancelText || '取消',
        type: configured.type || (action.type === 'danger' ? 'error' : 'warning')
    }
}

async function run(action: HsxActionItem) {
    if (isDisabled(action)) return
    try {
        if (action.confirm) {
            const options = confirmOptions(action)
            const confirmed = await feedback.confirm({
                message: options.message,
                title: options.title,
                confirmText: options.confirmText,
                cancelText: options.cancelText,
                type: options.type
            })
            if (!confirmed) return
        }
        emit('action', action, props.context)
        await action.action?.(props.context)
    } catch (error: unknown) {
        emit('error', error, action)
    }
}

function runByKey(key: string | number) {
    const action = moreActions.value.find((item) => item.key === key)
    if (action) void run(action)
}
</script>

<template>
    <div
        class="hsx-action-bar"
        :class="[
            `hsx-action-bar--${align}`,
            { 'is-wrap': wrap, 'is-bordered': bordered, 'is-sticky': sticky }
        ]"
    >
        <div v-if="$slots.left" class="hsx-action-bar__left"><slot name="left" :context="context" /></div>
        <div class="hsx-action-bar__main"><slot :context="context">
            <template v-for="action in directActions" :key="action.key">
                <slot :name="`action-${action.key}`" :action="action" :context="context" :run="() => run(action)">
                    <HsxButton
                        v-bind="action.props"
                        :size="size"
                        :type="action.type === 'default' ? undefined : action.type"
                        :plain="action.plain"
                        :link="action.link"
                        :round="action.round"
                        :loading="loading || action.loading"
                        :disabled="isDisabled(action)"
                        :permission="action.permission"
                        @click="run(action)"
                    >
                        <HsxIcon v-if="action.icon" :name="action.icon" :size="size === 'small' ? 14 : 16" />
                        {{ action.label }}
                    </HsxButton>
                </slot>
            </template>
            <el-dropdown v-if="moreActions.length" trigger="click" @command="runByKey">
                <el-button :size="size" :disabled="loading">
                    {{ moreText }}<HsxIcon name="element ArrowDown" :size="13" />
                </el-button>
                <template #dropdown>
                    <el-dropdown-menu>
                        <el-dropdown-item v-for="action in moreActions" :key="action.key" :command="action.key" :disabled="isDisabled(action)">
                            <HsxIcon v-if="action.icon" :name="action.icon" :size="14" />{{ action.label }}
                        </el-dropdown-item>
                    </el-dropdown-menu>
                </template>
            </el-dropdown>
        </slot></div>
        <div v-if="$slots.right" class="hsx-action-bar__right"><slot name="right" :context="context" /></div>
    </div>
</template>

<style scoped>
.hsx-action-bar { display: flex; width: 100%; min-width: 0; box-sizing: border-box; align-items: center; gap: var(--hsx-space-3, 12px); }
.hsx-action-bar.is-wrap .hsx-action-bar__main { flex-wrap: wrap; }
.hsx-action-bar.is-bordered { padding: 12px 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-lg); background: var(--hsx-bg-surface); }
.hsx-action-bar.is-sticky { position: sticky; z-index: 20; bottom: 0; padding: 12px 16px; border-top: 1px solid var(--hsx-border-color); background: color-mix(in srgb, var(--hsx-bg-surface) 92%, transparent); backdrop-filter: blur(14px); }
.hsx-action-bar__left,
.hsx-action-bar__right,
.hsx-action-bar__main { display: flex; min-width: 0; align-items: center; gap: 8px; }
.hsx-action-bar__main { flex: 1; }
.hsx-action-bar--left .hsx-action-bar__main { justify-content: flex-start; }
.hsx-action-bar--center .hsx-action-bar__main { justify-content: center; }
.hsx-action-bar--right .hsx-action-bar__main { justify-content: flex-end; }
.hsx-action-bar--between .hsx-action-bar__main { justify-content: space-between; }
:deep(.el-button + .el-button) { margin-left: 0; }
:deep(.el-button > span) { gap: 5px; }
</style>
