<template>
    <div class="voucher-upload">
        <upload-image v-model="localValue" :limit="3" width="72px" height="72px" image-text="上传凭证" />
        <div class="voucher-tip">选填。可上传转账截图、收款截图或其他资金凭证，最多 3 张；记录会随资金流水长期保留。</div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{ modelValue?: string }>(), { modelValue: '' })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void }>()
const localValue = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', String(value || '')) })
</script>

<style scoped>
.voucher-tip { margin-top:7px; color:#94a3b8; font-size:12px; line-height:1.55; }
</style>
