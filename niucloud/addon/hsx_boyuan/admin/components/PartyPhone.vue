<template>
    <span v-if="needsSync" class="muted">需重新读取</span>
    <button v-else-if="value != null && value !== ''" v-copy="value" class="number" type="button" title="复制电话" :aria-label="'复制电话：'+value">{{ value }}</button>
    <span v-else class="muted">未填写</span>
</template>
<script setup lang="ts">
import { copyDirective as vCopy } from '@/addon/hsx_components/directives/copy'
defineProps<{value?:string|null,needsSync?:boolean}>()
</script>
<style scoped>
.number{padding:0;border:0;background:none;color:inherit;font:inherit;font-variant-numeric:tabular-nums;cursor:copy;user-select:text;white-space:pre-wrap;overflow-wrap:anywhere;text-align:left}
.number:hover,.number:focus-visible{text-decoration:underline}.muted{color:var(--el-text-color-secondary)}
</style>
