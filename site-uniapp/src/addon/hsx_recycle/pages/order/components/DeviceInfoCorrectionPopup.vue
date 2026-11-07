<template>
    <u-popup :show="visible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="close">
        <view class="correction-popup">
            <view class="correction-head">
                <view><text class="correction-title">修改设备资料</text><text class="correction-subtitle">仅限确认前，不修改报价或流程状态</text></view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 correction-close" @click="close"></text>
            </view>
            <scroll-view scroll-y class="correction-body">
                <view v-if="loading" class="correction-hint">正在核对设备及修改权限…</view>
                <view v-else-if="loadError" class="correction-error">
                    <text>{{ loadError }}</text><u-button size="small" :plain="true" @click="loadDevice">重新加载</u-button>
                </view>
                <template v-else-if="device">
                    <view class="correction-trace">
                        <view><text>首次录入</text><text>{{ provenanceName(ability.provenance?.initial_entry) }}</text></view>
                        <view><text>质检人员</text><text>{{ provenanceName(ability.provenance?.checker) }}</text></view>
                    </view>
                    <view v-if="!ability.allowed" class="correction-error">{{ ability.reason || '设备已确认或进入后续业务，不支持修改资料。' }}</view>
                    <template v-else>
                        <view class="correction-note">修改前后内容与本次操作人会记录在设备日志中。确认后将进入 ERP，不再支持从这里修改。</view>
                        <view class="correction-field">
                            <text class="correction-label">设备型号 <text class="correction-required">*</text></text>
                            <view class="correction-model" @click="openModelPicker"><text>{{ form.model || '搜索并选择具体型号' }}</text><text class="nc-iconfont nc-icon-youV6xx1"></text></view>
                        </view>
                        <view v-for="field in identityFields" :key="field.key" class="correction-field">
                            <text class="correction-label">{{ field.label }}<text v-if="field.required" class="correction-required"> *</text></text>
                            <u-input v-model="form[field.key]" border="bottom" :maxlength="field.maxlength" :disabled="saving" :placeholder="field.placeholder" inputAlign="right" fontSize="28rpx" />
                        </view>
                        <view v-if="summaryLoading" class="correction-hint">加载设备摘要…</view>
                        <view v-else-if="summaryError" class="correction-error"><text>{{ summaryError }}</text><text class="correction-small">{{ canSaveIdentityOnly() ? '摘要暂不可修改，原摘要保持不变；可重试加载，或仅保存基础资料。' : '已填写的摘要保留，请重新加载后再保存，避免丢失修改。' }}</text><u-button size="small" :plain="true" @click="retrySummary">重新加载摘要</u-button></view>
                        <view v-else-if="summaryFields.length" class="correction-section">
                            <text class="correction-section-title">设备摘要</text>
                            <view v-for="field in summaryFields" :key="field.field_key" class="correction-field">
                                <text class="correction-label">{{ field.field_name }}<text v-if="Number(field.is_required) === 1" class="correction-required"> *</text><text v-if="field.unit">（{{ field.unit }}）</text></text>
                                <RecycleTagGroup v-if="isOptionField(field)" v-model="summaryValues[field.field_key]" :options="field.options || []" :multiple="isMultiple(field)" />
                                <u-switch v-else-if="field.component === 'switch'" v-model="summaryValues[field.field_key]" :disabled="saving" />
                                <u-textarea v-else-if="field.component === 'textarea'" v-model="summaryValues[field.field_key]" :disabled="saving" :placeholder="field.placeholder || '请输入'" />
                                <u-input v-else v-model="summaryValues[field.field_key]" :type="field.component === 'number' ? 'digit' : 'text'" :disabled="saving" border="bottom" :placeholder="field.placeholder || '请输入'" inputAlign="right" fontSize="28rpx" />
                            </view>
                        </view>
                        <view v-else class="correction-hint">该模板未配置设备摘要，可修改上方基础资料。</view>
                        <view class="correction-field"><text class="correction-label">修改说明（选填）</text><u-textarea v-model="form.correction_reason" :disabled="saving" :maxlength="200" placeholder="例如：核对实机后发现型号录错" /></view>
                        <view v-if="saveError" class="correction-error">{{ saveError }}</view>
                    </template>
                </template>
            </scroll-view>
            <view class="correction-footer"><u-button :disabled="saving" @click="close" :customStyle="{ flex: 1 }">取消</u-button><u-button type="primary" :loading="saving" :disabled="loading || !ability.allowed || summaryLoading || (!!summaryError && !canSaveIdentityOnly()) || !!loadError" @click="save" :customStyle="{ flex: 2 }">{{ summaryError && canSaveIdentityOnly() ? '仅保存基础资料' : '保存并记录日志' }}</u-button></view>
        </view>
    </u-popup>
    <u-popup :show="modelPickerVisible" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="closeModelPicker">
        <view class="correction-picker">
            <view class="correction-head"><text class="correction-title">选择具体型号</text><text class="nc-iconfont nc-icon-guanbiV6xx1 correction-close" @click="closeModelPicker"></text></view>
            <view class="correction-search"><u-input v-model="modelKeyword" placeholder="搜索型号名称" clearable border="surround" @confirm="searchModels" /><u-button size="small" type="primary" :loading="modelLoading" @click="searchModels">搜索</u-button></view>
            <scroll-view scroll-y class="correction-picker-list">
                <view v-if="modelError" class="correction-error">{{ modelError }}</view>
                <view v-else-if="!modelLoading && !modelOptions.length" class="correction-hint">输入型号名称搜索，只能选择具体型号。</view>
                <view v-for="node in modelOptions" :key="node.id" class="correction-model-option" @click="chooseModel(node)"><text>{{ leafName(node.node_name || node.model_full_name) }}</text><text class="correction-small">{{ node.model_path || node.model_full_name }}</text></view>
            </scroll-view>
        </view>
    </u-popup>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { getDevice, updateDevice, searchDeviceModelDictOptions } from '@/addon/hsx_recycle/api/order'
import { getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check-template'
import RecycleTagGroup from '@/addon/hsx_recycle/components/RecycleTagGroup.vue'

const props = defineProps<{ visible: boolean, deviceData?: Record<string, any> | null }>()
const emit = defineEmits(['update:visible', 'saved'])
const loading = ref(false), saving = ref(false), loadError = ref(''), saveError = ref('')
const device = ref<any>(null), ability = ref<any>({ allowed: false, provenance: {} })
const emptyForm = () => ({ model: '', category_id: 0, imei: '', imei2: '', sn: '', user_sn: '', correction_reason: '' } as Record<string, any>)
const form = ref(emptyForm())
const identityBaseline = ref<Record<string, any>>({})
const summaryFields = ref<any[]>([]), summaryValues = ref<Record<string, any>>({})
const summaryBaseline = ref<Record<string, any>>({})
const summaryLoading = ref(false), summaryError = ref('')
const modelPickerVisible = ref(false), modelKeyword = ref(''), modelOptions = ref<any[]>([]), modelLoading = ref(false), modelError = ref('')
let deviceRequest = 0, summaryRequest = 0, modelRequest = 0
const identityFields = [
    { key: 'imei', label: 'IMEI / SN', required: true, maxlength: 15, placeholder: '6–15位字母或数字' },
    { key: 'imei2', label: 'IMEI2', maxlength: 15, placeholder: '选填' },
    { key: 'sn', label: '设备 SN', maxlength: 80, placeholder: '选填' },
    { key: 'user_sn', label: '用户串号', maxlength: 80, placeholder: '选填' }
]
const leafName = (value: any) => String(value || '').split(/[\/／]/).map(part => part.trim()).filter(Boolean).pop() || ''
const provenanceName = (record: any) => record?.known && record?.name ? record.name : '历史未记录'
const isOptionField = (field: any) => ['radio', 'select', 'checkbox'].includes(field.component)
const isMultiple = (field: any) => field.component === 'checkbox' || (field.component === 'select' && field.selection_mode === 'multiple')
const isCategoryChanged = () => Number(form.value.category_id) !== Number(device.value?.category_id || 0)
const hasPendingSummaryChanges = () => Object.keys(summaryBaseline.value).some(key => JSON.stringify(summaryValues.value[key]) !== JSON.stringify(summaryBaseline.value[key]))
const canSaveIdentityOnly = () => !isCategoryChanged() && !hasPendingSummaryChanges()
const normalizeInfo = (value: any): Record<string, any> => {
    if (value && typeof value === 'object' && !Array.isArray(value)) return value
    try { const parsed = JSON.parse(value || '{}'); return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {} } catch { return {} }
}
const currentValues = (row: any) => {
    if (row?.info_correction?.summary && typeof row.info_correction.summary === 'object' && !Array.isArray(row.info_correction.summary)) return { ...row.info_correction.summary }
    const info = normalizeInfo(row?.info), meta = normalizeInfo(info.check_meta)
    const values = { ...normalizeInfo(info.sign_summary), ...info, ...normalizeInfo(meta.custom_fields), ...meta }
    for (const item of Array.isArray(meta.result_items) ? meta.result_items : []) if (item?.field_key && item.value !== undefined) values[item.field_key] = item.value
    for (const key of ['capacity', 'color', 'system_version', 'warranty_info']) if (row?.[key] !== undefined && row[key] !== null && row[key] !== '') values[key] = row[key]
    return values
}
const invalidate = () => { deviceRequest++; summaryRequest++; modelRequest++; loading.value = false; summaryLoading.value = false; modelLoading.value = false; modelPickerVisible.value = false }
const close = () => { if (saving.value) return; invalidate(); emit('update:visible', false) }

const loadDevice = async () => {
    const id = Number(props.deviceData?.id || 0), request = ++deviceRequest
    summaryRequest++; modelRequest++
    modelPickerVisible.value = false; modelOptions.value = []; modelLoading.value = false
    loading.value = true; loadError.value = ''; saveError.value = ''; summaryError.value = ''
    ability.value = { allowed: false, provenance: {} }; device.value = null
    if (!id) { loading.value = false; loadError.value = '缺少设备信息，请关闭后重新打开'; return }
    try {
        const res: any = await getDevice(id)
        if (request !== deviceRequest || !props.visible) return
        const row = res?.data
        if (!row?.id || Number(row.id) !== id) throw new Error('未取得当前设备资料，请重新加载')
        if (!row.info_correction || typeof row.info_correction.allowed !== 'boolean') throw new Error('回收后端尚未提供资料修改权限，请先更新后端')
        device.value = row; ability.value = row.info_correction
        form.value = { ...emptyForm(), model: Number(row.category_id || 0) > 0 ? leafName(row.model) : row.model || '', category_id: Number(row.category_id || 0), imei: row.imei || '', imei2: row.imei2 || '', sn: row.sn || '', user_sn: row.user_sn || '' }
        identityBaseline.value = { ...form.value }
        summaryFields.value = []; summaryValues.value = currentValues(row)
        summaryBaseline.value = JSON.parse(JSON.stringify(summaryValues.value))
        if (ability.value.allowed) await loadSummary(Number(form.value.category_id), Number(row.check_template_id || 0))
    } catch (error: any) {
        if (request === deviceRequest) loadError.value = error?.msg || error?.message || '设备资料加载失败，请重试'
    } finally { if (request === deviceRequest) loading.value = false }
}

const loadSummary = async (categoryId = Number(form.value.category_id), templateId = 0) => {
    const request = ++summaryRequest
    summaryLoading.value = true; summaryError.value = ''; summaryFields.value = []
    if (!categoryId && !templateId) { summaryLoading.value = false; return }
    try {
        const params: Record<string, any> = { category_id: categoryId }
        if (templateId > 0) params.template_id = templateId
        const res: any = await getCheckTemplateSchema(params)
        if (request !== summaryRequest || !props.visible) return
        const data = res?.data
        if (!data?.template || !Array.isArray(data.groups)) throw new Error('未取得有效的设备模板，请重新加载')
        if (['missing', 'invalid'].includes(data.schema_status)) throw new Error(data.schema_message || '设备模板不可用，请联系管理员')
        const fields: any[] = []
        for (const group of data.groups) for (const field of Array.isArray(group.fields) ? group.fields : []) if (Number(field?.extra_config?.summary_visible) === 1) fields.push({ ...field, component: field.component || 'input' })
        summaryFields.value = fields
        const values = { ...summaryValues.value }, baseline: Record<string, any> = {}
        const original = categoryId === Number(device.value?.category_id || 0) ? currentValues(device.value) : {}
        for (const field of fields) {
            const key = String(field.field_key), multiple = isMultiple(field)
            const normalize = (value: any) => {
                if (value === undefined || value === null) return multiple ? [] : field.component === 'switch' ? false : ''
                return multiple && !Array.isArray(value) ? (value === '' ? [] : [value]) : value
            }
            // 纠错不是重新质检；未知数据保持空白，不把模板默认值当作真实检测结果。
            values[key] = normalize(values[key])
            baseline[key] = normalize(original[key])
        }
        summaryValues.value = values
        summaryBaseline.value = baseline
    } catch (error: any) {
        if (request === summaryRequest) summaryError.value = error?.msg || error?.message || '设备摘要加载失败，请重试'
    } finally { if (request === summaryRequest) summaryLoading.value = false }
}
const retrySummary = () => loadSummary(Number(form.value.category_id), Number(form.value.category_id) === Number(device.value?.category_id || 0) ? Number(device.value?.check_template_id || 0) : 0)
const openModelPicker = () => { if (saving.value) return; modelOptions.value = []; modelPickerVisible.value = true; modelKeyword.value = form.value.model; void searchModels() }
const closeModelPicker = () => { modelRequest++; modelLoading.value = false; modelPickerVisible.value = false }
const searchModels = async () => {
    const request = ++modelRequest
    modelLoading.value = true; modelError.value = ''
    try {
        const res: any = await searchDeviceModelDictOptions({ keyword: modelKeyword.value.trim() })
        if (request !== modelRequest || !modelPickerVisible.value) return
        modelOptions.value = (Array.isArray(res?.data) ? res.data : []).filter((node: any) => Number(node.has_children || 0) !== 1 && (!Array.isArray(node.child_list) || !node.child_list.length))
    } catch (error: any) { if (request === modelRequest) modelError.value = error?.msg || error?.message || '型号搜索失败，请重试' }
    finally { if (request === modelRequest) modelLoading.value = false }
}
const chooseModel = (node: any) => {
    if (saving.value || Number(node?.has_children || 0) === 1 || (Array.isArray(node?.child_list) && node.child_list.length)) return
    const id = Number(node?.id || 0)
    if (!id) return
    const changed = id !== Number(form.value.category_id)
    form.value.category_id = id; form.value.model = String(node.node_name || '').trim() || leafName(node.model_full_name)
    closeModelPicker()
    if (changed) { summaryValues.value = {}; void loadSummary(id) }
}

const save = async () => {
    if (saving.value || loading.value || summaryLoading.value) return
    saveError.value = ''
    if (!ability.value.allowed || loadError.value || (summaryError.value && !canSaveIdentityOnly())) { saveError.value = ability.value.reason || loadError.value || summaryError.value || '当前不能修改，请重新加载核对'; return }
    const identityChanged = (key: string) => String(form.value[key] ?? '') !== String(identityBaseline.value[key] ?? '')
    if (identityChanged('model') && !form.value.model?.trim()) { saveError.value = '请选择设备型号'; return }
    if (identityChanged('imei') && !/^[A-Za-z0-9]{6,15}$/.test(form.value.imei)) { saveError.value = 'IMEI / SN 需为6–15位字母或数字，不能含空格或特殊字符'; return }
    if (identityChanged('imei2') && form.value.imei2 && !/^[A-Za-z0-9]{6,15}$/.test(form.value.imei2)) { saveError.value = 'IMEI2需为6–15位字母或数字'; return }
    const summary: Record<string, any> = {}
    const categoryChanged = isCategoryChanged()
    for (const field of summaryFields.value) {
        const key = String(field.field_key), value = summaryValues.value[key]
        const changed = categoryChanged || JSON.stringify(value) !== JSON.stringify(summaryBaseline.value[key])
        if (changed && Number(field.is_required) === 1 && (value === undefined || value === null || value === '' || (Array.isArray(value) && !value.length))) { saveError.value = `请填写「${field.field_name}」`; return }
        if (changed) summary[key] = value === undefined || value === null ? (isMultiple(field) ? [] : '') : value
    }
    const request = deviceRequest, id = Number(device.value?.id || 0)
    saving.value = true
    try {
        const payload: Record<string, any> = { action: 'correct_info', correction_reason: form.value.correction_reason }
        for (const key of ['model', 'category_id', 'imei', 'imei2', 'sn', 'user_sn']) if (identityChanged(key)) payload[key] = form.value[key]
        if (categoryChanged) { payload.category_id = Number(form.value.category_id); payload.model = form.value.model }
        if (!summaryError.value) payload.summary = summary
        const identityOnly = !!summaryError.value
        const res: any = await updateDevice(id, payload)
        if (request !== deviceRequest || !props.visible) return
        if (res?.code !== undefined && res.code !== 1) throw res
        uni.showToast({ title: res?.data?.changed === false ? '资料没有变化' : identityOnly ? '基础资料已修改，原摘要保持不变' : '资料已修改并记录日志', icon: 'none' })
        emit('saved', id); invalidate(); emit('update:visible', false)
    } catch (error: any) { if (request === deviceRequest) saveError.value = error?.msg || error?.message || '保存失败，已填内容保留，请重试' }
    finally { saving.value = false }
}
watch([() => props.visible, () => props.deviceData?.id], ([visible]) => { if (visible) void loadDevice(); else invalidate() }, { immediate: true })
onBeforeUnmount(invalidate)
</script>

<style scoped lang="scss">
.correction-popup { height: 88vh; display: flex; flex-direction: column; color: #334155; background: #fff; border-radius: 20rpx 20rpx 0 0; }
.correction-head { display: flex; align-items: center; justify-content: space-between; padding: 28rpx 28rpx 22rpx; gap: 20rpx; border-bottom: 1rpx solid #edf0f5; }
.correction-title { display: block; font-size: 32rpx; font-weight: 600; }
.correction-subtitle { display: block; margin-top: 8rpx; color: #8590a2; font-size: 23rpx; }
.correction-close { font-size: 34rpx; padding: 10rpx; color: #8590a2; }
.correction-body { flex: 1; min-height: 0; box-sizing: border-box; padding: 0 28rpx; }
.correction-trace { margin: 22rpx 0; padding: 18rpx; background: #f5f7fb; border-radius: 12rpx; font-size: 24rpx; }
.correction-trace > view { display: flex; justify-content: space-between; gap: 24rpx; padding: 5rpx 0; }
.correction-trace > view > text:first-child { color: #8590a2; flex-shrink: 0; }
.correction-note, .correction-hint, .correction-small { font-size: 23rpx; color: #8590a2; line-height: 1.6; }
.correction-note { margin-bottom: 16rpx; }
.correction-hint { padding: 22rpx 0; }
.correction-field { padding: 16rpx 0; }
.correction-label { display: block; font-size: 26rpx; color: #475569; margin-bottom: 14rpx; }
.correction-required { color: #ef4444; }
.correction-model { display: flex; justify-content: space-between; gap: 20rpx; padding: 12rpx 0; font-size: 28rpx; border-bottom: 1rpx solid #e5e7eb; }
.correction-section { margin-top: 16rpx; border-top: 1rpx solid #edf0f5; padding-top: 20rpx; }
.correction-section-title { font-size: 27rpx; font-weight: 600; }
.correction-error { display: flex; flex-direction: column; gap: 14rpx; margin: 22rpx 0; padding: 20rpx; border-radius: 12rpx; background: #fff6eb; color: #9a5b16; font-size: 25rpx; line-height: 1.6; overflow-wrap: anywhere; }
.correction-footer { display: flex; gap: 20rpx; padding: 22rpx 28rpx; border-top: 1rpx solid #edf0f5; }
.correction-picker { height: 70vh; display: flex; flex-direction: column; background: #fff; border-radius: 20rpx 20rpx 0 0; }
.correction-search { display: flex; gap: 20rpx; align-items: center; padding: 22rpx 28rpx; }
.correction-search > view { flex: 1; min-width: 0; }
.correction-picker-list { flex: 1; min-height: 0; box-sizing: border-box; padding: 0 28rpx; }
.correction-model-option { display: flex; flex-direction: column; gap: 6rpx; padding: 22rpx 0; font-size: 28rpx; border-bottom: 1rpx solid #edf0f5; }
</style>
