<template>
    <view class="coop-business-field">
        <view class="coop-business-field__label"
            ><text>{{ field.label }}</text
            ><text v-if="!field.required" class="coop-business-field__optional">选填</text></view
        >
        <picker
            v-if="field.type === 'select'"
            :range="field.options || []"
            range-key="label"
            :disabled="disabled"
            @change="selectOption"
        >
            <view class="coop-business-field__select" :class="{ 'is-empty': !displayValue, 'is-disabled': disabled }"
                ><text>{{ displayValue || field.placeholder || '请选择' }}</text
                ><u-icon name="arrow-right" size="12" color="#8996a6"
            /></view>
        </picker>
        <view
            v-else-if="field.type === 'address'"
            class="coop-business-field__address"
            :class="{ 'is-disabled': disabled }"
        >
            <textarea
                :value="String(modelValue || '')"
                :disabled="disabled"
                :maxlength="300"
                :auto-height="true"
                :placeholder="field.placeholder || '小区、街道或地标（门牌可不填）'"
                placeholder-class="coop-business-field__placeholder"
                @input="writeAddress"
            />
            <text class="coop-business-field__count">{{ charCount }}/300</text>
        </view>
        <u-input
            v-else
            :model-value="modelValue"
            :custom-style="cooperationInputStyle"
            :type="field.type === 'number' ? 'digit' : 'text'"
            :maxlength="1000"
            :placeholder="field.placeholder || '请填写' + field.label"
            :disabled="disabled"
            @update:model-value="$emit('update:modelValue', $event)"
        />
    </view>
</template>
<script setup lang="ts">
import { computed } from 'vue'
import type { BusinessField } from '../../utils/cooperation-business'
import { businessFieldValue } from '../../utils/cooperation-business'
import { cooperationInputStyle } from '../../utils/mobile-ui'
const props = withDefaults(defineProps<{ field: BusinessField; modelValue?: string | number; disabled?: boolean }>(), { modelValue: '', disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string | number): void }>()
const displayValue = computed(() => businessFieldValue(props.field, { [props.field.key]: props.modelValue }))
const charCount = computed(() => Array.from(String(props.modelValue || '')).length)
function selectOption(event: any) { if (props.disabled) return; const option = props.field.options?.[Number(event.detail.value)]; if (option) emit('update:modelValue', option.value) }
function writeAddress(event: any) { if (props.disabled) return; emit('update:modelValue', String(event.detail.value || '').replace(/[\r\n\t]+/g, ' ')) }
</script>
<style scoped lang="scss">
.coop-business-field { margin-top: 20px; min-width: 0; }
.coop-business-field__label { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; color: #35465c; font-size: 14px; line-height: 22px; }
.coop-business-field__optional { color: #8c99aa; font-size: 12px; }
.coop-business-field__select { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 48px; padding: 10px 12px; box-sizing: border-box; border: 1px solid #dfe6ee; border-radius: 6px; color: #35465c; font-size: 14px; line-height: 22px; }
.coop-business-field__select>text { min-width: 0; overflow-wrap: anywhere; }
.coop-business-field__select.is-empty { color: #9aa6b5; }
.coop-business-field__address { padding: 12px; border: 1px solid #dfe6ee; border-radius: 6px; box-sizing: border-box; background: #fff; }
.coop-business-field__address>textarea { display: block; width: 100%; min-height: 62px; color: #35465c; font-size: 14px; line-height: 22px; box-sizing: border-box; }
.coop-business-field__placeholder { color: #9aa6b5; }
.coop-business-field__count { display: block; text-align: right; color: #97a2b0; font-size: 11px; line-height: 18px; }
.is-disabled { opacity: .6; background: #f7f9fb; }
</style>
