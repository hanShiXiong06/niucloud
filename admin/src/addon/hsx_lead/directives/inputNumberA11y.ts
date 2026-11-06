import { nextTick, type ObjectDirective } from 'vue'

/** ElementPlus 2.7 sets this ARIA attribute only on mount. Keep accessibility
 * state aligned with the real native disabled property when editors enable. */
export function syncInputNumberAria(element: HTMLElement) {
    if (typeof element?.querySelectorAll !== 'function') return
    element.querySelectorAll<HTMLInputElement>('input[role="spinbutton"]').forEach(input => {
        if (input.disabled) input.setAttribute('aria-disabled', 'true')
        else input.removeAttribute('aria-disabled')
    })
}
export const vInputNumberA11y: ObjectDirective<HTMLElement> = {
    mounted: element => { void nextTick(() => syncInputNumberAria(element)) },
    updated: element => { void nextTick(() => syncInputNumberAria(element)) }
}
