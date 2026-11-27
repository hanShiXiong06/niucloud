<script lang="ts">
export default { name: 'HsxColumnSetting', inheritAttrs: false }
</script>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { ArrowDown, ArrowUp, Setting } from '@element-plus/icons-vue'
import type { HsxColumnSettingItem } from '../../types'
import { deepClone, isDeepEqual } from '../../utils'

const props = withDefaults(
    defineProps<{
        modelValue: HsxColumnSettingItem[]
        title?: string
        storageKey?: string
        allowFixed?: boolean
        minVisible?: number
    }>(),
    {
        title: '自定义列',
        storageKey: '',
        allowFixed: true,
        minVisible: 1
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: HsxColumnSettingItem[]): void
    (event: 'change', value: HsxColumnSettingItem[]): void
    (event: 'reset', value: HsxColumnSettingItem[]): void
}>()

const items = ref<HsxColumnSettingItem[]>([])
const initialItems = ref<HsxColumnSettingItem[]>([])
const visibleCount = computed(() => items.value.filter((item) => item.visible !== false).length)
const selectableItems = computed(() => items.value.filter((item) => !item.required && !item.disabled))
const allChecked = computed(() => selectableItems.value.length > 0 && selectableItems.value.every((item) => item.visible !== false))
const indeterminate = computed(() => selectableItems.value.some((item) => item.visible !== false) && !allChecked.value)

function normalize(source: HsxColumnSettingItem[]): HsxColumnSettingItem[] {
    return source.map((item): HsxColumnSettingItem => ({
        ...deepClone(item),
        visible: item.required ? true : item.visible !== false,
        fixed: item.fixed === 'left' || item.fixed === 'right' ? item.fixed : false
    }))
}

watch(
    () => props.modelValue,
    (value) => {
        const next = normalize(value || [])
        if (!initialItems.value.length) initialItems.value = deepClone(next)
        if (!isDeepEqual(items.value, next)) items.value = next
    },
    { immediate: true, deep: true }
)

function persist(value: HsxColumnSettingItem[]) {
    if (!props.storageKey || typeof window === 'undefined') return
    try {
        window.localStorage.setItem(`hsx:columns:${props.storageKey}`, JSON.stringify(value))
    } catch (_) {
        // 隐私模式或存储已满时，仅保持当前页面设置。
    }
}

function commit(next: HsxColumnSettingItem[]) {
    items.value = normalize(next)
    const value = deepClone(items.value)
    persist(value)
    emit('update:modelValue', value)
    emit('change', value)
}

function setVisible(index: number, visible: boolean) {
    const target = items.value[index]
    if (!target || target.required || target.disabled) return
    if (!visible && visibleCount.value <= props.minVisible) return
    const next = deepClone(items.value)
    next[index].visible = visible
    commit(next)
}

function toggleAll(checked: boolean) {
    const next = deepClone(items.value)
    next.forEach((item) => {
        if (!item.required && !item.disabled) item.visible = checked
    })
    if (!checked && props.minVisible > 0) {
        next.slice(0, props.minVisible).forEach((item) => (item.visible = true))
    }
    commit(next)
}

function setFixed(index: number, fixed: false | 'left' | 'right') {
    const next = deepClone(items.value)
    next[index].fixed = fixed
    commit(next)
}

function move(index: number, offset: -1 | 1) {
    const target = index + offset
    if (target < 0 || target >= items.value.length) return
    const next = deepClone(items.value)
    ;[next[index], next[target]] = [next[target], next[index]]
    commit(next)
}

function reset() {
    const next = normalize(initialItems.value)
    if (props.storageKey && typeof window !== 'undefined') {
        window.localStorage.removeItem(`hsx:columns:${props.storageKey}`)
    }
    commit(next)
    emit('reset', deepClone(next))
}

onMounted(() => {
    if (!props.storageKey || typeof window === 'undefined') return
    try {
        const saved = JSON.parse(window.localStorage.getItem(`hsx:columns:${props.storageKey}`) || '[]') as HsxColumnSettingItem[]
        if (!saved.length) return
        const sourceMap = new Map(items.value.map((item) => [item.key, item]))
        const restored: HsxColumnSettingItem[] = saved
            .filter((item) => sourceMap.has(item.key))
            .map((item): HsxColumnSettingItem => ({
                ...sourceMap.get(item.key)!,
                visible: item.visible,
                fixed: item.fixed === 'left' || item.fixed === 'right' ? item.fixed : false
            }))
        items.value.forEach((item) => {
            if (!restored.some((savedItem) => savedItem.key === item.key)) restored.push(item)
        })
        commit(restored)
    } catch (_) {
        // 本地配置损坏时使用组件默认列。
    }
})

defineExpose({ items, reset })
</script>

<template>
    <el-popover placement="bottom-end" :width="380" trigger="click">
        <template #reference>
            <el-button v-bind="$attrs" :icon="Setting">{{ title }}</el-button>
        </template>

        <div class="hsx-column-setting">
            <div class="hsx-column-setting__header">
                <el-checkbox
                    :model-value="allChecked"
                    :indeterminate="indeterminate"
                    @update:model-value="toggleAll(Boolean($event))"
                >全部显示</el-checkbox>
                <el-button link type="primary" @click="reset">恢复默认</el-button>
            </div>

            <div class="hsx-column-setting__list">
                <div v-for="(item, index) in items" :key="item.key" class="hsx-column-setting__item">
                    <el-checkbox
                        class="hsx-column-setting__check"
                        :model-value="item.visible !== false"
                        :disabled="item.required || item.disabled || (item.visible !== false && visibleCount <= minVisible)"
                        @update:model-value="setVisible(index, Boolean($event))"
                    >{{ item.label }}</el-checkbox>

                    <el-select
                        v-if="allowFixed"
                        class="hsx-column-setting__fixed"
                        size="small"
                        :model-value="item.fixed || false"
                        @update:model-value="setFixed(index, $event)"
                    >
                        <el-option label="不固定" :value="false" />
                        <el-option label="固定左侧" value="left" />
                        <el-option label="固定右侧" value="right" />
                    </el-select>

                    <el-button-group>
                        <el-button size="small" :icon="ArrowUp" :disabled="index === 0" @click="move(index, -1)" />
                        <el-button size="small" :icon="ArrowDown" :disabled="index === items.length - 1" @click="move(index, 1)" />
                    </el-button-group>
                </div>
            </div>
        </div>
    </el-popover>
</template>

<style scoped>
.hsx-column-setting__header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid var(--el-border-color-lighter); }
.hsx-column-setting__list { max-height: 360px; padding-top: 8px; overflow: auto; }
.hsx-column-setting__item { display: flex; min-height: 40px; align-items: center; gap: 8px; }
.hsx-column-setting__check { min-width: 0; flex: 1; }
.hsx-column-setting__check :deep(.el-checkbox__label) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-column-setting__fixed { width: 105px; }
</style>
