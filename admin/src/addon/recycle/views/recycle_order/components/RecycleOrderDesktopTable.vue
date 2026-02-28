<template>
  <el-table
    v-loading="props.loading"
    :data="props.list"
    :expand-row-keys="props.expandRowKeys"
    row-key="id"
    style="width: 100%"
    @expand-change="(row, expandedRows) => props.handleExpandChange(row, expandedRows)"
  >
    <el-table-column type="expand">
      <template #default="{ row }">
        <div class="p-5 bg-slate-50 rounded-md">
          <div class="mb-3 flex items-center justify-between border-b border-gray-200 pb-3">
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
            <el-table-column prop="imei" label="IMEI" min-width="150" />
            <el-table-column prop="model" label="设备型号" min-width="120" />
            <el-table-column prop="final_price" label="最终价格" width="100">
              <template #default="{ row: deviceRow }">
                <span class="text-red-500 font-semibold">{{ props.formatPrice(deviceRow.final_price) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="status_name" label="状态" width="100">
              <template #default="{ row: deviceRow }">
                <el-tag :type="props.getDeviceStatusType(deviceRow.status)" size="small">
                  {{ deviceRow.status_name }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="操作" min-width="280">
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
                    v-if="deviceRow.status == 2 || deviceRow.status == 3"
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
                    v-if="deviceRow.status >= 3"
                    type="info"
                    size="small"
                    :icon="Printer"
                    @click="props.printDeviceLabel(deviceRow)"
                  >
                    打印标签
                  </el-button>
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
          <div>
            <div class="text-sm font-medium text-gray-800">
              {{ row.recycleUserAddress?.name || row.member?.nickname || "未知用户" }}
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
        <el-tag v-if="row.count == props.getDeviceCount(row.devices)" type="success">
          {{ row.count }}/ {{ props.getDeviceCount(row.devices) }}台
        </el-tag>
        <el-tag v-else type="danger">
          {{ row.count ? row.count : "1" }}/
          {{ props.getDeviceCount(row.devices) }}台
        </el-tag>
      </template>
    </el-table-column>

    <el-table-column label="状态" width="120" align="center">
      <template #default="{ row }">
        <el-tag :type="props.getStatusType(row.status)" :effect="props.getStatusEffect(row.status)">
          <el-icon class="mr-1">
            <component :is="props.getStatusIcon(row.status)" />
          </el-icon>
          {{ row.status_name }}
        </el-tag>
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

    <el-table-column label="操作" width="280" fixed="right">
      <template #default="{ row }">
        <el-button-group v-if="props.orderStatusMap[row.status]?.action">
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
        </el-button-group>
        <el-button
          type="success"
          size="small"
          :icon="Share"
          @click="props.shareOrder(row)"
          class="ml-1"
        >
          分享
        </el-button>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup lang="ts">
import {
  Search,
  DocumentChecked,
  PriceTag,
  Check,
  Edit,
  Close,
  Printer,
  View,
  User,
  Loading,
  Share,
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
  printDeviceLabel: (device: any) => void;
  viewDetail: (device: any) => void;
  handleAction: (row: any, action: any) => void;
  handleExpressHover: (row: any) => void;
  handleExpressLeave: () => void;
  shareOrder: (row: any) => void;
}

const props = defineProps<Props>();
</script>
