<template>
  <el-table
    v-loading="props.loading"
    :data="props.list"
    :expand-row-keys="props.expandRowKeys"
    row-key="id"
    height="100%"
    style="width: 100%"
    @expand-change="(row, expandedRows) => props.handleExpandChange(row, expandedRows)"
  >
    <el-table-column type="expand">
      <template #default="{ row }">
        <div class="p-5 bg-slate-50 rounded-md">
          <div class="mb-3 flex items-center  border-b border-gray-200 pb-3">
            <h4 class="text-base font-medium text-gray-800">📱 设备列表</h4>
            <span class="text-sm text-gray-500">共 {{ row.devices?.length || 0 }} 台设备</span>
          </div>

          <el-table
            :data="row.devices"
            border
            size="small"
            @selection-change="(val) => props.handleDeviceSelectionChange(val, row.id)"
          >
            <el-table-column type="selection" width="55" />
            <el-table-column label="串号" width="190">
              <template #default="{ row: deviceRow }">
                <div class="text-xs leading-5">
                  <div v-if="deviceRow.user_sn" class="font-semibold text-gray-800">用户：{{ deviceRow.user_sn }}</div>
                  <div class="text-gray-500">{{ deviceRow.imei || '未录入' }}</div>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="model" label="设备型号" width="250" />
            <el-table-column prop="final_price" label="最终价格" width="100">
              <template #default="{ row: deviceRow }">
                <span class="text-red-500 font-semibold">{{ props.formatPrice(deviceRow.final_price) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="status_name" label="状态" width="100">
              <template #default="{ row: deviceRow }">
                <DeviceStatusBadge :status="deviceRow.status" :status-name="deviceRow.status_name" />
                <div v-if="deviceRow.consignment_order_id || deviceRow.consignmentOrder" class="mt-1">
                  <el-button link type="primary" size="small" @click="props.viewConsignment(deviceRow)">
                    {{ deviceRow.consignmentOrder?.consignment_no || '查看代卖单' }}
                  </el-button>
                </div>
              </template>
            </el-table-column>
            <el-table-column prop="confirm_status_name" label="确认状态" width="110">
              <template #default="{ row: deviceRow }">
                <el-tag v-if="deviceRow.status >= 4" :type="Number(deviceRow.confirm_status || 0) === 1 || deviceRow.status === 5 ? 'success' : 'warning'" size="small">
                  {{ deviceRow.confirm_status_name || (Number(deviceRow.confirm_status || 0) === 1 || deviceRow.status === 5 ? '已确认' : '待客户确认') }}
                </el-tag>
                <span v-else class="text-xs text-gray-400">-</span>
              </template>
            </el-table-column>
            <el-table-column prop="pay_status_name" label="打款状态" width="100">
              <template #default="{ row: deviceRow }">
                <el-tag v-if="deviceRow.status === 5" :type="Number(deviceRow.pay_status || 0) === 1 ? 'success' : 'warning'" size="small">
                  {{ deviceRow.pay_status_name || (Number(deviceRow.pay_status || 0) === 1 ? '已打款' : '未打款') }}
                </el-tag>
                <span v-else class="text-xs text-gray-400">-</span>
              </template>
            </el-table-column>
            <el-table-column label="操作"  fixed="right">
              <template #default="{ row: deviceRow }">
                <el-button-group>
                  <el-button
                    v-if="deviceRow.status === 1 && props.findOrderStatus(deviceRow.order_id) > 1"
                    type="primary"
                    size="small"
                    :icon="DocumentChecked"
                    @click="props.checkDevice(deviceRow)"
                  >
                    开始质检
                  </el-button>

                  <el-button
                    v-if="deviceRow.status == 2"
                    type="warning"
                    size="small"
                    :icon="Edit"
                    @click="props.checkDevice(deviceRow)"
                  >
                    编辑质检
                  </el-button>

                  <el-button
                    v-if="deviceRow.status == 3"
                    type="success"
                    size="small"
                    :icon="PriceTag"
                    @click="props.priceDevice(deviceRow)"
                  >
                    定价
                  </el-button>

                  <el-button
                    v-if="deviceRow.status == 4"
                    type="primary"
                    size="small"
                    :icon="Check"
                    @click="props.batchRecycleDevice(deviceRow.id)"
                  >
                    确认
                  </el-button>
                  <el-button
                    v-if="deviceRow.status == 4"
                    type="warning"
                    size="small"
                    :icon="Edit"
                    @click="props.priceDevice(deviceRow)"
                  >
                    重新定价
                  </el-button>
                  <el-button
                    v-if="deviceRow.status == 4"
                    type="danger"
                    size="small"
                    :icon="Close"
                    @click="props.batchReturnDevice(deviceRow.id)"
                  >
                    拒绝
                  </el-button>
                  <el-button
                    v-if="[3,4,7,8].includes(Number(deviceRow.status)) && !deviceRow.consignment_order_id"
                    type="info"
                    size="small"
                    :icon="Switch"
                    @click="props.transferConsignment(deviceRow)"
                  >
                    转代卖
                  </el-button>

                  <template v-if="props.getVisibleDevicePrintActions(deviceRow).length === 1">
                    <el-button
                      type="info"
                      size="small"
                      :icon="Printer"
                      @click="props.printDeviceByScene(deviceRow, props.getVisibleDevicePrintActions(deviceRow)[0])"
                    >
                      {{ props.getVisibleDevicePrintActions(deviceRow)[0].button_text || '打印' }}
                    </el-button>
                  </template>
                  <el-dropdown
                    v-else-if="props.getVisibleDevicePrintActions(deviceRow).length > 1"
                    trigger="click"
                    @command="(action) => props.printDeviceByScene(deviceRow, action)"
                  >
                    <el-button type="info" size="small" :icon="Printer">
                      打印
                    </el-button>
                    <template #dropdown>
                      <el-dropdown-menu>
                        <el-dropdown-item
                          v-for="action in props.getVisibleDevicePrintActions(deviceRow)"
                          :key="action.scene_key"
                          :command="action"
                        >
                          {{ action.button_text || action.scene_name || '打印' }}
                        </el-dropdown-item>
                      </el-dropdown-menu>
                    </template>
                  </el-dropdown>
                </el-button-group>

                <el-button
                  type="primary"
                  link
                  :icon="View"
                  @click="props.viewDetail(deviceRow)"
                  size="small"
                  class="ml-2"
                >
                  查看详情
                </el-button>
              </template>
            </el-table-column>
          </el-table>

          <div
            v-if="props.selectedDevices[row.id] && props.selectedDevices[row.id].length > 0 && row.status == 4"
            class="mt-3 flex justify-end"
          >
            <el-button type="primary" size="small" :icon="Check" @click="props.batchRecycleDevices(row.id)">
              批量确认 ({{ props.selectedDevices[row.id].length }})
            </el-button>
          </div>
        </div>
      </template>
    </el-table-column>

    <el-table-column label="订单信息" min-width="220">
      <template #default="{ row }">
        <div class="space-y-1">
          <div class="flex items-center text-sm">
            <span class="text-gray-400 min-w-[60px]">订单号：</span>
            <span class="text-gray-800">{{ row.order_no }}</span>
          </div>
          <div class="flex items-center text-sm">
            <span class="text-gray-400 min-w-[60px]">配送：</span>
            <el-tag size="small" :type="row.delivery_type === '1' ? 'warning' : 'success'">
              {{ row.delivery_type === "1" ? "📦 快递" : "🚗 自送" }}
            </el-tag>
          </div>
          <div class="flex items-center text-sm">
            <span class="text-gray-400 min-w-[60px]">来源：</span>
            <el-tag size="small" :type="row.order_source === 'agent' ? 'primary' : 'info'" effect="plain">
              {{ row.order_source === 'agent' ? '代下单' : '客户下单' }}
            </el-tag>
            <span v-if="row.order_source === 'agent' && row.agent_name" class="ml-2 text-xs text-gray-500">
              {{ row.agent_name }}
            </span>
          </div>
          <div v-if="row.delivery_type === '1'" class="flex items-start text-sm">
            <span class="text-gray-400 min-w-[60px]">快递单号：</span>
            <span
              class="flex-1 cursor-pointer rounded px-1 py-0.5 text-gray-800 transition hover:bg-blue-50 hover:text-blue-600 break-all"
              @click="props.handleExpressHover(row)"
              @mouseleave="props.handleExpressLeave"
            >
              <span v-if="!props.expressLoading[row.id]" class="font-mono font-medium">
                {{ row.express_no || "暂无" }}
              </span>
              <el-icon v-else class="animate-spin text-blue-500">
                <Loading />
              </el-icon>
              <el-icon v-if="row.express_no && !props.expressLoading[row.id]" class="ml-1 text-xs text-gray-400">
                <Search />
              </el-icon>
            </span>
          </div>
        </div>
      </template>
    </el-table-column>

    <el-table-column label="用户信息" min-width="160">
      <template #default="{ row }">
        <div class="flex items-center">
          <el-avatar :size="32" :src="row.member?.headimg ? props.img(row.member.headimg) : ''" class="mr-2">
            <el-icon><User /></el-icon>
          </el-avatar>
          <div class="flex-1">
            <div class="text-sm font-medium text-gray-800 flex items-center group">
              <span>{{ getUserDisplayName(row) }}</span>
              <el-icon
                v-if="row.member?.member_id"
                class="ml-1 text-xs text-gray-400 opacity-0 group-hover:opacity-100 cursor-pointer transition-opacity"
                @click="handleEditUsername(row)"
                title="点击编辑昵称"
              >
                <Edit />
              </el-icon>
            </div>
            <div class="text-xs text-gray-400">
              {{ row.member?.mobile || row.recycleUserAddress?.mobile || "暂无联系方式" }}
            </div>
          </div>
        </div>
      </template>
    </el-table-column>

    <el-table-column width="140" align="center">
      <template #header>
        <div class="text-xs">提交数量/签收数量</div>
      </template>
      <template #default="{ row }">
        <el-tag v-if="isDeviceCountMatched(row)" type="success">
          {{ getSubmittedDeviceCount(row) }}/ {{ getSignedDeviceCount(row) }}台
        </el-tag>
        <el-tag v-else type="danger">
          {{ getSubmittedDeviceCount(row) }}/
          {{ getSignedDeviceCount(row) }}台
        </el-tag>
      </template>
    </el-table-column>

    <el-table-column label="状态" width="130" align="center">
      <template #default="{ row }">
        <el-tag :type="props.getStatusType(row.status)" :effect="props.getStatusEffect(row.status)">
          {{ row.status_name }}
        </el-tag>
        <div class="mt-1 text-[11px] text-gray-500">{{ row.flow_mode_name || '整单流转' }}</div>
      </template>
    </el-table-column>

    <el-table-column label="设备进度" min-width="260">
      <template #default="{ row }">
        <div class="flex flex-wrap gap-1 text-xs">
          <template v-if="row.flow_summary?.progress?.length">
            <el-tag
              v-for="item in row.flow_summary.progress.filter(p => p.value > 0 || p.key === 'total')"
              :key="item.key"
              size="small"
              :type="item.color === 'info' ? 'info' : item.color === 'warning' ? 'warning' : item.color === 'primary' ? 'primary' : item.color === 'success' ? 'success' : item.color === 'danger' ? 'danger' : 'info'"
              :effect="item.key === 'paid' ? 'dark' : 'plain'"
            >{{ item.label }} {{ item.value }}</el-tag>
          </template>
          <template v-else>
            <el-tag size="small" type="info" effect="plain">共 {{ row.flow_summary?.total || row.devices?.length || 0 }} 台</el-tag>
            <el-tag size="small" type="warning" effect="plain">待处理 {{ (row.flow_summary?.pending_check || 0) + (row.flow_summary?.checking || 0) }}</el-tag>
            <el-tag size="small" type="primary" effect="plain">待确认 {{ row.flow_summary?.pending_confirm || 0 }}</el-tag>
            <el-tag size="small" type="success" effect="plain">待打款 {{ row.flow_summary?.pending_pay || 0 }}</el-tag>
            <el-tag size="small" type="success" effect="dark">已打款 {{ row.flow_summary?.paid || 0 }}</el-tag>
            <el-tag v-if="(row.flow_summary?.returned || 0) + (row.flow_summary?.consigned || 0) > 0" size="small" type="danger" effect="plain">异常 {{ (row.flow_summary?.returned || 0) + (row.flow_summary?.consigned || 0) }}</el-tag>
          </template>
        </div>
      </template>
    </el-table-column>

    <el-table-column prop="create_at" label="创建时间" width="180">
      <template #default="{ row }">
        {{ props.formatDateTime(row.create_at) }}
      </template>
    </el-table-column>

    <el-table-column prop="sign_at" label="签收时间" width="180">
      <template #default="{ row }">
        {{ props.formatDateTime(row.sign_at) }}
      </template>
    </el-table-column>

    <el-table-column prop="complete_at" label="完成时间" width="180">
      <template #default="{ row }">
        {{ props.formatDateTime(row.complete_at) }}
      </template>
    </el-table-column>

    <el-table-column prop="pay_time" label="打款时间" width="180">
      <template #default="{ row }">
        {{ props.formatDateTime(row.pay_time) }}
      </template>
    </el-table-column>

    <el-table-column label="操作" width="96" fixed="right" align="center">
      <template #default="{ row }">
        <el-popover
          placement="left-start"
          trigger="hover"
          :width="184"
          popper-class="recycle-order-action-popover"
        >
          <div class="recycle-order-action-list">
            <button
              v-for="item in getRowActions(row)"
              :key="item.key"
              type="button"
              class="recycle-order-action-item"
              @click="handleRowAction(row, item)"
            >
              <el-icon>
                <component :is="item.icon" />
              </el-icon>
              <span>{{ item.value }}</span>
            </button>
          </div>
          <template #reference>
            <el-button type="primary" plain size="small" :icon="MoreFilled">更多</el-button>
          </template>
        </el-popover>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import { ElMessageBox, ElMessage } from 'element-plus'
import request from '@/utils/request'
import DeviceStatusBadge from './DeviceStatusBadge.vue'
import {
  Search,
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

interface Props {
  loading: boolean;
  list: any[];
  expandRowKeys: Array<number | string>;
  orderStatusMap: Record<string, any>;
  selectedDevices: Record<string | number, any[]>;
  expressLoading: Record<string | number, boolean>;
  formatPrice: (price: any) => string;
  formatDateTime: (value: any) => string;
  getDeviceCount: (devices: any[]) => number;
  getStatusType: (status: any) => string;
  getStatusEffect: (status: any) => string;
  getStatusIcon: (status: any) => any;
  getDeviceStatusType: (status: any) => string;
  getActionButtonType: (key: string) => string;
  getActionIcon: (key: string) => any;
  findOrderStatus: (orderId: number) => number;
  img: (path: string) => string;
  handleExpandChange: (row: any, expandedRows: any[]) => void;
  handleDeviceSelectionChange: (val: any[], orderId: string | number) => void;
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
const emit = defineEmits(['refresh'])

const normalizeDeviceCount = (value: any) => {
  const count = Number(value)
  return Number.isFinite(count) && count > 0 ? count : 1
}

const getSubmittedDeviceCount = (row: any) => normalizeDeviceCount(row.count)

const getSignedDeviceCount = (row: any) => props.getDeviceCount(row.devices)

const isDeviceCountMatched = (row: any) => getSubmittedDeviceCount(row) === getSignedDeviceCount(row)

const getRowActions = (row: any) => {
  const statusActions = props.orderStatusMap[row.status]?.action || []
  const actions = statusActions.map((action: any) => ({
    key: action.key,
    value: action.value,
    type: 'order',
    icon: props.getActionIcon(action.key),
    raw: action,
  }))

  if (
    row.available_actions?.can_pay_devices &&
    !statusActions.some((action: any) => action.key === 'order_payment')
  ) {
    actions.push({
      key: 'order_payment',
      value: '去打款',
      type: 'order',
      icon: props.getActionIcon('order_payment'),
      raw: { key: 'order_payment', value: '去打款' },
    })
  }

  if (
    row.available_actions?.can_push_confirm_notice &&
    !statusActions.some((action: any) => action.key === 'order_push_notify')
  ) {
    actions.push({
      key: 'order_push_notify',
      value: '推送通知',
      type: 'order',
      icon: Bell,
      raw: { key: 'order_push_notify', value: '推送通知' },
    })
  }

  actions.push(
    { key: 'share_order', value: '分享订单', type: 'share', icon: Share, raw: null },
    { key: 'notice_logs', value: '通知记录', type: 'notice_logs', icon: Bell, raw: null },
  )

  return actions
}

const handleRowAction = (row: any, item: any) => {
  if (item.type === 'share') {
    props.shareOrder(row)
    return
  }
  if (item.type === 'notice_logs') {
    props.viewNoticeLogs(row)
    return
  }
  props.handleAction(row, item.raw)
}

// 获取用户显示名称（优先级：nickname → recycleUserAddress.name → "未知用户"）
const getUserDisplayName = (row: any) => {
  return row.member?.nickname || row.recycleUserAddress?.name || "未知用户"
}

// 编辑用户昵称
const handleEditUsername = async (row: any) => {
  if (!row.member?.member_id) {
    ElMessage.warning('该订单没有关联会员，无法编辑昵称')
    return
  }

  const currentNickname = row.member?.nickname || ''

  try {
    const { value } = await ElMessageBox.prompt('请输入用户昵称', '编辑昵称', {
      confirmButtonText: '确定',
      cancelButtonText: '取消',
      inputValue: currentNickname,
      inputPlaceholder: '请输入昵称',
      inputValidator: (value) => {
        if (!value || value.trim() === '') {
          return '昵称不能为空'
        }
        if (value.length > 50) {
          return '昵称长度不能超过50个字符'
        }
        return true
      }
    })

    if (value && value.trim()) {
      // 调用 API 更新 nickname
      await request.put(`member/member/modify/${row.member.member_id}/nickname`, {
        value: value.trim(),
        field: 'nickname'
      }, { showSuccessMessage: true })

      // 更新本地数据
      if (row.member) {
        row.member.nickname = value.trim()
      }
      // 触发刷新
      emit('refresh')
    }
  } catch (error: any) {
    if (error !== 'cancel') {
      console.error('更新昵称失败:', error)
    }
  }
}
</script>

<style scoped>
:deep(.el-table__fixed-right .el-table__cell) {
  overflow: visible;
}
</style>

<style>
.recycle-order-action-popover {
  padding: 8px !important;
}

.recycle-order-action-list {
  display: grid;
  gap: 4px;
}

.recycle-order-action-item {
  display: flex;
  width: 100%;
  align-items: center;
  gap: 8px;
  border: 0;
  border-radius: 6px;
  background: transparent;
  padding: 8px 10px;
  color: #334155;
  cursor: pointer;
  font-size: 13px;
  line-height: 18px;
  text-align: left;
  transition: background-color 0.16s ease, color 0.16s ease, transform 0.16s ease;
}

.recycle-order-action-item:hover {
  background: #eff6ff;
  color: #1d4ed8;
  transform: translateX(-2px);
}
</style>
