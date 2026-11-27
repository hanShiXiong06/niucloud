/** Guards asynchronous QR/status responses after close, refresh or employee changes. */
export function createBindingSessionGate() {
    let sequence = 0
    return {
        start: () => ++sequence,
        isCurrent: (ticket: number) => ticket === sequence,
        invalidate: () => { sequence += 1 }
    }
}

export type BindingStatus = 'waiting' | 'scanned' | 'bound' | 'expired' | 'failed'
export function bindingStatus(value: unknown): BindingStatus {
    return ['waiting', 'scanned', 'bound', 'expired', 'failed'].includes(String(value)) ? value as BindingStatus : 'failed'
}

/** Server duration + monotonic clock: wall-clock timezone/skew must never invalidate a QR. */
export function bindingDeadline(expiresIn: unknown, monotonicNow: number): number {
    const seconds = Number(expiresIn)
    if (!Number.isFinite(seconds) || seconds < 0) throw new Error('服务未返回有效的绑定剩余时间，请联系平台检查版本。')
    return monotonicNow + seconds * 1000
}

export function remainingBindingSeconds(deadline: number, monotonicNow: number): number {
    return Math.max(0, Math.ceil((Number(deadline || 0) - monotonicNow) / 1000))
}

/** A timed-out confirmation is not a failed binding; only a terminal server result resolves it. */
export function bindingOutcomeStillUncertain(status: BindingStatus, confirmationWasUncertain: boolean): boolean {
    return confirmationWasUncertain && !['bound', 'expired', 'failed'].includes(status)
}

/** Provider info exposes saved rows, not the site's `configured` flag. Never inspect unsaved input. */
export function hasSavedProviderConfiguration(data: Record<string, unknown>): boolean {
    return Number(data.id) > 0 && Number(data.enabled) === 1 && data.status !== 'preparing'
        && ['channel_code', 'provider_corp_id', 'suite_id', 'admin_miniapp_appid', 'admin_miniapp_name', 'web_base_url'].every(key => typeof data[key] === 'string' && String(data[key]).trim().length > 0)
        && ['suite_secret_configured', 'callback_token_configured', 'encoding_aes_key_configured'].every(key => Number(data[key]) === 1)
}

/** Callback readiness is a saved server result, never inferred from the editable form. */
export function hasSavedProviderCallbackPreparation(data: Record<string, unknown>): boolean {
    return Number(data.id) > 0 && (data.callback_ready === true || Number(data.callback_ready) === 1)
        && ['channel_code', 'provider_corp_id', 'web_base_url'].every(key => typeof data[key] === 'string' && String(data[key]).trim().length > 0)
        && ['callback_token_configured', 'encoding_aes_key_configured'].every(key => Number(data[key]) === 1)
}

/** An existing Suite must use the formal save path, even when currently disabled. */
export function canPrepareProviderConfiguration(data: Record<string, unknown>): boolean {
    return Number(data.enabled) !== 1 && !String(data.suite_id || '').trim() && Number(data.suite_secret_configured) !== 1
}

/** Query strings are hints only; successful authorization must be re-read from the server. */
export function readAuthorizationCallback(query: Record<string, unknown>) {
    const scalar = (value: unknown) => typeof value === 'string' ? value : ''
    const result = scalar(query.wecom_result)
    return {
        hasResult: Boolean(result),
        successHint: result === 'success' || result === 'bind_success',
        message: scalar(query.wecom_message).slice(0, 160),
        tab: ['config', 'staff', 'messages'].includes(scalar(query.tab)) ? scalar(query.tab) : 'config',
        cleanQuery: Object.fromEntries(Object.entries(query).filter(([key]) => !['wecom_result', 'wecom_message', 'tab'].includes(key)))
    }
}
