<template>
  <el-dialog
    v-model="dialogVisible"
    title=""
    :width="isMobile ? '95vw' : '600px'"
    top="4vh"
    :destroy-on-close="true"
    class="price-form-dialog"
  >
    <!-- ===== 渐变 Header ===== -->
    <template #header>
      <div class="pfd-header">
        <div class="pfd-header__left">
          <div class="pfd-header__icon">💰</div>
          <div>
            <h3 class="pfd-header__title">设备定价</h3>
            <p class="pfd-header__sub">请填写最终收购价格</p>
          </div>
        </div>
        <el-tag size="small" effect="plain" class="pfd-header__id">ID: {{ deviceData.id }}</el-tag>
      </div>
    </template>

    <div class="pfd-body">

      <!-- ===== 设备信息卡片 ===== -->
      <DeviceInfoCard :device="deviceData" mode="full" :show-status="false" />

      <!-- ===== 质检结果（只读展示）===== -->
      <div
        class="pfd-check-result"
        v-if="deviceData.check_result_seller || deviceData.check_result"
      >
        <div class="pfd-block-header">
          <span>✅ 卖家质检结果</span>
        </div>
        <div class="pfd-check-result__content">
          {{ deviceData.check_result_seller || deviceData.check_result }}
        </div>
      </div>

      <!-- ===== 定价表单 ===== -->
      <div class="pfd-form-card">
        <div class="pfd-block-header">
          <span>📋 定价信息</span>
        </div>
        <el-form :model="deviceForm" label-position="top" class="pfd-form">

          <!-- 最终价格 -->
          <el-form-item label="最终价格 *">
            <el-input-number
              v-model="deviceForm.final_price"
              placeholder="请输入最终价格"
              :step="10"
              :min="0"
              style="width: 100%;"
              @change="updatePriceClass"
            />
            <!-- 价格变化指示 -->
            <div v-if="deviceData.before_price && deviceForm.final_price !== undefined" class="pfd-price-diff">
              <span :class="['pfd-price-diff__badge', priceChangeClass]">
                {{ getPriceChangeText() }}
              </span>
            </div>
            <div class="pfd-hint">输入设备的最终定价金额，将影响用户的实际收益</div>
          </el-form-item>

          <!-- 卖货价格 -->
          <el-form-item label="卖货价格">
            <el-input-number
              v-model="deviceForm.sell_price"
              placeholder="请输入卖货价格"
              :step="10"
              :min="0"
              style="width: 100%;"
            />
            <div class="pfd-hint">可选填写，内部使用的货品售价</div>
          </el-form-item>

          <!-- 价格备注 -->
          <el-form-item label="价格备注">
            <el-input
              v-model="deviceForm.remark"
              type="textarea"
              :rows="3"
              placeholder="请输入定价理由或扣费说明"
              maxlength="200"
              show-word-limit
            />
          </el-form-item>

          <!-- 校验提示 -->
          <div
            v-if="!isFormValid && deviceForm.final_price !== undefined"
            class="pfd-alert pfd-alert--error"
          >
            ⚠️ 请输入有效的价格（大于等于0）
          </div>
          <div v-else-if="isFormValid" class="pfd-alert pfd-alert--success">
            ✅ 表单填写完整，可以提交定价
          </div>

        </el-form>
      </div>

    </div>

    <template #footer>
      <div :class="isMobile ? 'pfd-footer pfd-footer--mobile' : 'pfd-footer'">
        <el-button @click="handleCancel" :class="isMobile ? 'w-full !ml-0' : ''">取消</el-button>
        <el-button
          type="primary"
          @click="handleConfirm"
          :disabled="!isFormValid"
          :class="isMobile ? 'w-full !ml-0' : ''"
        >
          ✓ 确认定价
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch, computed, reactive, onMounted, onBeforeUnmount } from 'vue'
import { ElMessage } from 'element-plus'
import DeviceInfoCard from './DeviceInfoCard.vue'

interface DeviceInfo {
    id?: string | number;
    model?: string;
    imei?: string;
    capacity?: string;
    color?: string;
    system_version?: string;
    warranty_info?: string;
    before_price?: string | number;
    check_result?: string;
    check_result_seller?: string;
    check_result_buyer?: string;
    final_price?: string | number;
    sell_price?: string | number;
    remark?: string;
    status?: number;
    status_name?: string;
    info?: { sn?: string; [key: string]: any };
    [key: string]: any;
}

const props = defineProps({
    visible: { type: Boolean, default: false },
    device: { type: Object as () => DeviceInfo, default: () => ({}) }
})

const emit = defineEmits(['update:visible', 'confirm', 'cancel'])

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const isMobile = ref(false)

const deviceForm = reactive<{
    final_price: number | undefined;
    sell_price: number | undefined;
    remark: string;
}>({
    final_price: typeof props.device.final_price === 'number'
        ? props.device.final_price
        : typeof props.device.final_price === 'string'
            ? parseFloat(props.device.final_price) || undefined
            : undefined,
    sell_price: typeof props.device.sell_price === 'number'
        ? props.device.sell_price
        : typeof props.device.sell_price === 'string'
            ? parseFloat(props.device.sell_price) || undefined
            : undefined,
    remark: props.device.remark || ''
})

const isFormValid = computed(() =>
    typeof deviceForm.final_price === 'number' && deviceForm.final_price >= 0
)

const priceChangeClass = ref('')

const updateResponsiveState = () => { isMobile.value = window.innerWidth <= 768 }

const updatePriceClass = () => {
    const init = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)
    if (!deviceForm.final_price || !init) { priceChangeClass.value = ''; return }
    if (deviceForm.final_price > init) priceChangeClass.value = 'increase'
    else if (deviceForm.final_price < init) priceChangeClass.value = 'decrease'
    else priceChangeClass.value = ''
}

const getPriceChangeText = () => {
    const init = typeof deviceData.value.before_price === 'string'
        ? parseFloat(deviceData.value.before_price)
        : (deviceData.value.before_price || 0)
    if (!deviceForm.final_price || !init) return ''
    const diff = deviceForm.final_price - init
    if (diff > 0) return `↑ 上涨 ¥${diff.toFixed(2)}`
    if (diff < 0) return `↓ 下降 ¥${Math.abs(diff).toFixed(2)}`
    return '价格不变'
}

watch(() => props.visible, (v) => { dialogVisible.value = v })
watch(() => props.device, (newVal) => {
    deviceData.value = { ...newVal }
    deviceForm.final_price = typeof newVal.final_price === 'number' ? newVal.final_price
        : typeof newVal.final_price === 'string' ? parseFloat(newVal.final_price) || undefined : undefined
    deviceForm.sell_price = typeof newVal.sell_price === 'number' ? newVal.sell_price
        : typeof newVal.sell_price === 'string' ? parseFloat(newVal.sell_price) || undefined : undefined
    deviceForm.remark = newVal.remark || ''
    deviceData.value.status = 4
    updatePriceClass()
}, { deep: true })
watch(dialogVisible, (v) => { emit('update:visible', v) })

const handleCancel = () => { dialogVisible.value = false; emit('cancel') }
const handleConfirm = () => {
    if (!isFormValid.value) { ElMessage.warning('请输入有效的价格'); return }
    emit('confirm', {
        id: deviceData.value.id,
        final_price: deviceForm.final_price,
        sell_price: deviceForm.sell_price,
        status: deviceData.value.status,
        remark: deviceForm.remark
    })
}

onMounted(() => { updateResponsiveState(); window.addEventListener('resize', updateResponsiveState) })
onBeforeUnmount(() => { window.removeEventListener('resize', updateResponsiveState) })
</script>

<style lang="scss" scoped>
.price-form-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.18);
    overflow: hidden;
    max-width: 640px;
  }
  :deep(.el-dialog__header) { padding: 0; border: none; }
  :deep(.el-dialog__body) { padding: 0; background: #f1f5f9; }
  :deep(.el-dialog__footer) {
    padding: 0;
    border-top: 1px solid #e5e7eb;
    background: #f9fafb;
  }
  :deep(.el-dialog__headerbtn) {
    top: 14px; right: 14px;
    .el-dialog__close { color: #fff; background: rgba(255,255,255,0.2); border-radius: 4px; padding: 2px; }
  }
}

/* Header */
.pfd-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: linear-gradient(135deg, #ea580c 0%, #dc2626 100%);
  color: #fff;

  &__left { display: flex; align-items: center; gap: 12px; }
  &__icon { font-size: 28px; }
  &__title { font-size: 16px; font-weight: 700; margin: 0; }
  &__sub { font-size: 11px; color: rgba(255,255,255,0.75); margin: 2px 0 0; }
  &__id { background: rgba(255,255,255,0.15); border-color: rgba(255,255,255,0.3); color: #fff; }
}

/* Body */
.pfd-body {
  padding: 12px;
  display: flex;
  flex-direction: column;
  gap: 10px;
  max-height: 68vh;
  overflow-y: auto;

  &::-webkit-scrollbar { width: 5px; }
  &::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
}

/* 区块 header */
.pfd-block-header {
  display: flex;
  align-items: center;
  padding: 9px 12px;
  background: linear-gradient(to right, #f9fafb, #f3f4f6);
  border-bottom: 1px solid #e5e7eb;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
}

/* 质检结果展示 */
.pfd-check-result {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;


  &__content {
    padding: 10px 12px;
    font-size: 12px;
    color: #15803d;
    background: #f0fdf4;
    line-height: 1.6;
    white-space: pre-line;
  }
}

/* 定价表单 */
.pfd-form-card {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;

}

.pfd-form {
  padding: 12px 14px;

  :deep(.el-form-item__label) {
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    padding-bottom: 4px;
  }
}

/* 价格变化指示 */
.pfd-price-diff {
  margin-top: 6px;

  .pfd-price-diff__badge {
    display: inline-flex;
    align-items: center;
    font-size: 11px;
    font-weight: 500;
    padding: 2px 8px;
    border-radius: 4px;

    &.increase { background: #dcfce7; color: #15803d; }
    &.decrease { background: #fee2e2; color: #b91c1c; }
  }
}

.pfd-hint {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 4px;
}

/* 校验提示 */
.pfd-alert {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 10px;
  border-radius: 6px;
  font-size: 12px;
  margin-top: 4px;

  &--error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
  &--success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
}

/* Footer */
.pfd-footer {
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  padding: 12px 16px;

  &--mobile {
    flex-direction: column;
    gap: 8px;
  }
}
</style>
