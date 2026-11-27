import { computed, ref } from 'vue'

export function useLoading(initialValue = false) {
    const loadingCount = ref(initialValue ? 1 : 0)
    const loading = computed(() => loadingCount.value > 0)

    const startLoading = () => {
        loadingCount.value += 1
    }

    const stopLoading = () => {
        loadingCount.value = Math.max(0, loadingCount.value - 1)
    }

    const withLoading = async <T>(task: Promise<T> | (() => Promise<T>)): Promise<T> => {
        startLoading()
        try {
            return await (typeof task === 'function' ? task() : task)
        } finally {
            stopLoading()
        }
    }

    return { loading, startLoading, stopLoading, withLoading }
}
