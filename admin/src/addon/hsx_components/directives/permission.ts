import type { Directive } from 'vue'
import type { PermissionChecker } from '../tokens'

export function createPermissionDirective(checker?: PermissionChecker): Directive<HTMLElement, string | string[]> {
    return {
        mounted(element, binding) {
            if (!checker || checker(binding.value)) return
            element.remove()
        }
    }
}
