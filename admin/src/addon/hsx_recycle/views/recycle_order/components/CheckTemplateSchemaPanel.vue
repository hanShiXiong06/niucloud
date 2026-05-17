<template>
  <section class="cdd-center">
    <div v-if="visibleGroups.length" class="cdd-anchor-bar">
      <button
        v-for="group in visibleGroups"
        :key="groupKey(group)"
        type="button"
        @click.prevent.stop="scrollToGroup(group)"
      >
        {{ group.group_name }}
      </button>
    </div>

    <div ref="scrollRef" class="cdd-center-scroll" @click.stop>
      <section
        v-for="group in visibleGroups"
        :id="groupDomId(group)"
        :key="groupKey(group)"
        class="cdd-section cdd-work-section"
      >
        <div class="cdd-section-title">
          <span>{{ group.group_name }}</span>
          <em>{{ groupCheckedCount(group) }}/{{ group.fields.length }}</em>
        </div>

        <div class="cdd-schema-grid">
          <div
            v-for="field in group.fields"
            :key="field.field_key"
            class="cdd-schema-field"
            :class="{
              'cdd-schema-field--wide': field.component === 'textarea',
              'cdd-schema-field--done': !isEmptyValue(getValue(field))
            }"
          >
            <div class="cdd-schema-field__label">
              <span>{{ field.field_name }}</span>
              <em v-if="field.unit">{{ field.unit }}</em>
            </div>

            <el-input
              v-if="field.component === 'input'"
              :model-value="getValue(field)"
              :placeholder="field.placeholder"
              size="small"
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

            <div v-else-if="field.component === 'number'" class="cdd-inline-control">
              <el-input-number
                :model-value="toNumberValue(getValue(field))"
                :min="0"
                controls-position="right"
                size="small"
                class="cdd-schema-field__number"
                @update:model-value="value => emitChange(field, value)"
              />
              <span v-if="field.unit">{{ field.unit }}</span>
            </div>

            <el-switch
              v-else-if="field.component === 'switch'"
              :model-value="!!getValue(field)"
              active-text="是"
              inactive-text="否"
              size="small"
              @update:model-value="value => emitChange(field, value)"
            />

            <el-select
              v-else-if="field.component === 'select'"
              :model-value="normalizeSingleValue(getValue(field))"
              :placeholder="field.placeholder || '请选择'"
              size="small"
              clearable
              class="cdd-schema-field__select"
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
              size="small"
              class="cdd-option-group"
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
              size="small"
              class="cdd-option-group"
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
              size="small"
              clearable
              @update:model-value="value => emitChange(field, value)"
            />
          </div>
        </div>
      </section>

      <section v-if="!visibleGroups.length" class="cdd-section cdd-work-section cdd-schema-empty">
        <div class="cdd-section-title">质检模板</div>
        <div class="cdd-schema-empty__body">当前模板没有可填写的质检项</div>
      </section>
    </div>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

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
  sort?: number
  options?: CheckTemplateOption[]
}

interface CheckTemplateGroup {
  id?: number | string
  group_key?: string
  group_name: string
  sort?: number
  fields?: CheckTemplateField[]
}

const props = defineProps<{
  groups: CheckTemplateGroup[]
  getValue: (field: CheckTemplateField) => any
  getOptions: (field: CheckTemplateField) => CheckTemplateOption[]
}>()

const emit = defineEmits(['change'])

const scrollRef = ref<HTMLElement | null>(null)

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
  return `cdd-schema-group-${groupKey(group).replace(/[^a-zA-Z0-9_-]/g, '-')}`
}

const scrollToGroup = (group: CheckTemplateGroup) => {
  const scrollEl = scrollRef.value
  const target = document.getElementById(groupDomId(group))
  if (!scrollEl || !target) return
  const offset = target.offsetTop - scrollEl.offsetTop
  scrollEl.scrollTo({ top: Math.max(offset - 8, 0), behavior: 'smooth' })
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
.cdd-center {
  height: 100%;
  min-width: 0;
  min-height: 0;
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  gap: 8px;
  overflow: hidden;
}

.cdd-anchor-bar {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 6px;
  min-width: 0;
  overflow-x: auto;
  padding: 8px 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;

  button {
    flex: 0 0 auto;
    height: 28px;
    padding: 0 10px;
    border: 1px solid #d8dee8;
    border-radius: 6px;
    background: #f8fafc;
    color: #334155;
    font-size: 12px;
    cursor: pointer;

    &:hover {
      border-color: #2563eb;
      color: #2563eb;
      background: #eff6ff;
    }
  }
}

.cdd-center-scroll {
  flex: 1 1 auto;
  min-height: 0;
  max-height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;
  padding-right: 4px;
  display: flex;
  flex-direction: column;
  gap: 10px;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
}

.cdd-section {
  flex: 0 0 auto;
  min-height: max-content;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
  overflow: visible;
}

.cdd-section-title {
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 10px;
  padding: 9px 12px;
  border-bottom: 1px solid #eef2f7;
  background: #f8fafc;
  color: #111827;
  font-size: 13px;
  font-weight: 700;

  em {
    color: #64748b;
    font-size: 11px;
    font-style: normal;
    font-weight: 600;
  }
}

.cdd-schema-grid {
  min-height: max-content;
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  padding: 12px;
}

.cdd-schema-field {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 7px;
  padding: 10px;
  border: 1px solid #eef2f7;
  border-radius: 8px;
  background: #fbfdff;

  &--wide {
    grid-column: 1 / -1;
  }

  &--done {
    border-color: #bfdbfe;
    background: #f8fbff;
  }
}

.cdd-schema-field__label {
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

.cdd-inline-control {
  display: flex;
  align-items: center;
  gap: 8px;

  span {
    flex: 0 0 auto;
    color: #64748b;
    font-size: 12px;
  }
}

.cdd-schema-field__number,
.cdd-schema-field__select {
  width: 100%;
}

.cdd-option-group {
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
    border-radius: 6px;
    padding: 6px 9px;
    line-height: 1.2;
  }
}

.cdd-schema-empty__body {
  padding: 28px 16px;
  color: #94a3b8;
  font-size: 13px;
  text-align: center;
}

@media (max-width: 900px) {
  .cdd-schema-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .cdd-center {
    height: auto;
    min-height: max-content;
    display: block;
    overflow: visible;
    overscroll-behavior: auto;
    touch-action: pan-y;
  }

  .cdd-anchor-bar {
    position: sticky;
    top: 0;
    z-index: 2;
    margin-bottom: 8px;
  }

  .cdd-center-scroll {
    height: auto;
    max-height: none;
    min-height: max-content;
    overflow: visible;
    overscroll-behavior: auto;
    touch-action: pan-y;
    padding-right: 0;
  }

  .cdd-section,
  .cdd-schema-grid,
  .cdd-schema-field {
    overscroll-behavior: auto;
    touch-action: pan-y;
  }
}
</style>
