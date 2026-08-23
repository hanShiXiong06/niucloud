<script lang="ts">
export default { name: 'HsxFilterDrawer' }
</script>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue'
import type { AnyRecord, MobileFormField } from '../../types'
import { compactMobileFilters, countActiveMobileFilters, deepClone, isDeepEqual } from '../../utils'
import HsxButton from '../HsxButton/index.vue'
import HsxIcon from '../HsxIcon/index.vue'
import HsxPopup from '../HsxPopup/index.vue'
// 小程序必须显式引用 SFC 门面，否则 render-function 入口不会进入 usingComponents。
import HsxSchemaForm from '../HsxSchemaForm/index.vue'

const props = withDefaults(defineProps<{
    visible: boolean
    modelValue?: AnyRecord
    schema?: MobileFormField[]
    title?: string
    description?: string
    resetValue?: AnyRecord
    clean?: boolean
    validate?: boolean
    closeOnReset?: boolean
    adaptiveAt?: number
    adaptiveWidth?: number
    height?: string | number
    zIndex?: string | number
    ignoredCountKeys?: string[]
}>(), {
    modelValue: () => ({}),
    schema: () => [],
    title: '高级筛选',
    description: '组合条件精确定位结果',
    resetValue: () => ({}),
    clean: true,
    validate: false,
    closeOnReset: false,
    adaptiveAt: 600,
    adaptiveWidth: 460,
    height: '82vh',
    zIndex: 10280,
    ignoredCountKeys: () => []
})

const emit = defineEmits<{
    (event: 'update:visible', value: boolean): void
    (event: 'update:modelValue', value: AnyRecord): void
    (event: 'confirm', value: AnyRecord): void
    (event: 'reset', value: AnyRecord): void
    (event: 'change', prop: string, value: any, model: AnyRecord): void
}>()

const formRef = ref<any>()
const draft = ref<AnyRecord>({})
const activeCount = computed(() => countActiveMobileFilters(draft.value, props.ignoredCountKeys))

function fillDraft(value: AnyRecord) {
    const next = deepClone(value || {})
    if (!isDeepEqual(draft.value, next)) draft.value = next
}

watch(() => props.visible, (visible) => {
    if (visible) fillDraft(props.modelValue)
}, { immediate: true })

watch(() => props.modelValue, (value) => {
    if (props.visible && !isDeepEqual(value, draft.value)) fillDraft(value)
}, { deep: true })

function close() {
    emit('update:visible', false)
}

async function confirm() {
    if (props.validate) {
        const valid = await formRef.value?.validate?.()
        if (valid === false) return
    }
    const result = props.clean ? compactMobileFilters(draft.value) : deepClone(draft.value)
    emit('update:modelValue', result as AnyRecord)
    emit('confirm', result as AnyRecord)
    close()
}

async function reset() {
    fillDraft(props.resetValue)
    await nextTick()
    await formRef.value?.resetFields?.(deepClone(props.resetValue))
    const result = deepClone(props.resetValue)
    emit('update:modelValue', result)
    emit('reset', result)
    if (props.closeOnReset) close()
}

function handleFormChange(prop: string, value: any, model: AnyRecord) {
    emit('change', prop, value, model)
}

defineExpose({ open: () => emit('update:visible', true), close, confirm, reset, draft, activeCount, formRef })
</script>

<template>
    <HsxPopup
        :model-value="visible"
        mode="bottom"
        adaptive="side"
        :adaptive-at="adaptiveAt"
        :adaptive-width="adaptiveWidth"
        :height="height"
        adaptive-height="100vh"
        :z-index="zIndex"
        :closeable="false"
        body-padding="0"
        @update:model-value="emit('update:visible', $event)"
    >
        <template #header="{ close: closePopup }">
            <view class="hsx-filter-drawer__header">
                <view class="hsx-filter-drawer__heading">
                    <view class="hsx-filter-drawer__title-row">
                        <text class="hsx-filter-drawer__title">{{ title }}</text>
                        <text v-if="activeCount" class="hsx-filter-drawer__count">{{ activeCount }}</text>
                    </view>
                    <text v-if="description" class="hsx-filter-drawer__description">{{ description }}</text>
                </view>
                <HsxIcon name="close" clickable label="关闭筛选" @click="closePopup" />
            </view>
        </template>

        <view class="hsx-filter-drawer__body">
            <slot name="before" :model="draft" />
            <view v-if="schema.length" class="hsx-filter-drawer__form">
                <slot name="form" :model="draft" :set-model="fillDraft">
                    <HsxSchemaForm ref="formRef" v-model="draft" :schema="schema" label-position="top" @change="handleFormChange" />
                </slot>
            </view>
            <slot :model="draft" :set-model="fillDraft" />
            <slot name="after" :model="draft" />
        </view>

        <template #footer>
            <slot name="footer" :model="draft" :reset="reset" :confirm="confirm" :close="close">
                <view class="hsx-filter-drawer__footer">
                    <HsxButton block @click="reset">重置</HsxButton>
                    <HsxButton type="primary" block @click="confirm">查看结果<text v-if="activeCount">（{{ activeCount }}）</text></HsxButton>
                </view>
            </slot>
        </template>
    </HsxPopup>
</template>

<style scoped lang="scss">
.hsx-filter-drawer__header { display: flex; width: 100%; min-width: 0; min-height: 72px; box-sizing: border-box; align-items: center; justify-content: space-between; gap: 16px; padding: 13px 18px 11px; }
.hsx-filter-drawer__heading { min-width: 0; flex: 1; }
.hsx-filter-drawer__title-row { display: flex; align-items: center; gap: 8px; }
.hsx-filter-drawer__title,
.hsx-filter-drawer__description { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hsx-filter-drawer__title { color: var(--hsx-mobile-text-primary, #172033); font-size: var(--hsx-mobile-font-title, 18px); font-weight: 700; }
.hsx-filter-drawer__description { margin-top: 4px; color: var(--hsx-mobile-text-secondary, #8a94a5); font-size: var(--hsx-mobile-font-caption, 12px); }
.hsx-filter-drawer__count { display: inline-flex; min-width: 21px; height: 21px; box-sizing: border-box; align-items: center; justify-content: center; padding: 0 6px; border-radius: 999px; color: #fff; background: var(--hsx-mobile-primary, #2563eb); font-size: 11px; font-weight: 700; }
.hsx-filter-drawer__body { min-height: 100%; box-sizing: border-box; padding: 12px; background: var(--hsx-mobile-bg-page, #f2f5fa); }
.hsx-filter-drawer__form { overflow: hidden; padding: 4px 14px; border: 1px solid var(--hsx-mobile-border, #e6ebf2); border-radius: 16px; background: var(--hsx-mobile-bg-surface, #fff); }
.hsx-filter-drawer__footer { display: grid; width: 100%; grid-template-columns: minmax(96px, .72fr) minmax(150px, 1.28fr); gap: 10px; }
@media (min-width: 600px) {
    .hsx-filter-drawer__body { padding: 16px; }
    .hsx-filter-drawer__form { padding: 6px 16px; }
}
</style>
