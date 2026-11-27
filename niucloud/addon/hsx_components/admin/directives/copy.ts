import type { Directive } from 'vue'

async function copyText(text: string) {
    if (navigator.clipboard?.writeText) return navigator.clipboard.writeText(text)
    const textarea = document.createElement('textarea')
    textarea.value = text
    textarea.style.position = 'fixed'
    textarea.style.opacity = '0'
    document.body.appendChild(textarea)
    textarea.select()
    document.execCommand('copy')
    textarea.remove()
}

export const copyDirective: Directive<HTMLElement, string | (() => string)> = {
    mounted(element, binding) {
        const state = element as HTMLElement & {
            __hsxCopyValue?: string | (() => string)
            __hsxCopyCleanup?: () => void
        }
        state.__hsxCopyValue = binding.value
        const listener = () => {
            const source = state.__hsxCopyValue
            const value = typeof source === 'function' ? source() : source
            void copyText(String(value ?? ''))
        }
        element.addEventListener('click', listener)
        state.__hsxCopyCleanup = () => element.removeEventListener('click', listener)
    },
    updated(element, binding) {
        const state = element as HTMLElement & { __hsxCopyValue?: string | (() => string) }
        state.__hsxCopyValue = binding.value
    },
    unmounted(element) {
        const state = element as HTMLElement & { __hsxCopyCleanup?: () => void }
        state.__hsxCopyCleanup?.()
        delete state.__hsxCopyCleanup
    }
}
