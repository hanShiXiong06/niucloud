/** 商城只返回可用末级分类；这里仅整理浏览/搜索，不创建分类或猜测关联。 */
export interface MallCategoryOption {
    value: number
    label: string
    path: number[]
    names: string[]
    [key: string]: unknown
}

export interface MallCategoryNode {
    id: number
    name: string
    path: number[]
    names: string[]
    children: number[]
    leafCount: number
    option?: MallCategoryOption
}

export interface MallCategoryIndex {
    roots: number[]
    nodes: Map<number, MallCategoryNode>
    options: MallCategoryOption[]
}

function positiveId(value: unknown): number {
    if (typeof value !== 'number' && (typeof value !== 'string' || !/^[1-9][0-9]*$/.test(value))) return 0
    const id = Number(value)
    return Number.isSafeInteger(id) && id > 0 ? id : 0
}

function asOption(value: unknown): MallCategoryOption | null {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return null
    const row = value as Record<string, unknown>
    const id = positiveId(row.value)
    if (!id || typeof row.label !== 'string' || !row.label.trim()
        || !Array.isArray(row.path) || !row.path.length || !Array.isArray(row.names)
        || row.path.length !== row.names.length) return null
    const path = row.path.map(positiveId)
    if (path.some(item => item === 0) || new Set(path).size !== path.length || path[path.length - 1] !== id) return null
    if (row.names.some(name => typeof name !== 'string' || !name.trim())) return null
    return { ...row, value: id, label: row.label, path, names: [...row.names] as string[] }
}

/** 同名不同 ID 保留；同一 ID 的路径/名称冲突则都不猜测，防止点到错误分类。 */
export function buildMallCategoryIndex(source: unknown): MallCategoryIndex {
    const index: MallCategoryIndex = { roots: [], nodes: new Map(), options: [] }
    if (!Array.isArray(source)) return index
    const candidates = source.map(asOption).filter((item): item is MallCategoryOption => item !== null)
    const signatures = new Map<number, string>()
    const leafSignatures = new Map<number, string>()
    const conflicts = new Set<number>()
    const leafIds = new Set(candidates.map(item => item.value))

    for (const option of candidates) {
        const leafSignature = JSON.stringify([option.path, option.names, option.label])
        const previousLeaf = leafSignatures.get(option.value)
        if (previousLeaf !== undefined && previousLeaf !== leafSignature) conflicts.add(option.value)
        leafSignatures.set(option.value, leafSignature)
        option.path.forEach((id, depth) => {
            const signature = JSON.stringify([option.path.slice(0, depth), option.names[depth]])
            const previous = signatures.get(id)
            if (previous !== undefined && previous !== signature) conflicts.add(id)
            signatures.set(id, signature)
            if (depth < option.path.length - 1 && leafIds.has(id)) conflicts.add(id)
        })
    }

    const seenLeaves = new Set<number>()
    for (const option of candidates) {
        if (option.path.some(id => conflicts.has(id)) || seenLeaves.has(option.value)) continue
        seenLeaves.add(option.value)
        index.options.push(option)
        option.path.forEach((id, depth) => {
            let node = index.nodes.get(id)
            if (!node) {
                node = {
                    id, name: option.names[depth], path: option.path.slice(0, depth + 1),
                    names: option.names.slice(0, depth + 1), children: [], leafCount: 0,
                }
                index.nodes.set(id, node)
                const siblings = depth === 0 ? index.roots : index.nodes.get(option.path[depth - 1])!.children
                if (!siblings.includes(id)) siblings.push(id)
            }
            node.leafCount++
            if (depth === option.path.length - 1) node.option = option
        })
    }
    return index
}

function normalizedInput(value: unknown): string {
    if (typeof value !== 'string' && typeof value !== 'number') return ''
    const text = String(value)
    return (typeof text.normalize === 'function' ? text.normalize('NFKC') : text).toLowerCase()
}

/** 保留 +，避免把 Mate70 Pro+ 和 Pro 当成同一个精确搜索词。 */
export function normalizeCategorySearch(value: unknown): string {
    return normalizedInput(value).replace(/[\s\u3000\/_\-·•.,，。:：;；()（）\[\]【】|\\]+/g, '')
}

function searchTokens(query: string): string[] {
    const words = normalizedInput(query)
        .replace(/([\u3400-\u9fff])([a-z0-9])/g, '$1 $2')
        .replace(/([a-z0-9])([\u3400-\u9fff])/g, '$1 $2')
        .trim().split(/\s+/)
        .map(normalizeCategorySearch).filter(Boolean)
    return [...new Set(words)]
}

/** 全局或指定分支内搜索全部叶级；分页由 UI 控制，不截断后面的分类。 */
export function searchMallCategories(index: MallCategoryIndex, keyword: string, scopeId = 0): MallCategoryOption[] {
    const scope = scopeId === 0 ? 0 : positiveId(scopeId)
    if (scopeId !== 0 && (!scope || !index.nodes.has(scope))) return []
    const candidates = scope ? index.options.filter(option => option.path.includes(scope)) : index.options
    const compact = normalizeCategorySearch(keyword)
    const tokens = searchTokens(keyword)
    if (!tokens.length) return [...candidates]
    const matches: { option: MallCategoryOption; rank: number; order: number }[] = []
    candidates.forEach((option, order) => {
        const leaf = normalizeCategorySearch(option.names[option.names.length - 1])
        const full = normalizeCategorySearch(option.names.join(' / '))
        if (!tokens.every(token => full.includes(token))) return
        const rank = leaf === compact ? 0 : leaf.startsWith(compact) ? 1 : leaf.includes(compact) ? 2
            : tokens.every(token => leaf.includes(token)) ? 3 : full.includes(compact) ? 4 : 5
        matches.push({ option, rank, order })
    })
    return matches.sort((left, right) => left.rank - right.rank || left.order - right.order).map(item => item.option)
}

export function getMallCategoryTrail(index: MallCategoryIndex, id: number): MallCategoryNode[] {
    const node = index.nodes.get(positiveId(id))
    return node ? node.path.map(part => index.nodes.get(part)!).filter(Boolean) : []
}

export function getMallCategoryBrowseRows(index: MallCategoryIndex, parentId = 0): MallCategoryNode[] {
    const ids = parentId === 0 ? index.roots : index.nodes.get(positiveId(parentId))?.children || []
    return ids.map(id => index.nodes.get(id)!).filter(Boolean)
}

/** 打开已选叶级时浏览其父级；不会把父级当作可提交项。 */
export function getMallCategoryParentPath(index: MallCategoryIndex, leafId: number): number[] {
    const node = index.nodes.get(positiveId(leafId))
    return node?.option ? node.path.slice(0, -1) : []
}
