<script lang="ts">export default { name: 'HsxComponentCatalog' }</script>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { MobileComponentCatalogItem } from '../../types'
import { useHaptics } from '../../hooks/useFeedback'
import HsxIcon from '../HsxIcon/index.vue'
import HsxPopup from '../HsxPopup/index.vue'
import HsxSearchBar from '../HsxSearchBar/index.vue'

const props = withDefaults(defineProps<{
    items?: MobileComponentCatalogItem[]
    visible?: boolean | null
    title?: string
    triggerText?: string
    placeholder?: string
    fixed?: boolean
    activeKey?: string
    height?: string | number
    adaptiveAt?: number
    adaptiveWidth?: number
}>(), {
    items: () => [],
    visible: null,
    title: '组件目录',
    triggerText: '目录',
    placeholder: '搜索组件名称或能力',
    fixed: true,
    activeKey: '',
    height: '78vh',
    adaptiveAt: 600,
    adaptiveWidth: 560
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'select', item: MobileComponentCatalogItem, index: number): void
}>()

const innerVisible = ref(false)
const keyword = ref('')
const haptics = useHaptics()
const show = computed({
    get: () => props.visible === null ? innerVisible.value : props.visible,
    set: (value) => {
        innerVisible.value = value
        emit('update:visible', value)
    }
})
const filteredItems = computed(() => {
    const search = keyword.value.trim().toLowerCase()
    if (!search) return props.items
    return props.items.filter((item) => [item.label, item.group, item.description, item.key].some((value) => String(value || '').toLowerCase().includes(search)))
})
const groups = computed(() => {
    const result = new Map<string, Array<{ item: MobileComponentCatalogItem, index: number }>>()
    filteredItems.value.forEach((item) => {
        const group = item.group || '其他组件'
        const rows = result.get(group) || []
        rows.push({ item, index: props.items.indexOf(item) })
        result.set(group, rows)
    })
    return Array.from(result.entries()).map(([label, items]) => ({ label, items }))
})

function open() {
    keyword.value = ''
    show.value = true
    void haptics.selection()
}

function close() {
    show.value = false
}

function select(item: MobileComponentCatalogItem, index: number) {
    void haptics.selection()
    emit('select', item, index)
    close()
}

defineExpose({ open, close })
</script>

<template>
    <view
        v-if="fixed"
        class="hsx-component-catalog__floating"
        hover-class="hsx-component-catalog__floating--pressed"
        :hover-stay-time="80"
        role="button"
        aria-label="打开组件目录"
        @click="open"
    >
        <HsxIcon name="grid" :size="17" />
        <text>{{ triggerText }}</text>
    </view>
    <view v-else class="hsx-component-catalog__inline" @click="open">
        <slot name="trigger" :open="open">
            <HsxIcon name="grid" :size="17" /><text>{{ triggerText }}</text>
        </slot>
    </view>

    <HsxPopup
        :model-value="show"
        :title="title"
        mode="bottom"
        adaptive="side"
        :adaptive-at="adaptiveAt"
        :adaptive-width="adaptiveWidth"
        :height="height"
        adaptive-height="100vh"
        :z-index="10420"
        body-padding="0"
        @update:model-value="show = $event"
    >
        <view class="hsx-component-catalog__body">
            <view class="hsx-component-catalog__search">
                <HsxSearchBar v-model="keyword" :placeholder="placeholder" variant="outline" clearable />
            </view>

            <view v-if="groups.length" class="hsx-component-catalog__groups">
                <view v-for="group in groups" :key="group.label" class="hsx-component-catalog__group">
                    <view class="hsx-component-catalog__group-head">
                        <text class="hsx-component-catalog__group-title">{{ group.label }}</text>
                        <text class="hsx-component-catalog__group-count">{{ group.items.length }}</text>
                    </view>
                    <view class="hsx-component-catalog__grid">
                        <view
                            v-for="row in group.items"
                            :key="row.item.key"
                            class="hsx-component-catalog__item"
                            :class="{ 'is-active': row.item.key === activeKey }"
                            hover-class="hsx-component-catalog__item--pressed"
                            :hover-stay-time="80"
                            @click="select(row.item, row.index)"
                        >
                            <view class="hsx-component-catalog__icon"><HsxIcon :name="row.item.icon || 'grid'" :size="18" /></view>
                            <view class="hsx-component-catalog__content">
                                <view class="hsx-component-catalog__name-row">
                                    <text class="hsx-component-catalog__name">{{ row.item.label }}</text>
                                    <text v-if="row.item.badge !== undefined" class="hsx-component-catalog__badge">{{ row.item.badge }}</text>
                                </view>
                                <text v-if="row.item.description" class="hsx-component-catalog__description">{{ row.item.description }}</text>
                            </view>
                            <HsxIcon name="next" :size="15" color="var(--hsx-mobile-text-tertiary, #a5adba)" />
                        </view>
                    </view>
                </view>
            </view>
            <view v-else class="hsx-component-catalog__empty">没有匹配的组件</view>
        </view>
    </HsxPopup>
</template>

<style scoped lang="scss">
.hsx-component-catalog__floating,
.hsx-component-catalog__inline { display: inline-flex; align-items: center; justify-content: center; gap: 6px; color: #fff; background: linear-gradient(135deg, #2563eb, #3c9cff); font-size: 13px; font-weight: 650; box-shadow: 0 8px 20px rgba(37, 99, 235, .28); }
.hsx-component-catalog__floating { position: fixed; z-index: 90; right: 14px; bottom: calc(18px + env(safe-area-inset-bottom)); min-width: 68px; height: 40px; box-sizing: border-box; padding: 0 13px; border: 1px solid rgba(255, 255, 255, .24); border-radius: 999px; }
.hsx-component-catalog__inline { min-height: 36px; box-sizing: border-box; padding: 0 12px; border-radius: 10px; }
.hsx-component-catalog__floating--pressed,
.hsx-component-catalog__item--pressed { transform: translateY(1px) scale(.975); filter: brightness(.96); }
.hsx-component-catalog__body { min-height: 100%; background: var(--hsx-mobile-bg-page, #f3f6fa); }
.hsx-component-catalog__search { position: sticky; z-index: 2; top: 0; padding: 12px; border-bottom: 1px solid var(--hsx-mobile-border, #e6ebf2); background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-component-catalog__groups { padding: 14px 12px calc(20px + env(safe-area-inset-bottom)); }
.hsx-component-catalog__group + .hsx-component-catalog__group { margin-top: 18px; }
.hsx-component-catalog__group-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 9px; padding: 0 2px; }
.hsx-component-catalog__group-title { color: var(--hsx-mobile-text-primary, #172033); font-size: 15px; font-weight: 700; }
.hsx-component-catalog__group-count { min-width: 22px; height: 22px; padding: 0 6px; border-radius: 999px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); font-size: 11px; line-height: 22px; text-align: center; }
.hsx-component-catalog__grid { display: flex; flex-wrap: wrap; gap: 9px; }
.hsx-component-catalog__item { display: flex; width: calc(50% - 5px); min-width: 0; min-height: 72px; box-sizing: border-box; align-items: center; gap: 9px; padding: 11px; border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 13px; background: var(--hsx-mobile-bg-surface, #fff); box-shadow: 0 4px 12px rgba(22, 42, 83, .04); transition: transform 120ms ease, border-color 120ms ease; }
.hsx-component-catalog__item.is-active { border-color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #edf3ff); }
.hsx-component-catalog__icon { display: flex; width: 34px; height: 34px; flex: none; align-items: center; justify-content: center; border-radius: 10px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); }
.hsx-component-catalog__content { min-width: 0; flex: 1; }
.hsx-component-catalog__name-row { display: flex; min-width: 0; align-items: center; gap: 5px; }
.hsx-component-catalog__name { overflow: hidden; color: var(--hsx-mobile-text-primary, #172033); font-size: 13px; font-weight: 650; text-overflow: ellipsis; white-space: nowrap; }
.hsx-component-catalog__badge { flex: none; padding: 1px 5px; border-radius: 999px; color: var(--hsx-mobile-primary, #2563eb); background: var(--hsx-mobile-primary-soft, #eaf1ff); font-size: 9px; }
.hsx-component-catalog__description { display: -webkit-box; margin-top: 4px; overflow: hidden; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: 10px; line-height: 1.35; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-component-catalog__empty { padding: 80px 20px; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: 13px; text-align: center; }
@media (min-width: 600px) {
    .hsx-component-catalog__item { width: calc(33.333% - 6px); }
}
</style>
