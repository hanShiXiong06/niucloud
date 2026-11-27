<template>
  <el-popover
    placement="bottom-end"
    trigger="hover"
    :width="280"
    popper-class="check-template-selector-popover"
  >
    <template #reference>
      <div class="check-template-selector">
        <div class="check-template-selector__main">
          <span>质检模板</span>
          <strong>{{ templateInfo?.template_name || '未选择模板' }}</strong>
        </div>
        <el-select
          :model-value="modelValue"
          size="small"
          filterable
          remote
          :remote-method="handleSearch"
          class="check-template-selector__select"
          :loading="loading"
          placeholder="搜索模板"
          @change="handleChange"
        >
          <el-option
            v-for="template in templates"
            :key="template.id"
            :label="template.template_name"
            :value="Number(template.id)"
          >
            <div class="check-template-option">
              <span>{{ template.template_name }}</span>
              <em>{{ sceneText(template.scene) }}{{ Number(template.is_default) === 1 ? ' / 默认' : '' }}</em>
            </div>
          </el-option>
        </el-select>
      </div>
    </template>

    <div class="check-template-popover">
      <div class="check-template-popover__title">{{ templateInfo?.template_name || '未选择模板' }}</div>
      <div class="check-template-popover__grid">
        <div>
          <span>适用场景</span>
          <strong>{{ sceneText(templateInfo?.scene) }}</strong>
        </div>
        <div>
          <span>模板结构</span>
          <strong>{{ groupCount }} 组 / {{ fieldCount }} 项</strong>
        </div>
        <div>
          <span>可选模板</span>
          <strong>{{ templates.length }} 个</strong>
        </div>
        <div>
          <span>当前状态</span>
          <strong>{{ Number(templateInfo?.is_default) === 1 ? '默认模板' : '自定义模板' }}</strong>
        </div>
      </div>
      <div class="check-template-popover__tip">切换模板会重新加载质检项，设备基础信息会保留。</div>
    </div>
  </el-popover>
</template>

<script setup lang="ts">
const props = defineProps<{
  modelValue: number
  templateInfo: Record<string, any> | null
  templates: any[]
  groupCount: number
  fieldCount: number
  loading?: boolean
}>()

const emit = defineEmits<{
  (event: 'update:modelValue', value: number): void
  (event: 'change', value: number): void
  (event: 'search', keyword: string): void
}>()

const handleSearch = (keyword: string) => {
  emit('search', keyword)
}

const sceneText = (scene?: string) => {
  const sceneMap: Record<string, string> = {
    phone: '手机',
    fold: '折叠屏',
    watch: '手表',
    tablet: '平板',
    computer: '电脑',
    common: '通用',
    pjt: '拍机堂'
  }
  return sceneMap[String(scene || '')] || '通用'
}

const handleChange = (value: number) => {
  emit('update:modelValue', value)
  emit('change', value)
}
</script>

<style lang="scss" scoped>
.check-template-selector {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  width: 284px;
  min-width: 0;
  padding: 4px 6px 4px 9px;
  border: 1px solid #dbeafe;
  border-radius: 8px;
  background: #f8fbff;
}

.check-template-selector__main {
  min-width: 0;
  flex: 1;
  line-height: 1.15;

  span {
    display: block;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
  }

  strong {
    display: block;
    overflow: hidden;
    color: #0f172a;
    font-size: 13px;
    font-weight: 700;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.check-template-selector__select {
  width: 122px;
  flex: 0 0 auto;
}

.check-template-option {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  span {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  em {
    color: #94a3b8;
    font-size: 12px;
    font-style: normal;
  }
}

.check-template-popover {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.check-template-popover__title {
  overflow: hidden;
  color: #0f172a;
  font-size: 14px;
  font-weight: 700;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.check-template-popover__grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 8px;

  div {
    min-width: 0;
    padding: 8px;
    border-radius: 6px;
    background: #f8fafc;
  }

  span {
    display: block;
    color: #64748b;
    font-size: 11px;
  }

  strong {
    display: block;
    margin-top: 3px;
    color: #111827;
    font-size: 13px;
  }
}

.check-template-popover__tip {
  padding: 8px 10px;
  border-radius: 6px;
  background: #eff6ff;
  color: #1d4ed8;
  font-size: 12px;
  line-height: 1.5;
}
</style>
