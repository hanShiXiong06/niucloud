<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    :width="isMobile ? '95vw' : '980px'"
    :destroy-on-close="true"
    class="check-device-dialog"
    align-center
  >

    <!-- ===================================================================
         设备档案：型号 + IMEI 标识栏
    =================================================================== -->
    <section class="cdd-section cdd-device-card">
      <div class="cdd-device-card__header">
        <div class="cdd-device-card__info">
          <div class="cdd-device-card__icon">📱</div>
          <div class="cdd-device-card__content">
            <!-- 型号显示/编辑 -->
            <div class="cdd-device-card__model-row">
              <template v-if="!isEditingDeviceInfo">
                <div class="cdd-device-card__model-display">
                  <span class="model-text">{{ deviceForm.model || '未知型号' }}</span>
                </div>
              </template>
              <el-input
                v-else
                v-model="deviceForm.model"
                placeholder="请输入设备型号"
                size="default"
                clearable
                ref="modelInputRef"
                class="cdd-model-input"
              >
                <template #prefix>
                  <el-icon><Cellphone /></el-icon>
                </template>
              </el-input>
            </div>

            <!-- IMEI显示/编辑 -->
            <div class="cdd-device-card__imei-row">
              <template v-if="!isEditingDeviceInfo">
                <div class="cdd-device-card__imei-display">
                  <span class="imei-label">IMEI</span>
                  <span class="imei-value">{{ formatImei(deviceForm.imei) || '未录入' }}</span>
                </div>
              </template>
              <el-input
                v-else
                v-model="deviceForm.imei"
                placeholder="请输入或扫描15位IMEI"
                size="default"
                clearable
                maxlength="15"
                show-word-limit
                ref="imeiInputRef"
                @input="handleImeiInput"
                class="cdd-imei-input"
              >
                <template #prefix>
                  <el-icon><Postcard /></el-icon>
                </template>
                <template #append>
                  <el-button @click="focusImeiInput" size="small" type="primary" link>
                    <el-icon><Aim /></el-icon> 扫码
                  </el-button>
                </template>
              </el-input>
            </div>
          </div>
        </div>

        <!-- 编辑按钮 -->
        <div class="cdd-device-card__actions">
          <template v-if="!isEditingDeviceInfo">
            <el-button
              type="primary"
              size="small"
              link
              @click="startEditDeviceInfo"
              class="cdd-edit-link"
            >
              <el-icon><Edit /></el-icon>
              <span>编辑</span>
            </el-button>
          </template>
          <template v-else>
            <el-button
              type="success"
              size="small"
              @click="saveDeviceInfo"
            >
              <el-icon><Check /></el-icon>
            </el-button>
            <el-button
              size="small"
              @click="cancelEditDeviceInfo"
            >
              <el-icon><Close /></el-icon>
            </el-button>
          </template>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 1：设备信息（8项输入：规格 + 电池 + 锁）
    =================================================================== -->
    <section class="cdd-section cdd-block-info">
      <div class="cdd-block-header">
        <span>📋 设备信息</span>
        <div class="cdd-toolbar">
          <!-- 联网查询组 -->
          <div class="cdd-query-group">
            <span class="cdd-query-group__tag">联网</span>
            <el-button size="small" text :loading="loadingCoverage" :disabled="!deviceForm.imei" @click="fetchCoverage">
              <el-icon v-if="!loadingCoverage"><Headset /></el-icon>
              {{ loadingCoverage ? '查询中...' : '查保修' }}
            </el-button>
            <el-button size="small" text :loading="loadingActivationLock" :disabled="!deviceForm.imei" @click="fetchActivationlock">
              <el-icon v-if="!loadingActivationLock"><Lock /></el-icon>
              {{ loadingActivationLock ? '查询中...' : '查激活锁' }}
            </el-button>
            <el-button size="small" text :loading="loadingMdm" :disabled="!deviceForm.imei" @click="fetchMdm">
              <el-icon v-if="!loadingMdm"><Monitor /></el-icon>
              {{ loadingMdm ? '查询中...' : '查监管锁' }}
            </el-button>
          </div>
          <!-- 本地操作 -->
          <div class="cdd-local-group">
            <el-button size="small" text @click="fillCommonResult">常用模板</el-button>
            <el-button size="small" text type="danger" @click="clearAllSelections">清空选项</el-button>
          </div>
        </div>
      </div>

      <!-- 8格输入网格（2行×4列） -->
      <div class="cdd-info-grid">
        <!-- 第一行：基本规格 -->
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">💾</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">内存</span>
            <el-input v-model="deviceForm.capacity" size="small" placeholder="如 256GB" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">🎨</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">颜色</span>
            <el-input v-model="deviceForm.color" size="small" placeholder="如 深空黑色" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">📲</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">系统版本</span>
            <el-input v-model="deviceForm.system_version" size="small" placeholder="如 iOS 17.3.1" />
          </div>
        </div>
        <div class="cdd-info-cell">
          <span class="cdd-info-cell__icon">🛡</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">保修信息</span>
            <el-input v-model="deviceForm.warranty_info" size="small" placeholder="保修日期/过保/未激活" />
          </div>
        </div>
        <!-- 第二行：电池 + 锁 -->
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔋</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">电池健康度</span>
            <div class="cdd-info-cell__input-row">
              <el-input-number
                v-model="templateSelections.battery"
                :min="0" :max="100" :step="1"
                size="small" controls-position="right"
                class="cdd-info-cell__number"
                @change="updateCheckResult"
              />
              <span class="cdd-info-cell__unit">%</span>
            </div>
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔁</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">循环次数</span>
            <div class="cdd-info-cell__input-row">
              <el-input
                v-model="templateSelections.battery_num"
                type="number" size="small" style="flex:1"
                @change="updateCheckResult"
              />
              <span class="cdd-info-cell__unit">次</span>
            </div>
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🔒</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">激活锁</span>
            <el-switch
              v-model="templateSelections.activationLock"
              active-text="已开" inactive-text="未开" size="small"
              style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e"
              @change="updateCheckResult"
            />
          </div>
        </div>
        <div class="cdd-info-cell cdd-info-cell--row2">
          <span class="cdd-info-cell__icon">🖥</span>
          <div class="cdd-info-cell__body">
            <span class="cdd-info-cell__label">监管锁</span>
            <el-switch
              v-model="templateSelections.mdmLock"
              active-text="已开" inactive-text="未开" size="small"
              style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e"
              @change="updateCheckResult"
            />
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 2：外观规格（外屏 | 内屏 | 中框）
    =================================================================== -->
    <section class="cdd-section cdd-block-appearance">
      <div class="cdd-block-header">
        <span>📐 外观规格</span>
        <span
          v-if="[templateSelections.screenId, templateSelections.indisplayId, templateSelections.appearanceId].filter(Boolean).length > 0"
          class="cdd-block-badge"
        >
          已选 {{ [templateSelections.screenId, templateSelections.indisplayId, templateSelections.appearanceId].filter(Boolean).length }}/3
        </span>
      </div>
      <div class="cdd-sub-cols cdd-sub-cols--3">
        <!-- 外屏规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.screenId }">
          <div class="cdd-sub-header">
            外屏规格
            <span v-if="templateSelections.screenId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.screen" :key="opt.value"
              :type="templateSelections.screenId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.screenId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectScreenOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 内屏规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.indisplayId }">
          <div class="cdd-sub-header">
            内屏规格
            <span v-if="templateSelections.indisplayId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.indisplay" :key="opt.value"
              :type="templateSelections.indisplayId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.indisplayId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectIndisplayOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 中框规格 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.appearanceId }">
          <div class="cdd-sub-header">
            中框规格
            <span v-if="templateSelections.appearanceId" class="cdd-sub-done">✓</span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.appearance" :key="opt.value"
              :type="templateSelections.appearanceId === String(opt.value) ? 'primary' : undefined"
              :effect="templateSelections.appearanceId === String(opt.value) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="selectAppearanceOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         Block 3：问题记录（功能异常 | 维修记录）
    =================================================================== -->
    <section class="cdd-section cdd-block-issues">
      <div class="cdd-block-header">
        <span>⚠️ 问题记录</span>
        <span
          v-if="templateSelections.functionIds.length + templateSelections.fixIds.length > 0"
          class="cdd-block-badge cdd-block-badge--warn"
        >
          已选 {{ templateSelections.functionIds.length + templateSelections.fixIds.length }} 项
        </span>
      </div>
      <div class="cdd-sub-cols cdd-sub-cols--2">
        <!-- 功能异常 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.functionIds.length > 0 }">
          <div class="cdd-sub-header">
            功能
            <span v-if="templateSelections.functionIds.length > 0" class="cdd-sub-badge cdd-sub-badge--danger">
              {{ templateSelections.functionIds.length }} 项
            </span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.function" :key="opt.value"
              :type="templateSelections.functionIds.includes(String(opt.value)) ? 'danger' : undefined"
              :effect="templateSelections.functionIds.includes(String(opt.value)) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="toggleFunctionOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
        <!-- 维修记录 -->
        <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.fixIds.length > 0 }">
          <div class="cdd-sub-header">
            维修记录
            <span v-if="templateSelections.fixIds.length > 0" class="cdd-sub-badge cdd-sub-badge--warning">
              {{ templateSelections.fixIds.length }} 项
            </span>
          </div>
          <div class="cdd-tag-grid">
            <el-tag
              v-for="opt in checkDictOptions.fix" :key="opt.value"
              :type="templateSelections.fixIds.includes(String(opt.value)) ? 'warning' : undefined"
              :effect="templateSelections.fixIds.includes(String(opt.value)) ? 'dark' : 'plain'"
              size="small" class="cdd-check-tag"
              @click="toggleFixOption(opt.value)"
            >{{ opt.name }}</el-tag>
          </div>
        </div>
      </div>
    </section>

    <!-- ===================================================================
         区域 3：质检结果文本（卖家 / 买家）
    =================================================================== -->
    <el-form ref="formRef" :model="deviceForm" :rules="rules" label-position="top">
      <section class="cdd-section cdd-result-section">
        <div class="cdd-result-cols">

          <!-- 卖家质检结果 -->
          <div class="cdd-result-card cdd-result-card--seller">
            <div class="cdd-result-card__header">
              <span class="cdd-result-card__dot"></span>
              <span class="cdd-result-card__title">卖家可见质检结果</span>
              <el-tooltip content="此内容将展示给卖家" placement="top">
                <el-icon class="cdd-result-card__help"><Warning /></el-icon>
              </el-tooltip>
            </div>
            <el-form-item prop="check_result_seller" class="cdd-result-card__body">
              <el-input
                v-model="deviceForm.check_result_seller"
                type="textarea"
                :rows="isMobile ? 5 : 7"
                placeholder="由上方质检选项自动生成，也可手动编辑..."
                maxlength="500"
                show-word-limit
                resize="none"
              />
            </el-form-item>
          </div>

          <!-- 买家质检结果 -->
          <div class="cdd-result-card cdd-result-card--buyer">
            <div class="cdd-result-card__header">
              <span class="cdd-result-card__dot"></span>
              <span class="cdd-result-card__title">买家可见质检结果</span>
              <el-tooltip content="此内容将展示给买家，可独立编辑" placement="top">
                <el-icon class="cdd-result-card__help"><Warning /></el-icon>
              </el-tooltip>
              <el-button
                type="primary" link size="small"
                class="ml-auto"
                @click="syncSellerResultToBuyer"
              >
                <el-icon><CopyDocument /></el-icon> 同步卖家
              </el-button>
            </div>
            <el-form-item prop="check_result_buyer" class="cdd-result-card__body">
              <el-input
                v-model="deviceForm.check_result_buyer"
                type="textarea"
                :rows="isMobile ? 5 : 7"
                placeholder="可点击「同步卖家」快速填充，再按需修改..."
                maxlength="500"
                show-word-limit
                resize="none"
              />
            </el-form-item>
          </div>

        </div>
      </section>

      <!-- ===================================================================
           区域 4：定价信息
      =================================================================== -->
      <section class="cdd-section cdd-pricing-section">
        <div class="cdd-block-header">💰 定价信息</div>

        <!-- 回收定价：独占一行、视觉突出 -->
        <div class="cdd-pricing-main">
          <el-form-item prop="final_price" class="cdd-pricing-main__item">
            <template #label>
              <span class="cdd-pricing-main__label">
                回收定价
                <span class="cdd-pricing-main__required">*</span>
                <span v-if="deviceData.initial_price" class="cdd-pricing-main__ref">
                  参考预估：¥{{ deviceData.initial_price }}
                </span>
              </span>
            </template>
            <el-input-number
              v-model="deviceForm.final_price"
              :step="10" :precision="2" :min="0" :max="99999"
              placeholder="输入回收定价"
              controls-position="right"
              class="cdd-pricing-main__input"
            />
          </el-form-item>
          <el-form-item label="扣费说明" prop="remark" class="cdd-pricing-sub__item cdd-pricing-sub__item--remark">
            <el-input
              v-model="deviceForm.remark"
              placeholder="扣费原因、特殊备注..."
              maxlength="200"
              clearable
            />
          </el-form-item>
        </div>
      </section>

      <!-- ===================================================================
           区域 5：质检图片
      =================================================================== -->
      <section class="cdd-section cdd-photos-section">
        <div class="cdd-photos-cols">

          <!-- 卖家图片 -->
          <div class="cdd-photo-block cdd-photo-block--seller">
            <div class="cdd-photo-block__header">
              <span class="cdd-photo-block__dot"></span>
              卖家质检图片
            </div>
            <div class="cdd-photo-block__body">
              <div v-if="isMobile" class="cdd-camera-row">
                <el-button
                  type="primary" plain size="small"
                  :loading="cameraUploading"
                  :disabled="cameraUploading || checkImageCount >= maxCheckImageCount"
                  @click="openCameraCapture"
                >{{ cameraUploading ? '上传中...' : '📷 拍照上传' }}</el-button>
                <span class="cdd-camera-tip">{{ checkImageCount }}/{{ maxCheckImageCount }}</span>
                <input
                  ref="cameraInputRef" type="file" accept="image/*"
                  capture="environment" multiple class="hidden"
                  @change="handleCameraFilesChange"
                />
              </div>
              <upload-image v-model="deviceForm.check_images" :limit="9" />
            </div>
          </div>

          <!-- 买家图片 -->
          <div class="cdd-photo-block cdd-photo-block--buyer">
            <div class="cdd-photo-block__header">
              <span class="cdd-photo-block__dot"></span>
              买家质检图片
              <el-button type="primary" link size="small" class="ml-auto" @click="syncSellerImagesToBuyer">
                <el-icon><CopyDocument /></el-icon> 同步卖家图片
              </el-button>
            </div>
            <div class="cdd-photo-block__body">
              <div v-if="isMobile" class="cdd-camera-row">
                <el-button
                  type="primary" plain size="small"
                  :loading="buyerCameraUploading"
                  :disabled="buyerCameraUploading || buyerCheckImageCount >= buyerMaxCheckImageCount"
                  @click="openBuyerCameraCapture"
                >{{ buyerCameraUploading ? '上传中...' : '📷 拍照上传' }}</el-button>
                <span class="cdd-camera-tip">{{ buyerCheckImageCount }}/{{ buyerMaxCheckImageCount }}</span>
                <input
                  ref="buyerCameraInputRef" type="file" accept="image/*"
                  capture="environment" multiple class="hidden"
                  @change="handleBuyerCameraFilesChange"
                />
              </div>
              <upload-image v-model="deviceForm.check_images_buyer" :limit="6" />
            </div>
          </div>

        </div>
      </section>

    </el-form>

    <!-- ===================================================================
         底部操作栏
    =================================================================== -->
    <template #footer>
      <div class="cdd-footer">
        <span class="cdd-footer__info">已填质检项：{{ checkedCount }}</span>
        <div class="cdd-footer__btns">
          <el-button size="large" @click="handleCancel">取消</el-button>
          <el-button type="warning" size="large" :loading="savingDraft" @click="handleSaveDraft">
            {{ savingDraft ? '暂存中...' : '暂存草稿' }}
          </el-button>
          <el-button type="primary" size="large" :loading="submitting" @click="handleConfirm">
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
  Edit, Postcard, Aim, Monitor, Check, Headset, Lock, CopyDocument, Warning
} from '@element-plus/icons-vue'
import QRCode from 'qrcode'

import { getCoverage, getActivationlock, getMdm } from '@/addon/recycle/api/device_query_api'
import { useCheckDeviceDict } from '@/addon/recycle/hooks/useCheckDeviceDict'
import {
  normalizeInfo,
  useCheckMeta,
  type CheckMetaPayload,
  type CheckOptionsGroup
} from './composables/useCheckMeta'
import { useCameraUpload } from './composables/useCameraUpload'

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

const props = defineProps<{ visible: boolean; device: DeviceInfo }>()

const emit = defineEmits<{
  'update:visible': [value: boolean]
  'confirm': [data: any]
  'cancel': []
  'save-draft': [data: any]
}>()

const dictOptions = useCheckDeviceDict()
const checkDictOptions = computed<CheckOptionsGroup>(() => dictOptions.options.value as CheckOptionsGroup)

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const modelInputRef = ref()
const isEditingDeviceInfo = ref(false)
const qrCode = ref('')
const isMobile = ref(false)

// 保存编辑前的原始数据
const originalDeviceInfo = ref({ model: '', imei: '' })

const loadingCoverage = ref(false)
const loadingActivationLock = ref(false)
const loadingMdm = ref(false)
const activationLockInfo = ref<any>(null)
const mdmInfo = ref<any>(null)

const deviceForm = reactive({
  model: props.device.model || '',
  check_result: '',
  check_result_seller: props.device.check_result_seller || props.device.check_result || '',
  check_result_buyer: props.device.check_result_buyer || '',
  check_images: props.device.check_images_seller || props.device.check_images || '',
  check_images_buyer: props.device.check_images_buyer || '',
  final_price: parsePrice(props.device.final_price),
  sell_price: parsePrice(props.device.sell_price),
  remark: props.device.remark || '',
  imei: props.device.imei || '',
  info: normalizeInfo(props.device.info),
  system_version: props.device.system_version || '',
  warranty_info: props.device.warranty_info || '',
  capacity: normalizeInfo(props.device.info).capacity || '',
  color: normalizeInfo(props.device.info).color || ''
})

const rules = reactive<FormRules>({
  check_result_seller: [
    { required: true, message: '请输入质检结果', trigger: 'blur' },
    { min: 5, message: '质检结果至少5个字符', trigger: 'blur' }
  ]
})

const {
  templateSelections, checkedCount, getSubmitInfo,
  updateCheckResult, clearAllSelections, fillCommonResult,
  restoreFromDevice,
  selectScreenOption, selectIndisplayOption, selectAppearanceOption,
  toggleFunctionOption, toggleFixOption
} = useCheckMeta({ dictOptions: checkDictOptions, deviceForm })

const {
  cameraUploading, cameraInputRef, maxCheckImageCount,
  checkImageCount, openCameraCapture, handleCameraFilesChange
} = useCameraUpload({ checkImages: toRef(deviceForm, 'check_images') })

const {
  cameraUploading: buyerCameraUploading,
  cameraInputRef: buyerCameraInputRef,
  maxCheckImageCount: buyerMaxCheckImageCount,
  checkImageCount: buyerCheckImageCount,
  openCameraCapture: openBuyerCameraCapture,
  handleCameraFilesChange: handleBuyerCameraFilesChange
} = useCameraUpload({ checkImages: toRef(deviceForm, 'check_images_buyer') })

// 电池卡完成状态
const batteryDone = computed(() =>
  templateSelections.battery !== undefined || templateSelections.battery_num !== undefined
)

function parsePrice(value: any): number | undefined {
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const parsed = parseFloat(value)
    return Number.isNaN(parsed) ? undefined : parsed
  }
  return undefined
}

const updateDeviceMode = () => { isMobile.value = window.innerWidth <= 768 }

const generateQrCode = async () => {
  qrCode.value = await QRCode.toDataURL(
    window.location.origin + '/site/diy/attachment',
    { errorCorrectionLevel: 'L', margin: 0, width: 100 }
  )
}

// 格式化 IMEI 显示
const formatImei = (imei: string) => {
  if (!imei) return ''
  return imei.replace(/(\d{4})(?=\d)/g, '$1 ')
}

// 开始编辑设备信息
const startEditDeviceInfo = () => {
  originalDeviceInfo.value = {
    model: deviceForm.model,
    imei: deviceForm.imei
  }
  isEditingDeviceInfo.value = true
  nextTick(() => {
    modelInputRef.value?.focus()
  })
}

// 保存设备信息
const saveDeviceInfo = () => {
  if (!deviceForm.model?.trim()) {
    ElMessage.warning('请输入设备型号')
    return
  }
  if (!deviceForm.imei?.trim()) {
    ElMessage.warning('请输入IMEI号')
    return
  }
  if (deviceForm.imei.length !== 15) {
    ElMessage.warning('IMEI号必须是15位')
    return
  }

  isEditingDeviceInfo.value = false
  ElMessage.success('设备信息已更新')
}

// 取消编辑设备信息
const cancelEditDeviceInfo = () => {
  deviceForm.model = originalDeviceInfo.value.model
  deviceForm.imei = originalDeviceInfo.value.imei
  isEditingDeviceInfo.value = false
}

const handleImeiInput = (value: string) => {
  deviceForm.imei = value.replace(/[^0-9]/g, '')
}

const focusImeiInput = () => {
  imeiInputRef.value?.focus()
}

// 查询保修 —— 结果直接回填规格输入框，不弹额外面板
const fetchCoverage = async () => {
  if (!deviceForm.imei) { ElMessage.warning('请先输入IMEI号码'); return }
  loadingCoverage.value = true
  try {
    const brand = dictOptions.extractBrand(deviceData.value.model || '')
    const res = await getCoverage({ imei: deviceForm.imei, brand })
    if (res.data?.model) {
      deviceData.value.model = `${res.data.model} ${res.data.capacity} ${res.data.color}`
      deviceForm.info = { ...normalizeInfo(deviceForm.info), ...res.data }
      deviceForm.info = getSubmitInfo()
      if (res.data.capacity) deviceForm.capacity = res.data.capacity
      if (res.data.color) deviceForm.color = res.data.color
      if (res.data.osVersion) deviceForm.system_version = res.data.osVersion
      if (res.data.coverage) {
        const s = res.data.coverage.status || ''
        if (s === 'Out Of Warranty') deviceForm.warranty_info = '过保'
        else if (s === 'Not Activated' || !res.data.coverage.date) deviceForm.warranty_info = '未激活'
        else deviceForm.warranty_info = res.data.coverage.date || '在保'
      }
      updateCheckResult()
      ElMessage.success('保修信息已自动填入规格栏')
    } else if (res.data?.msg) {
      ElMessage.error('保修查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到保修信息')
    }
  } catch {
    ElMessage.error('保修查询失败，请稍后重试')
  } finally {
    loadingCoverage.value = false
  }
}

const fetchActivationlock = async () => {
  if (!deviceForm.imei) { ElMessage.warning('请先输入IMEI号码'); return }
  loadingActivationLock.value = true
  try {
    const res = await getActivationlock(deviceForm.imei)
    if (res.data?.sn) {
      activationLockInfo.value = res.data
      templateSelections.activationLock = res.data.locked === true || res.data.fmi === 'On'
      updateCheckResult()
      ElMessage.success(`激活锁：${templateSelections.activationLock ? '已开启' : '未开启'}，已自动填入`)
    } else if (res.data?.msg) {
      ElMessage.error('激活锁查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到激活锁信息')
    }
  } catch {
    ElMessage.error('激活锁查询失败，请稍后重试')
  } finally {
    loadingActivationLock.value = false
  }
}

const fetchMdm = async () => {
  if (!deviceForm.imei) { ElMessage.warning('请先输入IMEI号码'); return }
  loadingMdm.value = true
  try {
    const res = await getMdm(deviceForm.imei)
    if (res.data?.sn) {
      mdmInfo.value = res.data
      templateSelections.mdmLock = res.data.locked === true || res.data.mdm === 'On' || res.data.mdm === true
      updateCheckResult()
      ElMessage.success(`监管锁：${templateSelections.mdmLock ? '已开启' : '未开启'}，已自动填入`)
    } else if (res.data?.msg) {
      ElMessage.error('监管锁查询失败：' + res.data.msg)
    } else {
      ElMessage.warning('未查询到监管锁信息')
    }
  } catch {
    ElMessage.error('监管锁查询失败，请稍后重试')
  } finally {
    loadingMdm.value = false
  }
}

const syncSellerResultToBuyer = () => {
  if (!deviceForm.check_result_seller) { ElMessage.warning('卖家质检结果为空，无法同步'); return }
  deviceForm.check_result_buyer = deviceForm.check_result_seller
  ElMessage.success('已同步，可继续单独编辑买家内容')
}

const syncSellerImagesToBuyer = () => {
  if (!deviceForm.check_images) { ElMessage.warning('卖家质检图片为空，无法同步'); return }
  deviceForm.check_images_buyer = deviceForm.check_images
  ElMessage.success('已同步卖家图片到买家')
}

const handleCancel = () => { dialogVisible.value = false; emit('cancel') }

const handleConfirm = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    submitting.value = true
    try { emit('confirm', buildSubmitPayload('check')); dialogVisible.value = false }
    finally { submitting.value = false }
  })
}

const handleSaveDraft = async () => {
  savingDraft.value = true
  try { emit('save-draft', buildSubmitPayload('save_draft')); dialogVisible.value = false }
  finally { savingDraft.value = false }
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
    info: getSubmitInfo(),
    system_version: deviceForm.system_version,
    warranty_info: deviceForm.warranty_info,
    capacity: deviceForm.capacity,
    color: deviceForm.color
  }
}

const initializeFormFromDevice = (device: DeviceInfo) => {
  activationLockInfo.value = null
  mdmInfo.value = null
  isEditingDeviceInfo.value = false
  loadingCoverage.value = false
  loadingActivationLock.value = false
  loadingMdm.value = false
  clearAllSelections()
  deviceData.value = { ...device }
  // 更新设备基本信息
  deviceForm.model = device.model || ''
  deviceForm.imei = device.imei || ''
  deviceForm.check_result_seller = device.check_result_seller || device.check_result || ''
  deviceForm.check_result = ''
  deviceForm.check_result_buyer = device.check_result_buyer || ''
  deviceForm.check_images = device.check_images_seller || device.check_images || ''
  deviceForm.check_images_buyer = device.check_images_buyer || ''
  deviceForm.final_price = parsePrice(device.final_price)
  deviceForm.sell_price = parsePrice(device.sell_price)
  deviceForm.remark = device.remark || ''
  deviceForm.imei = device.imei || ''
  deviceForm.info = normalizeInfo(device.info)
  deviceForm.system_version = device.system_version || ''
  deviceForm.warranty_info = device.warranty_info || ''
  const restoredInfo = normalizeInfo(device.info)
  deviceForm.capacity = device.capacity || restoredInfo.capacity || ''
  deviceForm.color = device.color || restoredInfo.color || ''
  restoreFromDevice(device)
  nextTick(() => { formRef.value?.clearValidate() })
}

watch(() => props.visible, (val) => { dialogVisible.value = val })
watch(() => props.device, (val) => { initializeFormFromDevice(val) }, { deep: true })
watch(dialogVisible, (val) => { emit('update:visible', val) })
watch(
  () => [
    checkDictOptions.value.screen, checkDictOptions.value.indisplay,
    checkDictOptions.value.appearance, checkDictOptions.value.function, checkDictOptions.value.fix
  ],
  () => {
    if (!dialogVisible.value || checkedCount.value > 0) return
    restoreFromDevice(deviceData.value)
  },
  { deep: true }
)

onMounted(async () => {
  updateDeviceMode()
  window.addEventListener('resize', updateDeviceMode)
  generateQrCode()
  initializeFormFromDevice(props.device)
  await dictOptions.loadDictionary()
})
onBeforeUnmount(() => { window.removeEventListener('resize', updateDeviceMode) })
</script>

<style lang="scss" scoped>
/* ============================================================
   Dialog 容器
   ============================================================ */
.check-device-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 8px 40px rgba(0,0,0,0.12);
  }
  :deep(.el-dialog__header) {
    padding: 18px 24px 14px;
    background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
    border: none;
    .el-dialog__title { font-size: 17px; font-weight: 700; color: #fff; }
  }
  :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: rgba(255,255,255,0.8);
    &:hover { color: #fff; }
  }
  :deep(.el-dialog__body) {
    padding: 12px 16px;
    background: #f4f6f9;
    max-height: 76vh;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
    gap: 10px;
    &::-webkit-scrollbar { width: 5px; }
    &::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 3px; }
  }
  :deep(.el-dialog__footer) {
    padding: 14px 20px;
    background: #fff;
    border-top: 1px solid #e5e7eb;
  }
}

/* ============================================================
   通用 section 容器
   ============================================================ */
.cdd-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  overflow: hidden;
  margin-bottom: 10px;
}

/* ============================================================
   设备档案卡
   ============================================================ */
.cdd-device-card {
  background: linear-gradient(135deg, #393b41 0%, #302e32 100%);
  border: none;
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px;
    gap: 16px;
  }

  &__info {
    display: flex;
    align-items: center;
    gap: 16px;
    flex: 1;
    min-width: 0;
  }

  &__icon {
    font-size: 42px;
    flex-shrink: 0;
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
  }

  &__content {
    flex: 1;
    min-width: 0;
  }

  &__model-row {
    margin-bottom: 8px;
  }

  &__model-display {
    .model-text {
      font-size: 18px;
      font-weight: 700;
      color: #ffffff;
      text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      display: block;
      line-height: 1.4;
    }
  }

  &__imei-row {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  &__imei-display {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 6px 12px;
    border-radius: 20px;
    border: 1px solid rgba(255, 255, 255, 0.2);

    .imei-label {
      font-size: 11px;
      font-weight: 600;
      color: rgba(255, 255, 255, 0.8);
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .imei-value {
      font-size: 13px;
      font-weight: 600;
      color: #ffffff;
      font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
      letter-spacing: 0.5px;
    }
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
  }

  // 编辑模式下的输入框样式
  .cdd-model-input,
  .cdd-imei-input {
    :deep(.el-input__wrapper) {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.3);
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);

      &:hover {
        border-color: rgba(255, 255, 255, 0.5);
      }

      &.is-focus {
        border-color: #ffffff;
        box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
      }
    }

    :deep(.el-input__inner) {
      color: #1f2937;
      font-weight: 500;
    }
  }

  .cdd-model-input {
    :deep(.el-input__inner) {
      font-size: 16px;
      font-weight: 600;
    }
  }

  .cdd-imei-input {
    :deep(.el-input__inner) {
      font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
      letter-spacing: 0.5px;
    }
  }
}

// 编辑链接按钮样式
.cdd-edit-link {
  color: rgba(255, 255, 255, 0.9) !important;
  font-weight: 500;
  transition: all 0.2s ease;

  &:hover {
    color: #ffffff !important;
    transform: translateY(-1px);
  }

  span {
    margin-left: 4px;
  }
}

// 编辑模式下的按钮样式
.cdd-device-card__actions {
  .el-button {
    backdrop-filter: blur(10px);

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
    }

    &.el-button--success {
      background: #10b981;
      border-color: #10b981;
      color: #ffffff;

      &:hover {
        background: #059669;
        border-color: #059669;
      }
    }
  }
}

/* ============================================================
   通用 block 标题栏
   ============================================================ */
.cdd-block-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 8px;
  padding: 10px 14px;
  background: #f9fafb;
  border-bottom: 1px solid #e5e7eb;
  font-size: 13px;
  font-weight: 700;
  color: #1f2937;
}

.cdd-block-badge {
  font-size: 11px;
  color: #4f46e5;
  background: #eef2ff;
  padding: 1px 8px;
  border-radius: 10px;
  font-weight: 600;

  &--warn {
    color: #b45309;
    background: #fef3c7;
  }
}

/* ============================================================
   Block 1：设备信息 - 工具栏 + 8格输入
   ============================================================ */
.cdd-toolbar {
  display: flex;
  align-items: center;
  gap: 4px;
  flex-wrap: wrap;
}

// 联网查询组
.cdd-query-group {
  display: flex;
  align-items: center;
  gap: 0;
  background: #f3f4f6;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  padding: 2px 6px;

  &__tag {
    font-size: 10px;
    color: #6366f1;
    background: #eef2ff;
    padding: 1px 5px;
    border-radius: 3px;
    margin-right: 4px;
    font-weight: 600;
    white-space: nowrap;
  }
}

// 本地操作组
.cdd-local-group {
  display: flex;
  align-items: center;
  gap: 0;
  margin-left: 4px;
  padding-left: 8px;
  border-left: 1px solid #e5e7eb;
}

// 8格输入网格（2行 × 4列）
.cdd-info-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
}

.cdd-info-cell {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 10px 12px;
  border-right: 1px solid #f1f5f9;

  // 每行末格无右边框
  &:nth-child(4n) { border-right: none; }

  // 第二行加上边框
  &--row2 { border-top: 1px solid #f1f5f9; }

  &__icon { font-size: 15px; flex-shrink: 0; margin-top: 18px; }
  &__body { display: flex; flex-direction: column; gap: 5px; flex: 1; min-width: 0; }
  &__label { font-size: 10px; color: #94a3b8; font-weight: 500; white-space: nowrap; }
  &__input-row { display: flex; align-items: center; gap: 4px; }
  &__number { flex: 1; }
  &__unit { font-size: 12px; color: #9ca3af; flex-shrink: 0; }
}

/* ============================================================
   Block 2 & 3：外观规格 / 问题记录 - 子区块列
   ============================================================ */
.cdd-sub-cols {
  display: grid;

  &--3 { grid-template-columns: repeat(3, 1fr); }
  &--2 { grid-template-columns: repeat(2, 1fr); }
}

.cdd-sub-section {
  padding: 10px 12px;
  border-right: 1px solid #f1f5f9;

  &:last-child { border-right: none; }

  &--done {
    background: #fafffe;
    .cdd-sub-header { color: #16a34a; }
  }
}

.cdd-sub-header {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  font-weight: 600;
  color: #374151;
  margin-bottom: 8px;
}

.cdd-sub-done {
  font-size: 11px;
  color: #16a34a;
  font-weight: 700;
}

.cdd-sub-badge {
  font-size: 10px;
  padding: 1px 6px;
  border-radius: 10px;
  font-weight: 600;

  &--danger  { color: #ef4444; background: #fee2e2; }
  &--warning { color: #d97706; background: #fef3c7; }
}

.cdd-tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 5px;
}

.cdd-check-tag {
  cursor: pointer;
  transition: transform 0.15s;
  &:hover { transform: translateY(-1px); }
}

/* ============================================================
   区域 3：质检结果
   ============================================================ */
.cdd-result-section { overflow: visible; }

.cdd-result-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  padding: 10px;
}

.cdd-result-card {
  border-radius: 8px;
  border: 1px solid #e5e7eb;
  overflow: hidden;

  &__header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    font-size: 12px;
    font-weight: 600;
    border-bottom: 1px solid transparent;
  }
  &__dot {
    width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0;
  }
  &__title { flex: 1; }
  &__help  { color: #9ca3af; font-size: 13px; cursor: help; }
  &__body  {
    padding: 8px 10px 0;
    margin-bottom: 0 !important;
    :deep(.el-form-item__content) { line-height: 1; }
    :deep(.el-textarea__inner) { font-size: 12px; line-height: 1.6; border-radius: 6px; }
  }

  // 卖家：蓝
  &--seller {
    border-color: #dbeafe;
    .cdd-result-card__header { color: #1d4ed8; background: linear-gradient(to right, #eff6ff, #fff); border-bottom-color: #dbeafe; }
    .cdd-result-card__dot { background: #3b82f6; }
    :deep(.el-textarea__inner) { border-color: #bfdbfe; &:focus { border-color: #3b82f6; box-shadow: 0 0 0 2px rgba(59,130,246,0.1); } }
  }
  // 买家：绿
  &--buyer {
    border-color: #d1fae5;
    .cdd-result-card__header { color: #047857; background: linear-gradient(to right, #ecfdf5, #fff); border-bottom-color: #d1fae5; }
    .cdd-result-card__dot { background: #10b981; }
    :deep(.el-textarea__inner) { border-color: #a7f3d0; &:focus { border-color: #10b981; box-shadow: 0 0 0 2px rgba(16,185,129,0.1); } }
  }
}

/* ============================================================
   区域 4：定价
   ============================================================ */
.cdd-pricing-section {
  :deep(.el-form-item) { margin-bottom: 0; }
  :deep(.el-form-item__label) { font-size: 12px; font-weight: 600; color: #374151; padding-bottom: 4px; line-height: 1.4; }
}

.cdd-pricing-main {
  padding: 12px 14px 10px;
  border-bottom: 1px solid #fef3c7;
  background: linear-gradient(to right, #fffbeb, #fff);

  &__label { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
  &__required { color: #ef4444; font-size: 13px; }
  &__ref { font-size: 11px; color: #9ca3af; font-weight: 400; }
  &__input { width: 200px !important; }
  &__item :deep(.el-form-item__label) { font-size: 13px; font-weight: 700; color: #92400e; }
}

.cdd-pricing-sub {
  display: grid;
  grid-template-columns: 180px 1fr;
  gap: 12px;
  padding: 10px 14px;

  &__item { }
  &__item--remark { }
}

/* ============================================================
   区域 5：图片
   ============================================================ */
.cdd-photos-cols {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0;
}

.cdd-photo-block {
  &:first-child { border-right: 1px solid #e5e7eb; }

  &__header {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 9px 14px;
    font-size: 12px;
    font-weight: 600;
    border-bottom: 1px solid #e5e7eb;
  }
  &__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
  &__body { padding: 12px 14px; }

  &--seller {
    .cdd-photo-block__header { color: #1d4ed8; background: #f5f8ff; }
    .cdd-photo-block__dot   { background: #3b82f6; }
  }
  &--buyer {
    .cdd-photo-block__header { color: #047857; background: #f2fdf7; }
    .cdd-photo-block__dot   { background: #10b981; }
  }
}

.cdd-camera-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}
.cdd-camera-tip { font-size: 11px; color: #9ca3af; }

/* ============================================================
   底部操作栏
   ============================================================ */
.cdd-footer {
  display: flex;
  align-items: center;
  justify-content: space-between;

  &__info {
    font-size: 12px;
    color: #6b7280;
    background: #f3f4f6;
    padding: 4px 10px;
    border-radius: 4px;
  }
  &__btns { display: flex; gap: 10px; }
}

/* ============================================================
   响应式
   ============================================================ */
@media (max-width: 768px) {
  .check-device-dialog {
    :deep(.el-dialog) { width: 95vw !important; }
  }

  // Block 1 设备信息：4列 → 2列
  .cdd-info-grid {
    grid-template-columns: repeat(2, 1fr);

    .cdd-info-cell {
      // 每行第2格去掉右边框
      &:nth-child(4n) { border-right: 1px solid #f1f5f9; } // reset
      &:nth-child(2n) { border-right: none; }

      // row2 相对整体是第5-8格，都需要 border-top
      &--row2 { border-top: 1px solid #f1f5f9; }
      // 但第5、6格 (前两个 row2) 的 border-top 已有，第7、8格也有，OK
    }
  }

  // Block 2 外观规格：3列 → 1列
  .cdd-sub-cols--3 {
    grid-template-columns: 1fr;

    .cdd-sub-section {
      border-right: none;
      border-bottom: 1px solid #f1f5f9;
      &:last-child { border-bottom: none; }
    }
  }

  // Block 3 问题记录：2列 → 1列
  .cdd-sub-cols--2 {
    grid-template-columns: 1fr;

    .cdd-sub-section {
      border-right: none;
      border-bottom: 1px solid #f1f5f9;
      &:last-child { border-bottom: none; }
    }
  }

  // 工具栏换行处理
  .cdd-block-header { flex-direction: column; align-items: flex-start; }
  .cdd-toolbar { width: 100%; }
  .cdd-query-group { flex: 1; }
  .cdd-local-group { border-left: none; padding-left: 0; border-top: 1px solid #e5e7eb; padding-top: 6px; width: 100%; }

  // 结果两列 → 一列
  .cdd-result-cols { grid-template-columns: 1fr; }

  // 定价副栏 → 一列
  .cdd-pricing-sub { grid-template-columns: 1fr; }
  .cdd-pricing-main__input { width: 100% !important; }

  // 图片两列 → 一列
  .cdd-photos-cols {
    grid-template-columns: 1fr;
    .cdd-photo-block:first-child { border-right: none; border-bottom: 1px solid #e5e7eb; }
  }

  // footer 竖排
  .cdd-footer {
    flex-direction: column;
    gap: 10px;
    align-items: stretch;
    &__btns { flex-direction: column; .el-button { width: 100%; } }
  }
}
</style>
