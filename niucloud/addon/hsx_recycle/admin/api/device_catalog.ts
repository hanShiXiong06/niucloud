import request from '@/utils/request'

export interface CatalogPolicy {
    site_id: number
    initialized: boolean
    effective: boolean
    site_enabled: boolean
    read_only: boolean
    can_manage_public: boolean
    message: string
}
export const getDeviceCatalogConfig = () => request.get('recycle/catalog/config')
export const setDeviceCatalogChoice = (enabled: boolean) => request.post('recycle/catalog/config', { enabled: enabled ? 1 : 0 })
export const previewPublicCatalog = () => request.get('recycle/platform/catalog/preview')
export const promotePublicCatalog = (fingerprint: string) => request.post('recycle/platform/catalog/promote', { fingerprint })
export const getCatalogSites = (params: Record<string, any>) => request.get('recycle/platform/catalog/sites', { params })
export const grantCatalogSite = (site_id: number, allowed: boolean) => request.post('recycle/platform/catalog/grant', { site_id, allowed: allowed ? 1 : 0 })
export interface PrintRecoveryPreview {
    restore: number
    clean: number
    blocked: number
    fingerprint: string
    items: Array<{ target_name: string; template_name: string; action: string; reason: string }>
}
export const previewCatalogPrintRecovery = () => request.get('recycle/catalog/print_recovery')
export const restoreCatalogPrintRecovery = (fingerprint: string) => request.post('recycle/catalog/print_recovery', { fingerprint })
