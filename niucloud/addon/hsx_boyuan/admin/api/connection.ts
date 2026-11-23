import request from '@/utils/request'

export type Environment = 'test' | 'production'
export interface ConnectionProfile {
    party_phone_field?: 'contact_phone' | 'help_code'
    protocol: string; endpoint: string; gateway_path: string; account_id: string; app_id: string
    app_secret_configured: boolean; connection_key: string; probe_at: number; contract_confirmed: boolean; suggested_url: string
}
export interface ConnectionState {
    voucher_release?: { ready:boolean; enabled:boolean }
    purchase_release?: { eligible:boolean; recorded_ref:string; candidate:null|{task_id:number; test_account:string; test_bill:string} }
    exchange_views?: string[]
    interface_trace?: { window_id:string; last_window_id:string; expires_at:number; server_time:number }
    mode: Environment | 'mock'; revision: number; profiles: Record<Environment, ConnectionProfile>
    dispatching: boolean; finance_active: boolean; active_key: string; can_edit: boolean; can_probe: boolean
}
export const readConnection = () => request.get('boyuan/config', { params: { operation: 'connection_setup' } })
export const toggleInterfaceTrace = (data: Record<string, unknown>) => request.post('boyuan/config', { ...data, operation: 'connection_trace' })
export const saveConnection = (data: Record<string, unknown>) => request.post('boyuan/config', { ...data, operation: 'connection_setup' })
export const verifyConnection = (data: Record<string, unknown>) => request.post('boyuan/probe', { ...data, operation: 'connection_setup' })
export const pauseConnection = (data: Record<string, unknown>) => request.post('boyuan/config', { ...data, operation: 'connection_pause' })
export const enableManualPurchase = (data: Record<string, unknown>) => request.post('boyuan/config', { ...data, operation: 'production_manual_purchase' })
export const setManualVouchers = (data: Record<string, unknown>) => request.post('boyuan/config', { ...data, operation: 'production_vouchers' })
