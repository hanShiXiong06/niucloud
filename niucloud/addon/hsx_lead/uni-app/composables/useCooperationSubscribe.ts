import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { getWeappTemplateId } from '@/app/api/system'
import { getSiteId, getToken } from '@/utils/common'
import { coopNotice } from '../utils/notice'
import { reportCooperationNoticeAuthorization } from '../api/notice'

const noticeKeys = 'hsx_lead_task_assigned,hsx_lead_task_changed'
type SubscribeStatus =
    | 'accepted'
    | 'partial'
    | 'rejected'
    | 'cancelled'
    | 'failed'
    | 'unconfigured'
    | 'preparing'
    | 'unsupported'
    | 'login_required'
    | 'ignored'
export interface CooperationSubscribeResult {
    status: SubscribeStatus
    accepted?: number
    total?: number
}

/** Template discovery reuses NiuCloud; authorization stays an explicit user tap. */
export function useCooperationSubscribe(options: { enabled?: () => boolean } = {}) {
    const preparing = ref(false),
        requesting = ref(false),
        prepared = ref(false),
        templateIds = ref<string[]>([])
    const loading = computed(() => preparing.value || requesting.value)
    let disposed = false,
        owner = '',
        epoch = 0,
        pending: Promise<boolean> | null = null
    let notifyPreparationFailure = false
    let supported = false
    // #ifdef MP-WEIXIN
    supported = true
    // #endif
    const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
    const enabled = () => options.enabled?.() !== false
    const current = (who: string, turn: number) =>
        !disposed && enabled() && !!getToken() && who === owner && who === identity() && turn === epoch
    function identify() {
        const who = identity()
        if (who !== owner) {
            owner = who
            epoch++
            templateIds.value = []
            prepared.value = false
            preparing.value = false
            requesting.value = false
            pending = null
            notifyPreparationFailure = false
        }
        return who
    }
    function feedback(title: string) {
        coopNotice(title)
    }
    function prepare(notifyFailure = false) {
        if (!enabled() || !supported || disposed || !getToken()) return Promise.resolve(false)
        const who = identify(),
            turn = epoch
        if (pending) {
            notifyPreparationFailure ||= notifyFailure
            return pending
        }
        if (prepared.value) return Promise.resolve(true)
        notifyPreparationFailure = notifyFailure
        preparing.value = true
        pending = (async () => {
            try {
                const response: any = await Promise.resolve().then(() =>
                    current(who, turn) ? getWeappTemplateId(noticeKeys) : null
                )
                if (!current(who, turn)) return false
                if (!Array.isArray(response?.data)) throw new Error('invalid templates')
                templateIds.value = [
                    ...new Set<string>(
                        response.data
                            .filter((value: unknown) => typeof value === 'string' && value.trim())
                            .map((value: string) => value.trim())
                    )
                ].slice(0, 3)
                prepared.value = true
                return true
            } catch (_) {
                // Background preloading stays quiet; an explicit subscription
                // attempt must explain failure instead of staying "preparing".
                if (current(who, turn) && notifyPreparationFailure) feedback('通知模板读取失败，请点击订阅重试')
                return false
            } finally {
                if (turn === epoch) {
                    preparing.value = false
                    pending = null
                    notifyPreparationFailure = false
                }
            }
        })()
        return pending
    }
    function request(): Promise<CooperationSubscribeResult> {
        if (!enabled()) return Promise.resolve({ status: 'ignored' })
        if (!supported) {
            feedback('请在微信小程序内订阅通知')
            return Promise.resolve({ status: 'unsupported' })
        }
        if (disposed || requesting.value) return Promise.resolve({ status: 'ignored' })
        if (!getToken()) {
            feedback('请先登录后订阅通知')
            return Promise.resolve({ status: 'login_required' })
        }
        const who = identify(),
            turn = epoch
        if (!prepared.value) {
            // Do not call the native authorization after an awaited network
            // request: that can lose WeChat's user-gesture context. Preload on
            // mount; if it is not ready, finish discovery and let the next tap authorize.
            void prepare(true)
            feedback('订阅入口正在准备，请稍后再点')
            return Promise.resolve({ status: 'preparing' })
        }
        if (!templateIds.value.length) {
            feedback('平台尚未配置通知模板，请联系管理员')
            return Promise.resolve({ status: 'unconfigured' })
        }
        const ids = [...templateIds.value]
        requesting.value = true
        return new Promise((resolve) => {
            const finish = (result: CooperationSubscribeResult, message = '') => {
                if (!current(who, turn)) {
                    if (turn === epoch) requesting.value = false
                    resolve({ status: 'ignored' })
                    return
                }
                requesting.value = false
                if (message) feedback(message)
                resolve(result)
            }
            try {
                uni.requestSubscribeMessage({
                    tmplIds: ids,
                    success: (result) => {
                        const accepted = ids.filter((id) => (result as Record<string, any>)[id] === 'accept').length
                        // Fresh quota: let the server resend work-order notices that WeChat rejected (43101) earlier.
                        // Fire and forget; the subscription result shown to the user does not depend on it.
                        if (accepted > 0 && current(who, turn)) void reportCooperationNoticeAuthorization().catch(() => {})
                        if (accepted === ids.length)
                            finish({ status: 'accepted', accepted, total: ids.length }, '已同意本次通知')
                        else if (accepted > 0)
                            finish(
                                { status: 'partial', accepted, total: ids.length },
                                `已同意 ${accepted} 项，其余未开启`
                            )
                        else if (ids.some((id) => (result as Record<string, any>)[id] === 'filter'))
                            finish(
                                { status: 'unconfigured', accepted: 0, total: ids.length },
                                '通知模板暂不可用，请联系管理员'
                            )
                        else if (ids.every((id) => ['reject', 'ban'].includes((result as Record<string, any>)[id])))
                            finish(
                                { status: 'rejected', accepted: 0, total: ids.length },
                                '未同意订阅，暂不能接收这类通知'
                            )
                        else
                            finish(
                                { status: 'failed', accepted: 0, total: ids.length },
                                '订阅结果未完整返回，请稍后重试'
                            )
                    },
                    fail: (result) => {
                        const cancelled = /cancel|取消/i.test(String(result.errMsg || ''))
                        finish(
                            { status: cancelled ? 'cancelled' : 'failed' },
                            cancelled ? '本次订阅已取消' : '订阅未完成，请稍后重试'
                        )
                    }
                })
            } catch (_) {
                finish({ status: 'failed' }, '订阅未完成，请稍后重试')
            }
        })
    }
    onMounted(() => {
        void prepare()
    })
    onBeforeUnmount(() => {
        disposed = true
        epoch++
        preparing.value = false
        requesting.value = false
        pending = null
    })
    return { loading, preparing, requesting, prepared, prepare, request }
}
