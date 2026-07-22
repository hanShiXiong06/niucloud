<template>
  <FormDialog
    :visible="dialogVisible"
    title="收款方式"
    subtitle="选择收款方式与账号，确认后打款（不可撤销）"
    width="md"
    :loading="submitting"
    :confirm-disabled="!canConfirm"
    :confirm-text="confirmButtonText"
    @update:visible="dialogVisible = $event"
    @confirm="handleConfirmPayment"
    @cancel="dialogVisible = false"
  >
    <div v-if="paymentInfoData && paymentInfoData.length > 0">
      <!-- 订单摘要信息卡片 -->
      <el-card v-if="currentPaymentInfo && currentPaymentInfo.order_summary" shadow="never" class="mb-4">
        <template #header>
          <div class="card-header">
            <span>订单 #{{ currentPaymentInfo.order_summary.order_id }}</span>
            <el-tag :type="currentPaymentInfo.order_summary.status === 7 ? 'success' : 'warning'" size="small">
              ¥{{ currentPaymentInfo.order_summary.total_amount }}
            </el-tag>
          </div>
        </template>

        <el-alert v-if="isDevicePaymentMode" class="mb-4" type="warning" :closable="false" show-icon>
          <template #title>
            当前为按设备打款模式，请选择本次要打款的设备。未选择的设备会继续保持未打款状态。
          </template>
        </el-alert>

        <!-- 设备详情 -->
        <el-collapse>
          <el-collapse-item title="设备详情列表" name="devices">
            <el-table
              v-if="!isMobile"
              :data="currentPaymentInfo.order_summary.devices"
              size="small"
              border
              @selection-change="handleDeviceSelectionChange"
            >
              <el-table-column v-if="isDevicePaymentMode" type="selection" width="46" :selectable="isDeviceSelectable" />
              <el-table-column prop="model" label="型号" min-width="120" />
              <el-table-column prop="imei" label="IMEI" min-width="120" show-overflow-tooltip />
              <el-table-column prop="final_price" label="价格" width="80">
                <template #default="scope">
                  <span class="price">¥{{ scope.row.final_price }}</span>
                </template>
              </el-table-column>
              <el-table-column prop="status_name" label="状态" width="80">
                <template #default="scope">
                  <el-tag size="small" :type="scope.row.status === 6 ? 'danger' : 'success'">
                    {{ scope.row.status_name }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column v-if="isDevicePaymentMode" prop="pay_status_name" label="打款" width="90">
                <template #default="scope">
                  <el-tag size="small" :type="scope.row.pay_status === 1 ? 'success' : 'warning'">
                    {{ scope.row.pay_status_name || (scope.row.pay_status === 1 ? '已打款' : '未打款') }}
                  </el-tag>
                </template>
              </el-table-column>
              <el-table-column v-if="isDevicePaymentMode" label="说明" min-width="120">
                <template #default="scope">
                  <span class="text-xs" :class="isDeviceSelectable(scope.row) ? 'text-green-600' : 'text-gray-400'">
                    {{ isDeviceSelectable(scope.row) ? '可打款' : (scope.row.pay_disabled_reason || scope.row.disabled_reason || '暂不可打款') }}
                  </span>
                </template>
              </el-table-column>
            </el-table>
            <div v-else class="space-y-2">
              <div
                v-for="(device, deviceIndex) in currentPaymentInfo.order_summary.devices || []"
                :key="`${device.imei}-${deviceIndex}`"
                class="rounded-lg border border-gray-200 bg-gray-50 p-3"
              >
                <div class="mb-1 flex items-start justify-between gap-2">
                  <div class="flex items-center gap-2">
                    <el-checkbox
                      v-if="isDevicePaymentMode"
                      :model-value="selectedDeviceIds.includes(device.id)"
                      :disabled="!isDeviceSelectable(device)"
                      @change="toggleMobileDevice(device, $event)"
                    />
                    <div class="text-sm font-semibold text-gray-800">{{ device.model || '未知型号' }}</div>
                  </div>
                  <el-tag size="small" :type="device.status === 6 ? 'danger' : 'success'">
                    {{ device.status_name }}
                  </el-tag>
                </div>
                <div class="text-xs text-gray-500 break-all">{{ device.imei || '无IMEI' }}</div>
                <div class="mt-2 flex items-center justify-between">
                  <div class="text-sm font-semibold text-orange-500">¥{{ device.final_price }}</div>
                  <el-tag v-if="isDevicePaymentMode" size="small" :type="device.pay_status === 1 ? 'success' : 'warning'">
                    {{ device.pay_status_name || (device.pay_status === 1 ? '已打款' : '未打款') }}
                  </el-tag>
                </div>
                <div v-if="isDevicePaymentMode" class="mt-1 text-xs" :class="isDeviceSelectable(device) ? 'text-green-600' : 'text-gray-400'">
                  {{ isDeviceSelectable(device) ? '可打款' : (device.pay_disabled_reason || device.disabled_reason || '暂不可打款') }}
                </div>
              </div>
            </div>
          </el-collapse-item>
        </el-collapse>
        <div v-if="isDevicePaymentMode" class="device-payment-summary">
          <span>已选 {{ selectedDevices.length }} 台</span>
          <strong>本次打款 ¥{{ selectedDeviceAmount.toFixed(2) }}</strong>
        </div>
      </el-card>

      <!-- 支付方式选择 -->
      <el-card shadow="never" class="payment-method-card">
        <template #header>
          <div class="card-header">
            <span>选择收款方式</span>
          </div>
        </template>

        <el-radio-group v-model="selectedPayTypeIndex" class="payment-radio-group">
          <el-radio-button v-for="(item, index) in paymentInfoData" :key="index" :value="index">
            {{ item.pay_type }}
          </el-radio-button>
        </el-radio-group>

        <!-- 收款信息 -->
        <div v-if="currentPaymentInfo" class="payment-info">
          <div class="account-info">
            <el-text type="info">收款账号：</el-text>
            <el-text type="primary">{{ currentPaymentInfo.account }}</el-text>
          </div>

          <div class="qrcode-container">
            <div v-if="currentPaymentInfo.qrcode_image">
              <el-image class="qrcode" :src="currentPaymentInfo.qrcode_image" fit="contain" />
            </div>
            <div v-else class="no-qrcode">
              <el-icon><Picture /></el-icon>
              <span>暂无收款码</span>
            </div>
          </div>
        </div>
      </el-card>

      <!-- 自定义支付方式（无收款码时显示） -->
      <el-card v-if="!hasQrCode" shadow="never" class="custom-payment-card">
        <template #header>
          <div class="card-header">
            <span>📝 自定义支付信息</span>
            <el-tag type="warning" size="small">用户未上传收款码</el-tag>
          </div>
        </template>

        <el-form label-position="top">
          <el-form-item label="支付方式">
            <el-input
              v-model="customPayType"
              placeholder="请输入支付方式，如：微信转账、支付宝转账、银行卡转账等"
              clearable
            />
          </el-form-item>
          <el-form-item label="收款账号">
            <el-input
              v-model="customAccount"
              placeholder="请输入收款账号"
              clearable
            />
          </el-form-item>
        </el-form>
      </el-card>

      <!-- 打款凭证上传 -->
      <el-card shadow="never" class="payment-proof-card">
        <template #header>
          <div class="card-header">
            <span>📸 打款凭证</span>
            <el-tag type="info" size="small">支持多张图片</el-tag>
          </div>
        </template>

        <div class="upload-section">
          <upload-image v-model="paymentImages" :limit="9" />
          <div class="upload-tip">
            <el-icon><InfoFilled /></el-icon>
            <span>请上传打款截图作为凭证，最多9张</span>
          </div>
        </div>
      </el-card>
    </div>

    <!-- 无支付方式时的空状态 -->
    <div v-else class="empty-state">
      <el-empty description="该商户暂无收款方式信息">
        <template #default>
          <el-card shadow="never" class="custom-payment-card-empty">
            <el-form label-position="top">
              <el-form-item label="支付方式">
                <el-input
                  v-model="customPayType"
                  placeholder="请输入支付方式"
                  clearable
                />
              </el-form-item>
              <el-form-item label="收款账号">
                <el-input
                  v-model="customAccount"
                  placeholder="请输入收款账号"
                  clearable
                />
              </el-form-item>
              <el-form-item label="打款凭证">
                <upload-image v-model="paymentImages" :limit="9" />
              </el-form-item>
            </el-form>
          </el-card>
        </template>
      </el-empty>
    </div>

    <!-- ERP 接管财务后，资金账户是实际付款事实的必填项。 -->
    <el-card v-if="showCapitalAccount" shadow="never" class="capital-account-card">
      <template #header>
        <div class="card-header">
          <span>💰 出账户头</span>
          <el-tag type="warning" size="small">ERP接管时必选</el-tag>
        </div>
      </template>
      <el-select
        v-model="selectedCapitalAccountId"
        placeholder="请选择实际出款账户"
        clearable
        filterable
        style="width: 100%"
      >
        <el-option
          v-for="acc in capitalAccounts"
          :key="acc.id"
          :label="capitalAccountLabel(acc)"
          :value="acc.id"
        />
      </el-select>
      <div class="capital-account-tip">
        <el-icon><InfoFilled /></el-icon>
        <span>确认后由 ERP 统一生成付款结算、账户出账和设备级核销，回收插件只接收结算结果，不会重复记账。</span>
      </div>
    </el-card>
  </FormDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage } from 'element-plus'
import FormDialog from '@/addon/hsx_recycle/components/FormDialog.vue'
import { Picture, InfoFilled } from '@element-plus/icons-vue'
import { getCapitalAccountOptions } from '@/addon/hsx_recycle/api/recycle_order'

// 定义支付信息接口
interface PaymentInfoItem {
  pay_type: string
  account: string
  qrcode_image?: string
  payment_mode?: 'order' | 'device'
  device_payment_summary?: Record<string, any> | null
  order_summary?: {
    order_id: number | string
    status?: number
    delivery_type_name?: string
    device_count?: number
    customer_name?: string
    customer_mobile?: string
    total_amount?: number | string
    checked_count?: number
    confirmed_count?: number
    success_count?: number
    returned_count?: number
    devices?: Array<{
      id: number | string
      model: string
      imei: string
      final_price: number | string
      status: number
      status_name: string
      pay_status?: number
      pay_status_name?: string
      confirm_status?: number
      confirm_status_name?: string
      can_pay?: boolean
      pay_disabled_reason?: string
      disabled_reason?: string
    }>
  }
  [key: string]: any
}

const props = withDefaults(defineProps<{
  visible: boolean
  paymentInfo: PaymentInfoItem[]
  orderId?: number | string  // 订单ID，当paymentInfo为空时使用
  submitting?: boolean        // 父级真实的打款提交中状态
}>(), { submitting: false })

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'payment-confirmed': [data: {
    orderId: number | string
    payType: string
    account?: string
    paymentImages?: string
    paymentMode?: 'order' | 'device'
    selectedDeviceIds?: Array<number | string>
    amount?: number | string      // 本次打款金额（订单模式为订单总额）
    deviceCount?: number          // 设备模式下本次打款的设备数
    capitalAccountId?: number     // 出账户头ID（来自ERP资金账户，未选为undefined）
  }]
}>()

// 内部状态
const dialogVisible = ref(props.visible)
const paymentInfoData = ref<PaymentInfoItem[]>(props.paymentInfo)
const selectedPayTypeIndex = ref(0)
// confirming 由父级通过 props.submitting 传入（反映真实的服务端打款进行中状态）
const isMobile = ref(false)
const selectedDevices = ref<any[]>([])

// 自定义支付信息（无收款码时使用）
const customPayType = ref('')
const customAccount = ref('')

// 打款凭证图片
const paymentImages = ref('')

// 出账户头（从哪个ERP资金账户出钱）
const capitalAccounts = ref<any[]>([])
const erpConnected = ref(false)
const selectedCapitalAccountId = ref<number | undefined>(undefined)

// 仅当装了ERP且有启用账户时才显示户头选择
const showCapitalAccount = computed(() => erpConnected.value && capitalAccounts.value.length > 0)

const capitalAccountLabel = (acc: any) => {
  const typeName = acc.type_name || acc.account_type_text || ''
  const typeText = typeName ? `[${typeName}] ` : ''
  const bal = (acc.balance !== undefined && acc.balance !== null && acc.balance !== '')
    ? ` · 余额¥${acc.balance}` : ''
  return `${typeText}${acc.name || acc.account_name || '未命名账户'}${bal}`
}

const loadCapitalAccounts = async () => {
  try {
    const res: any = await getCapitalAccountOptions()
    capitalAccounts.value = Array.isArray(res.data?.accounts) ? res.data.accounts : []
    erpConnected.value = !!res.data?.erp_connected
  } catch (e) {
    capitalAccounts.value = []
    erpConnected.value = false
  }
}

const updateResponsiveState = () => {
  isMobile.value = window.innerWidth <= 768
}

// 计算当前选择的支付方式
const currentPaymentInfo = computed(() => {
  if (!paymentInfoData.value || paymentInfoData.value.length === 0 || selectedPayTypeIndex.value < 0) {
    return null
  }
  return paymentInfoData.value[selectedPayTypeIndex.value]
})

const isDevicePaymentMode = computed(() => currentPaymentInfo.value?.payment_mode === 'device')

const selectedDeviceIds = computed(() => selectedDevices.value.map((item) => item.id))

const selectedDeviceAmount = computed(() => {
  return selectedDevices.value.reduce((sum, item) => sum + Number(item.final_price || 0), 0)
})

const confirmButtonText = computed(() => {
  if (isDevicePaymentMode.value) {
    return selectedDevices.value.length > 0 ? `确认给 ${selectedDevices.value.length} 台设备打款` : '请选择设备'
  }
  return '确认已打款'
})

const isDeviceSelectable = (device: any) => {
  if (typeof device.can_pay !== 'undefined') {
    return Boolean(device.can_pay)
  }
  return Number(device.status) === 5 && Number(device.pay_status || 0) !== 1 && Number(device.final_price || 0) > 0
}

const handleDeviceSelectionChange = (rows: any[]) => {
  selectedDevices.value = rows.filter(isDeviceSelectable)
}

const toggleMobileDevice = (device: any, checked: string | number | boolean) => {
  const index = selectedDevices.value.findIndex((item) => item.id === device.id)
  if (checked && index === -1 && isDeviceSelectable(device)) {
    selectedDevices.value.push(device)
  }
  if (!checked && index > -1) {
    selectedDevices.value.splice(index, 1)
  }
}

// 判断当前支付方式是否有收款码
const hasQrCode = computed(() => {
  return currentPaymentInfo.value?.qrcode_image ? true : false
})

const needsCustomPayType = computed(() => {
  return !currentPaymentInfo.value?.pay_type || currentPaymentInfo.value?.pay_type === '自定义'
})

// 判断是否可以确认打款
const canConfirm = computed(() => {
  // 有支付方式数据时
  if (paymentInfoData.value && paymentInfoData.value.length > 0) {
    // 必须有订单信息
    if (!currentPaymentInfo.value?.order_summary) return false
    // 如果没有收款码，必须填写自定义支付方式
    if (needsCustomPayType.value && !customPayType.value) return false
    return true
  }
  // 无支付方式数据时，必须填写自定义支付方式
  return !!customPayType.value
})

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
  dialogVisible.value = newVal
  if (newVal) {
    // 重置状态
    customPayType.value = ''
    customAccount.value = ''
    paymentImages.value = ''
    selectedDevices.value = []
    selectedCapitalAccountId.value = undefined
    loadCapitalAccounts()
  }
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
  emit('update:visible', newVal)
})

// 监听paymentInfo变化
watch(() => props.paymentInfo, (newVal) => {
  paymentInfoData.value = newVal
  selectedPayTypeIndex.value = newVal.length > 0 ? 0 : -1
  selectedDevices.value = []
})

// 确认打款
const handleConfirmPayment = () => {
  // 确定最终的支付方式和账号
  let finalPayType = ''
  let finalAccount = ''
  // 优先使用 order_summary 中的 order_id，否则使用 props.orderId
  let orderId: number | string = currentPaymentInfo.value?.order_summary?.order_id || props.orderId || ''

  if (paymentInfoData.value && paymentInfoData.value.length > 0 && currentPaymentInfo.value) {
    if (hasQrCode.value && currentPaymentInfo.value.pay_type !== '自定义') {
      // 有收款码，使用选择的支付方式
      finalPayType = currentPaymentInfo.value.pay_type
      finalAccount = currentPaymentInfo.value.account
    } else {
      // 无收款码，使用自定义支付方式
      finalPayType = customPayType.value || currentPaymentInfo.value.pay_type
      finalAccount = customAccount.value || currentPaymentInfo.value.account
    }
  } else {
    // 完全没有支付方式数据，使用自定义
    finalPayType = customPayType.value
    finalAccount = customAccount.value
  }

  if (!finalPayType) {
    ElMessage.warning('请选择或输入支付方式')
    return
  }

  if (isDevicePaymentMode.value && selectedDevices.value.length === 0) {
    ElMessage.warning('请选择本次需要打款的设备')
    return
  }

  if (erpConnected.value && !selectedCapitalAccountId.value) {
    ElMessage.warning(capitalAccounts.value.length ? '请选择ERP实际出款账户' : 'ERP未配置可用资金账户，请先在ERP资金账户中启用账户')
    return
  }

  // 仅发起打款，不在此自关弹窗：由父级在服务端打款成功后关闭，失败时弹窗保留。
  // 加载态与防重复点击由 props.submitting 驱动。
  emit('payment-confirmed', {
    orderId,
    payType: finalPayType,
    account: finalAccount,
    paymentImages: paymentImages.value,
    paymentMode: isDevicePaymentMode.value ? 'device' : 'order',
    selectedDeviceIds: selectedDeviceIds.value,
    amount: currentPaymentInfo.value?.order_summary?.total_amount,
    deviceCount: isDevicePaymentMode.value ? selectedDevices.value.length : undefined,
    capitalAccountId: selectedCapitalAccountId.value
  })
}

onMounted(() => {
  updateResponsiveState()
  window.addEventListener('resize', updateResponsiveState)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateResponsiveState)
})
</script>

<style lang="scss" scoped>
.card-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.mb-4 {
  margin-bottom: 16px;
}

.payment-method-card {
  margin-bottom: 16px;
}

.payment-radio-group {
  display: flex;
  flex-wrap: wrap;
  margin-bottom: 16px;
  gap: 8px;
}

.payment-radio-group :deep(.el-radio-button) {
  margin-left: 0;
}

.payment-info {
  display: flex;
  flex-direction: column;
  align-items: center;
}

.account-info {
  margin-bottom: 16px;
  text-align: center;
}

.qrcode-container {
  display: flex;
  justify-content: center;
}

.qrcode {
  width: 180px;
  height: 180px;
  border: 1px solid #ebeef5;
  border-radius: 4px;
}

.no-qrcode {
  width: 180px;
  height: 180px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  background-color: #f5f7fa;
  border-radius: 4px;
  color: #909399;

  .el-icon {
    font-size: 32px;
    margin-bottom: 8px;
  }
}

/* 统一卡片风格：浅边框 + 留白背景，靠头部标签区分用途，避免彩色边框拼色 */
.payment-method-card,
.custom-payment-card,
.payment-proof-card,
.capital-account-card {
  margin-bottom: 16px;
  border: 1px solid var(--el-border-color-lighter);
  background: var(--el-fill-color-blank);
}

.capital-account-card {
  margin-bottom: 0;
}

.custom-payment-card-empty {
  width: 100%;
  margin-top: 16px;
}

.capital-account-tip {
  display: flex;
  align-items: center;
  gap: 6px;
  margin-top: 10px;
  font-size: 12px;
  color: var(--el-text-color-secondary);

  .el-icon {
    color: var(--el-color-primary);
  }
}

.upload-section {
  .upload-tip {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    font-size: 12px;
    color: #909399;

    .el-icon {
      color: #409eff;
    }
  }
}

.empty-state {
  padding: 16px 0;
}

.dialog-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
}

.price {
  color: #ff6b00;
  font-weight: 500;
}

.device-payment-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  background: #f8fafc;
  color: #475569;

  strong {
    color: #111827;
  }
}

@media (max-width: 768px) {
  .card-header {
    align-items: flex-start;
    gap: 8px;
  }

  .payment-radio-group {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .payment-radio-group :deep(.el-radio-button) {
    width: 100%;
  }

  .payment-radio-group :deep(.el-radio-button__inner) {
    width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  .qrcode {
    width: 140px;
    height: 140px;
  }

  .no-qrcode {
    width: 140px;
    height: 140px;
  }

  .mobile-footer {
    width: 100%;
    flex-direction: column;
  }
}
</style>
