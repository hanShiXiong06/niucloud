<template>
    <div ref="el" class="md-view"></div>
</template>

<script setup lang="ts">
import { ref, watch, onMounted, nextTick } from 'vue'
import Vditor from 'vditor'
import 'vditor/dist/index.css'

const props = defineProps<{ content: string }>()
const el = ref<HTMLElement | null>(null)

const render = () => {
    if (!el.value) return
    try {
        Vditor.preview(el.value as HTMLDivElement, props.content || '', {
            mode: 'light',
            hljs: { enable: true, style: 'github', lineNumber: false },
            // sanitize:false 允许渲染 <details> 折叠等内联 HTML（内容来自自家模型，可信）
            markdown: { toc: false, mark: true, footnotes: false, autoSpace: true, sanitize: false } as any,
            speech: { enable: false },
            anchor: 0,
        } as any)
    } catch (e) {
        // 渲染失败兜底为纯文本
        if (el.value) el.value.textContent = props.content || ''
    }
}

onMounted(render)
watch(() => props.content, () => nextTick(render))
</script>

<style lang="scss" scoped>
.md-view {
    font-size: 14px;
    line-height: 1.7;
    color: #303133;
    word-break: break-word;
}

.md-view :deep(p) {
    margin: 6px 0;
}

.md-view :deep(ul),
.md-view :deep(ol) {
    padding-left: 20px;
    margin: 6px 0;
}

.md-view :deep(h1),
.md-view :deep(h2),
.md-view :deep(h3),
.md-view :deep(h4) {
    margin: 12px 0 6px;
    font-weight: 600;
}

.md-view :deep(table) {
    border-collapse: collapse;
    margin: 8px 0;
    width: 100%;
}

.md-view :deep(th),
.md-view :deep(td) {
    border: 1px solid #ebeef5;
    padding: 6px 10px;
    font-size: 13px;
}

.md-view :deep(th) {
    background: #f5f7fa;
}

.md-view :deep(code) {
    background: #f5f7fa;
    padding: 1px 5px;
    border-radius: 3px;
    font-size: 13px;
}

.md-view :deep(blockquote) {
    border-left: 3px solid #dcdfe6;
    padding-left: 10px;
    color: #909399;
    margin: 8px 0;
}

.md-view :deep(details) {
    border: 1px solid #ebeef5;
    border-radius: 6px;
    padding: 6px 10px;
    margin: 8px 0;
    background: #fff;
}

.md-view :deep(summary) {
    cursor: pointer;
    font-weight: 500;
    color: #409eff;
    outline: none;
    user-select: none;
}

.md-view :deep(details[open] summary) {
    margin-bottom: 6px;
    border-bottom: 1px dashed #ebeef5;
    padding-bottom: 6px;
}
</style>
