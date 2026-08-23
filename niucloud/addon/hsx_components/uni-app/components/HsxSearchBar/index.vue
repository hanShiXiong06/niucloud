<script lang="ts">
export default { name: 'HsxSearchBar' }
</script>

<script setup lang="ts">
import { computed, onBeforeUnmount } from 'vue'
import type { MobileSearchTrigger } from '../../types'
import { useHaptics } from '../../hooks/useFeedback'
import HsxIcon from '../HsxIcon/index.vue'

const props = withDefaults(defineProps<{
    modelValue?: string | number
    placeholder?: string
    maxlength?: number
    disabled?: boolean
    loading?: boolean
    clearable?: boolean
    autoSearch?: boolean
    debounce?: number
    searchOnClear?: boolean
    showFilter?: boolean
    filterCount?: number
    showAction?: boolean
    actionText?: string
    size?: 'small' | 'normal' | 'large'
    variant?: 'filled' | 'outline' | 'glass'
    round?: boolean
    haptic?: boolean
}>(), {
    modelValue: '',
    placeholder: '搜索关键词',
    maxlength: 80,
    disabled: false,
    loading: false,
    clearable: true,
    autoSearch: false,
    debounce: 400,
    searchOnClear: true,
    showFilter: false,
    filterCount: 0,
    showAction: false,
    actionText: '搜索',
    size: 'normal',
    variant: 'filled',
    round: true,
    haptic: true
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'input', value: string): void
    (event: 'search', value: string, trigger: MobileSearchTrigger): void
    (event: 'clear'): void
    (event: 'filter'): void
}>()

const textValue = computed(() => String(props.modelValue ?? ''))
const badgeText = computed(() => props.filterCount > 99 ? '99+' : String(Math.max(0, props.filterCount)))
const haptics = useHaptics()
let debounceTimer: ReturnType<typeof setTimeout> | undefined

function emitSearch(trigger: MobileSearchTrigger, value = textValue.value) {
    if (props.disabled || props.loading) return
    if (props.haptic && trigger !== 'debounce') void haptics.selection()
    emit('search', value.trim(), trigger)
}

function scheduleSearch(value: string) {
    if (!props.autoSearch) return
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => emitSearch('debounce', value), Math.max(0, props.debounce))
}

function handleInput(value: string | number) {
    const next = String(value ?? '')
    emit('update:modelValue', next)
    emit('input', next)
    scheduleSearch(next)
}

function handleClear() {
    if (debounceTimer) clearTimeout(debounceTimer)
    emit('update:modelValue', '')
    emit('input', '')
    emit('clear')
    if (props.searchOnClear) emit('search', '', 'clear')
}

function handleFilter() {
    if (props.disabled) return
    if (props.haptic) void haptics.selection()
    emit('filter')
}

onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<template>
    <view
        class="hsx-search-bar"
        :class="[
            `hsx-search-bar--${size}`,
            `hsx-search-bar--${variant}`,
            {
                'is-round': round,
                'is-disabled': disabled,
                'is-loading': loading,
                'has-filter': showFilter,
                'has-action': showAction,
                'has-trailing': Boolean($slots.trailing)
            }
        ]"
        role="search"
    >
        <view class="hsx-search-bar__leading" @click="emitSearch('icon')">
            <slot name="leading">
                <HsxIcon :name="loading ? 'refresh' : 'search'" :spin="loading" :size="size === 'small' ? 16 : 18" />
            </slot>
        </view>

        <u-input
            class="hsx-search-bar__input"
            :model-value="textValue"
            :placeholder="placeholder"
            :maxlength="maxlength"
            :disabled="disabled"
            :clearable="clearable"
            confirm-type="search"
            border="none"
            @update:model-value="handleInput"
            @confirm="emitSearch('enter')"
            @clear="handleClear"
        />

        <view v-if="$slots.trailing" class="hsx-search-bar__trailing"><slot name="trailing" /></view>

        <view v-if="showFilter" class="hsx-search-bar__filter" role="button" aria-label="打开筛选" @click="handleFilter">
            <slot name="filter" :count="filterCount">
                <HsxIcon name="filter" :size="18" />
                <text v-if="filterCount > 0" class="hsx-search-bar__badge">{{ badgeText }}</text>
            </slot>
        </view>

        <view v-if="showAction" class="hsx-search-bar__action" role="button" @click="emitSearch('button')">
            <slot name="action">{{ actionText }}</slot>
        </view>
    </view>
</template>

<style scoped lang="scss">
.hsx-search-bar {
    display: flex;
    width: 100%;
    min-width: 0;
    height: 44px;
    box-sizing: border-box;
    align-items: center;
    gap: 8px;
    padding: 0 10px;
    overflow: visible;
    border: 1px solid transparent;
    border-radius: 12px;
    color: var(--hsx-mobile-text-regular, #596579);
    background: var(--hsx-mobile-bg-muted, #f5f7fa);
    transition: border-color var(--hsx-mobile-motion-fast, 120ms) var(--hsx-mobile-ease, ease), background-color var(--hsx-mobile-motion-fast, 120ms) var(--hsx-mobile-ease, ease);
}
.hsx-search-bar--small { height: 36px; padding: 0 8px; }
.hsx-search-bar--large { height: 48px; padding: 0 12px; }
.hsx-search-bar--outline { border-color: var(--hsx-mobile-border, #e6ebf2); background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-search-bar--glass { border-color: rgba(255, 255, 255, .18); background: rgba(255, 255, 255, .12); backdrop-filter: blur(16px); }
.hsx-search-bar.is-round { border-radius: 999px; }
.hsx-search-bar:focus-within { border-color: var(--hsx-mobile-primary-border, #9bb8ff); box-shadow: 0 0 0 3px rgba(37, 99, 235, .08); }
.hsx-search-bar.is-disabled { opacity: .56; }
.hsx-search-bar__leading,
.hsx-search-bar__trailing,
.hsx-search-bar__filter,
.hsx-search-bar__action { display: flex; flex: none; align-items: center; justify-content: center; }
.hsx-search-bar__leading { width: 24px; height: 100%; color: var(--hsx-mobile-text-secondary, #8a94a5); }
.hsx-search-bar__input { min-width: 0; flex: 1; overflow: hidden; }
.hsx-search-bar__input :deep(.u-input),
.hsx-search-bar__input :deep(.u-input__content),
.hsx-search-bar__input :deep(.u-input__content__field-wrapper) { width: 100%; min-width: 0; height: 100%; padding: 0 !important; overflow: hidden; background: transparent !important; }
.hsx-search-bar__input :deep(.u-input__content__field-wrapper__field) { min-width: 0; height: 100%; color: var(--hsx-mobile-text-primary, #172033) !important; font-size: var(--hsx-mobile-font-control, 14px) !important; line-height: 1.4; }
.hsx-search-bar__input :deep(.u-input__content__field-wrapper__field::placeholder) { color: var(--hsx-mobile-text-tertiary, #a5adba) !important; }
.hsx-search-bar__input :deep(.u-input__content__clear) { flex: none; }
.hsx-search-bar__filter { position: relative; width: 34px; height: 34px; border: 1px solid rgba(37, 99, 235, .08); border-radius: 50%; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); }
.hsx-search-bar__filter:active,
.hsx-search-bar__action:active { transform: scale(.96); }
.hsx-search-bar__badge { position: absolute; top: -4px; right: -5px; min-width: 17px; height: 17px; box-sizing: border-box; padding: 0 4px; border: 2px solid var(--hsx-mobile-bg-surface, #fff); border-radius: 999px; color: #fff; background: var(--hsx-mobile-danger, #f56c6c); font-size: 9px; font-weight: 700; line-height: 13px; text-align: center; }
.hsx-search-bar__action { min-width: 52px; height: 34px; box-sizing: border-box; padding: 0 12px; border-radius: 999px; color: #fff; background: linear-gradient(135deg, var(--hsx-mobile-primary, #2563eb), #3c9cff); box-shadow: 0 5px 12px rgba(37, 99, 235, .2); font-size: 13px; font-weight: 650; white-space: nowrap; }
.hsx-search-bar__trailing { color: var(--hsx-mobile-text-secondary, #8a94a5); }
@media (max-width: 360px) {
    .hsx-search-bar { gap: 5px; padding-right: 7px; padding-left: 7px; }
    .hsx-search-bar__leading { width: 20px; }
    .hsx-search-bar__filter { width: 32px; height: 32px; }
    .hsx-search-bar__action { min-width: 46px; padding: 0 9px; }
}
</style>
