<template>
  <div class="ddp">
    <div class="ddp-header">
      <span class="ddp-title">流转进度</span>
      <span class="ddp-sub">回收完成后，设备在 ERP / 数据中台的去向</span>
    </div>

    <div class="ddp-track">
      <div
        v-for="(n, i) in nodes"
        :key="n.key"
        class="ddp-node"
        :class="{ 'is-done': i <= activeIndex, 'is-current': i === activeIndex }"
      >
        <div class="ddp-dot">
          <el-icon v-if="i < activeIndex" :size="13"><Check /></el-icon>
          <span v-else class="ddp-dot-idx">{{ i + 1 }}</span>
        </div>
        <div class="ddp-label">{{ n.label }}</div>
        <div v-if="n.hint" class="ddp-hint">{{ n.hint }}</div>
        <div v-if="i < nodes.length - 1" class="ddp-line" :class="{ 'is-done': i < activeIndex }"></div>
      </div>
    </div>

    <div v-if="stage === 0" class="ddp-foot">已回收，等待 ERP 入库后开始流转。</div>
    <div v-else-if="stagedAt" class="ddp-foot">最近更新：{{ formatTime(stagedAt) }}</div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Check } from '@element-plus/icons-vue'

const props = defineProps<{
  stage?: number
  salePrice?: number | string
  stagedAt?: number
  erpAssetId?: number
}>()

const stage = computed(() => Number(props.stage || 0))
const stagedAt = computed(() => Number(props.stagedAt || 0))

const salePriceText = computed(() => {
  const v = Number(props.salePrice || 0)
  return v > 0 ? `定价 ¥${v}` : ''
})

const nodes = computed(() => [
  { key: 'recycle', label: '回收完成', hint: '' },
  { key: 'stocked', label: '已入库', hint: '' },
  { key: 'photo', label: '转中台', hint: '' },
  { key: 'priced', label: '已定价', hint: salePriceText.value },
  { key: 'sold', label: '已售/下架', hint: '待商城接入' }
])

// stage: 0/10/20/30/40 → 已达节点下标
const activeIndex = computed(() => {
  const s = stage.value
  if (s >= 40) return 4
  if (s >= 30) return 3
  if (s >= 20) return 2
  if (s >= 10) return 1
  return 0
})

function formatTime(ts: number) {
  if (!ts) return ''
  const d = new Date(ts * 1000)
  const p = (n: number) => (n < 10 ? '0' + n : '' + n)
  return `${d.getFullYear()}-${p(d.getMonth() + 1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}`
}
</script>

<style scoped>
.ddp {
  background: #fff;
  border: 1px solid #eef0f4;
  border-radius: 12px;
  padding: 16px 18px;
}
.ddp-header {
  display: flex;
  align-items: baseline;
  gap: 10px;
  margin-bottom: 16px;
}
.ddp-title {
  font-size: 14px;
  font-weight: 600;
  color: #1f2937;
}
.ddp-sub {
  font-size: 12px;
  color: #9ca3af;
}
.ddp-track {
  display: flex;
  align-items: flex-start;
}
.ddp-node {
  position: relative;
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}
.ddp-dot {
  width: 26px;
  height: 26px;
  border-radius: 50%;
  background: #f1f3f7;
  color: #9ca3af;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 12px;
  font-weight: 600;
  z-index: 1;
}
.ddp-node.is-done .ddp-dot {
  background: #4f46e5;
  color: #fff;
}
.ddp-node.is-current .ddp-dot {
  box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.14);
}
.ddp-dot-idx {
  line-height: 1;
}
.ddp-label {
  margin-top: 7px;
  font-size: 12px;
  color: #6b7280;
}
.ddp-node.is-done .ddp-label {
  color: #374151;
  font-weight: 500;
}
.ddp-hint {
  margin-top: 2px;
  font-size: 11px;
  color: #9ca3af;
}
.ddp-line {
  position: absolute;
  top: 13px;
  left: 50%;
  width: 100%;
  height: 2px;
  background: #e8eaf0;
  z-index: 0;
}
.ddp-line.is-done {
  background: #4f46e5;
}
.ddp-foot {
  margin-top: 14px;
  font-size: 12px;
  color: #9ca3af;
}
</style>
