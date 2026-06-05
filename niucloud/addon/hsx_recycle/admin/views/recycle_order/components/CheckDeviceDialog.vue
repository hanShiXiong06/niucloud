<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    :width="dialogWidth"
    :top="isMobile ? '2vh' : '3vh'"
    :destroy-on-close="true"
    class="check-device-dialog cdd-workbench-dialog"
  >
    <div class="cdd-workbench">
      <header class="cdd-topbar">
        <div class="cdd-topbar__identity">
          <template v-if="!isEditingDeviceInfo">
            <div class="cdd-model-title">{{ deviceForm.model || '未知型号' }}</div>
            <div class="cdd-imei-line">
              <span>IMEI</span>
              <strong>{{ formatImei(deviceForm.imei) || '未录入' }}</strong>
            </div>
          </template>
          <template v-else>
            <el-input ref="modelInputRef" v-model="deviceForm.model" placeholder="请输入设备型号" clearable class="cdd-topbar__model-input">
              <template #prefix><el-icon><Cellphone /></el-icon></template>
            </el-input>
            <el-input ref="imeiInputRef" v-model="deviceForm.imei" placeholder="请输入或扫描15位IMEI" clearable maxlength="15" show-word-limit class="cdd-topbar__imei-input" @input="handleImeiInput">
              <template #prefix><el-icon><Postcard /></el-icon></template>
              <template #append>
                <el-button @click="focusImeiInput"><el-icon><Aim /></el-icon></el-button>
              </template>
            </el-input>
          </template>
        </div>

        <div class="cdd-topbar__actions">
          <CheckTemplateSelector
            v-model="selectedCheckTemplateId"
            :template-info="checkTemplateInfo"
            :templates="checkTemplateList"
            :group-count="checkTemplateGroups.length"
            :field-count="checkTemplateFields.length"
            :loading="checkTemplateLoading || checkSchemaLoading"
            @change="handleCheckTemplateChange"
          />
          <el-tag size="small" type="success" effect="plain">已填 {{ checkedCount }} 项</el-tag>
          <template v-if="!isEditingDeviceInfo">
            <el-button size="small" :icon="Edit" @click="startEditDeviceInfo">编辑设备</el-button>
          </template>
          <template v-else>
            <el-button size="small" type="success" :icon="Check" @click="saveDeviceInfo">保存</el-button>
            <el-button size="small" :icon="Close" @click="cancelEditDeviceInfo">取消</el-button>
          </template>
        </div>
      </header>

      <el-form ref="formRef" :model="deviceForm" :rules="rules" label-position="top" class="cdd-main-form">
        <div v-if="isMobile" class="cdd-mobile-main">
          <section class="cdd-panel cdd-summary-panel">
            <div class="cdd-panel__title">设备摘要</div>
            <div class="cdd-summary-list">
              <div v-for="field in deviceSummaryFields" :key="field.field_key">
                <span>{{ field.field_name }}</span>
                <strong>{{ formatSummaryFieldValue(field) }}</strong>
              </div>
              <div v-if="!deviceSummaryFields.length" class="cdd-summary-empty">未配置摘要字段</div>
            </div>
          </section>

          <section class="cdd-panel">
            <div class="cdd-panel__title">联网查询</div>
            <div class="cdd-query-stack">
              <el-button v-for="action in visibleDeviceQueryActions" :key="action.code" plain :loading="isQueryActionLoading(action.code)" :disabled="!deviceForm.imei" @click="runDeviceQueryAction(action)">
                <el-icon v-if="!isQueryActionLoading(action.code)"><component :is="getQueryActionIcon(action.result_handler)" /></el-icon>
                {{ isQueryActionLoading(action.code) ? '查询中...' : action.name }}
              </el-button>
              <div v-if="visibleDeviceQueryActions.length === 0" class="cdd-muted">暂无可用查询服务</div>
            </div>
          </section>

          <section class="cdd-panel">
            <div class="cdd-panel__title">快捷操作</div>
            <div class="cdd-action-stack">
              <el-button type="danger" plain @click="clearAllSelections">清空选项</el-button>
            </div>
          </section>

          <CheckTemplateMobilePanel
            :groups="checkTemplateGroups"
            :get-value="getTemplateFieldValue"
            :get-options="getTemplateFieldOptions"
            @change="handleTemplateFieldChange"
          />

          <section class="cdd-panel cdd-result-panel">
            <div class="cdd-panel__title">卖家质检结果</div>
            <el-form-item prop="check_result_seller" class="cdd-result-field"><el-input v-model="deviceForm.check_result_seller" type="textarea" :rows="6" placeholder="由质检选项自动生成，也可手动编辑..." maxlength="500" show-word-limit resize="none" /></el-form-item>
          </section>

          <section class="cdd-panel cdd-result-panel">
            <div class="cdd-panel__title"><span>买家质检结果</span><el-button type="primary" link size="small" @click="syncSellerResultToBuyer"><el-icon><CopyDocument /></el-icon> 同步</el-button></div>
            <el-form-item prop="check_result_buyer" class="cdd-result-field"><el-input v-model="deviceForm.check_result_buyer" type="textarea" :rows="5" placeholder="可同步卖家内容后单独调整..." maxlength="500" show-word-limit resize="none" /></el-form-item>
          </section>

          <section class="cdd-panel cdd-price-panel">
            <div class="cdd-panel__title">定价</div>
            <el-form-item prop="final_price" class="cdd-price-field">
              <template #label><span>回收定价 <em v-if="deviceData.initial_price">参考 ¥{{ deviceData.initial_price }}</em></span></template>
              <el-input-number v-model="deviceForm.final_price" :step="10" :precision="2" :min="0" :max="99999" controls-position="right" class="cdd-price-input" />
            </el-form-item>
            <el-form-item label="扣费说明" prop="remark" class="cdd-remark-field"><el-input v-model="deviceForm.remark" placeholder="扣费原因、特殊备注..." maxlength="200" clearable /></el-form-item>
          </section>

          <section class="cdd-panel cdd-upload-panel">
            <el-collapse>
              <el-collapse-item>
                <template #title><span class="cdd-upload-title">质检图片  卖家 {{ checkImageCount }}/{{ maxCheckImageCount }}  买家 {{ buyerCheckImageCount }}/{{ buyerMaxCheckImageCount }}</span></template>
                <div class="cdd-camera-row"><el-button type="primary" plain size="small" :loading="cameraUploading" :disabled="cameraUploading || checkImageCount >= maxCheckImageCount" @click="openCameraCapture">{{ cameraUploading ? '上传中...' : '拍照上传' }}</el-button><input ref="cameraInputRef" type="file" accept="image/*" capture="environment" multiple class="hidden" @change="handleCameraFilesChange" /></div>
                <div class="cdd-upload-block"><div class="cdd-upload-label">卖家图片</div><upload-image v-model="deviceForm.check_images" :limit="9" /></div>
                <div class="cdd-upload-block"><div class="cdd-upload-label">买家图片 <el-button type="primary" link size="small" @click="syncSellerImagesToBuyer">同步卖家</el-button></div><div class="cdd-camera-row"><el-button type="primary" plain size="small" :loading="buyerCameraUploading" :disabled="buyerCameraUploading || buyerCheckImageCount >= buyerMaxCheckImageCount" @click="openBuyerCameraCapture">{{ buyerCameraUploading ? '上传中...' : '拍照上传' }}</el-button><input ref="buyerCameraInputRef" type="file" accept="image/*" capture="environment" multiple class="hidden" @change="handleBuyerCameraFilesChange" /></div><upload-image v-model="deviceForm.check_images_buyer" :limit="6" /></div>
              </el-collapse-item>
            </el-collapse>
          </section>
        </div>

        <div v-else class="cdd-main">
          <aside class="cdd-side cdd-side--left">
            <div class="cdd-panel cdd-summary-panel">
              <div class="cdd-panel__title">设备摘要</div>
              <div class="cdd-summary-list">
                <div v-for="field in deviceSummaryFields" :key="field.field_key">
                  <span>{{ field.field_name }}</span>
                  <strong>{{ formatSummaryFieldValue(field) }}</strong>
                </div>
                <div v-if="!deviceSummaryFields.length" class="cdd-summary-empty">未配置摘要字段</div>
              </div>
            </div>

            <div class="cdd-panel">
              <div class="cdd-panel__title">联网查询</div>
              <div class="cdd-query-stack">
                <el-button v-for="action in visibleDeviceQueryActions" :key="action.code" size="small" plain :loading="isQueryActionLoading(action.code)" :disabled="!deviceForm.imei" @click="runDeviceQueryAction(action)">
                  <el-icon v-if="!isQueryActionLoading(action.code)"><component :is="getQueryActionIcon(action.result_handler)" /></el-icon>
                  {{ isQueryActionLoading(action.code) ? '查询中...' : action.name }}
                </el-button>
                <div v-if="visibleDeviceQueryActions.length === 0" class="cdd-muted">暂无可用查询服务</div>
              </div>
            </div>

            <div class="cdd-panel">
              <div class="cdd-panel__title">快捷操作</div>
              <div class="cdd-action-stack">
                <el-button size="small" type="danger" plain @click="clearAllSelections">清空选项</el-button>
              </div>
            </div>

            <div class="cdd-panel cdd-image-summary">
              <div class="cdd-panel__title">图片</div>
              <div class="cdd-summary-list">
                <div><span>卖家图片</span><strong>{{ checkImageCount }}/{{ maxCheckImageCount }}</strong></div>
                <div><span>买家图片</span><strong>{{ buyerCheckImageCount }}/{{ buyerMaxCheckImageCount }}</strong></div>
              </div>
            </div>
          </aside>

          <CheckTemplateSchemaPanel
            :groups="checkTemplateGroups"
            :get-value="getTemplateFieldValue"
            :get-options="getTemplateFieldOptions"
            @change="handleTemplateFieldChange"
          />

          <aside class="cdd-side cdd-side--right">
            <div class="cdd-right-scroll">
              <section class="cdd-panel cdd-result-panel">
                <div class="cdd-panel__title">卖家质检结果</div>
                <el-form-item prop="check_result_seller" class="cdd-result-field"><el-input v-model="deviceForm.check_result_seller" type="textarea" :rows="7" placeholder="由质检选项自动生成，也可手动编辑..." maxlength="500" show-word-limit resize="none" /></el-form-item>
              </section>

              <section class="cdd-panel cdd-result-panel">
                <div class="cdd-panel__title"><span>买家质检结果</span><el-button type="primary" link size="small" @click="syncSellerResultToBuyer"><el-icon><CopyDocument /></el-icon> 同步</el-button></div>
                <el-form-item prop="check_result_buyer" class="cdd-result-field"><el-input v-model="deviceForm.check_result_buyer" type="textarea" :rows="5" placeholder="可同步卖家内容后单独调整..." maxlength="500" show-word-limit resize="none" /></el-form-item>
              </section>

              <section class="cdd-panel cdd-price-panel">
                <div class="cdd-panel__title">定价</div>
                <el-form-item prop="final_price" class="cdd-price-field">
                  <template #label><span>回收定价 <em v-if="deviceData.initial_price">参考 ¥{{ deviceData.initial_price }}</em></span></template>
                  <el-input-number v-model="deviceForm.final_price" :step="10" :precision="2" :min="0" :max="99999" controls-position="right" class="cdd-price-input" />
                </el-form-item>
                <el-form-item label="扣费说明" prop="remark" class="cdd-remark-field"><el-input v-model="deviceForm.remark" placeholder="扣费原因、特殊备注..." maxlength="200" clearable /></el-form-item>
              </section>

              <section class="cdd-panel cdd-upload-panel">
                <el-collapse>
                  <el-collapse-item>
                    <template #title><span class="cdd-upload-title">质检图片  卖家 {{ checkImageCount }}/{{ maxCheckImageCount }}  买家 {{ buyerCheckImageCount }}/{{ buyerMaxCheckImageCount }}</span></template>
                    <div v-if="isMobile" class="cdd-camera-row"><el-button type="primary" plain size="small" :loading="cameraUploading" :disabled="cameraUploading || checkImageCount >= maxCheckImageCount" @click="openCameraCapture">{{ cameraUploading ? '上传中...' : '拍照上传' }}</el-button><input ref="cameraInputRef" type="file" accept="image/*" capture="environment" multiple class="hidden" @change="handleCameraFilesChange" /></div>
                    <div class="cdd-upload-block"><div class="cdd-upload-label">卖家图片</div><upload-image v-model="deviceForm.check_images" :limit="9" /></div>
                    <div class="cdd-upload-block"><div class="cdd-upload-label">买家图片 <el-button type="primary" link size="small" @click="syncSellerImagesToBuyer">同步卖家</el-button></div><div v-if="isMobile" class="cdd-camera-row"><el-button type="primary" plain size="small" :loading="buyerCameraUploading" :disabled="buyerCameraUploading || buyerCheckImageCount >= buyerMaxCheckImageCount" @click="openBuyerCameraCapture">{{ buyerCameraUploading ? '上传中...' : '拍照上传' }}</el-button><input ref="buyerCameraInputRef" type="file" accept="image/*" capture="environment" multiple class="hidden" @change="handleBuyerCameraFilesChange" /></div><upload-image v-model="deviceForm.check_images_buyer" :limit="6" /></div>
                  </el-collapse-item>
                </el-collapse>
              </section>
            </div>
          </aside>
        </div>
      </el-form>
    </div>

    <template #footer>
      <div class="cdd-footer">
        <span class="cdd-footer__info">已填质检项：{{ checkedCount }}</span>
        <div class="cdd-footer__btns">
          <el-button class="cdd-footer__cancel" size="large" @click="handleCancel">取消</el-button>
          <el-button class="cdd-return-btn" type="danger" plain size="large" :disabled="savingDraft || submitting" @click="handleReturnDevice">退回设备</el-button>
          <el-button type="warning" size="large" :loading="savingDraft" @click="handleSaveDraft">{{ savingDraft ? '暂存中...' : '暂存草稿' }}</el-button>
          <el-button type="primary" size="large" :loading="submitting" @click="handleConfirm"><el-icon v-if="!submitting"><Check /></el-icon>{{ submitting ? '提交中...' : '完成质检' }}</el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed, nextTick, onMounted, onBeforeUnmount, toRef } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance, type FormRules } from 'element-plus'
import {
  Cellphone, Edit, Postcard, Aim, Monitor, Check, Close, Headset, Lock, CopyDocument
} from '@element-plus/icons-vue'

import { queryDeviceByService } from '@/addon/hsx_recycle/api/device_query_api'
import { getDeviceQueryConfigList } from '@/addon/hsx_recycle/api/device_query_config'
import { getCheckTemplateAll, getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check_template'
import {
  normalizeInfo,
  useCheckMeta,
  type CheckMetaPayload,
  type CheckOptionsGroup,
  type CheckTemplateField
} from './composables/useCheckMeta'
import { useCameraUpload } from './composables/useCameraUpload'
import CheckTemplateSchemaPanel from './CheckTemplateSchemaPanel.vue'
import CheckTemplateMobilePanel from './CheckTemplateMobilePanel.vue'
import CheckTemplateSelector from './CheckTemplateSelector.vue'

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
  check_template_id?: number | string
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

const emit = defineEmits(['update:visible', 'confirm', 'cancel', 'save-draft', 'return-device'])

const checkSchemaLoading = ref(false)
const checkTemplateLoading = ref(false)
const checkTemplateInfo = ref<any>(null)
const checkTemplateGroups = ref<any[]>([])
const checkTemplateList = ref<any[]>([])
const selectedCheckTemplateId = ref<number>(0)
const knownFieldOptionKeys = ['screen_id', 'indisplay_id', 'appearance_id', 'function_ids', 'fix_ids'] as const
type KnownFieldOptionKey = typeof knownFieldOptionKeys[number]
type KnownOptionBucket = 'screen' | 'indisplay' | 'appearance' | 'function' | 'fix'

const schemaFieldKeyMap: Record<KnownFieldOptionKey, KnownOptionBucket> = {
  screen_id: 'screen',
  indisplay_id: 'indisplay',
  appearance_id: 'appearance',
  function_ids: 'function',
  fix_ids: 'fix'
}

const builtInFieldAccessors: Record<string, {
  get: () => any
  set: (value: any) => void
}> = {
  capacity: { get: () => deviceForm.capacity, set: value => { deviceForm.capacity = value || '' } },
  color: { get: () => deviceForm.color, set: value => { deviceForm.color = value || '' } },
  system_version: { get: () => deviceForm.system_version, set: value => { deviceForm.system_version = value || '' } },
  warranty_info: { get: () => deviceForm.warranty_info, set: value => { deviceForm.warranty_info = value || '' } },
  battery: { get: () => templateSelections.battery, set: value => { templateSelections.battery = normalizeOptionalNumber(value) } },
  battery_num: { get: () => templateSelections.battery_num, set: value => { templateSelections.battery_num = normalizeOptionalNumber(value) } },
  activation_lock: { get: () => templateSelections.activationLock, set: value => { templateSelections.activationLock = !!value } },
  mdm_lock: { get: () => templateSelections.mdmLock, set: value => { templateSelections.mdmLock = !!value } },
  screen_id: { get: () => templateSelections.screenId, set: value => { templateSelections.screenId = normalizeStringValue(value) } },
  indisplay_id: { get: () => templateSelections.indisplayId, set: value => { templateSelections.indisplayId = normalizeStringValue(value) } },
  appearance_id: { get: () => templateSelections.appearanceId, set: value => { templateSelections.appearanceId = normalizeStringValue(value) } },
  function_ids: { get: () => templateSelections.functionIds, set: value => { templateSelections.functionIds = normalizeStringArray(value) } },
  fix_ids: { get: () => templateSelections.fixIds, set: value => { templateSelections.fixIds = normalizeStringArray(value) } }
}

const normalizeSchemaOption = (option: any) => ({
  name: option.name || option.label || option.option_label || '',
  label: option.label || option.name || option.option_label || '',
  value: String(option.value ?? option.option_value ?? ''),
  sort: Number(option.sort || 0),
  memo: option.memo || '',
  extra_config: option.extra_config || {}
})

const fieldConfigByKey = computed<Record<string, CheckTemplateField>>(() => {
  const map: Record<string, CheckTemplateField> = {}
  checkTemplateGroups.value.forEach((group: any) => {
    ;(group.fields || []).forEach((field: any) => {
      map[field.field_key] = {
        ...field,
        options: (field.options || []).map(normalizeSchemaOption)
      }
    })
  })
  return map
})

const checkTemplateFields = computed<CheckTemplateField[]>(() => {
  return checkTemplateGroups.value.flatMap((group: any) => group.fields || [])
})

const getFieldExtraConfig = (field: CheckTemplateField) => {
  const config = (field as any).extra_config
  if (!config) return {}
  if (typeof config === 'string') {
    try {
      const parsed = JSON.parse(config)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      return {}
    }
  }
  return typeof config === 'object' ? config : {}
}

const isSummaryField = (field: CheckTemplateField) => {
  const config = getFieldExtraConfig(field)
  return Number(config.summary_visible || config.show_in_summary || 0) === 1
}

const fallbackSummaryKeys = ['capacity', 'color', 'system_version', 'warranty_info']

const deviceSummaryFields = computed<CheckTemplateField[]>(() => {
  const visibleFields = checkTemplateFields.value.filter(field => Number((field as any).is_show ?? 1) === 1)
  const configuredFields = visibleFields
    .filter(isSummaryField)
    .sort((a: any, b: any) => Number(a.sort || 0) - Number(b.sort || 0))
    .slice(0, 5)

  if (configuredFields.length) return configuredFields

  return fallbackSummaryKeys
    .map(key => fieldConfigByKey.value[key])
    .filter(Boolean)
    .slice(0, 5)
})

const checkDictOptions = computed<CheckOptionsGroup>(() => {
  const options: CheckOptionsGroup = {
    screen: [],
    indisplay: [],
    appearance: [],
    function: [],
    fix: []
  }
  Object.entries(schemaFieldKeyMap).forEach(([fieldKey, optionKey]) => {
    const field = fieldConfigByKey.value[fieldKey]
    if (field?.options?.length) {
      options[optionKey] = field.options as any
    }
  })
  return options
})

const normalizeOptionalNumber = (value: any): number | undefined => {
  if (value === '' || value === null || value === undefined) return undefined
  const parsed = Number(value)
  return Number.isNaN(parsed) ? undefined : parsed
}

const normalizeStringValue = (value: any): string => {
  if (value === null || value === undefined) return ''
  return String(value)
}

const normalizeStringArray = (value: any): string[] => {
  if (!Array.isArray(value)) return []
  return value.map(item => normalizeStringValue(item)).filter(Boolean)
}

const getTemplateFieldValue = (field: CheckTemplateField) => {
  return builtInFieldAccessors[field.field_key]?.get() ?? templateSelections.customFields[field.field_key]
}

const getTemplateFieldOptions = (field: CheckTemplateField) => {
  const optionKey = schemaFieldKeyMap[field.field_key as KnownFieldOptionKey]
  if (optionKey && checkDictOptions.value[optionKey]?.length) return checkDictOptions.value[optionKey]
  return field.options || []
}

const formatSummaryFieldValue = (field: CheckTemplateField) => {
  const value = getTemplateFieldValue(field)
  if (value === '' || value === null || value === undefined || (Array.isArray(value) && !value.length)) return '-'

  const options = getTemplateFieldOptions(field)
  if (options.length) {
    const labelMap = new Map(options.map((option: any) => [String(option.value), option.name || option.label || option.value]))
    if (Array.isArray(value)) {
      const labels = value.map(item => labelMap.get(String(item)) || String(item)).filter(Boolean)
      return labels.length ? labels.join('、') : '-'
    }
    return labelMap.get(String(value)) || String(value)
  }

  if (typeof value === 'boolean') return value ? '是' : '否'
  return String(value)
}

const handleTemplateFieldChange = (field: CheckTemplateField, value: any) => {
  const accessor = builtInFieldAccessors[field.field_key]
  if (accessor) {
    accessor.set(value)
  } else {
    templateSelections.customFields[field.field_key] = value
  }
  updateCheckResult()
}

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const modelInputRef = ref()
const isEditingDeviceInfo = ref(false)
const isMobile = ref(false)
const dialogWidth = computed(() => isMobile.value ? '96vw' : 'min(1280px, 96vw)')

// 保存编辑前的原始数据
const originalDeviceInfo = ref({ model: '', imei: '' })

const activationLockInfo = ref<any>(null)
const mdmInfo = ref<any>(null)
const deviceQueryServices = ref<any[]>([])
const deviceQueryLoadingMap = ref<Record<string, boolean>>({})

const visibleDeviceQueryActions = computed(() => {
  return deviceQueryServices.value
    .filter(service => (
      Number(service.enabled) === 1
      && Number(service.show_in_check) === 1
      && Number(service.enabled_mapping_count ?? (service.mapping_count || 0)) > 0
    ))
    .sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
})

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
  updateCheckResult, clearAllSelections,
  restoreFromDevice
} = useCheckMeta({
  dictOptions: checkDictOptions,
  deviceForm,
  fieldConfigByKey,
  templateInfo: computed(() => checkTemplateInfo.value),
  shouldRestoreMeta: (meta) => {
    const currentTemplateId = checkTemplateInfo.value?.id
    return !meta.template_id || !currentTemplateId || String(meta.template_id) === String(currentTemplateId)
  }
})

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

function parsePrice(value: any): number | undefined {
  if (typeof value === 'number') return value
  if (typeof value === 'string') {
    const parsed = parseFloat(value)
    return Number.isNaN(parsed) ? undefined : parsed
  }
  return undefined
}

const updateDeviceMode = () => { isMobile.value = window.innerWidth <= 980 }

const resolveDeviceTemplateId = (device: DeviceInfo) => {
  const info = normalizeInfo(device.info)
  const rawId = device.check_template_id || info?.check_meta?.template_id || device.check_meta?.template_id || 0
  const templateId = Number(rawId)
  return Number.isNaN(templateId) ? 0 : templateId
}

const loadCheckTemplateList = async () => {
  checkTemplateLoading.value = true
  try {
    const res: any = await getCheckTemplateAll({ status: 1 })
    checkTemplateList.value = Array.isArray(res.data) ? res.data : (res.data?.list || [])
  } catch (error) {
    checkTemplateList.value = []
  } finally {
    checkTemplateLoading.value = false
  }
}

const loadCheckTemplateSchema = async (templateId = selectedCheckTemplateId.value, restoreDevice = true) => {
  checkSchemaLoading.value = true
  try {
    const params = templateId ? { template_id: templateId } : {}
    const res: any = await getCheckTemplateSchema(params)
    const payload = res.data || {}
    checkTemplateInfo.value = payload.template || null
    checkTemplateGroups.value = payload.groups || []
    selectedCheckTemplateId.value = Number(payload.template?.id || templateId || 0)
    if (restoreDevice) {
      restoreFromDevice(deviceData.value)
    } else {
      clearAllSelections()
    }
  } catch (error) {
    checkTemplateInfo.value = null
    checkTemplateGroups.value = []
    selectedCheckTemplateId.value = 0
  } finally {
    checkSchemaLoading.value = false
  }
}

const handleCheckTemplateChange = async (templateId: number) => {
  const previousTemplateId = Number(checkTemplateInfo.value?.id || 0)
  if (!templateId || templateId === previousTemplateId) return

  try {
    await ElMessageBox.confirm(
      '切换模板后会重新加载质检项，已填写的模板选项将清空，设备型号、IMEI、容量、颜色等基础信息会保留。',
      '切换质检模板',
      {
        confirmButtonText: '确认切换',
        cancelButtonText: '取消',
        type: 'warning'
      }
    )
    await loadCheckTemplateSchema(templateId, false)
    ElMessage.success('质检模板已切换')
  } catch (error: any) {
    selectedCheckTemplateId.value = previousTemplateId
  }
}

const loadDeviceQueryActions = async () => {
  try {
    const res = await getDeviceQueryConfigList({ page: 1, limit: 100 })
    const payload = res.data?.list || res.data?.config ? res.data : (res.data?.data || {})
    deviceQueryServices.value = payload.list || payload.data || []
  } catch {
    deviceQueryServices.value = []
  }
}

const isQueryActionLoading = (code: string) => {
  return !!deviceQueryLoadingMap.value[code]
}

const getQueryActionIcon = (handler: string) => {
  if (handler === 'coverage') return Headset
  if (handler === 'activationlock') return Lock
  if (handler === 'mdm') return Monitor
  return Headset
}

const runDeviceQueryAction = async (action: any) => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码')
    return
  }
  const serviceCode = action.code
  deviceQueryLoadingMap.value = { ...deviceQueryLoadingMap.value, [serviceCode]: true }
  try {
    const res = await queryDeviceByService({
      service_code: serviceCode,
      query_code: deviceForm.imei,
      query_type: action.query_type || 'imei'
    })
    applyDeviceQueryResult(action, res.data?.data || res.data || {})
  } catch (error: any) {
    ElMessage.error(error?.message || `${action.name || '设备查询'}失败，请检查设备查询配置`)
  } finally {
    deviceQueryLoadingMap.value = { ...deviceQueryLoadingMap.value, [serviceCode]: false }
  }
}

const unwrapDeviceQueryData = (payload: any) => {
  if (payload?.data?.data) return payload.data.data
  if (payload?.data) return payload.data
  return payload || {}
}

const applyDeviceQueryResult = (action: any, payload: any) => {
  const data = unwrapDeviceQueryData(payload)
  if (!data || Object.keys(data).length === 0) {
    ElMessage.warning(`${action.name || '设备查询'}未查询到有效数据`)
    return
  }

  deviceForm.info = {
    ...normalizeInfo(deviceForm.info),
    [action.code]: data,
    last_device_query: {
      service_code: action.code,
      service_name: action.name,
      result_handler: action.result_handler || 'generic',
      data
    }
  }

  if (action.result_handler === 'coverage') {
    applyCoverageData(data)
    ElMessage.success(`${action.name}已自动填入`)
    return
  }

  if (action.result_handler === 'activationlock') {
    activationLockInfo.value = data
    templateSelections.activationLock = data.locked === true || data.fmi === 'On' || data.activation_lock === 'On' || data.activation_lock === '有锁'
    updateCheckResult()
    ElMessage.success(`${action.name}：${templateSelections.activationLock ? '已开启' : '未开启'}，已自动填入`)
    return
  }

  if (action.result_handler === 'mdm') {
    mdmInfo.value = data
    templateSelections.mdmLock = data.locked === true || data.mdm === 'On' || data.mdm === true
    updateCheckResult()
    ElMessage.success(`${action.name}：${templateSelections.mdmLock ? '已开启' : '未开启'}，已自动填入`)
    return
  }

  ElMessage.success(`${action.name || '设备查询'}查询成功`)
}

const applyCoverageData = (data: any) => {
  const { capacity, color, modelDisplay } = parseCoverageFields(data)
  const fullModel = [modelDisplay, capacity, color].filter(Boolean).join(' ')
  if (fullModel) {
    deviceData.value.model = fullModel
    deviceForm.model = fullModel
  }
  deviceForm.info = { ...normalizeInfo(deviceForm.info), ...data }
  deviceForm.info = getSubmitInfo()
  if (capacity) deviceForm.capacity = capacity
  if (color) deviceForm.color = color
  if (data.osVersion) deviceForm.system_version = data.osVersion
  if (data.coverage) {
    deviceForm.warranty_info = parseCoverageStatus(data.coverage)
  } else if (data.coverage_status || data.coverage_date) {
    deviceForm.warranty_info = parseCoverageStatus({
      status: data.coverage_status,
      date: data.coverage_date
    })
  }
  updateCheckResult()
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

// 从接口返回数据中兼容提取 capacity / color / model
const parseCoverageFields = (d: any) => {
  // capacity / color：安卓品牌放在 product 子对象，苹果直接在顶层
  const capacity = d.capacity || d.product?.capacity || ''
  const color    = d.color    || d.product?.color    || ''
  // model：接口返回的 d.model 是最权威的型号名（OPPO Find X9 Pro / iPhone 16 Pro 等）
  // product.model 仅作兜底（部分品牌顶层 model 为空时）
  const modelDisplay = d.model || d.product?.model || ''
  return { capacity, color, modelDisplay }
}

// 兼容多品牌的保修状态解析
const parseCoverageStatus = (coverage: any): string => {
  if (!coverage) return ''
  const status = (coverage.status || '').trim()
  const date   = (coverage.date   || '').trim()
  if (status === 'Out Of Warranty') return '过保'
  if (status === 'Not Activated' || (!date && !status)) return '未激活'
  if (status === 'In Warranty' || status === 'Active') {
    return date ? `保 ${date}` : '在保'
  }
  // 其他情况：有日期就显示日期，否则显示原始 status
  return date || status || '在保'
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

const handleReturnDevice = () => {
  if (savingDraft.value || submitting.value) return
  if (!deviceData.value?.id) {
    ElMessage.warning('设备信息异常，无法退回')
    return
  }
  emit('return-device', deviceData.value.id)
}

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
    model: deviceForm.model,
    check_template_id: selectedCheckTemplateId.value || checkTemplateInfo.value?.id || 0,
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
  deviceQueryLoadingMap.value = {}
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
  selectedCheckTemplateId.value = resolveDeviceTemplateId(device)
  const restoredInfo = normalizeInfo(device.info)
  deviceForm.capacity = device.capacity || restoredInfo.capacity || ''
  deviceForm.color = device.color || restoredInfo.color || ''
  nextTick(() => { formRef.value?.clearValidate() })
}

watch(() => props.visible, (val) => { dialogVisible.value = val })
watch(() => props.device, async (val) => {
  initializeFormFromDevice(val)
  if (dialogVisible.value) await loadCheckTemplateSchema(selectedCheckTemplateId.value)
}, { deep: true })
watch(dialogVisible, async (val) => {
  emit('update:visible', val)
  if (!val) return
  initializeFormFromDevice(props.device)
  if (!checkTemplateList.value.length) await loadCheckTemplateList()
  await loadCheckTemplateSchema(selectedCheckTemplateId.value)
})
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
  initializeFormFromDevice(props.device)
  await loadCheckTemplateList()
  if (dialogVisible.value) await loadCheckTemplateSchema(selectedCheckTemplateId.value)
  await loadDeviceQueryActions()
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
   自定义质检项
   ============================================================ */
.cdd-custom-section {
  overflow: visible;
}

.cdd-custom-groups {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 10px 12px 12px;
}

.cdd-custom-group {
  padding-bottom: 10px;
  border-bottom: 1px solid #f1f5f9;

  &:last-child {
    padding-bottom: 0;
    border-bottom: none;
  }
}

.cdd-custom-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.cdd-custom-field {
  min-width: 0;

  &__label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 5px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
  }

  &__unit {
    color: #94a3b8;
    font-weight: 400;
  }

  &__number,
  &__select {
    width: 100%;
  }

  :deep(.el-radio-group),
  :deep(.el-checkbox-group) {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  :deep(.el-radio-button__inner),
  :deep(.el-checkbox-button__inner) {
    border-radius: 4px;
    border-left: 1px solid var(--el-border-color);
  }
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
   工作台布局覆盖
   ============================================================ */
.cdd-workbench-dialog {
  :deep(.el-dialog) {
    height: min(94vh, 900px);
    max-height: 94vh;
    display: flex;
    flex-direction: column;
    border-radius: 10px;
  }

  :deep(.el-dialog__header) {
    flex: 0 0 auto;
    padding: 12px 18px;
    background: #fff;
    border-bottom: 1px solid #e5e7eb;

    .el-dialog__title {
      color: #111827;
      font-size: 16px;
      font-weight: 700;
    }
  }

  :deep(.el-dialog__headerbtn .el-dialog__close) {
    color: #6b7280;

    &:hover {
      color: #111827;
    }
  }

  :deep(.el-dialog__body) {
    flex: 1;
    min-height: 0;
    padding: 0;
    overflow: hidden;
    display: block;
  }

  :deep(.el-dialog__footer) {
    flex: 0 0 auto;
    border-top: 1px solid #e5e7eb;
  }
}

.cdd-workbench {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
}

.cdd-main-form {
  flex: 1 1 auto;
  height: calc(min(94vh, 900px) - 64px - 69px - 59px);
  min-height: 420px;
  overflow: hidden;
}

.cdd-topbar {
  flex: 0 0 auto;
  min-height: 68px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 14px;
  padding: 10px 16px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}

.cdd-topbar__identity {
  min-width: min(100%, 420px);
  display: grid;
  grid-template-columns: minmax(260px, 1fr) auto;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.cdd-model-title {
  overflow: hidden;
  color: #111827;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.3;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cdd-imei-line {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #6b7280;
  font-size: 12px;

  span {
    padding: 2px 6px;
    border-radius: 4px;
    background: #f3f4f6;
    color: #64748b;
    font-weight: 700;
  }

  strong {
    color: #111827;
    font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
  }
}

.cdd-topbar__model-input {
  min-width: 280px;
}

.cdd-topbar__imei-input {
  width: 280px;
}

.cdd-topbar__actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.cdd-main {
  height: 100%;
  min-height: 0;
  display: grid;
  grid-template-columns: 230px minmax(0, 1fr) 330px;
  gap: 10px;
  padding: 10px;
  overflow: hidden;
}

.cdd-side,
.cdd-center {
  min-width: 0;
  min-height: 0;
  height: 100%;
}

.cdd-side {
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: hidden;
}

.cdd-side--left {
  overflow-y: auto;
  overscroll-behavior: contain;
}

.cdd-side--right {
  display: flex;
  flex-direction: column;
}

.cdd-panel,
.cdd-work-section {
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
}

.cdd-panel {
  padding: 12px;
}

.cdd-panel__title,
.cdd-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 10px;
  color: #111827;
  font-size: 13px;
  font-weight: 700;

  em {
    color: #6b7280;
    font-style: normal;
    font-size: 12px;
    font-weight: 500;
  }
}

.cdd-summary-list {
  display: grid;
  gap: 8px;

  div {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    align-items: start;
    gap: 8px;
    color: #6b7280;
    font-size: 12px;
  }

  strong {
    min-width: 0;
    color: #111827;
    font-weight: 600;
    line-height: 1.35;
    overflow-wrap: anywhere;
  }
}

.cdd-query-stack,
.cdd-action-stack {
  display: grid;
  gap: 8px;

  .el-button {
    justify-content: flex-start;
    margin-left: 0;
  }
}

.cdd-muted {
  color: #9ca3af;
  font-size: 12px;
}

.cdd-center {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  overflow: hidden;
}

.cdd-anchor-bar {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px;
  margin-bottom: 8px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 8px;

  button {
    padding: 5px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: #4b5563;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    text-decoration: none;
    cursor: pointer;

    &:hover {
      color: #2563eb;
      background: #eff6ff;
    }
  }
}

.cdd-center-scroll,
.cdd-right-scroll {
  min-height: 0;
  height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;

  &::-webkit-scrollbar {
    width: 6px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 6px;
    background: #cbd5e1;
  }
}

.cdd-center-scroll {
  display: block;
  gap: 10px;
  padding-right: 4px;
}

.cdd-right-scroll {
  display: block;
  padding-right: 4px;
}

.cdd-center-scroll > .cdd-work-section + .cdd-work-section,
.cdd-right-scroll > .cdd-panel + .cdd-panel {
  margin-top: 10px;
}

.cdd-work-section {
  padding: 12px;
  margin-bottom: 0;
  scroll-margin-top: 8px;
}

.cdd-info-grid {
  gap: 10px;
}

.cdd-work-section .cdd-info-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.cdd-work-section .cdd-info-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 0;
  border: 0;
}

.cdd-info-cell__label,
.cdd-custom-field__label {
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
}

.cdd-inline-control {
  display: flex;
  align-items: center;
  gap: 6px;

  .el-input,
  .el-input-number {
    flex: 1;
    min-width: 0;
  }

  span {
    flex: 0 0 auto;
    color: #94a3b8;
    font-size: 12px;
  }
}

.cdd-info-cell--switch {
  justify-content: space-between;
}

.cdd-work-section .cdd-sub-section {
  padding: 10px;
  border: 1px solid #eef2f7;
  border-radius: 6px;
  background: #fbfdff;
}

.cdd-work-section .cdd-sub-section--done {
  background: #f0fdf4;
  border-color: #bbf7d0;
}

.cdd-work-section .cdd-sub-cols {
  gap: 10px;
}

.cdd-sub-header em {
  color: #6b7280;
  font-style: normal;
  font-size: 11px;
  font-weight: 600;
}

.cdd-tag-grid {
  max-height: 128px;
  overflow-y: auto;
  padding-right: 2px;
}

.cdd-tag-grid--scroll {
  max-height: 172px;
}

.cdd-check-tag {
  border-radius: 5px;
  user-select: none;
}

.cdd-custom-section {
  overflow: hidden;
}

.cdd-custom-groups {
  padding: 0;
}

.cdd-result-field,
.cdd-price-field,
.cdd-remark-field {
  margin-bottom: 0 !important;
}

.cdd-result-field :deep(.el-textarea__inner) {
  font-size: 12px;
  line-height: 1.55;
}

.cdd-price-input {
  width: 100%;
}

.cdd-price-field :deep(.el-form-item__label) {
  font-size: 12px;
  font-weight: 700;

  em {
    margin-left: 6px;
    color: #9ca3af;
    font-style: normal;
    font-weight: 400;
  }
}

.cdd-upload-panel {
  padding: 0;
  overflow: hidden;

  :deep(.el-collapse) {
    border: 0;
  }

  :deep(.el-collapse-item__header) {
    height: 42px;
    padding: 0 12px;
    border-bottom-color: #e5e7eb;
    font-weight: 700;
  }

  :deep(.el-collapse-item__wrap) {
    border-bottom: 0;
  }

  :deep(.el-collapse-item__content) {
    max-height: 240px;
    overflow-y: auto;
    padding: 10px 12px 12px;
  }
}

.cdd-upload-title {
  color: #111827;
  font-size: 13px;
}

.cdd-upload-block + .cdd-upload-block {
  margin-top: 12px;
}

.cdd-upload-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  color: #4b5563;
  font-size: 12px;
  font-weight: 700;
}

.cdd-footer {
  min-height: 38px;
}

/* ============================================================
   响应式
   ============================================================ */
@media (max-width: 980px) {
  .check-device-dialog {
    :deep(.el-dialog) {
      width: 96vw !important;
      height: 92vh;
    }
  }

  .cdd-topbar {
    align-items: flex-start;
    flex-direction: column;
  }

  .cdd-topbar__identity {
    width: 100%;
    grid-template-columns: 1fr;
  }

  .cdd-topbar__imei-input,
  .cdd-topbar__model-input {
    width: 100%;
    min-width: 0;
  }

  .cdd-main {
    grid-template-columns: 1fr;
    height: auto;
    overflow-y: auto;
  }

  .cdd-main-form {
    height: auto;
    min-height: 0;
    overflow: visible;
  }

  .cdd-side--left,
  .cdd-side--right,
  .cdd-center {
    height: auto;
    overflow: visible;
  }

  .cdd-center-scroll,
  .cdd-right-scroll {
    overflow: visible;
  }

  .cdd-anchor-bar {
    position: sticky;
    top: 0;
    z-index: 2;
    overflow-x: auto;
  }

  .cdd-work-section .cdd-info-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
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
  .cdd-custom-grid { grid-template-columns: 1fr; }

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
    &__btns {
      flex-direction: column;

      .el-button {
        width: 100%;
        margin-left: 0;
      }
    }
  }
}

/* ============================================================
   质检工作台统一样式
   放在样式末尾作为最终规范层，覆盖上方历史样式差异。
   ============================================================ */
$cdd-border: #e5e7eb;
$cdd-soft-border: #eef2f7;
$cdd-text: #111827;
$cdd-text-secondary: #4b5563;
$cdd-text-muted: #94a3b8;
$cdd-bg: #f5f7fa;
$cdd-panel-bg: #ffffff;
$cdd-primary: #2563eb;
$cdd-success: #16a34a;
$cdd-danger: #dc2626;
$cdd-warning: #d97706;

.check-device-dialog.cdd-workbench-dialog {
  :deep(.el-dialog) {
    width: min(1240px, 96vw);
    height: min(90vh, 860px);
    max-height: 860px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    border-radius: 10px;
    box-shadow: 0 18px 48px rgba(15, 23, 42, 0.18);
  }

  :deep(.el-dialog__header) {
    flex: 0 0 auto;
    padding: 12px 18px;
    background: $cdd-panel-bg;
    border-bottom: 1px solid $cdd-border;

    .el-dialog__title {
      color: $cdd-text;
      font-size: 16px;
      font-weight: 700;
      line-height: 1.4;
    }
  }

  :deep(.el-dialog__headerbtn) {
    top: 11px;
    right: 14px;
    width: 32px;
    height: 32px;

    .el-dialog__close {
      color: #6b7280;
      font-size: 17px;

      &:hover {
        color: $cdd-text;
      }
    }
  }

  :deep(.el-dialog__body) {
    flex: 1 1 auto;
    min-height: 0;
    display: block;
    padding: 0;
    overflow: hidden;
    background: $cdd-bg;
  }

  :deep(.el-dialog__footer) {
    flex: 0 0 auto;
    padding: 10px 16px;
    background: $cdd-panel-bg;
    border-top: 1px solid $cdd-border;
  }

  :deep(.el-button) {
    border-radius: 6px;
  }

  :deep(.el-input__wrapper),
  :deep(.el-textarea__inner),
  :deep(.el-input-number .el-input__wrapper) {
    border-radius: 6px;
    box-shadow: 0 0 0 1px #d8dee8 inset;
  }

  :deep(.el-input__wrapper:hover),
  :deep(.el-textarea__inner:hover),
  :deep(.el-input-number .el-input__wrapper:hover) {
    box-shadow: 0 0 0 1px #b9c4d3 inset;
  }

  :deep(.el-input__wrapper.is-focus),
  :deep(.el-textarea__inner:focus),
  :deep(.el-input-number .el-input__wrapper.is-focus) {
    box-shadow: 0 0 0 1px $cdd-primary inset, 0 0 0 3px rgba(37, 99, 235, 0.12);
  }
}

.cdd-workbench {
  height: 100%;
  min-height: 0;
  display: flex;
  flex-direction: column;
  color: $cdd-text;
}

.cdd-topbar {
  flex: 0 0 auto;
  min-height: 68px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 10px 16px;
  background: $cdd-panel-bg;
  border-bottom: 1px solid $cdd-border;
}

.cdd-topbar__identity {
  flex: 1 1 auto;
  min-width: 0;
  display: grid;
  grid-template-columns: minmax(220px, 1fr) auto;
  align-items: center;
  gap: 12px;
}

.cdd-model-title {
  min-width: 0;
  overflow: hidden;
  color: $cdd-text;
  font-size: 18px;
  font-weight: 700;
  line-height: 1.35;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.cdd-imei-line {
  display: inline-flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  min-width: 0;
  padding: 5px 8px;
  border: 1px solid $cdd-soft-border;
  border-radius: 6px;
  background: #f8fafc;

  span {
    flex: 0 0 auto;
    padding: 0;
    border-radius: 0;
    background: transparent;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    line-height: 1.4;
  }

  strong {
    min-width: 0;
    overflow: hidden;
    color: $cdd-text;
    font-family: 'SF Mono', 'Monaco', 'Menlo', 'Consolas', monospace;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}

.cdd-topbar__model-input,
.cdd-topbar__imei-input {
  min-width: 0;
}

.cdd-topbar__model-input {
  width: min(420px, 100%);
}

.cdd-topbar__imei-input {
  width: 300px;
}

.cdd-topbar__actions {
  flex: 0 0 auto;
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;

  :deep(.el-tag) {
    height: 26px;
    padding: 0 8px;
    border-radius: 6px;
    font-weight: 600;
  }
}

.cdd-main-form {
  flex: 1 1 auto;
  height: calc(min(90vh, 860px) - 68px - 59px - 49px);
  min-height: 420px;
  overflow: hidden;
}

.cdd-main {
  height: 100%;
  min-height: 0;
  display: grid;
  grid-template-columns: 220px minmax(0, 1fr) 340px;
  gap: 10px;
  padding: 10px;
  overflow: hidden;
}

.cdd-mobile-main {
  display: none;
}

.cdd-side,
.cdd-center {
  min-width: 0;
  min-height: 0;
  height: 100%;
}

.cdd-side {
  display: flex;
  flex-direction: column;
  gap: 10px;
  overflow: hidden;
}

.cdd-side--left,
.cdd-right-scroll,
.cdd-center-scroll {
  overflow-y: auto;
  overscroll-behavior: contain;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 transparent;

  &::-webkit-scrollbar {
    width: 6px;
    height: 6px;
  }

  &::-webkit-scrollbar-thumb {
    border-radius: 999px;
    background: #cbd5e1;
  }

  &::-webkit-scrollbar-track {
    background: transparent;
  }
}

.cdd-side--right {
  display: flex;
  flex-direction: column;
}

.cdd-center {
  display: grid;
  grid-template-rows: auto minmax(0, 1fr);
  overflow: hidden;
}

.cdd-panel,
.cdd-work-section {
  background: $cdd-panel-bg;
  border: 1px solid $cdd-border;
  border-radius: 8px;
  box-shadow: none;
}

.cdd-panel {
  padding: 12px;
}

.cdd-work-section {
  padding: 12px;
  margin: 0;
  scroll-margin-top: 8px;
}

.cdd-panel__title,
.cdd-section-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  min-height: 20px;
  margin: 0 0 10px;
  color: $cdd-text;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.45;

  span {
    min-width: 0;
  }

  em {
    flex: 0 0 auto;
    color: #6b7280;
    font-size: 12px;
    font-style: normal;
    font-weight: 500;
  }
}

.cdd-summary-list {
  display: grid;
  gap: 8px;

  div {
    display: grid;
    grid-template-columns: 52px minmax(0, 1fr);
    align-items: start;
    gap: 8px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.35;
  }

  span {
    color: #64748b;
  }

  strong {
    min-width: 0;
    overflow-wrap: anywhere;
    color: $cdd-text;
    font-weight: 600;
  }
}

.cdd-query-stack,
.cdd-action-stack {
  display: grid;
  gap: 8px;

  .el-button {
    width: 100%;
    justify-content: flex-start;
    margin-left: 0;
  }
}

.cdd-muted {
  padding: 8px 0;
  color: $cdd-text-muted;
  font-size: 12px;
  text-align: center;
}

.cdd-anchor-bar {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px;
  margin-bottom: 8px;
  overflow-x: auto;
  background: $cdd-panel-bg;
  border: 1px solid $cdd-border;
  border-radius: 8px;

  button {
    flex: 0 0 auto;
    min-height: 28px;
    padding: 5px 10px;
    border: 0;
    border-radius: 6px;
    background: transparent;
    color: $cdd-text-secondary;
    font-size: 12px;
    font-weight: 600;
    line-height: 1.4;
    cursor: pointer;
    transition: background-color 0.16s ease, color 0.16s ease;

    &:hover,
    &:focus-visible {
      color: $cdd-primary;
      background: #eff6ff;
      outline: none;
    }
  }
}

.cdd-center-scroll,
.cdd-right-scroll {
  min-height: 0;
  height: 100%;
  padding-right: 4px;
}

.cdd-center-scroll > .cdd-work-section + .cdd-work-section,
.cdd-right-scroll > .cdd-panel + .cdd-panel {
  margin-top: 10px;
}

.cdd-work-section .cdd-info-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.cdd-work-section .cdd-info-cell {
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 0;
  border: 0;
}

.cdd-info-cell__label,
.cdd-custom-field__label {
  color: #64748b;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
}

.cdd-inline-control {
  display: flex;
  align-items: center;
  gap: 6px;

  .el-input,
  .el-input-number {
    flex: 1 1 auto;
    min-width: 0;
    width: 100%;
  }

  span {
    flex: 0 0 auto;
    color: $cdd-text-muted;
    font-size: 12px;
  }
}

.cdd-info-cell--switch {
  min-height: 54px;
  justify-content: space-between;
}

.cdd-work-section .cdd-sub-cols {
  display: grid;
  gap: 10px;
}

.cdd-sub-cols--3 {
  grid-template-columns: repeat(3, minmax(0, 1fr));
}

.cdd-sub-cols--2 {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.cdd-work-section .cdd-sub-section {
  min-width: 0;
  padding: 10px;
  border: 1px solid $cdd-soft-border;
  border-radius: 6px;
  background: #fbfdff;
}

.cdd-work-section .cdd-sub-section--done {
  border-color: #bbf7d0;
  background: #f0fdf4;
}

.cdd-sub-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 6px;
  margin-bottom: 8px;
  color: #374151;
  font-size: 12px;
  font-weight: 700;
  line-height: 1.4;

  em {
    flex: 0 0 auto;
    color: #6b7280;
    font-size: 11px;
    font-style: normal;
    font-weight: 600;
  }
}

.cdd-tag-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  max-height: 132px;
  overflow-y: auto;
  padding-right: 2px;
}

.cdd-tag-grid--scroll {
  max-height: 176px;
}

.cdd-check-tag {
  height: 26px;
  display: inline-flex;
  align-items: center;
  border-radius: 6px;
  cursor: pointer;
  user-select: none;
  transition: transform 0.14s ease, box-shadow 0.14s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 3px 8px rgba(15, 23, 42, 0.08);
  }
}

.cdd-custom-groups {
  display: flex;
  flex-direction: column;
  gap: 10px;
  padding: 0;
}

.cdd-custom-group {
  min-width: 0;
  padding: 10px;
  border: 1px solid $cdd-soft-border;
  border-radius: 6px;
  background: #fbfdff;
}

.cdd-custom-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}

.cdd-custom-field {
  min-width: 0;

  &__label {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-bottom: 5px;
  }

  &__unit {
    color: $cdd-text-muted;
    font-weight: 400;
  }

  &__number,
  &__select {
    width: 100%;
  }

  :deep(.el-radio-group),
  :deep(.el-checkbox-group) {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  :deep(.el-radio-button__inner),
  :deep(.el-checkbox-button__inner) {
    border-left: 1px solid var(--el-border-color);
    border-radius: 6px;
  }
}

.cdd-result-field,
.cdd-price-field,
.cdd-remark-field {
  margin-bottom: 0 !important;
}

.cdd-result-field {
  :deep(.el-form-item__content) {
    line-height: 1;
  }

  :deep(.el-textarea__inner) {
    min-height: 112px !important;
    color: #1f2937;
    font-size: 12px;
    line-height: 1.6;
  }
}

.cdd-price-panel {
  :deep(.el-form-item__label) {
    display: block;
    padding-bottom: 4px;
    color: #374151;
    font-size: 12px;
    font-weight: 700;
    line-height: 1.4;

    em {
      margin-left: 6px;
      color: $cdd-warning;
      font-style: normal;
      font-weight: 500;
    }
  }

  .cdd-remark-field {
    margin-top: 10px;
  }
}

.cdd-price-input {
  width: 100%;
}

.cdd-upload-panel {
  padding: 0;
  overflow: hidden;

  :deep(.el-collapse) {
    border: 0;
  }

  :deep(.el-collapse-item__header) {
    height: auto;
    min-height: 42px;
    padding: 0 12px;
    border-bottom-color: $cdd-border;
    color: $cdd-text;
    font-weight: 700;
    line-height: 1.4;
  }

  :deep(.el-collapse-item__wrap) {
    border-bottom: 0;
  }

  :deep(.el-collapse-item__content) {
    max-height: 250px;
    overflow-y: auto;
    padding: 10px 12px 12px;
  }
}

.cdd-upload-title {
  color: $cdd-text;
  font-size: 13px;
  white-space: normal;
}

.cdd-upload-block + .cdd-upload-block {
  margin-top: 12px;
}

.cdd-upload-label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  margin-bottom: 8px;
  color: $cdd-text-secondary;
  font-size: 12px;
  font-weight: 700;
}

.cdd-camera-row {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 8px;
  flex-wrap: wrap;
}

.cdd-footer {
  min-height: 38px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;

  &__info {
    flex: 0 0 auto;
    padding: 5px 10px;
    border-radius: 6px;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
  }

  &__btns {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 10px;

    .el-button {
      margin-left: 0;
    }
  }
}

.cdd-return-btn {
  border-color: #fecaca;
  background: #fff7f7;
}

@media (max-width: 1180px) {
  .cdd-main {
    grid-template-columns: 200px minmax(0, 1fr) 320px;
  }

  .cdd-work-section .cdd-info-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 980px) {
  .cdd-main {
    grid-template-columns: minmax(0, 1fr) 320px;
  }

  .cdd-side--left {
    display: none;
  }

  .cdd-sub-cols--3,
  .cdd-sub-cols--2,
  .cdd-custom-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .check-device-dialog.cdd-workbench-dialog {
    :deep(.el-dialog) {
      width: 96vw !important;
      height: 92vh;
      max-height: 92vh;
      margin: 4vh auto !important;
    }

    :deep(.el-dialog__header) {
      padding: 10px 42px 10px 14px;
    }

    :deep(.el-dialog__footer) {
      padding: 10px 12px;
    }
  }

  .cdd-topbar {
    align-items: stretch;
    flex-direction: column;
    gap: 10px;
    min-height: 0;
    padding: 10px 12px;
  }

  .cdd-topbar__identity {
    width: 100%;
    grid-template-columns: 1fr;
    gap: 8px;
  }

  .cdd-model-title {
    white-space: normal;
  }

  .cdd-imei-line {
    width: 100%;
  }

  .cdd-topbar__model-input,
  .cdd-topbar__imei-input {
    width: 100%;
    min-width: 0;
  }

  .cdd-topbar__actions {
    justify-content: flex-start;
  }

  .cdd-main-form {
    height: auto;
    min-height: 0;
    overflow: visible;
  }

  .cdd-main {
    height: 100%;
    display: block;
    padding: 8px;
    overflow-y: auto;
  }

  .cdd-side,
  .cdd-side--right,
  .cdd-center {
    height: auto;
    overflow: visible;
  }

  .cdd-side--left {
    display: flex;
    margin-bottom: 8px;
  }

  .cdd-center {
    display: block;
  }

  .cdd-center-scroll,
  .cdd-right-scroll {
    height: auto;
    overflow: visible;
    padding-right: 0;
  }

  .cdd-anchor-bar {
    position: sticky;
    top: 0;
    z-index: 2;
    margin-bottom: 8px;
  }

  .cdd-work-section .cdd-info-grid,
  .cdd-sub-cols--3,
  .cdd-sub-cols--2,
  .cdd-custom-grid {
    grid-template-columns: 1fr;
  }

  .cdd-right-scroll {
    margin-top: 10px;
  }

  .cdd-footer {
    align-items: stretch;
    flex-direction: column;

    &__info {
      text-align: center;
    }

    &__btns {
      flex-direction: column;
      width: 100%;

      .el-button {
        width: 100%;
        margin-left: 0;
      }
    }
  }
}

/* ============================================================
   质检工作台视觉刷新 v2
   Element Plus Dialog 可能与 scoped 样式同级/teleport，这里用全局选择器确保命中。
   ============================================================ */
:global(.el-dialog.check-device-dialog.cdd-workbench-dialog),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog) {
  overflow: hidden;
  border: 1px solid #d8e0ea;
  border-radius: 12px;
  background: #f3f6fa;
  box-shadow: 0 24px 70px rgba(15, 23, 42, 0.2);
}

:global(.el-dialog.check-device-dialog.cdd-workbench-dialog .el-dialog__header),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog__header) {
  min-height: 50px;
  padding: 14px 52px 13px 18px;
  background: linear-gradient(90deg, #ffffff 0%, #f4f8ff 55%, #eefdf7 100%);
  border-bottom: 1px solid #dbe4ef;
}

:global(.el-dialog.check-device-dialog.cdd-workbench-dialog .el-dialog__title),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog__title) {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  color: #0f172a;
  font-size: 16px;
  font-weight: 800;
}

:global(.el-dialog.check-device-dialog.cdd-workbench-dialog .el-dialog__title::before),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog__title::before) {
  width: 4px;
  height: 18px;
  border-radius: 999px;
  background: #2563eb;
  content: '';
}

.cdd-topbar {
  min-height: 74px;
  padding: 12px 16px;
  background: linear-gradient(90deg, #ffffff 0%, #f8fbff 100%);
  border-bottom: 1px solid #dbe4ef;
  box-shadow: 0 1px 0 rgba(255, 255, 255, 0.8) inset;
}

.cdd-model-title {
  position: relative;
  padding-left: 14px;
  color: #0f172a;
  font-size: 19px;
  font-weight: 800;
}

.cdd-model-title::before {
  position: absolute;
  top: 4px;
  bottom: 4px;
  left: 0;
  width: 4px;
  border-radius: 999px;
  background: #22c55e;
  content: '';
}

.cdd-imei-line {
  min-height: 34px;
  padding: 7px 10px;
  border-color: #cbd5e1;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.cdd-topbar__actions {
  :deep(.el-tag) {
    border-color: #bfdbfe;
    background: #eff6ff;
    color: #1d4ed8;
  }

  :deep(.el-tag.el-tag--success) {
    border-color: #bbf7d0;
    background: #f0fdf4;
    color: #15803d;
  }
}

.cdd-main {
  grid-template-columns: 226px minmax(0, 1fr) 352px;
  gap: 12px;
  padding: 12px;
  background: #eef3f8;
}

.cdd-panel,
.cdd-work-section,
.cdd-anchor-bar {
  border-color: #dbe4ef;
  border-radius: 10px;
  background: #ffffff;
  box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.cdd-panel {
  padding: 13px;
}

.cdd-panel__title,
.cdd-section-title {
  margin: -3px -3px 12px;
  padding: 0 0 9px;
  border-bottom: 1px solid #edf2f7;
  color: #0f172a;
  font-size: 13px;
  font-weight: 800;
}

.cdd-summary-panel {
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
}

.cdd-summary-list {
  gap: 9px;

  div {
    grid-template-columns: 50px minmax(0, 1fr);
    padding: 7px 8px;
    border-radius: 7px;
    background: #f8fafc;
  }
}

.cdd-summary-empty {
  display: block !important;
  padding: 12px 8px;
  color: #94a3b8;
  font-size: 12px;
  text-align: center;
  background: transparent !important;
}

.check-device-dialog.cdd-workbench-dialog {
  :deep(.el-dialog),
  :deep(.el-dialog__body) {
    min-height: 0;
  }
}

.cdd-workbench,
.cdd-main-form,
.cdd-main,
.cdd-side,
.cdd-side--right,
.cdd-right-scroll,
:deep(.cdd-center),
:deep(.cdd-center-scroll) {
  min-height: 0;
}

.cdd-main-form {
  display: flex;
  flex-direction: column;
}

.cdd-main {
  flex: 1 1 auto;
}

:deep(.cdd-center) {
  height: 100%;
  overflow: hidden;
}

.cdd-side--right {
  overflow: hidden;
}

.cdd-right-scroll,
:deep(.cdd-center-scroll) {
  flex: 1 1 auto;
  max-height: 100%;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.cdd-query-stack .el-button,
.cdd-action-stack .el-button {
  min-height: 32px;
  border-color: #d7e0ea;
  background: #ffffff;
}

.cdd-query-stack .el-button:hover,
.cdd-action-stack .el-button:hover {
  border-color: #93c5fd;
  background: #eff6ff;
}

.cdd-anchor-bar {
  gap: 7px;
  padding: 9px;
}

.cdd-anchor-bar button {
  min-height: 30px;
  padding: 6px 11px;
  border: 1px solid transparent;
}

.cdd-anchor-bar button:hover,
.cdd-anchor-bar button:focus-visible {
  border-color: #bfdbfe;
}

.cdd-work-section {
  padding: 14px;
}

.cdd-work-section .cdd-info-grid {
  gap: 12px;
}

.cdd-work-section .cdd-info-cell {
  padding: 10px;
  border: 1px solid #edf2f7;
  border-radius: 8px;
  background: #fbfdff;
}

.cdd-info-cell--switch {
  justify-content: center;
}

.cdd-work-section .cdd-sub-section,
.cdd-custom-group {
  padding: 12px;
  border-color: #dfe7f1;
  border-radius: 8px;
  background: #fbfdff;
}

.cdd-work-section .cdd-sub-section--done {
  border-color: #86efac;
  background: #f0fdf4;
  box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.08);
}

.cdd-sub-header {
  margin-bottom: 9px;
  color: #1f2937;
  font-weight: 800;
}

.cdd-tag-grid {
  gap: 7px;
  max-height: 142px;
}

.cdd-tag-grid--scroll {
  max-height: 188px;
}

.cdd-check-tag {
  height: 28px;
  padding: 0 9px;
  border-radius: 7px;
  font-weight: 600;
}

.cdd-result-panel {
  border-left: 4px solid #3b82f6;
}

.cdd-result-panel + .cdd-result-panel {
  border-left-color: #10b981;
}

.cdd-result-field :deep(.el-textarea__inner) {
  background: #fbfdff;
}

.cdd-price-panel {
  border-left: 4px solid #f59e0b;
  background: linear-gradient(180deg, #ffffff 0%, #fffaf0 100%);
}

.cdd-upload-panel {
  border-left: 4px solid #8b5cf6;
}

.cdd-footer__info {
  background: #eff6ff;
  color: #1d4ed8;
}

.cdd-footer__btns {
  .el-button {
    min-width: 104px;
  }
}

@media (max-width: 1180px) {
  .cdd-main {
    grid-template-columns: 210px minmax(0, 1fr) 326px;
  }
}

@media (max-width: 980px) {
  .cdd-main {
    grid-template-columns: minmax(0, 1fr) 326px;
  }
}

@media (max-width: 768px) {
  .cdd-main {
    display: block;
    padding: 9px;
  }

  .cdd-topbar {
    padding: 11px 12px;
  }

  .cdd-work-section .cdd-info-cell {
    padding: 9px;
  }
}

@media (max-width: 768px) {
  .check-device-dialog.cdd-workbench-dialog {
    :deep(.el-dialog) {
      height: 92vh;
      max-height: 92vh;
      overflow: hidden;
    }

    :deep(.el-dialog__body) {
      flex: 1 1 auto;
      min-height: 0;
      overflow-y: auto;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
    }
  }

  .cdd-workbench {
    min-height: max-content;
    overflow: visible;
  }

  .cdd-main-form {
    display: block;
    flex: none;
    min-height: max-content;
    height: auto;
    overflow: visible;
  }

  .cdd-main {
    display: none;
  }

  .cdd-mobile-main {
    height: auto;
    min-height: max-content;
    display: grid;
    align-content: start;
    gap: 10px;
    padding: 10px;
    overflow: visible;
    overscroll-behavior: auto;
    touch-action: pan-y;
  }

  .cdd-mobile-main,
  .cdd-mobile-main *,
  .cdd-panel {
    touch-action: pan-y;
  }

  .cdd-price-input,
  .cdd-price-input :deep(.el-input-number) {
    width: 100%;
  }
}
</style>
