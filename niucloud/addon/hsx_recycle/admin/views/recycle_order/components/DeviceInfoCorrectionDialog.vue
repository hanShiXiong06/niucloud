<template>
  <HsxDialog v-model="visible" title="修改设备资料" width="720px" :confirm-loading="saving" :close-on-click-modal="false" destroy-on-close>
    <div v-loading="loading" class="device-correction">
      <el-alert :closable="false" :type="ability.allowed ? 'info' : 'warning'" show-icon>
        <template #title>{{ ability.allowed ? '仅限确认前纠错，不改报价、质检负责人或业务状态；修改前后内容会记入操作日志。' : (ability.reason || '正在核对设备是否允许修改') }}</template>
      </el-alert>
      <div v-if="form.id" class="device-correction__owners">
        <span>首次录入：{{ ability.provenance?.initial_entry?.name || '历史未记录' }}</span>
        <span>质检人员：{{ ability.provenance?.checker?.name || '历史未记录' }}</span>
      </div>
      <div v-if="loadError" class="device-correction__error">
        <span>{{ loadError }}</span><el-button link type="primary" @click="loadDevice">重新加载</el-button>
      </div>
      <el-form v-if="form.id" :model="form" label-position="top" :disabled="!ability.allowed || saving">
        <div class="device-correction__grid">
          <el-form-item label="标准型号" required>
            <el-select v-model="form.category_id" filterable remote :remote-method="searchModels" :loading="modelLoading" placeholder="搜索具体型号" @change="selectModel" class="device-correction__control">
              <el-option v-for="node in modelOptions" :key="node.id" :label="node.node_name || leafName(node.model_full_name)" :value="Number(node.id)" />
            </el-select>
          </el-form-item>
          <el-form-item label="IMEI / 设备串号" required><el-input v-model="form.imei" maxlength="15" placeholder="6–15 位字母或数字" /></el-form-item>
          <el-form-item label="IMEI2（选填）"><el-input v-model="form.imei2" maxlength="32" /></el-form-item>
          <el-form-item label="SN（选填）"><el-input v-model="form.sn" maxlength="80" /></el-form-item>
          <el-form-item label="用户串号（选填）"><el-input v-model="form.user_sn" maxlength="80" /></el-form-item>
        </div>
        <el-alert v-if="form.category_id !== originalCategoryId" type="warning" :closable="false" show-icon title="型号已更换，请重新核对设备摘要；原质检记录会保留，不会重新完成质检。" class="device-correction__model-tip" />
        <div v-if="schemaLoading" class="device-correction__hint">正在加载该型号的设备摘要…</div>
        <div v-else-if="schemaError" class="device-correction__error"><span>{{ schemaError }} {{ allowIdentityOnly ? '本次仅保存基础资料，原设备摘要不变。' : '请重新加载后再保存，未保存的摘要修改会保留。' }}</span><el-button link type="primary" @click="loadSchema">重新加载摘要</el-button></div>
        <CheckSummaryFields v-else :fields="fields" v-model="summaryValues" :imei="form.imei" :brand="form.model" />
        <el-form-item label="修改说明（选填）" class="device-correction__reason"><el-input v-model="reason" type="textarea" :rows="2" maxlength="200" show-word-limit placeholder="例如：型号选择错误，核对实机后纠正" /></el-form-item>
      </el-form>
    </div>
    <template #footer>
      <el-button :disabled="saving" @click="visible = false">取消</el-button>
      <el-button type="primary" :loading="saving" :disabled="loading || !ability.allowed || schemaLoading || (!!schemaError && !allowIdentityOnly) || !!loadError" @click="save">保存并留痕</el-button>
    </template>
  </HsxDialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import { getDevice, updateDevice } from '@/addon/hsx_recycle/api/recycle_order'
import { getRecycleDeviceModelDictOptions } from '@/addon/hsx_recycle/api/recycle_device_model_dict'
import { getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check_template'
import CheckSummaryFields from '@/addon/hsx_recycle/components/device-entry/CheckSummaryFields.vue'
import { validateSummaryRequired } from '@/addon/hsx_recycle/components/device-entry/summaryUtil'
import { deviceImeiError } from '@/addon/hsx_recycle/components/device-entry/imeiValidation'

const props = defineProps<{ visible: boolean; deviceId: number | string }>()
const emit = defineEmits(['update:visible', 'saved'])
const feedback = useFeedback()
const visible = computed({ get: () => props.visible, set: value => { if (!saving.value) emit('update:visible', value) } })
const loading = ref(false), saving = ref(false), modelLoading = ref(false), schemaLoading = ref(false)
const loadError = ref(''), schemaError = ref(''), reason = ref('')
const ability = ref<any>({ allowed: false, reason: '' })
const form = reactive({ id: 0, model: '', category_id: 0, imei: '', imei2: '', sn: '', user_sn: '' })
const originalCategoryId = ref(0)
const modelOptions = ref<any[]>([]), fields = ref<any[]>([]), summaryValues = ref<Record<string, any>>({})
const rawDevice = ref<any>({})
const originalForm = ref<Record<string, any>>({})
const originalSummary = ref<Record<string, any>>({})
const allowIdentityOnly = computed(() => !!schemaError.value && form.category_id === originalCategoryId.value && JSON.stringify(summaryValues.value) === JSON.stringify(originalSummary.value))
let loadVersion = 0, schemaVersion = 0, searchVersion = 0
const leafName = (value: any) => String(value || '').split(/[／/]/).map(value => value.trim()).filter(Boolean).pop() || ''
const decode = (value: any) => { if (typeof value !== 'string') return value && typeof value === 'object' ? value : {}; try { return JSON.parse(value) || {} } catch { return {} } }

const currentSummary = (device: any): Record<string, any> => {
  if (device.info_correction?.summary && typeof device.info_correction.summary === 'object') return { ...device.info_correction.summary }
  const info = decode(device.info), meta = decode(info.check_meta)
  const values: Record<string, any> = { ...decode(info.sign_summary), ...decode(meta.custom_fields), ...info }
  for (const item of Array.isArray(meta.result_items) ? meta.result_items : []) {
    if (item.field_key) values[item.field_key] = item.values ?? item.value ?? ''
  }
  for (const key of ['capacity', 'color', 'system_version', 'warranty_info']) if (device[key] !== undefined && device[key] !== null) values[key] = device[key]
  if (meta.battery !== undefined) values.battery = meta.battery
  if (meta.battery_num !== undefined) values.battery_num = meta.battery_num
  return values
}

const loadDevice = async () => {
  const version = ++loadVersion
  ++schemaVersion; ++searchVersion
  loading.value = true; loadError.value = ''; schemaError.value = ''; reason.value = ''
  ability.value = { allowed: false, reason: '' }; form.id = 0; fields.value = []; summaryValues.value = {}; modelOptions.value = []
  try {
    const result: any = await getDevice(Number(props.deviceId))
    if (version !== loadVersion || !props.visible) return
    const device = result?.data
    if (!device?.id || Number(device.id) !== Number(props.deviceId)) throw new Error('未取得当前设备资料，请重新加载')
    rawDevice.value = device
    ability.value = device.info_correction || { allowed: false, reason: '后端尚未提供资料纠错能力，请先更新回收插件后端' }
    Object.assign(form, { id: Number(device.id), model: device.model || '', category_id: Number(device.category_id || 0), imei: device.imei || '', imei2: device.imei2 || '', sn: device.sn || '', user_sn: device.user_sn || '' })
    originalForm.value = { ...form }
    originalCategoryId.value = form.category_id
    modelOptions.value = form.category_id ? [{ id: form.category_id, node_name: leafName(form.model) }] : []
    summaryValues.value = currentSummary(device)
    originalSummary.value = { ...summaryValues.value }
    if (ability.value.allowed) await loadSchema()
  } catch (error: any) {
    if (version === loadVersion) loadError.value = error?.msg || error?.message || '设备资料加载失败'
  } finally { if (version === loadVersion) loading.value = false }
}

const loadSchema = async () => {
  const version = ++schemaVersion, categoryId = form.category_id, deviceId = form.id
  schemaLoading.value = true; schemaError.value = ''; fields.value = []
  try {
    const result: any = await getCheckTemplateSchema(categoryId && categoryId !== originalCategoryId.value ? { category_id: categoryId } : { device_id: deviceId })
    if (version !== schemaVersion || deviceId !== form.id || !props.visible) return
    const data = result?.data
    if (!data?.template || !Array.isArray(data.groups)) throw new Error('未取得有效的设备摘要模板，请重新加载')
    if (['missing', 'invalid'].includes(data.schema_status)) throw new Error(data.schema_message || '设备摘要模板不可用')
    fields.value = data.groups.flatMap((group: any) => (group.fields || []).filter((field: any) => Number(field.extra_config?.summary_visible || 0) === 1))
    const values = { ...summaryValues.value }
    const baseline = currentSummary(rawDevice.value)
    for (const field of fields.value) {
      const key = field.field_key, value = values[key]
      const multiple = field.component === 'checkbox' || (field.component === 'select' && field.selection_mode === 'multiple')
      if (multiple) values[key] = Array.isArray(value) ? [...value] : (value === undefined || value === null || value === '' ? [] : [value])
      else if (field.component === 'number') values[key] = value === undefined || value === null || value === '' ? undefined : Number(Array.isArray(value) ? value[0] : value)
      else if (field.component === 'switch') values[key] = value === true || value === 1 || value === '1'
      else values[key] = Array.isArray(value) ? (value[0] ?? '') : (value ?? '')
      const old = baseline[key]
      if (multiple) baseline[key] = Array.isArray(old) ? [...old] : (old === undefined || old === null || old === '' ? [] : [old])
      else if (field.component === 'number') baseline[key] = old === undefined || old === null || old === '' ? undefined : Number(Array.isArray(old) ? old[0] : old)
      else if (field.component === 'switch') baseline[key] = old === true || old === 1 || old === '1'
      else baseline[key] = Array.isArray(old) ? (old[0] ?? '') : (old ?? '')
    }
    originalSummary.value = baseline
    summaryValues.value = values
  } catch (error: any) { if (version === schemaVersion) schemaError.value = error?.msg || error?.message || '设备摘要加载失败' }
  finally { if (version === schemaVersion) schemaLoading.value = false }
}

const searchModels = async (keyword: string) => {
  const version = ++searchVersion
  modelLoading.value = true
  try {
    const result: any = await getRecycleDeviceModelDictOptions({ keyword: keyword.trim() })
    if (version !== searchVersion || !props.visible) return
    const selected = modelOptions.value.find(node => Number(node.id) === form.category_id)
    const nodes = Array.isArray(result?.data) ? result.data : []
    modelOptions.value = selected && !nodes.some((node: any) => Number(node.id) === form.category_id) ? [selected, ...nodes] : nodes
  } catch (error: any) { if (version === searchVersion) feedback.error(error?.msg || error?.message || '型号搜索失败，请重试') }
  finally { if (version === searchVersion) modelLoading.value = false }
}

const selectModel = () => {
  const selected = modelOptions.value.find(node => Number(node.id) === form.category_id)
  if (!selected) return
  form.model = selected.node_name || leafName(selected.model_full_name)
  summaryValues.value = form.category_id === originalCategoryId.value ? currentSummary(rawDevice.value) : {}
  void loadSchema()
}

const save = async () => {
  if (saving.value || loading.value || !ability.value.allowed || schemaLoading.value || (schemaError.value && !allowIdentityOnly.value) || loadError.value) return
  const imeiError = deviceImeiError(form.imei)
  if (imeiError) { feedback.warning(imeiError); return }
  if (!form.model.trim()) { feedback.warning('请先选择设备型号'); return }
  const changedSummaryFields = fields.value.filter(field => form.category_id !== originalCategoryId.value || JSON.stringify(summaryValues.value[field.field_key] ?? '') !== JSON.stringify(originalSummary.value[field.field_key] ?? ''))
  const summaryError = validateSummaryRequired(changedSummaryFields, summaryValues.value)
  if (summaryError) { feedback.warning(summaryError); return }
  const summary: Record<string, any> = {}
  for (const field of fields.value) {
    const key = field.field_key, value = summaryValues.value[key] ?? ''
    if (form.category_id !== originalCategoryId.value || JSON.stringify(value) !== JSON.stringify(originalSummary.value[key] ?? '')) summary[key] = value
  }
  const payload: Record<string, any> = { action: 'correct_info', correction_reason: reason.value.trim() }
  for (const key of ['model', 'category_id', 'imei', 'imei2', 'sn', 'user_sn'] as const) {
    if (String(form[key]) !== String(originalForm.value[key])) payload[key] = form[key]
  }
  if (Object.keys(summary).length || form.category_id !== originalCategoryId.value) payload.summary = summary
  const deviceId = form.id, version = loadVersion
  saving.value = true
  try {
    const result: any = await updateDevice(deviceId, payload)
    if (version !== loadVersion || deviceId !== form.id || !props.visible) { emit('saved'); return }
    feedback.success(result?.data?.changed === false ? '资料没有变化' : '资料已修正并记录日志')
    emit('saved'); emit('update:visible', false)
  } catch (error: any) { if (version === loadVersion) feedback.error(error?.msg || error?.message || '保存失败，已填写资料会保留') }
  finally { saving.value = false }
}

watch(() => [props.visible, props.deviceId], () => {
  if (props.visible && props.deviceId) void loadDevice()
  else { ++loadVersion; ++schemaVersion; ++searchVersion; loading.value = false; schemaLoading.value = false; modelLoading.value = false }
}, { immediate: true })
onBeforeUnmount(() => { ++loadVersion; ++schemaVersion; ++searchVersion })
</script>

<style scoped>
.device-correction { min-height: 180px; }
.device-correction__owners { display: flex; flex-wrap: wrap; gap: 12px 24px; margin: 16px 0; color: #606266; font-size: 13px; }
.device-correction__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
.device-correction__control { width: 100%; }
.device-correction__error { display: flex; align-items: center; gap: 12px; margin: 16px 0; color: #b25e09; line-height: 1.6; }
.device-correction__hint { padding: 20px 0; color: #909399; }
.device-correction__model-tip { margin-bottom: 16px; }
.device-correction__reason { margin-top: 20px; }
@media (max-width: 600px) { .device-correction__grid { grid-template-columns: 1fr; } }
</style>
