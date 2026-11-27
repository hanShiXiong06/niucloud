import { computed, ref } from 'vue'

/** One loading/error owner for the two framework-decorated landing pages. */
export function useCooperationDiyLoad(label: string) {
    const loading = ref(true),
        error = ref('')
    let timer: ReturnType<typeof setTimeout> | undefined,
        active = true,
        disposed = false
    function clearTimer() {
        if (timer !== undefined) {
            clearTimeout(timer)
            timer = undefined
        }
    }
    function start() {
        if (disposed) return
        active = true
        clearTimer()
        loading.value = true
        error.value = ''
        timer = setTimeout(() => {
            timer = undefined
            if (!disposed && active && loading.value) {
                loading.value = false
                error.value = `${label}暂时没有加载出来，请重新加载`
            }
        }, 12000)
    }
    function complete() {
        if (disposed || !active) return false
        clearTimer()
        loading.value = false
        error.value = ''
        return true
    }
    function fail() {
        if (disposed || !active) return
        clearTimer()
        loading.value = false
        error.value = `${label}加载失败，请重新加载`
    }
    function pause() {
        active = false
        clearTimer()
        loading.value = false
    }
    function dispose() {
        pause()
        disposed = true
    }
    return {
        loading,
        error,
        ready: computed(() => !loading.value && !error.value),
        start,
        complete,
        fail,
        pause,
        dispose,
        requestOptions: { onRequestStart: start, onRequestError: fail }
    }
}
