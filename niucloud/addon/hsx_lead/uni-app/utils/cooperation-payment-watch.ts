/** Bounded foreground reconciliation. This utility never starts/cancels a payment. */
export function createCooperationPaymentWatch(options: {
    check: () => Promise<unknown>
    required: () => boolean
    blocked: () => boolean
    afterSeconds?: () => unknown
    schedule: (callback: () => void, delay: number) => unknown
    cancel: (timer: any) => void
    now?: () => number
}) {
    const delays = [1500, 2500, 4000, 6000, 10000, 15000, 20000, 30000]
    const now = options.now || (() => Date.now())
    let key = '',
        version = 0,
        checks = 0,
        deadline = 0,
        timer: any = null,
        running = false
    function clear() {
        if (timer !== null) options.cancel(timer)
        timer = null
    }
    function stop() {
        clear()
        key = ''
        checks = 0
        deadline = 0
        running = false
        version++
    }
    function sync(next: string) {
        if (next !== key) {
            stop()
            key = next
        }
        if (!key || !options.required()) {
            clear()
            return
        }
        if (timer !== null || running || checks >= delays.length) return
        if (!deadline) deadline = now() + 120000
        if (now() >= deadline) return
        const turn = version
        const hint = Number(options.afterSeconds?.())
        const delay =
            Number.isFinite(hint) && hint > 0
                ? Math.max(delays[checks], Math.min(30, Math.max(1, hint)) * 1000)
                : delays[checks]
        timer = options.schedule(() => {
            timer = null
            void tick(turn)
        }, delay)
    }
    async function tick(turn: number) {
        if (turn !== version || !key || !options.required() || now() >= deadline) return
        if (options.blocked()) {
            sync(key)
            return
        }
        checks++
        running = true
        try {
            await options.check()
        } catch (_) {
            /* Owning page displays errors; no retry may create a charge. */
        } finally {
            if (turn === version) {
                running = false
                sync(key)
            }
        }
    }
    return { sync, stop }
}
