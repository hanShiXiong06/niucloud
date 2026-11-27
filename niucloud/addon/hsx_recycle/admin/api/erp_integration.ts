import request from '@/utils/request'

export type RecycleErpMode = 'local' | 'self_erp'

export interface RecycleErpIntegration {
    mode: RecycleErpMode
    configured: boolean
    installed: boolean
    message: string
    changed_at: number
}

export function getRecycleErpIntegration() {
    return request.get('/recycle/recycle_order/erp-integration')
}

export function saveRecycleErpIntegration(mode: RecycleErpMode) {
    return request.put('/recycle/recycle_order/erp-integration', { mode, confirm: true })
}
