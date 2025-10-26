import { reactive } from 'vue'

interface PopupState {
  visible: boolean
  order: AnyObject | null
  actionKey: string
  onConfirm?: (payload?: any) => void
  onClose?: () => void
}

// 师傅端：独立的弹窗状态（不依赖 systemStore）
export const popupState = reactive<PopupState>({
  visible: false,
  order: null,
  actionKey: ''
})

// 设置弹窗状态（兼容原业务：可直接传入回调）
export function setPopupState(params: Partial<PopupState>) {
  popupState.visible = params.visible ?? true
  popupState.order = params.order ?? null
  popupState.actionKey = params.actionKey ?? ''
  popupState.onConfirm = params.onConfirm
  popupState.onClose = params.onClose
}

// 打开弹窗（语义化别名）
export function openPopup(params: Partial<PopupState>) {
  setPopupState({ ...params, visible: true })
}

// 关闭弹窗并清理状态
export function closePopup() {
  popupState.visible = false
  popupState.order = null
  popupState.actionKey = ''
  popupState.onConfirm = undefined
  popupState.onClose = undefined
}

// 确认弹窗（调用回调后自动关闭）
export function confirmPopup(payload?: any) {
  try {
    if (typeof popupState.onConfirm === 'function') {
      popupState.onConfirm(payload)
    }
  } finally {
    closePopup()
  }
}