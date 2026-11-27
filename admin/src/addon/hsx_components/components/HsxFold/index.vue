<script lang="ts">export default { name: 'HsxFold' }</script>
<script setup lang="ts">
import { computed, getCurrentInstance, ref, watch } from 'vue'
import { ArrowRight } from '@element-plus/icons-vue'
const props = withDefaults(defineProps<{
    title: string
    summary?: string
    modelValue?: boolean
    defaultOpen?: boolean
    resetKey?: string | number
}>(), { summary: '', modelValue: undefined, defaultOpen: false, resetKey: '' })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const innerOpen = ref(props.defaultOpen)
const open = computed({ get: () => props.modelValue ?? innerOpen.value, set: value => { innerOpen.value = value; emit('update:modelValue', value) } })
const contentId = `hsx-fold-${getCurrentInstance()?.uid}`
watch(() => props.resetKey, () => { innerOpen.value = props.defaultOpen })
</script>
<template>
    <section class="hsx-fold" :class="{ 'hsx-fold--open': open }">
        <button class="hsx-fold__trigger" type="button" :aria-expanded="open" :aria-controls="contentId" @click="open = !open">
            <el-icon class="hsx-fold__arrow"><ArrowRight /></el-icon>
            <span class="hsx-fold__title">{{ title }}</span>
            <span v-if="summary" class="hsx-fold__summary">{{ summary }}</span>
            <span class="hsx-fold__label">{{ open ? '收起' : '展开' }}</span>
        </button>
        <!-- 保留表单和组件实例：折叠不清空输入，不触发重复加载。 -->
        <div v-show="open" :id="contentId" class="hsx-fold__body"><slot /></div>
    </section>
</template>
<style scoped>
.hsx-fold { border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-sm); background: var(--hsx-bg-surface); }
.hsx-fold__trigger { display: flex; align-items: center; gap: 8px; width: 100%; padding: 10px 12px; background: transparent; border: 0; color: var(--hsx-text-primary); cursor: pointer; text-align: left; font: inherit; font-size: 13px; line-height: 22px; }
.hsx-fold__trigger:focus-visible { outline: 2px solid var(--hsx-color-primary); outline-offset: 2px; }
.hsx-fold__title { font-weight: 600; }
.hsx-fold__summary { flex: 1; min-width: 0; color: var(--hsx-text-secondary); overflow-wrap: anywhere; font-size: 12px; }
.hsx-fold__label { margin-left: auto; flex: none; color: var(--hsx-color-primary); font-size: 12px; }
.hsx-fold__arrow { flex: none; color: var(--hsx-text-secondary); transition: transform var(--hsx-motion-fast); }
.hsx-fold--open .hsx-fold__arrow { transform: rotate(90deg); }
.hsx-fold__body { padding: 12px; border-top: 1px solid var(--hsx-border-color); min-width: 0; }
@media (prefers-reduced-motion: reduce) { .hsx-fold__arrow { transition: none; } }
</style>
