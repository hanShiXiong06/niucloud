<script lang="ts">export default { name: 'HsxNotice' }</script>
<script setup lang="ts">
import { computed, getCurrentInstance, ref, useSlots, watch } from 'vue'
import { CircleCheck, Close, InfoFilled, WarningFilled } from '@element-plus/icons-vue'

const props = withDefaults(defineProps<{
    modelValue?: boolean
    title: string
    description?: string
    type?: 'info' | 'success' | 'warning' | 'error'
    closable?: boolean
    defaultExpanded?: boolean
    resetKey?: string | number
}>(), { modelValue: undefined, description: '', type: 'info', closable: true, defaultExpanded: false, resetKey: '' })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'close'): void }>()
const slots = useSlots()
const dismissed = ref(false)
const expanded = ref(props.defaultExpanded)
const contentId = `hsx-notice-${getCurrentInstance()?.uid}`
const visible = computed(() => props.modelValue ?? !dismissed.value)
const hasDetails = computed(() => !!props.description || !!slots.default)
const icon = computed(() => props.type === 'success' ? CircleCheck : ['warning', 'error'].includes(props.type) ? WarningFilled : InfoFilled)
function close() {
    if (!props.closable) return
    dismissed.value = true
    emit('update:modelValue', false)
    emit('close')
}
// 本次关闭只影响阅读；新对象或新问题必须重新显示，不保存业务确认状态。
watch(() => [props.resetKey, props.title, props.description], () => {
    dismissed.value = false
    expanded.value = props.defaultExpanded
})
</script>
<template>
    <section v-if="visible" class="hsx-notice" :class="`hsx-notice--${type}`" :role="type === 'error' ? 'alert' : 'status'">
        <div class="hsx-notice__row">
            <el-icon class="hsx-notice__icon" aria-hidden="true"><component :is="icon" /></el-icon>
            <div class="hsx-notice__title">{{ title }}</div>
            <div class="hsx-notice__actions">
                <slot name="actions" />
                <button v-if="hasDetails" type="button" class="hsx-notice__toggle" :aria-expanded="expanded" :aria-controls="contentId" @click="expanded = !expanded">{{ expanded ? '收起说明' : '查看说明' }}</button>
                <button v-if="closable" type="button" class="hsx-notice__close" :aria-label="`关闭提示：${title}`" title="关闭本次提示" @click="close"><el-icon><Close /></el-icon></button>
            </div>
        </div>
        <div v-if="hasDetails" v-show="expanded" :id="contentId" class="hsx-notice__detail"><slot>{{ description }}</slot></div>
    </section>
</template>
<style scoped>
.hsx-notice { --notice-color: var(--hsx-color-primary); padding: 10px 12px; border: 1px solid var(--hsx-border-color); border-left: 3px solid var(--notice-color); border-radius: var(--hsx-radius-sm); background: var(--hsx-bg-muted); color: var(--hsx-text-regular); font-size: 13px; line-height: 21px; }
.hsx-notice--success { --notice-color: var(--hsx-color-success); }
.hsx-notice--warning { --notice-color: var(--hsx-color-warning); }
.hsx-notice--error { --notice-color: var(--hsx-color-danger); }
.hsx-notice__row { display: flex; align-items: flex-start; gap: 8px; flex-wrap: wrap; }
.hsx-notice__icon { flex: none; margin-top: 3px; color: var(--notice-color); }
.hsx-notice__title { flex: 1; min-width: 120px; color: var(--hsx-text-primary); font-weight: 500; overflow-wrap: anywhere; }
.hsx-notice__actions { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-left: auto; }
.hsx-notice__toggle, .hsx-notice__close { border: 0; background: transparent; color: var(--hsx-text-secondary); cursor: pointer; font: inherit; padding: 0 4px; min-height: 24px; }
.hsx-notice__toggle { color: var(--hsx-color-primary); }
.hsx-notice__close { display: inline-flex; align-items: center; justify-content: center; min-width: 24px; }
.hsx-notice button:focus-visible { outline: 2px solid var(--hsx-color-primary); outline-offset: 2px; border-radius: 3px; }
.hsx-notice__detail { margin: 8px 0 0 22px; padding-top: 8px; border-top: 1px solid var(--hsx-border-color); overflow-wrap: anywhere; }
@media (max-width: 640px) { .hsx-notice__actions { max-width: 100%; } .hsx-notice__detail { margin-left: 0; } }
</style>
