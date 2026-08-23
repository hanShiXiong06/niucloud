import { ref, watch, type Ref } from 'vue'

export function useStorage<T>(key: string, defaultValue: T): Ref<T> {
    const storedValue = uni.getStorageSync(key)
    const value = ref<T>(storedValue === '' || storedValue === undefined ? defaultValue : storedValue) as Ref<T>

    watch(
        value,
        (nextValue) => {
            if (nextValue === undefined || nextValue === null) uni.removeStorageSync(key)
            else uni.setStorageSync(key, nextValue)
        },
        { deep: true }
    )

    return value
}
