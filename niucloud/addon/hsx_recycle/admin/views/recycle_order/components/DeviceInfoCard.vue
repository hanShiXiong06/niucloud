<template>
  <!--
    DeviceInfoCard —— 统一设备信息展示卡片
    Props:
      device  : 设备数据对象
      mode    : 'full'（默认，完整卡片）| 'compact'（紧凑，适合 Dialog header 内嵌）
      showStatus : 是否显示状态标签（默认 true）
  -->
  <div :class="['device-info-card', `device-info-card--${mode}`]">

    <!-- ===== 顶部：型号 + IMEI + 状态 ===== -->
    <div class="dic-header">
      <div class="dic-header__icon"><el-icon><Cellphone /></el-icon></div>
      <div class="dic-header__main">
        <div class="dic-header__model">{{ device.model || '未知型号' }}</div>
        <div class="dic-header__meta">
          <span class="dic-meta-item" v-if="device.imei">
            <span class="dic-meta-label">IMEI</span>
            <span class="dic-meta-value dic-meta-value--mono">{{ device.imei }}</span>
          </span>
          <span class="dic-meta-sep" v-if="device.imei && sn">·</span>
          <span class="dic-meta-item" v-if="sn">
            <span class="dic-meta-label">SN</span>
            <span class="dic-meta-value dic-meta-value--mono">{{ sn }}</span>
          </span>
        </div>
      </div>
      <div class="dic-header__badge" v-if="showStatus && device.status_name">
        <el-tag :type="statusTagType" size="small" effect="light">
          {{ device.status_name }}
        </el-tag>
      </div>
    </div>



    <!-- ===== full 模式额外：创建时间 ===== -->
    <div class="dic-footer" v-if="mode === 'full' && device.create_at">
      <span class="dic-footer-item">
        <span class="dic-footer-label">录入时间</span>
        <span class="dic-footer-value">{{ formatDate(device.create_at) }}</span>
      </span>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Cellphone, Coin, Brush, Medal } from '@element-plus/icons-vue'

interface DeviceData {
  model?: string
  imei?: string
  status?: number | string
  status_name?: string
  capacity?: string
  color?: string
  system_version?: string
  warranty_info?: string
  create_at?: string | number
  info?: {
    sn?: string
    [key: string]: any
  }
  [key: string]: any
}

const props = defineProps({
  device: {
    type: Object as () => DeviceData,
    default: () => ({})
  },
  mode: {
    type: String as () => 'full' | 'compact',
    default: 'full'
  },
  showStatus: {
    type: Boolean,
    default: true
  }
})

// 序列号：兼容 device.info.sn 或 device.sn
const sn = computed(() => props.device?.info?.sn || props.device?.sn || '')

// 状态标签颜色映射
const statusTagType = computed(() => {
  const status = parseInt(String(props.device?.status ?? ''))
  const map: Record<number, string> = {
    1: 'info',
    2: 'primary',
    3: 'success',
    4: 'warning',
    5: 'success',
    6: 'danger',
    7: 'info'
  }
  return map[status] || 'info'
})

// 规格列表 —— 优先用后端自描述的 check_summary(field_name + 已解析 label),前端零硬编码;
// 无 check_summary(如列表行场景)时回退旧字段,保证不破。
const SPEC_ICONS: Record<string, any> = { capacity: Coin, color: Brush, system_version: Cellphone, warranty_info: Medal }
const specs = computed(() => {
  const summary = (props.device as any)?.check_summary
  if (Array.isArray(summary) && summary.length) {
    return summary.map((s: any) => ({
      key: String(s.field_key ?? ''),
      icon: SPEC_ICONS[s.field_key] || Cellphone,
      label: String(s.field_name ?? ''),
      value: `${s.label ?? ''}${s.unit ?? ''}`.trim()
    }))
  }
  return [
    { key: 'capacity',       icon: Coin,      label: '内存',   value: props.device?.capacity },
    { key: 'color',          icon: Brush,     label: '颜色',   value: props.device?.color },
    { key: 'system_version', icon: Cellphone, label: '系统版本', value: props.device?.system_version },
    { key: 'warranty_info',  icon: Medal,     label: '保修信息', value: props.device?.warranty_info }
  ]
})

const formatDate = (dateStr: string | number) => {
  if (!dateStr) return '—'
  let date: Date
  if (typeof dateStr === 'number') {
    date = new Date(dateStr > 9999999999 ? dateStr : dateStr * 1000)
  } else {
    date = new Date(dateStr)
  }
  if (isNaN(date.getTime())) return '—'
  return date.toLocaleString('zh-CN', {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit'
  })
}

const moneyText = (value: any) => {
    const amount = typeof value === 'number' ? value : parseFloat(value || '0')
    if (!Number.isFinite(amount)) return '0'
    return Number.isInteger(amount) ? String(amount) : amount.toFixed(2)
}
</script>

<style lang="scss" scoped>
/* ============================
   DeviceInfoCard 基础变量
   ============================ */
$border-radius: 10px;
$spec-bg: #f8fafc;
$spec-border: #e2e8f0;

/* ============================
   full 模式 - 完整卡片
   ============================ */
.device-info-card--full {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: $border-radius;

  .dic-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 16px 12px;
    background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
    border-bottom: 1px solid #bae6fd;
  }

  .dic-footer {
    padding: 6px 16px;
    background: #f9fafb;
    border-top: 1px solid #f1f5f9;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
  }
}

/* ============================
   compact 模式 - 无边框，内嵌在 header 等深色背景中
   ============================ */
.device-info-card--compact {
  .dic-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 0;
  }

  
}

/* ============================
   Header 区域（共用）
   ============================ */
.dic-header__icon {
  font-size: 24px;
  flex-shrink: 0;
  line-height: 1;
  margin-top: 2px;
  color: var(--el-color-primary);
}

.dic-header__main {
  flex: 1;
  min-width: 0;
}

.dic-header__model {
  font-size: 15px;
  font-weight: 700;
  color: #1e293b;
  line-height: 1.3;
  word-break: break-all;

  .device-info-card--compact & {
    color: #fff;
    font-size: 16px;
  }
}

.dic-header__meta {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 4px;
  margin-top: 4px;
}

.dic-meta-item {
  display: inline-flex;
  align-items: center;
  gap: 3px;
}

.dic-meta-label {
  font-size: 11px;
  color: #64748b;
  background: #e2e8f0;
  padding: 0 4px;
  border-radius: 3px;
  line-height: 1.6;

  .device-info-card--compact & {
    color: rgba(255,255,255,0.6);
    background: rgba(255,255,255,0.15);
  }
}

.dic-meta-value {
  font-size: 14px;
  color: #0069ff;

  &--mono { font-family: 'SF Mono', 'Fira Code', monospace; letter-spacing: 0.02em; }

  .device-info-card--compact & { color: rgba(255,255,255,0.9); }
}

.dic-meta-sep {
  color: #cbd5e1;
  font-size: 12px;
}

.dic-header__badge {
  flex-shrink: 0;
}



.dic-spec-item {
  display: flex;
  align-items: flex-start;
  gap: 6px;
  padding: 10px 12px;
  border-right: 1px solid $spec-border;

  &:last-child { border-right: none; }

  // 底部分割线（移动端2列时需要）
  border-bottom: 1px solid transparent;
}

.dic-spec-icon {
  font-size: 14px;
  flex-shrink: 0;
  margin-top: 1px;
}

.dic-spec-body {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.dic-spec-label {
  font-size: 10px;
  color: #94a3b8;
  line-height: 1.3;
  white-space: nowrap;
}

.dic-spec-value {
  font-size: 12px;
  font-weight: 600;
  color: #1e293b;
  margin-top: 2px;
  word-break: break-all;
  line-height: 1.4;

  &--empty {
    color: #cbd5e1;
    font-weight: 400;
  }
}

/* ============================
   Footer 区域
   ============================ */
.dic-footer-item {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.dic-footer-label {
  font-size: 11px;
  color: #9ca3af;
}

.dic-footer-value {
  font-size: 11px;
  color: #6b7280;
}


</style>
