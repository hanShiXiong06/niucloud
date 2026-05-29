import { ref } from 'vue'

export const useRecyclePaging = <T = any>() => {
    const pagingRef = ref<any>()
    const list = ref<T[]>([])

    const reload = () => {
        pagingRef.value?.reload()
    }

    const refresh = () => {
        pagingRef.value?.refresh?.()
    }

    const complete = (rows: T[] | false) => {
        pagingRef.value?.complete(rows)
    }

    return {
        pagingRef,
        list,
        reload,
        refresh,
        complete
    }
}
