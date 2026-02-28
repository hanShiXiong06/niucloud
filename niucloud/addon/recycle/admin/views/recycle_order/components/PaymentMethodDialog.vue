<template>
  <el-dialog v-model="dialogVisible" title="收款方式" width="700px" :destroy-on-close="true">
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

        <!-- 设备详情 -->
        <el-collapse>
          <el-collapse-item title="设备详情列表" name="devices">
            <el-table :data="currentPaymentInfo.order_summary.devices" size="small" border>
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
            </el-table>
          </el-collapse-item>
        </el-collapse>
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

    <!-- 对话框底部按钮 -->
    <template #footer>
      <div class="dialog-footer">
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button
          type="primary"
          @click="handleConfirmPayment"
          :disabled="!canConfirm"
          :loading="confirming"
        >
          确认已打款
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import { ElMessage } from 'element-plus'
import { Picture, InfoFilled } from '@element-plus/icons-vue'

// 定义支付信息接口
interface PaymentInfoItem {
  pay_type: string
  account: string
  qrcode_image?: string
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
      model: string
      imei: string
      final_price: number | string
      status: number
      status_name: string
    }>
  }
  [key: string]: any
}

const props = defineProps<{
  visible: boolean
  paymentInfo: PaymentInfoItem[]
  orderId?: number | string  // 订单ID，当paymentInfo为空时使用
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'payment-confirmed': [data: {
    orderId: number | string
    payType: string
    account?: string
    paymentImages?: string
  }]
}>()

// 内部状态
const dialogVisible = ref(props.visible)
const paymentInfoData = ref<PaymentInfoItem[]>(props.paymentInfo)
const selectedPayTypeIndex = ref(0)
const confirming = ref(false)

// 自定义支付信息（无收款码时使用）
const customPayType = ref('')
const customAccount = ref('')

// 打款凭证图片
const paymentImages = ref('')

// 计算当前选择的支付方式
const currentPaymentInfo = computed(() => {
  if (!paymentInfoData.value || paymentInfoData.value.length === 0 || selectedPayTypeIndex.value < 0) {
    return null
  }
  return paymentInfoData.value[selectedPayTypeIndex.value]
})

// 判断当前支付方式是否有收款码
const hasQrCode = computed(() => {
  return currentPaymentInfo.value?.qrcode_image ? true : false
})

// 判断是否可以确认打款
const canConfirm = computed(() => {
  // 有支付方式数据时
  if (paymentInfoData.value && paymentInfoData.value.length > 0) {
    // 必须有订单信息
    if (!currentPaymentInfo.value?.order_summary) return false
    // 如果没有收款码，必须填写自定义支付方式
    if (!hasQrCode.value && !customPayType.value) return false
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
})

// 确认打款
const handleConfirmPayment = () => {
  // 确定最终的支付方式和账号
  let finalPayType = ''
  let finalAccount = ''
  // 优先使用 order_summary 中的 order_id，否则使用 props.orderId
  let orderId: number | string = currentPaymentInfo.value?.order_summary?.order_id || props.orderId || ''

  if (paymentInfoData.value && paymentInfoData.value.length > 0 && currentPaymentInfo.value) {
    if (hasQrCode.value) {
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

  confirming.value = true

  // 发送确认事件
  emit('payment-confirmed', {
    orderId,
    payType: finalPayType,
    account: finalAccount,
    paymentImages: paymentImages.value
  })

  // 关闭对话框
  setTimeout(() => {
    confirming.value = false
    dialogVisible.value = false
  }, 300)
}
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

.custom-payment-card {
  margin-bottom: 16px;
  border: 1px dashed #e6a23c;
  background: #fdf6ec;
}

.custom-payment-card-empty {
  width: 100%;
  margin-top: 16px;
}

.payment-proof-card {
  border: 1px solid #409eff;
  background: #ecf5ff;
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
}

.price {
  color: #ff6b00;
  font-weight: 500;
}
</style>
