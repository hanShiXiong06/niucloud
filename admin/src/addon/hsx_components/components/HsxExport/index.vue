<script lang="ts">
export default { name: 'HsxExport', inheritAttrs: false }
</script>

<script setup lang="ts">
import { Download } from '@element-plus/icons-vue'
import HsxButton from '../HsxButton/index.vue'
import type { AnyRecord, HsxExportColumn, HsxExportContext, HsxExportResult } from '../../types'
import { deepClone, getPathValue } from '../../utils'

const props = withDefaults(
    defineProps<{
        data?: AnyRecord[]
        columns?: HsxExportColumn[]
        query?: AnyRecord
        filename?: string
        format?: 'csv' | 'json'
        exporter?: (context: HsxExportContext) => Promise<HsxExportResult> | HsxExportResult
        buttonText?: string
        includeBom?: boolean
        disabled?: boolean
        permission?: string | string[]
    }>(),
    {
        data: () => [],
        columns: () => [],
        query: () => ({}),
        filename: '数据导出',
        format: 'csv',
        buttonText: '导出',
        includeBom: true,
        disabled: false
    }
)

const emit = defineEmits<{
    (event: 'start', context: HsxExportContext): void
    (event: 'success', result: HsxExportResult, context: HsxExportContext): void
    (event: 'error', error: unknown, context: HsxExportContext): void
}>()

function actualColumns(): HsxExportColumn[] {
    if (props.columns.length) return props.columns
    return Object.keys(props.data[0] || {}).map((prop) => ({ prop, label: prop }))
}

function cellText(value: any): string {
    if (value === undefined || value === null) return ''
    if (value instanceof Date) return value.toISOString()
    if (typeof value === 'object') return JSON.stringify(value)
    return String(value)
}

function csvCell(value: any): string {
    const text = cellText(value)
    return /[",\r\n]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text
}

function localBlob(context: HsxExportContext): Blob {
    if (context.format === 'json') {
        return new Blob([JSON.stringify(context.data, null, 2)], { type: 'application/json;charset=utf-8' })
    }
    const header = context.columns.map((column) => csvCell(column.label)).join(',')
    const rows = context.data.map((row, index) => context.columns.map((column) => {
        const value = getPathValue(row, String(column.prop))
        return csvCell(column.formatter ? column.formatter(row, value, index) : value)
    }).join(','))
    const content = [header, ...rows].join('\r\n')
    return new Blob([props.includeBom ? '\uFEFF' : '', content], { type: 'text/csv;charset=utf-8' })
}

function filenameWithExtension(filename: string, format: string) {
    return filename.toLowerCase().endsWith(`.${format}`) ? filename : `${filename}.${format}`
}

function downloadUrl(url: string, filename?: string) {
    const anchor = document.createElement('a')
    anchor.href = url
    if (filename) anchor.download = filename
    anchor.style.display = 'none'
    document.body.appendChild(anchor)
    anchor.click()
    anchor.remove()
}

function downloadBlob(blob: Blob | ArrayBuffer, filename: string) {
    const target = blob instanceof Blob ? blob : new Blob([blob])
    const url = URL.createObjectURL(target)
    downloadUrl(url, filename)
    setTimeout(() => URL.revokeObjectURL(url), 1000)
}

function handleResult(result: HsxExportResult, context: HsxExportContext) {
    const fallbackName = filenameWithExtension(context.filename, context.format)
    if (result instanceof Blob || result instanceof ArrayBuffer) {
        downloadBlob(result, fallbackName)
        return
    }
    if (typeof result === 'string') {
        if (/^(https?:\/\/|\/|blob:|data:)/.test(result)) downloadUrl(result, fallbackName)
        else downloadBlob(new Blob([result], { type: 'text/plain;charset=utf-8' }), fallbackName)
        return
    }
    if (result?.url) downloadUrl(result.url, result.filename || fallbackName)
    else if (result?.blob) downloadBlob(result.blob, result.filename || fallbackName)
}

async function handleExport() {
    const context: HsxExportContext = {
        data: deepClone(props.data),
        columns: deepClone(actualColumns()),
        query: deepClone(props.query),
        filename: props.filename,
        format: props.format
    }
    emit('start', context)
    try {
        const result = props.exporter ? await props.exporter(context) : localBlob(context)
        handleResult(result, context)
        emit('success', result, context)
        return result
    } catch (error) {
        emit('error', error, context)
        return undefined
    }
}

defineExpose({ export: handleExport })
</script>

<template>
    <HsxButton
        v-bind="$attrs"
        :icon="Download"
        :action="handleExport"
        :disabled="disabled"
        :permission="permission"
    >
        <slot>{{ buttonText }}</slot>
    </HsxButton>
</template>
