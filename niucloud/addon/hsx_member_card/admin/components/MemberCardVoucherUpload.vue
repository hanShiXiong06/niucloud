<template>
    <div>
        <upload-image v-model="value" :limit="3" width="72px" height="72px" image-text="上传凭证" />
        <div class="mt-[7px] text-xs leading-5 text-gray-400">选填，最多 3 张；可用于留存转账或收款凭证。</div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ modelValue?: string }>(), { modelValue: '' })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>()
const value = computed({
    get: () => props.modelValue,
    set: next => emit('update:modelValue', String(next || '')),
})
</script>
