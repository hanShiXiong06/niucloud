import { reactive } from 'vue'

export function sourceId(value: unknown): number {
    if (typeof value !== 'string' && typeof value !== 'number') return 0
    const id = Number(value)
    return /^[1-9]\d{0,15}$/.test(String(value)) && Number.isSafeInteger(id) ? id : 0
}

// Only follow the local path and simple query supplied by an authorized ERP extension.
export function resultTarget(path: unknown, query: unknown) {
    if (typeof path !== 'string' || !/^\/(?:[a-z0-9_-]+\/)+[a-z0-9_-]+$/i.test(path) || /^\/(admin|home)\//i.test(path)) return null
    if (!query || typeof query !== 'object' || Array.isArray(query)) return null
    const params: Record<string,string> = {}
    for (const [key,value] of Object.entries(query)) {
        if (!/^[a-z][a-z0-9_]{0,40}$/i.test(key) || ['constructor','prototype'].includes(key) ||
            typeof value !== 'string' || !value || value.length > 160 || /[\u0000-\u001f]/.test(value)) return null
        params[key] = value
    }
    return Object.keys(params).length ? {path,query:params} : null
}

export function saleActionLinks(sale: any) {
    const id = sourceId(sale?.id), no = sale?.sale_no
    if (!id || typeof no !== 'string' || !no.trim() || no.length > 128 || !Array.isArray(sale.integration_actions)) return []
    return sale.integration_actions.flatMap((action: any) => {
        if (!action || typeof action.key !== 'string' || typeof action.label !== 'string' || !action.label.trim()) return []
        // Extension supplies a local page, never its own choice of sale or task.
        const target = resultTarget(action.pc_route, { sale_id: String(id), sale_no: no })
        return target ? [{ key: action.key, label: action.label, target }] : []
    })
}

export function useSourceDocument(fetchDocument: (id:number) => Promise<any>) {
    const detail = reactive({visible:false, loading:false, data:null as any, error:'', orderId:0})
    let revision = 0
    async function loadDetail(value:unknown) {
        const id = sourceId(value)
        if (!id) return
        const version = ++revision
        Object.assign(detail,{visible:true, loading:true, data:null, error:'', orderId:id})
        try {
            const res = await fetchDocument(id)
            if (version !== revision) return
            if (!res?.data || sourceId(res.data.id) !== id) throw Error('mismatch')
            detail.data = res.data
        } catch {
            if (version === revision) detail.error = '未能读取这张单据。请刷新重试，或核对本站单号和查看权限。'
        } finally {
            if (version === revision) detail.loading = false
        }
    }
    return {detail, loadDetail}
}
