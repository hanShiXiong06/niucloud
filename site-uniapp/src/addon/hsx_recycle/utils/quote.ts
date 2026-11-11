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
}

/** 每层遵循后台 sort 降序；相同排序保留接口顺序，不按名称重排。 */
export function flattenQuoteCategories(nodes: any[], parents: string[] = []): QuoteCategory[] {
    if (!Array.isArray(nodes)) return []
    const result: QuoteCategory[] = []
    nodes.map((node, index) => ({ node, index }))
        .filter(({ node }) => node && Number(node.category_id) > 0)
        .sort((a, b) => (Number(b.node.sort) || 0) - (Number(a.node.sort) || 0) || a.index - b.index)
        .forEach(({ node }) => {
            const name = String(node.category_name || '未命名分类')
            const path = [...parents, name]
            result.push({
                ...node,
                category_id: Number(node.category_id),
                site_id: Number(node.site_id),
                category_name: name,
                images: String(node.images || ''),
                parentName: parents.join(' / '),
                fullName: path.join(' / ')
            })
            result.push(...flattenQuoteCategories(node.child_list, path))
        })
    return result
}

export function filterQuoteCategories(rows: QuoteCategory[], keyword: string, status: string): QuoteCategory[] {
    const normalize = (value: string) => value.toLowerCase().replace(/\s+/g, '')
    const search = normalize(keyword.trim())
    return rows.filter(row => (!search || normalize(row.fullName).includes(search))
        && (status === 'all' || (status === 'ready' ? Boolean(row.images) : !row.images)))
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
