<script lang="ts">
export default { name: 'HsxSelect' }
</script>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { MobileOption } from '../../types'
import { resolveMobileThemeVariables, useMobileTheme } from '../../hooks/useMobileTheme'
import HsxIcon from '../HsxIcon/index.vue'
import HsxPopup from '../HsxPopup/index.vue'

const props = withDefaults(
    defineProps<{
        modelValue?: string | number | boolean
        options?: MobileOption[]
        title?: string
        placeholder?: string
        disabled?: boolean
        clearable?: boolean
        searchable?: boolean
        searchPlaceholder?: string
        emptyText?: string
        zIndex?: string | number
    }>(),
    {
        options: () => [],
        title: '请选择',
        placeholder: '请选择',
        disabled: false,
        clearable: true,
        searchable: false,
        searchPlaceholder: '搜索选项',
        emptyText: '暂无可选项',
        zIndex: 10220
    }
)

const emit = defineEmits<{
    (event: 'update:modelValue', value: string | number | boolean | undefined): void
    (event: 'change', value: string | number | boolean | undefined, option?: MobileOption): void
    (event: 'open'): void
    (event: 'close'): void
}>()

const visible = ref(false)
const themeMode = useMobileTheme()
const portalThemeStyle = computed(() => resolveMobileThemeVariables(themeMode.value))
const keyword = ref('')
const selected = computed(() => props.options.find((option) => option.value === props.modelValue))
const filteredOptions = computed(() => {
    const search = keyword.value.trim().toLowerCase()
    if (!search) return props.options
    return props.options.filter((option) => String(option.label).toLowerCase().includes(search))
})

function open() {
    if (props.disabled) return
    keyword.value = ''
    visible.value = true
    emit('open')
}

function close() {
    visible.value = false
    emit('close')
}

function choose(option: MobileOption) {
    if (option.disabled) return
    emit('update:modelValue', option.value)
    emit('change', option.value, option)
    close()
}

function clear(event?: any) {
    event?.stopPropagation?.()
    emit('update:modelValue', undefined)
    emit('change', undefined)
}

function handlePopupClose() {
    visible.value = false
    emit('close')
}

defineExpose({ open, close, clear })
</script>

<template>
    <view class="hsx-select">
        <view class="hsx-select__trigger" :class="{ 'is-disabled': disabled }" @click="open">
            <text class="hsx-select__value" :class="{ 'is-placeholder': !selected }">
                {{ selected?.label || placeholder }}
            </text>
            <view class="hsx-select__suffix">
                <view v-if="clearable && selected && !disabled" class="hsx-select__clear" @click.stop="clear">
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
            height="min(72vh, 620px)"
            :z-index="zIndex"
            body-padding="12px 16px 20px"
            @close="handlePopupClose"
        >
            <view class="hsx-select__panel">
                <view v-if="searchable" class="hsx-select__search">
                    <u-search
                        v-model="keyword"
                        :placeholder="searchPlaceholder"
                        :show-action="false"
                        bg-color="var(--hsx-mobile-bg-muted, #f4f6f8)"
                    />
                </view>

                <view v-if="filteredOptions.length" class="hsx-select__options">
                    <view
                        v-for="option in filteredOptions"
                        :key="String(option.value)"
                        class="hsx-select__option"
                        :class="{
                            'is-selected': option.value === modelValue,
                            'is-disabled': option.disabled
                        }"
                        @click="choose(option)"
                    >
                        <text class="hsx-select__option-label">{{ option.label }}</text>
                        <HsxIcon
                            v-if="option.value === modelValue"
                            name="success"
                            :size="20"
                            color="var(--hsx-mobile-primary, #2563eb)"
                        />
                    </view>
                </view>
                <view v-else class="hsx-select__empty">{{ emptyText }}</view>
            </view>
        </HsxPopup>
        </view>
        <!-- #ifdef H5 -->
        </Teleport>
        <!-- #endif -->
    </view>
</template>

<style scoped lang="scss">
.hsx-select,
.hsx-select__trigger {
    width: 100%;
    min-width: 0;
}

.hsx-select__trigger {
    display: flex;
    min-height: var(--hsx-mobile-control-height, 40px);
    box-sizing: border-box;
    align-items: center;
    gap: 10px;
    color: var(--hsx-mobile-text-primary, #172033);
    font-size: var(--hsx-mobile-font-control, 14px);
}

.hsx-select__trigger.is-disabled { opacity: .5; }
.hsx-select__value {
    display: -webkit-box;
    min-width: 0;
    flex: 1;
    overflow: hidden;
    line-height: 1.45;
    overflow-wrap: anywhere;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}
.hsx-select__value.is-placeholder { color: var(--hsx-mobile-text-tertiary, #a5adba); }
.hsx-select__suffix { display: flex; flex: none; align-items: center; }
.hsx-select__clear { display: flex; width: 20px; height: 20px; align-items: center; justify-content: center; border-radius: 50%; background: #c5cad3; }
.hsx-select__search { margin-bottom: 10px; }
.hsx-select__options { display: flex; flex-direction: column; gap: 6px; }
.hsx-select__option {
    display: flex;
    min-height: 48px;
    box-sizing: border-box;
    align-items: center;
    gap: 12px;
    padding: 11px 13px;
    border: 1px solid transparent;
    border-radius: 11px;
    background: var(--hsx-mobile-bg-muted, #f6f7f9);
}
.hsx-select__option:active { background: rgba(37, 99, 235, .09); }
.hsx-select__option.is-selected { border-color: rgba(37, 99, 235, .3); background: rgba(37, 99, 235, .08); }
.hsx-select__option.is-disabled { opacity: .45; }
.hsx-select__option-label { min-width: 0; flex: 1; color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-body, 14px); line-height: 1.5; overflow-wrap: anywhere; }
.hsx-select__empty { display: flex; min-height: 180px; align-items: center; justify-content: center; color: var(--hsx-mobile-text-secondary, #8a8f99); font-size: 13px; }
</style>
