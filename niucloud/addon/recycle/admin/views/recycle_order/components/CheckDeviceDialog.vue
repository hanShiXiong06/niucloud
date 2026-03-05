<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    width="960px"
    :destroy-on-close="true"
    class="check-device-dialog"
    align-center
  >
    <!-- ==================== 设备信息条 ==================== -->
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

    <!-- ==================== 智能质检面板 ==================== -->
    <div class="smart-check-panel">
      <div class="panel-header flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <span class="text-[15px] font-semibold text-gray-900">🔍 智能质检</span>
        <div class="header-actions flex w-full flex-wrap gap-2 md:w-auto md:justify-end">
          <el-button
            class="action-btn !m-0"
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
            class="action-btn !m-0"
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
            class="action-btn !m-0"
            size="small"
            text
            :loading="loadingMdm"
            :disabled="!deviceForm.imei"
            @click="fetchMdm"
          >
            <el-icon v-if="!loadingMdm"><Monitor /></el-icon>
            {{ loadingMdm ? '查询中...' : '查询监管锁' }}
          </el-button>
          <span class="divider hidden md:inline-block">|</span>
          <el-button class="action-btn !m-0" size="small" text @click="clearAllSelections">清空</el-button>
          <el-button class="action-btn !m-0" size="small" text @click="fillCommonResult">常用模板</el-button>
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

      <!-- ==================== 质检选项区 - 紧凑网格 ==================== -->
      <div class="check-grid">
        <!-- 第一行: 电池(3col) + 外屏(3col) + 内屏(3col) + 中框(3col) -->
        <CheckCard class="grid-cell grid-cell--battery" title="电池状态" icon="Lightning">
          <div class="input-row">
            <span class="label">健康度</span>
            <el-input-number
              v-model="templateSelections.battery"
              :min="0"
              :max="100"
              :step="1"
              size="small"
              controls-position="right"
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

        <CheckCard class="grid-cell grid-cell--screen" title="外屏规格" icon="Monitor">
          <div class="tag-grid">
            <el-tag
              v-for="option in checkDictOptions.screen"
              :key="option.value"
              :type="templateSelections.screenId === String(option.value) ? 'primary' : undefined"
              :effect="templateSelections.screenId === String(option.value) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="selectScreenOption(option.value)"
            >
              {{ option.name }}
            </el-tag>
          </div>
        </CheckCard>

        <CheckCard class="grid-cell grid-cell--indisplay" title="内屏规格" icon="Iphone">
          <div class="tag-grid">
            <el-tag
              v-for="option in checkDictOptions.indisplay"
              :key="option.value"
              :type="templateSelections.indisplayId === String(option.value) ? 'primary' : undefined"
              :effect="templateSelections.indisplayId === String(option.value) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="selectIndisplayOption(option.value)"
            >
              {{ option.name }}
            </el-tag>
          </div>
        </CheckCard>

        <CheckCard class="grid-cell grid-cell--appearance" title="中框规格" icon="Picture">
          <div class="tag-grid">
            <el-tag
              v-for="option in checkDictOptions.appearance"
              :key="option.value"
              :type="templateSelections.appearanceId === String(option.value) ? 'primary' : undefined"
              :effect="templateSelections.appearanceId === String(option.value) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="selectAppearanceOption(option.value)"
            >
              {{ option.name }}
            </el-tag>
          </div>
        </CheckCard>

        <!-- 第二行: 功能规格(8col) + 维修规格(4col) -->
        <CheckCard class="grid-cell grid-cell--function" title="功能规格" icon="Setting">
          <div class="tag-grid">
            <el-tag
              v-for="option in checkDictOptions.function"
              :key="option.value"
              :type="templateSelections.functionIds.includes(String(option.value)) ? 'danger' : undefined"
              :effect="templateSelections.functionIds.includes(String(option.value)) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="toggleFunctionOption(option.value)"
            >
              {{ option.name }}
            </el-tag>
          </div>
        </CheckCard>

        <CheckCard class="grid-cell grid-cell--fix" title="维修规格" icon="Tools">
          <div class="tag-grid">
            <el-tag
              v-for="option in checkDictOptions.fix"
              :key="option.value"
              :type="templateSelections.fixIds.includes(String(option.value)) ? 'warning' : undefined"
              :effect="templateSelections.fixIds.includes(String(option.value)) ? 'dark' : 'plain'"
              size="small"
              class="check-tag"
              @click="toggleFixOption(option.value)"
            >
              {{ option.name }}
            </el-tag>
          </div>
        </CheckCard>
      </div>
    </div>

    <!-- ==================== 核心信息表单 ==================== -->
    <el-form
      ref="formRef"
      :model="deviceForm"
      :rules="rules"
      label-position="top"
      class="core-form"
    >
      <!-- ==================== 质检结果区 ==================== -->
      <div class="result-cards">
        <!-- 卖家质检结果 -->
        <div class="result-card result-card--seller">
          <div class="result-card__header">
            <div class="result-card__title">
              <span class="result-card__dot"></span>
              卖家可见质检结果
            </div>
          </div>
          <el-form-item prop="check_result_seller" class="result-card__body">
            <el-input
              v-model="deviceForm.check_result_seller"
              type="textarea"
              :rows="6"
              placeholder="由上方质检选项自动生成，也可手动编辑..."
              maxlength="500"
              show-word-limit
              resize="none"
            />
          </el-form-item>
        </div>

        <!-- 买家质检结果 -->
        <div class="result-card result-card--buyer">
          <div class="result-card__header">
            <div class="result-card__title">
              <span class="result-card__dot"></span>
              买家可见质检结果
              <el-tooltip content="买家质检结果可单独编辑，不影响卖家结果" placement="top">
                <el-icon class="text-gray-400" style="vertical-align: middle; cursor: help; margin-left: 2px;"><Warning /></el-icon>
              </el-tooltip>
            </div>
            <el-button type="primary" link size="small" @click="syncSellerResultToBuyer">
              <el-icon><CopyDocument /></el-icon>
              同步卖家
            </el-button>
          </div>
          <el-form-item prop="check_result_buyer" class="result-card__body">
            <el-input
              v-model="deviceForm.check_result_buyer"
              type="textarea"
              :rows="6"
              placeholder="买家可见展示文案，可点击「同步卖家」快速填充..."
              maxlength="500"
              show-word-limit
              resize="none"
            />
          </el-form-item>
        </div>

        <!-- 标签打印内容 -->
        <div class="result-card result-card--label">
          <div class="result-card__header">
            <div class="result-card__title">
              <span class="result-card__dot"></span>
              标签打印内容
            </div>
            <el-button size="small" @click="generateLabel" class="label-gen-btn">
              <el-icon style="margin-right: 2px;"><Printer /></el-icon>
              生成标签
            </el-button>
          </div>
          <el-form-item prop="check_result" class="result-card__body">
            <el-input
              v-model="deviceForm.check_result"
              type="textarea"
              :rows="6"
              placeholder="查询保修后自动生成（型号 / 内存 / 保修 / 系统），也可手动编辑..."
              maxlength="300"
              show-word-limit
              resize="none"
            />
          </el-form-item>
        </div>
      </div>

      <!-- ==================== 定价与备注区 ==================== -->
      <div class="pricing-bar">
       
        <el-form-item label="最终价格" prop="final_price" class="pricing-bar__price">
          <el-input-number
            v-model="deviceForm.final_price"
            :step="10"
            :precision="2"
            :min="0"
            :max="99999"
            placeholder="定价"
            controls-position="right"
          />
        </el-form-item>
        <el-form-item label="卖货价格" prop="sell_price" class="pricing-bar__price">
          <el-input-number
            v-model="deviceForm.sell_price"
            :step="10"
            :precision="2"
            :min="0"
            :max="99999"
            placeholder="卖货价"
            controls-position="right"
          />
        </el-form-item>
         <el-form-item label="扣费说明" prop="remark" class="pricing-bar__remark">
          <el-input
            v-model="deviceForm.remark"
            placeholder="扣费说明、特殊备注..."
            maxlength="200"
            clearable
          />
        </el-form-item>
      </div>

      <!-- 质检图片区 -->
      <div class="form-section">
        <div class="form-section__header">📸 质检图片</div>
        <div class="form-row form-row--2col">
          <el-form-item label="卖家可见质检图片">
            <div class="upload-wrapper">
              <div class="upload-main">
                <div v-if="isMobile" class="mobile-camera-actions">
                  <el-button
                    type="primary"
                    plain
                    :loading="cameraUploading"
                    :disabled="cameraUploading || checkImageCount >= maxCheckImageCount"
                    @click="openCameraCapture"
                  >
                    {{ cameraUploading ? '上传中...' : '拍照上传' }}
                  </el-button>
                  <span class="camera-tip">
                    {{ `已上传 ${checkImageCount}/${maxCheckImageCount}` }}
                  </span>
                  <input
                    ref="cameraInputRef"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    class="hidden"
                    @change="handleCameraFilesChange"
                  />
                </div>
                <upload-image v-model="deviceForm.check_images" :limit="9" />
              </div>
            </div>
          </el-form-item>

          <el-form-item>
            <template #label>
              <div class="form-label-with-action">
                <span>买家可见质检图片</span>
                <el-button
                  type="primary"
                  link
                  size="small"
                  @click="syncSellerImagesToBuyer"
                >
                  <el-icon><CopyDocument /></el-icon>
                  同步卖家图片
                </el-button>
              </div>
            </template>
            <div class="upload-wrapper">
              <div class="upload-main">
                <div v-if="isMobile" class="mobile-camera-actions">
                  <el-button
                    type="primary"
                    plain
                    :loading="buyerCameraUploading"
                    :disabled="buyerCameraUploading || buyerCheckImageCount >= buyerMaxCheckImageCount"
                    @click="openBuyerCameraCapture"
                  >
                    {{ buyerCameraUploading ? '上传中...' : '拍照上传（买家）' }}
                  </el-button>
                  <span class="camera-tip">
                    {{ `已上传 ${buyerCheckImageCount}/${buyerMaxCheckImageCount}` }}
                  </span>
                  <input
                    ref="buyerCameraInputRef"
                    type="file"
                    accept="image/*"
                    capture="environment"
                    multiple
                    class="hidden"
                    @change="handleBuyerCameraFilesChange"
                  />
                </div>
                <upload-image v-model="deviceForm.check_images_buyer" :limit="6" />
              </div>
            </div>
          </el-form-item>
        </div>
      </div>
    </el-form>

    <!-- ==================== 底部操作栏 ==================== -->
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
import { ref, reactive, watch, computed, nextTick, onMounted, onBeforeUnmount, toRef } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import {
  Edit, Postcard, Aim, Monitor, Check, Headset, Close, Lock, CopyDocument, Warning, Printer
} from '@element-plus/icons-vue'
import QRCode from 'qrcode'

// API
import { getCoverage, getActivationlock, getMdm } from '@/addon/recycle/api/device_query_api'

// Composables
import { useCheckDeviceDict } from '@/addon/recycle/hooks/useCheckDeviceDict'
import {
  normalizeInfo,
  useCheckMeta,
  type CheckMetaPayload,
  type CheckOptionsGroup
} from './composables/useCheckMeta'
import { useCameraUpload } from './composables/useCameraUpload'

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
  sell_price?: string | number
  check_result?: string
  check_result_seller?: string
  check_result_buyer?: string
  check_meta?: CheckMetaPayload | string | null
  check_images?: string
  check_images_seller?: string
  check_images_buyer?: string
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
const checkDictOptions = computed<CheckOptionsGroup>(() => dictOptions.options.value as CheckOptionsGroup)

// ==================== 状态管理 ====================
const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const showImeiEdit = ref(false)
const qrCode = ref('')
const isMobile = ref(false)

// 查询状态
const loadingCoverage = ref(false)
const loadingActivationLock = ref(false)
const loadingMdm = ref(false)

// 查询结果
const warrantyInfo = ref<any>(null)
const activationLockInfo = ref<any>(null)
const mdmInfo = ref<any>(null)

// ==================== 表单数据 ====================
const deviceForm = reactive({
  check_result: '',
  check_result_seller: props.device.check_result_seller || props.device.check_result || '',
  check_result_buyer: props.device.check_result_buyer || '',
  check_images: props.device.check_images_seller || props.device.check_images || '',
  check_images_buyer: props.device.check_images_buyer || '',
  final_price: parsePrice(props.device.final_price),
  sell_price: parsePrice(props.device.sell_price),
  remark: props.device.remark || '',
  imei: props.device.imei || '',
  info: normalizeInfo(props.device.info)
})

// 表单验证规则
const rules = reactive<FormRules>({
  check_result_seller: [
    { required: true, message: '请输入质检结果', trigger: 'blur' },
    { min: 5, message: '质检结果至少5个字符', trigger: 'blur' }
  ]
})

const {
  templateSelections,
  checkedCount,
  getSubmitInfo,
  updateCheckResult,
  buildLabelText,
  clearAllSelections,
  fillCommonResult,
  restoreFromDevice,
  selectScreenOption,
  selectIndisplayOption,
  selectAppearanceOption,
  toggleFunctionOption,
  toggleFixOption
} = useCheckMeta({
  dictOptions: checkDictOptions,
  deviceForm
})

const {
  cameraUploading,
  cameraInputRef,
  maxCheckImageCount,
  checkImageCount,
  openCameraCapture,
  handleCameraFilesChange
} = useCameraUpload({
  checkImages: toRef(deviceForm, 'check_images')
})

const {
  cameraUploading: buyerCameraUploading,
  cameraInputRef: buyerCameraInputRef,
  maxCheckImageCount: buyerMaxCheckImageCount,
  checkImageCount: buyerCheckImageCount,
  openCameraCapture: openBuyerCameraCapture,
  handleCameraFilesChange: handleBuyerCameraFilesChange
} = useCameraUpload({
  checkImages: toRef(deviceForm, 'check_images_buyer')
})

// ==================== 工具函数 ====================
function parsePrice(value: any): number | undefined {
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const parsed = parseFloat(value)
    return Number.isNaN(parsed) ? undefined : parsed
  }
  return undefined
}

const updateDeviceMode = () => {
  isMobile.value = window.innerWidth <= 768
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
      deviceForm.info = {
        ...normalizeInfo(deviceForm.info),
        ...res.data
      }
      deviceForm.info = getSubmitInfo()

      // 将保修状态写入质检结果
      refreshAfterWarranty()

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

// 保修查询后：保修数据已写入 info，由 updateCheckResult 统一生成卖家文本
const refreshAfterWarranty = () => {
  updateCheckResult()
  deviceForm.check_result = buildLabelText()
}

// 手动生成标签
const generateLabel = () => {
  deviceForm.check_result = buildLabelText()
  if (!deviceForm.check_result) {
    ElMessage.warning('暂无设备信息可生成标签，请先查询保修')
  }
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

// ==================== 同步卖家信息到买家 ====================
const syncSellerResultToBuyer = () => {
  if (!deviceForm.check_result_seller) {
    ElMessage.warning('卖家质检结果为空，无法同步')
    return
  }
  deviceForm.check_result_buyer = deviceForm.check_result_seller
  ElMessage.success('已同步卖家质检结果到买家，您仍可单独编辑买家内容')
}

const syncSellerImagesToBuyer = () => {
  if (!deviceForm.check_images) {
    ElMessage.warning('卖家质检图片为空，无法同步')
    return
  }
  deviceForm.check_images_buyer = deviceForm.check_images
  ElMessage.success('已同步卖家质检图片到买家，您仍可单独编辑买家图片')
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
      emit('confirm', buildSubmitPayload('check'))
      dialogVisible.value = false
    } finally {
      submitting.value = false
    }
  })
}

const handleSaveDraft = async () => {
  savingDraft.value = true
  try {
    emit('save-draft', buildSubmitPayload('save_draft'))
    dialogVisible.value = false
  } finally {
    savingDraft.value = false
  }
}

function buildSubmitPayload(action: 'check' | 'save_draft') {
  const sellerImages = deviceForm.check_images || ''
  return {
    id: deviceData.value.id,
    check_result: deviceForm.check_result,
    check_result_seller: deviceForm.check_result_seller,
    check_result_buyer: deviceForm.check_result_buyer,
    check_images: sellerImages,
    check_images_seller: sellerImages,
    check_images_buyer: deviceForm.check_images_buyer,
    remark: deviceForm.remark,
    check_status: action === 'check' ? 1 : undefined,
    final_price: deviceForm.final_price,
    sell_price: deviceForm.sell_price,
    action,
    imei: deviceForm.imei,
    model: deviceData.value.model,
    info: getSubmitInfo()
  }
}

const initializeFormFromDevice = (device: DeviceInfo) => {
  // 清空上一台设备的所有残留状态
  warrantyInfo.value = null
  activationLockInfo.value = null
  mdmInfo.value = null
  showImeiEdit.value = false
  loadingCoverage.value = false
  loadingActivationLock.value = false
  loadingMdm.value = false
  clearAllSelections()

  // 填充新设备数据
  deviceData.value = { ...device }
  deviceForm.check_result_seller = device.check_result_seller || device.check_result || ''
  deviceForm.check_result = ''  // 稍后由 buildLabelText() 生成
  deviceForm.check_result_buyer = device.check_result_buyer || ''
  deviceForm.check_images = device.check_images_seller || device.check_images || ''
  deviceForm.check_images_buyer = device.check_images_buyer || ''
  deviceForm.final_price = parsePrice(device.final_price)
  deviceForm.sell_price = parsePrice(device.sell_price)
  deviceForm.remark = device.remark || ''
  deviceForm.imei = device.imei || ''
  deviceForm.info = normalizeInfo(device.info)

  // 从新设备的 check_meta 恢复质检选项
  restoreFromDevice(device)

  // 从 info 生成标签打印内容
  deviceForm.check_result = buildLabelText()

  // 重置表单校验状态
  nextTick(() => {
    formRef.value?.clearValidate()
  })
}

// ==================== 监听器 ====================
watch(() => props.visible, (val) => {
  dialogVisible.value = val
})

watch(() => props.device, (val) => {
  initializeFormFromDevice(val)
}, { deep: true })

watch(dialogVisible, (val) => {
  emit('update:visible', val)
})

watch(
  () => [
    checkDictOptions.value.screen,
    checkDictOptions.value.indisplay,
    checkDictOptions.value.appearance,
    checkDictOptions.value.function,
    checkDictOptions.value.fix
  ],
  () => {
    if (!dialogVisible.value) return
    if (checkedCount.value > 0) return
    restoreFromDevice(deviceData.value)
  },
  { deep: true }
)

// ==================== 生命周期 ====================
onMounted(async () => {
  updateDeviceMode()
  window.addEventListener('resize', updateDeviceMode)
  generateQrCode()
  initializeFormFromDevice(props.device)
  await dictOptions.loadDictionary()
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', updateDeviceMode)
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
    padding: 14px 20px;
    background-color: #fafbfc;
    max-height: 75vh;
    overflow-y: auto;
  }

  :deep(.el-dialog__footer) {
    padding: 16px 24px;
    background-color: white;
    border-top: 1px solid #e5e7eb;
  }
}

// ==================== 设备信息条 ====================
.device-info-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 12px;
  margin-bottom: 10px;

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

// ==================== 智能质检面板 ====================
.smart-check-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin-bottom: 10px;

  .panel-header {
    padding: 12px 16px;
    border-bottom: 1px solid #e5e7eb;

    .header-actions {
      display: flex;
      align-items: stretch;
      width: 100%;
      gap: 8px;
      flex-wrap: wrap;

      .action-btn {
        min-width: 108px;
        flex: 1 1 calc(50% - 4px);
      }

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
}

// ==================== 质检选项网格 ====================
.check-grid {
  display: grid;
  grid-template-columns: repeat(12, 1fr);
  gap: 8px;
  padding: 12px;

  .grid-cell {
    &--battery { grid-column: span 3; }
    &--screen { grid-column: span 3; }
    &--indisplay { grid-column: span 3; }
    &--appearance { grid-column: span 3; }
    &--function { grid-column: span 8; }
    &--fix { grid-column: span 4; }
  }
}

// ==================== 标签网格 ====================
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

// ==================== 输入行 ====================
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

// ==================== 核心表单 ====================
.core-form {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

// ==================== 质检结果卡片组（一行三列） ====================
.result-cards {
  display: grid;
  grid-template-columns: 1fr 1fr 1fr;
  gap: 10px;
}

.result-card {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  background: white;
  overflow: hidden;
  transition: box-shadow 0.2s;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid transparent;
  }

  &__title {
    display: flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
  }

  &__dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    flex-shrink: 0;
  }

  &__body {
    padding: 0 10px 10px;
    margin-bottom: 0 !important;

    :deep(.el-form-item__content) {
      line-height: 1;
    }

    :deep(.el-textarea__inner) {
      border-radius: 6px;
      font-size: 12px;
      line-height: 1.6;
    }
  }

  // —— 卖家：蓝色 ——
  &--seller {
    border-color: #dbeafe;
    background: linear-gradient(180deg, #eff6ff 0%, #fff 50%);

    .result-card__header { color: #1d4ed8; border-bottom-color: #dbeafe; }
    .result-card__dot { background: #3b82f6; }

    :deep(.el-textarea__inner) {
      border-color: #bfdbfe;
      &:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); }
    }
  }

  // —— 买家：绿色 ——
  &--buyer {
    border-color: #d1fae5;
    background: linear-gradient(180deg, #ecfdf5 0%, #fff 50%);

    .result-card__header { color: #047857; border-bottom-color: #d1fae5; }
    .result-card__dot { background: #10b981; }

    :deep(.el-textarea__inner) {
      border-color: #a7f3d0;
      &:focus { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,0.1); }
    }
  }

  // —— 标签：橙色虚线 ——
  &--label {
    border: 1.5px dashed #fed7aa;
    background: linear-gradient(180deg, #fff7ed 0%, #fff 50%);

    .result-card__header { color: #c2410c; border-bottom-color: #fed7aa; }
    .result-card__dot { background: #f97316; }

    :deep(.el-textarea__inner) {
      border-color: #fed7aa;
      background: #fffbf5;
      font-family: 'SF Mono', 'Menlo', 'Consolas', monospace;
      font-size: 12px;
      letter-spacing: 0.2px;
      &:focus { border-color: #f97316; box-shadow: 0 0 0 2px rgba(249,115,22,0.1); }
    }

    .label-gen-btn {
      border-color: #fb923c;
      color: #ea580c;
      background: white;
      font-size: 11px;
      border-radius: 4px;
      padding: 2px 8px;
      height: 22px;

      &:hover {
        background: #fff7ed;
        border-color: #f97316;
        color: #c2410c;
      }
    }
  }
}

// ==================== 定价栏（紧凑一行） ====================
.pricing-bar {
  display: grid;
  grid-template-columns: auto auto auto;
  gap: 12px;
  align-items: end;
  background: linear-gradient(90deg, #fffbeb 0%, #fff 60%);
  border: 1px solid #fde68a;
  border-radius: 8px;
  padding: 12px 14px;

  :deep(.el-form-item) {
    margin-bottom: 0;
  }

  :deep(.el-form-item__label) {
    font-size: 12px;
    font-weight: 600;
    color: #92400e;
    padding-bottom: 4px;
  }

  &__remark {
    :deep(.el-input__inner) {
      border-radius: 6px;
    }
  }

  &__price {
    :deep(.el-input-number) {
      width: 140px;
    }
  }
}

// ==================== 图片区（保留原有 form-section 样式） ====================
.form-section {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  overflow: hidden;

  &__header {
    padding: 12px 20px;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
    font-size: 14px;
    font-weight: 600;
    color: #374151;
  }

  .form-row {
    padding: 20px;

    &--3col {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 20px;
    }

    &--2col {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 20px;
      padding-top: 0;
    }

    &--1col {
      padding: 0 20px 20px;
    }
  }

  .form-label-with-action {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;

    .el-button {
      font-size: 12px;
      padding: 0;
    }
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

    .upload-main {
      flex: 1;
    }

    .mobile-camera-actions {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      flex-wrap: wrap;

      .camera-tip {
        font-size: 12px;
        color: #6b7280;
      }
    }
  }
}

// ==================== 底部操作栏 ====================
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

// ==================== 响应式 ====================
@media (max-width: 768px) {
  .check-device-dialog {
    :deep(.el-dialog) {
      width: 95vw !important;
    }
  }

  .check-grid {
    grid-template-columns: 1fr;

    .grid-cell {
      &--battery,
      &--screen,
      &--indisplay,
      &--appearance,
      &--function,
      &--fix {
        grid-column: span 1;
      }
    }
  }

  .result-cards {
    grid-template-columns: 1fr;
  }

  .pricing-bar {
    grid-template-columns: 1fr;

    &__price :deep(.el-input-number) {
      width: 100% !important;
    }
  }

  .form-section .form-row {
    &--3col,
    &--2col {
      grid-template-columns: 1fr;
    }
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

@media (min-width: 769px) and (max-width: 1024px) {
  .check-grid {
    grid-template-columns: repeat(6, 1fr);

    .grid-cell {
      &--battery { grid-column: span 3; }
      &--screen { grid-column: span 3; }
      &--indisplay { grid-column: span 3; }
      &--appearance { grid-column: span 3; }
      &--function { grid-column: span 4; }
      &--fix { grid-column: span 2; }
    }
  }
}

@media (min-width: 769px) {
  .smart-check-panel .panel-header .header-actions {
    width: auto;
    align-items: center;

    .action-btn {
      min-width: auto;
      flex: 0 0 auto;
    }
  }
}
</style>
