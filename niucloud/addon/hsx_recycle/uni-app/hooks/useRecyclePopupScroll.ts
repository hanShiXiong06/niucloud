import { computed, inject, onUnmounted, provide, reactive, ref, watch } from 'vue'
import type { InjectionKey } from 'vue'
import { onHide, onShow } from '@dcloudio/uni-app'

type PopupKey = symbol | string
type PopupScrollContext = { setPopupOpen: (key: PopupKey, open: boolean) => void }
const popupScrollKey: InjectionKey<PopupScrollContext> = Symbol('recycle-popup-scroll')

/** 每页独立计数：多层弹窗关闭一层不解锁，离开页面不影响下一页。 */
export function useRecyclePopupPage() {
  const opened = reactive(new Set<PopupKey>())
  const active = ref(true)
  const setPopupOpen = (key: PopupKey, open: boolean) => {
    if (open) opened.add(key)
    else opened.delete(key)
  }
  provide(popupScrollKey, { setPopupOpen })
  onShow(() => { active.value = true })
  onHide(() => { active.value = false })
  onUnmounted(() => opened.clear())
  const popupScrollLocked = computed(() => active.value && opened.size > 0)
  // page-meta 必须由页面首节点承载，不能放入弹窗组件内部。
  const popupPageStyle = computed(() => popupScrollLocked.value ? 'overflow:hidden;overscroll-behavior:none;' : '')
  return { popupScrollLocked, popupPageStyle, setPopupOpen }
}

export function useRecyclePopupLock(isOpen: () => boolean) {
  const context = inject(popupScrollKey, null)
  const key = Symbol('popup')
  watch(isOpen, open => context?.setPopupOpen(key, open), { immediate: true, flush: 'sync' })
  onUnmounted(() => context?.setPopupOpen(key, false))
}
