<template>
    <span v-if="isRemark" class="excel-remark-cell" :title="String(value || '-')">{{ value || '-' }}</span>
    <div
        v-else-if="editable"
        :class="['inline-price-input', `is-${saveState || 'idle'}`, trend ? `price-${trend}` : '']"
    >
        <input
            class="inline-price-native"
            type="text"
            inputmode="decimal"
            :value="value"
            :aria-label="label"
            @blur="handleBlur"
            @keydown.enter="handleEnter"
        />
        <span v-if="saveState && saveState !== 'idle' && saveState !== 'dirty'" class="inline-save-indicator" aria-hidden="true" />
    </div>
    <span v-else class="empty-price-cell">-</span>
</template>

<script lang="ts" setup>

defineProps<{
    value: string | number
    label: string
    editable: boolean
    isRemark?: boolean
    saveState?: string
    trend?: '' | 'up' | 'down'
}>()

const emit = defineEmits<{
    (event: 'update', value: string): void
    (event: 'flush'): void
}>()

const handleBlur = (event: FocusEvent) => {
    emit('update', (event.currentTarget as HTMLInputElement).value)
    emit('flush')
}

const handleEnter = (event: KeyboardEvent) => {
    const input = event.currentTarget as HTMLInputElement
    input.blur()
}
</script>
