<template>
  <el-dialog
    v-model="dialogVisible"
    title="设备质检"
    :width="isMobile ? '96vw' : '1240px'"
    :destroy-on-close="true"
    class="check-device-dialog cdd-workbench-dialog"
    align-center
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
          <el-tag size="small" effect="plain">{{ checkTemplateInfo?.template_name || '默认质检模板' }}</el-tag>
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
        <div class="cdd-main">
          <aside class="cdd-side cdd-side--left">
            <div class="cdd-panel cdd-summary-panel">
              <div class="cdd-panel__title">设备摘要</div>
              <div class="cdd-summary-list">
                <div><span>容量</span><strong>{{ deviceForm.capacity || '-' }}</strong></div>
                <div><span>颜色</span><strong>{{ deviceForm.color || '-' }}</strong></div>
                <div><span>系统</span><strong>{{ deviceForm.system_version || '-' }}</strong></div>
                <div><span>保修</span><strong>{{ deviceForm.warranty_info || '-' }}</strong></div>
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

          <section class="cdd-center">
            <div class="cdd-anchor-bar">
              <button type="button" @click.prevent.stop="scrollToCheckSection('cdd-device-info')">{{ groupLabel('device_info', '设备信息') }}</button>
              <button type="button" @click.prevent.stop="scrollToCheckSection('cdd-appearance')">{{ groupLabel('appearance', '外观规格') }}</button>
              <button type="button" @click.prevent.stop="scrollToCheckSection('cdd-issues')">{{ groupLabel('issues', '问题记录') }}</button>
              <button v-if="customCheckGroups.length" type="button" @click.prevent.stop="scrollToCheckSection('cdd-custom')">自定义</button>
            </div>

            <div ref="centerScrollRef" class="cdd-center-scroll" @click.stop>
              <section id="cdd-device-info" class="cdd-section cdd-work-section">
                <div class="cdd-section-title">{{ groupLabel('device_info', '设备信息') }}</div>
                <div class="cdd-info-grid">
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('capacity', '内存') }}</span><el-input v-model="deviceForm.capacity" size="small" :placeholder="fieldPlaceholder('capacity', '如 256GB')" @change="updateCheckResult" /></div>
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('color', '颜色') }}</span><el-input v-model="deviceForm.color" size="small" :placeholder="fieldPlaceholder('color', '如 深空黑色')" @change="updateCheckResult" /></div>
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('system_version', '系统版本') }}</span><el-input v-model="deviceForm.system_version" size="small" :placeholder="fieldPlaceholder('system_version', '如 iOS 17.3.1')" @change="updateCheckResult" /></div>
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('warranty_info', '保修信息') }}</span><el-input v-model="deviceForm.warranty_info" size="small" :placeholder="fieldPlaceholder('warranty_info', '保修日期/过保/未激活')" @change="updateCheckResult" /></div>
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('battery', '电池健康度') }}</span><div class="cdd-inline-control"><el-input-number v-model="templateSelections.battery" :min="0" :max="100" :step="1" size="small" controls-position="right" @change="updateCheckResult" /><span>{{ fieldUnit('battery', '%') }}</span></div></div>
                  <div class="cdd-info-cell"><span class="cdd-info-cell__label">{{ fieldLabel('battery_num', '循环次数') }}</span><div class="cdd-inline-control"><el-input v-model="templateSelections.battery_num" type="number" size="small" @change="updateCheckResult" /><span>{{ fieldUnit('battery_num', '次') }}</span></div></div>
                  <div class="cdd-info-cell cdd-info-cell--switch"><span class="cdd-info-cell__label">{{ fieldLabel('activation_lock', '激活锁') }}</span><el-switch v-model="templateSelections.activationLock" active-text="已开" inactive-text="未开" size="small" style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e" @change="updateCheckResult" /></div>
                  <div class="cdd-info-cell cdd-info-cell--switch"><span class="cdd-info-cell__label">{{ fieldLabel('mdm_lock', '监管锁') }}</span><el-switch v-model="templateSelections.mdmLock" active-text="已开" inactive-text="未开" size="small" style="--el-switch-on-color:#ef4444;--el-switch-off-color:#22c55e" @change="updateCheckResult" /></div>
                </div>
              </section>

              <section id="cdd-appearance" class="cdd-section cdd-work-section">
                <div class="cdd-section-title"><span>{{ groupLabel('appearance', '外观规格') }}</span><em>已选 {{ [templateSelections.screenId, templateSelections.indisplayId, templateSelections.appearanceId].filter(Boolean).length }}/3</em></div>
                <div class="cdd-sub-cols cdd-sub-cols--3">
                  <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.screenId }">
                    <div class="cdd-sub-header">{{ fieldLabel('screen_id', '外屏规格') }}</div>
                    <div class="cdd-tag-grid"><el-tag v-for="opt in checkDictOptions.screen" :key="opt.value" :type="templateSelections.screenId === String(opt.value) ? 'primary' : undefined" :effect="templateSelections.screenId === String(opt.value) ? 'dark' : 'plain'" size="small" class="cdd-check-tag" @click="selectScreenOption(opt.value)">{{ opt.name }}</el-tag></div>
                  </div>
                  <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.indisplayId }">
                    <div class="cdd-sub-header">{{ fieldLabel('indisplay_id', '内屏规格') }}</div>
                    <div class="cdd-tag-grid"><el-tag v-for="opt in checkDictOptions.indisplay" :key="opt.value" :type="templateSelections.indisplayId === String(opt.value) ? 'primary' : undefined" :effect="templateSelections.indisplayId === String(opt.value) ? 'dark' : 'plain'" size="small" class="cdd-check-tag" @click="selectIndisplayOption(opt.value)">{{ opt.name }}</el-tag></div>
                  </div>
                  <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': !!templateSelections.appearanceId }">
                    <div class="cdd-sub-header">{{ fieldLabel('appearance_id', '中框规格') }}</div>
                    <div class="cdd-tag-grid"><el-tag v-for="opt in checkDictOptions.appearance" :key="opt.value" :type="templateSelections.appearanceId === String(opt.value) ? 'primary' : undefined" :effect="templateSelections.appearanceId === String(opt.value) ? 'dark' : 'plain'" size="small" class="cdd-check-tag" @click="selectAppearanceOption(opt.value)">{{ opt.name }}</el-tag></div>
                  </div>
                </div>
              </section>

              <section id="cdd-issues" class="cdd-section cdd-work-section">
                <div class="cdd-section-title"><span>{{ groupLabel('issues', '问题记录') }}</span><em>已选 {{ templateSelections.functionIds.length + templateSelections.fixIds.length }} 项</em></div>
                <div class="cdd-sub-cols cdd-sub-cols--2">
                  <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.functionIds.length > 0 }">
                    <div class="cdd-sub-header">{{ fieldLabel('function_ids', '功能') }} <em v-if="templateSelections.functionIds.length">{{ templateSelections.functionIds.length }} 项</em></div>
                    <div class="cdd-tag-grid cdd-tag-grid--scroll"><el-tag v-for="opt in checkDictOptions.function" :key="opt.value" :type="templateSelections.functionIds.includes(String(opt.value)) ? 'danger' : undefined" :effect="templateSelections.functionIds.includes(String(opt.value)) ? 'dark' : 'plain'" size="small" class="cdd-check-tag" @click="toggleFunctionOption(opt.value)">{{ opt.name }}</el-tag></div>
                  </div>
                  <div class="cdd-sub-section" :class="{ 'cdd-sub-section--done': templateSelections.fixIds.length > 0 }">
                    <div class="cdd-sub-header">{{ fieldLabel('fix_ids', '维修记录') }} <em v-if="templateSelections.fixIds.length">{{ templateSelections.fixIds.length }} 项</em></div>
                    <div class="cdd-tag-grid cdd-tag-grid--scroll"><el-tag v-for="opt in checkDictOptions.fix" :key="opt.value" :type="templateSelections.fixIds.includes(String(opt.value)) ? 'warning' : undefined" :effect="templateSelections.fixIds.includes(String(opt.value)) ? 'dark' : 'plain'" size="small" class="cdd-check-tag" @click="toggleFixOption(opt.value)">{{ opt.name }}</el-tag></div>
                  </div>
                </div>
              </section>

              <section v-if="customCheckGroups.length" id="cdd-custom" class="cdd-section cdd-work-section">
                <div class="cdd-section-title">自定义质检项</div>
                <div class="cdd-custom-groups">
                  <div v-for="group in customCheckGroups" :key="group.id || group.group_key" class="cdd-custom-group">
                    <div class="cdd-sub-header">{{ group.group_name }}</div>
                    <div class="cdd-custom-grid">
                      <div v-for="field in group.fields" :key="field.field_key" class="cdd-custom-field">
                        <div class="cdd-custom-field__label">{{ field.field_name }}<span v-if="field.unit" class="cdd-custom-field__unit">{{ field.unit }}</span></div>
                        <el-input v-if="field.component === 'input'" :model-value="templateSelections.customFields[field.field_key]" :placeholder="field.placeholder" size="small" clearable @update:model-value="value => setCustomFieldValue(field.field_key, value)" />
                        <el-input v-else-if="field.component === 'textarea'" :model-value="templateSelections.customFields[field.field_key]" :placeholder="field.placeholder" type="textarea" :rows="2" resize="none" @update:model-value="value => setCustomFieldValue(field.field_key, value)" />
                        <el-input-number v-else-if="field.component === 'number'" :model-value="templateSelections.customFields[field.field_key]" :min="0" controls-position="right" size="small" class="cdd-custom-field__number" @update:model-value="value => setCustomFieldValue(field.field_key, value)" />
                        <el-switch v-else-if="field.component === 'switch'" :model-value="!!templateSelections.customFields[field.field_key]" active-text="是" inactive-text="否" size="small" @update:model-value="value => setCustomFieldValue(field.field_key, value)" />
                        <el-select v-else-if="field.component === 'select'" :model-value="templateSelections.customFields[field.field_key]" :placeholder="field.placeholder || '请选择'" size="small" clearable class="cdd-custom-field__select" @update:model-value="value => setCustomFieldValue(field.field_key, value)"><el-option v-for="option in field.options || []" :key="option.value" :label="option.name || option.label" :value="String(option.value)" /></el-select>
                        <el-radio-group v-else-if="field.component === 'radio'" :model-value="templateSelections.customFields[field.field_key]" size="small" @update:model-value="value => setCustomFieldValue(field.field_key, value)"><el-radio-button v-for="option in field.options || []" :key="option.value" :label="String(option.value)">{{ option.name || option.label }}</el-radio-button></el-radio-group>
                        <el-checkbox-group v-else-if="field.component === 'checkbox'" :model-value="templateSelections.customFields[field.field_key] || []" size="small" @update:model-value="value => setCustomFieldValue(field.field_key, value)"><el-checkbox-button v-for="option in field.options || []" :key="option.value" :label="String(option.value)">{{ option.name || option.label }}</el-checkbox-button></el-checkbox-group>
                        <el-input v-else :model-value="templateSelections.customFields[field.field_key]" :placeholder="field.placeholder" size="small" clearable @update:model-value="value => setCustomFieldValue(field.field_key, value)" />
                      </div>
                    </div>
                  </div>
                </div>
              </section>
            </div>
          </section>

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
          <el-button size="large" @click="handleCancel">取消</el-button>
          <el-button type="warning" size="large" :loading="savingDraft" @click="handleSaveDraft">{{ savingDraft ? '暂存中...' : '暂存草稿' }}</el-button>
          <el-button type="primary" size="large" :loading="submitting" @click="handleConfirm"><el-icon v-if="!submitting"><Check /></el-icon>{{ submitting ? '提交中...' : '完成质检' }}</el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, watch, computed, nextTick, onMounted, onBeforeUnmount, toRef } from 'vue'
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import {
  Cellphone, Edit, Postcard, Aim, Monitor, Check, Close, Headset, Lock, CopyDocument
} from '@element-plus/icons-vue'

import { queryDeviceByService } from '@/addon/recycle/api/device_query_api'
import { getDeviceQueryConfigList } from '@/addon/recycle/api/device_query_config'
import { getCheckTemplateSchema } from '@/addon/recycle/api/check_template'
import { useCheckDeviceDict } from '@/addon/recycle/hooks/useCheckDeviceDict'
import {
  normalizeInfo,
  useCheckMeta,
  type CheckMetaPayload,
  type CheckOptionsGroup,
  type CheckTemplateField
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
const checkSchemaLoading = ref(false)
const checkTemplateInfo = ref<any>(null)
const checkTemplateGroups = ref<any[]>([])
const knownCheckFieldKeys = ['capacity', 'color', 'system_version', 'warranty_info', 'battery', 'battery_num', 'activation_lock', 'mdm_lock', 'screen_id', 'indisplay_id', 'appearance_id', 'function_ids', 'fix_ids']
const schemaFieldKeyMap: Record<string, keyof CheckOptionsGroup> = {
  screen_id: 'screen',
  indisplay_id: 'indisplay',
  appearance_id: 'appearance',
  function_ids: 'function',
  fix_ids: 'fix'
}

const normalizeSchemaOption = (option: any) => ({
  name: option.name || option.label || option.option_label || '',
  label: option.label || option.name || option.option_label || '',
  value: String(option.value ?? option.option_value ?? ''),
  sort: Number(option.sort || 0),
  memo: option.memo || ''
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

const groupConfigByKey = computed<Record<string, any>>(() => {
  const map: Record<string, any> = {}
  checkTemplateGroups.value.forEach((group: any) => {
    map[group.group_key] = group
  })
  return map
})

const checkDictOptions = computed<CheckOptionsGroup>(() => {
  const options = { ...(dictOptions.options.value as CheckOptionsGroup) }
  Object.entries(schemaFieldKeyMap).forEach(([fieldKey, optionKey]) => {
    const field = fieldConfigByKey.value[fieldKey]
    if (field?.options?.length) {
      options[optionKey] = field.options as any
    }
  })
  return options
})

const customCheckGroups = computed(() => {
  return checkTemplateGroups.value
    .map((group: any) => ({
      ...group,
      fields: (group.fields || []).filter((field: any) => !knownCheckFieldKeys.includes(field.field_key))
    }))
    .filter((group: any) => group.fields.length)
})

const dialogVisible = ref(props.visible)
const deviceData = ref<DeviceInfo>({ ...props.device })
const submitting = ref(false)
const savingDraft = ref(false)
const formRef = ref<FormInstance>()
const imeiInputRef = ref()
const modelInputRef = ref()
const centerScrollRef = ref<HTMLElement | null>(null)
const isEditingDeviceInfo = ref(false)
const isMobile = ref(false)

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
  restoreFromDevice,
  setCustomFieldValue,
  selectScreenOption, selectIndisplayOption, selectAppearanceOption,
  toggleFunctionOption, toggleFixOption
} = useCheckMeta({
  dictOptions: checkDictOptions,
  deviceForm,
  fieldConfigByKey,
  templateInfo: computed(() => checkTemplateInfo.value)
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

const updateDeviceMode = () => { isMobile.value = window.innerWidth <= 768 }

const loadCheckTemplateSchema = async () => {
  checkSchemaLoading.value = true
  try {
    const res: any = await getCheckTemplateSchema({ scene: 'phone' })
    const payload = res.data || {}
    checkTemplateInfo.value = payload.template || null
    checkTemplateGroups.value = payload.groups || []
    restoreFromDevice(deviceData.value)
  } catch (error) {
    checkTemplateInfo.value = null
    checkTemplateGroups.value = []
  } finally {
    checkSchemaLoading.value = false
  }
}

const fieldLabel = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.field_name || fallback
}

const fieldPlaceholder = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.placeholder || fallback
}

const fieldUnit = (fieldKey: string, fallback: string) => {
  return fieldConfigByKey.value[fieldKey]?.unit || fallback
}

const groupLabel = (groupKey: string, fallback: string) => {
  return groupConfigByKey.value[groupKey]?.group_name || fallback
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

const scrollToCheckSection = (sectionId: string) => {
  const scrollEl = centerScrollRef.value
  const target = document.getElementById(sectionId)
  if (!scrollEl || !target) return
  const offset = target.offsetTop - scrollEl.offsetTop
  scrollEl.scrollTo({ top: Math.max(offset - 8, 0), behavior: 'smooth' })
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
    return date ? `在保至 ${date}` : '在保'
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
  initializeFormFromDevice(props.device)
  await dictOptions.loadDictionary()
  await loadCheckTemplateSchema()
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
    height: min(90vh, 860px);
    max-height: 860px;
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
    background: #f5f7fa;
    display: block;
  }

  :deep(.el-dialog__footer) {
    flex: 0 0 auto;
    padding: 10px 16px;
    background: #fff;
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
  height: calc(min(90vh, 860px) - 64px - 69px - 59px);
  min-height: 420px;
  overflow: hidden;
}

.cdd-topbar {
  flex: 0 0 auto;
  min-height: 68px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 14px;
  padding: 10px 16px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
}

.cdd-topbar__identity {
  min-width: 0;
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
@media (max-width: 768px) {
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
    gap: 10px;

    .el-button {
      margin-left: 0;
    }
  }
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

:global(.el-dialog.check-device-dialog.cdd-workbench-dialog .el-dialog__body),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog__body) {
  background: #eef3f8;
}

:global(.el-dialog.check-device-dialog.cdd-workbench-dialog .el-dialog__footer),
:global(.check-device-dialog.cdd-workbench-dialog .el-dialog__footer) {
  background: #ffffff;
  border-top: 1px solid #dbe4ef;
  box-shadow: 0 -8px 20px rgba(15, 23, 42, 0.04);
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
    grid-template-columns: 46px minmax(0, 1fr);
    padding: 7px 8px;
    border-radius: 7px;
    background: #f8fafc;
  }
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
</style>
