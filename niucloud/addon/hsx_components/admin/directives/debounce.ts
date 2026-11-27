import type { Directive } from 'vue'

interface DebounceBinding {
    handler: (...args: any[]) => void
    delay?: number
}

const cleanupKey = Symbol('hsx-debounce-cleanup')
const configKey = Symbol('hsx-debounce-config')

type DebounceElement = HTMLElement & {
    [cleanupKey]?: () => void
    [configKey]?: DebounceBinding
}

function normalizeConfig(value: DebounceBinding | (() => void)): DebounceBinding {
    return typeof value === 'function' ? { handler: value, delay: 300 } : value
}

export const debounceDirective: Directive<DebounceElement, DebounceBinding | (() => void)> = {
    mounted(element, binding) {
        element[configKey] = normalizeConfig(binding.value)
        let timer: ReturnType<typeof setTimeout> | undefined
        const listener = (event: Event) => {
            if (timer) clearTimeout(timer)
            const config = element[configKey]
            if (!config) return
            timer = setTimeout(() => config.handler(event), config.delay ?? 300)
        }
        element.addEventListener('click', listener)
        element[cleanupKey] = () => {
            if (timer) clearTimeout(timer)
            element.removeEventListener('click', listener)
        }
    },
    updated(element, binding) {
        element[configKey] = normalizeConfig(binding.value)
    },
    unmounted(element) {
        element[cleanupKey]?.()
        delete element[cleanupKey]
        delete element[configKey]
    }
}
