export type CategoryStatus = 'enabled' | 'disabled' | 'unknown'
export interface CategoryRow {
    id: string; name: string; code: string; parent_id: string | null; parent_name: string | null; status: CategoryStatus
}
export interface CategoryNode extends CategoryRow {
    parent: string; depth: number; root: string; issue: string; childIds: string[]
}
export interface CategoryIndex {
    byId: Map<string, CategoryNode>; roots: CategoryNode[]; ordered: CategoryNode[]; issues: CategoryNode[]; levels: number[]
}
const collator = new Intl.Collator('zh-CN', { numeric: true })
const compare = (a: CategoryNode, b: CategoryNode) => collator.compare(a.code, b.code) || collator.compare(a.name, b.name) || a.id.localeCompare(b.id)
const rootMarker = (s: string) => s === '0' || s === '00000000-0000-0000-0000-000000000000'
export const levelName = (depth: number) => depth ? (['', '一级', '二级', '三级', '四级', '五级', '六级', '七级', '八级', '九级', '十级'][depth] || '第' + depth + '级') : '待核对'
export const statusText: Record<CategoryStatus, string> = { enabled: '启用', disabled: '停用', unknown: '未提供' }

export function indexCategories(rows: CategoryRow[]): CategoryIndex {
    const byId = new Map<string, CategoryNode>()
    for (const r of rows) {
        if (!r || typeof r.id !== 'string' || !r.id || byId.has(r.id) || typeof r.name !== 'string' || typeof r.code !== 'string'
            || (r.parent_id !== null && typeof r.parent_id !== 'string') || (r.parent_name !== null && typeof r.parent_name !== 'string')
            || !['enabled', 'disabled', 'unknown'].includes(r.status)) throw Error('分类档案不完整或编号重复，请刷新目录后重试。')
        byId.set(r.id, { ...r, parent: r.parent_id || '', depth: 0, root: '', issue: '', childIds: [] })
    }
    // Resolve each parent chain once. No name/code matching, no recursion for broken or very deep trees.
    for (const start of byId.values()) {
        if (start.depth || start.issue) continue
        const chain: CategoryNode[] = [], seen = new Set<string>()
        let n = start, depth = 0, root = '', issue = ''
        while (true) {
            if (n.depth || n.issue) { depth = n.depth; root = n.root; issue = n.issue; break }
            if (seen.has(n.id)) { issue = '上级关系循环'; break }
            chain.push(n); seen.add(n.id)
            const parent = byId.get(n.parent)
            if (!n.parent || (!parent && rootMarker(n.parent))) {
                if (n.parent_name?.trim()) issue = '有上级名称，但没有可核对的上级编号'
                else { root = n.id; n.parent = '' }
                break
            }
            if (!parent) { issue = '在本站档案中找不到上级'; break }
            n = parent
        }
        for (let i = chain.length - 1; i >= 0; i--) {
            const node = chain[i]
            if (issue) node.issue = issue
            else { node.depth = ++depth; node.root = root }
        }
    }
    const roots: CategoryNode[] = [], issues: CategoryNode[] = [], levels = new Set<number>()
    // Order by category code within each set of siblings; a code itself never determines parentage.
    for (const node of [...byId.values()].sort(compare)) {
        if (node.issue) { issues.push(node); continue }
        levels.add(node.depth)
        if (node.parent) byId.get(node.parent)!.childIds.push(node.id)
        else roots.push(node)
    }
    const index = { byId, roots, issues, levels: [...levels].sort((a, b) => a - b), ordered: [] as CategoryNode[] }
    index.ordered = [...visibleBranches(index, roots, null), ...issues]
    return index
}

export function visibleBranches(index: CategoryIndex, roots: CategoryNode[], expanded: Set<string> | null): CategoryNode[] {
    const stack = [...roots].reverse(), result: CategoryNode[] = []
    while (stack.length) {
        const node = stack.pop()!
        result.push(node)
        if (!expanded || expanded.has(node.id)) {
            for (let i = node.childIds.length - 1; i >= 0; i--) stack.push(index.byId.get(node.childIds[i])!)
        }
    }
    return result
}
export function lineage(index: CategoryIndex, item: CategoryNode): CategoryNode[] {
    if (!item.depth) return []
    const result = [item]
    while (result[result.length - 1].parent) result.push(index.byId.get(result[result.length - 1].parent)!)
    return result.reverse()
}
export function matchCategories(index: CategoryIndex, keyword: string, level: string, status: string): CategoryNode[] {
    const term = keyword.trim().toLocaleLowerCase()
    const pathQuery = /[/／›>＞]/.test(term) ? term.split(/\s*[/／›>＞]\s*/).filter(Boolean).join(' / ') : ''
    return index.ordered.filter(n => {
        if ((level && level !== String(n.depth)) || (status && status !== n.status)) return false
        if (!term) return true
        if (pathQuery && lineage(index, n).map(p => p.name.toLocaleLowerCase()).join(' / ').includes(pathQuery)) return true
        return [n.name, n.code, n.id].some(v => v.toLocaleLowerCase().includes(term))
    })
}
