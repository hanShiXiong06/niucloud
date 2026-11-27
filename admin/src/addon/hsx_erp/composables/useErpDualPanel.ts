import { ref } from 'vue'

type PanelMode = 'idle' | 'create' | 'detail'

/**
 * ERP 双栏页面（左列表 + 右表单）状态管理 Composable
 *
 * 用法：
 *   const { mode, selected, openCreate, openDetail, resetPanel } = useErpDualPanel<MyType>()
 */
export function useErpDualPanel<T = any>() {
    const mode = ref<PanelMode>('idle')
    const selected = ref<T | null>(null)

    function openCreate() {
        mode.value = 'create'
        selected.value = null
    }

    function openDetail(item: T) {
        selected.value = item
        mode.value = 'detail'
    }

    /** 退出创建模式：如果有已选项则回到 detail，否则回到 idle */
    function resetPanel() {
        mode.value = selected.value ? 'detail' : 'idle'
    }

    function clearPanel() {
        mode.value = 'idle'
        selected.value = null
    }

    return {
        mode,
        selected,
        openCreate,
        openDetail,
        resetPanel,
        clearPanel,
    }
}
