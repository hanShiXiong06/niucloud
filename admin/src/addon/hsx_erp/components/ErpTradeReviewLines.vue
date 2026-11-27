<template>
    <div v-if="lines.length === 1" class="one-line"><b>{{ lines[0].name }}</b><span>{{ lines[0].identity }}</span></div>
    <HsxFold v-else-if="lines.length" class="review-lines" title="核对货品明细" :summary="lines.length + ' 项'">
        <ul><li v-for="(line, i) in lines" :key="i"><b>{{ line.name }}</b><span>{{ line.identity }}</span><strong>{{ line.amount }}</strong></li></ul>
    </HsxFold>
</template>
<script setup lang="ts">
import { HsxFold } from '@/addon/hsx_components/core'
defineProps<{lines: {name: string; identity: string; amount: string}[]}>()
</script>
<style scoped>
.one-line,.review-lines{margin-bottom:14px}.one-line,li{display:flex;gap:8px 16px;align-items:baseline;flex-wrap:wrap;font-size:13px}.one-line b,li b{font-weight:500}.one-line span,li span{color:var(--el-text-color-regular);overflow-wrap:anywhere}ul{padding:0;margin:0;max-height:200px;overflow:auto}li{padding:5px 0;border-bottom:1px solid var(--el-border-color-lighter)}li strong{margin-left:auto}
</style>
