<script lang="ts">
export default { name: 'HsxButton', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, inject, ref } from 'vue'
import { HSX_PERMISSION_CHECKER, type PermissionChecker } from '../../tokens'

const props = withDefaults(
    defineProps<{
        action?: (event: MouseEvent) => any | Promise<any>
        autoLoading?: boolean
        debounce?: number
        loading?: boolean
        disabled?: boolean
        permission?: string | string[]
        permissionChecker?: PermissionChecker
        hideWithoutPermission?: boolean
    }>(),
    {
        autoLoading: true,
        debounce: 500,
        loading: false,
        disabled: false,
        hideWithoutPermission: true
    }
)

const emit = defineEmits<{
    (event: 'click', value: MouseEvent): void
    (event: 'error', error: unknown): void
}>()

const injectedChecker = inject(HSX_PERMISSION_CHECKER, undefined)
const innerLoading = ref(false)
let lastClickAt = 0

const hasPermission = computed(() => {
    if (!props.permission) return true
    const checker = props.permissionChecker || injectedChecker
    return checker ? checker(props.permission) : true
})

const display = computed(() => !props.hideWithoutPermission || hasPermission.value)
const actualLoading = computed(() => props.loading || innerLoading.value)
const actualDisabled = computed(() => props.disabled || actualLoading.value || !hasPermission.value)

async function handleClick(event: MouseEvent) {
    const now = Date.now()
    if (actualDisabled.value || now - lastClickAt < props.debounce) return
    lastClickAt = now
    emit('click', event)
    if (!props.action) return

    try {
        const result = props.action(event)
        if (props.autoLoading && result instanceof Promise) {
            innerLoading.value = true
            await result
        }
    } catch (error) {
        emit('error', error)
        throw error
    } finally {
        innerLoading.value = false
    }
}
</script>

<template>
    <el-button
        v-if="display"
        v-bind="$attrs"
        :loading="actualLoading"
        :disabled="actualDisabled"
        @click="handleClick"
    >
        <slot />
    </el-button>
</template>
