import request from '@/utils/request'

export interface QuoteImageSourceItem { id: string; name: string; group: string; url: string }
export interface QuoteImageMapping { source_id: string; source_name: string; category_id: number; enabled: number }
export interface QuoteImageRunItem { source_id: string; source_name: string; category_id: number; status: string; message: string }
export interface QuoteImageRun {
    run_id: string; status: string; message: string; total: number; done: number; updated: number; unchanged: number; failed: number
    started_at: number; finished_at: number; items: QuoteImageRunItem[]; next_run_at?: number
}
export interface QuoteImageSyncConfig {
    enabled: number; source_url: string; run_time: string; mappings: QuoteImageMapping[]
    source_catalog: QuoteImageSourceItem[]; category_options: Array<{ value: number; label: string }>
    queue_enabled: boolean; status: QuoteImageRun; recent_runs: QuoteImageRun[]; next_run_at?: number
}
export type QuoteImageSyncInput = Pick<QuoteImageSyncConfig, 'enabled' | 'source_url' | 'run_time' | 'mappings'>

const quiet = { showErrorMessage: false }
export const getQuoteImageSyncConfig = () => request.get('recycle/quote_image_sync/config', quiet)
export const saveQuoteImageSyncConfig = (data: QuoteImageSyncInput) => request.post('recycle/quote_image_sync/config', data, quiet)
export const discoverQuoteImageSources = (source_url: string) => request.post('recycle/quote_image_sync/discover', { source_url }, quiet)
export const startQuoteImageSync = () => request.post('recycle/quote_image_sync/run', {}, quiet)
export const getQuoteImageSyncStatus = () => request.get('recycle/quote_image_sync/status', quiet)
export const cancelQuoteImageSync = (run_id: string) => request.post('recycle/quote_image_sync/cancel', { run_id }, quiet)
