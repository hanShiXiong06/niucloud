<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    width="960px"
    :destroy-on-close="true"
    class="check-device-dialog"
    align-center
  >
    <!-- 设备信息条 -->
    <div class="device-info-bar">
      <div class="device-basic">
        <div class="device-icon">📱</div>
        <div class="device-details">
          <h3 class="device-model">{{ deviceData.model || "未知型号" }}</h3>
          <div class="device-meta">
            <template v-if="!showImeiEdit">
              <span class="imei-display">IMEI: {{ deviceForm.imei || '未录入' }}</span>
            </template>
            <el-input
              v-else
              v-model="deviceForm.imei"
              placeholder="请输入15位IMEI或使用扫码枪"
              size="default"
              clearable
              maxlength="15"
              show-word-limit
              ref="imeiInputRef"
              @input="handleImeiInput"
              @blur="showImeiEdit = false"
            >
              <template #prefix>
                <el-icon><Postcard /></el-icon>
              </template>
              <template #append>
                <el-button @click="focusImeiInput">
                  <el-icon><Aim /></el-icon>
                  扫码
                </el-button>
              </template>
            </el-input>
          </div>
        </div>
      </div>
      <div class="quick-actions">
        <el-button v-if="!showImeiEdit" size="small" type="primary" @click="toggleImeiEdit">
          <el-icon><Edit /></el-icon>
          修改IMEI
        </el-button>
      </div>
    </div>

    <!-- 智能质检面板 -->
    <div class="smart-check-panel">
      <div class="panel-header">
        <span>🔍 智能质检</span>
        <div class="header-actions">
          <el-button
            size="small"
            text
            :loading="loadingCoverage"
            :disabled="!deviceForm.imei"
            @click="fetchCoverage"
          >
            <el-icon v-if="!loadingCoverage"><Headset /></el-icon>
            {{ loadingCoverage ? '查询中...' : '查询保修' }}
          </el-button>
          <el-button
            size="small"
            text
            :loading="loadingActivationLock"
            :disabled="!deviceForm.imei"
            @click="fetchActivationlock"
          >
            <el-icon v-if="!loadingActivationLock"><Lock /></el-icon>
            {{ loadingActivationLock ? '查询中...' : '查询激活锁' }}
          </el-button>
          <el-button
            size="small"
            text
            :loading="loadingMdm"
            :disabled="!deviceForm.imei"
            @click="fetchMdm"
          >
            <el-icon v-if="!loadingMdm"><Monitor /></el-icon>
            {{ loadingMdm ? '查询中...' : '查询监管锁' }}
          </el-button>
          <span class="divider">|</span>
          <el-button size="small" text @click="clearAllSelections">清空</el-button>
          <el-button size="small" text @click="fillCommonResult">常用模板</el-button>
        </div>
      </div>

      <!-- 保修信息显示区域 -->
      <el-collapse-transition>
        <div v-if="warrantyInfo" class="warranty-display-panel">
          <div class="warranty-header">
            <span>📱 设备保修信息</span>
            <el-button size="small" text @click="clearWarrantyInfo">
              <el-icon><Close /></el-icon>
              清除
            </el-button>
          </div>
          <WarrantyInfoDisplay :warrantyData="warrantyInfo" />
        </div>
      </el-collapse-transition>

      <!-- 快速质检选项 - 卡片式布局 -->
      <div class="check-grid">
        <!-- 电池状态卡片 -->
        <CheckCard title="电池状态" icon="Lightning">
          <div class="input-row">
            <span class="label">健康度</span>
            <el-input-number
              v-model="templateSelections.battery"
              :min="0"
              :max="100"
              :step="1"
              size="small"
              @change="updateCheckResult"
            />
            <span class="unit">%</span>
          </div>
          <div class="input-row">
            <span class="label">循环</span>
            <el-input
              v-model="templateSelections.battery_num"
              type="number"
              size="small"
              @change="updateCheckResult"
            />
            <span class="unit">次</span>
          </div>
          <div class="input-row">
            <span class="label">激活锁</span>
            <el-switch
              v-model="templateSelections.activationLock"
              active-text="on"
              inactive-text="off"
              size="small"
              style="--el-switch-on-color: #ff4949; --el-switch-off-color: #13ce66"
              @change="updateCheckResult"
            />
          </div>
          <div class="input-row">
            <span class="label">监管锁</span>
            <el-switch
              v-model="templateSelections.mdmLock"
              active-text="on"
              inactive-text="off"
              size="small"
              style="--el-switch-on-color: #ff4949; --el-switch-off-color: #13ce66"
              @change="updateCheckResult"
            />
          </div>
        </CheckCard>

        <!-- 屏幕状态卡片 -->
        <CheckCard title="屏幕状态" icon="Monitor">
          <div class="tag-grid">
            <el-tag
              v-for="option in dictOptions.screenLabels()"
              :key="option"
              :type="templateSelections.screen === option ? 'primary' : undefined"
              :effect="templateSelections.screen === option ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="selectScreenOption(option)"
            >
              {{ option }}
            </el-tag>
          </div>
        </CheckCard>

        <!-- 外观状态卡片 -->
        <CheckCard title="外观状态" icon="Picture">
          <div class="tag-grid">
            <el-tag
              v-for="option in dictOptions.appearanceLabels()"
              :key="option"
              :type="templateSelections.appearance === option ? 'primary' : undefined"
              :effect="templateSelections.appearance === option ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="selectAppearanceOption(option)"
            >
              {{ option }}
            </el-tag>
          </div>
        </CheckCard>

        <!-- 功能异常卡片 -->
        <CheckCard title="功能异常" icon="Setting">
          <div class="tag-grid">
            <el-tag
              v-for="option in dictOptions.functionLabels()"
              :key="option"
              :type="templateSelections.function.includes(option) ? 'danger' : undefined"
              :effect="templateSelections.function.includes(option) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="toggleFunctionOption(option)"
            >
              {{ option }}
            </el-tag>
          </div>
        </CheckCard>
      </div>
    </div>

    <!-- 核心信息表单 -->
    <el-form
      ref="formRef"
      :model="deviceForm"
      :rules="rules"
      label-position="top"
      class="core-form"
    >
      <div class="form-row">
        <el-form-item label="📋 质检结果" prop="check_result">
          <el-input
            v-model="deviceForm.check_result"
            type="textarea"
            :rows="4"
            placeholder="详细描述设备状态，或使用上方快速选择..."
            maxlength="100"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="📋 扣费说明" prop="remark">
          <el-input
            v-model="deviceForm.remark"
            type="textarea"
            :rows="4"
            placeholder="扣费说明、特殊情况备注等..."
            maxlength="200"
            show-word-limit
          />
        </el-form-item>
        <el-form-item label="💰 最终价格" prop="final_price" class="price-item">
          <el-input-number
            v-model="deviceForm.final_price"
            :step="10"
            :precision="2"
            :min="0"
            :max="99999"
            placeholder="定价"
            class="price-input"
          />
        </el-form-item>
      </div>

      <el-form-item label="📸 质检图片">
        <div class="upload-wrapper">
          <upload-image v-model="deviceForm.check_images" :limit="6" />
          <div v-if="qrCode" class="qr-quick-scan">
            <img :src="qrCode" class="qr-mini" alt="扫码上传" />
            <span>手机扫码上传</span>
          </div>
        </div>
      </el-form-item>
    </el-form>

    <!-- 底部操作栏 -->
    <template #footer>
      <div class="action-bar">
        <div class="action-info">
          <span class="check-count">已检测项目: {{ checkedCount }}</span>
        </div>
        <div class="action-buttons">
          <el-button size="large" @click="handleCancel">取消</el-button>
          <el-button
            type="warning"
            size="large"
            :loading="savingDraft"
            @click="handleSaveDraft"
          >
            {{ savingDraft ? '暂存中...' : '暂存质检' }}
          </el-button>
          <el-button
            type="primary"
            size="large"
            :loading="submitting"
            @click="handleConfirm"
          >
            <el-icon v-if="!submitting"><Check /></el-icon>
            {{ submitting ? '提交中...' : '完成质检' }}
          </el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed, onMounted } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import {
  Edit, Postcard, Aim, Monitor, Check, Headset, Close, Lock
} from '@element-plus/icons-vue'
import QRCode from 'qrcode'

// API
import { getCoverage, getActivationlock, getMdm } from '@/addon/recycle/api/device_query_api'

// Composables
import { useCheckDeviceDict } from '@/addon/recycle/hooks/useCheckDeviceDict'

// Components
import WarrantyInfoDisplay from '@/addon/recycle/components/WarrantyInfoDisplay.vue'
import CheckCard from './CheckCard.vue'

// ==================== 类型定义 ====================
interface DeviceInfo {
  id?: string | number
  model?: string
  imei?: string
  initial_price?: string | number
  final_price?: string | number
  check_result?: string
  check_images?: string
  check_status?: number
  remark?: string
  status?: number
  info?: any
  [key: string]: any
}

// ==================== Props & Emits ====================
const props = defineProps<{
  visible: boolean
  device: DeviceInfo
}>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'confirm': [data: any]
  'cancel': []
  'save-draft': [data: any]
}>()

// ==================== 字典数据 ====================
const dictOptions = useCheckDeviceDict()

// ==================== 状态管理 ====================
const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const showImeiEdit = ref(false)
const qrCode = ref('')

// 查询状态
const loadingCoverage = ref(false)
const loadingActivationLock = ref(false)
const loadingMdm = ref(false)

// 查询结果
const warrantyInfo = ref<any>(null)
const activationLockInfo = ref<any>(null)
const mdmInfo = ref<any>(null)

// 原始质检结果（用于拼接）
const originalCheckResult = ref(props.device.check_result || '')

// ==================== 表单数据 ====================
const deviceForm = reactive({
  check_result: props.device.check_result || '',
  check_images: props.device.check_images || '',
  final_price: parsePrice(props.device.final_price),
  remark: props.device.remark || '',
  imei: props.device.imei || '',
  info: undefined as any
})

// 模板选择状态
const templateSelections = reactive({
  battery: undefined as number | undefined,
  battery_num: undefined as number | undefined,
  screen: '',
  appearance: '',
  function: [] as string[],
  activationLock: false,
  mdmLock: false
})

// 表单验证规则
const rules = reactive<FormRules>({
  check_result: [
    { required: true, message: '请输入质检结果', trigger: 'blur' },
    { min: 5, message: '质检结果至少5个字符', trigger: 'blur' }
  ]
})

// ==================== 计算属性 ====================
const checkedCount = computed(() => {
  let count = 0
  if (templateSelections.battery) count++
  if (templateSelections.battery_num) count++
  if (templateSelections.screen) count++
  if (templateSelections.appearance) count++
  count += templateSelections.function.length
  return count
})

// ==================== 工具函数 ====================
function parsePrice(value: any): number | undefined {
  if (typeof value === 'number') return value
  if (typeof value === 'string') return parseFloat(value) || undefined
  return undefined
}

// ==================== 二维码生成 ====================
const generateQrCode = async () => {
  qrCode.value = await QRCode.toDataURL(
    window.location.origin + '/site/diy/attachment',
    { errorCorrectionLevel: 'L', margin: 0, width: 100 }
  )
}

// ==================== IMEI 相关 ====================
const toggleImeiEdit = () => {
  showImeiEdit.value = !showImeiEdit.value
  if (showImeiEdit.value) {
    setTimeout(() => imeiInputRef.value?.focus(), 100)
  }
}

const handleImeiInput = (value: string) => {
  deviceForm.imei = value.replace(/[^0-9]/g, '')
}

const focusImeiInput = () => {
  imeiInputRef.value?.focus()
}

// ==================== 质检选项操作 ====================
const selectScreenOption = (option: string) => {
  templateSelections.screen = templateSelections.screen === option ? '' : option
  updateCheckResult()
}

const selectAppearanceOption = (option: string) => {
  templateSelections.appearance = templateSelections.appearance === option ? '' : option
  updateCheckResult()
}

const toggleFunctionOption = (option: string) => {
  const index = templateSelections.function.indexOf(option)
  if (index > -1) {
    templateSelections.function.splice(index, 1)
  } else {
    templateSelections.function.push(option)
  }
  updateCheckResult()
}

const updateCheckResult = () => {
  const results: string[] = []

  if (templateSelections.battery) {
    results.push(`电池健康度${templateSelections.battery}%`)
  }
  if (templateSelections.battery_num) {
    results.push(`循环${templateSelections.battery_num}次`)
  }
  if (templateSelections.activationLock) {
    results.push('激活锁开启')
  }
  if (templateSelections.mdmLock) {
    results.push('监管锁开启')
  }
  if (templateSelections.screen) {
    results.push(`屏幕${templateSelections.screen}`)
  }
  if (templateSelections.appearance) {
    results.push(`外观${templateSelections.appearance}`)
  }
  if (templateSelections.function.length > 0) {
    results.push(`功能异常: ${templateSelections.function.join('、')}`)
  }

  const templateResult = results.join('; ')
  if (originalCheckResult.value && templateResult) {
    deviceForm.check_result = originalCheckResult.value + '; ' + templateResult
  } else if (templateResult) {
    deviceForm.check_result = templateResult
  }
}

const clearAllSelections = () => {
  templateSelections.battery = undefined
  templateSelections.battery_num = undefined
  templateSelections.activationLock = false
  templateSelections.mdmLock = false
  templateSelections.screen = ''
  templateSelections.appearance = ''
  templateSelections.function = []
  deviceForm.check_result = ''
}

const fillCommonResult = () => {
  templateSelections.battery = 85
  templateSelections.screen = '完好'
  templateSelections.appearance = '轻微磨损'
  updateCheckResult()
}

// ==================== 查询功能 ====================
const fetchCoverage = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码')
    return
  }

  loadingCoverage.value = true
  try {
    const brand = dictOptions.extractBrand(deviceData.value.model || '')
    const res = await getCoverage({ imei: deviceForm.imei, brand })

    if (res.data?.model) {
      warrantyInfo.value = res.data
      deviceData.value.model = `${res.data.model} ${res.data.capacity} ${res.data.color}`
      deviceForm.info = res.data
      ElMessage.success('保修信息查询成功')
    } else if (res.data?.msg) {
      ElMessage.error('保修查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到保修信息')
    }
  } catch (error) {
    console.error('保修查询失败:', error)
    ElMessage.error('保修查询失败，请稍后重试')
  } finally {
    loadingCoverage.value = false
  }
}

const clearWarrantyInfo = () => {
  warrantyInfo.value = null
}

const fetchActivationlock = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码')
    return
  }

  loadingActivationLock.value = true
  try {
    const res = await getActivationlock(deviceForm.imei)

    if (res.data?.sn) {
      activationLockInfo.value = res.data
      templateSelections.activationLock = res.data.locked === true || res.data.fmi === 'On'
      updateCheckResult()
      ElMessage.success('激活锁信息查询成功')
    } else if (res.data?.msg) {
      ElMessage.error('激活锁查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到激活锁信息')
    }
  } catch (error) {
    console.error('激活锁查询失败:', error)
    ElMessage.error('激活锁查询失败，请稍后重试')
  } finally {
    loadingActivationLock.value = false
  }
}

const fetchMdm = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码')
    return
  }

  loadingMdm.value = true
  try {
    const res = await getMdm(deviceForm.imei)

    if (res.data?.sn) {
      mdmInfo.value = res.data
      templateSelections.mdmLock = res.data.locked === true || res.data.mdm === 'On' || res.data.mdm === true
      updateCheckResult()
      ElMessage.success('监管锁信息查询成功')
    } else if (res.data?.msg) {
      ElMessage.error('监管锁查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到监管锁信息')
    }
  } catch (error) {
    console.error('监管锁查询失败:', error)
    ElMessage.error('监管锁查询失败，请稍后重试')
  } finally {
    loadingMdm.value = false
  }
}

// ==================== 表单操作 ====================
const handleCancel = () => {
  dialogVisible.value = false
  emit('cancel')
}

const handleConfirm = async () => {
  if (!formRef.value) return

  await formRef.value.validate(async (valid) => {
    if (!valid) return

    submitting.value = true
    try {
      emit('confirm', {
        id: deviceData.value.id,
        check_result: deviceForm.check_result,
        check_images: deviceForm.check_images,
        remark: deviceForm.remark,
        check_status: 1,
        final_price: deviceForm.final_price,
        action: 'check',
        imei: deviceForm.imei,
        model: deviceData.value.model,
        info: deviceForm.info
      })
      dialogVisible.value = false
    } finally {
      submitting.value = false
    }
  })
}

const handleSaveDraft = async () => {
  savingDraft.value = true
  try {
    emit('save-draft', {
      id: deviceData.value.id,
      check_result: deviceForm.check_result,
      check_images: deviceForm.check_images,
      remark: deviceForm.remark,
      final_price: deviceForm.final_price,
      imei: deviceForm.imei,
      model: deviceData.value.model,
      info: deviceForm.info,
      action: 'save_draft'
    })
    dialogVisible.value = false
  } finally {
    savingDraft.value = false
  }
}

// ==================== 监听器 ====================
watch(() => props.visible, (val) => {
  dialogVisible.value = val
})

watch(() => props.device, (val) => {
  deviceData.value = { ...val }
  deviceForm.check_result = val.check_result || ''
  deviceForm.check_images = val.check_images || ''
  deviceForm.final_price = parsePrice(val.final_price)
  deviceForm.remark = val.remark || ''
  deviceForm.imei = val.imei || ''
  originalCheckResult.value = val.check_result || ''
}, { deep: true })

watch(dialogVisible, (val) => {
  emit('update:visible', val)
})

// ==================== 生命周期 ====================
onMounted(() => {
  generateQrCode()
  dictOptions.loadDictionary()
})
</script>

<style lang="scss" scoped>
.check-device-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }

  :deep(.el-dialog__header) {
    padding: 20px 24px 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border: none;

    .el-dialog__title {
      font-size: 18px;
      font-weight: 600;
      color: white;
    }
  }

  :deep(.el-dialog__body) {
    padding: 20px 24px;
    background-color: #fafbfc;
    max-height: 70vh;
    overflow-y: auto;
  }

  :deep(.el-dialog__footer) {
    padding: 16px 24px;
    background-color: white;
    border-top: 1px solid #e5e7eb;
  }
}

// 设备信息条
.device-info-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;

  .device-basic {
    display: flex;
    align-items: center;
    gap: 12px;

    .device-icon {
      font-size: 24px;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f3f4f6;
      border-radius: 8px;
    }

    .device-details {
      .device-model {
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #111827;
      }

      .device-meta {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 13px;
        color: #6b7280;
      }
    }
  }
}

// 智能质检面板
.smart-check-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin-bottom: 16px;

  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    color: #111827;

    .header-actions {
      display: flex;
      align-items: center;
      gap: 8px;

      .divider {
        color: #d1d5db;
        margin: 0 4px;
      }
    }
  }

  .warranty-display-panel {
    margin: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;

    .warranty-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
      border-bottom: 1px solid #e5e7eb;
      font-weight: 600;
      color: #374151;
    }
  }

  .check-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 2fr;
    gap: 12px;
    padding: 16px;
  }
}

// 标签网格
.tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;

  .check-tag {
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;

    &:hover {
      transform: translateY(-1px);
    }
  }
}

// 输入行
.input-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;

  &:last-child {
    margin-bottom: 0;
  }

  .label {
    width: 60px;
    font-size: 12px;
    color: #6b7280;
    flex-shrink: 0;
  }

  .unit {
    font-size: 12px;
    color: #9ca3af;
  }
}

// 核心表单
.core-form {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 20px;

  .form-row {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 24px;
  }

  .price-item {
    .price-input {
      width: 100%;
    }
  }

  .upload-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 16px;

    .qr-quick-scan {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;

      .qr-mini {
        width: 50px;
        height: 50px;
        border-radius: 4px;
        border: 1px solid #e5e7eb;
      }

      span {
        font-size: 12px;
        color: #6b7280;
      }
    }
  }
}

// 底部操作栏
.action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;

  .action-info {
    .check-count {
      font-size: 13px;
      color: #6b7280;
      background: #f3f4f6;
      padding: 4px 8px;
      border-radius: 4px;
    }
  }

  .action-buttons {
    display: flex;
    gap: 12px;
  }
}

// 响应式
@media (max-width: 768px) {
  .check-device-dialog {
    :deep(.el-dialog) {
      width: 95vw !important;
    }
  }

  .smart-check-panel .check-grid {
    grid-template-columns: 1fr 1fr;
  }

  .core-form .form-row {
    grid-template-columns: 1fr;
  }

  .action-bar {
    flex-direction: column;
    gap: 12px;

    .action-buttons {
      width: 100%;

      .el-button {
        flex: 1;
      }
    }
  }
}
</style>
