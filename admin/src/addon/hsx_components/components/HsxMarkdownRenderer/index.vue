<script lang="ts">
export default { name: 'HsxMarkdownRenderer' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import MarkdownIt from 'markdown-it'
import hljs from 'highlight.js/lib/core'
import bash from 'highlight.js/lib/languages/bash'
import css from 'highlight.js/lib/languages/css'
import javascript from 'highlight.js/lib/languages/javascript'
import json from 'highlight.js/lib/languages/json'
import php from 'highlight.js/lib/languages/php'
import typescript from 'highlight.js/lib/languages/typescript'
import xml from 'highlight.js/lib/languages/xml'
import 'highlight.js/styles/github.css'

const props = withDefaults(defineProps<{ content?: string }>(), { content: '' })

hljs.registerLanguage('bash', bash)
hljs.registerLanguage('shell', bash)
hljs.registerLanguage('css', css)
hljs.registerLanguage('javascript', javascript)
hljs.registerLanguage('js', javascript)
hljs.registerLanguage('json', json)
hljs.registerLanguage('php', php)
hljs.registerLanguage('typescript', typescript)
hljs.registerLanguage('ts', typescript)
hljs.registerLanguage('html', xml)
hljs.registerLanguage('xml', xml)

function escapeHtml(value: string) {
    return value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
}

const markdown: MarkdownIt = new MarkdownIt({
    html: false,
    linkify: true,
    breaks: true,
    typographer: true,
    highlight(code, language) {
        if (language && hljs.getLanguage(language)) {
            return `<pre class="hljs"><code>${hljs.highlight(code, { language }).value}</code></pre>`
        }
        return `<pre class="hljs"><code>${escapeHtml(code)}</code></pre>`
    }
})

const defaultLinkOpen = markdown.renderer.rules.link_open
const renderLinkOpen: MarkdownIt.Renderer.RenderRule = (tokens, index, options, env, self) => {
    const hrefIndex = tokens[index].attrIndex('href')
    const href = hrefIndex >= 0 ? String(tokens[index].attrs?.[hrefIndex]?.[1] || '') : ''
    if (!/^(https?:\/\/|\/)/i.test(href)) tokens[index].attrSet('href', '#')
    tokens[index].attrSet('target', '_blank')
    tokens[index].attrSet('rel', 'noopener noreferrer nofollow')
    return defaultLinkOpen ? defaultLinkOpen(tokens, index, options, env, self) : self.renderToken(tokens, index, options)
}
markdown.renderer.rules.link_open = renderLinkOpen

const html = computed(() => markdown.render(props.content || ''))
</script>

<template><div class="hsx-markdown" v-html="html" /></template>

<style scoped>
.hsx-markdown { min-width: 0; color: #303133; font-size: 14px; line-height: 1.75; overflow-wrap: anywhere; }
.hsx-markdown :deep(p) { margin: 0 0 9px; }
.hsx-markdown :deep(p:last-child) { margin-bottom: 0; }
.hsx-markdown :deep(h1), .hsx-markdown :deep(h2), .hsx-markdown :deep(h3), .hsx-markdown :deep(h4) { margin: 16px 0 8px; color: #17191c; line-height: 1.4; }
.hsx-markdown :deep(h1) { font-size: 20px; }
.hsx-markdown :deep(h2) { font-size: 17px; }
.hsx-markdown :deep(h3), .hsx-markdown :deep(h4) { font-size: 15px; }
.hsx-markdown :deep(ul), .hsx-markdown :deep(ol) { margin: 8px 0; padding-left: 22px; }
.hsx-markdown :deep(li) { margin: 4px 0; }
.hsx-markdown :deep(a) { color: var(--el-color-primary); text-decoration: none; }
.hsx-markdown :deep(a:hover) { text-decoration: underline; }
.hsx-markdown :deep(blockquote) { margin: 10px 0; padding: 8px 12px; border-left: 3px solid #409eff; background: #f5f8fc; color: #606266; }
.hsx-markdown :deep(code:not(pre code)) { padding: 2px 5px; border-radius: 4px; background: #f2f4f7; color: #c03639; font-size: 12px; }
.hsx-markdown :deep(pre) { max-width: 100%; margin: 10px 0; padding: 13px 15px; border: 1px solid #e4e7ed; border-radius: 6px; overflow: auto; background: #f7f8fa; }
.hsx-markdown :deep(table) { display: block; width: 100%; margin: 10px 0; border-collapse: collapse; overflow-x: auto; }
.hsx-markdown :deep(th), .hsx-markdown :deep(td) { padding: 8px 10px; border: 1px solid #dcdfe6; text-align: left; white-space: nowrap; }
.hsx-markdown :deep(th) { background: #f5f7fa; color: #303133; font-weight: 600; }
</style>
