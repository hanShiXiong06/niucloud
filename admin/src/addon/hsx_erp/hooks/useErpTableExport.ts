import * as XLSX from 'xlsx'

export interface ErpExportColumn {
    key: string
    label: string
    width?: number
}

export interface ErpTableExportOptions {
    title: string
    sheetName?: string
    fileName: string
    columns: ErpExportColumn[]
    rows: any[][]
    metaRows?: any[][]
    summaryRows?: any[][]
}

/**
 * ERP 配置型表格的公共导出器。
 *
 * 业务组件只负责提供白名单字段与已规范化数据；这里统一处理标题、元信息、列宽、
 * 空行和汇总行。IMEI/SN 应由调用方传字符串，金额与数量传 number，避免 Excel 丢精度或科学计数。
 */
export function exportErpConfiguredTable(options: ErpTableExportOptions) {
    const body: any[][] = [
        [options.title],
        ...(options.metaRows || []),
        [],
        options.columns.map((item) => item.label),
        ...options.rows,
        ...((options.summaryRows || []).length ? [[], ...(options.summaryRows || [])] : [])
    ]
    const worksheet = XLSX.utils.aoa_to_sheet(body)
    worksheet['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: Math.max(0, options.columns.length - 1) } }]
    worksheet['!cols'] = options.columns.map((item) => ({ wch: Math.max(10, Math.round(Number(item.width || 120) / 8)) }))
    const workbook = XLSX.utils.book_new()
    XLSX.utils.book_append_sheet(workbook, worksheet, options.sheetName || '数据明细')
    XLSX.writeFile(workbook, options.fileName)
}
