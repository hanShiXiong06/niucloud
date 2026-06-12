<template>
  <PremiumTheme class="staff-kpi min-h-screen bg-gray-50">
    <!-- 头部：标题 + 时间/角色筛选 -->
    <div class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-10">
      <div class="mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
          <div class="flex items-center space-x-3">
            <div class="w-9 h-9 bg-indigo-600 rounded-lg flex items-center justify-center">
              <el-icon :size="18" color="white"><Medal /></el-icon>
            </div>
            <div>
              <h1 class="text-xl font-semibold text-gray-900">员工考核</h1>
              <p class="text-sm text-gray-500">按关键动作衡量每个人的产出、效率与价值贡献</p>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-3">
            <el-radio-group v-model="quickRange" size="default" @change="onQuickRange">
              <el-radio-button label="today">今日</el-radio-button>
              <el-radio-button label="7d">近7天</el-radio-button>
              <el-radio-button label="30d">近30天</el-radio-button>
            </el-radio-group>
            <el-date-picker
              v-model="dateRange"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              value-format="YYYY-MM-DD"
              :clearable="false"
              style="width: 240px"
              @change="onDateRange"
            />
            <el-select v-model="roleFilter" placeholder="全部角色" clearable style="width: 130px">
              <el-option v-for="r in roleOptions" :key="r" :label="r" :value="r" />
            </el-select>
            <el-button :icon="Refresh" circle @click="loadData" />
          </div>
        </div>
      </div>
    </div>

    <div class="mx-auto px-4 sm:px-6 lg:px-8 py-5 space-y-5">
      <!-- 团队汇总 -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <StatCard title="签收（台）" :main-value="team.signed" :sub-value="`${rows.length} 名员工`" color="blue" :icon="Box" />
        <StatCard title="质检（台）" :main-value="team.check" :sub-value="`平均时效 ${fmtDur(team.avgCheck)}`" color="indigo" :icon="Search" />
        <StatCard title="定价（台）" :main-value="team.price" :sub-value="`平均时效 ${fmtDur(team.avgPrice)}`" color="purple" :icon="PriceTag" />
        <StatCard title="打款（台）" :main-value="team.pay" :sub-value="`回收成本 ¥${fmtMoney(team.payAmount)}`" color="green" :icon="Wallet" />
      </div>

      <!-- 综合得分前三 -->
      <div v-if="topThree.length" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div
          v-for="(p, i) in topThree"
          :key="p.user_id"
          class="relative rounded-xl border p-4 flex items-center gap-4 bg-white"
          :class="podiumClass(i)"
        >
          <div class="w-12 h-12 rounded-full flex items-center justify-center text-white text-lg font-bold" :class="medalClass(i)">
            {{ i + 1 }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-semibold text-gray-900 truncate">{{ p.user_name }}</div>
            <div class="text-xs text-gray-500">{{ p.user_type_name || '—' }}</div>
          </div>
          <div class="text-right">
            <div class="text-2xl font-bold" :class="medalTextClass(i)">{{ p.score }}</div>
            <div class="text-xs text-gray-400">综合得分</div>
          </div>
        </div>
      </div>

      <!-- 权重配置 -->
      <div class="bg-white rounded-xl border border-gray-100 p-4">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
          <div class="flex items-center gap-2 text-sm text-gray-700 font-medium">
            <el-icon class="text-indigo-500"><SetUp /></el-icon> 考核权重
            <el-tooltip placement="top">
              <template #content>
                <div class="text-xs leading-5">
                  综合得分 = 产出分×产出权重 + 效率分×效率权重 + 价值分×价值权重<br />
                  · 产出：签收/质检/定价/打款台数<br />
                  · 效率：各环节平均处理时长（越短越高）<br />
                  · 价值：打款金额与定价毛利贡献<br />
                  三项各自在团队内归一为 0–100 分
                </div>
              </template>
              <el-icon class="text-gray-400 cursor-help"><QuestionFilled /></el-icon>
            </el-tooltip>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 w-12">产出</span>
            <el-input-number v-model="weights.volume" :min="0" :max="100" :step="5" size="small" controls-position="right" style="width: 110px" />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 w-12">效率</span>
            <el-input-number v-model="weights.efficiency" :min="0" :max="100" :step="5" size="small" controls-position="right" style="width: 110px" />
          </div>
          <div class="flex items-center gap-2">
            <span class="text-sm text-gray-500 w-12">价值</span>
            <el-input-number v-model="weights.quality" :min="0" :max="100" :step="5" size="small" controls-position="right" style="width: 110px" />
          </div>
          <el-button size="small" text type="primary" @click="resetWeights">恢复默认</el-button>
        </div>
      </div>

      <!-- 明细表 -->
      <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
        <el-table
          v-loading="loading"
          :data="rows"
          stripe
          :default-sort="{ prop: 'score', order: 'descending' }"
          row-key="user_id"
          style="width: 100%"
        >
          <el-table-column label="排名" width="70" align="center">
            <template #default="{ row }">
              <span class="font-semibold" :class="row._rank <= 3 ? 'text-indigo-600' : 'text-gray-400'">{{ row._rank }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="user_name" label="员工" min-width="120" fixed>
            <template #default="{ row }">
              <div class="font-medium text-gray-900">{{ row.user_name }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="user_type_name" label="角色" min-width="100">
            <template #default="{ row }">
              <el-tag size="small" effect="plain" type="info">{{ row.user_type_name || '—' }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="signed_device_count" label="签收" width="90" align="center" sortable />
          <el-table-column label="质检" min-width="140" align="center" sortable :sort-by="sortByCheck">
            <template #default="{ row }">
              <div class="font-semibold text-gray-800">{{ row.check_count }} <span class="text-xs font-normal text-gray-400">台</span></div>
              <div class="text-xs text-gray-400">均 {{ fmtDur(row.avg_check_duration) }}</div>
            </template>
          </el-table-column>
          <el-table-column label="定价" min-width="170" align="center" sortable :sort-by="sortByPrice">
            <template #default="{ row }">
              <div class="font-semibold text-gray-800">{{ row.price_count }} <span class="text-xs font-normal text-gray-400">台</span></div>
              <div class="text-xs text-gray-400">均 {{ fmtDur(row.avg_price_duration) }} · 毛利 {{ row.avg_margin > 0 ? '¥' + fmtMoney(row.avg_margin) : '—' }}</div>
            </template>
          </el-table-column>
          <el-table-column label="打款" min-width="160" align="center" sortable :sort-by="sortByPay">
            <template #default="{ row }">
              <div class="font-semibold text-gray-800">{{ row.payment_count }} <span class="text-xs font-normal text-gray-400">台</span></div>
              <div class="text-xs text-gray-400">均 {{ fmtDur(row.avg_pay_duration) }} · ¥{{ fmtMoney(row.total_pay_amount) }}</div>
            </template>
          </el-table-column>
          <el-table-column prop="score" label="综合得分" min-width="160" align="center" sortable>
            <template #default="{ row }">
              <div class="flex items-center gap-2">
                <el-progress :percentage="row.score" :stroke-width="8" :show-text="false" :color="scoreColor(row.score)" class="flex-1" />
                <span class="font-bold text-gray-800 w-9 text-right">{{ row.score }}</span>
              </div>
            </template>
          </el-table-column>
          <template #empty>
            <div class="py-12 text-gray-400">所选时间内暂无员工操作数据</div>
          </template>
        </el-table>
      </div>

      <p class="text-xs text-gray-400 px-1">
        说明：数据来自每台设备的关键动作时间线（签收→质检→定价→打款），按操作人归集。得分口径与权重可在上方调整，调整后即时重算。
      </p>
    </div>
  </PremiumTheme>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import {
  Medal, Refresh, Box, Search, PriceTag, Wallet, SetUp, QuestionFilled
} from '@element-plus/icons-vue'
import PremiumTheme from '../../components/PremiumTheme.vue'
import StatCard from './components/StatCard.vue'
import { getStaffKpiBoard } from '../../api/stats'

interface KpiRow {
  user_id: number
  user_name: string
  user_type_name: string
  signed_device_count: number
  check_count: number
  price_count: number
  payment_count: number
  avg_check_duration: number
  avg_price_duration: number
  avg_pay_duration: number
  total_pay_amount: number
  avg_margin: number
  score: number
  _rank: number
}

const loading = ref(false)
const raw = ref<KpiRow[]>([])
const quickRange = ref<'today' | '7d' | '30d'>('30d')
const dateRange = ref<[string, string]>(rangeOf('30d'))
const roleFilter = ref('')

const DEFAULT_WEIGHTS = { volume: 40, efficiency: 30, quality: 30 }
const weights = reactive({ ...DEFAULT_WEIGHTS })
function resetWeights() { Object.assign(weights, DEFAULT_WEIGHTS) }

function pad(n: number) { return n < 10 ? '0' + n : '' + n }
function ymd(d: Date) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` }
function rangeOf(r: 'today' | '7d' | '30d'): [string, string] {
  const end = new Date()
  const start = new Date()
  if (r === '7d') start.setDate(start.getDate() - 6)
  else if (r === '30d') start.setDate(start.getDate() - 29)
  return [ymd(start), ymd(end)]
}
function onQuickRange() { dateRange.value = rangeOf(quickRange.value); loadData() }
function onDateRange() { loadData() }

function num(v: any): number { const n = Number(v); return isNaN(n) ? 0 : n }

const sortByCheck = (r: KpiRow) => r.check_count
const sortByPrice = (r: KpiRow) => r.price_count
const sortByPay = (r: KpiRow) => r.payment_count

async function loadData() {
  loading.value = true
  try {
    const res: any = await getStaffKpiBoard({
      start_time: dateRange.value?.[0] || '',
      end_time: dateRange.value?.[1] || ''
    })
    const list: any[] = (res?.data ?? res) || []
    raw.value = list.map((r) => ({
      user_id: num(r.user_id),
      user_name: r.user_name || '未命名',
      user_type_name: r.user_type_name || '',
      signed_device_count: num(r.signed_device_count),
      check_count: num(r.check_count),
      price_count: num(r.price_count),
      payment_count: num(r.payment_count),
      avg_check_duration: num(r.avg_check_duration),
      avg_price_duration: num(r.avg_price_duration),
      avg_pay_duration: num(r.avg_pay_duration),
      total_pay_amount: num(r.total_pay_amount),
      avg_margin: num(r.avg_margin),
      score: 0,
      _rank: 0
    }))
  } catch (e) {
    raw.value = []
  } finally {
    loading.value = false
  }
}

const roleOptions = computed(() => {
  const s = new Set<string>()
  raw.value.forEach((r) => { if (r.user_type_name) s.add(r.user_type_name) })
  return Array.from(s)
})

// 归一化辅助：值越大分越高
function maxOf(arr: number[]) { return arr.reduce((m, v) => (v > m ? v : m), 0) }
// 时效分：在所有正值里，越短分越高
function effScore(row: KpiRow, mins: { c: number; p: number; y: number }) {
  const parts: number[] = []
  if (row.avg_check_duration > 0 && mins.c > 0) parts.push(Math.min(100, (mins.c / row.avg_check_duration) * 100))
  if (row.avg_price_duration > 0 && mins.p > 0) parts.push(Math.min(100, (mins.p / row.avg_price_duration) * 100))
  if (row.avg_pay_duration > 0 && mins.y > 0) parts.push(Math.min(100, (mins.y / row.avg_pay_duration) * 100))
  if (!parts.length) return 0
  return parts.reduce((a, b) => a + b, 0) / parts.length
}

const rows = computed<KpiRow[]>(() => {
  let list = raw.value.slice()
  if (roleFilter.value) list = list.filter((r) => r.user_type_name === roleFilter.value)
  if (!list.length) return []

  const volMax = maxOf(list.map((r) => r.signed_device_count + r.check_count + r.price_count + r.payment_count))
  const amtMax = maxOf(list.map((r) => r.total_pay_amount))
  const marginMax = maxOf(list.map((r) => r.avg_margin))
  const posMin = (arr: number[]) => { const p = arr.filter((v) => v > 0); return p.length ? Math.min(...p) : 0 }
  const mins = {
    c: posMin(list.map((r) => r.avg_check_duration)),
    p: posMin(list.map((r) => r.avg_price_duration)),
    y: posMin(list.map((r) => r.avg_pay_duration))
  }
  const wSum = (weights.volume + weights.efficiency + weights.quality) || 1

  const scored = list.map((r) => {
    const vol = r.signed_device_count + r.check_count + r.price_count + r.payment_count
    const volumeScore = volMax > 0 ? (vol / volMax) * 100 : 0
    const efficiencyScore = effScore(r, mins)
    const amtScore = amtMax > 0 ? (r.total_pay_amount / amtMax) * 100 : 0
    const marginScore = marginMax > 0 ? (r.avg_margin / marginMax) * 100 : 0
    const qualityScore = marginMax > 0 ? amtScore * 0.5 + marginScore * 0.5 : amtScore
    const composite = (weights.volume * volumeScore + weights.efficiency * efficiencyScore + weights.quality * qualityScore) / wSum
    return { ...r, score: Math.round(composite) }
  })
  scored.sort((a, b) => b.score - a.score)
  scored.forEach((r, i) => (r._rank = i + 1))
  return scored
})

const topThree = computed(() => rows.value.slice(0, 3))

const team = computed(() => {
  const list = rows.value
  const sum = (f: (r: KpiRow) => number) => list.reduce((a, r) => a + f(r), 0)
  const avg = (f: (r: KpiRow) => number) => {
    const p = list.map(f).filter((v) => v > 0)
    return p.length ? p.reduce((a, b) => a + b, 0) / p.length : 0
  }
  return {
    signed: sum((r) => r.signed_device_count),
    check: sum((r) => r.check_count),
    price: sum((r) => r.price_count),
    pay: sum((r) => r.payment_count),
    payAmount: sum((r) => r.total_pay_amount),
    avgCheck: avg((r) => r.avg_check_duration),
    avgPrice: avg((r) => r.avg_price_duration)
  }
})

function fmtDur(sec: number) {
  const s = num(sec)
  if (s <= 0) return '—'
  if (s < 60) return `${Math.round(s)}秒`
  if (s < 3600) return `${Math.round(s / 60)}分`
  if (s < 86400) return `${(s / 3600).toFixed(1)}小时`
  return `${(s / 86400).toFixed(1)}天`
}
function fmtMoney(v: number) {
  return num(v).toLocaleString('zh-CN', { maximumFractionDigits: 0 })
}
function scoreColor(s: number) {
  if (s >= 80) return '#16a34a'
  if (s >= 60) return '#4f46e5'
  if (s >= 40) return '#f59e0b'
  return '#9ca3af'
}
function podiumClass(i: number) {
  return ['border-yellow-200 bg-gradient-to-br from-yellow-50 to-white',
    'border-gray-200 bg-gradient-to-br from-gray-50 to-white',
    'border-orange-200 bg-gradient-to-br from-orange-50 to-white'][i] || 'border-gray-100'
}
function medalClass(i: number) {
  return ['bg-yellow-500', 'bg-gray-400', 'bg-orange-400'][i] || 'bg-gray-300'
}
function medalTextClass(i: number) {
  return ['text-yellow-600', 'text-gray-500', 'text-orange-500'][i] || 'text-gray-500'
}

onMounted(loadData)
</script>

<style scoped>
.staff-kpi :deep(.el-progress-bar__outer) {
  background-color: #eef2ff;
}
</style>
