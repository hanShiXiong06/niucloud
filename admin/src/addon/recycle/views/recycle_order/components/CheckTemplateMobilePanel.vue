<template>
  <section class="cdd-mobile-schema">
    <div v-if="visibleGroups.length" class="cdd-mobile-schema__nav">
      <button
        v-for="group in visibleGroups"
        :key="groupKey(group)"
        type="button"
        @click="scrollToGroup(group)"
      >
        {{ group.group_name }}
      </button>
    </div>

    <section
      v-for="group in visibleGroups"
      :id="groupDomId(group)"
      :key="groupKey(group)"
      class="cdd-mobile-schema__group"
    >
      <div class="cdd-mobile-schema__title">
        <span>{{ group.group_name }}</span>
        <em>{{ groupCheckedCount(group) }}/{{ group.fields.length }}</em>
      </div>

      <div class="cdd-mobile-schema__fields">
        <div
          v-for="field in group.fields"
          :key="field.field_key"
          class="cdd-mobile-schema__field"
          :class="{ 'is-done': !isEmptyValue(getValue(field)) }"
        >
          <div class="cdd-mobile-schema__label">
            <span>{{ field.field_name }}</span>
            <em v-if="field.unit">{{ field.unit }}</em>
          </div>

          <el-input
            v-if="field.component === 'input'"
            :model-value="getValue(field)"
            :placeholder="field.placeholder"
            clearable
            @update:model-value="value => emitChange(field, value)"
          />

          <el-input
            v-else-if="field.component === 'textarea'"
            :model-value="getValue(field)"
            :placeholder="field.placeholder"
            type="textarea"
            :rows="3"
            resize="none"
            @update:model-value="value => emitChange(field, value)"
          />

          <div v-else-if="field.component === 'number'" class="cdd-mobile-schema__inline">
            <el-input-number
              :model-value="toNumberValue(getValue(field))"
              :min="0"
              controls-position="right"
              class="cdd-mobile-schema__number"
              @update:model-value="value => emitChange(field, value)"
            />
            <span v-if="field.unit">{{ field.unit }}</span>
          </div>

          <el-switch
            v-else-if="field.component === 'switch'"
            :model-value="!!getValue(field)"
            active-text="是"
            inactive-text="否"
            @update:model-value="value => emitChange(field, value)"
          />

          <el-select
            v-else-if="field.component === 'select'"
            :model-value="normalizeSingleValue(getValue(field))"
            :placeholder="field.placeholder || '请选择'"
            clearable
            class="cdd-mobile-schema__select"
            @update:model-value="value => emitChange(field, value)"
          >
            <el-option
              v-for="option in getOptions(field)"
              :key="String(option.value)"
              :label="option.name || option.label"
              :value="String(option.value)"
            />
          </el-select>

          <el-radio-group
            v-else-if="field.component === 'radio'"
            :model-value="normalizeSingleValue(getValue(field))"
            class="cdd-mobile-schema__options"
            @update:model-value="value => emitChange(field, value)"
          >
            <el-radio-button
              v-for="option in getOptions(field)"
              :key="String(option.value)"
              :label="String(option.value)"
            >
              {{ option.name || option.label }}
            </el-radio-button>
          </el-radio-group>

          <el-checkbox-group
            v-else-if="field.component === 'checkbox'"
            :model-value="normalizeArrayValue(getValue(field))"
            class="cdd-mobile-schema__options"
            @update:model-value="value => emitChange(field, value)"
          >
            <el-checkbox-button
              v-for="option in getOptions(field)"
              :key="String(option.value)"
              :label="String(option.value)"
            >
              {{ option.name || option.label }}
            </el-checkbox-button>
          </el-checkbox-group>

          <el-input
            v-else
            :model-value="getValue(field)"
            :placeholder="field.placeholder"
            clearable
            @update:model-value="value => emitChange(field, value)"
          />
        </div>
      </div>
    </section>

    <section v-if="!visibleGroups.length" class="cdd-mobile-schema__empty">
      当前模板没有可填写的质检项
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface CheckTemplateOption {
  name?: string
  label?: string
  value: string | number
}

interface CheckTemplateField {
  id?: number | string
  field_key: string
  field_name: string
  component: string
  unit?: string
  placeholder?: string
  options?: CheckTemplateOption[]
}

interface CheckTemplateGroup {
  id?: number | string
  group_key?: string
  group_name: string
  fields?: CheckTemplateField[]
}

const props = defineProps<{
  groups: CheckTemplateGroup[]
  getValue: (field: CheckTemplateField) => any
  getOptions: (field: CheckTemplateField) => CheckTemplateOption[]
}>()

const emit = defineEmits(['change'])

const visibleGroups = computed(() => {
  return (props.groups || [])
    .map(group => ({
      ...group,
      fields: (group.fields || []).filter(field => field && field.field_key)
    }))
    .filter(group => group.fields.length)
})

const groupKey = (group: CheckTemplateGroup) => String(group.id || group.group_key || group.group_name)

const groupDomId = (group: CheckTemplateGroup) => {
  return `cdd-mobile-schema-group-${groupKey(group).replace(/[^a-zA-Z0-9_-]/g, '-')}`
}

const scrollToGroup = (group: CheckTemplateGroup) => {
  document.getElementById(groupDomId(group))?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const isEmptyValue = (value: any) => {
  if (value === '' || value === null || value === undefined) return true
  return Array.isArray(value) && value.length === 0
}

const groupCheckedCount = (group: CheckTemplateGroup) => {
  return (group.fields || []).filter(field => !isEmptyValue(props.getValue(field))).length
}

const normalizeSingleValue = (value: any) => {
  if (value === null || value === undefined) return ''
  return String(value)
}

const normalizeArrayValue = (value: any) => {
  if (!Array.isArray(value)) return []
  return value.map(item => String(item))
}

const toNumberValue = (value: any) => {
  if (value === '' || value === null || value === undefined) return undefined
  const parsed = Number(value)
  return Number.isNaN(parsed) ? undefined : parsed
}

const emitChange = (field: CheckTemplateField, value: any) => {
  emit('change', field, value)
}
</script>

<style scoped lang="scss">
.cdd-mobile-schema {
  display: grid;
  gap: 10px;
}

.cdd-mobile-schema__nav {
  position: sticky;
  top: 0;
  z-index: 3;
  display: flex;
  gap: 8px;
  padding: 8px;
  overflow-x: auto;
  border: 1px solid #dbe4ef;
  border-radius: 10px;
  background: #ffffff;
  -webkit-overflow-scrolling: touch;

  button {
    flex: 0 0 auto;
    min-height: 30px;
    padding: 0 11px;
    border: 1px solid #d8dee8;
    border-radius: 7px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
    font-weight: 600;
  }
}

.cdd-mobile-schema__group {
  scroll-margin-top: 54px;
  overflow: visible;
  border: 1px solid #dbe4ef;
  border-radius: 10px;
  background: #ffffff;
}

.cdd-mobile-schema__title {
  min-height: 40px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 10px 12px;
  border-bottom: 1px solid #edf2f7;
  color: #0f172a;
  font-size: 13px;
  font-weight: 800;

  em {
    color: #64748b;
    font-size: 11px;
    font-style: normal;
    font-weight: 600;
  }
}

.cdd-mobile-schema__fields {
  display: grid;
  gap: 9px;
  padding: 10px;
}

.cdd-mobile-schema__field {
  min-width: 0;
  display: grid;
  gap: 7px;
  padding: 10px;
  border: 1px solid #eef2f7;
  border-radius: 8px;
  background: #fbfdff;

  &.is-done {
    border-color: #bfdbfe;
    background: #f8fbff;
  }
}

.cdd-mobile-schema__label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  color: #334155;
  font-size: 12px;
  font-weight: 700;

  em {
    color: #94a3b8;
    font-size: 11px;
    font-style: normal;
    font-weight: 600;
  }
}

.cdd-mobile-schema__inline {
  display: flex;
  align-items: center;
  gap: 8px;

  span {
    flex: 0 0 auto;
    color: #64748b;
    font-size: 12px;
  }
}

.cdd-mobile-schema__number,
.cdd-mobile-schema__select {
  width: 100%;
}

.cdd-mobile-schema__options {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;

  :deep(.el-radio-button),
  :deep(.el-checkbox-button) {
    margin: 0;
  }

  :deep(.el-radio-button__inner),
  :deep(.el-checkbox-button__inner) {
    border-left: var(--el-border);
    border-radius: 7px;
    padding: 7px 10px;
    line-height: 1.25;
    white-space: normal;
  }
}

.cdd-mobile-schema__empty {
  padding: 28px 12px;
  border: 1px solid #dbe4ef;
  border-radius: 10px;
  background: #ffffff;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}
</style>
