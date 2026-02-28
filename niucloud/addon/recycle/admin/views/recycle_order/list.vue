<template>
  <div class="recycle-order-list h-full">
    <el-card class="box-card !border-none h-full relative" shadow="never">
      <div class="flex justify-between items-center mb-4">
        <span class="text-xl font-semibold text-gray-800">📱 回收订单管理</span>
        <div class="btn-wrap">
          <el-button type="primary" :icon="Plus" @click="showAddOrderDialog">代下单</el-button>
        </div>
      </div>

      <!-- 🔍 智能搜索系统 - 吸顶固定显示 -->
      <div
        class="mb-3 sticky top-30 z-50 bg-white/95 backdrop-blur-sm shadow-lg rounded-lg p-2 -mx-2"
      >
        <!-- 搜索表单 -->
        <div class="p-2">
          <el-form :inline="true" :model="advancedSearchForm">
            <!-- 第一行：订单信息 -->
            <el-form-item label="订单编号">
              <el-input
                v-model="advancedSearchForm.order_id"
                placeholder="输入精确订单号"
                clearable
                class="w-full"
              />
            </el-form-item>

            <el-form-item label="快递单号">
              <el-input
                v-model="advancedSearchForm.express_no"
                placeholder="输入快递单号"
                clearable
                class="w-full"
              />
            </el-form-item>

            <el-form-item label="订单状态">
              <el-select
                v-model="advancedSearchForm.status"
                placeholder="选择状态"
                clearable
                multiple
                collapse-tags
                class="w-full"
              >
                <el-option
                  v-for="(status, key) in orderStatusMap"
                  :key="key"
                  :label="status.name"
                  :value="status.status"
                >
                </el-option>
              </el-select>
            </el-form-item>
            <el-form-item label="用户搜索">
              <member-select
                v-model="advancedSearchForm.member_id"
                placeholder="🔍 输入用户昵称、手机号或用户编号"
                @change="handleMemberChange"
                class="w-full"
              />
            </el-form-item>
            <el-form-item label="用户手机号">
              <el-input
                v-model="advancedSearchForm.user_mobile"
                placeholder="输入用户手机号"
                clearable
                class="w-full"
              />
            </el-form-item>

            <el-form-item label="配送方式">
              <el-select
                v-model="advancedSearchForm.delivery_type"
                placeholder="选择配送方式"
                clearable
                multiple
                class="w-full"
              >
                <el-option label="📦 快递配送" value="1" />
                <el-option label="🚗 自送到店" value="2" />
              </el-select>
            </el-form-item>
            <el-form-item label="设备IMEI">
              <el-input
                v-model="advancedSearchForm.device_imei"
                placeholder="输入设备IMEI号"
                clearable
                class="w-full"
              />
            </el-form-item>
            <el-form-item label="设备型号">
              <el-input
                v-model="advancedSearchForm.device_model"
                placeholder="输入设备型号"
                clearable
                class="w-full"
              />
            </el-form-item>
            <el-form-item label="创建时间">
              <el-date-picker
                v-model="advancedSearchForm.create_time_range"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                class="w-full"
              />
            </el-form-item>
            <!-- 签收时间 -->
            <el-form-item label="签收时间">
              <el-date-picker
                v-model="advancedSearchForm.sign_at"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                class="w-full"
              />
            </el-form-item>
            <!-- 完成时间 -->
            <el-form-item label="质检时间">
              <el-date-picker
                v-model="advancedSearchForm.complete_at"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                class="w-full"
              />
            </el-form-item>
            <!-- 打款时间 -->
            <el-form-item label="打款时间">
              <el-date-picker
                v-model="advancedSearchForm.pay_time"
                type="daterange"
                range-separator="至"
                start-placeholder="开始日期"
                end-placeholder="结束日期"
                format="YYYY-MM-DD"
                value-format="YYYY-MM-DD"
                class="w-full"
              />
            </el-form-item>

            <!-- 操作按钮 -->
            <div class="flex justify-center pt-4 border-t border-orange-200">
              <div class="flex gap-3">
                <el-button
                  type="primary"
                  :icon="Search"
                  @click="advancedSearch"
                  class="bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 border-0 shadow-sm px-6"
                >
                  执行高级搜索
                </el-button>
                <el-button
                  :icon="Refresh"
                  @click="resetAdvancedSearch"
                  class="border-gray-300 text-gray-600 hover:border-gray-400 px-6"
                >
                  重置所有条件
                </el-button>
              </div>
            </div>
          </el-form>
        </div>
      </div>

      <!-- 状态标签页 -->
      <el-tabs
        v-model="activeTab"
        @tab-click="handleTabClick"
        class="order-tabs"
      >
        <el-tab-pane name="">
          <template #label>
            <div class="flex items-center">
              <el-icon class="mr-1"><Document /></el-icon>
              <span>全部</span>
            </div>
          </template>
        </el-tab-pane>
        <el-tab-pane
          v-for="(item, key) in orderStatusMap"
          :key="key"
          :name="item.status"
        >
          <template #label>
            <div class="flex items-center">
              <el-icon class="mr-1">
                <component :is="getStatusIcon(item.status)" />
              </el-icon>
              <span>{{ item.name }}</span>
              <el-badge
                v-if="item.status < 7"
                :value="getStatusCount(item.status)"
                class="ml-1"
                :type="getStatusBadgeType(item.status)"
              />
            </div>
          </template>
        </el-tab-pane>
      </el-tabs>

      <!-- 列表 -->
      <el-table
        ref="orderTable"
        v-loading="loading"
        :data="list"
        :expand-row-keys="expandRowKeys"
        row-key="id"
        style="width: 100%"
        @expand-change="handleExpandChange"
        class="order-table"
      >
        <el-table-column type="expand">
          <template #default="{ row }">
            <div class="device-expansion-panel">
              <div class="panel-header mb-3">
                <h4 class="text-lg font-medium text-gray-800">📱 设备列表</h4>
                <span class="text-sm text-gray-500"
                  >共 {{ row.devices?.length || 0 }} 台设备</span
                >
              </div>
              <el-table
                :data="row.devices"
                border
                size="small"
                @selection-change="
                  (val) => handleDeviceSelectionChange(val, row.id)
                "
                class="device-table"
              >
                <el-table-column type="selection" width="55" />

                <el-table-column prop="imei" label="IMEI" min-width="150" />
                <el-table-column
                  prop="model"
                  label="设备型号"
                  min-width="120"
                />
                <el-table-column
                  prop="final_price"
                  label="最终价格"
                  width="100"
                >
                  <template #default="{ row }">
                    <span class="price-text">{{
                      formatPrice(row.final_price)
                    }}</span>
                  </template>
                </el-table-column>
                <el-table-column prop="status_name" label="状态" width="100">
                  <template #default="{ row }">
                    <el-tag
                      :type="getDeviceStatusType(row.status)"
                      size="small"
                    >
                      {{ row.status_name }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="操作" min-width="280">
                  <template #default="{ row }">
                    <el-button-group>
                      <el-button
                        v-if="
                          row.status === 1 && findOrderStatus(row.order_id) > 1
                        "
                        type="primary"
                        size="small"
                        :icon="DocumentChecked"
                        @click="checkDevice(row)"
                      >
                        开始质检
                      </el-button>

                      <el-button
                        v-if="row.status == 2 || row.status == 3"
                        type="success"
                        size="small"
                        :icon="PriceTag"
                        @click="priceDevice(row)"
                      >
                        定价
                      </el-button>

                      <el-button
                        v-if="row.status == 4"
                        type="primary"
                        size="small"
                        :icon="Check"
                        @click="batchRecycleDevice(row.id)"
                      >
                        确认
                      </el-button>
                      <el-button
                        v-if="row.status == 4"
                        type="warning"
                        size="small"
                        :icon="Edit"
                        @click="priceDevice(row)"
                      >
                        重新定价
                      </el-button>
                      <el-button
                        v-if="row.status == 4"
                        type="danger"
                        size="small"
                        :icon="Close"
                        @click="batchReturnDevice(row.id)"
                      >
                        拒绝
                      </el-button>

                      <el-button
                        v-if="row.status >= 3"
                        type="info"
                        size="small"
                        :icon="Printer"
                        @click="printDeviceLabel(row)"
                      >
                        打印标签
                      </el-button>
                    </el-button-group>

                    <el-button
                      type="primary"
                      link
                      :icon="View"
                      @click="viewDetail(row)"
                      size="small"
                      class="ml-2"
                    >
                      查看详情
                    </el-button>
                  </template>
                </el-table-column>
              </el-table>

              <!-- 批量操作按钮 -->
              <div
                class="flex justify-end mt-3"
                v-if="
                  selectedDevices[row.id] &&
                  selectedDevices[row.id].length > 0 &&
                  row.status == 4
                "
              >
                <el-button-group>
                  <el-button
                    type="primary"
                    size="small"
                    :icon="Check"
                    @click="batchRecycleDevices(row.id)"
                  >
                    批量确认 ({{ selectedDevices[row.id].length }})
                  </el-button>
                </el-button-group>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="订单信息" min-width="200">
          <template #default="{ row }">
            <div class="order-info-cell">
              <div class="info-row">
                <span class="label">订单号：</span>
                <span class="value">{{ row.order_no }}</span>
              </div>
              <div class="info-row">
                <span class="label">配送：</span>
                <span class="value">
                  <el-tag
                    size="small"
                    :type="row.delivery_type === '1' ? 'warning' : 'success'"
                  >
                    {{ row.delivery_type === "1" ? "📦 快递" : "🚗 自送" }}
                  </el-tag>
                </span>
              </div>
              <div v-if="row.delivery_type === '1'" class="info-row">
                <span class="text-gray-500 text-xs min-w-16">快递单号：</span>
                <span
                  class="flex-1 cursor-pointer transition-all duration-300 ease-in-out rounded px-1 py-0.5 hover:bg-blue-50 hover:text-blue-600 text-gray-800 text-sm break-all"
                  @click="handleExpressHover(row)"
                  @mouseleave="handleExpressLeave"
                >
                  <span
                    v-if="!expressLoading[row.id]"
                    class="font-mono font-medium"
                  >
                    {{ row.express_no || "暂无" }}
                  </span>
                  <el-icon v-else class="animate-spin text-blue-500">
                    <Loading />
                  </el-icon>
                  <el-icon
                    v-if="row.express_no && !expressLoading[row.id]"
                    class="ml-1 text-gray-400 text-xs"
                  >
                    <Search />
                  </el-icon>
                </span>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="用户信息" min-width="150">
          <template #default="{ row }">
            <div class="user-info-cell">
              <div class="user-avatar">
                <el-avatar
                  :size="32"
                  :src="row.member?.headimg ? img(row.member.headimg) : ''"
                  class="mr-2"
                >
                  <el-icon><User /></el-icon>
                </el-avatar>
              </div>
              <div class="user-details">
                <div class="user-name">
                  {{
                    row.recycleUserAddress?.name ||
                    row.member?.nickname ||
                    "未知用户"
                  }}
                </div>
                <div class="user-phone">
                  {{
                    row.member?.mobile ||
                    row.recycleUserAddress?.mobile ||
                    "暂无联系方式"
                  }}
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
            <el-tag
              v-if="row.count == getDeviceCount(row.devices)"
              type="success"
              class="device-count-tag"
            >
              {{ row.count }}/ {{ getDeviceCount(row.devices) }}台
            </el-tag>
            <el-tag v-else type="danger" class="device-count-tag">
              {{ row.count ? row.count : "1" }}/
              {{ getDeviceCount(row.devices) }}台
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="120" align="center">
          <template #default="{ row }">
            <el-tag
              :type="getStatusType(row.status)"
              :effect="getStatusEffect(row.status)"
            >
              <el-icon class="mr-1">
                <component :is="getStatusIcon(row.status)" />
              </el-icon>
              {{ row.status_name }}
            </el-tag>
          </template>
        </el-table-column>

        <el-table-column prop="create_at" label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.create_at) }}
          </template>
        </el-table-column>

        <el-table-column prop="sign_at" label="签收时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.sign_at) }}
          </template>
        </el-table-column>

        <el-table-column prop="complete_at" label="完成时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.complete_at) }}
          </template>
        </el-table-column>

        <el-table-column prop="pay_time" label="打款时间" width="180">
          <template #default="{ row }">
            {{ formatDateTime(row.pay_time) }}
          </template>
        </el-table-column>

        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button-group v-if="orderStatusMap[row.status]?.action">
              <el-button
                v-for="action in orderStatusMap[row.status].action"
                :key="action.key"
                size="small"
                :type="getActionButtonType(action.key)"
                :icon="getActionIcon(action.key)"
                @click="handleAction(row, action)"
              >
                {{ action.value }}
              </el-button>
            </el-button-group>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="flex justify-between items-center mt-4">
        <div class="text-sm text-gray-500">
          共 {{ pagination.total }} 条记录，当前第 {{ pagination.page }} 页
        </div>

        <el-pagination
          v-model:current-page="pagination.page"
          :page-sizes="[15, 30, 50, 100]"
          :page-size="pagination.limit"
          :total="pagination.total"
          :hide-on-single-page="false"
          layout="  sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handleCurrentChange"
        />
      </div>
    </el-card>

    <!-- 使用代下单弹窗组件 -->
    <AddOrderDialog
      v-model:visible="addOrderDialogVisible"
      @success="handleAddOrderSuccess"
    />

    <!-- 设备信息确认对话框组件 -->
    <DeviceConfirmDialog
      v-model:visible="orderDialogVisible"
      :device-list="currentDevices"
      :order-id="currentOrderId"
      @confirm="handleDeviceConfirm"
      @add-device="addDevice"
      @edit-device="editDevice"
      @remove-device="removeDevice"
      @cancel="cancelDeviceEdit"
    />

    <!-- 添加新的质检对话框组件 -->
    <CheckDeviceDialog
      v-model:visible="checkDeviceLogVisible"
      :device="checkDeviceLogForm"
      @confirm="submitDeviceCheck"
      @save-draft="handleCheckDeviceSaveDraft"
    />

    <PriceFormDialog
      v-model:visible="priceDeviceLogVisible"
      :device="checkDeviceLogForm"
      @confirm="submitDevicePrice"
    />

    <!-- 使用设备详情对话框组件 -->
    <DeviceDetailDialog
      v-model:visible="deviceLogVisible"
      :device="deviceDetailData"
      @closed="handleDeviceDetailClosed"
    />

    <!-- 订单详情 -->
    <OrderDetailDialog
      v-model:visible="orderDetailVisible"
      :order-detail="orderDetail"
    />
    <!-- 支付方式 -->
    <PaymentMethodDialog
      v-model:visible="paymentDialogVisible"
      :payment-info="paymentInfo"
      :order-id="currentOrderId"
      @payment-confirmed="handlePaymentConfirm"
    />

    <!-- 快递信息弹出框 -->
    <el-dialog
      v-model="expressPopoverVisible"
      title="快递物流信息"
      width="600px"
      :destroy-on-close="true"
    >
      <div v-if="expressInfo" class="express-info-container">
        <!-- 快递基本信息 -->
        <div class="express-header mb-4">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-lg font-medium">
                {{ expressInfo.logisticsCompanyName }}
              </h3>
              <p class="text-sm text-gray-600">
                运单号：{{ expressInfo.mailNo }}
              </p>
            </div>
            <el-tag
              :type="getExpressStatusType(expressInfo.logisticsStatus)"
              size="large"
            >
              {{ expressInfo.logisticsStatusDesc }}
            </el-tag>
          </div>
          <div class="mt-2">
            <p class="text-sm text-gray-600">
              最新状态：{{ expressInfo.theLastMessage }}
            </p>
            <p class="text-xs text-gray-500">
              更新时间：{{ expressInfo.theLastTime }}
            </p>
          </div>
        </div>

        <!-- 物流轨迹 -->
        <div class="express-trace">
          <h4 class="text-md font-medium mb-3">物流轨迹</h4>
          <el-timeline>
            <el-timeline-item
              v-for="(item, index) in expressInfo.logisticsTraceDetailList"
              :key="index"
              :timestamp="item.timeDesc"
              :type="index === 0 ? 'primary' : 'info'"
              :size="index === 0 ? 'large' : 'normal'"
            >
              <div class="trace-item">
                <div class="trace-location">{{ item.areaName }}</div>
                <div class="trace-desc">{{ item.desc }}</div>
              </div>
            </el-timeline-item>
          </el-timeline>
        </div>
      </div>

      <div v-else class="text-center py-8 text-gray-500">暂无快递信息</div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted, nextTick, watch, computed } from "vue";
import {
  ElMessage,
  ElMessageBox,
  ElLoading,
  ElNotification,
} from "element-plus";
import {
  Plus,
  Search,
  Refresh,
  ArrowDown,
  ArrowUp,
  Document,
  DocumentChecked,
  PriceTag,
  Check,
  Edit,
  Close,
  Printer,
  View,
  User,
  Clock,
  ShoppingBag,
  QuestionFilled,
  SuccessFilled,
  WarningFilled,
  CircleClose,
  InfoFilled,
  Filter,
  Download,
  Loading,
  Bell,
} from "@element-plus/icons-vue";
import { usePageState } from "../../hooks/usePageState";
// 路由
import { useRoute } from "vue-router";
const route = useRoute();

import {
  getRecycleOrderList,
  getRecycleOrderStatusList,
  updateRecycleOrder,
  getRecycleOrderInfo,
  // 定价
  confirmPrice,
  getDevice,
  updateDevice,
  getMerchantPayInfo,
  paymentConfirm,
  batchRecycleDevices as apiBatchRecycleDevices,
  batchReturnDevices as apiBatchReturnDevices,
  deleteRecycleOrder,
  deleteRecycleDevice,
  pushOrderNotify, // 推送通知API
} from "@/addon/recycle/api/recycle_order";
import { getExpress } from "@/addon/recycle/api/device_query_api";

// 导入打印API
import { _printDeviceLabel } from "@/addon/recycle/api/printer";

// 导入代下单弹窗组件
import AddOrderDialog from "./components/AddOrderDialog.vue";
// 导入设备列表弹窗组件
// import DeviceListDialog from './components/DeviceListDialog.vue'
// 导入设备详情弹窗组件
// import DeviceDetailDialog from './components/DeviceDetailDialog.vue'
// 导入新的支付方式对话框组件
import PaymentMethodDialog from "./components/PaymentMethodDialog.vue";
// 导入订单详情弹窗组件
import OrderDetailDialog from "./components/OrderDetailDialog.vue";
// 导入定价表单组件
import PriceFormDialog from "./components/PriceFormDialog.vue";
import CheckDeviceDialog from "./components/CheckDeviceDialog.vue";
// 导入设备确认对话框组件
import DeviceConfirmDialog from "./components/DeviceConfirmDialog.vue";
// 导入设备详情对话框组件
import DeviceDetailDialog from "./components/DeviceDetailDialog.vue";

import MemberSelect from "@/addon/recycle/components/member-select/index.vue";

// 引入图片预览工具
import { img, debounce } from "@/utils/common";

// 状态定义
interface OrderStatus {
  name: string;
  status: number;
  action: string[];
}

// 订单列表项定义
interface OrderItem {
  id: number;
  status: number;
  status_name: string;
  delivery_type: string;
  delivery_type_name: string;
  express_no: string;
  create_at: string;
  update_at: string;
  devices: any[];
  member: {
    member_id: number;
    nickname: string;
    mobile: string;
    headimg: string;
  };
}

// 分页参数
interface Pagination {
  page: number;
  limit: number;
  total: number;
}

// 定义订单详情类型
interface OrderDetail {
  id: number | string;
  status: number;
  status_name: string;
  customer_name?: string;
  customer_phone?: string;
  pay_type?: string;
  total_amount?: number | string;
  delivery_type_name?: string;
  express_company?: string;
  express_no?: string;
  device_count?: number;
  create_at?: string;
  update_at?: string;
  sign_at?: string;
  complete_at?: string;
  pay_time?: string;
  remark?: string;
  member?: {
    member_id: number | string;
    username?: string;
    nickname?: string;
    mobile?: string;
  };
  devices?: any[];
  [key: string]: any;
}

// 状态管理
const orderStatusMap = ref<Record<string, OrderStatus>>({});
const loading = ref(false);
const list = ref<OrderItem[]>([]);

// 分页
const {
  expandedRows,
  pagination,
  setExpandedRows,
  setPagination,
  applyExpandedRows,
} = usePageState("recycle_order_list");

// 计算属性：确保展开行键是正确的格式
const expandRowKeys = computed(() => {
  return expandedRows.value || [];
});

// 时间格式化函数
const formatDateTime = (
  dateTime: string | number | null | undefined
): string => {
  if (!dateTime) return "-";

  // 如果是时间戳（数字）
  if (typeof dateTime === "number") {
    const date = new Date(dateTime * 1000);
    return date
      .toLocaleString("zh-CN", {
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
        hour: "2-digit",
        minute: "2-digit",
        second: "2-digit",
        hour12: false,
      })
      .replace(/\//g, "-");
  }

  // 如果是字符串格式
  if (typeof dateTime === "string") {
    // 如果已经是格式化的日期时间字符串，直接返回
    if (dateTime.match(/^\d{4}-\d{2}-\d{2}/)) {
      return dateTime;
    }
  }

  return "-";
};

const orderDialogVisible = ref(false);
const paymentDialogVisible = ref(false);
const currentOrderId = ref<number | string>(0);
const currentDevices = ref<any[]>([]);
const orderDetailVisible = ref(false);
const orderDetail = ref<OrderDetail | null>(null);
const originalDevices = ref<any[]>([]);
const paymentInfo = ref<any[]>([]);
const selectedPayTypeIndex = ref(0);

// 快递信息相关
const expressLoading = ref<Record<string, boolean>>({});
const expressPopoverVisible = ref(false);
const expressInfo = ref<any>(null);
const currentExpressRow = ref<any>(null);
const hoverTimer = ref<any>(null);

// 设备管理相关
const openDeviceDialog = async (orderId: number | string) => {
  currentOrderId.value = orderId;

  try {
    const res = await getRecycleOrderInfo(orderId);
    if (res.code === 1) {
      // 将设备列表数据标记为非编辑状态
      currentDevices.value = res.data.devices.map((device: any) => ({
        ...device,
        editing: false,
      }));
      originalDevices.value = JSON.parse(JSON.stringify(currentDevices.value));
      orderDialogVisible.value = true;
    } else {
      ElMessage.error(res.message || "获取订单信息失败");
    }
  } catch (error: any) {
    console.error("获取订单信息失败：", error);
    ElMessage.error("获取订单信息失败：" + (error.message || "未知错误"));
  }
};

// 添加设备
const addDevice = () => {
  // 添加一个新设备到列表中
  currentDevices.value.push({
    imei: "",
    model: "",
    initial_price: 0,
    editing: true,
    category_id: 1,
  });
};

// 编辑设备
const editDevice = (row: any) => {
  // 查找设备并设置为编辑状态
  const index = currentDevices.value.findIndex(
    (item) => item === row || item.id === row.id
  );
  if (index !== -1) {
    currentDevices.value[index].editing = true;
  }
};

// 删除设备
const removeDevice = async (row: any) => {
  try {
    // 仅处理已保存到数据库的设备（有ID的设备）
    if (row.id) {
      await deleteRecycleDevice(row.id);
      ElMessage.success("删除成功");
      await getList();
      orderDialogVisible.value = false;
    }

    // 同时从父组件的设备列表中移除该设备（处理本地设备和已保存设备）
    currentDevices.value = currentDevices.value.filter(
      (item) => item !== row && item.id !== row.id
    );
  } catch (error: any) {
    console.error("删除设备失败：", error);
    ElMessage.error("删除失败：" + (error.message || "未知错误"));
  }
};

// 取消设备编辑
const cancelDeviceEdit = () => {
  orderDialogVisible.value = false;
  // 清空当前设备列表
  currentDevices.value = [];
  currentOrderId.value = 0;
};

// 快速搜索表单
const quickSearchForm = reactive({
  keyword: "",
  status: "",
  delivery_type: "",
});

// 高级搜索表单
const advancedSearchForm = reactive({
  order_id: "",
  express_no: "",
  status: [],
  member_id: "",
  user_mobile: "",
  delivery_type: [],
  device_imei: "",
  device_model: "",
  device_count_min: null,
  device_count_max: null,
  amount_min: null,
  amount_max: null,
  create_time_range: [],
  update_time_range: [],
  sign_at: [],
  complete_at: [],
  pay_time: [],
});

// 搜索显示控制
const showAdvancedSearch = ref(true);

// 获取状态列表
const loadStatusList = async () => {
  try {
    const res = await getRecycleOrderStatusList();
    if (res.code === 1) {
      orderStatusMap.value = res.data;
    }
  } catch (error) {
    console.error("获取状态列表失败:", error);
  }
};

// 表格ref
const orderTable = ref(null);

// 获取列表数据
const getList = async (page = 1) => {
  loading.value = true;

  try {
    // 构建请求参数，过滤空值
    const params: any = {
      page: pagination.value.page,
      limit: pagination.value.limit,
    };

    // 添加快速搜索参数
    if (quickSearchForm.keyword) params.keyword = quickSearchForm.keyword;
    if (quickSearchForm.status) params.status = quickSearchForm.status;
    if (quickSearchForm.delivery_type)
      params.delivery_type = quickSearchForm.delivery_type;

    // 添加高级搜索参数
    if (advancedSearchForm.order_id)
      params.order_id = advancedSearchForm.order_id;
    if (advancedSearchForm.express_no)
      params.express_no = advancedSearchForm.express_no;
    if (advancedSearchForm.status && advancedSearchForm.status.length > 0)
      params.status = advancedSearchForm.status.join(",");
    if (advancedSearchForm.member_id)
      params.member_id = advancedSearchForm.member_id;
    if (advancedSearchForm.user_mobile)
      params.user_mobile = advancedSearchForm.user_mobile;
    if (
      advancedSearchForm.delivery_type &&
      advancedSearchForm.delivery_type.length > 0
    )
      params.delivery_type = advancedSearchForm.delivery_type.join(",");
    if (advancedSearchForm.device_imei)
      params.device_imei = advancedSearchForm.device_imei;
    if (advancedSearchForm.device_model)
      params.device_model = advancedSearchForm.device_model;
    if (advancedSearchForm.device_count_min)
      params.device_count_min = advancedSearchForm.device_count_min;
    if (advancedSearchForm.device_count_max)
      params.device_count_max = advancedSearchForm.device_count_max;
    if (advancedSearchForm.amount_min)
      params.amount_min = advancedSearchForm.amount_min;
    if (advancedSearchForm.amount_max)
      params.amount_max = advancedSearchForm.amount_max;
    if (
      advancedSearchForm.create_time_range &&
      advancedSearchForm.create_time_range.length === 2
    ) {
      params.create_time_start = advancedSearchForm.create_time_range[0];
      params.create_time_end = advancedSearchForm.create_time_range[1];
    }
    if (
      advancedSearchForm.update_time_range &&
      advancedSearchForm.update_time_range.length === 2
    ) {
      params.update_time_start = advancedSearchForm.update_time_range[0];
      params.update_time_end = advancedSearchForm.update_time_range[1];
    }
    // 打款时间
    if (
      advancedSearchForm.pay_time &&
      advancedSearchForm.pay_time.length === 2
    ) {
      params.pay_time = advancedSearchForm.pay_time;
    }
    // 签收时间
    if (advancedSearchForm.sign_at && advancedSearchForm.sign_at.length === 2) {
      params.sign_at = advancedSearchForm.sign_at;
    }
    // 完成时间
    if (
      advancedSearchForm.complete_at &&
      advancedSearchForm.complete_at.length === 2
    ) {
      params.complete_at = advancedSearchForm.complete_at;
    }

    const res = await getRecycleOrderList(params);

    // 根据实际API返回结构调整
    if (res.data.data) {
      list.value = res.data.data;
    } else if (res.data.list) {
      list.value = res.data.list;
    } else {
      list.value = [];
      console.error("API返回的数据结构不符合预期:", res.data);
    }
    // 处理不同的分页数据结构
    const total = res.data.total || res.data.count || 0;
    // status_counts
    statusCounts.value = res.data.status_counts;

    setPagination({
      total,
    });

    // 不需要手动展开行了，因为我们使用了:expand-row-keys绑定
  } catch (error) {
    console.error("获取列表失败:", error);
    ElMessage.error("获取列表失败");
  } finally {
    loading.value = false;
  }
};

const handleSizeChange = (val: number) => {
  // 更新分页大小
  setPagination({
    limit: val,
    page: pagination.value.page,
  });
  getList(1);
};

const handleCurrentChange = (val: number) => {
  // 更新页码
  setPagination({
    page: val,
    limit: pagination.value.limit,
  });
  getList(val);
};

// 搜索面板折叠状态
const searchPanelCollapsed = ref(false);

// 状态统计
const statusCounts = ref<Record<string, number>>({});

// 获取状态数量
const getStatusCount = (status: string | number) => {
  if (status === "all") {
    return pagination.value.total;
  }
  return statusCounts.value[status] || 0;
};

// 优化的状态标签类型 - 避免颜色冲突
const getStatusType = (status: number): string => {
  const typeMap: Record<number, string> = {
    1: "warning", // 待签收 - 橙色
    2: "warning", // 已签收 - 默认灰色
    3: "primary", // 质检中 - 蓝色
    4: "success", // 已质检 - 绿色
    5: "warning", // 待确认 - 橙色
    6: "primary", // 待打款 - 蓝色
    7: "success", // 已完成 - 绿色
    8: "info", // 已关闭 - 蓝色
    9: "danger", // 已取消 - 红色
    10: "danger", // 已删除 - 红色
  };
  return typeMap[status] || "info";
};

// 状态效果类型
const getStatusEffect = (status: number): string => {
  const effectMap: Record<number, string> = {
    1: "light", // 待签收
    2: "light", // 已签收
    3: "light", // 质检中
    4: "light", // 已质检
    5: "light", // 待确认
    6: "dark", // 待打款
    7: "dark", // 已完成
    8: "plain", // 已关闭
    9: "dark", // 已取消
    10: "dark", // 已删除
  };
  return effectMap[status] || "plain";
};

// 设备状态类型
const getDeviceStatusType = (status: number): string => {
  const typeMap: Record<number, string> = {
    1: "info", // 待质检
    2: "warning", // 质检中
    3: "primary", // 已质检
    4: "success", // 已定价
    5: "success", // 已确认
    6: "danger", // 已退回
  };
  return typeMap[status] || "info";
};

// 状态图标
const getStatusIcon = (status: number) => {
  switch (status) {
    case 1:
      return "Clock";
    case 2:
      return "Finished";
    case 3:
      return "Loading";
    case 4:
      return "DocumentChecked";
    case 5:
      return "CircleCheck";
    case 6:
      return "Money";
    case 7:
      return "CircleCheckFilled";
    case 8:
      return "CircleClose";

    default:
      return "QuestionFilled";
  }
};

// 状态徽章类型
const getStatusBadgeType = (status: number): string => {
  const typeMap: Record<number, string> = {
    1: "warning", // 待签收
    2: "info", // 已签收
    3: "primary", // 质检中
    4: "success", // 已质检
    5: "warning", // 待确认
    6: "primary", // 待打款
    7: "success", // 已完成
    8: "info", // 已关闭
    9: "danger", // 已取消
    10: "danger", // 已删除
  };
  return typeMap[status] || "info";
};

// 操作按钮类型
const getActionButtonType = (actionKey: string): string => {
  const typeMap: Record<string, string> = {
    order_sign: "primary",
    order_check: "success",
    order_price: "warning",
    order_payment: "primary",
    order_complete: "success",
    order_cancel: "danger",
    order_delete: "danger",
    order_detail: "info",
  };
  return typeMap[actionKey] || "default";
};

// 操作按钮图标
const getActionIcon = (actionKey: string) => {
  const iconMap: Record<string, any> = {
    order_sign: DocumentChecked,
    order_check: Search,
    order_price: PriceTag,
    order_payment: ShoppingBag,
    order_complete: SuccessFilled,
    order_cancel: CircleClose,
    order_delete: Close,
    order_detail: View,
    order_push_notify: Bell, // 推送通知图标
  };
  return iconMap[actionKey] || InfoFilled;
};

// 格式化价格
const formatPrice = (price: number | string): string => {
  if (!price) return "¥0.00";
  const num = typeof price === "string" ? parseFloat(price) : price;
  return `¥${num.toFixed(2)}`;
};

// 处理推送通知
const handlePushNotify = async (row: any) => {
  try {
    await ElMessageBox.confirm("确定要推送订单确认通知给用户吗？", "推送通知", {
      confirmButtonText: "确定推送",
      cancelButtonText: "取消",
      type: "info",
    });

    const loading = ElLoading.service({
      lock: true,
      text: "正在发送通知...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    // 调用推送通知API
    await pushOrderNotify(row.id);

    loading.close();
    ElMessage.success("推送通知已发送");
  } catch (error: any) {
    if (error !== "cancel") {
      console.error("推送通知失败：", error);
      ElMessage.error(error.message || "推送通知失败");
    }
  }
};

// 处理订单操作
const handleAction = async (row, action) => {
  try {
    // 根据操作类型执行不同的操作
    if (action.key === "order_cancel") {
      // 取消订单
      try {
        const { value: reason } = await ElMessageBox.prompt(
          "请输入取消原因",
          "取消订单",
          {
            confirmButtonText: "确定",
            cancelButtonText: "取消",
            inputPlaceholder: "请输入取消原因",
            inputValidator: (value) => {
              if (!value || !value.trim()) {
                return "取消原因不能为空";
              }
              return true;
            },
          }
        );

        const cancelReason = reason.trim();

        // 使用action.key作为操作标识
        await updateRecycleOrder(row.id, {
          action: "order_cancel",
          cancel_reason: cancelReason,
          reason: cancelReason,
        });
        ElMessage.success("订单已取消");
        await getList(pagination.value.page); // 刷新列表，保持当前页
      } catch (error) {
        if (error !== "cancel") {
          throw error;
        }
      }
    } else if (action.key === "order_delete") {
      // 删除订单
      await ElMessageBox.confirm("确定要删除该订单吗？", "提示", {
        confirmButtonText: "确定",
        cancelButtonText: "取消",
        type: "warning",
      });
      await deleteRecycleOrder(row.id);
      ElMessage.success("订单已删除");
      await getList(pagination.value.page); // 刷新列表，保持当前页
    } else if (action.key === "order_complete") {
      // 完成订单
      await ElMessageBox.confirm("确定要完成该订单吗？", "提示", {
        confirmButtonText: "确定",
        cancelButtonText: "取消",
        type: "warning",
      });

      // 使用action.key作为操作标识
      await updateRecycleOrder(row.id, { action: "order_complete" });
      ElMessage.success("操作成功，订单已完成");
      await getList(pagination.value.page); // 刷新列表，保持当前页
    } else if (action.key === "order_sign") {
      // 签收订单
      currentOrderId.value = row.id;
      currentDevices.value = row.devices || [];
      orderDialogVisible.value = true;
    } else if (action.key === "order_check") {
      // 质检订单
      currentOrderId.value = row.id;
      currentDevices.value = row.devices || [];
      checkDeviceLogVisible.value = true;
    } else if (action.key === "order_price") {
      // 定价订单
      currentOrderId.value = row.id;
      currentDevices.value = row.devices || [];
      priceDeviceLogVisible.value = true;
    } else if (action.key == "order_payment") {
      // 确认打款 前 将用户的收款方式 弹出来 展示给财务看 , 财务打完款后 点击确定按钮 , 完成打款
      // row.member_id 需携带
      currentOrderId.value = row.id;
      paymentDialogVisible.value = true;

      const res = await getMerchantPayInfo(row.member_id);
      if (res.data && Array.isArray(res.data)) {
        // 获取当前订单的总数据
        const order = list.value.find((item) => item.id === row.id);

        // 计算退回的设备数量
        let returnedDeviceCount = 0;
        if (order && order.devices && Array.isArray(order.devices)) {
          returnedDeviceCount = order.devices.filter(
            (device) => device.status === 6
          ).length;
        }
        // 计算已质检的设备数量
        let checkedDeviceCount = 0;
        if (order && order.devices && Array.isArray(order.devices)) {
          checkedDeviceCount = order.devices.filter(
            (device) => device.status >= 4
          ).length;
        }

        // 计算已确认的设备数量
        let confirmedDeviceCount = 0;
        if (order && order.devices && Array.isArray(order.devices)) {
          confirmedDeviceCount = order.devices.filter(
            (device) => device.status >= 5
          ).length;
        }
        // 计算成功回收的设备数量
        let successDeviceCount = 0;
        if (order && order.devices && Array.isArray(order.devices)) {
          successDeviceCount = order.devices.filter(
            (device) => device.status == 5
          ).length;
        }

        // 计算所有设备 并且同意回收的 最终价格
        let finalPrice = 0;
        if (order && order.devices && Array.isArray(order.devices)) {
          finalPrice = order.devices
            .filter((device) => device.status == 5)
            .reduce((sum, device) => sum + +device.final_price, 0);
        }

        // 准备订单摘要信息
        const orderSummary = {
          order_id: order ? order.id : row.id,
          delivery_type: order ? order.delivery_type_text : "",
          device_count: order && order.devices ? order.devices.length : 0,
          confirmed_count: confirmedDeviceCount,
          checked_count: checkedDeviceCount,
          success_count: successDeviceCount,
          returned_count: returnedDeviceCount,
          price_per_device: order ? order.price_per_device : 0,
          total_amount: order ? finalPrice : 0,
          customer_name: order ? order.member.nickname : "",
          customer_mobile: order ? order.member.mobile : "",
          delivery_type_name: order ? order.delivery_type_name : "",
          // 添加设备详情列表
          devices:
            order && order.devices
              ? order.devices.map((device) => ({
                  id: device.id,
                  model: device.model,
                  imei: device.imei,
                  final_price: device.final_price || 0,
                  status: device.status,
                  status_name: device.status_name || "",
                }))
              : [],
        };

        // 将订单摘要信息添加到每个支付方式中
        const paymentInfoWithOrder = res.data.map((item) => {
          return {
            ...item,
            order_summary: orderSummary,
          };
        });

        paymentInfo.value = paymentInfoWithOrder;

        // 默认选中第一个
        selectedPayTypeIndex.value = paymentInfoWithOrder.length > 0 ? 0 : -1;
      } else {
        paymentInfo.value = [];
      }
    } else if (action.key == "order_detail") {
      // 查看详情
      const order = list.value.find((item) => item.id === row.id);

      if (order) {
        orderDetail.value = order as OrderDetail;
        orderDetailVisible.value = true;
      } else {
        ElMessage.warning("未找到订单信息");
      }
    } else if (action.key == "order_push_notify") {
      // 推送通知 - 提醒用户确认订单
      await handlePushNotify(row);
    } else {
      // 其他操作 - 使用action.key作为操作标识
      await updateRecycleOrder(row.id, { action: action.key });
      ElMessage.success("操作成功");
      await getList(); // 刷新列表
    }
  } catch (error) {
    console.error("操作失败：", error);
    ElMessage.error(error.message || "操作失败");
  }
};

// 获取设备数量
const getDeviceCount = (devices: any[]) => {
  if (!devices) return 0;
  return devices.length;
};

// 快速搜索
const quickSearch = () => {
  // 清空高级搜索条件
  resetAdvancedSearchForm();
  // 重置到第一页
  setPagination({ page: 1 });
  getList(1);
};

// 重置快速搜索
const resetQuickSearch = () => {
  quickSearchForm.keyword = "";
  quickSearchForm.status = "";
  quickSearchForm.delivery_type = "";
  activeTab.value = "";
  // 重置到第一页
  setPagination({ page: 1 });
  getList(1);
};

// 高级搜索
const advancedSearch = () => {
  // 清空快速搜索条件
  resetQuickSearchForm();
  // 清空标签页状态
  activeTab.value = "";
  // 重置到第一页
  setPagination({ page: 1 });
  getList(1);
};

// 重置高级搜索
const resetAdvancedSearch = () => {
  resetAdvancedSearchForm();
  // 清空标签页状态
  activeTab.value = "";
  // 重置到第一页
  setPagination({ page: 1 });
  getList(1);
};

// 重置快速搜索表单
const resetQuickSearchForm = () => {
  quickSearchForm.keyword = "";
  quickSearchForm.status = "";
  quickSearchForm.delivery_type = "";
};

// 重置高级搜索表单
const resetAdvancedSearchForm = () => {
  advancedSearchForm.order_id = "";
  advancedSearchForm.express_no = "";
  advancedSearchForm.status = [];
  advancedSearchForm.member_id = "";
  advancedSearchForm.user_mobile = "";
  advancedSearchForm.delivery_type = [];
  advancedSearchForm.device_imei = "";
  advancedSearchForm.device_model = "";
  advancedSearchForm.device_count_min = null;
  advancedSearchForm.device_count_max = null;
  advancedSearchForm.amount_min = null;
  advancedSearchForm.amount_max = null;
  advancedSearchForm.create_time_range = [];
  advancedSearchForm.update_time_range = [];
  advancedSearchForm.sign_at = [];
  advancedSearchForm.complete_at = [];
  advancedSearchForm.pay_time = [];
};
const handleMemberChange = (memberId: number | null, memberInfo: any) => {
  advancedSearchForm.member_id = memberId;
  // 当用户选择变化时自动搜索
  setPagination({ page: 1 });
  getList(1);
};

// 导出数据
const exportData = () => {
  ElMessage.info("导出功能开发中...");
};

// 获取订单状态
const findOrderStatus = (orderId: number) => {
  const order = list.value.find((item) => item.id === orderId);
  return order ? order.status : 0;
};

// 表单数据
const checkDeviceLogForm = ref({
  id: 0,
  imei: "",
  model: "",
  initial_price: "",
  check_result: "",
  check_images: "",
  remark: "",
  status: 0,
  final_price: "",
  check_status: 0,
});

// 表单验证规则
const checkDeviceLogRules = {
  check_result: [
    { required: true, message: "请输入质检结果", trigger: "blur" },
  ],
};

//checkDevice
const checkDeviceLogVisible = ref(false);
const priceDeviceLogVisible = ref(false);
const priceForm = ref({}); // 添加空的priceForm对象

//editDevice
const checkDevice = async (row: any) => {
  // 查找当前设备所属的订单
  const orderId = row.order_id;
  // 在list中查找对应的订单
  const order = list.value.find((item) => item.id === orderId);

  // 如果订单状态是待签收(1)，则提示用户先签收订单
  if (order && order.status === 1) {
    ElMessage.warning("请先签收订单后再进行质检");
    return;
  }

  checkDeviceLogVisible.value = true;
  const res = await getDevice(row.id);
  if (res.code === 1) {
    checkDeviceLogForm.value = {
      id: res.data.id,
      imei: res.data.imei,
      model: res.data.model,
      initial_price: res.data.initial_price,
      check_result: res.data.check_result || "",
      check_images: res.data.check_images || "",
      final_price: res.data.final_price || "",
      remark: res.data.remark || "",
      status: res.data.status,
      check_status: res.data.check_status,
    };
  }
};

const priceDevice = async (row: any) => {
  try {
    const res = await getDevice(row.id);
    if (res.code === 1) {
      checkDeviceLogForm.value = {
        id: res.data.id,
        imei: res.data.imei,
        model: res.data.model,
        initial_price: res.data.initial_price,
        check_result: res.data.check_result || "",
        check_images: res.data.check_images || "",
        final_price: res.data.final_price || "",
        before_price: res.data.before_price || "",
        remark: res.data.remark || "",
        status: res.data.status,
        check_status: res.data.check_status,
      };
      priceDeviceLogVisible.value = true;
    }
  } catch (error) {
    console.error("获取设备信息失败:", error);
    ElMessage.error("获取设备信息失败");
  }
};

// 提交设备质检
const submitDeviceCheck = async (formData) => {
  try {
    // 验证数据是否完整
    if (!formData.check_result) {
      ElMessage.warning("请填写质检结果");
      return;
    }

    await updateDevice(formData.id, {
      check_result: formData.check_result,
      check_images: formData.check_images,
      remark: formData.remark,
      check_status: 1, // 已质检
      final_price: formData.final_price,
      action: "check", // 添加操作类型，表示这是质检操作
      imei: formData.imei,
      info: formData.info,
      model: formData.model,
    });

    ElMessage.success("质检信息提交成功");
    checkDeviceLogVisible.value = false;
    await getList(pagination.value.page); // 刷新列表，保持当前页
  } catch (error) {
    console.error("提交质检信息失败：", error);
    // 显示后端返回的具体错误信息
    ElMessage.error(error.response?.data?.message || "提交失败，请重试");
  }
};

// 暂存质检信息
const handleCheckDeviceSaveDraft = async (formData) => {
  const loading = ElLoading.service({
    lock: true,
    text: "正在暂存...",
    background: "rgba(0, 0, 0, 0.7)",
  });

  try {
    await updateDevice(formData.id, {
      check_result: formData.check_result,
      check_images: formData.check_images,
      remark: formData.remark,
      final_price: formData.final_price,
      imei: formData.imei,
      info: formData.info,
      model: formData.model,
      action: "save_draft",
    });

    ElMessage.success("质检信息已暂存");
    checkDeviceLogVisible.value = false;
    await getList(pagination.value.page);
  } catch (error) {
    console.error("暂存质检信息失败：", error);
    ElMessage.error(error.response?.data?.message || "暂存失败，请重试");
  } finally {
    loading.close();
  }
};

// 提交设备定价
const submitDevicePrice = async (formData) => {
  try {
    // 验证价格是否有效
    if (!formData || !formData.final_price || formData.final_price <= 0) {
      ElMessage.warning("请输入有效的价格");
      return;
    }

    // 提交数据
    await confirmPrice(formData.id, formData);

    ElMessage.success("定价信息提交成功");
    priceDeviceLogVisible.value = false;
    await getList(pagination.value.page); // 刷新列表，保持当前页
  } catch (error) {
    console.error("提交定价信息失败：", error);
    ElMessage.error("提交失败，请重试");
  }
};

// 设备多选
const selectedDevices = ref({});

// 设备多选变化事件
const handleDeviceSelectionChange = (val, orderId) => {
  selectedDevices.value[orderId] = val;
};

// 单个确认
const batchRecycleDevice = async (deviceId) => {
  if (!deviceId) {
    ElMessage.warning("请选择设备");
  }

  try {
    await apiBatchRecycleDevices({ ids: deviceId + "", remark: "管理员确认" });
    getList(pagination.value.page); // 刷新列表，保持当前页
  } catch (error) {
    ElMessage.error("确认失败");
  }
};
// 单个拒绝
const batchReturnDevice = async (deviceId) => {
  if (!deviceId) {
    ElMessage.warning("请选择设备");
    return;
  }

  try {
    // 合并确认和输入原因到一个弹窗
    const { value: remark } = await ElMessageBox.prompt(
      "确定要拒绝回收该设备吗？将创建退货订单处理该设备。\n请输入拒绝回收原因：",
      "拒绝回收确认",
      {
        confirmButtonText: "确定拒绝",
        cancelButtonText: "取消",
        type: "warning",
        inputPlaceholder: "请输入拒绝回收原因",
      }
    );

    const loading = ElLoading.service({
      lock: true,
      text: "正在处理...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    await apiBatchReturnDevices({
      ids: deviceId + "",
      remark: remark || "商户拒绝回收",
    });
    loading.close();
    ElMessage.success("拒绝成功，已创建退货订单");
    getList(pagination.value.page); // 刷新列表，保持当前页
  } catch (error) {
    if (error === "cancel") return; // 用户取消操作
    console.error("拒绝失败:", error);
    // ElMessage.error('拒绝失败: ' + (error.message || '未知错误'))
  }
};

// 批量确认设备
const batchRecycleDevices = async (orderId) => {
  try {
    const selectedDeviceIds = selectedDevices.value[orderId].map(
      (device) => device.id
    );
    if (selectedDeviceIds.length === 0) {
      ElMessage.warning("请选择设备");
      return;
    }

    // 弹出输入备注的对话框
    const { value: remark } = await ElMessageBox.prompt(
      "请输入操作备注",
      "批量确认设备",
      {
        confirmButtonText: "确认",
        cancelButtonText: "取消",
        inputPlaceholder: "请输入备注信息（可选）",
      }
    );

    await apiBatchRecycleDevices({
      ids: selectedDeviceIds.join(","),
      remark: remark || "",
    });
    ElMessage.success("批量确认成功");
    await getList(pagination.value.page); // 刷新列表，保持当前页
  } catch (error) {
    if (error !== "cancel") {
      console.error("批量确认失败：", error);
      ElMessage.error("批量确认失败");
    }
  }
};

// 批量拒绝设备
const batchReturnDevices = async (orderId) => {
  try {
    const selectedDeviceIds = selectedDevices.value[orderId].map(
      (device) => device.id
    );
    if (selectedDeviceIds.length === 0) {
      ElMessage.warning("请选择设备");
      return;
    }

    // 合并确认和输入原因到一个弹窗
    const { value: remark } = await ElMessageBox.prompt(
      `确定要拒绝回收已选择的 ${selectedDeviceIds.length} 台设备吗？将创建退货订单处理这些设备。\n请输入拒绝回收原因：`,
      "批量拒绝回收确认",
      {
        confirmButtonText: "确定拒绝",
        cancelButtonText: "取消",
        type: "warning",
        inputPlaceholder: "请输入拒绝回收原因",
      }
    );

    const loading = ElLoading.service({
      lock: true,
      text: "正在处理...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    await apiBatchReturnDevices({
      ids: selectedDeviceIds.join(","),
      remark: remark || "商户批量拒绝回收",
    });

    loading.close();
    ElMessage.success("批量拒绝成功，已创建退货订单");
    await getList(); // 刷新列表
  } catch (error) {
    if (error === "cancel") return; // 用户取消操作
    console.error("批量拒绝失败:", error);
    ElMessage.error("批量拒绝失败: " + (error.message || "未知错误"));
  }
};

// 表格展开/折叠事件
const handleExpandChange = (row: any, expandedRows: any[]) => {
  // 我们需要从中提取 ID 数组
  const expandedIds = expandedRows.map((expandedRow) => expandedRow.id);

  // 使用usePageState的方法保存展开状态
  setExpandedRows(expandedIds);
};

const deviceLogVisible = ref(false);
const deviceInfo = ref({});

// viewDetail
const viewDetail = async (row) => {
  // 直接通过 getDevice 获取设备信息
  try {
    const data = await getDevice(row.id);
    if (data.code !== 1) {
      ElMessage.error(data.msg || "获取设备详情失败");
      return;
    }
    deviceDetailData.value = data.data;
    deviceLogVisible.value = true;
  } catch (error) {
    console.error("获取设备详情失败:", error);
    ElMessage.error("获取设备详情失败");
  }
};

// 代下单相关
const addOrderDialogVisible = ref(false);

// 显示代下单弹窗
const showAddOrderDialog = () => {
  addOrderDialogVisible.value = true;
};

// 处理代下单成功
const handleAddOrderSuccess = async () => {
  // 刷新列表
  await getList();
};

// 页面加载
onMounted(async () => {
  await loadStatusList();
  // 使用保存的页码获取数据
  await getList(pagination.value.page);
});

// 计算属性：获取当前选中的收款方式信息
const currentPaymentInfo = computed(() => {
  if (
    paymentInfo.value &&
    paymentInfo.value.length > 0 &&
    selectedPayTypeIndex.value >= 0 &&
    selectedPayTypeIndex.value < paymentInfo.value.length
  ) {
    return paymentInfo.value[selectedPayTypeIndex.value];
  }
  return null;
});

// tab切换
const activeTab = ref("");
const handleTabClick = (tab: any) => {
  // 根据tab的name值进行筛选
  activeTab.value = tab.props.name;
  quickSearchForm.status = tab.props.name;

  // 清空高级搜索中的状态筛选，避免冲突
  advancedSearchForm.status = [];

  // 重新获取列表数据，重置到第一页
  setPagination({ page: 1 });
  getList(1);
};

// 处理支付确认
const handlePaymentConfirm = async (paymentData) => {
  try {
    // 使用传入的 orderId，如果为空则使用 currentOrderId
    const orderId = paymentData.orderId || currentOrderId.value;
    if (!orderId) {
      ElMessage.error("订单ID不能为空");
      return;
    }

    // 调用确认打款API
    await paymentConfirm(orderId, {
      pay_type: paymentData.payType,
      account: paymentData.account,
      payment_images: paymentData.paymentImages,
      remark: "财务已确认打款",
    });

    ElMessage.success("确认打款成功");
    paymentDialogVisible.value = false;

    // 刷新订单列表
    await getList();
  } catch (error) {
    console.error("确认打款失败", error);
    ElMessage.error("确认打款失败");
  }
};

// 设备详情弹窗相关
const deviceDetailData = ref(null);

// 处理设备详情关闭
const handleDeviceDetailClosed = () => {
  deviceDetailData.value = null;
};

// 处理设备确认提交
const handleDeviceConfirm = async (data: {
  orderId: number | string;
  devices: any[];
}) => {
  const loading = ElLoading.service({
    lock: true,
    text: "正在保存...",
    background: "rgba(0, 0, 0, 0.7)",
  });

  try {
    // 提交设备信息
    const result = await updateRecycleOrder(data.orderId, {
      action: "order_sign",
      devices: data.devices.map((device) => ({
        id: device.id,
        imei: device.imei,
        model: device.model,
        initial_price: device.initial_price,
        category_id: device.category_id,
      })),
    });

    if (result.code !== 1) {
      loading.close();
      orderDialogVisible.value = false;
      throw new Error(result.message || "操作失败");
    }

    ElMessage.success("订单签收成功");
    orderDialogVisible.value = false;
    await getList(); // 刷新列表

    loading.close();
  } catch (error: any) {
    // console.error('保存设备信息失败：', error)
    // orderDialogVisible.value = false
    loading.close();
    // ElMessage.error(error.message || '保存失败')
  }
};

// 处理质检图片数组
const checkImagesArray = computed(() => {
  if (!deviceInfo.value?.data?.check_images) return [];
  return deviceInfo.value.data.check_images.split(",").filter((url) => url);
});

// 预览图片
const previewImage = (url) => {
  img(url);
};

// 查看订单详情
const viewOrderDetail = (order: any) => {
  orderDetail.value = order;
  orderDetailVisible.value = true;
};

// 打印设备标签
const printDeviceLabel = async (device: any) => {
  try {
    const loading = ElLoading.service({
      lock: true,
      text: "正在打印标签...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    const res = await _printDeviceLabel(device.id);
    loading.close();

    if (res.code === 1) {
      ElMessage.success("标签打印成功");
      // 检查是否是模拟打印
      if (res.data && res.data.simulated) {
        ElNotification({
          title: "模拟打印",
          message: "已生成打印内容，但未连接实际打印机",
          type: "warning",
          duration: 5000,
        });
        // 弹出打印内容预览窗口
        ElMessageBox.alert(
          `<pre style="white-space: pre-wrap; font-family: monospace; font-size: 12px;">${res.data.content}</pre>`,
          "打印内容预览",
          {
            dangerouslyUseHTMLString: true,
            confirmButtonText: "关闭",
            callback: () => {},
          }
        );
      }
    } else {
      const errorMsg = res.message || "打印失败";
      const debugInfo = res.debug_info ? JSON.stringify(res.debug_info) : "";
      ElMessage.error(`${errorMsg} ${debugInfo}`);
      console.error("打印失败:", res);
    }
  } catch (error: any) {
    console.error("打印过程异常:", error);
    ElMessage.error(`打印过程异常: ${error.message || "未知错误"}`);
  }
};

// 查询快递的物流信息
const queryExpress = async (express_code: string, mobile: string) => {
  try {
    const res = await getExpress(express_code, mobile);
    return res.data;
  } catch (error) {
    console.error("查询快递信息失败:", error);
    throw error;
  }
};

// 处理快递单号悬停
const handleExpressHover = async (row: any) => {
  if (!row.express_no || row.express_no === "暂无") return;

  // 清除之前的计时器
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value);
  }

  // 设置延迟查询
  hoverTimer.value = setTimeout(async () => {
    try {
      // 设置loading状态
      expressLoading.value[row.id] = true;

      // 获取用户手机号后4位
      const mobile = row.member?.mobile || row.recycleUserAddress?.mobile || "";
      const mobileLast4 = mobile.slice(-4);

      if (!mobileLast4) {
        ElMessage.warning("无法获取用户手机号，无法查询快递信息");
        return;
      }

      // 查询快递信息
      const expressData = await queryExpress(row.express_no, mobileLast4);

      // 检查是否有有效的快递数据
      if (
        !expressData ||
        !expressData.data ||
        !expressData.data.logisticsTraceDetailList ||
        expressData.data.logisticsTraceDetailList.length === 0
      ) {
        ElMessage.info("暂无物流信息");
        return;
      }

      // 设置快递信息并显示弹出框
      expressInfo.value = expressData.data;
      currentExpressRow.value = row;
      expressPopoverVisible.value = true;
    } catch (error) {
      console.error("查询快递信息失败:", error);
      ElMessage.error("查询快递信息失败");
    } finally {
      // 清除loading状态
      expressLoading.value[row.id] = false;
    }
  }, 500); // 500ms延迟
};

// 处理鼠标离开
const handleExpressLeave = () => {
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value);
    hoverTimer.value = null;
  }
};

// 获取快递状态类型
const getExpressStatusType = (status: string) => {
  switch (status) {
    case "ACCEPT":
      return "info";
    case "TRANSPORT":
      return "warning";
    case "DELIVER":
      return "primary";
    case "SIGN":
      return "success";
    case "REJECT":
    case "EXCEPTION":
      return "danger";
    default:
      return "info";
  }
};

// 获取状态描述
const getStatusDesc = (status: number) => {
  switch (status) {
    case 1:
      return "待签收";
    case 2:
      return "质检中";
    case 3:
      return "已质检";
    case 4:
      return "待定价";
    case 5:
      return "待用户确认";
    case 6:
      return "待财务打款";
    case 7:
      return "已打款|完成✅";
    case 8:
      return "已取消";
    case 9:
      return "已取消";
    case 10:
      return "已删除";
    default:
      return "未知状态";
  }
};
</script>

<style lang="scss" scoped>
.recycle-order-list {
  .el-card {
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  :deep(.el-card__body) {
    flex: 1;
    overflow: auto;
  }
}

.recycle-order-list {
  .el-card {
    height: 100%;
    display: flex;
    flex-direction: column;
  }

  :deep(.el-card__body) {
    flex: 1;
    overflow: auto;
  }

  // 搜索系统样式已全部使用 Tailwind CSS 替代

  // 标签页样式
  .order-tabs {
    :deep(.el-tabs__header) {
      margin-bottom: 16px;
    }

    :deep(.el-tabs__nav-wrap) {
      &::after {
        background-color: #e4e7ed;
      }
    }

    :deep(.el-badge) {
      .el-badge__content {
        font-size: 11px;
        padding: 0 4px;
        height: 16px;
        line-height: 16px;
        min-width: 16px;
      }
    }
  }

  // 表格样式
  .order-table {
    :deep(.el-table__expanded-cell) {
      padding: 0 !important;
    }

    // 订单信息单元格
    .order-info-cell {
      .info-row {
        display: flex;
        align-items: center;
        margin-bottom: 4px;

        &:last-child {
          margin-bottom: 0;
        }

        .label {
          color: #909399;
          font-size: 12px;
          min-width: 60px;
        }

        .value {
          color: #303133;
          font-size: 13px;
        }
      }
    }

    // 用户信息单元格
    .user-info-cell {
      display: flex;
      align-items: center;

      .user-details {
        .user-name {
          font-weight: 500;
          color: #303133;
          font-size: 14px;
          margin-bottom: 2px;
        }

        .user-phone {
          color: #909399;
          font-size: 12px;
        }
      }
    }

    // 设备数量标签
    .device-count-tag {
      font-weight: 500;
    }
  }

  // 设备展开面板样式
  .device-expansion-panel {
    padding: 20px;
    background-color: #fafbfc;
    border-radius: 6px;

    .panel-header {
      display: flex;
      justify-content: between;
      align-items: center;
      border-bottom: 1px solid #e4e7ed;
      padding-bottom: 12px;
    }

    .device-table {
      :deep(.el-table__header) {
        background-color: #f5f7fa;
      }

      :deep(.el-table) {
        border-radius: 4px;
        overflow: hidden;
      }
    }

    .price-text {
      color: #f56c6c;
      font-weight: 600;
      font-size: 14px;
    }
  }
}

.device-detail {
  padding: 0 10px;

  .detail-section {
    margin-bottom: 20px;
    border-bottom: 1px solid #ebeef5;
    padding-bottom: 15px;

    &:last-child {
      border-bottom: none;
    }

    .section-title {
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 12px;
      color: #303133;
      position: relative;
      padding-left: 10px;

      &::before {
        content: "";
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 16px;
        background-color: #409eff;
        border-radius: 2px;
      }
    }

    .info-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;

      .info-item {
        display: flex;
        align-items: flex-start;

        &.full-width {
          grid-column: span 2;
        }

        .label {
          width: 80px;
          color: #606266;
          font-size: 14px;
        }

        .value {
          flex: 1;
          color: #303133;
          font-size: 14px;
          word-break: break-all;

          &.price {
            color: #f56c6c;
            font-weight: 500;
          }

          &.highlight {
            font-size: 16px;
            font-weight: bold;
          }
        }

        .status-tag {
          padding: 2px 8px;
          border-radius: 4px;
          font-size: 12px;

          &.status-1 {
            background-color: #e6f7ff;
            color: #1890ff;
          }

          &.status-2 {
            background-color: #fff7e6;
            color: #fa8c16;
          }

          &.status-3 {
            background-color: #f6ffed;
            color: #52c41a;
          }

          &.status-4 {
            background-color: #e6fffb;
            color: #13c2c2;
          }

          &.status-5 {
            background-color: #f9f0ff;
            color: #722ed1;
          }

          &.status-6 {
            background-color: #fff1f0;
            color: #f5222d;
          }
        }
      }
    }

    .check-images {
      margin-top: 15px;

      h4 {
        font-size: 14px;
        margin-bottom: 10px;
        font-weight: 500;
      }

      .image-list {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;

        :deep(.el-image) {
          width: 80px;
          height: 80px;
          border-radius: 4px;
          cursor: pointer;
          border: 1px solid #ebeef5;
          transition: all 0.3s;

          &:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 12px 0 rgba(0, 0, 0, 0.1);
          }
        }
      }
    }

    .remark-content {
      padding: 10px;
      background-color: #f5f7fa;
      border-radius: 4px;
      color: #606266;
      min-height: 40px;
    }
  }
}

.empty-data {
  padding: 30px 0;
}

/* 代下单相关样式 */
.user-info {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
}

.user-info div {
  padding: 5px 0;
}

.search-results {
  max-height: 300px;
  overflow-y: auto;
}

.order-info ol {
  padding-left: 20px;
  margin-top: 5px;
  margin-bottom: 0;
}

.order-info p {
  margin-bottom: 5px;
}

/* 搜索系统完全使用 Tailwind CSS 重构，删除旧样式 */
</style>

<style scoped>
.express-no-container {
  cursor: pointer;
  transition: all 0.3s ease;
  border-radius: 4px;
  padding: 2px 4px;
}

.express-no-container:hover {
  background-color: #f5f7fa;
  color: #409eff;
}

.express-no {
  font-family: "Courier New", monospace;
  font-weight: 500;
}

.express-loading {
  color: #409eff;
}

.express-icon {
  color: #909399;
  font-size: 12px;
}

.express-info-container {
  max-height: 500px;
  overflow-y: auto;
}

.express-header {
  border-bottom: 1px solid #ebeef5;
  padding-bottom: 16px;
}

.trace-item .trace-location {
  font-weight: 500;
  color: #303133;
  margin-bottom: 4px;
}

.trace-item .trace-desc {
  color: #606266;
  font-size: 14px;
  line-height: 1.5;
}
</style>
