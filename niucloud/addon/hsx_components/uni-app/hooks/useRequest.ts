import { computed, ref, shallowRef } from 'vue'

export interface UseRequestOptions<T> {
    initialData?: T
    onSuccess?: (data: T) => void
    onError?: (error: unknown) => void
}

export function useRequest<T, Args extends any[] = any[]>(
    request: (...args: Args) => Promise<T>,
    options: UseRequestOptions<T> = {}
) {
    const pendingCount = ref(0)
    const loading = computed(() => pendingCount.value > 0)
    const data = shallowRef<T | undefined>(options.initialData)
    const error = shallowRef<unknown>()

    const execute = async (...args: Args): Promise<T> => {
        pendingCount.value += 1
        error.value = undefined
        try {
            const result = await request(...args)
            data.value = result
            options.onSuccess?.(result)
            return result
        } catch (reason) {
            error.value = reason
            options.onError?.(reason)
            throw reason
        } finally {
            pendingCount.value = Math.max(0, pendingCount.value - 1)
        }
    }

    return { loading, data, error, execute }
}
