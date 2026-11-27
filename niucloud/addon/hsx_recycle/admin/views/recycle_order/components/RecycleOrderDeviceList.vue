<template>
  <section class="order-devices" :class="{ 'order-devices--wide': !props.compact }" aria-label="订单设备清单">
    <div class="order-devices__toolbar">
      <HsxTitle title="设备清单" size="subsection" :level="3" />
      <span class="order-devices__count">共 {{ devices.length }} 台</span>
      <div v-if="canBatch && selectedIds.size" class="order-devices__selection">
        <el-button type="primary" size="small" :icon="Check" @click="emit('batch-confirm')">批量确认（{{ selectedIds.size }}）</el-button>
      </div>
    </div>

    <div v-if="!devices.length" class="order-devices__empty">暂无设备，请先完成签收录入</div>
    <div v-else class="order-devices__scroll" tabindex="0" aria-label="设备表格，窄屏可左右滑动">
      <table class="order-devices__table" aria-label="设备清单">
        <colgroup>
          <col v-if="canBatch" class="order-devices__check-col" />
          <col class="order-devices__model-col" />
          <col v-if="!props.compact" class="order-devices__serial-col" />
          <col class="order-devices__price-col" />
          <col class="order-devices__progress-col" />
          <col class="order-devices__actions-col" />
        </colgroup>
        <thead>
          <tr>
            <th v-if="canBatch" scope="col" class="order-devices__check">
              <el-checkbox :model-value="allSelected" :indeterminate="partlySelected" aria-label="全选设备" @change="selectAll" />
            </th>
            <th scope="col">{{ props.compact ? '设备 / 串号' : '设备型号' }}</th>
            <th v-if="!props.compact" scope="col">IMEI / SN</th>
            <th scope="col" class="order-devices__price">最终价格</th>
            <th scope="col">当前进度</th>
            <th scope="col" class="order-devices__actions-heading">操作</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="{ device, progress, primaryAction, moreActions } in deviceRows" :key="device.id" class="order-device" :class="{ 'order-device--selected': selectedIds.has(String(device.id)) }">
            <td v-if="canBatch" class="order-devices__check">
              <el-checkbox
                :model-value="selectedIds.has(String(device.id))"
                :aria-label="`选择设备 ${device.imei || device.user_sn || device.model || ''}`"
                @change="checked => selectDevice(device, checked)"
              />
            </td>
            <td class="order-device__identity">
              <div class="order-device__model">{{ device.model || '型号待补充' }}</div>
              <template v-if="props.compact">
                <div class="order-device__serial"><span>IMEI / SN</span><code>{{ device.imei || '未录入' }}</code></div>
                <div v-if="device.user_sn && device.user_sn !== device.imei" class="order-device__serial"><span>客户串号</span><code>{{ device.user_sn }}</code></div>
              </template>
            </td>
            <td v-if="!props.compact">
              <div class="order-device__serial"><code>{{ device.imei || '未录入' }}</code></div>
              <div v-if="device.user_sn && device.user_sn !== device.imei" class="order-device__serial"><span>客户串号</span><code>{{ device.user_sn }}</code></div>
            </td>
            <td class="order-devices__price"><strong>{{ props.formatPrice(device.final_price) }}</strong></td>
            <td>
              <el-popover placement="top" trigger="click" :width="264" title="进度说明">
                <dl class="order-device-progress-detail">
                  <template v-for="item in progress.details" :key="item.label">
                    <dt>{{ item.label }}</dt><dd>{{ item.value }}</dd>
                  </template>
                </dl>
                <p v-if="progress.hint" class="order-device-progress-hint">{{ progress.hint }}</p>
                <template #reference>
                  <button type="button" class="order-device__progress-button" :aria-label="`${progress.label}，点击查看进度说明`" title="点击查看设备、确认和打款明细">
                    <HsxTag :text="progress.label" :tone="progress.tone" size="small" dot />
                    <el-icon class="order-device__progress-info"><InfoFilled /></el-icon>
                  </button>
                </template>
              </el-popover>
              <el-button v-if="device.consignment_order_id || device.consignmentOrder" class="order-device__consignment" link type="primary" @click="props.viewConsignment(device)">{{ device.consignmentOrder?.consignment_no || '查看代卖单' }}</el-button>
            </td>
            <td>
              <div class="order-device__actions">
                <el-button v-if="primaryAction" type="primary" link size="small" @click="primaryAction.handler()">{{ primaryAction.label }}</el-button>
                <el-button type="primary" link size="small" @click="props.viewDetail(device)">详情</el-button>
                <el-dropdown v-if="moreActions.length" trigger="click">
                  <el-button type="primary" link size="small" :icon="MoreFilled">更多</el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item v-for="action in moreActions" :key="action.key" :divided="action.danger" @click="action.handler()">
                        <span class="order-device__menu-item" :class="{ 'order-device__menu-item--danger': action.danger }"><el-icon><component :is="action.icon" /></el-icon>{{ action.label }}</span>
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <p v-if="devices.length" class="order-devices__scroll-hint">左右滑动可查看完整设备表</p>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { Check, InfoFilled, MoreFilled } from '@element-plus/icons-vue'
import { HsxTag, HsxTitle } from '@/addon/hsx_components/core'
import type { HsxTagTone } from '@/addon/hsx_components/types'
import type { DeviceRowAction } from '@/addon/hsx_recycle/hooks/useDeviceRowActions'
import { useRecycleOrderUi } from '@/addon/hsx_recycle/hooks/useRecycleOrderUi'

const props = withDefaults(defineProps<{
  compact?: boolean
  order: any
  selectedDevices: any[]
  formatPrice: (value: any) => string
  getDevicePrimaryAction: (device: any) => DeviceRowAction | null
  getDeviceMoreActions: (device: any) => DeviceRowAction[]
  viewConsignment: (device: any) => void
  viewDetail: (device: any) => void
}>(), { compact: true })
const emit = defineEmits<{
  (event: 'selection-change', devices: any[]): void
  (event: 'batch-confirm'): void
}>()

const { getDeviceStatusText, getDeviceStatusType, getDeviceNextStep } = useRecycleOrderUi()

// 仅归纳展示，不改变业务状态、付款入口和权限。已回收不等于已打款。
const getDeviceProgress = (device: any) => {
  const status = Number(device.status)
  const confirm = device.confirm_status == null || device.confirm_status === '' ? null : Number(device.confirm_status)
  const pay = device.pay_status == null || device.pay_status === '' ? null : Number(device.pay_status)
  const deviceText = device.status_name || getDeviceStatusText(status) || '状态待核对'
  const confirmText = ({ 0: '待客户确认', 1: '已确认', 2: '已拒绝' } as Record<number, string>)[confirm as number]
    || device.confirm_status_name || '状态未返回'
  const payText = ({ 0: '未打款', 1: '已打款', 2: '部分打款' } as Record<number, string>)[pay as number]
    || device.pay_status_name || '状态未返回'
  const details = [{ label: '设备状态', value: deviceText }]
  if ([4, 5, 7, 8, 9].includes(status) || confirm === 1 || confirm === 2) details.push({ label: '报价确认', value: confirmText })
  if (status === 5 || pay === 1 || pay === 2 || (status === 4 && confirm === 1)) details.push({ label: '回收打款', value: payText })

  const type = getDeviceStatusType(status)
  let label = deviceText
  let tone: HsxTagTone = type === 'info' ? 'neutral' : type as HsxTagTone
  let hint = getDeviceNextStep(status)

  // 退回/代卖不归入普通回收待付款，相关已付事实仍在明细中保留。
  if (status === 6) return { label, tone, hint, details }
  if (status === 9 || device.dispose_type === 'consign') {
    return { label: '已转代卖', tone: 'neutral' as HsxTagTone, hint: '请在代卖订单中跟进销售与结算；这里的回收打款状态不代表代卖款已结清。', details }
  }
  if ([4, 7, 8].includes(status) && confirm === 2) {
    return { label: '客户已拒绝', tone: 'danger' as HsxTagTone, hint: '请核对退回安排或重新沟通报价，不要直接打款。', details }
  }
  // 与后端 resolveConfirmStatus 一致：status=5 已完成回收确认。
  if (status === 5 || (status === 4 && confirm === 1)) {
    tone = 'warning'
    if (pay === 0) {
      label = '待打款'
      hint = '回收已确认，尚未打款。请在订单打款入口核对应付、已付和剩余金额后处理。'
    } else if (pay === 2) {
      label = '待补款'
      hint = '已有部分打款记录，剩余款项尚未结清（可能包含补差）。请核对剩余金额，不要按原总额重复打款。'
    } else if (pay === 1) {
      label = '已打款'
      tone = 'success'
      hint = '系统记录回收款已结清；后续入库、整备进度请查看对应业务。'
    } else {
      label = '打款待核对'
      hint = '没有有效的打款状态，请先到详情核对记录，不要重复打款。'
    }
  } else if (status === 4) {
    label = confirm === 0 ? '待客户确认' : '确认待核对'
    tone = 'warning'
    hint = confirm === 0 ? '请先完成报价确认，再按流程办理打款。' : '未返回有效的确认状态，请到详情核对后继续。'
  } else if (status === 3) {
    label = '待定价'
  }
  return { label, tone, hint, details }
}

const devices = computed<any[]>(() => props.order.devices || [])
const deviceRows = computed(() => devices.value.map(device => ({
  device,
  progress: getDeviceProgress(device),
  primaryAction: props.getDevicePrimaryAction(device),
  moreActions: props.getDeviceMoreActions(device),
})))
const canBatch = computed(() => Number(props.order.status) === 4)
const selectedIds = computed(() => new Set(props.selectedDevices.map(device => String(device.id))))
const allSelected = computed(() => devices.value.length > 0 && devices.value.every(device => selectedIds.value.has(String(device.id))))
const partlySelected = computed(() => !allSelected.value && devices.value.some(device => selectedIds.value.has(String(device.id))))

// 选择状态由页面统一持有；切换横竖屏或收起设备，不清空已选设备。
const selectAll = (checked: string | number | boolean) => emit('selection-change', checked ? [...devices.value] : [])
const selectDevice = (device: any, checked: string | number | boolean) => {
  const selected = props.selectedDevices.filter(item => String(item.id) !== String(device.id))
  if (checked) selected.push(device)
  emit('selection-change', selected)
}
</script>

<style scoped>
.order-devices { min-width: 0; padding: 0; color: var(--hsx-text-regular); }
.order-devices__toolbar { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; min-height: 30px; margin-bottom: 6px; }
.order-devices__count { color: var(--hsx-text-secondary); font-size: 12px; }
.order-devices__selection { margin-left: auto; }
.order-devices__empty { padding: 16px 10px; text-align: center; font-size: 12px; color: var(--hsx-text-secondary); }
.order-devices__scroll { max-width: 100%; overflow-x: auto; border: 1px solid var(--hsx-border-color); border-radius: 4px; background: var(--hsx-bg-surface); -webkit-overflow-scrolling: touch; }
.order-devices__table { width: 100%; min-width: 620px; border-collapse: collapse; table-layout: fixed; font-size: 12px; line-height: 20px; }
.order-devices__table th, .order-devices__table td { box-sizing: border-box; padding: 7px 10px; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--hsx-border-color); }
.order-devices__table th { padding-top: 5px; padding-bottom: 5px; background: var(--hsx-bg-muted); color: var(--hsx-text-secondary); font-weight: 500; }
.order-devices__table tbody tr:last-child td { border-bottom: 0; }
.order-devices__table tbody tr:hover { background: var(--el-fill-color-light); }
.order-device--selected { background: var(--el-color-primary-light-9); }
.order-devices__check-col { width: 34px; }
.order-devices__price-col { width: 92px; }
.order-devices__progress-col { width: 138px; }
.order-devices__actions-col { width: 186px; }
.order-devices--wide .order-devices__model-col { width: 32%; }
.order-devices--wide .order-devices__serial-col { width: 24%; }
.order-devices--wide .order-devices__price-col { width: 10%; }
.order-devices--wide .order-devices__progress-col { width: 15%; }
.order-devices--wide .order-devices__actions-col { width: 19%; }
.order-devices--wide .order-device__serial { font-size: 12px; }
.order-devices__table .order-devices__check { padding: 0 6px; text-align: center; }
.order-devices__check :deep(.el-checkbox) { height: 32px; margin: 0; }
.order-devices__table .order-devices__price { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.order-devices__price strong { font-size: 13px; font-weight: 600; color: var(--el-color-danger); }
.order-device__model { color: var(--hsx-text-primary); font-size: 13px; font-weight: 500; overflow-wrap: anywhere; }
.order-device__serial { display: flex; flex-wrap: wrap; gap: 0 6px; font-size: 11px; line-height: 18px; }
.order-device__serial span { color: var(--hsx-text-secondary); }
.order-device__serial code { color: var(--hsx-text-regular); font-family: inherit; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; user-select: text; }
.order-device__progress-button { display: inline-flex; align-items: center; gap: 5px; min-height: 32px; max-width: 100%; padding: 0; border: 0; border-radius: 4px; background: transparent; cursor: pointer; }
.order-device__progress-button:focus-visible { outline: 2px solid var(--el-color-primary); outline-offset: 2px; }
.order-device__progress-info { color: var(--hsx-text-secondary); font-size: 12px; }
.order-device__progress-button:hover .order-device__progress-info { color: var(--el-color-primary); }
.order-device-progress-detail { display: grid; grid-template-columns: 64px minmax(0, 1fr); gap: 7px 12px; margin: 0; font-size: 12px; line-height: 20px; }
.order-device-progress-detail dt { color: var(--el-text-color-secondary); }
.order-device-progress-detail dd { margin: 0; color: var(--el-text-color-primary); overflow-wrap: anywhere; }
.order-device-progress-hint { margin: 10px 0 0; padding-top: 9px; border-top: 1px solid var(--el-border-color-lighter); font-size: 12px; line-height: 20px; color: var(--el-text-color-regular); }
.order-device__consignment { max-width: 100%; font-size: 11px; white-space: normal; overflow-wrap: anywhere; }
.order-devices__table .order-devices__actions-heading { text-align: right; }
.order-device__actions { display: flex; align-items: center; justify-content: flex-end; flex-wrap: wrap; gap: 0 10px; }
.order-device__actions :deep(.el-button) { min-height: 32px; margin-left: 0; padding-left: 0; padding-right: 0; font-size: 12px; }
.order-device__menu-item { display: inline-flex; align-items: center; gap: 6px; min-height: 32px; }
.order-device__menu-item--danger { color: var(--el-color-danger); }
.order-devices__scroll-hint { display: none; margin: 5px 0 0; color: var(--hsx-text-secondary); font-size: 11px; }
@media (pointer: coarse) {
  .order-device__actions :deep(.el-button), .order-devices__check :deep(.el-checkbox), .order-device__progress-button { min-height: 36px; }
}
@media (max-width: 640px) {
  .order-devices__scroll-hint { display: block; }
}
</style>
