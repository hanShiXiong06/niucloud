<script lang="ts">
export default { name: 'HsxCascader' }
</script>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxPopup from '../HsxPopup/index.vue'
import type {
    MobileCascaderLoadContext,
    MobileCascaderNodeValue,
    MobileCascaderOption
} from '../../types'
import { deepClone } from '../../utils'
import { resolveMobileThemeVariables, useMobileTheme } from '../../hooks/useMobileTheme'

const props = withDefaults(
    defineProps<{
        modelValue?: MobileCascaderNodeValue[]
        options?: MobileCascaderOption[]
        fetchOptions?: (parent: MobileCascaderOption | null, context: MobileCascaderLoadContext) => Promise<MobileCascaderOption[]>
        resolvePath?: (value: MobileCascaderNodeValue[]) => Promise<MobileCascaderOption[]>
        labelKey?: string
        valueKey?: string
        childrenKey?: string
        leafKey?: string
        title?: string
        placeholder?: string
        separator?: string
        maxLevel?: number
        clearable?: boolean
        disabled?: boolean
        showAllLevels?: boolean
        searchable?: boolean
        zIndex?: string | number
    }>(),
    {
        modelValue: () => [],
        options: () => [],
        labelKey: 'label',
        valueKey: 'value',
        childrenKey: 'children',
        leafKey: 'leaf',
        title: '请选择',
        placeholder: '请选择',
        separator: ' / ',
        maxLevel: 8,
        clearable: true,
        disabled: false,
        showAllLevels: false,
        searchable: true,
        zIndex: 10240
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: MobileCascaderNodeValue[]): void
    (event: 'change', value: MobileCascaderNodeValue[], path: MobileCascaderOption[]): void
    (event: 'open'): void
    (event: 'close'): void
    (event: 'load', options: MobileCascaderOption[], parent: MobileCascaderOption | null): void
    (event: 'error', error: unknown, stage: 'load' | 'resolve'): void
}>()

const visible = ref(false)
const themeMode = useMobileTheme()
const portalThemeStyle = computed(() => resolveMobileThemeVariables(themeMode.value))
const loading = ref(false)
const keyword = ref('')
const displayPath = ref<MobileCascaderOption[]>([])
const parentPath = ref<MobileCascaderOption[]>([])
const selectedLeaf = ref<MobileCascaderOption | null>(null)
const currentOptions = ref<MobileCascaderOption[]>([])
let operationVersion = 0

const labels = computed(() => displayPath.value.map(optionLabel).filter(Boolean))
const displayText = computed(() => props.showAllLevels ? labels.value.join(props.separator) : labels.value[labels.value.length - 1] || '')
const draftPath = computed(() => selectedLeaf.value ? [...parentPath.value, selectedLeaf.value] : parentPath.value)
const selectedText = computed(() => draftPath.value.map(optionLabel).filter(Boolean).join(props.separator))
const filteredOptions = computed(() => {
    const search = keyword.value.trim().toLowerCase()
    if (!search) return currentOptions.value
    return currentOptions.value.filter((option) => optionLabel(option).toLowerCase().includes(search))
})

watch(() => props.modelValue, () => void resolveDisplayPath(), { immediate: true, deep: true })
watch(() => props.options, () => void resolveDisplayPath(), { deep: true })

function optionLabel(option: MobileCascaderOption) {
    return String(option?.[props.labelKey] ?? '')
}

function optionValue(option: MobileCascaderOption) {
    return option?.[props.valueKey] as MobileCascaderNodeValue
}

function optionChildren(option: MobileCascaderOption): MobileCascaderOption[] {
    return (option?.[props.childrenKey] as MobileCascaderOption[]) || []
}

function optionLeaf(option: MobileCascaderOption) {
    return Boolean(option?.[props.leafKey])
}

function sameOption(left: MobileCascaderOption | null, right: MobileCascaderOption | null) {
    return left && right ? optionValue(left) === optionValue(right) : left === right
}

function findStaticPath(values: MobileCascaderNodeValue[]) {
    const path: MobileCascaderOption[] = []
    let options = props.options
    for (const value of values) {
        const option = options.find((item) => optionValue(item) === value)
        if (!option) break
        path.push(option)
        options = optionChildren(option)
    }
    return path
}

async function resolveDisplayPath() {
    if (!props.modelValue.length) {
        displayPath.value = []
        return []
    }
    if (!props.resolvePath) {
        displayPath.value = deepClone(findStaticPath(props.modelValue))
        return displayPath.value
    }
    try {
        displayPath.value = deepClone(await props.resolvePath(props.modelValue))
        return displayPath.value
    } catch (error) {
        emit('error', error, 'resolve')
        return displayPath.value
    }
}

async function requestOptions(parent: MobileCascaderOption | null, path: MobileCascaderOption[], level: number) {
    const children = parent ? optionChildren(parent) : props.options
    if (children.length) return deepClone(children)
    if (!props.fetchOptions) return []
    const options = await props.fetchOptions(parent, { level, path: deepClone(path) })
    emit('load', options, parent)
    return deepClone(options)
}

async function loadLevel(path: MobileCascaderOption[]) {
    const version = ++operationVersion
    loading.value = true
    keyword.value = ''
    try {
        const parent = path[path.length - 1] || null
        const options = await requestOptions(parent, path, path.length)
        if (version === operationVersion) currentOptions.value = options
        return options
    } catch (error) {
        if (version === operationVersion) currentOptions.value = []
        emit('error', error, 'load')
        return []
    } finally {
        if (version === operationVersion) loading.value = false
    }
}

async function open() {
    if (props.disabled) return
    visible.value = true
    emit('open')
    const resolved = await resolveDisplayPath()
    const last = resolved[resolved.length - 1]
    if (last && (optionLeaf(last) || resolved.length >= props.maxLevel)) {
        parentPath.value = deepClone(resolved.slice(0, -1))
        selectedLeaf.value = deepClone(last)
    } else {
        parentPath.value = deepClone(resolved)
        selectedLeaf.value = null
    }
    await loadLevel(parentPath.value)
}

function close() {
    visible.value = false
    emit('close')
}

function handlePopupClose() {
    visible.value = false
    emit('close')
}

async function choose(option: MobileCascaderOption) {
    if (option.disabled || loading.value) return
    const nextPath = [...parentPath.value, option]
    if (optionLeaf(option) || nextPath.length >= props.maxLevel) {
        selectedLeaf.value = deepClone(option)
        return
    }

    loading.value = true
    let children: MobileCascaderOption[] = []
    try {
        children = await requestOptions(option, nextPath, nextPath.length)
    } catch (error) {
        emit('error', error, 'load')
    } finally {
        loading.value = false
    }
    if (!children.length) {
        selectedLeaf.value = deepClone(option)
        return
    }
    parentPath.value = deepClone(nextPath)
    selectedLeaf.value = null
    currentOptions.value = children
    keyword.value = ''
}

async function previousLevel() {
    if (!parentPath.value.length || loading.value) return
    parentPath.value = parentPath.value.slice(0, -1)
    selectedLeaf.value = null
    await loadLevel(parentPath.value)
}

async function resetToRoot() {
    parentPath.value = []
    selectedLeaf.value = null
    await loadLevel([])
}

function confirm() {
    const path = deepClone(draftPath.value)
    if (!path.length || !selectedLeaf.value) return
    const value = path.map(optionValue)
    displayPath.value = path
    emit('update:modelValue', value)
    emit('change', value, path)
    close()
}

function clear(event?: any) {
    event?.stopPropagation?.()
    displayPath.value = []
    parentPath.value = []
    selectedLeaf.value = null
    emit('update:modelValue', [])
    emit('change', [], [])
}

defineExpose({ open, close, clear, reload: () => loadLevel(parentPath.value), displayPath, currentOptions, loading })
</script>

<template>
    <view class="hsx-cascader">
        <view class="hsx-cascader__trigger" :class="{ 'is-disabled': disabled }" @click="open">
            <text class="hsx-cascader__value" :class="{ 'is-placeholder': !displayText }">
                {{ displayText || placeholder }}
            </text>
            <view class="hsx-cascader__suffix">
                <view v-if="clearable && displayText && !disabled" class="hsx-cascader__clear" @click.stop="clear">
                    <HsxIcon name="close" :size="12" color="#fff" />
                </view>
                <HsxIcon v-else name="next" :size="16" color="var(--hsx-mobile-text-tertiary, #b5b8bf)" />
            </view>
        </view>

        <!-- #ifdef H5 -->
        <Teleport to="body">
        <!-- #endif -->
        <view class="hsx-mobile-theme-context" :class="`hsx-mobile-theme--${themeMode}`" :style="portalThemeStyle">
        <HsxPopup
            v-model="visible"
            :title="title"
            height="min(78vh, 680px)"
            :z-index="zIndex"
            body-padding="12px 16px 16px"
            @close="handlePopupClose"
        >
            <view class="hsx-cascader__panel">
                <view class="hsx-cascader__path">
                    <view class="hsx-cascader__path-main">
                        <text class="hsx-cascader__path-label">当前层级</text>
                        <text class="hsx-cascader__path-value">{{ parentPath.map(optionLabel).join(separator) || '全部机型' }}</text>
                    </view>
                    <text v-if="parentPath.length" class="hsx-cascader__path-action" @click="resetToRoot">回到首级</text>
                </view>

                <view v-if="selectedText" class="hsx-cascader__selected">
                    <HsxIcon name="success" :size="17" color="var(--hsx-mobile-success, #16a34a)" />
                    <text>已选：{{ selectedText }}</text>
                </view>

                <view class="hsx-cascader__toolbar">
                    <view v-if="parentPath.length" class="hsx-cascader__back" @click="previousLevel">
                        <HsxIcon name="back" :size="16" />
                        <text>上一层</text>
                    </view>
                    <view v-if="searchable" class="hsx-cascader__search">
                        <u-search v-model="keyword" placeholder="搜索当前层" :show-action="false" bg-color="var(--hsx-mobile-bg-muted, #f4f6f8)" />
                    </view>
                </view>

                <view v-if="loading" class="hsx-cascader__state">
                    <u-loading-icon mode="circle" />
                    <text>正在加载</text>
                </view>
                <view v-else-if="filteredOptions.length" class="hsx-cascader__options">
                    <view
                        v-for="option in filteredOptions"
                        :key="String(optionValue(option))"
                        class="hsx-cascader__option"
                        :class="{
                            'is-selected': sameOption(selectedLeaf, option),
                            'is-disabled': option.disabled
                        }"
                        @click="choose(option)"
                    >
                        <text class="hsx-cascader__option-label">{{ optionLabel(option) }}</text>
                        <HsxIcon
                            :name="sameOption(selectedLeaf, option) ? 'success' : (optionLeaf(option) ? 'success' : 'next')"
                            :size="18"
                            :color="sameOption(selectedLeaf, option) ? 'var(--hsx-mobile-primary, #2563eb)' : 'var(--hsx-mobile-text-tertiary, #a5adba)'"
                        />
                    </view>
                </view>
                <view v-else class="hsx-cascader__state">暂无可选项</view>
            </view>

            <template #footer>
                <view class="hsx-cascader__actions">
                    <button class="hsx-cascader__button is-cancel" @click="close">取消</button>
                    <button class="hsx-cascader__button is-confirm" :disabled="loading || !selectedLeaf" @click="confirm">确定</button>
                </view>
            </template>
        </HsxPopup>
        </view>
        <!-- #ifdef H5 -->
        </Teleport>
        <!-- #endif -->
    </view>
</template>

<style scoped lang="scss">
.hsx-cascader,
.hsx-cascader__trigger { width: 100%; min-width: 0; }
.hsx-cascader__trigger { display: flex; min-height: var(--hsx-mobile-control-height, 40px); box-sizing: border-box; align-items: center; gap: 10px; color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-control, 14px); }
.hsx-cascader__trigger.is-disabled { opacity: .5; }
.hsx-cascader__value { display: -webkit-box; min-width: 0; flex: 1; overflow: hidden; line-height: 1.45; overflow-wrap: anywhere; -webkit-box-orient: vertical; -webkit-line-clamp: 2; }
.hsx-cascader__value.is-placeholder { color: var(--hsx-mobile-text-tertiary, #a5adba); }
.hsx-cascader__suffix { display: flex; flex: none; align-items: center; }
.hsx-cascader__clear { display: flex; width: 20px; height: 20px; align-items: center; justify-content: center; border-radius: 50%; background: #c5cad3; }
.hsx-cascader__path { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border-radius: 10px; background: var(--hsx-mobile-bg-muted, #f5f7fa); }
.hsx-cascader__path-main { display: flex; min-width: 0; flex: 1; flex-direction: column; }
.hsx-cascader__path-label { color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 11px; }
.hsx-cascader__path-value { margin-top: 3px; color: var(--hsx-mobile-text-primary, #172033); font-size: 13px; line-height: 1.45; overflow-wrap: anywhere; }
.hsx-cascader__path-action { flex: none; color: var(--hsx-mobile-primary, #2563eb); font-size: 12px; }
.hsx-cascader__selected { display: flex; align-items: flex-start; gap: 7px; margin-top: 10px; padding: 9px 11px; border-radius: 9px; color: var(--hsx-mobile-success, #16a34a); background: rgba(22, 163, 74, .08); font-size: 12px; line-height: 1.5; }
.hsx-cascader__selected text { min-width: 0; flex: 1; overflow-wrap: anywhere; }
.hsx-cascader__toolbar { display: flex; align-items: center; gap: 10px; margin: 10px 0; }
.hsx-cascader__back { display: flex; min-height: 36px; flex: none; align-items: center; gap: 4px; padding: 0 9px; border-radius: 9px; color: var(--hsx-mobile-text-primary, #172033); background: var(--hsx-mobile-bg-muted, #f4f6f8); font-size: 12px; }
.hsx-cascader__search { min-width: 0; flex: 1; }
.hsx-cascader__options { display: flex; flex-direction: column; gap: 6px; }
.hsx-cascader__option { display: flex; min-height: 50px; box-sizing: border-box; align-items: center; gap: 12px; padding: 11px 13px; border: 1px solid transparent; border-radius: 11px; background: var(--hsx-mobile-bg-muted, #f6f7f9); }
.hsx-cascader__option:active { background: rgba(37, 99, 235, .09); }
.hsx-cascader__option.is-selected { border-color: rgba(37, 99, 235, .3); background: rgba(37, 99, 235, .08); }
.hsx-cascader__option.is-disabled { opacity: .45; }
.hsx-cascader__option-label { min-width: 0; flex: 1; color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-body, 14px); line-height: 1.5; overflow-wrap: anywhere; }
.hsx-cascader__state { display: flex; min-height: 180px; align-items: center; justify-content: center; gap: 8px; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 13px; }
.hsx-cascader__actions { display: flex; gap: 10px; }
.hsx-cascader__button { height: 42px; flex: 1; border: 0; border-radius: 999px; font-size: 14px; line-height: 42px; }
.hsx-cascader__button::after { border: 0; }
.hsx-cascader__button.is-cancel { color: var(--hsx-mobile-text-primary, #172033); background: var(--hsx-mobile-bg-muted, #f3f4f6); }
.hsx-cascader__button.is-confirm { color: #fff; background: var(--hsx-mobile-primary, #2563eb); }
.hsx-cascader__button[disabled] { opacity: .45; }
</style>
