<template>
    <view class="markdown-content">
        <template v-for="(block, index) in blocks" :key="`${block.type}_${index}`">
            <view v-if="block.type === 'code'" class="markdown-code">
                <view class="code-head"><text>{{ block.language || '文本' }}</text><view class="copy-code" @click="copy(block.text)"><u-icon name="file-text" size="14" color="#667085" /><text>复制</text></view></view>
                <scroll-view scroll-x class="code-scroll"><text selectable class="code-text">{{ block.text }}</text></scroll-view>
            </view>
            <view v-else-if="block.type === 'list'" class="markdown-list">
                <view v-for="(item, itemIndex) in block.items" :key="itemIndex" class="list-row">
                    <text class="list-mark">{{ block.ordered ? `${itemIndex + 1}.` : '•' }}</text>
                    <text selectable class="list-text"><text v-for="(token, tokenIndex) in inline(item)" :key="tokenIndex" :class="`inline-${token.type}`">{{ token.text }}</text></text>
                </view>
            </view>
            <scroll-view v-else-if="block.type === 'table'" scroll-x :show-scrollbar="false" class="markdown-table-scroll">
                <view class="markdown-table" :style="{ minWidth: `${Math.max(2, block.headers?.length || 0) * 190}rpx` }">
                    <view class="table-row table-head">
                        <view v-for="(cell, cellIndex) in block.headers" :key="cellIndex" class="table-cell" :class="`align-${block.alignments?.[cellIndex] || 'left'}`">
                            <text selectable><text v-for="(token, tokenIndex) in inline(cell)" :key="tokenIndex" :class="`inline-${token.type}`">{{ token.text }}</text></text>
                        </view>
                    </view>
                    <view v-for="(row, rowIndex) in block.rows" :key="rowIndex" class="table-row">
                        <view v-for="(_, cellIndex) in block.headers" :key="cellIndex" class="table-cell" :class="`align-${block.alignments?.[cellIndex] || 'left'}`">
                            <text selectable><text v-for="(token, tokenIndex) in inline(row[cellIndex] || '')" :key="tokenIndex" :class="`inline-${token.type}`">{{ token.text }}</text></text>
                        </view>
                    </view>
                </view>
            </scroll-view>
            <view v-else-if="block.type === 'quote'" class="markdown-quote"><text selectable>{{ block.text }}</text></view>
            <view v-else :class="['markdown-line', block.type, block.level ? `heading-${block.level}` : '']">
                <text selectable><text v-for="(token, tokenIndex) in inline(block.text)" :key="tokenIndex" :class="`inline-${token.type}`">{{ token.text }}</text></text>
            </view>
        </template>
    </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

type InlineToken = { type: 'text' | 'strong' | 'code'; text: string }
type MarkdownBlock = { type: string; text: string; level?: number; language?: string; ordered?: boolean; items?: string[]; headers?: string[]; rows?: string[][]; alignments?: string[] }

const props = defineProps<{ content?: string }>()

const blocks = computed<MarkdownBlock[]>(() => {
    const lines = String(props.content || '').replace(/\r\n?/g, '\n').split('\n')
    const result: MarkdownBlock[] = []
    let paragraph: string[] = []
    let list: MarkdownBlock | null = null
    let code: string[] | null = null
    let language = ''
    const flushParagraph = () => {
        if (!paragraph.length) return
        result.push({ type: 'paragraph', text: paragraph.join('\n') })
        paragraph = []
    }
    const flushList = () => {
        if (!list) return
        result.push(list)
        list = null
    }
    for (let lineIndex = 0; lineIndex < lines.length; lineIndex += 1) {
        const line = lines[lineIndex]
        const fence = line.match(/^\s*```([^`]*)$/)
        if (fence) {
            if (code === null) {
                flushParagraph(); flushList(); code = []; language = fence[1].trim().slice(0, 24)
            } else {
                result.push({ type: 'code', text: code.join('\n'), language }); code = null; language = ''
            }
            continue
        }
        if (code !== null) { code.push(line); continue }
        if (line.includes('|') && lineIndex + 1 < lines.length && isTableSeparator(lines[lineIndex + 1])) {
            flushParagraph(); flushList()
            const headers = splitTableRow(line)
            const separators = splitTableRow(lines[lineIndex + 1])
            const alignments = separators.map((cell) => cell.startsWith(':') && cell.endsWith(':') ? 'center' : (cell.endsWith(':') ? 'right' : 'left'))
            const rows: string[][] = []
            lineIndex += 2
            while (lineIndex < lines.length && lines[lineIndex].trim() && lines[lineIndex].includes('|')) {
                rows.push(splitTableRow(lines[lineIndex]))
                lineIndex += 1
            }
            lineIndex -= 1
            result.push({ type: 'table', text: '', headers, rows, alignments })
            continue
        }
        const heading = line.match(/^\s*(#{1,4})\s+(.+)$/)
        if (heading) {
            flushParagraph(); flushList()
            result.push({ type: 'heading', level: heading[1].length, text: heading[2].trim() })
            continue
        }
        const unordered = line.match(/^\s*[-*+]\s+(.+)$/)
        const ordered = line.match(/^\s*\d+[.)]\s+(.+)$/)
        if (unordered || ordered) {
            flushParagraph()
            const isOrdered = Boolean(ordered)
            if (!list || list.ordered !== isOrdered) { flushList(); list = { type: 'list', text: '', ordered: isOrdered, items: [] } }
            list.items?.push(String((ordered || unordered)?.[1] || '').trim())
            continue
        }
        const quote = line.match(/^\s*>\s?(.*)$/)
        if (quote) {
            flushParagraph(); flushList(); result.push({ type: 'quote', text: quote[1] })
            continue
        }
        if (!line.trim()) { flushParagraph(); flushList(); continue }
        flushList()
        paragraph.push(line)
    }
    if (code !== null) result.push({ type: 'code', text: code.join('\n'), language })
    flushParagraph(); flushList()
    return result
})

const splitTableRow = (line: string): string[] => {
    const value = String(line || '').trim().replace(/^\|/, '').replace(/\|$/, '')
    const cells: string[] = []
    let cell = ''
    let escaped = false
    for (const char of value) {
        if (escaped) { cell += char; escaped = false; continue }
        if (char === '\\') { escaped = true; continue }
        if (char === '|') { cells.push(cell.trim()); cell = ''; continue }
        cell += char
    }
    cells.push(cell.trim())
    return cells
}
const isTableSeparator = (line: string): boolean => {
    const cells = splitTableRow(line)
    return cells.length > 0 && cells.every((cell) => /^:?-{3,}:?$/.test(cell.replace(/\s+/g, '')))
}

const inline = (text: string): InlineToken[] => {
    const value = String(text || '')
    const tokens: InlineToken[] = []
    const pattern = /(\*\*[^*\n]+\*\*|`[^`\n]+`)/g
    let cursor = 0
    let match: RegExpExecArray | null
    while ((match = pattern.exec(value)) !== null) {
        if (match.index > cursor) tokens.push({ type: 'text', text: value.slice(cursor, match.index) })
        const raw = match[0]
        tokens.push(raw.startsWith('**')
            ? { type: 'strong', text: raw.slice(2, -2) }
            : { type: 'code', text: raw.slice(1, -1) })
        cursor = match.index + raw.length
    }
    if (cursor < value.length) tokens.push({ type: 'text', text: value.slice(cursor) })
    return tokens.length ? tokens : [{ type: 'text', text: value }]
}

const copy = (text: string) => uni.setClipboardData({ data: text, success: () => uni.showToast({ title: '已复制', icon: 'none' }) })
</script>

<style lang="scss" scoped>
.markdown-content { color: inherit; font-size: 27rpx; line-height: 1.72; word-break: break-word; }
.markdown-line { margin: 10rpx 0; white-space: pre-wrap; }
.markdown-line:first-child { margin-top: 0; }.markdown-line:last-child { margin-bottom: 0; }
.heading { margin-top: 22rpx; color: #172033; font-weight: 700; }.heading-1 { font-size: 32rpx; }.heading-2 { font-size: 30rpx; }.heading-3, .heading-4 { font-size: 28rpx; }
.inline-strong { font-weight: 700; }.inline-code { padding: 2rpx 8rpx; border-radius: 4rpx; background: #eef1f5; color: #344054; font-family: monospace; }
.markdown-list { margin: 12rpx 0; }.list-row { display: flex; align-items: flex-start; margin: 8rpx 0; }.list-mark { flex: 0 0 34rpx; color: #667085; }.list-text { flex: 1; min-width: 0; }
.markdown-quote { margin: 14rpx 0; padding: 12rpx 18rpx; border-left: 5rpx solid #98a2b3; background: #f5f6f8; color: #475467; }
.markdown-table-scroll { width: 100%; margin: 16rpx 0; border: 1rpx solid #dfe3e8; border-radius: 8rpx; background: #fff; }.markdown-table { width: max-content; }.table-row { display: flex; border-top: 1rpx solid #e7eaee; }.table-row:first-child { border-top: 0; }.table-head { background: #f4f6f8; color: #344054; font-weight: 650; }.table-cell { box-sizing: border-box; width: 190rpx; min-height: 64rpx; padding: 13rpx 15rpx; border-left: 1rpx solid #e7eaee; font-size: 21rpx; line-height: 1.5; white-space: normal; word-break: break-word; }.table-cell:first-child { border-left: 0; }.align-center { text-align: center; }.align-right { text-align: right; }
.markdown-code { overflow: hidden; margin: 16rpx 0; border: 1rpx solid #dfe3e8; border-radius: 8rpx; background: #f8f9fb; }.code-head { display: flex; height: 58rpx; align-items: center; justify-content: space-between; padding: 0 18rpx; border-bottom: 1rpx solid #e5e7eb; color: #667085; font-size: 21rpx; }.copy-code { display: flex; align-items: center; gap: 6rpx; }.code-scroll { max-width: 100%; }.code-text { display: block; padding: 18rpx; color: #1f2937; font-family: monospace; font-size: 23rpx; line-height: 1.6; white-space: pre; }
</style>
