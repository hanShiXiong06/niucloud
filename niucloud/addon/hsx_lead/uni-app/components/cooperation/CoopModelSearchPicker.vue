<template><ModelCatalogPicker :show="show" :page="page" :model-value="modelValue" :title="title" :subtitle="subtitle" :max="max" :business-type="businessType" :selection-mode="selectionMode" @update:model-value="$emit('update:modelValue', $event)" @close="$emit('close')" /></template>
<script setup lang="ts">
import ModelCatalogPicker from './ModelCatalogPicker.vue'
import type { CoopRow } from '../../api/cooperation'
withDefaults(defineProps<{ show: boolean; page?: boolean; modelValue: CoopRow[]; title?: string; subtitle?: string; max?: number; businessType?: string; selectionMode?: 'single' | 'multiple' }>(), { title: '选择意向', subtitle: '', max: 0, businessType: 'purchase', selectionMode: 'multiple' })
defineEmits<{ (event: 'close'): void; (event: 'update:modelValue', value: CoopRow[]): void }>()
</script>
