const mobilePages: Record<string, string> = {
    workbench: 'dashboard/index', purchase: 'purchase/list', purchase_return: 'purchase_return/list',
    sale: 'sale/list', sale_return: 'sale_return/list', stock: 'stock/list',
    stocktake: 'stocktake/list', payable: 'payable/list', receivable: 'receivable/list'
}

export function erpMobileDestination(path: string, userAgent: string): string | null {
    // iPads and Android tablets keep the desktop workspace, regardless of orientation.
    if (!/iPhone|iPod|Android.*Mobile/i.test(userAgent)) return null
    const page = path.match(/\/site\/hsx_erp\/([^/?]+)\/?$/)?.[1]
    return page && mobilePages[page] ? `/adminapp/addon/hsx_erp/pages/${mobilePages[page]}` : null
}

export function erpTableHeight(viewport: number, top: number, footer = 58): number {
    return Math.max(240, Math.floor(viewport - Math.max(0, top) - footer))
}

export function erpHeaderPreferenceKey(page: string, siteId: number, uid: number): string {
    return `hsx_erp:workspace-header:v1:${siteId}:${uid}:${encodeURIComponent(page)}`
}

export function readErpHeaderCollapsed(storage: Pick<Storage, 'getItem'> | undefined, key: string, width: number, height: number): boolean {
    try {
        const saved = storage?.getItem(key)
        if (saved === '1' || saved === '0') return saved === '1'
    } catch { /* Layout remains usable when browser storage is unavailable. */ }
    return width <= 1100 || height <= 800
}

export function writeErpHeaderCollapsed(storage: Pick<Storage, 'setItem'> | undefined, key: string, collapsed: boolean): void {
    try { storage?.setItem(key, collapsed ? '1' : '0') } catch { /* Keep the in-memory choice. */ }
}
