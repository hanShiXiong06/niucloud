import type { AnyRecord, HsxTreeOptions } from '../types'

function actualOptions(options: HsxTreeOptions = {}) {
    return {
        idKey: options.idKey || 'id',
        parentKey: options.parentKey || 'parent_id',
        childrenKey: options.childrenKey || 'children',
        rootValues: options.rootValues || [undefined, null, 0, '0', ''],
        keepOrphans: options.keepOrphans !== false
    }
}

export function buildTree<T extends AnyRecord>(rows: T[], options: HsxTreeOptions = {}): T[] {
    const config = actualOptions(options)
    const nodeMap = new Map<any, T>()

    rows.forEach((row) => {
        const node = { ...row, [config.childrenKey]: [] } as T
        nodeMap.set(row[config.idKey], node)
    })

    const roots: T[] = []
    rows.forEach((row) => {
        const id = row[config.idKey]
        const parentId = row[config.parentKey]
        const node = nodeMap.get(id)!
        const parent = nodeMap.get(parentId)
        const isRoot = config.rootValues.some((value) => Object.is(value, parentId))
        if (!isRoot && parent && parent !== node) {
            ;(parent[config.childrenKey] as T[]).push(node)
        } else if (isRoot || config.keepOrphans) {
            roots.push(node)
        }
    })

    // 防止自引用和循环数据让表格递归崩溃；无法从根访问到的循环节点作为独立根展示。
    const visited = new Set<any>()
    const visit = (nodes: T[], ancestors = new Set<any>()) => {
        nodes.forEach((node) => {
            const id = node[config.idKey]
            visited.add(id)
            const nextAncestors = new Set(ancestors)
            nextAncestors.add(id)
            const nodeRecord = node as AnyRecord
            const children = (nodeRecord[config.childrenKey] || []) as T[]
            nodeRecord[config.childrenKey] = children.filter((child) => !nextAncestors.has(child[config.idKey]))
            visit(nodeRecord[config.childrenKey] as T[], nextAncestors)
        })
    }
    visit(roots)
    if (config.keepOrphans) {
        nodeMap.forEach((node, id) => {
            if (!visited.has(id)) {
                roots.push(node)
                visit([node])
            }
        })
    }
    return roots
}

export function flattenTree<T extends AnyRecord>(rows: T[], childrenKey = 'children'): T[] {
    const result: T[] = []
    const walk = (nodes: T[]) => nodes.forEach((node) => {
        result.push(node)
        const children = node[childrenKey]
        if (Array.isArray(children) && children.length) walk(children)
    })
    walk(rows)
    return result
}

export function createTreeParentMap<T extends AnyRecord>(rows: T[], rowKey = 'id', childrenKey = 'children') {
    const map = new Map<any, T>()
    const walk = (nodes: T[]) => nodes.forEach((node) => {
        const children = Array.isArray(node[childrenKey]) ? node[childrenKey] as T[] : []
        children.forEach((child) => map.set(child[rowKey], node))
        walk(children)
    })
    walk(rows)
    return map
}

export function treeDescendants<T extends AnyRecord>(row: T, childrenKey = 'children'): T[] {
    const children = Array.isArray(row[childrenKey]) ? row[childrenKey] as T[] : []
    return flattenTree(children, childrenKey)
}
