export interface QuoteCategory {
    category_id: number
    site_id: number
    category_name: string
    category_full_name?: string
    images: string
    is_show?: number
    sort?: number
    parentName: string
    fullName: string
    pid?: number
    depth?: number
    ancestorIds?: number[]
    hasChildren?: boolean
}

export type QuoteCategoryFilter = 'all' | 'ready' | 'empty' | 'visible' | 'hidden'

/** 每层遵循后台 sort 降序；相同排序保留接口顺序，不按名称重排。 */
export function flattenQuoteCategories(nodes: any[], parents: string[] = [], ancestorIds: number[] = []): QuoteCategory[] {
    if (!Array.isArray(nodes)) return []
    const result: QuoteCategory[] = []
    nodes.map((node, index) => ({ node, index }))
        .filter(({ node }) => node && Number(node.category_id) > 0)
        .sort((a, b) => (Number(b.node.sort) || 0) - (Number(a.node.sort) || 0) || a.index - b.index)
        .forEach(({ node }) => {
            const id = Number(node.category_id)
            const name = String(node.category_name || '未命名分类')
            const path = [...parents, name]
            const children = Array.isArray(node.child_list) ? node.child_list : []
            result.push({
                ...node,
                category_id: id,
                site_id: Number(node.site_id),
                category_name: name,
                images: String(node.images || ''),
                parentName: parents.join(' / '),
                fullName: path.join(' / '),
                pid: ancestorIds[ancestorIds.length - 1] || 0,
                depth: ancestorIds.length,
                ancestorIds: [...ancestorIds],
                hasChildren: children.some((child: any) => child && Number(child.category_id) > 0)
            })
            result.push(...flattenQuoteCategories(children, path, [...ancestorIds, id]))
        })
    return result
}

export function filterQuoteCategories(rows: QuoteCategory[], keyword: string, status: string): QuoteCategory[] {
    const normalize = (value: string) => value.toLowerCase().replace(/\s+/g, '')
    const search = normalize(keyword.trim())
    return rows.filter(row => (!search || normalize(row.fullName).includes(search))
        && (status === 'all'
            || (status === 'visible' ? Number(row.is_show) !== 0
                : status === 'hidden' ? Number(row.is_show) === 0
                    : status === 'ready' ? Boolean(row.images) : !row.images)))
}

/** 默认按展开状态显示；搜索或筛选时自动显示命中项及祖先，保留原有树顺序与行数据。 */
export function visibleQuoteTreeRows(rows: QuoteCategory[], keyword = '', status: string = 'all', expandedIds: number[] = []): QuoteCategory[] {
    if (!Array.isArray(rows)) return []
    if (keyword.trim() || status !== 'all') {
        const includedIds = new Set<number>()
        filterQuoteCategories(rows, keyword, status).forEach(row => {
            includedIds.add(row.category_id)
            ;(row.ancestorIds || []).forEach(id => includedIds.add(id))
        })
        return rows.filter(row => includedIds.has(row.category_id))
    }
    const expanded = new Set(expandedIds)
    return rows.filter(row => (row.ancestorIds || []).every(id => expanded.has(id)))
}

export function formatQuoteTime(value: string | number | undefined): string {
    if (!value) return ''
    const date = typeof value === 'number' || /^\d+$/.test(String(value))
        ? new Date(Number(value) * 1000)
        : new Date(String(value).replace(/-/g, '/'))
    if (Number.isNaN(date.getTime())) return String(value)
    const pad = (part: number) => String(part).padStart(2, '0')
    return `${date.getFullYear()}/${pad(date.getMonth() + 1)}/${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** 上传接口异常返回非 JSON 时，框架上传 Promise 也可能不结束；只在本页兜底。 */
export function withQuoteUploadTimeout<T>(task: Promise<T>, timeout = 125000): Promise<T> {
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error('上传超时，请重试；当前报价未改变')), timeout)
        task.then(value => { clearTimeout(timer); resolve(value) }, error => { clearTimeout(timer); reject(error) })
    })
}
