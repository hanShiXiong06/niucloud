<template>
  <div class="recycle-order-list h-full">
    <el-card class="box-card !border-none h-full relative" shadow="never">
      <div class="order-list-header">
        <div :class="isMobile ? 'order-page-header order-page-header--mobile' : 'order-page-header'">
          <span :class="isMobile ? 'text-lg font-semibold text-gray-800' : 'text-xl font-semibold text-gray-800'">📱 回收订单管理</span>
          <div :class="isMobile ? 'w-full' : 'btn-wrap'">
            <el-button type="primary" :icon="Plus" :class="isMobile ? 'w-full' : ''" @click="showAddOrderDialog">代下单</el-button>
          </div>
        </div>

        <RecycleOrderSearchPanel
          :is-mobile="isMobile"
          :mobile-search-visible="mobileSearchVisible"
          :advanced-search-form="advancedSearchForm"
          :order-status-map="orderStatusMap"
          @toggle-mobile-search="mobileSearchVisible = !mobileSearchVisible"
          @advanced-search="advancedSearch"
          @reset-search="resetAdvancedSearch"
          @member-change="handleMemberChange"
        />

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
      </div>

      <!-- 列表 -->
      <div :class="isMobile ? 'order-table-region order-table-region--mobile' : 'order-table-region'">
        <RecycleOrderDesktopTable
          v-if="!isMobile"
          :loading="loading"
          :list="list"
          :expand-row-keys="expandRowKeys"
          :order-status-map="orderStatusMap"
          :selected-devices="selectedDevices"
          :express-loading="expressLoading"
          :format-price="formatPrice"
          :format-date-time="formatDateTime"
          :get-device-count="getDeviceCount"
          :get-status-type="getStatusType"
          :get-status-effect="getStatusEffect"
          :get-status-icon="getStatusIcon"
          :get-device-status-type="getDeviceStatusType"
          :get-action-button-type="getActionButtonType"
          :get-action-icon="getActionIcon"
          :find-order-status="findOrderStatus"
          :img="img"
          :handle-expand-change="handleExpandChange"
          :handle-device-selection-change="handleDeviceSelectionChange"
          :check-device="checkDevice"
          :price-device="priceDevice"
          :batch-recycle-device="batchRecycleDevice"
          :batch-return-device="batchReturnDevice"
          :batch-recycle-devices="batchRecycleDevices"
          :print-device-label="printDeviceLabel"
          :view-detail="viewDetail"
          :handle-action="handleAction"
          :handle-express-hover="handleExpressHover"
          :handle-express-leave="handleExpressLeave"
          :share-order="shareOrder"
          @refresh="getList"

        />

        <RecycleOrderMobileCards
          v-else
          :loading="loading"
          :list="list"
          :order-status-map="orderStatusMap"
          :selected-devices="selectedDevices"
          :express-loading="expressLoading"
          :format-price="formatPrice"
          :format-date-time="formatDateTime"
          :get-device-count="getDeviceCount"
          :get-status-type="getStatusType"
          :get-status-effect="getStatusEffect"
          :get-device-status-type="getDeviceStatusType"
          :get-action-button-type="getActionButtonType"
          :get-action-icon="getActionIcon"
          :find-order-status="findOrderStatus"
          :img="img"
          :is-mobile-order-expanded="isMobileOrderExpanded"
          :toggle-mobile-order-expand="toggleMobileOrderExpand"
          :is-mobile-device-selected="isMobileDeviceSelected"
          :handle-mobile-device-selection="handleMobileDeviceSelection"
          :check-device="checkDevice"
          :price-device="priceDevice"
          :batch-recycle-device="batchRecycleDevice"
          :batch-return-device="batchReturnDevice"
          :batch-recycle-devices="batchRecycleDevices"
          :print-device-label="printDeviceLabel"
          :view-detail="viewDetail"
          :handle-action="handleAction"
          :handle-express-hover="handleExpressHover"
          :handle-express-leave="handleExpressLeave"
          :share-order="shareOrder"
        />
      </div>

      <!-- 分页 -->
      <div :class="isMobile ? 'order-pagination order-pagination--mobile' : 'order-pagination'">
        <div class="text-sm text-gray-500">
          <template v-if="isMobile">
            共 {{ pagination.total }} 条记录
          </template>
          <template v-else>
            共 {{ pagination.total }} 条记录，当前第 {{ pagination.page }} 页
          </template>
        </div>

        <el-pagination
          v-model:current-page="pagination.page"
          :page-sizes="[15, 30, 50, 100]"
          :page-size="pagination.limit"
          :total="pagination.total"
          :hide-on-single-page="false"
          :small="isMobile"
          :layout="isMobile ? 'prev, pager, next' : 'sizes, prev, pager, next, jumper'"
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
      @view-device="viewDetail"
      @query-express="handleExpressQuery"
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
      :width="isMobile ? '95vw' : '760px'"
      top="4vh"
      :destroy-on-close="true"
      class="express-dialog"
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
import { ref, onMounted, onBeforeUnmount, computed } from "vue";
import {
  ElMessage,
  ElMessageBox,
  ElLoading,
  ElNotification,
} from "element-plus";
import {
  Plus,
  Document,
} from "@element-plus/icons-vue";
import { usePageState } from "../../hooks/usePageState";
import { useRecycleOrderQuery } from "../../hooks/useRecycleOrderQuery";
import { useRecycleOrderActions } from "../../hooks/useRecycleOrderActions";
import { useRecycleDeviceActions } from "../../hooks/useRecycleDeviceActions";
import { useRecycleOrderUi } from "../../hooks/useRecycleOrderUi";

import {
  getRecycleOrderList,
  getRecycleOrderStatusList,
  updateRecycleOrder,
  getDevice,
  paymentConfirm,
} from "@/addon/recycle/api/recycle_order";
import { getExpress } from "@/addon/recycle/api/device_query_api";
import { generateOrderShortLink } from "@/addon/recycle/api/shortlink";
import { useClipboard } from "@vueuse/core";

// 导入打印API
import {
  getDeviceLabelPrintPlan,
  printDeviceLabel as submitDeviceLabelPrint,
} from "@/addon/recycle/api/printer";

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
import RecycleOrderSearchPanel from "./components/RecycleOrderSearchPanel.vue";
import RecycleOrderDesktopTable from "./components/RecycleOrderDesktopTable.vue";
import RecycleOrderMobileCards from "./components/RecycleOrderMobileCards.vue";

// 引入图片预览工具
import { img } from "@/utils/common";

// 状态定义
interface OrderActionItem {
  key: string;
  value: string;
}

interface OrderStatus {
  name: string;
  status: number;
  action: OrderActionItem[];
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

// 分页
const {
  expandedRows,
  pagination,
  setExpandedRows,
  setPagination,
} = usePageState("recycle_order_list");

const {
  loading,
  list,
  advancedSearchForm,
  activeTab,
  getList,
  handleSizeChange,
  handleCurrentChange,
  getStatusCount,
  advancedSearch,
  resetAdvancedSearch,
  handleMemberChange,
  handleTabClick,
} = useRecycleOrderQuery<OrderItem>({
  pagination,
  setPagination,
  fetchList: getRecycleOrderList,
  onError: (message: string) => ElMessage.error(message),
});

const {
  getStatusType,
  getStatusEffect,
  getDeviceStatusType,
  getStatusIcon,
  getStatusBadgeType,
  getActionButtonType,
  getActionIcon,
  formatDateTime,
  formatPrice,
  getDeviceCount,
  getExpressStatusType,
} = useRecycleOrderUi();

// 计算属性：确保展开行键是正确的格式
const expandRowKeys = computed(() => {
  return expandedRows.value || [];
});

const orderDialogVisible = ref(false);
const paymentDialogVisible = ref(false);
const currentOrderId = ref<number | string>(0);
const currentDevices = ref<any[]>([]);
const orderDetailVisible = ref(false);
const orderDetail = ref<OrderDetail | null>(null);
const paymentInfo = ref<any[]>([]);
const selectedPayTypeIndex = ref(0);
const checkDeviceLogVisible = ref(false);
const priceDeviceLogVisible = ref(false);
const isMobile = ref(false);
const mobileSearchVisible = ref(false);
const mobileExpandedOrders = ref<Array<number | string>>([]);

const { handleAction } = useRecycleOrderActions({
  list,
  pagination,
  getList,
  currentOrderId,
  currentDevices,
  orderDialogVisible,
  checkDeviceLogVisible,
  priceDeviceLogVisible,
  paymentDialogVisible,
  paymentInfo,
  selectedPayTypeIndex,
  orderDetailVisible,
  orderDetail,
});

const {
  checkDeviceLogForm,
  selectedDevices,
  checkDevice,
  priceDevice,
  submitDeviceCheck,
  handleCheckDeviceSaveDraft,
  submitDevicePrice,
  handleDeviceSelectionChange,
  batchRecycleDevice,
  batchReturnDevice,
  batchRecycleDevices,
} = useRecycleDeviceActions({
  list,
  pagination,
  getList,
  checkDeviceLogVisible,
  priceDeviceLogVisible,
});

// 快递信息相关
const expressLoading = ref<Record<string, boolean>>({});
const expressPopoverVisible = ref(false);
const expressInfo = ref<any>(null);
const hoverTimer = ref<any>(null);

// 取消设备编辑
const cancelDeviceEdit = () => {
  orderDialogVisible.value = false;
  // 清空当前设备列表
  currentDevices.value = [];
  currentOrderId.value = 0;
};

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

const updateResponsiveState = () => {
  isMobile.value = window.innerWidth <= 768;
  if (!isMobile.value) {
    mobileSearchVisible.value = false;
    mobileExpandedOrders.value = [];
  }
};

// 获取订单状态
const findOrderStatus = (orderId: number) => {
  const order = list.value.find((item) => item.id === orderId);
  return order ? order.status : 0;
};

// 表格展开/折叠事件
const handleExpandChange = (row: any, expandedRows: any[]) => {
  // 我们需要从中提取 ID 数组
  const expandedIds = expandedRows.map((expandedRow) => expandedRow.id);

  // 使用usePageState的方法保存展开状态
  setExpandedRows(expandedIds);
};

const toggleMobileOrderExpand = (orderId: number | string) => {
  const index = mobileExpandedOrders.value.findIndex((id) => id === orderId);
  if (index > -1) {
    mobileExpandedOrders.value.splice(index, 1);
  } else {
    mobileExpandedOrders.value.push(orderId);
  }
};

const isMobileOrderExpanded = (orderId: number | string) => {
  return mobileExpandedOrders.value.includes(orderId);
};

const isMobileDeviceSelected = (
  orderId: number | string,
  deviceId: number | string
) => {
  const selectedList = selectedDevices.value[orderId] || [];
  return selectedList.some((item: any) => item.id === deviceId);
};

const handleMobileDeviceSelection = (
  orderId: number | string,
  device: any,
  checked: string | number | boolean
) => {
  const selectedList = selectedDevices.value[orderId]
    ? [...selectedDevices.value[orderId]]
    : [];
  const index = selectedList.findIndex((item: any) => item.id === device.id);
  const isChecked = !!checked;

  if (isChecked && index === -1) {
    selectedList.push(device);
  }

  if (!isChecked && index > -1) {
    selectedList.splice(index, 1);
  }

  handleDeviceSelectionChange(selectedList, orderId);
};

const deviceLogVisible = ref(false);

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

// 处理设备深链接（扫码跳转 ?recycle_device=ID）
const handleDeviceDeepLink = async () => {
  // 直接从 window.location 读取，避免 Vue Router 守卫重定向导致 query 丢失
  const urlParams = new URLSearchParams(window.location.search);
  const deviceId = urlParams.get("id");
  if (!deviceId) return;

  // 清除 URL 参数（避免刷新重复触发）
  urlParams.delete("id");
  const newSearch = urlParams.toString();
  const newUrl = window.location.pathname + (newSearch ? "?" + newSearch : "");
  window.history.replaceState({}, "", newUrl);

  try {
    const res = await getDevice(Number(deviceId));
    if (res.code !== 1 || !res.data) {
      ElMessage.error("设备不存在或已删除");
      return;
    }

    const device = res.data;
    const status = device.status;

    if (status === 1 || status === 2) {
      // 待质检 / 质检中 → 打开质检弹窗
      checkDevice(device);
    } else if (status === 3 || status === 4) {
      // 已质检 / 待确认 → 打开定价弹窗
      priceDevice(device);
    } else {
      // 已回收 / 已退回 / 其他 → 打开详情弹窗
      viewDetail(device);
    }
  } catch (error) {
    console.error("设备深链接处理失败:", error);
    ElMessage.error("获取设备信息失败");
  }
};

// 页面加载
onMounted(async () => {
  updateResponsiveState();
  window.addEventListener("resize", updateResponsiveState);
  await loadStatusList();
  // 使用保存的页码获取数据
  await getList(pagination.value.page);
  // 处理设备深链接（扫码跳转）
  await handleDeviceDeepLink();
});

onBeforeUnmount(() => {
  window.removeEventListener("resize", updateResponsiveState);
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value);
    hoverTimer.value = null;
  }
});

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
  let loading: ReturnType<typeof ElLoading.service> | null = null;

  try {
    loading = ElLoading.service({
      lock: true,
      text: "正在保存...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    // 提交设备信息
    const result = await updateRecycleOrder(data.orderId, {
      action: "order_sign",
      devices: data.devices.map((device) => {
        const { editing, _originalData, isNew, ...rest } = device
        return rest
      }),
    });

    if (result.code !== 1) {
      orderDialogVisible.value = false;
      throw new Error(result.message || "操作失败");
    }

    ElMessage.success("订单签收成功");
    orderDialogVisible.value = false;
    await getList(); // 刷新列表
  } catch (error: any) {
    console.error("保存设备信息失败：", error);
    ElMessage.error(error.message || "保存失败");
  } finally {
    loading?.close();
  }
};

// 打印设备标签
const printDeviceLabel = async (device: any) => {
  let loading: ReturnType<typeof ElLoading.service> | null = null;
  try {
    const planRes = await getDeviceLabelPrintPlan(device.id);
    if (planRes.code !== 1 || !planRes.data?.can_print) {
      throw new Error(planRes.msg || planRes.data?.message || "打印计划不可用");
    }

    const plan = planRes.data;
    const escapeHtml = (value: any) =>
      String(value).replace(/[&<>"']/g, (char) => {
        const map: Record<string, string> = {
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        };
        return map[char] || char;
      });
    const safeText = (value: any, fallback = "未填写") =>
      escapeHtml(value === undefined || value === null || value === "" ? fallback : value);
    const confirmHtml = `
      <div class="device-print-plan">
        <div class="device-print-plan__title">请确认本次打印内容</div>
        <div class="device-print-plan__grid">
          <span>打印场景</span><strong>${safeText(plan.scene?.scene_name)}</strong>
          <span>设备型号</span><strong>${safeText(plan.device?.model)}</strong>
          <span>设备串号</span><strong>${safeText(plan.device?.imei || plan.device?.sn)}</strong>
          <span>订单编号</span><strong>${safeText(plan.device?.order_no)}</strong>
          <span>打印模板</span><strong>${safeText(plan.template?.template_name)}</strong>
          <span>目标打印机</span><strong>${safeText(plan.printer?.printer_name)}</strong>
          <span>打印份数</span><strong>${safeText(plan.copies, "1")} 份</strong>
        </div>
        <div class="device-print-plan__hint">确认后会立即发送到打印机。若模板或打印机不对，请先到打印模板中调整绑定关系。</div>
      </div>
    `;

    await ElMessageBox.confirm(confirmHtml, "打印设备标签", {
      dangerouslyUseHTMLString: true,
      confirmButtonText: "确认打印",
      cancelButtonText: "取消",
    });

    loading = ElLoading.service({
      lock: true,
      text: "正在发送打印任务...",
      background: "rgba(0, 0, 0, 0.7)",
    });

    const res = await submitDeviceLabelPrint(device.id);

    if (res.code === 1) {
      // 检查是否是模拟打印
      if (res.data && res.data.simulated) {
        const printPreviewContent =
          typeof res.data.content === "string"
            ? res.data.content
            : JSON.stringify(res.data.content || {}, null, 2);

        ElNotification({
          title: "模拟打印",
          message: "已生成打印内容，但未连接实际打印机",
          type: "warning",
          duration: 5000,
        });
        // 弹出打印内容预览窗口（纯文本，避免 HTML 注入）
        ElMessageBox.alert(
          printPreviewContent || "暂无打印内容",
          "打印内容预览",
          {
            confirmButtonText: "关闭",
            callback: () => {},
          }
        );
      }
    } else {
      console.error("打印失败:", res);
    }
  } catch (error: any) {
    if (error === "cancel" || error === "close") return;
    console.error("打印过程异常:", error);
  } finally {
    loading?.close();
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
const handleExpressHover = async (row: any, delay = 500) => {
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
      const mobile =
        row.member?.mobile ||
        row.recycleUserAddress?.mobile ||
        row.member_mobile ||
        row.mobile ||
        "";
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
      expressPopoverVisible.value = true;
    } catch (error) {
      console.error("查询快递信息失败:", error);
      ElMessage.error("查询快递信息失败");
    } finally {
      // 清除loading状态
      expressLoading.value[row.id] = false;
    }
  }, delay);
};

const handleExpressQuery = (row: any) => {
  handleExpressHover(row, 0);
};

// 处理鼠标离开
const handleExpressLeave = () => {
  if (hoverTimer.value) {
    clearTimeout(hoverTimer.value);
    hoverTimer.value = null;
  }
};

// 剪贴板
const { copy, isSupported: clipboardSupported } = useClipboard();

// 分享订单 - 生成小程序短链接
const shareOrder = async (row: any) => {
  const loading = ElLoading.service({
    lock: true,
    text: "正在生成分享链接...",
    background: "rgba(0, 0, 0, 0.7)",
  });

  try {
    const res = await generateOrderShortLink({
      order_id: row.id,
      order_no: row.order_no || "",
    });

    loading.close();

    if (res.code !== 1) {
      ElMessage.error(res.msg || "生成分享链接失败");
      return;
    }

    const shortLink = res.data?.short_link;
    if (!shortLink) {
      ElMessage.error("生成分享链接失败，返回数据为空");
      return;
    }

    // 格式化分享文本
    const userName = row.recycleUserAddress?.name || row.member?.nickname || "客户";
    const shareText = `${userName}的回收订单 ${row.order_no} ${shortLink}`;

    if (clipboardSupported.value) {
      await copy(shareText);
      ElMessage.success("分享链接已复制到剪贴板，可直接发送给客户");
    } else {
      ElMessageBox.alert(shareText, "分享链接（请手动复制）", {
        confirmButtonText: "关闭",
        type: "success",
      });
    }
  } catch (error: any) {
    loading.close();
    ElMessage.error("生成分享链接失败：" + (error.message || "未知错误"));
  }
};
</script>

<style lang="scss" scoped>
.recycle-order-list {
  height: calc(100vh - 90px);
  min-height: 0;
  max-height: calc(100vh - 90px);
  overflow: hidden;

  .el-card {
    height: 100%;
    min-height: 0;
    display: flex;
    flex-direction: column;
  }

  :deep(.el-card__body) {
    flex: 1;
    min-height: 0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 16px;
  }

  .order-list-header {
    flex: 0 0 auto;
    min-width: 0;
  }

  .order-page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 12px;
  }

  .order-page-header--mobile {
    align-items: stretch;
    flex-direction: column;
  }

  .order-table-region {
    flex: 1 1 auto;
    min-height: 0;
    height: 0;
    overflow: hidden;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    background: #fff;
  }

  .order-table-region--mobile {
    height: auto;
    overflow-y: auto;
    border: 0;
    background: transparent;
  }

  .order-pagination {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    min-height: 52px;
    padding-top: 10px;
    margin-top: 0;
    background: #fff;
  }

  .order-pagination--mobile {
    align-items: flex-start;
    flex-direction: column;
    gap: 8px;
    padding-top: 12px;
  }

  // 标签页样式
  .order-tabs {
    margin-bottom: 10px;

    :deep(.el-tabs__header) {
      margin-bottom: 0;
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

}

@media (max-width: 768px) {
  .recycle-order-list {
    max-height: none;
    overflow: visible;

    :deep(.el-card__body) {
      overflow: visible;
      padding: 12px;
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

@media (max-width: 768px) {
  .recycle-order-list {
    .order-tabs {
      :deep(.el-tabs__nav-wrap) {
        overflow-x: auto;
      }

      :deep(.el-tabs__nav-scroll) {
        overflow-x: auto;
      }

      :deep(.el-tabs__item) {
        padding: 0 10px;
      }
    }
  }
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
  max-height: min(68vh, 620px);
  overflow-y: auto;
  padding-right: 6px;
}

.express-header {
  border-bottom: 1px solid #ebeef5;
  padding-bottom: 16px;
  position: sticky;
  top: 0;
  z-index: 1;
  background: #fff;
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

:global(.device-print-confirm-box) {
  width: min(560px, calc(100vw - 32px));
}

:global(.device-print-confirm-box .el-message-box__message) {
  width: 100%;
}

:global(.device-print-plan) {
  padding-top: 2px;
}

:global(.device-print-plan__title) {
  margin-bottom: 12px;
  color: #303133;
  font-size: 15px;
  font-weight: 600;
}

:global(.device-print-plan__grid) {
  display: grid;
  grid-template-columns: 88px minmax(0, 1fr);
  gap: 8px 12px;
  padding: 12px;
  border: 1px solid #ebeef5;
  border-radius: 6px;
  background: #fafafa;
}

:global(.device-print-plan__grid span) {
  color: #909399;
}

:global(.device-print-plan__grid strong) {
  min-width: 0;
  color: #303133;
  font-weight: 600;
  word-break: break-all;
}

:global(.device-print-plan__hint) {
  margin-top: 12px;
  padding: 10px 12px;
  border-radius: 6px;
  background: #ecf5ff;
  color: #337ecc;
  line-height: 1.6;
}
</style>
