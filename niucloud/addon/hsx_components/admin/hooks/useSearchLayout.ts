import { computed, ref } from 'vue'

export type HsxSearchLayout = 'horizontal' | 'vertical'
const STORAGE_KEY = 'hsx.admin.search-layout.v1'

function readPreference(): HsxSearchLayout {
    try {
        if (typeof window !== 'undefined' && window.localStorage.getItem(STORAGE_KEY) === 'vertical') return 'vertical'
    } catch { /* 隐私模式或存储受限不影响查询。 */ }
    return 'horizontal'
}

// 同一个浏览器中的业务页面共用偏好，不保存任何业务数据或查询条件。
const preferredLayout = ref<HsxSearchLayout>(readPreference())

export function useSearchLayout() {
    const layout = computed({
        get: () => preferredLayout.value,
        set: (value: HsxSearchLayout) => {
            if (value !== 'horizontal' && value !== 'vertical') return
            preferredLayout.value = value
            try {
                if (typeof window !== 'undefined') window.localStorage.setItem(STORAGE_KEY, value)
            } catch { /* 存储失败时本次页面仍立即生效。 */ }
        }
    })
    const labelPosition = computed(() => layout.value === 'horizontal' ? 'right' : 'top')
    return { layout, labelPosition }
}
