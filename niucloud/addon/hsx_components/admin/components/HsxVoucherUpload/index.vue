<script lang="ts">
export default { name: 'HsxVoucherUpload' }
</script>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    modelValue?: string
    limit?: number
    width?: string | number
    height?: string | number
    imageText?: string
    tip?: string
    required?: boolean
    disabled?: boolean
    compact?: boolean
}>(), {
    modelValue: '',
    limit: 3,
    width: 72,
    height: 72,
    imageText: '上传凭证',
    tip: '可上传转账截图、收款截图或其他资金凭证，记录会随业务流水长期保留。',
    required: false,
    disabled: false,
    compact: false
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'change', value: string): void
}>()

const value = computed({
    get: () => props.modelValue,
    set: (next: string) => emit('update:modelValue', String(next || ''))
})
const imageCount = computed(() => String(props.modelValue || '').split(',').filter(Boolean).length)
const widthValue = computed(() => typeof props.width === 'number' ? `${props.width}px` : props.width)
const heightValue = computed(() => typeof props.height === 'number' ? `${props.height}px` : props.height)

function handleChange(next: string) {
    emit('change', String(next || ''))
}
</script>

<template>
    <div class="hsx-voucher-upload" :class="{ 'is-disabled': disabled, 'is-compact': compact }">
        <div class="hsx-voucher-upload__header">
            <div class="hsx-voucher-upload__label">
                <slot name="label">业务凭证</slot>
                <span v-if="required" class="hsx-voucher-upload__required">*</span>
                <span v-else class="hsx-voucher-upload__optional">选填</span>
            </div>
            <span class="hsx-voucher-upload__count">{{ imageCount }}/{{ limit }}</span>
        </div>
        <div class="hsx-voucher-upload__control">
            <upload-image
                v-model="value"
                :limit="limit"
                :width="widthValue"
                :height="heightValue"
                :image-text="imageText"
                @change="handleChange"
            />
        </div>
        <div v-if="tip || $slots.tip" class="hsx-voucher-upload__tip">
            <slot name="tip">{{ tip }}</slot>
        </div>
    </div>
</template>

<style scoped>
.hsx-voucher-upload { min-width: 0; }
.hsx-voucher-upload__header { display: flex; align-items: center; justify-content: space-between; gap: var(--hsx-space-3); margin-bottom: var(--hsx-space-2); }
.hsx-voucher-upload__label { display: flex; min-width: 0; align-items: center; gap: var(--hsx-space-1); color: var(--hsx-text-regular); font-size: var(--hsx-font-size-body); font-weight: 600; line-height: var(--hsx-line-height-body); }
.hsx-voucher-upload__required { color: var(--hsx-color-danger); }
.hsx-voucher-upload__optional { padding: 1px 6px; border-radius: var(--hsx-radius-full); background: var(--hsx-bg-muted); color: var(--hsx-text-secondary); font-size: var(--hsx-font-size-caption); font-weight: 400; }
.hsx-voucher-upload__count { flex: none; color: var(--hsx-text-secondary); font-size: var(--hsx-font-size-caption); }
.hsx-voucher-upload__control { min-width: 0; }
.hsx-voucher-upload__tip { margin-top: var(--hsx-space-2); color: var(--hsx-text-secondary); font-size: var(--hsx-font-size-caption); line-height: var(--hsx-line-height-caption); }
.hsx-voucher-upload.is-disabled { opacity: .62; }
.hsx-voucher-upload.is-disabled .hsx-voucher-upload__control { pointer-events: none; }
.hsx-voucher-upload.is-compact .hsx-voucher-upload__header { margin-bottom: var(--hsx-space-1); }
.hsx-voucher-upload.is-compact .hsx-voucher-upload__tip { margin-top: var(--hsx-space-1); }
</style>
