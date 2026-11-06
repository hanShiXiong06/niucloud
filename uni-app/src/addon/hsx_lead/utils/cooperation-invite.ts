import { handleOnloadParams } from '@/utils/common'

/** Use NiuCloud's mini-program scene convention, including its mid handling. */
export function cooperationInviteParams(options: any): Record<string, any> {
    if (!options || typeof options !== 'object' || Array.isArray(options)) return {}
    try {
        const parsed = handleOnloadParams(options) || {}
        // The QR scene is limited to 32 bytes. pi is the compact equivalent of
        // the ordinary direct-share query's promotion_invite flag.
        return { ...parsed, ...(String(parsed.pi || '') === '1' ? { promotion_invite: 1 } : {}) }
    } catch { return {} }
}
