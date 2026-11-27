import { computed, ref, shallowRef } from 'vue'
import type { DialogMode } from '../types'

export function useDialog<T = any>(initialMode: DialogMode = 'create') {
    const visible = ref(false)
    const mode = ref<DialogMode>(initialMode)
    const payload = shallowRef<T>()
    const readonly = computed(() => mode.value === 'view')

    const open = (nextMode: DialogMode = 'create', data?: T) => {
        mode.value = nextMode
        payload.value = data
        visible.value = true
    }

    const close = () => {
        visible.value = false
    }

    const create = (data?: T) => open('create', data)
    const edit = (data: T) => open('edit', data)
    const view = (data: T) => open('view', data)

    return { visible, mode, payload, readonly, open, close, create, edit, view }
}
