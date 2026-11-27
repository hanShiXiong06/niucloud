import { computed, ref } from 'vue'
import type { Row } from './useLeadUi'

/** One explicit login, one idempotent task. A lost HTTP response never starts another paid recognition. */
export function useAutomaticAuthorization(options: {
    start: (merchantId: number, data: Row) => Promise<any>
    advance: (merchantId: number, data: Row) => Promise<any>
    cancel: (merchantId: number, data: Row) => Promise<any>
    complete: (result: Row) => void
    errorText: (cause: any, fallback: string) => string
}) {
    const status = ref('idle'),
        stage = ref(''),
        message = ref(''),
        taskToken = ref('')
    const active = computed(() => ['starting', 'processing', 'paused'].includes(status.value))
    const working = computed(() => ['starting', 'processing'].includes(status.value))
    let generation = 0,
        timer: ReturnType<typeof setTimeout> | undefined,
        merchantId = 0,
        expiresAt = 0,
        lastAdvance = 0
    let request: Row | null = null
    function clearTimer() {
        if (timer !== undefined) clearTimeout(timer)
        timer = undefined
    }
    function cancelToken(id: number, token: string) {
        if (token) void options.cancel(id, { task_token: token }).catch(() => {})
    }
    function stop() {
        const oldToken = taskToken.value,
            oldMerchant = merchantId
        generation++
        clearTimer()
        request = null
        merchantId = 0
        taskToken.value = ''
        expiresAt = 0
        lastAdvance = 0
        status.value = 'idle'
        stage.value = ''
        message.value = ''
        cancelToken(oldMerchant, oldToken)
    }
    function fail(text: string, state = 'failed') {
        clearTimer()
        status.value = state
        message.value = text
        taskToken.value = ''
        request = null
    }
    function schedule(delay = 5000) {
        clearTimer()
        timer = setTimeout(
            () => {
                timer = undefined
                void advance()
            },
            Math.max(5000, delay)
        )
    }
    function accept(payload: Row, token: number, id: number) {
        if (token !== generation) {
            cancelToken(id, typeof payload.task_token === 'string' ? payload.task_token : '')
            return
        }
        if (payload.status === 'completed') {
            if (!payload.result || typeof payload.result !== 'object' || Array.isArray(payload.result))
                throw new Error('自动登录结果不完整，请改用手动验证码。')
            clearTimer()
            taskToken.value = ''
            request = null
            status.value = 'completed'
            stage.value = ''
            message.value = ''
            options.complete(payload.result)
            return
        }
        if (['failed', 'expired', 'cancelled'].includes(payload.status)) {
            fail(payload.message || '本次自动登录未完成，请改用手动验证码。', payload.status)
            return
        }
        if (payload.status !== 'processing' || !/^[a-f0-9]{64}$/i.test(payload.task_token || ''))
            throw new Error('自动登录任务返回不完整，请改用手动验证码。')
        const expiry = Number(payload.expires_at)
        if (!Number.isFinite(expiry) || expiry * 1000 <= Date.now() || expiry * 1000 > Date.now() + 600000)
            throw new Error('本次自动登录任务已过期，请重新操作。')
        if (taskToken.value && taskToken.value !== payload.task_token)
            throw new Error('自动登录任务标识不一致，已停止查询。')
        taskToken.value = payload.task_token
        expiresAt = expiry
        status.value = 'processing'
        stage.value = payload.stage || 'recognizing'
        message.value = payload.message || '正在识别验证码，请稍候。'
        schedule(Math.min(30000, Math.max(5, Number(payload.retry_after) || 5)) * 1000)
    }
    async function start(id: number, data: Row) {
        if (active.value) return
        stop()
        merchantId = id
        if (!globalThis.crypto?.getRandomValues) {
            fail('当前浏览器不支持安全任务标识，请使用 HTTPS 或改用手动验证码。')
            return
        }
        const bytes = new Uint8Array(16)
        globalThis.crypto.getRandomValues(bytes)
        const requestId = Array.from(bytes, (value) => value.toString(16).padStart(2, '0')).join('')
        // Keep only nonsecret context for recovering an ambiguous start response.
        request = {
            provider: data.provider,
            credentials: { ...data.credentials },
            scope: { ...data.scope },
            request_id: requestId
        }
        const token = generation
        status.value = 'starting'
        stage.value = 'preparing'
        message.value = '正在创建本次登录任务…'
        try {
            accept((await options.start(id, { ...request, password: data.password })).data || {}, token, id)
        } catch (cause) {
            if (token === generation) {
                clearTimer()
                status.value = 'paused'
                message.value = options.errorText(cause, '暂未收到结果。请继续查询本次申请，不要重复发起。')
            }
        }
    }
    async function advance() {
        if (!active.value || !taskToken.value) return
        if (expiresAt * 1000 <= Date.now()) {
            fail('本次自动登录已过期，请改用手动验证码或重新发起。', 'expired')
            return
        }
        const wait = 5000 - (Date.now() - lastAdvance)
        if (wait > 0) {
            clearTimer()
            timer = setTimeout(() => {
                timer = undefined
                void advance()
            }, wait)
            return
        }
        const token = generation,
            id = merchantId
        lastAdvance = Date.now()
        status.value = 'processing'
        clearTimer()
        try {
            accept((await options.advance(id, { task_token: taskToken.value })).data || {}, token, id)
        } catch (cause) {
            if (token === generation) {
                status.value = 'paused'
                message.value = options.errorText(cause, '查询暂时中断，原任务仍保留；请继续查询。')
            }
        }
    }
    async function resume() {
        if (status.value !== 'paused') return
        if (taskToken.value) {
            await advance()
            return
        }
        if (!request) return
        const token = generation,
            id = merchantId
        status.value = 'starting'
        message.value = '正在查询本次申请…'
        try {
            accept((await options.start(id, { ...request, resume_only: true })).data || {}, token, id)
        } catch (cause) {
            if (token === generation) {
                status.value = 'paused'
                message.value = options.errorText(cause, '暂未恢复原任务。可继续查询，或停止后改用手动验证码。')
            }
        }
    }
    return { status, stage, message, taskToken, active, working, start, resume, stop }
}
