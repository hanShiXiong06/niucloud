<template>
    <view class="erp-swipe-action">
        <u-swipe-action>
            <u-swipe-action-item
                :show="open"
                :name="name"
                :disabled="disabled || normalizedActions.length === 0"
                :threshold="threshold"
                :duration="duration"
                :options="normalizedActions"
                :close-on-click="true"
                @update:show="value => emit('update:open', value)"
                @click="handleAction"
            >
                <slot />
            </u-swipe-action-item>
        </u-swipe-action>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type ErpSwipeAction = {
    key: string
    text?: string
    icon?: string
    color?: string
    backgroundColor?: string
    width?: string
}

const props = withDefaults(defineProps<{
    open?: boolean
    name?: string | number
    actions?: ErpSwipeAction[]
    disabled?: boolean
    threshold?: number
    duration?: number
}>(), {
    open: false,
    name: '',
    actions: () => [],
    disabled: false,
    threshold: 28,
    duration: 260,
})

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void
    (event: 'action', key: string, payload: { index: number; name: string | number }): void
}>()

const normalizedActions = computed(() => props.actions.map(action => ({
    text: action.text || '',
    icon: action.icon || '',
    iconSize: 19,
    style: {
        width: action.width || '72px',
        color: action.color || '#ffffff',
        backgroundColor: action.backgroundColor || '#ef4444',
        fontSize: '13px',
        fontWeight: '600',
    },
})))

function handleAction(payload: { index: number; name: string | number }) {
    const action = props.actions[payload.index]
    emit('update:open', false)
    if (action) emit('action', action.key, payload)
}
</script>

<style scoped lang="scss">
.erp-swipe-action {
    overflow: hidden;
    border-radius: inherit;
    background: #fff;
}
</style>
