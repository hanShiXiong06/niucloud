<template>
    <el-tooltip :content="text || fallback" :disabled="!text" placement="top" :show-after="250">
        <span class="erp-overflow-text" :class="lines > 1 ? 'is-multiline' : ''" :style="styleVars">{{ text || fallback }}</span>
    </el-tooltip>
</template>

<script setup lang="ts">
import { computed } from 'vue'
const props = withDefaults(defineProps<{ text?: string | number; fallback?: string; maxWidth?: string; lines?: number }>(), { text: '', fallback: '-', maxWidth: '100%', lines: 1 })
const styleVars = computed(() => ({ maxWidth: props.maxWidth, '--erp-lines': String(Math.max(1, props.lines)) }))
</script>

<style scoped>
.erp-overflow-text { display:inline-block; overflow:hidden; min-width:0; vertical-align:bottom; text-overflow:ellipsis; white-space:nowrap; }
.erp-overflow-text.is-multiline { display:-webkit-box; white-space:normal; -webkit-box-orient:vertical; -webkit-line-clamp:var(--erp-lines); }
</style>
