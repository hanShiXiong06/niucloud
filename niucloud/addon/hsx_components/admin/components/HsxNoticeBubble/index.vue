<script lang="ts">export default { name: 'HsxNoticeBubble', inheritAttrs: false }</script>
<script setup lang="ts">
withDefaults(defineProps<{
    modelValue?: boolean
    title?: string
    content?: string
    trigger?: 'click' | 'focus' | 'hover' | 'contextmenu'
    placement?: string
    width?: string | number
    disabled?: boolean
    showArrow?: boolean
}>(), { modelValue: undefined, trigger: 'click', placement: 'bottom', width: 300, disabled: false, showArrow: true })
const emit = defineEmits<{
    (event: 'update:modelValue', value: boolean): void
    (event: 'show'): void
    (event: 'hide'): void
}>()
</script>
<template>
    <el-popover
        v-bind="$attrs"
        popper-class="hsx-notice-bubble-popper"
        :visible="modelValue"
        :trigger="trigger"
        :placement="placement as any"
        :width="width"
        :disabled="disabled"
        :show-arrow="showArrow"
        @update:visible="emit('update:modelValue', $event)"
        @show="emit('show')"
        @hide="emit('hide')"
    >
        <template #reference><slot name="reference" /></template>
        <div class="hsx-notice-bubble">
            <div v-if="title || $slots.title" class="hsx-notice-bubble__title"><slot name="title">{{ title }}</slot></div>
            <div class="hsx-notice-bubble__content"><slot>{{ content }}</slot></div>
            <div v-if="$slots.actions" class="hsx-notice-bubble__actions"><slot name="actions" /></div>
        </div>
    </el-popover>
</template>
<style scoped>
.hsx-notice-bubble__title { margin-bottom: 8px; color: var(--hsx-text-primary); font-size: 14px; font-weight: 650; }
.hsx-notice-bubble__content { color: var(--hsx-text-regular); font-size: 13px; line-height: 1.65; }
.hsx-notice-bubble__actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 12px; }
</style>
