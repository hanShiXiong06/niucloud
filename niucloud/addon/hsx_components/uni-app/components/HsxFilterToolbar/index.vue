<script lang="ts">
export default { name: 'HsxFilterToolbar' }
</script>

<script setup lang="ts">
import { computed } from 'vue'
import type { MobileFilterSelectionMode, MobileFilterToolbarItem } from '../../types'
import { useHaptics } from '../../hooks/useFeedback'
import HsxIcon from '../HsxIcon/index.vue'

const props = withDefaults(defineProps<{
    modelValue?: string | number | Array<string | number>
    items?: MobileFilterToolbarItem[]
    selection?: MobileFilterSelectionMode
    dense?: boolean
    showScrollbar?: boolean
    haptic?: boolean
}>(), {
    modelValue: '',
    items: () => [],
    selection: 'none',
    dense: false,
    showScrollbar: false,
    haptic: true
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: string | number | Array<string | number>): void
    (event: 'select', item: MobileFilterToolbarItem, index: number): void
}>()

const haptics = useHaptics()
const selectedValues = computed<Array<string | number>>(() => Array.isArray(props.modelValue) ? props.modelValue : [props.modelValue])

function isActive(item: MobileFilterToolbarItem) {
    return item.active === true || (props.selection !== 'none' && selectedValues.value.includes(item.value))
}

function select(item: MobileFilterToolbarItem, index: number) {
    if (item.disabled) return
    if (props.haptic) void haptics.selection()
    if (props.selection === 'single') emit('update:modelValue', item.value)
    if (props.selection === 'multiple') {
        const current = Array.isArray(props.modelValue) ? [...props.modelValue] : []
        emit('update:modelValue', current.includes(item.value) ? current.filter((value) => value !== item.value) : [...current, item.value])
    }
    emit('select', item, index)
}
</script>

<template>
    <scroll-view scroll-x :enable-flex="true" :show-scrollbar="showScrollbar" class="hsx-filter-toolbar">
        <view class="hsx-filter-toolbar__inner" :class="{ 'is-dense': dense }">
            <view
                v-for="(item, index) in items"
                :key="String(item.value)"
                class="hsx-filter-toolbar__item"
                :class="{ 'is-active': isActive(item), 'is-disabled': item.disabled }"
                role="button"
                @click="select(item, index)"
            >
                <slot name="item" :item="item" :index="index" :active="isActive(item)">
                    <HsxIcon v-if="item.icon" :name="item.icon" :size="14" />
                    <text class="hsx-filter-toolbar__label">{{ item.label }}</text>
                    <text v-if="item.subscribedText" class="hsx-filter-toolbar__subscribed">{{ item.subscribedText }}</text>
                    <text v-else-if="Number(item.count || 0) > 0" class="hsx-filter-toolbar__count">{{ item.count }}</text>
                    <HsxIcon v-if="item.arrow !== false" name="uview arrow-down-fill" :size="9" />
                </slot>
            </view>
            <slot name="after" />
        </view>
    </scroll-view>
</template>

<style scoped lang="scss">
.hsx-filter-toolbar { width: 100%; white-space: nowrap; }
.hsx-filter-toolbar__inner { display: inline-flex; width: max-content; min-width: 100%; height: 54px; box-sizing: border-box; align-items: center; gap: 8px; padding: 8px 12px; }
.hsx-filter-toolbar__inner.is-dense { height: 46px; padding-top: 6px; padding-bottom: 6px; }
.hsx-filter-toolbar__item { display: inline-flex; min-width: 0; height: 36px; box-sizing: border-box; flex: none; align-items: center; justify-content: center; gap: 4px; padding: 0 13px; border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 999px; color: var(--hsx-mobile-text-regular, #596579); background: var(--hsx-mobile-bg-muted, #f5f7fa); font-size: 13px; transition: transform var(--hsx-mobile-motion-fast, 120ms), border-color var(--hsx-mobile-motion-fast, 120ms); }
.is-dense .hsx-filter-toolbar__item { height: 32px; padding: 0 11px; font-size: 12px; }
.hsx-filter-toolbar__item:active { transform: scale(.97); }
.hsx-filter-toolbar__item.is-active { border-color: var(--hsx-mobile-primary-border, #9bb8ff); color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); font-weight: 600; }
.hsx-filter-toolbar__item.is-disabled { opacity: .46; }
.hsx-filter-toolbar__label { max-width: 132px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-filter-toolbar__count { display: inline-flex; min-width: 18px; height: 18px; box-sizing: border-box; align-items: center; justify-content: center; padding: 0 5px; border-radius: 999px; color: #fff; background: var(--hsx-mobile-primary, #2563eb); font-size: 10px; line-height: 18px; }
.hsx-filter-toolbar__subscribed { padding: 2px 6px; border-radius: 999px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-bg-surface, #fff); font-size: 10px; font-weight: 600; }
</style>
