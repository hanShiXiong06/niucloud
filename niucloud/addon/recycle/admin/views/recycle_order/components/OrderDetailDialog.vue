<template>
  <el-dialog
    v-model="dialogVisible"
    title="订单详情"
    :width="isMobile ? '95vw' : '820px'"
    top="4vh"
    class="diy-dialog-wrap order-detail-dialog"
    :destroy-on-close="true"
  >
    <div v-if="orderData" class="odd-wrap">

      <!-- ===== 会员信息 ===== -->
      <div class="odd-section" v-if="orderData.member">
        <div class="odd-section-header">👤 会员信息</div>
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

      <!-- ===== 订单信息 ===== -->
      <div class="odd-section">
        <div class="odd-section-header">📦 订单信息</div>
        <div class="odd-desc-grid odd-desc-grid--3col">
          <div class="odd-desc-item">
            <span class="odd-desc-label">订单编号</span>
            <span class="odd-desc-value odd-desc-value--mono">{{ orderData.id || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">订单状态</span>
            <el-tag :type="orderData.status === 7 ? 'success' : 'info'" size="small">{{ orderData.status_name }}</el-tag>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">总金额</span>
            <span class="odd-desc-value odd-desc-value--price">¥{{ totalAmount }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">打款方式</span>
            <span class="odd-desc-value">{{ orderData.pay_type || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">收款账号</span>
            <span class="odd-desc-value odd-desc-value--mono">{{ orderData.pay_account || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">打款时间</span>
            <span class="odd-desc-value">
              {{ orderData.pay_time ? new Date(orderData.pay_time * 1000).toLocaleString() : '暂无' }}
            </span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">配送方式</span>
            <span class="odd-desc-value">{{ orderData.delivery_type_name || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">快递公司</span>
            <span class="odd-desc-value">{{ orderData.express_company || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">快递单号</span>
            <span class="odd-desc-value odd-desc-value--mono">{{ orderData.express_no || '暂无' }}</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">设备数量</span>
            <span class="odd-desc-value">{{ deviceCount }} 台</span>
          </div>
          <div class="odd-desc-item">
            <span class="odd-desc-label">创建时间</span>
            <span class="odd-desc-value">{{ orderData.create_at || '暂无' }}</span>
          </div>
          <div class="odd-desc-item odd-desc-item--full">
            <span class="odd-desc-label">备注</span>
            <span class="odd-desc-value">{{ orderData.remark || '暂无备注' }}</span>
          </div>
        </div>
      </div>

      <!-- ===== 打款凭证图片 ===== -->
      <div class="odd-section" v-if="paymentImageList.length > 0">
        <div class="odd-section-header">📸 打款凭证</div>
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

      <!-- ===== 设备清单 ===== -->
      <div class="odd-section">
        <div class="odd-section-header">
          📱 设备清单
          <el-tag type="info" size="small" effect="plain" class="ml-auto">{{ deviceCount }} 台</el-tag>
        </div>

        <!-- PC 端表格 -->
        <el-table
          v-if="!isMobile"
          :data="orderData.devices"
          style="width: 100%"
          border
          stripe
          size="small"
        >
          <el-table-column prop="id" label="ID" width="55" />
          <el-table-column prop="model" label="型号" min-width="110" />
          <el-table-column prop="imei" label="IMEI" min-width="130" />
          <el-table-column label="规格" min-width="180">
            <template #default="scope">
              <div class="odd-spec-tags">
                <el-tag v-if="scope.row.capacity" size="small" type="info" effect="plain">{{ scope.row.capacity }}</el-tag>
                <el-tag v-if="scope.row.color" size="small" type="info" effect="plain">{{ scope.row.color }}</el-tag>
                <el-tag v-if="scope.row.system_version" size="small" type="info" effect="plain">{{ scope.row.system_version }}</el-tag>
                <el-tag v-if="scope.row.warranty_info" size="small" type="warning" effect="plain">{{ scope.row.warranty_info }}</el-tag>
                <span v-if="!scope.row.capacity && !scope.row.color && !scope.row.system_version && !scope.row.warranty_info" class="odd-no-spec">—</span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="final_price" label="最终价格" width="90">
            <template #default="scope">
              <span class="odd-price">¥{{ scope.row.final_price }}</span>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="90">
            <template #default="scope">
              <el-tag :type="scope.row.status === 6 ? 'danger' : 'success'" size="small">
                {{ scope.row.status_name }}
              </el-tag>
            </template>
          </el-table-column>
        </el-table>

        <!-- 移动端卡片 -->
        <div v-else class="odd-device-cards">
          <div
            v-for="device in orderData.devices || []"
            :key="device.id"
            class="odd-device-card"
          >
            <div class="odd-device-card__top">
              <span class="odd-device-card__model">{{ device.model || '未知型号' }}</span>
              <el-tag size="small" :type="device.status === 6 ? 'danger' : 'success'">
                {{ device.status_name }}
              </el-tag>
            </div>
            <div class="odd-device-card__imei">IMEI：{{ device.imei || '暂无' }}</div>
            <!-- 规格信息 -->
            <div class="odd-device-card__specs" v-if="device.capacity || device.color || device.system_version || device.warranty_info">
              <el-tag v-if="device.capacity" size="small" type="info" effect="plain">{{ device.capacity }}</el-tag>
              <el-tag v-if="device.color" size="small" type="info" effect="plain">{{ device.color }}</el-tag>
              <el-tag v-if="device.system_version" size="small" type="info" effect="plain">{{ device.system_version }}</el-tag>
              <el-tag v-if="device.warranty_info" size="small" type="warning" effect="plain">{{ device.warranty_info }}</el-tag>
            </div>
            <div class="odd-device-card__bottom">
              <span class="odd-device-card__id">ID：{{ device.id }}</span>
              <span class="odd-price">¥{{ device.final_price }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ===== 退货信息 ===== -->
      <template v-if="returnOrderList.length > 0">
        <div class="odd-section" v-for="returnOrder in returnOrderList" :key="returnOrder.id">
          <div class="odd-section-header">🔄 退货信息</div>
          <div class="odd-desc-grid">
            <div class="odd-desc-item">
              <span class="odd-desc-label">退货单号</span>
              <span class="odd-desc-value odd-desc-value--mono">{{ returnOrder.order_no }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">退货状态</span>
              <el-tag size="small" :type="returnOrderStatusType(returnOrder.status)">{{ returnOrder.status_name }}</el-tag>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">快递公司</span>
              <span class="odd-desc-value">{{ returnOrder.express_company || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">快递单号</span>
              <span class="odd-desc-value odd-desc-value--mono">{{ returnOrder.express_no || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">创建时间</span>
              <span class="odd-desc-value">{{ returnOrder.create_at || '暂无' }}</span>
            </div>
            <div class="odd-desc-item">
              <span class="odd-desc-label">完成时间</span>
              <span class="odd-desc-value">{{ returnOrder.over_at || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--full">
              <span class="odd-desc-label">退货地址</span>
              <span class="odd-desc-value">{{ returnOrder.return_address || '暂无' }}</span>
            </div>
            <div class="odd-desc-item odd-desc-item--full">
              <span class="odd-desc-label">备注</span>
              <span class="odd-desc-value">{{ returnOrder.remark || returnOrder.comment || '暂无' }}</span>
            </div>
          </div>
          <!-- 退货设备列表 -->
          <div v-if="returnOrder.return_devices?.length" class="odd-return-devices">
            <div class="odd-return-devices__title">退货设备</div>
            <el-table v-if="!isMobile" :data="returnOrder.return_devices" size="small" border stripe>
              <el-table-column label="IMEI" min-width="130">
                <template #default="scope">{{ scope.row.device?.imei || '暂无' }}</template>
              </el-table-column>
              <el-table-column label="型号" min-width="100">
                <template #default="scope">{{ scope.row.device?.model || '暂无' }}</template>
              </el-table-column>
              <el-table-column label="状态" width="100">
                <template #default="scope">
                  <el-tag size="small">{{ scope.row.status_name || '暂无' }}</el-tag>
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="odd-device-cards">
              <div v-for="rd in returnOrder.return_devices" :key="rd.id" class="odd-device-card">
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
    devices?: Array<{
        id: number | string;
        imei: string;
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

const emit = defineEmits(['update:visible'])

const dialogVisible = ref(props.visible)
const orderData = ref<OrderDetail | null>(props.orderDetail)
const isMobile = ref(false)
const returnOrderList = ref<any[]>([])
const showImageViewer = ref(false)
const previewIndex = ref(0)

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

const deviceCount = computed(() => orderData.value?.devices?.length || 0)

const totalAmount = computed(() => {
    if (!orderData.value?.devices) return '0.00'
    return orderData.value.devices.reduce((sum, d) => sum + (parseFloat(d.final_price as string) || 0), 0).toFixed(2)
})

const paymentImageList = computed(() => {
    if (!orderData.value?.payment_images) return []
    return orderData.value.payment_images.split(',').filter((i: string) => i.trim())
})

const handlePreview = (index: number) => { previewIndex.value = index; showImageViewer.value = true }

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
watch(dialogVisible, (v) => { emit('update:visible', v) })
watch(() => props.orderDetail, (v) => {
    orderData.value = v
    returnOrderList.value = []
    if (v?.id) loadReturnOrders(v.id)
})

onMounted(() => { updateResponsiveState(); window.addEventListener('resize', updateResponsiveState) })
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>

<style lang="scss" scoped>
/* Dialog */
.order-detail-dialog {
  :deep(.el-dialog__body) { padding: 0; background: #f1f5f9; }
}

/* 整体包裹 */
.odd-wrap {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 80vh;
  overflow-y: auto;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
}

/* section 卡片 */
.odd-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
}

.odd-section-header {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 9px 14px;
  background: linear-gradient(to right, #f9fafb, #f3f4f6);
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
  font-weight: 600;
  color: #374151;
}

/* 描述 grid */
.odd-desc-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 0;

  &--3col { grid-template-columns: repeat(3, 1fr); }
}

.odd-desc-item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 10px 14px;
  border-bottom: 1px solid #f3f4f6;
  border-right: 1px solid #f3f4f6;

  &:nth-child(2n) { border-right: none; }
  &:last-child, &:nth-last-child(2):nth-child(2n+1) { border-bottom: none; }

  &--full {
    grid-column: 1 / -1;
    border-right: none;
  }

  .odd-desc-label {
    font-size: 10px;
    color: #9ca3af;
    font-weight: 500;
  }
  .odd-desc-value {
    font-size: 13px;
    color: #1e293b;
    font-weight: 500;
    word-break: break-all;

    &--mono { font-family: 'SF Mono', 'Fira Code', monospace; font-size: 12px; }
    &--price { color: #ea580c; font-weight: 700; font-size: 15px; }
  }
}

/* 打款凭证 */
.odd-payment-images {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  padding: 12px 14px;
}
.odd-payment-img {
  width: 100px;
  height: 100px;
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  cursor: pointer;
  transition: transform 0.2s;
  &:hover { transform: scale(1.04); box-shadow: 0 4px 12px rgba(0,0,0,0.12); }
}

/* 规格标签 */
.odd-spec-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
.odd-no-spec { font-size: 12px; color: #d1d5db; }

/* 价格 */
.odd-price {
  color: #ea580c;
  font-weight: 700;
  font-size: 14px;
}

/* 设备卡片（移动端） */
.odd-device-cards {
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}
.odd-device-card {
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 10px 12px;
  background: #f9fafb;

  &__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
  }
  &__model { font-size: 13px; font-weight: 600; color: #1e293b; }
  &__imei { font-size: 11px; color: #6b7280; font-family: monospace; margin-bottom: 6px; }
  &__specs { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; }
  &__bottom { display: flex; align-items: center; justify-content: space-between; }
  &__id { font-size: 11px; color: #9ca3af; }
}

/* 退货设备 */
.odd-return-devices {
  padding: 10px 14px;
  border-top: 1px solid #f3f4f6;

  &__title {
    font-size: 12px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 8px;
  }
}

/* Footer */
.odd-footer {
  display: flex;
  justify-content: flex-end;
  &--mobile { width: 100%; }
}

/* 响应式 */
@media (max-width: 768px) {
  .odd-desc-grid { grid-template-columns: repeat(2, 1fr); }
  .odd-desc-grid--3col { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
  .odd-desc-grid,
  .odd-desc-grid--3col { grid-template-columns: 1fr; }
  .odd-desc-item { border-right: none; }
}
</style>
