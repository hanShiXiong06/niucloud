<template>
  <div v-loading="props.loading" class="order-cards">
    <el-empty v-if="!props.list.length" description="暂无订单数据" />

    <article v-for="row in props.list" :key="row.id" class="order-card" :aria-label="`回收订单 ${row.order_no || ''}`">
      <header class="order-card__heading">
        <span class="order-card__number">{{ row.order_no || '订单号待补充' }}</span>
        <el-tag :type="props.getStatusType(row.status)" :effect="props.getStatusEffect(row.status)" size="small">{{ row.status_name }}</el-tag>
      </header>

      <div class="order-card__customer">
        <el-avatar
          :size="32"
          :src="row.member?.headimg ? props.img(row.member.headimg) : ''"
          :class="{ 'order-card__avatar--clickable': row.member?.member_id }"
          @click="row.member?.member_id && props.openMemberDetail(row.member)"
        ><el-icon><User /></el-icon></el-avatar>
        <div class="order-card__contact">
          <strong>{{ row.recycleUserAddress?.name || row.member?.nickname || '未知用户' }}</strong>
          <span>{{ row.member?.mobile || row.recycleUserAddress?.mobile || '暂无联系方式' }}</span>
        </div>
        <el-tag class="order-card__delivery" size="small" :type="deliveryTagType(row.delivery_type)">{{ deliveryLabel(row) }}</el-tag>
      </div>

      <div class="order-card__progress">
        <span :class="{ 'order-card__count--mismatch': !isDeviceCountMatched(row) }">已录 {{ getSignedDeviceCount(row) }} / 提交 {{ getSubmittedDeviceCount(row) }} 台</span>
        <template v-if="row.flow_summary?.progress?.length">
          <el-tag
            v-for="item in row.flow_summary.progress.filter(p => p.value > 0 && p.key !== 'total')"
            :key="item.key"
            size="small"
            :type="['info', 'warning', 'primary', 'success', 'danger'].includes(item.color) ? item.color : 'info'"
            :effect="item.key === 'paid' ? 'dark' : 'plain'"
          >{{ item.label }} {{ item.value }}</el-tag>
        </template>
      </div>

      <HsxFold title="订单信息" :summary="props.formatDateTime(row.create_at)" class="order-card__metadata">
        <dl class="order-card__facts">
          <div><dt>下单来源</dt><dd>{{ row.order_source === 'agent' ? '代下单' : '客户下单' }}{{ row.order_source === 'agent' && row.agent_name ? ' · ' + row.agent_name : '' }}</dd></div>
          <div><dt>流转方式</dt><dd>{{ row.flow_mode_name || '整单流转' }}</dd></div>
          <div><dt>创建时间</dt><dd>{{ props.formatDateTime(row.create_at) }}</dd></div>
          <div v-if="row.sign_at"><dt>签收时间</dt><dd>{{ props.formatDateTime(row.sign_at) }}</dd></div>
          <div v-if="row.complete_at"><dt>完成时间</dt><dd>{{ props.formatDateTime(row.complete_at) }}</dd></div>
          <div v-if="row.pay_time"><dt>打款时间</dt><dd>{{ props.formatDateTime(row.pay_time) }}</dd></div>
          <div v-if="String(row.delivery_type) === '1'">
            <dt>快递单号</dt>
            <dd><el-button link type="primary" :loading="props.expressLoading[row.id]" @click="props.handleExpressHover(row)" @mouseleave="props.handleExpressLeave">{{ row.express_no || '暂无' }}</el-button></dd>
          </div>
          <template v-if="String(row.delivery_type) === '3'">
            <div><dt>物流车辆</dt><dd>{{ [row.logistics_name, row.logistics_vehicle_no].filter(Boolean).join(' · ') || '待补充' }}</dd></div>
            <div><dt>取货地点</dt><dd>{{ row.logistics_pickup_address || '待补充' }}</dd></div>
            <div><dt>物流联系</dt><dd>{{ [row.logistics_contact_name, row.logistics_contact_mobile].filter(Boolean).join(' · ') || '待补充' }}</dd></div>
            <div v-if="row.logistics_eta_at"><dt>预计到达</dt><dd>{{ props.formatDateTime(row.logistics_eta_at) }}</dd></div>
          </template>
        </dl>
      </HsxFold>

      <div class="order-card__toolbar">
        <el-button
          class="order-card__expand"
          text type="primary"
          :icon="props.isMobileOrderExpanded(row.id) ? ArrowUp : ArrowDown"
          :aria-expanded="props.isMobileOrderExpanded(row.id)"
          :aria-controls="`order-devices-${row.id}`"
          @click="props.toggleMobileOrderExpand(row.id)"
        >{{ props.isMobileOrderExpanded(row.id) ? '收起设备' : '展开设备' }}（{{ row.devices?.length || 0 }}）</el-button>
        <el-dropdown trigger="click">
          <el-button :icon="MoreFilled">更多操作</el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item v-for="action in getOrderActions(row)" :key="action.key" @click="action.handler()">{{ action.label }}</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </div>

      <el-collapse-transition>
        <div v-show="props.isMobileOrderExpanded(row.id)" :id="`order-devices-${row.id}`" class="order-card__devices">
          <RecycleOrderDeviceList
            :order="row"
            :selected-devices="props.selectedDevices[row.id] || []"
            :format-price="props.formatPrice"
            :get-device-primary-action="getDevicePrimaryAction"
            :get-device-more-actions="getDeviceMoreActions"
            :view-consignment="props.viewConsignment"
            :view-detail="props.viewDetail"
            @selection-change="devices => props.handleDeviceSelectionChange(devices, row.id)"
            @batch-confirm="props.batchRecycleDevices(row.id)"
          />
        </div>
      </el-collapse-transition>
    </article>
  </div>
</template>

<script setup lang="ts">
import {
  ArrowDown,
  ArrowUp,
  User,
  MoreFilled,
} from "@element-plus/icons-vue";
import { HsxFold } from '@/addon/hsx_components/core';
import RecycleOrderDeviceList from './RecycleOrderDeviceList.vue';
import { useDeviceRowActions } from "@/addon/hsx_recycle/hooks/useDeviceRowActions";
import useUserStore from "@/stores/modules/user";

// 高危订单动作 → 权限点（无权限不显示）
const userStore = useUserStore();
const ACTION_PERM: Record<string, string> = {
  order_payment: "recycle_order_payment_confirm",
  order_payment_confirm: "recycle_order_payment_confirm",
  order_delete: "recycle_order_delete",
};
const hasActionPerm = (key: string) => {
  const perm = ACTION_PERM[key];
  return !perm || (userStore.rules || []).includes(perm);
};
const deliveryLabel = (row: any) => row.delivery_type_name || ({ '1': '快递到店', '2': '客户自送', '3': '物流车配送' }[String(row.delivery_type)] || '未知');
const deliveryTagType = (value: any) => String(value) === '3' ? 'primary' : (String(value) === '1' ? 'warning' : 'success');

interface Props {
  loading: boolean;
  list: any[];
  orderStatusMap: Record<string, any>;
  selectedDevices: Record<string | number, any[]>;
  handleDeviceSelectionChange: (devices: any[], orderId: number | string) => void;
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
  openMemberDetail: (member: any) => void;
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

const getOrderActions = (row: any) => {
  const actions = [...(props.orderStatusMap[row.status]?.action || [])];
  if (row.available_actions?.can_pay_devices && !actions.some(action => action.key === 'order_payment')) {
    actions.push({ key: 'order_payment', value: '去打款' });
  }
  if (row.available_actions?.can_push_confirm_notice && !actions.some(action => action.key === 'order_push_notify')) {
    actions.push({ key: 'order_push_notify', value: '推送通知' });
  }
  return [
    ...actions.filter(action => hasActionPerm(action.key)).map(action => ({ key: action.key, label: action.value, handler: () => props.handleAction(row, action) })),
    { key: 'share_order', label: '分享订单', handler: () => props.shareOrder(row) },
    { key: 'notice_logs', label: '通知记录', handler: () => props.viewNoticeLogs(row) },
  ];
};
</script>

<style scoped>
.order-cards { display: grid; gap: 14px; min-width: 0; }
.order-card { min-width: 0; padding: 16px; border: 1px solid var(--hsx-border-color); border-radius: var(--hsx-radius-md); background: var(--hsx-bg-surface); }
.order-card__heading { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; }
.order-card__number { min-width: 0; font-size: 12px; line-height: 22px; font-variant-numeric: tabular-nums; color: var(--hsx-text-secondary); overflow-wrap: anywhere; }
.order-card__heading :deep(.el-tag) { flex: none; }
.order-card__customer { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; margin-top: 12px; }
.order-card__avatar--clickable { cursor: pointer; }
.order-card__contact { display: flex; align-items: baseline; flex-wrap: wrap; gap: 4px 12px; flex: 1; min-width: 0; overflow-wrap: anywhere; }
.order-card__contact strong { font-size: 15px; color: var(--hsx-text-primary); }
.order-card__contact span { font-size: 12px; color: var(--hsx-text-secondary); }
.order-card__delivery { margin-left: auto; }
.order-card__progress { display: flex; align-items: center; flex-wrap: wrap; gap: 6px 10px; margin: 12px 0; font-size: 12px; color: var(--hsx-text-regular); }
.order-card__count--mismatch { color: var(--el-color-danger); }
.order-card__metadata { margin-bottom: 10px; }
.order-card__facts { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 240px), 1fr)); gap: 10px 16px; margin: 0; }
.order-card__facts > div { display: flex; align-items: baseline; min-width: 0; gap: 10px; font-size: 12px; line-height: 20px; }
.order-card__facts dt { flex: 0 0 54px; color: var(--hsx-text-secondary); }
.order-card__facts dd { margin: 0; min-width: 0; color: var(--hsx-text-regular); overflow-wrap: anywhere; }
.order-card__toolbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; }
.order-card__toolbar :deep(.el-button) { min-height: 40px; margin-left: 0; }
.order-card__expand { padding-left: 0; }
.order-card__devices { margin: 10px -4px -4px; }
@media (max-width: 480px) {
  .order-card { padding: 12px; }
  .order-card__contact { flex-direction: column; }
  .order-card__metadata :deep(.hsx-fold__summary) { font-size: 11px; }
}
</style>
