<template>
  <el-dialog
    v-model="dialogVisible"
    title="订单详情"
    :width="isMobile ? '96vw' : 'min(1180px, calc(100vw - 48px))'"
    :top="isMobile ? '2vh' : '3vh'"
    class="diy-dialog-wrap order-detail-dialog"
    :destroy-on-close="true"
  >
    <div v-if="orderData" class="odd-wrap">
      <div class="odd-summary">
        <div class="odd-summary__main">
          <div class="odd-summary__label">订单编号</div>
          <div class="odd-summary__no">{{ orderData.order_no || orderData.id || '暂无' }}</div>
        </div>
        <div class="odd-summary__metrics">
          <div class="odd-metric">
            <span>状态</span>
            <el-tag :type="orderData.status === 7 ? 'success' : 'info'" size="small">
              {{ orderData.status_name || '暂无' }}
            </el-tag>
          </div>
          <div class="odd-metric">
            <span>设备</span>
            <strong>{{ deviceCount }} 台</strong>
          </div>
          <div class="odd-metric">
            <span>总金额</span>
            <strong class="odd-price">¥{{ totalAmount }}</strong>
          </div>
          <div class="odd-metric">
            <span>创建时间</span>
            <strong>{{ orderData.create_at || '暂无' }}</strong>
          </div>
        </div>
      </div>

      <div class="odd-info-layout">
        <div class="odd-section" v-if="orderData.member">
          <div class="odd-section-header">
            <span>会员信息</span>
          </div>
          <div class="odd-desc-grid">
            <div class="odd-desc-item">
              <span class="odd-desc-label">会员ID</span>
              <span class="odd-desc-value">{{ orderData.member.member_id }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">用户名</span>
              <span class="odd-desc-value">{{ orderData.member.username || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">昵称</span>
              <span class="odd-desc-value">{{ orderData.member.nickname || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">手机号</span>
              <span class="odd-desc-value odd-desc-value--mono">{{ orderData.member.mobile || '暂无' }}</span>
            </div>
          </div>
        </div>

        <div class="odd-section">
          <div class="odd-section-header">
            <span>订单信息</span>
          </div>
          <div class="odd-desc-grid">
            <div class="odd-desc-item">
              <span class="odd-desc-label">配送方式</span>
              <span class="odd-desc-value">{{ orderData.delivery_type_name || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">快递公司</span>
              <span class="odd-desc-value">{{ orderData.express_company || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">快递单号</span>
              <span class="odd-desc-value odd-inline-action">
                <span class="odd-desc-value--mono">{{ orderData.express_no || '暂无' }}</span>
                <el-button
                  v-if="orderData.express_no"
                  link
                  type="primary"
                  size="small"
                  @click="queryOrderExpress"
                >
                  查物流
                </el-button>
              </span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">打款方式</span>
              <span class="odd-desc-value">{{ orderData.pay_type || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">打款时间</span>
              <span class="odd-desc-value">{{ formatTime(orderData.pay_time) }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">收款账号</span>
              <span class="odd-desc-value odd-desc-value--mono">{{ orderData.pay_account || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">备注</span>
              <span class="odd-desc-value">{{ orderData.remark || '暂无备注' }}</span>
            </div>
          </div>
        </div>
      </div>

      <div class="odd-section" v-if="paymentImageList.length > 0">
        <div class="odd-section-header">
          <span>打款凭证</span>
          <el-tag type="info" size="small" effect="plain">{{ paymentImageList.length }} 张</el-tag>
        </div>
        <div class="odd-payment-images">
          <el-image
            v-for="(imgUrl, index) in paymentImageList"
            :key="index"
            :src="imgUrl"
            fit="cover"
            class="odd-payment-img"
            @click="handlePreview(index)"
          />
        </div>
        <el-image-viewer
          v-if="showImageViewer"
          :url-list="paymentImageList"
          :initial-index="previewIndex"
          @close="showImageViewer = false"
          teleported
        />
      </div>

      <div class="odd-section odd-section--devices">
        <div class="odd-section-header">
          <span>设备清单</span>
          <el-tag type="info" size="small" effect="plain" class="ml-auto">{{ deviceCount }} 台</el-tag>
        </div>

        <el-table
          v-if="!isMobile"
          :data="orderData.devices || []"
          style="width: 100%"
          border
          stripe
          size="small"
          :max-height="deviceTableHeight"
        >
          <el-table-column label="型号" min-width="210" fixed>
            <template #default="scope">
              <div class="odd-model-cell">
                <el-button
                  class="odd-model-link"
                  link
                  type="primary"
                  @click="openDeviceDetail(scope.row)"
                >
                  {{ scope.row.model || '未知型号' }}
                </el-button>
                <div class="odd-device-sub">ID {{ scope.row.id }} · {{ scope.row.status_name || '未知状态' }}</div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="串号" min-width="220">
            <template #default="scope">
              <div class="odd-serial-cell">
                <div v-if="scope.row.user_sn">用户：{{ scope.row.user_sn }}</div>
                <div>管理：{{ scope.row.imei || '暂无' }}</div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="规格" min-width="230">
            <template #default="scope">
              <div class="odd-spec-tags">
                <el-tag v-if="scope.row.capacity" size="small" type="info" effect="plain">{{ scope.row.capacity }}</el-tag>
                <el-tag v-if="scope.row.color" size="small" type="info" effect="plain">{{ scope.row.color }}</el-tag>
                <el-tag v-if="scope.row.system_version" size="small" type="info" effect="plain">{{ scope.row.system_version }}</el-tag>
                <el-tag v-if="scope.row.warranty_info" size="small" type="warning" effect="plain">{{ scope.row.warranty_info }}</el-tag>
                <span v-if="!scope.row.capacity && !scope.row.color && !scope.row.system_version && !scope.row.warranty_info" class="odd-no-spec">暂无</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="初始价格" width="100" align="right">
            <template #default="scope">
              <span>¥{{ scope.row.initial_price || '0.00' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="最终价格" width="110" align="right">
            <template #default="scope">
              <span class="odd-price">¥{{ scope.row.final_price || '0.00' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="110">
            <template #default="scope">
              <el-tag :type="scope.row.status === 6 ? 'danger' : 'success'" size="small">
                {{ scope.row.status_name || '暂无' }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <div v-else class="odd-device-cards">
          <div
            v-for="device in orderData.devices || []"
            :key="device.id"
            class="odd-device-card"
            @click="openDeviceDetail(device)"
          >
            <div class="odd-device-card__top">
              <span class="odd-device-card__model">{{ device.model || '未知型号' }}</span>
              <el-tag size="small" :type="device.status === 6 ? 'danger' : 'success'">
                {{ device.status_name || '暂无' }}
              </el-tag>
            </div>
            <div v-if="device.user_sn" class="odd-device-card__imei">用户串号：{{ device.user_sn }}</div>
            <div class="odd-device-card__imei">管理串号：{{ device.imei || '暂无' }}</div>
            <div class="odd-device-card__specs" v-if="device.capacity || device.color || device.system_version || device.warranty_info">
              <el-tag v-if="device.capacity" size="small" type="info" effect="plain">{{ device.capacity }}</el-tag>
              <el-tag v-if="device.color" size="small" type="info" effect="plain">{{ device.color }}</el-tag>
              <el-tag v-if="device.system_version" size="small" type="info" effect="plain">{{ device.system_version }}</el-tag>
              <el-tag v-if="device.warranty_info" size="small" type="warning" effect="plain">{{ device.warranty_info }}</el-tag>
            </div>
            <div class="odd-device-card__bottom">
              <span class="odd-device-card__id">ID：{{ device.id }}</span>
              <span class="odd-price">¥{{ device.final_price || '0.00' }}</span>
            </div>
          </div>
        </div>
      </div>

      <template v-if="returnOrderList.length > 0">
        <div class="odd-section" v-for="returnOrder in returnOrderList" :key="returnOrder.id">
          <div class="odd-section-header">
            <span>退货信息</span>
            <el-tag size="small" :type="returnOrderStatusType(returnOrder.status)">
              {{ returnOrder.status_name || '暂无' }}
            </el-tag>
          </div>
          <div class="odd-desc-grid odd-desc-grid--return">
            <div class="odd-desc-item">
              <span class="odd-desc-label">退货单号</span>
              <span class="odd-desc-value odd-desc-value--mono">{{ returnOrder.order_no || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">快递公司</span>
              <span class="odd-desc-value">{{ returnOrder.express_company || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">快递单号</span>
              <span class="odd-desc-value odd-inline-action">
                <span class="odd-desc-value--mono">{{ returnOrder.express_no || '暂无' }}</span>
                <el-button
                  v-if="returnOrder.express_no"
                  link
                  type="primary"
                  size="small"
                  @click="queryReturnExpress(returnOrder)"
                >
                  查物流
                </el-button>
              </span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">创建时间</span>
              <span class="odd-desc-value">{{ returnOrder.create_at || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">完成时间</span>
              <span class="odd-desc-value">{{ returnOrder.over_at || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">退货地址</span>
              <span class="odd-desc-value">{{ returnOrder.return_address || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--wide">
              <span class="odd-desc-label">备注</span>
              <span class="odd-desc-value">{{ returnOrder.remark || returnOrder.comment || '暂无' }}</span>
            </div>
          </div>

          <div v-if="returnOrder.return_devices?.length" class="odd-return-devices">
            <div class="odd-return-devices__title">退货设备</div>
            <el-table
              v-if="!isMobile"
              :data="returnOrder.return_devices"
              size="small"
              border
              stripe
              max-height="260"
            >
              <el-table-column label="型号" min-width="160">
                <template #default="scope">
                  <el-button
                    v-if="scope.row.device"
                    class="odd-model-link"
                    link
                    type="primary"
                    @click="openDeviceDetail(scope.row.device)"
                  >
                    {{ scope.row.device?.model || '暂无' }}
                  </el-button>
                  <span v-else>暂无</span>
                </template>
              </el-table-column>
              <el-table-column label="IMEI" min-width="170">
                <template #default="scope">{{ scope.row.device?.imei || '暂无' }}</template>
              </el-table-column>
              <el-table-column label="状态" width="110">
                <template #default="scope">
                  <el-tag size="small">{{ scope.row.status_name || '暂无' }}</el-tag>
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="odd-device-cards">
              <div
                v-for="rd in returnOrder.return_devices"
                :key="rd.id"
                class="odd-device-card"
                @click="rd.device && openDeviceDetail(rd.device)"
              >
                <div class="odd-device-card__top">
                  <span class="odd-device-card__model">{{ rd.device?.model || '暂无' }}</span>
                  <el-tag size="small">{{ rd.status_name || '暂无' }}</el-tag>
                </div>
                <div class="odd-device-card__imei">IMEI：{{ rd.device?.imei || '暂无' }}</div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </div>

    <template #footer>
      <div :class="isMobile ? 'odd-footer odd-footer--mobile' : 'odd-footer'">
        <el-button :class="isMobile ? '!ml-0 w-full' : ''" @click="dialogVisible = false">关闭</el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { getReturnOrderList } from '@/addon/recycle/api/recycle_return_order'

interface OrderDetail {
    id: number | string;
    order_no?: string;
    status: number;
    status_name: string;
    pay_type?: string;
    pay_account?: string;
    payment_images?: string;
    delivery_type_name?: string;
    express_company?: string;
    express_no?: string;
    pay_time?: number;
    create_at?: string;
    remark?: string;
    member?: { member_id: number | string; username?: string; nickname?: string; mobile?: string };
    recycleUserAddress?: { mobile?: string; [key: string]: any };
    devices?: Array<{
        id: number | string;
        imei: string;
        user_sn?: string;
        model: string;
        capacity?: string;
        color?: string;
        system_version?: string;
        warranty_info?: string;
        initial_price: number | string;
        final_price: number | string;
        status: number;
        status_name: string;
    }>;
    [key: string]: any;
}

const props = defineProps({
    visible: { type: Boolean, default: false },
    orderDetail: { type: Object as () => OrderDetail | null, default: null }
})

const emit = defineEmits(['update:visible', 'closed', 'view-device', 'query-express'])

const dialogVisible = ref(props.visible)
const orderData = ref<OrderDetail | null>(props.orderDetail)
const isMobile = ref(false)
const returnOrderList = ref<any[]>([])
const showImageViewer = ref(false)
const previewIndex = ref(0)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

const deviceCount = computed(() => orderData.value?.devices?.length || 0)
const deviceTableHeight = computed(() => (deviceCount.value > 8 ? 430 : undefined))

const totalAmount = computed(() => {
    if (!orderData.value?.devices) return '0.00'
    return orderData.value.devices.reduce((sum, d) => sum + (parseFloat(d.final_price as string) || 0), 0).toFixed(2)
})

const paymentImageList = computed(() => {
    if (!orderData.value?.payment_images) return []
    return orderData.value.payment_images.split(',').filter((i: string) => i.trim())
})

const handlePreview = (index: number) => { previewIndex.value = index; showImageViewer.value = true }

const formatTime = (time?: number | string) => {
    if (!time) return '暂无'
    if (typeof time === 'number') return new Date(time * 1000).toLocaleString()
    return time
}

const openDeviceDetail = (device: any) => {
    if (!device?.id) return
    emit('view-device', device)
}

const queryOrderExpress = () => {
    if (!orderData.value?.express_no) return
    emit('query-express', orderData.value)
}

const queryReturnExpress = (returnOrder: any) => {
    if (!returnOrder?.express_no || !orderData.value) return
    emit('query-express', {
        ...returnOrder,
        member: orderData.value.member,
        recycleUserAddress: {
            ...(orderData.value.recycleUserAddress || {}),
            mobile: returnOrder.member_mobile || orderData.value.member?.mobile || orderData.value.recycleUserAddress?.mobile
        }
    })
}

const returnOrderStatusType = (status: number) => {
    const map: Record<number, string> = { 0: 'warning', 1: '', 2: 'success', 3: 'info' }
    return map[status] || 'info'
}

const loadReturnOrders = async (orderId: number | string) => {
    try {
        const res = await getReturnOrderList({ order_id: orderId })
        const data = res.data || []
        returnOrderList.value = Array.isArray(data) ? data : (data.data || [])
    } catch { returnOrderList.value = [] }
}

watch(() => props.visible, (v) => { dialogVisible.value = v })
watch(dialogVisible, (v) => {
    emit('update:visible', v)
    if (!v) emit('closed')
})
watch(() => props.orderDetail, (v) => {
    orderData.value = v
    returnOrderList.value = []
    if (v?.id) loadReturnOrders(v.id)
})

onMounted(() => { updateResponsiveState(); window.addEventListener('resize', updateResponsiveState) })
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>

<style lang="scss" scoped>
.order-detail-dialog {
  :deep(.el-dialog) {
    border-radius: 10px;
    overflow: hidden;
  }

  :deep(.el-dialog__body) {
    padding: 0;
    background: #f4f6f8;
    overflow: hidden;
  }
}

.odd-wrap {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: calc(100vh - 150px);
  overflow-y: auto;

  &::-webkit-scrollbar { width: 6px; }
  &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
}

.odd-summary {
  display: grid;
  grid-template-columns: minmax(260px, 1.2fr) 2fr;
  gap: 10px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px 14px;
}

.odd-summary__label {
  font-size: 12px;
  color: #64748b;
  margin-bottom: 4px;
}

.odd-summary__no {
  font-size: 18px;
  font-weight: 700;
  color: #0f172a;
  word-break: break-all;
}

.odd-summary__metrics {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 8px;
}

.odd-metric {
  min-width: 0;
  padding: 8px 10px;
  background: #f8fafc;
  border: 1px solid #eef2f7;
  border-radius: 6px;

  span {
    display: block;
    margin-bottom: 4px;
    font-size: 11px;
    color: #64748b;
  }

  strong {
    display: block;
    min-width: 0;
    font-size: 13px;
    color: #1e293b;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.odd-info-layout {
  display: grid;
  grid-template-columns: minmax(300px, 0.85fr) minmax(420px, 1.15fr);
  gap: 10px;
}

.odd-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;
}

.odd-section--devices {
  min-height: 0;
}

.odd-section-header {
  min-height: 40px;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 12px;
  background: #f8fafc;
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
  font-weight: 600;
  color: #334155;
}

.odd-desc-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.odd-desc-grid--return {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.odd-desc-item {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 9px 12px;
  border-right: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;

  &:nth-child(2n) { border-right: none; }

  &--wide {
    grid-column: 1 / -1;
    border-right: none;
  }
}

.odd-desc-grid--return .odd-desc-item:nth-child(2n) {
  border-right: 1px solid #f1f5f9;
}

.odd-desc-label {
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.2;
}

.odd-desc-value {
  min-width: 0;
  font-size: 13px;
  color: #1e293b;
  line-height: 1.45;
  word-break: break-all;

  &--mono {
    font-family: 'SF Mono', 'Fira Code', Consolas, monospace;
    font-size: 12px;
  }
}

.odd-inline-action {
  display: flex;
  align-items: center;
  gap: 8px;
}

.odd-payment-images {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 12px;
}

.odd-payment-img {
  width: 88px;
  height: 88px;
  border-radius: 6px;
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: transform 0.2s, box-shadow 0.2s;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 14px rgba(15, 23, 42, 0.14);
  }
}

.odd-model-cell {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.odd-model-link {
  max-width: 100%;
  justify-content: flex-start;
  padding: 0;
  height: auto;
  line-height: 1.4;
  white-space: normal;
  text-align: left;
}

.odd-device-sub {
  font-size: 11px;
  color: #94a3b8;
}

.odd-spec-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.odd-no-spec {
  font-size: 12px;
  color: #94a3b8;
}

.odd-serial-cell {
  color: #475569;
  font-family: 'SF Mono', 'Fira Code', Consolas, monospace;
  font-size: 12px;
  line-height: 1.55;
  word-break: break-all;
}

.odd-price {
  color: #ea580c;
  font-weight: 700;
}

.odd-device-cards {
  max-height: 52vh;
  overflow-y: auto;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.odd-device-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
  background: #f8fafc;
  cursor: pointer;

  &__top,
  &__bottom {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
  }

  &__top { margin-bottom: 6px; }
  &__model { font-size: 13px; font-weight: 600; color: #1e293b; }
  &__imei { font-size: 11px; color: #64748b; font-family: monospace; margin-bottom: 5px; word-break: break-all; }
  &__specs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
  &__id { font-size: 11px; color: #94a3b8; }
}

.odd-return-devices {
  padding: 10px 12px;
  border-top: 1px solid #f1f5f9;

  &__title {
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
    margin-bottom: 8px;
  }
}

.odd-footer {
  display: flex;
  justify-content: flex-end;

  &--mobile { width: 100%; }
}

:deep(.el-table) {
  --el-table-header-bg-color: #f8fafc;
}

:deep(.el-table .cell) {
  line-height: 1.45;
}

@media (max-width: 960px) {
  .odd-summary,
  .odd-info-layout {
    grid-template-columns: 1fr;
  }

  .odd-summary__metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .odd-desc-grid--return {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  .odd-wrap {
    max-height: calc(100vh - 128px);
  }
}

@media (max-width: 520px) {
  .odd-summary__metrics,
  .odd-desc-grid,
  .odd-desc-grid--return {
    grid-template-columns: 1fr;
  }

  .odd-desc-item,
  .odd-desc-grid--return .odd-desc-item:nth-child(2n) {
    border-right: none;
  }
}
</style>
