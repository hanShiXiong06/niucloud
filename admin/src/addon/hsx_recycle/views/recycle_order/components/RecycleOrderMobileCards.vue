<template>
  <div v-loading="props.loading">
    <el-empty v-if="!props.list.length" description="暂无订单数据" />

    <div v-else class="grid gap-3">
      <el-card v-for="row in props.list" :key="row.id" shadow="hover" class="rounded-lg">
        <div class="mb-2 flex items-start justify-between gap-2">
          <div class="text-[13px] font-semibold text-gray-700 break-all">
            订单号：{{ row.order_no || row.id }}
          </div>
          <el-tag :type="props.getStatusType(row.status)" :effect="props.getStatusEffect(row.status)" size="small">
            {{ row.status_name }}
          </el-tag>
        </div>

        <div class="mb-2 flex items-center gap-2">
          <el-avatar :size="28" :src="row.member?.headimg ? props.img(row.member.headimg) : ''">
            <el-icon><User /></el-icon>
          </el-avatar>
          <div class="min-w-0">
            <div class="text-sm font-semibold text-gray-900">
              {{ row.recycleUserAddress?.name || row.member?.nickname || "未知用户" }}
            </div>
            <div class="text-xs text-gray-500">
              {{ row.member?.mobile || row.recycleUserAddress?.mobile || "暂无联系方式" }}
            </div>
          </div>
        </div>

        <div class="mb-2 grid grid-cols-2 gap-2">
          <div class="rounded-md border border-gray-200 bg-slate-50 p-2">
            <div class="mb-1 text-xs text-gray-500">配送方式</div>
            <el-tag size="small" :type="row.delivery_type === '1' ? 'warning' : 'success'">
              {{ row.delivery_type === "1" ? "📦 快递" : "🚗 自送" }}
            </el-tag>
          </div>

          <div class="rounded-md border border-gray-200 bg-slate-50 p-2">
            <div class="mb-1 text-xs text-gray-500">设备数量</div>
            <el-tag v-if="isDeviceCountMatched(row)" type="success" size="small">
              {{ getSubmittedDeviceCount(row) }}/{{ getSignedDeviceCount(row) }}台
            </el-tag>
            <el-tag v-else type="danger" size="small">
              {{ getSubmittedDeviceCount(row) }}/{{ getSignedDeviceCount(row) }}台
            </el-tag>
          </div>

          <div class="col-span-2 rounded-md border border-gray-200 bg-slate-50 p-2">
            <div class="mb-1 text-xs text-gray-500">创建时间</div>
            <div class="text-xs text-gray-800">{{ props.formatDateTime(row.create_at) }}</div>
          </div>

          <div v-if="row.delivery_type === '1'" class="col-span-2 rounded-md border border-gray-200 bg-slate-50 p-2">
            <div class="mb-1 text-xs text-gray-500">快递单号</div>
            <div
              class="cursor-pointer text-xs text-blue-600 break-all"
              @click="props.handleExpressHover(row)"
              @mouseleave="props.handleExpressLeave"
            >
              <span v-if="!props.expressLoading[row.id]">{{ row.express_no || "暂无" }}</span>
              <el-icon v-else class="animate-spin text-blue-500">
                <Loading />
              </el-icon>
            </div>
          </div>
        </div>

        <div v-if="props.orderStatusMap[row.status]?.action?.length" class="mb-2 flex flex-wrap gap-2">
          <el-button
            v-for="action in props.orderStatusMap[row.status].action"
            :key="action.key"
            size="small"
            :type="props.getActionButtonType(action.key)"
            :icon="props.getActionIcon(action.key)"
            @click="props.handleAction(row, action)"
          >
            {{ action.value }}
          </el-button>
          <el-button
            v-if="row.available_actions?.can_pay_devices && !props.orderStatusMap[row.status]?.action?.some((action: any) => action.key === 'order_payment')"
            type="primary"
            size="small"
            :icon="props.getActionIcon('order_payment')"
            @click="props.handleAction(row, { key: 'order_payment', value: '去打款' })"
          >
            去打款
          </el-button>
          <el-button
            v-if="row.available_actions?.can_push_confirm_notice && !props.orderStatusMap[row.status]?.action?.some((action: any) => action.key === 'order_push_notify')"
            type="warning"
            size="small"
            :icon="Bell"
            @click="props.handleAction(row, { key: 'order_push_notify', value: '推送通知' })"
          >
            推送通知
          </el-button>
          <el-button
            type="success"
            size="small"
            :icon="Share"
            @click="props.shareOrder(row)"
          >
            分享
          </el-button>
          <el-button
            type="info"
            size="small"
            :icon="Bell"
            @click="props.viewNoticeLogs(row)"
          >
            通知记录
          </el-button>
        </div>
        <div v-else class="mb-2 flex flex-wrap gap-2">
          <el-button
            v-if="row.available_actions?.can_pay_devices"
            type="primary"
            size="small"
            :icon="props.getActionIcon('order_payment')"
            @click="props.handleAction(row, { key: 'order_payment', value: '去打款' })"
          >
            去打款
          </el-button>
          <el-button
            v-if="row.available_actions?.can_push_confirm_notice"
            type="warning"
            size="small"
            :icon="Bell"
            @click="props.handleAction(row, { key: 'order_push_notify', value: '推送通知' })"
          >
            推送通知
          </el-button>
          <el-button
            type="success"
            size="small"
            :icon="Share"
            @click="props.shareOrder(row)"
          >
            分享
          </el-button>
          <el-button
            type="info"
            size="small"
            :icon="Bell"
            @click="props.viewNoticeLogs(row)"
          >
            通知记录
          </el-button>
        </div>

        <el-button
          size="small"
          text
          type="primary"
          @click="props.toggleMobileOrderExpand(row.id)"
          class="!pl-0"
        >
          {{ props.isMobileOrderExpanded(row.id) ? "收起设备列表" : "展开设备列表" }}
          ({{ row.devices?.length || 0 }})
        </el-button>

        <el-collapse-transition>
          <div v-show="props.isMobileOrderExpanded(row.id)" class="mt-2 grid gap-2 border-t border-dashed border-gray-300 pt-2">
            <div v-for="device in row.devices || []" :key="device.id" class="rounded-md border border-gray-200 bg-white p-2">
              <div class="text-xs font-semibold text-gray-800 break-all">
                {{ device.user_sn || device.imei || "无串号" }}
              </div>
              <div v-if="device.user_sn && device.imei" class="text-[11px] text-gray-400 break-all">
                管理录入：{{ device.imei }}
              </div>
              <div class="mt-1 text-xs text-gray-600">{{ device.model || "未知型号" }}</div>

              <div class="mt-2 flex items-center justify-between">
                <span class="text-sm font-semibold text-red-500">{{ props.formatPrice(device.final_price) }}</span>
                <DeviceStatusBadge :status="device.status" :status-name="device.status_name" />
              </div>
              <div v-if="device.status === 5" class="mt-2">
                <el-tag :type="Number(device.pay_status || 0) === 1 ? 'success' : 'warning'" size="small">
                  {{ device.pay_status_name || (Number(device.pay_status || 0) === 1 ? '已打款' : '未打款') }}
                </el-tag>
              </div>
              <div v-if="device.consignment_order_id || device.consignmentOrder" class="mt-2">
                <el-button link type="primary" size="small" @click="props.viewConsignment(device)">
                  {{ device.consignmentOrder?.consignment_no || '查看代卖单' }}
                </el-button>
              </div>

              <div v-if="row.status == 4" class="mt-2 border-t border-dashed border-gray-200 pt-2">
                <el-checkbox
                  :model-value="props.isMobileDeviceSelected(row.id, device.id)"
                  @change="(checked) => props.handleMobileDeviceSelection(row.id, device, checked)"
                >
                  批量选择
                </el-checkbox>
              </div>

              <div class="mt-2 flex flex-wrap items-center gap-2">
                <!-- 主行动：当前状态最该做的下一步 -->
                <el-button
                  v-if="getDevicePrimaryAction(device)"
                  :type="getDevicePrimaryAction(device)!.type || 'primary'"
                  size="small"
                  :icon="getDevicePrimaryAction(device)!.icon"
                  @click="getDevicePrimaryAction(device)!.handler()"
                >
                  {{ getDevicePrimaryAction(device)!.label }}
                </el-button>

                <!-- 更多：次要操作 -->
                <el-dropdown v-if="getDeviceMoreActions(device).length" trigger="click">
                  <el-button size="small" :icon="MoreFilled">更多</el-button>
                  <template #dropdown>
                    <el-dropdown-menu>
                      <el-dropdown-item
                        v-for="a in getDeviceMoreActions(device)"
                        :key="a.key"
                        :divided="a.danger"
                        @click="a.handler()"
                      >
                        <span class="flex items-center gap-1" :class="a.danger ? 'text-red-500' : ''">
                          <el-icon><component :is="a.icon" /></el-icon>{{ a.label }}
                        </span>
                      </el-dropdown-item>
                    </el-dropdown-menu>
                  </template>
                </el-dropdown>

                <el-button
                  type="primary"
                  link
                  :icon="View"
                  @click="props.viewDetail(device)"
                  size="small"
                >
                  详情
                </el-button>
              </div>
            </div>

            <div
              v-if="props.selectedDevices[row.id] && props.selectedDevices[row.id].length > 0 && row.status == 4"
              class="flex justify-end"
            >
              <el-button type="primary" size="small" :icon="Check" @click="props.batchRecycleDevices(row.id)">
                批量确认 ({{ props.selectedDevices[row.id].length }})
              </el-button>
            </div>
          </div>
        </el-collapse-transition>
      </el-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import {
  DocumentChecked,
  PriceTag,
  Check,
  Edit,
  Close,
  Printer,
  View,
  Switch,
  User,
  Loading,
  Share,
  Bell,
  MoreFilled,
} from "@element-plus/icons-vue";
import DeviceStatusBadge from "./DeviceStatusBadge.vue";
import { useDeviceRowActions } from "@/addon/hsx_recycle/hooks/useDeviceRowActions";

interface Props {
  loading: boolean;
  list: any[];
  orderStatusMap: Record<string, any>;
  selectedDevices: Record<string | number, any[]>;
  expressLoading: Record<string | number, boolean>;
  formatPrice: (price: any) => string;
  formatDateTime: (value: any) => string;
  getDeviceCount: (devices: any[]) => number;
  getStatusType: (status: any) => string;
  getStatusEffect: (status: any) => string;
  getDeviceStatusType: (status: any) => string;
  getActionButtonType: (key: string) => string;
  getActionIcon: (key: string) => any;
  findOrderStatus: (orderId: number) => number;
  img: (path: string) => string;
  isMobileOrderExpanded: (orderId: number | string) => boolean;
  toggleMobileOrderExpand: (orderId: number | string) => void;
  isMobileDeviceSelected: (orderId: number | string, deviceId: number | string) => boolean;
  handleMobileDeviceSelection: (
    orderId: number | string,
    device: any,
    checked: string | number | boolean
  ) => void;
  checkDevice: (row: any) => void;
  priceDevice: (row: any) => void;
  batchRecycleDevice: (id: number | string) => void;
  batchReturnDevice: (id: number | string) => void;
  batchRecycleDevices: (orderId: number | string) => void;
  manualPrintActions: any[];
  getVisibleDevicePrintActions: (device: any) => any[];
  printDeviceByScene: (device: any, action: any) => void;
  transferConsignment: (device: any) => void;
  viewConsignment: (device: any) => void;
  viewDetail: (device: any) => void;
  handleAction: (row: any, action: any) => void;
  handleExpressHover: (row: any) => void;
  handleExpressLeave: () => void;
  shareOrder: (row: any) => void;
  viewNoticeLogs: (row: any) => void;
}

const props = defineProps<Props>();

// 设备操作主次：主行动 + 更多动作（与桌面表格共用同一逻辑）
const { getDevicePrimaryAction, getDeviceMoreActions } = useDeviceRowActions({
  checkDevice: props.checkDevice,
  priceDevice: props.priceDevice,
  batchRecycleDevice: props.batchRecycleDevice,
  batchReturnDevice: props.batchReturnDevice,
  transferConsignment: props.transferConsignment,
  getVisibleDevicePrintActions: props.getVisibleDevicePrintActions,
  printDeviceByScene: props.printDeviceByScene,
  findOrderStatus: props.findOrderStatus,
})

const normalizeDeviceCount = (value: any) => {
  const count = Number(value)
  return Number.isFinite(count) && count > 0 ? count : 1
}

const getSubmittedDeviceCount = (row: any) => normalizeDeviceCount(row.count)

const getSignedDeviceCount = (row: any) => props.getDeviceCount(row.devices)

const isDeviceCountMatched = (row: any) => getSubmittedDeviceCount(row) === getSignedDeviceCount(row)
</script>
