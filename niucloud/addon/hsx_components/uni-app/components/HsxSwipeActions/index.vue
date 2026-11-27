<script lang="ts">
export default { name: 'HsxSwipeActions' }
</script>

<script setup lang="ts">
import { ref } from 'vue'
import type { AnyRecord, MobileSwipeActionOption } from '../../types'
import { useHaptics } from '../../hooks/useFeedback'

const props = withDefaults(
    defineProps<{
        items?: AnyRecord[]
        rowKey?: string | ((item: AnyRecord, index: number) => string | number)
        actions?: MobileSwipeActionOption[] | ((item: AnyRecord, index: number) => MobileSwipeActionOption[])
        disabled?: boolean | ((item: AnyRecord, index: number) => boolean)
        autoClose?: boolean
        threshold?: number
        duration?: string | number
        haptic?: boolean
    }>(),
    {
        items: () => [],
        rowKey: 'id',
        actions: () => [
            { text: '编辑', name: 'edit', icon: 'edit-pen', tone: 'primary' as const },
            { text: '删除', name: 'delete', icon: 'trash-fill', tone: 'danger' as const }
        ],
        disabled: false,
        autoClose: true,
        threshold: 30,
        duration: 280,
        haptic: true
    }
)

const emit = defineEmits<{
    (event: 'action', action: MobileSwipeActionOption, item: AnyRecord, index: number): void
}>()

const itemRefs = ref<any[]>([])
const haptics = useHaptics()
const toneColors: Record<string, string> = {
    neutral: '#64748b',
    primary: '#2563eb',
    success: '#16a34a',
    warning: '#d97706',
    danger: '#ef4444'
}

function rowId(item: AnyRecord, index: number) {
    return typeof props.rowKey === 'function' ? props.rowKey(item, index) : item?.[props.rowKey] ?? index
}

function rowActions(item: AnyRecord, index: number) {
    const actions = typeof props.actions === 'function' ? props.actions(item, index) : props.actions
    return actions.map((action) => ({
        ...action,
        style: {
            color: '#fff',
            backgroundColor: toneColors[action.tone || 'neutral'],
            fontSize: '14px',
            ...(action.style || {})
        }
    }))
}

function isDisabled(item: AnyRecord, index: number) {
    return typeof props.disabled === 'function' ? props.disabled(item, index) : props.disabled
}

function onAction(detail: any, item: AnyRecord, index: number) {
    const action = rowActions(item, index)[Number(detail.index)]
    if (!action || action.disabled) return
    if (props.haptic) void (action.tone === 'danger' ? haptics.trigger('medium') : haptics.selection())
    emit('action', action, item, index)
}

function closeAll() {
    itemRefs.value.forEach((item) => item?.closeHandler?.())
}

defineExpose({ closeAll })
</script>

<template>
    <u-swipe-action :auto-close="autoClose">
        <u-swipe-action-item
            v-for="(item, index) in items"
            :key="String(rowId(item, index))"
            :ref="(value: any) => { if (value) itemRefs[index] = value }"
            :name="rowId(item, index)"
            :options="rowActions(item, index)"
            :disabled="isDisabled(item, index)"
            :auto-close="autoClose"
            :threshold="threshold"
            :duration="duration"
            @click="onAction($event, item, index)"
        >
            <view class="hsx-swipe-actions__content">
                <slot :item="item" :index="index" />
            </view>
        </u-swipe-action-item>
    </u-swipe-action>
</template>

<style scoped>
.hsx-swipe-actions__content {
    min-width: 0;
    background: var(--hsx-mobile-bg-page, #f5f7fb);
}
</style>
