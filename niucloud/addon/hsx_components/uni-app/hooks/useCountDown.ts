import { computed, onUnmounted, ref } from 'vue'

export function useCountDown(defaultSeconds = 60) {
    const seconds = ref(0)
    const running = computed(() => seconds.value > 0)
    const text = computed(() => (running.value ? `${seconds.value}s` : '获取验证码'))
    let timer: ReturnType<typeof setInterval> | undefined

    const stop = () => {
        if (timer) clearInterval(timer)
        timer = undefined
        seconds.value = 0
    }

    const start = (duration = defaultSeconds) => {
        stop()
        seconds.value = duration
        timer = setInterval(() => {
            seconds.value -= 1
            if (seconds.value <= 0) stop()
        }, 1000)
    }

    onUnmounted(stop)
    return { seconds, running, text, start, stop }
}
