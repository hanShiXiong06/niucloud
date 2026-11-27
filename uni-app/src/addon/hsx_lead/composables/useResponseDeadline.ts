import { computed, onBeforeUnmount, ref, watch } from 'vue'

type ResponseLead = Record<string, any>
/** A server-anchored display clock. It never performs a request or closes/reassigns a lead. */
export function useResponseDeadline(lead: () => ResponseLead, active: () => boolean = () => true) {
    const remainingSeconds = ref<number | null>(null)
    let baseRemaining: number | null = null, baseTick = 0, timer: ReturnType<typeof setInterval> | undefined, disposed = false
    const now = () => typeof performance !== 'undefined' && typeof performance.now === 'function' ? performance.now() : Date.now()
    const visible = computed(() => lead().status === 'assigned' && Number.isSafeInteger(Number(lead().response_deadline)) && Number(lead().response_deadline) > 0)
    const overdue = computed(() => visible.value && (remainingSeconds.value === 0 || remainingSeconds.value === null && lead().response_overdue === true))
    const formatted = computed(() => {
        if (remainingSeconds.value === null) return ''
        const seconds = Math.max(0, remainingSeconds.value), hours = Math.floor(seconds / 3600), minutes = Math.floor(seconds % 3600 / 60), pad = (value: number) => String(value).padStart(2, '0')
        return hours ? `${pad(hours)}:${pad(minutes)}:${pad(seconds % 60)}` : `${pad(minutes)}:${pad(seconds % 60)}`
    })
    function stop() { if (timer !== undefined) clearInterval(timer); timer = undefined }
    function tick() {
        if (!visible.value || baseRemaining === null) { remainingSeconds.value = null; return }
        remainingSeconds.value = Math.max(0, Math.ceil(baseRemaining - Math.max(0, now() - baseTick) / 1000))
        if (remainingSeconds.value === 0) stop()
    }
    function resume() {
        stop(); tick()
        if (!disposed && active() && visible.value && remainingSeconds.value !== null && remainingSeconds.value > 0) timer = setInterval(tick, 1000)
    }
    watch(() => [lead().id, lead().assignment_version, lead().status, lead().response_deadline, lead().server_time, lead().response_remaining_seconds], () => {
        const row = lead(), deadline = Number(row.response_deadline), serverTime = Number(row.server_time), remaining = row.response_remaining_seconds
        baseTick = now()
        baseRemaining = Number.isSafeInteger(serverTime) && serverTime > 0 && Number.isSafeInteger(deadline) && deadline > 0 ? Math.max(0, deadline - serverTime) : typeof remaining === 'number' && Number.isSafeInteger(remaining) && remaining >= 0 ? remaining : null
        resume()
    }, { immediate: true, flush: 'sync' })
    watch(active, enabled => { if (enabled) resume(); else stop() }, { flush: 'sync' })
    onBeforeUnmount(() => { disposed = true; stop() })
    return { visible, remainingSeconds, overdue, formatted }
}
