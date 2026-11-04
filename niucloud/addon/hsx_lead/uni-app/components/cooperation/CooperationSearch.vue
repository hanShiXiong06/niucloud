<template>
    <view class="coop-search-field" :class="{ 'is-focused': focused, 'is-disabled': disabled, 'is-loading': loading, 'is-compact': compact }" :aria-busy="loading">
        <u-search
            ref="searchRef"
            v-bind="$attrs"
            :model-value="modelValue"
            :placeholder="placeholder"
            :show-action="showAction"
            :action-text="actionText"
            :disabled="disabled || loading"
            :height="compact ? 38 : 42"
            bg-color="#ffffff"
            color="#2e4059"
            placeholder-color="#687a94"
            search-icon-color="#5c7699"
            :input-style="resolvedInputStyle"
            :action-style="resolvedActionStyle"
            @update:model-value="update"
            @change="emit('change', $event)"
            @search="search"
            @custom="custom"
            @clear="emit('clear')"
            @focus="focus"
            @blur="blur"
            @click="emit('click')"
            @click-icon="emit('clickIcon', $event)"
        >
            <template v-if="$slots.label" #label><slot name="label" /></template>
        </u-search>
    </view>
</template>
<script lang="ts">
export default { inheritAttrs: false }
</script>
<script setup lang="ts">
import { computed, ref } from 'vue'
const props = withDefaults(defineProps<{
    modelValue?: string | number
    placeholder?: string
    showAction?: boolean
    actionText?: string
    disabled?: boolean
    loading?: boolean
    compact?: boolean
    inputStyle?: Record<string, string | number>
    actionStyle?: Record<string, string | number>
}>(), { modelValue: '', placeholder: '搜索', showAction: true, actionText: '查询', disabled: false, loading: false, compact: false })
const emit = defineEmits<{
    (event: 'update:modelValue', value: string | number): void
    (event: 'change' | 'search' | 'custom' | 'focus' | 'blur' | 'clickIcon', value: string | number): void
    (event: 'clear' | 'click'): void
}>()
const focused = ref(false), searchRef = ref<{ clear?: () => void } | null>(null)
const resolvedInputStyle = computed(() => ({ fontSize: '14px', ...props.inputStyle }))
// u-search hides its action via width:0, so a positive minWidth would bring it
// back even with showAction=false. Hide it explicitly in that branch.
const resolvedActionStyle = computed(() => props.showAction ? ({ color: 'var(--primary-color, #3478cc)', background: '#edf4ff', fontSize: '14px', fontWeight: '550', minWidth: '50px', minHeight: props.compact ? '38px' : '42px', lineHeight: props.compact ? '38px' : '42px', borderRadius: '7px', marginRight: '2px', ...props.actionStyle }) : ({ display: 'none', width: '0', minWidth: '0', margin: '0' }))
function update(value: string | number) { emit('update:modelValue', value) }
// Keep u-search events distinct: Enter emits only search, the action emits only
// custom. Consumers already listen to the appropriate event; no extra reload.
function search(value: string | number) { if (!props.disabled && !props.loading) emit('search', value) }
function custom(value: string | number) { if (!props.disabled && !props.loading) emit('custom', value) }
function focus(value: string | number) { focused.value = true; emit('focus', value) }
function blur(value: string | number) { focused.value = false; emit('blur', value) }
function clear() { searchRef.value?.clear?.() }
defineExpose({ clear })
</script>
<style scoped lang="scss">
.coop-search-field { width: 100%; min-width: 0; box-sizing: border-box; padding: 2px 2px 2px 3px; border: 1px solid #b9cbe2; border-radius: 10px; background: #fff; box-shadow: 0 1px 2px rgba(30,65,110,.03); transition: border-color 150ms ease, box-shadow 150ms ease; }
.coop-search-field.is-focused,.coop-search-field:focus-within { border-color: var(--primary-color, #3478cc); box-shadow: 0 0 0 3px rgba(52,120,204,.08); }
.coop-search-field.is-disabled,.coop-search-field.is-loading { opacity: .6; }
.coop-search-field :deep(.u-search) { width: 100%; min-width: 0; }
.coop-search-field :deep(.u-search__content) { min-width: 0; }
.coop-search-field :deep(.u-search__content__input) { min-width: 0; }
.coop-search-field :deep(.u-search__action) { flex-shrink: 0; text-align: center; white-space: nowrap; }
@media(prefers-reduced-motion:reduce) { .coop-search-field { transition: none; } }
</style>
