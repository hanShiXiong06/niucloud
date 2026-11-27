import request from '@/utils/request'

export type CoopRow = Record<string, any>
export type Direction = 'outgoing' | 'incoming'
export type CooperationTab = 'deliveries' | 'stores' | 'wallet' | 'profile'
export const cooperationPageUrls: Record<CooperationTab, string> = {
    deliveries: '/addon/hsx_lead/pages/workbench/index',
    stores: '/addon/hsx_lead/pages/stores/index',
    wallet: '/addon/hsx_lead/pages/wallet/index',
    profile: '/addon/hsx_lead/pages/profile/index'
}
export type LeadAction = 'accept' | 'reject' | 'withdraw' | 'cancel' | 'deal' | 'no_deal'
const root = 'hsx_lead/cooperation'
const quiet = { showErrorMessage: false }
// This endpoint contains only the visitor entry policy, never merchant/private business data.
export const cooperationAccess = () => request.get(`${root}/access`, {}, quiet)
export const cooperationBootstrap = () => request.get(`${root}/bootstrap`, {}, quiet)
export const saveCooperationProfile = (data: CoopRow, existing = false) => existing ? request.put(`${root}/profile`, data, quiet) : request.post(`${root}/profile`, data, quiet)
export const getCooperationProfileForm = () => request.get(`${root}/profile-form`, {}, quiet)
export const cooperationCatalog = (keyword = '', options: { browse?: boolean | number; business_type?: string } = {}) => request.get(`${root}/catalog`, { keyword, ...options }, quiet)
export const cooperationLeads = (data: CoopRow) => request.get(`${root}/leads`, data, quiet)
export const cooperationLead = (id: number) => request.get(`${root}/leads/${id}`, {}, quiet)
export const createCooperationLead = (data: CoopRow) => request.post(`${root}/leads`, data, quiet)
export const cooperationStores = (data: CoopRow = {}, leadId = 0) => request.get(leadId ? `${root}/leads/${leadId}/stores` : `${root}/stores`, data, quiet)
export const cooperationStoreInventory = (id: number, data: CoopRow = {}) => request.get(`${root}/stores/${id}/inventory`, data, quiet)
export const assignCooperationLead = (id: number, data: CoopRow) => request.post(`${root}/leads/${id}/assign`, data, quiet)
export const actCooperationLead = (id: number, action: LeadAction, assignment_version: number, remark = '') => request.post(`${root}/leads/${id}/action`, { action, assignment_version, remark }, quiet)
export const createCooperationOrder = (data: CoopRow) => request.post(`${root}/orders`, data, quiet)
// Quote is read-only. Prices and unused-term credit are calculated by the server;
// the returned token is confirmed later when creating a new entitlement order.
export const cooperationOrder = (id: number) => request.get(`${root}/orders/${id}`, {}, quiet)
// Reconcile/close only an abandoned original cashier attempt; never creates a charge.
export const resumeCooperationOrder = (id: number) => request.post(`${root}/orders/${id}/resume`, {}, quiet)
/** Query/recover the original receipt only; never close, sign or debit a payment. */
export const checkCooperationOrderPayment = (id: number) => request.post(`${root}/orders/${id}/check`, {}, quiet)
export const cooperationOrders = (query: CoopRow) => request.get(`${root}/orders`, query, quiet)
export const closeCooperationOrder = (id: number) => request.post(`${root}/orders/${id}/close`, { confirm: true }, quiet)
export const cooperationOrderRefund = (id: number) => request.get(`${root}/orders/${id}/refund`, {}, quiet)
export const requestCooperationOrderRefund = (id: number, data: { confirm: true; reason: string; request_id: string }) => request.post(`${root}/orders/${id}/refund`, data, quiet)
// Refresh only reads the original refund identity. It must never create a new refund.
export const refreshCooperationOrderRefund = (id: number) => request.post(`${root}/orders/${id}/refund/refresh`, {}, quiet)
export const cancelCooperationOrderRefund = (id: number, request_version: string) => request.post(`${root}/orders/${id}/refund/cancel`, { request_version }, quiet)
export const cooperationWallet = (data: CoopRow = {}) => request.get(`${root}/wallet`, data, quiet)
export const uploadCooperationAttachment = (filePath: string) => request.upload(`${root}/attachments`, { filePath, name: 'file' }, quiet)
export const uploadCooperationStorefront = (filePath: string) => request.upload(`${root}/storefront`, { filePath, name: 'file' }, quiet)
export const uploadCooperationBusinessLicense = (filePath: string) => request.upload(`${root}/business-license`, { filePath, name: 'file' }, quiet)
export const previewCooperationBusinessLicense = (token: string) => request.post(`${root}/business-license/preview`, { token }, quiet)
export const cooperationConnection = () => request.get(`${root}/connection`, {}, quiet)
export const cooperationChallenge = (data: CoopRow) => request.post(`${root}/connection/challenge`, data, quiet)
export const cooperationProbe = (data: CoopRow) => request.post(`${root}/connection/probe`, data, quiet)
export const cooperationActivate = (data: CoopRow) => request.post(`${root}/connection/activate`, data, quiet)
export const cooperationConnectionSettings = (data: CoopRow) => request.put(`${root}/connection/settings`, data, quiet)
export const cooperationSync = () => request.post(`${root}/sync`, {}, quiet)
export const cooperationSyncInfo = (id: number) => request.get(`${root}/sync/${id}`, {}, quiet)
export const cooperationSyncAdvance = (id: number) => request.post(`${root}/sync/${id}/advance`, {}, quiet)

export function coopError(cause: any, fallback = '暂时未完成，请保留填写内容后重试') {
    if (cause?.msg) return String(cause.msg)
    const message = String(cause?.message || cause?.errMsg || '')
    // Keep the caller's context: an uncertain payment must not become a generic
    // "pay again" network prompt. Raw uni transport codes are not user guidance.
    if (!message || /^(?:(?:request|uploadFile|downloadFile):fail|Failed to fetch|NetworkError|Network request failed|Load failed)/i.test(message)) return fallback
    return message
}
export function coopRequestId() { return `coop_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 14)}` }
export function coopMoney(value: any) { return value == null || value === '' || !Number.isFinite(Number(value)) ? '—' : `¥${Number(value).toFixed(2)}` }
export function coopDate(value: any) { if (!value) return '尚未记录'; const date = new Date(/^\d+$/.test(String(value)) ? Number(value) * 1000 : value); return Number.isNaN(date.getTime()) ? '尚未记录' : `${date.getFullYear()}/${date.getMonth() + 1}/${date.getDate()} ${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}` }
export const businessLabels: Record<string, string> = { purchase: '二手机', new_phone: '新机', rent: '租机', exchange: '二手机置换', broadband: '宽带', repair: '手机维修', home_recycle: '二手机上门回收' }
export const coopStatusLabels: Record<string, string> = { selecting: '待选门店', assigned: '待接单', following: '跟进中', unpaid: '成交待付佣', paid: '已付佣', no_deal: '未成交', cancelled: '已取消' }
