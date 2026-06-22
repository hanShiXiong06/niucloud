<template>
  <!--
    质检结果面板(可复用):
      - 顶部级别计数:异常 / 一般 / 正常
      - 优先突出"需关注"(异常+一般)项
      - 全部质检项默认折叠,客户只需看异常
    级别由后端按"选项文本"实时取自字典(severity 字段), 本组件只渲染、不计算。
    Props:
      severitySummary  { abnormal, general, normal }  后端 severity_summary
      abnormalItems    any[]  后端 abnormal_items(已异常在前)
      items            any[]  全部 result_items(折叠区)
      text             string 兜底文本(无结构化项时显示)
  -->
  <div class="qc-panel">
    <!-- 突出项:由质检模板"设备摘要"勾选的关键字段驱动(最多5个),值已解析、异常标色 -->
    <div v-if="summaryFields && summaryFields.length" class="qc-summary">
      <div
        v-for="(s, i) in summaryFields"
        :key="'s' + i"
        class="qc-summary-chip"
        :class="`is-${s.severity || 'normal'}`"
      >
        <span class="qc-summary-chip__name">{{ s.field_name }}</span>
        <span class="qc-summary-chip__val">{{ s.label }}</span>
      </div>
    </div>

    <div class="qc-counts">
      <span class="qc-chip is-abnormal" :class="{ 'is-zero': !counts.abnormal }">异常 {{ counts.abnormal }}</span>
      <span class="qc-chip is-general" :class="{ 'is-zero': !counts.general }">一般 {{ counts.general }}</span>
      <span class="qc-chip is-normal">正常 {{ counts.normal }}</span>
    </div>

    <div v-if="flagged.length" class="qc-flagged">
      <button type="button" class="qc-flagged__toggle" @click="openFlagged = !openFlagged">
        <span class="qc-flagged__title">需关注 {{ flagged.length }} 项</span>
        <span class="qc-toggle__arrow" :class="{ 'is-open': openFlagged }">▾</span>
      </button>
      <div v-show="openFlagged" class="qc-flagged__body">
        <div v-for="(it, i) in flagged" :key="'f' + i" class="qc-item" :class="`is-${it.severity || 'abnormal'}`">
          <span class="qc-item__name">{{ it.field_name || '检测项' }}</span>
          <span class="qc-item__val">{{ itemVal(it) }}</span>
        </div>
      </div>
    </div>
    <div v-else class="qc-allgood">✓ 无异常项，设备状态良好</div>

    <div v-if="allItems.length || text" class="qc-all">
      <button type="button" class="qc-toggle" @click="open = !open">
        <span>{{ open ? '收起质检摘要' : '展开全部质检项' }}</span>
        <span v-if="allItems.length" class="qc-toggle__count">{{ allItems.length }}</span>
        <span class="qc-toggle__arrow" :class="{ 'is-open': open }">▾</span>
      </button>
      <div v-show="open" class="qc-all__body">
        <template v-if="allItems.length">
          <div v-for="(it, i) in allItems" :key="'a' + i" class="qc-item" :class="`is-${it.severity || 'normal'}`">
            <span class="qc-item__name">{{ it.field_name || '检测项' }}</span>
            <span class="qc-item__val">{{ itemVal(it) }}</span>
          </div>
        </template>
        <div v-else class="qc-text">{{ text }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const props = defineProps<{
  severitySummary?: Record<string, number> | null
  abnormalItems?: any[] | null
  items?: any[] | null
  text?: string
  // 突出项:质检模板"设备摘要"勾选的关键字段(≤5),每项 { field_key, field_name, label, severity }
  summaryFields?: any[] | null
}>()

const open = ref(false)
const openFlagged = ref(true) // 需关注/异常 区默认展开,可手动折叠

const allItems = computed<any[]>(() => Array.isArray(props.items) ? props.items : [])

const flagged = computed<any[]>(() => {
  if (Array.isArray(props.abnormalItems) && props.abnormalItems.length) return props.abnormalItems
  return allItems.value.filter((i: any) => i.severity && i.severity !== 'normal')
})

const counts = computed(() => {
  if (props.severitySummary) {
    return {
      abnormal: Number(props.severitySummary.abnormal || 0),
      general: Number(props.severitySummary.general || 0),
      normal: Number(props.severitySummary.normal || 0)
    }
  }
  const c: Record<string, number> = { abnormal: 0, general: 0, normal: 0 }
  allItems.value.forEach((i: any) => {
    const s = i.severity || 'normal'
    c[s] = (c[s] || 0) + 1
  })
  return c as { abnormal: number; general: number; normal: number }
})

const itemVal = (it: any) =>
  (it.labels && it.labels.length ? it.labels.join('、') : (it.text || ''))
</script>

<style lang="scss" scoped>
.qc-panel { display: flex; flex-direction: column; gap: 10px; padding: 10px; }

.qc-summary { display: flex; flex-wrap: wrap; gap: 8px; }
.qc-summary-chip {
  display: inline-flex;
  align-items: baseline;
  gap: 4px;
  font-size: 12px;
  padding: 5px 11px;
  border-radius: 8px;
  background: var(--el-fill-color-light);
  border: 1px solid var(--el-border-color-lighter);

  &__name { color: var(--el-text-color-secondary); }
  &__val { color: var(--el-text-color-primary); font-weight: 600; }

  &.is-abnormal {
    background: var(--el-color-danger-light-9);
    border-color: var(--el-color-danger-light-7);
    .qc-summary-chip__val { color: var(--el-color-danger); }
  }
  &.is-general {
    background: var(--el-color-warning-light-9);
    border-color: var(--el-color-warning-light-7);
    .qc-summary-chip__val { color: var(--el-color-warning); }
  }
}

.qc-counts { display: flex; gap: 8px; }
.qc-chip {
  font-size: 12px;
  font-weight: 600;
  padding: 3px 10px;
  border-radius: 999px;
  line-height: 1.6;
  &.is-abnormal { color: var(--el-color-danger); background: var(--el-color-danger-light-9); }
  &.is-general { color: var(--el-color-warning); background: var(--el-color-warning-light-9); }
  &.is-normal { color: var(--el-color-success); background: var(--el-color-success-light-9); }
  &.is-zero { opacity: .45; }
}

.qc-flagged__toggle {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  padding: 2px 0;
  margin-bottom: 6px;
  background: transparent;
  border: none;
  cursor: pointer;
}
.qc-flagged__title {
  font-size: 12px;
  font-weight: 600;
  color: var(--el-color-danger);
}
.qc-flagged__toggle .qc-toggle__arrow {
  font-size: 12px;
  color: var(--el-color-danger);
  transition: transform .2s;
  &.is-open { transform: rotate(180deg); }
}
.qc-allgood {
  font-size: 12px;
  color: var(--el-color-success);
  background: var(--el-color-success-light-9);
  padding: 8px 10px;
  border-radius: 6px;
}

.qc-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  font-size: 12px;
  padding: 6px 9px;
  border-radius: 6px;
  border-left: 3px solid transparent;
  background: var(--el-fill-color-lighter);
  margin-bottom: 4px;

  &__name { color: var(--el-text-color-secondary); flex-shrink: 0; }
  &__val { color: var(--el-text-color-primary); text-align: right; word-break: break-all; }

  &.is-abnormal {
    background: var(--el-color-danger-light-9);
    border-left-color: var(--el-color-danger);
    .qc-item__val { color: var(--el-color-danger); font-weight: 600; }
  }
  &.is-general {
    background: var(--el-color-warning-light-9);
    border-left-color: var(--el-color-warning);
    .qc-item__val { color: var(--el-color-warning); }
  }
  &.is-normal { border-left-color: var(--el-border-color-lighter); }
}

.qc-all { margin-top: 2px; }
.qc-toggle {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--el-color-primary);
  background: transparent;
  border: none;
  cursor: pointer;
  padding: 4px 0;

  &__count {
    font-size: 11px;
    color: var(--el-text-color-secondary);
    background: var(--el-fill-color);
    border-radius: 999px;
    padding: 0 7px;
    line-height: 1.6;
  }
  &__arrow { transition: transform .2s; &.is-open { transform: rotate(180deg); } }
}
.qc-all__body { margin-top: 6px; }
.qc-text { font-size: 12px; line-height: 1.7; color: var(--el-text-color-regular); white-space: pre-wrap; }
</style>
