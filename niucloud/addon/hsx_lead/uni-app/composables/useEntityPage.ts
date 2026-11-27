import { onBeforeUnmount, ref } from 'vue'
import { getSiteId, getToken } from '@/utils/common'
import { coopError, type CoopRow } from '../api/cooperation'

/** Each read/write is bound to the active login and page lifetime. */
export function useEntityPage(read: () => Promise<any>) {
    const data = ref<CoopRow>({}),
        loading = ref(false),
        busy = ref(false),
        error = ref(''),
        writeError = ref(''),
        ready = ref(false)
    let active = true,
        disposed = false,
        owner = '',
        epoch = 0
    const identity = () => `${getSiteId(uni.getStorageSync('wap_site_id') || '')}:${getToken() || ''}`
    const valid = (who: string) => active && !disposed && !!getToken() && who === identity() && owner === who
    function identify() {
        const who = identity()
        if (who !== owner) {
            owner = who
            epoch++
            data.value = {}
            ready.value = false
            error.value = ''
            writeError.value = ''
        }
        return who
    }
    async function refresh() {
        active = true
        const who = identify(),
            turn = ++epoch
        if (!getToken()) {
            ready.value = false
            data.value = {}
            error.value = '请先登录后再查看门店'
            return false
        }
        loading.value = true
        error.value = ''
        try {
            const response = await read()
            if (!valid(who) || turn !== epoch) return false
            if (!response?.data || typeof response.data !== 'object' || Array.isArray(response.data))
                throw new Error('门店资料未完整返回，请刷新核对')
            data.value = response.data
            ready.value = true
            return true
        } catch (cause) {
            if (valid(who) && turn === epoch) {
                error.value = coopError(cause, '读取未完成，请刷新重试')
                ready.value = false
            }
            return false
        } finally {
            if (turn === epoch) loading.value = false
        }
    }
    async function write(work: () => Promise<any>) {
        // A stale form/confirmation must not become a write for a newly signed
        // in account. Only an explicit fresh read can establish its ownership.
        const who = identity()
        if (!ready.value || !valid(who) || busy.value) return null
        busy.value = true
        writeError.value = ''
        try {
            const response = await work()
            if (!valid(who)) return null
            return response.data || {}
        } catch (cause) {
            if (valid(who)) writeError.value = coopError(cause, '结果尚未确认，请先刷新核对，勿重复提交')
            return null
        } finally {
            busy.value = false
        }
    }
    function pause() {
        active = false
        epoch++
        loading.value = false
    }
    onBeforeUnmount(() => {
        disposed = true
        pause()
    })
    return { data, loading, busy, error, writeError, ready, refresh, write, pause, current: valid, identity }
}
