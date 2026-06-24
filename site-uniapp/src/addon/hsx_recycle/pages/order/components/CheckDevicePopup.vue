<template>
    <u-popup :show="show" mode="bottom" round="20" :safeAreaInsetBottom="true" @close="handleClose">
        <view class="check-popup">
            <view class="check-header">
                <view class="check-header__main">
                    <view class="check-title">设备质检</view>
                    <view class="check-model">{{ deviceData?.model || '未识别设备' }}</view>
                    <view class="check-meta">
                        <view class="check-meta__item">
                            <text class="check-meta__label">IMEI</text>
                            <text class="check-meta__value">{{ deviceData?.imei || deviceData?.user_sn || '-' }}</text>
                        </view>
                        <view class="check-meta__item check-meta__item--price">
                            <text class="check-meta__label">参考价</text>
                            <text class="check-meta__value">¥{{ formatMoney(deviceData?.initial_price || 0) }}</text>
                        </view>
                    </view>
                </view>
                <text class="nc-iconfont nc-icon-guanbiV6xx1 check-close" @click="handleClose"></text>
            </view>

            <scroll-view scroll-y class="check-content">
                <view class="section">
                    <view class="section-title">质检结论</view>
                    <u-radio-group v-model="checkConclusion" placement="row" iconPlacement="left">
                        <u-radio
                            activeColor="#fa5c1e"
                            :name="1"
                            label="正常回收"
                            labelColor="#334155"
                            :labelSize="'28rpx'"
                            :customStyle="{ marginRight: '48rpx' }"
                        ></u-radio>
                        <u-radio
                            activeColor="#ef4444"
                            :name="2"
                            label="退回设备"
                            labelColor="#334155"
                            :labelSize="'28rpx'"
                        ></u-radio>
                    </u-radio-group>
                    <view class="section-tip">
                        {{ checkConclusion === 2 ? '该设备将按退回处理，质检不处理回收定价。' : '完成质检后进入回收定价，由定价环节决定报价和整备安排。' }}
                    </view>
                </view>

                <!-- 退回时整块隐藏：质检项 / 质检图片 / 质检摘要 -->
                <template v-if="!isReject">
                <view v-if="schemaLoading" class="loading-card">
                    <text>正在加载检测项...</text>
                </view>

                <!-- 质检项：分区卡片，每个检测项一行（标签左 + 选项横向滚动右），参考拍机堂 -->
                <view v-for="group in schemaGroups" :key="group.id || group.group_key" class="section section-card">
                    <view class="section-title">
                        <text class="section-title__name">{{ group.group_name }}</text>
                        <text class="group-default-btn" @tap="applyGroupDefaults(group)">一键默认</text>
                    </view>
                    <view v-if="group.description" class="group-desc">{{ group.description }}</view>

                    <view
                        v-for="field in group.fields || []"
                        :key="field.id || field.field_key"
                        class="field-row"
                        :class="{ 'field-row--block': !isTextField(field) && field.component !== 'switch' }"
                    >
                        <view class="field-row__label">
                            {{ field.field_name }}
                            <text v-if="Number(field.is_required || 0) === 1" class="field-required">*</text>
                        </view>

                        <view class="field-row__control">
                            <u-input
                                v-if="isTextField(field)"
                                v-model="fieldValues[field.field_key]"
                                class="field-input"
                                :type="field.component === 'number' ? 'digit' : 'text'"
                                :placeholder="field.placeholder || `请输入${ field.field_name }`"
                                border="none"
                                clearable
                                inputAlign="left"
                                fontSize="26rpx"
                                placeholderClass="text-[var(--text-color-light9)] text-[26rpx]"
                                @input="handleTemplateValueChange"
                            ></u-input>

                            <view v-else-if="field.component === 'switch'" class="field-switch">
                                <text class="field-switch__text">{{ fieldValues[field.field_key] ? '已开启' : '未开启' }}</text>
                                <u-switch
                                    v-model="fieldValues[field.field_key]"
                                    activeColor="#fa5c1e"
                                    @change="handleTemplateValueChange"
                                ></u-switch>
                            </view>

                            <!-- 选项：换行铺开（跨端最稳；横滑在小程序纵向scroll-view弹窗内拿不到手势，已实测放弃） -->
                            <RecycleTagGroup
                                v-else
                                v-model="fieldValues[field.field_key]"
                                :options="field.options || []"
                                :multiple="field.component === 'checkbox'"
                                @change="handleTemplateValueChange"
                            />
                        </view>
                    </view>
                </view>
                  <view class="section">
                    <view class="section-title">
                        <text>质检图片</text>
                        <text class="section-suffix">{{ checkImageCount }}/9</text>
                    </view>
                    <RecycleImageUploader
                        v-model="checkImages"
                        add-text="添加图片"
                        fail-text="质检图片上传失败"
                        :max-count="9"
                        :multiple="true"
                        @uploading="imageUploading = $event"
                    />
                </view>
                <view class="section">
                    <view class="section-title">
                        <text>质检摘要</text>
                        <text class="section-action" @click="resetSummaryToTemplate">按模板生成</text>
                    </view>
                    <!-- <u-textarea
                        v-model="formData.check_result_seller"
                        placeholder="请输入质检结果描述"
                        :maxlength="500"
                        :height="180"
                        count
                        @input="handleSummaryInput"
                    ></u-textarea> -->
                    <view v-if="generatedSummary" class="summary-preview">
                        <view class="summary-preview__label">模板摘要：</view>
                        <view class="summary-preview__value">{{ generatedSummary }}</view>
                    </view>
                </view>
                </template>

                <view class="section">
                    <view class="section-title">{{ isReject ? '退回原因' : '备注说明' }}</view>
                    <u-textarea
                        v-model="formData.remark"
                        :placeholder="isReject ? '请填写退回原因（如：用户不卖了、报价不符、设备异常）' : '如：特殊扣费说明、外观描述、退回原因'"
                        :maxlength="200"
                        :height="120"
                        count
                    ></u-textarea>
                </view>

              
            </scroll-view>

            <view class="check-footer">
                <u-button @click="handleClose" :customStyle="{ flex: 1 }">取消</u-button>
                <u-button @click="submitForm('save_draft')" :customStyle="{ flex: 1, margin: '0 16rpx' }" :loading="savingDraft">
                    暂存
                </u-button>
                <u-button type="primary" @click="submitForm('check')" :customStyle="{ flex: 1.4 }" :loading="submitting">
                    提交
                </u-button>
            </view>
        </view>
    </u-popup>

</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { getCheckTemplateAll, getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check-template'
import RecycleTagGroup from '@/addon/hsx_recycle/components/RecycleTagGroup.vue'
import { batchReturnDevices, getDevice, updateDevice } from '@/addon/hsx_recycle/api/order'
import RecycleImageUploader from '@/addon/hsx_recycle/components/RecycleImageUploader.vue'
import { confirmDanger } from '@/addon/hsx_recycle/utils/confirm'

interface Props {
    visible: boolean
    deviceData: any
}

interface TemplateField {
    id?: number
    field_key: string
    field_name: string
    component?: string
    placeholder?: string
    result_template?: string
    result_visible?: number
    is_required?: number
    options?: Array<{ value: string, label?: string, name?: string }>
}

const props = defineProps<Props>()
const emit = defineEmits(['update:visible', 'success'])

const show = ref(false)
const submitting = ref(false)
const savingDraft = ref(false)
const schemaLoading = ref(false)
const selectedTemplateId = ref<number | string>(0)
const activeTemplateScrollId = ref('')
const templateOptions = ref<any[]>([])
const schemaGroups = ref<any[]>([])
const fieldValues = ref<Record<string, any>>({})
const checkImages = ref('')
const imageUploading = ref(false)
const formData = ref({
    check_result_seller: '',
    remark: ''
})
const summaryAutoSync = ref(true)
const checkConclusion = ref(1)
// 退回：隐藏质检模板，提交时质检信息全部清空
const isReject = computed(() => Number(checkConclusion.value) === 2)
const originalInfo = ref<Record<string, any>>({})
const latestDeviceData = ref<any>(null)

const deviceData = computed(() => latestDeviceData.value || props.deviceData || {})
const selectedTemplateInfo = computed(() => {
    const targetId = Number(selectedTemplateId.value || 0)
    return templateOptions.value.find((item: any) => Number(item?.id || 0) === targetId) || null
})
const checkImageCount = computed(() => checkImages.value ? checkImages.value.split(',').filter(Boolean).length : 0)

watch(() => props.visible, (value) => {
    show.value = value
    if (value) {
        initPopup()
    }
})

watch(show, (value) => {
    if (!value) emit('update:visible', false)
})

const generatedSummary = computed(() => {
    const items: string[] = []
    schemaGroups.value.forEach((group: any) => {
        ;(group.fields || []).forEach((field: TemplateField) => {
            if (Number(field.result_visible ?? 1) !== 1) return
            const value = fieldValues.value[field.field_key]
            const text = formatFieldResult(field, value)
            if (text) items.push(text)
        })
    })
    return items.join('\n')
})

watch(generatedSummary, () => {
    syncSummaryWithTemplate()
})

const initPopup = async () => {
    submitting.value = false
    savingDraft.value = false
    summaryAutoSync.value = true
    schemaGroups.value = []
    fieldValues.value = {}

    // 先从接口获取最新的设备数据
    if (props.deviceData?.id) {
        try {
            const res: any = await getDevice(props.deviceData.id)
            if (res?.data) {
                latestDeviceData.value = res.data
            }
        } catch (error) {
            console.warn('获取设备最新数据失败，使用传入的数据', error)
            latestDeviceData.value = null
        }
    }

    originalInfo.value = normalizeObject(deviceData.value.info)
    formData.value = {
        check_result_seller: deviceData.value.check_result_seller || deviceData.value.check_result || '',
        remark: deviceData.value.remark || ''
    }
    checkConclusion.value = Number(deviceData.value.status) === 6 ? 2 : 1
    initImages()
    await loadTemplates()
    initializeSummarySync()
}

const initImages = () => {
    const raw = deviceData.value.check_images_seller || deviceData.value.check_images || ''
    checkImages.value = String(raw || '')
        .split(',')
        .filter(Boolean)
        .join(',')
    imageUploading.value = false
}

const loadTemplates = async () => {
    try {
        const res: any = await getCheckTemplateAll()
        templateOptions.value = Array.isArray(res?.data) ? res.data : []

        // 已有质检模板（之前质检过 / 中台带过来）→ 直接用；否则按 device_id 解析（与 PC check_template/schema?device_id 同口径）
        const explicitTplId = Number(deviceData.value.check_template_id || normalizeObject(originalInfo.value.check_meta).template_id || 0)
        if (explicitTplId > 0) {
            selectedTemplateId.value = explicitTplId
            activeTemplateScrollId.value = `check-template-${ explicitTplId }`
            await fetchTemplateSchema(explicitTplId)
        } else if (deviceData.value.id) {
            await loadSchemaByDevice()
        } else {
            const def = Number(
                templateOptions.value.find((item: any) => Number(item.is_default || 0) === 1)?.id ||
                templateOptions.value[0]?.id || 0
            )
            selectedTemplateId.value = def
            activeTemplateScrollId.value = def ? `check-template-${ def }` : ''
            if (def) await fetchTemplateSchema(def)
        }
    } catch (error) {
        templateOptions.value = []
        schemaGroups.value = []
    }
}

// 按 device_id 解析设备绑定的质检模板 + 字段（后端 resolveByDeviceId，一次返回 resolve+groups）
const loadSchemaByDevice = async () => {
    schemaLoading.value = true
    try {
        const res: any = await getCheckTemplateSchema({ device_id: deviceData.value.id })
        const data = res?.data || {}
        const resolve = data.resolve || {}
        const tplId = Number(resolve.template_id || data.template?.id || 0)
        if (tplId) {
            selectedTemplateId.value = tplId
            activeTemplateScrollId.value = `check-template-${ tplId }`
        }
        schemaGroups.value = Array.isArray(data.groups) ? data.groups : []
        restoreFieldValues()
    } catch (error) {
        console.warn('按设备解析质检模板失败', error)
        schemaGroups.value = []
    } finally {
        schemaLoading.value = false
    }
}

const handleTemplateChange = async (templateId: number | string) => {
    if (Number(selectedTemplateId.value) === Number(templateId)) return
    selectedTemplateId.value = Number(templateId)
    activeTemplateScrollId.value = `check-template-${ templateId }`
    await fetchTemplateSchema(templateId)
    summaryAutoSync.value = true
    syncSummaryWithTemplate(true)
}

const fetchTemplateSchema = async (templateId: number | string) => {
    schemaLoading.value = true
    try {
        const res: any = await getCheckTemplateSchema({ template_id: templateId, scene: 'phone' })
        schemaGroups.value = Array.isArray(res?.data?.groups) ? res.data.groups : []
        restoreFieldValues()
    } catch (error) {
        schemaGroups.value = []
        fieldValues.value = {}
    } finally {
        schemaLoading.value = false
    }
}

// 空值判断（与 PC useCheckMeta 口径一致）
const isEmptyFieldValue = (value: any) =>
    value === undefined || value === null || value === '' || (Array.isArray(value) && value.length === 0)

// 反显签收/代下单录入的设备信息：优先取 info.sign_summary（对象），兼容旧数据扁平存于 info。
// 与 PC applySignSummary 口径一致：reserved 键不当作质检字段。
const extractSignValues = (info: Record<string, any>): Record<string, any> => {
    const summary = normalizeObject(info?.sign_summary)
    if (summary && Object.keys(summary).length) {
        return { ...summary }
    }
    const reserved = new Set(['goods_category', 'check_meta', 'sign_summary', 'coverage'])
    const out: Record<string, any> = {}
    Object.entries(info || {}).forEach(([key, value]) => {
        if (!reserved.has(key) && !isEmptyFieldValue(value)) out[key] = value
    })
    return out
}

// 字段默认值：选项类取 is_default 的项；开关/文本取 default_value
const fieldDefaultValue = (field: any) => {
    const multi = field.component === 'checkbox'
    const opts = Array.isArray(field.options) ? field.options : []
    if (opts.length) {
        const defs = opts.filter((o: any) => Number(o.is_default) === 1).map((o: any) => String(o.value))
        return multi ? defs : (defs.length ? defs[0] : '')
    }
    if (field.component === 'switch') return normalizeBoolean(field.default_value)
    return (field.default_value !== undefined && field.default_value !== null) ? field.default_value : ''
}

// 一键默认：把某大类下所有检测项设为默认选项
const applyGroupDefaults = (group: any) => {
    const next = { ...fieldValues.value }
    ;(group.fields || []).forEach((field: any) => {
        next[field.field_key] = fieldDefaultValue(field)
    })
    fieldValues.value = next
    handleTemplateValueChange()
}

const restoreFieldValues = () => {
    const nextValues: Record<string, any> = {}
    const checkMeta = normalizeObject(originalInfo.value.check_meta)

    // 如果 check_meta 有 result_items（PC 端格式），转换为平铺格式
    let flatCheckMeta = { ...checkMeta }
    if (checkMeta.result_items && Array.isArray(checkMeta.result_items)) {
        checkMeta.result_items.forEach((item: any) => {
            if (item.field_key && item.value !== undefined) {
                flatCheckMeta[item.field_key] = item.value
            }
        })
    }

    // 是否已有真实质检数据（有 result_items，或除 template_id 外存在有效键）
    const hasRealCheckMeta =
        (Array.isArray(checkMeta.result_items) && checkMeta.result_items.length > 0) ||
        Object.keys(checkMeta).some((key) => !['template_id', 'result_items'].includes(key) && !isEmptyFieldValue(checkMeta[key]))

    // 签收/代下单录入值：仅在无真实质检时作为底层预填，真实质检与设备字段仍优先覆盖
    const signValues = hasRealCheckMeta ? {} : extractSignValues(originalInfo.value)

    const source = {
        ...signValues,
        ...flatCheckMeta,
        capacity: deviceData.value.capacity || originalInfo.value.capacity || flatCheckMeta.capacity || '',
        color: deviceData.value.color || originalInfo.value.color || flatCheckMeta.color || '',
        system_version: deviceData.value.system_version || originalInfo.value.system_version || flatCheckMeta.system_version || '',
        warranty_info: deviceData.value.warranty_info || originalInfo.value.warranty_info || flatCheckMeta.warranty_info || '',
        battery: flatCheckMeta.battery ?? '',
        battery_num: flatCheckMeta.battery_num ?? '',
        activation_lock: normalizeBoolean(flatCheckMeta.activation_lock ?? flatCheckMeta.activationLock ?? false),
        mdm_lock: normalizeBoolean(flatCheckMeta.mdm_lock ?? flatCheckMeta.mdmLock ?? false)
    }

    schemaGroups.value.forEach((group: any) => {
        ;(group.fields || []).forEach((field: TemplateField) => {
            const currentValue = fieldValues.value[field.field_key]
            if (currentValue !== undefined && currentValue !== null && currentValue !== '') {
                nextValues[field.field_key] = currentValue
                return
            }

            let rawValue = source[field.field_key]
            // 无录入值 → 用模板默认（默认选中 is_default 选项）
            if (isEmptyFieldValue(rawValue)) {
                rawValue = fieldDefaultValue(field)
            }
            if (field.component === 'checkbox') {
                nextValues[field.field_key] = Array.isArray(rawValue)
                    ? rawValue.map((item: any) => String(item))
                    : (isEmptyFieldValue(rawValue) ? [] : [String(rawValue)])
            } else if (field.component === 'switch') {
                nextValues[field.field_key] = normalizeBoolean(rawValue)
            } else if (field.component === 'number') {
                nextValues[field.field_key] = rawValue === undefined || rawValue === null ? '' : String(rawValue)
            } else {
                nextValues[field.field_key] = rawValue === undefined || rawValue === null ? '' : rawValue
            }
        })
    })
    fieldValues.value = nextValues
}

const isTextField = (field: TemplateField) => {
    return ['input', 'number', '', undefined].includes(field.component as any)
}

const isInlineField = (field: TemplateField) => {
    return isTextField(field) || field.component === 'switch'
}

const toggleFieldOption = (field: TemplateField, optionValue: string) => {
    if (field.component === 'checkbox') {
        const current = Array.isArray(fieldValues.value[field.field_key]) ? [...fieldValues.value[field.field_key]] : []
        const exists = current.includes(optionValue)
        fieldValues.value[field.field_key] = exists
            ? current.filter(item => item !== optionValue)
            : [...current, optionValue]
    } else {
        fieldValues.value[field.field_key] = optionValue
    }
    handleTemplateValueChange()
}

const getOptionClass = (field: TemplateField, optionValue: string) => {
    const value = fieldValues.value[field.field_key]
    const active = Array.isArray(value) ? value.includes(optionValue) : String(value) === String(optionValue)
    return active ? 'option-chip option-chip--active' : 'option-chip'
}

// u-tag 选中态判断
const isOptionActive = (field: TemplateField, optionValue: any) => {
    const value = fieldValues.value[field.field_key]
    return Array.isArray(value)
        ? value.some((v: any) => String(v) === String(optionValue))
        : String(value) === String(optionValue)
}

const handleTemplateValueChange = () => {
    syncSummaryWithTemplate()
}

const resetSummaryToTemplate = () => {
    summaryAutoSync.value = true
    syncSummaryWithTemplate(true)
}

const handleSummaryInput = (value: any) => {
    const nextValue = typeof value === 'string'
        ? value
        : value?.detail?.value ?? value?.target?.value ?? formData.value.check_result_seller

    formData.value.check_result_seller = nextValue
    summaryAutoSync.value = !normalizeSummaryText(nextValue) || normalizeSummaryText(nextValue) === normalizeSummaryText(generatedSummary.value)
}

const initializeSummarySync = () => {
    const currentSummary = normalizeSummaryText(formData.value.check_result_seller)
    const templateSummary = normalizeSummaryText(generatedSummary.value)

    if (!currentSummary) {
        summaryAutoSync.value = true
        syncSummaryWithTemplate(true)
        return
    }

    if (!templateSummary) {
        summaryAutoSync.value = false
        return
    }

    summaryAutoSync.value = currentSummary === templateSummary
    if (summaryAutoSync.value) {
        syncSummaryWithTemplate(true)
    }
}

const syncSummaryWithTemplate = (force = false) => {
    const currentSummary = normalizeSummaryText(formData.value.check_result_seller)
    if (force || summaryAutoSync.value || !currentSummary) {
        formData.value.check_result_seller = generatedSummary.value
    }
}

const submitForm = async (action: 'check' | 'save_draft') => {
    if (!deviceData.value.id) {
        uni.showToast({ title: '请选择设备', icon: 'none' })
        return
    }

    if (imageUploading.value) {
        uni.showToast({ title: '图片上传中，请稍候', icon: 'none' })
        return
    }

    if (action === 'check') {
        // 退回不需要质检摘要；正常回收才校验
        if (!isReject.value && !formData.value.check_result_seller.trim() && !generatedSummary.value) {
            uni.showToast({ title: '请填写质检摘要', icon: 'none' })
            return
        }
        // 二次确认：质检提交进入下一环节，避免误提交
        const tip = Number(checkConclusion.value) === 2
            ? '确认将该设备按【退回】处理并提交质检？'
            : '确认提交质检结果？提交后进入回收定价环节。'
        const ok = await confirmDanger(tip, { title: '确认提交质检', confirmText: '确认提交' })
        if (!ok) return
    }

    if (action === 'check') submitting.value = true
    if (action === 'save_draft') savingDraft.value = true

    try {
        const payload = buildSubmitPayload(action)
        // 直接传扁平参数（后端已统一不再包 data 层）
        await updateDevice(deviceData.value.id, payload)
        if (shouldCreateReturnOrder(action)) {
            await createReturnOrder()
        }
        uni.showToast({ title: getSuccessMessage(action), icon: 'success' })
        emit('success')
        handleClose()
    } catch (error: any) {
        uni.showToast({ title: error?.msg || error?.message || '提交失败', icon: 'none' })
    } finally {
        submitting.value = false
        savingDraft.value = false
    }
}

const shouldCreateReturnOrder = (action: 'check' | 'save_draft') => {
    if (action !== 'check') return false
    if (Number(checkConclusion.value) !== 2) return false
    if (Number(deviceData.value.return_order_id || 0) > 0) return false
    return true
}

const createReturnOrder = async () => {
    const remark = formData.value.remark.trim()
        || normalizeSummaryText(formData.value.check_result_seller)
        || generatedSummary.value
        || '移动端质检退回设备'

    await batchReturnDevices({
        ids: String(deviceData.value.id),
        remark
    })
}

const getSuccessMessage = (action: 'check' | 'save_draft') => {
    if (action === 'save_draft') return '已暂存质检数据'
    if (Number(checkConclusion.value) === 2) return '已创建退回处理'
    return '质检提交成功'
}

const buildSubmitPayload = (action: 'check' | 'save_draft') => {
    const checkMeta = buildCheckMeta()
    const infoFields = buildInfoFields()
    const sellerSummary = normalizeSummaryText(formData.value.check_result_seller) || generatedSummary.value
    const buyerSummary = String(deviceData.value.check_result_buyer || '').trim()
    const buyerImages = normalizeImageValue(deviceData.value.check_images_buyer)
    const sellerImages = normalizeImageValue(checkImages.value)
    const info: Record<string, any> = {
        ...originalInfo.value,
        ...infoFields,
        goods_category: resolveGoodsCategory(),
        check_meta: checkMeta
    }

    // 退回：质检信息全部清空，只保留退回标记 + 退回原因(remark) + 设备基本信息
    if (isReject.value) {
        return {
            action,
            check_status: checkConclusion.value,
            check_result: '',
            check_result_seller: '',
            check_result_buyer: '',
            check_images: '',
            check_images_seller: '',
            check_images_buyer: '',
            remark: formData.value.remark.trim(),
            imei: String(deviceData.value.imei || deviceData.value.user_sn || ''),
            check_template_id: 0,
            model: String(deviceData.value.model || originalInfo.value.model || ''),
            info: { ...originalInfo.value, goods_category: resolveGoodsCategory(), check_meta: {} }
        }
    }

    return {
        action,
        check_status: checkConclusion.value,
        check_result: '',
        check_result_seller: sellerSummary,
        check_result_buyer: buyerSummary,
        check_images: sellerImages,
        check_images_seller: sellerImages,
        check_images_buyer: buyerImages,
        remark: formData.value.remark.trim(),
        imei: String(deviceData.value.imei || deviceData.value.user_sn || ''),
        check_template_id: Number(selectedTemplateId.value || 0),
        model: String(deviceData.value.model || originalInfo.value.model || ''),
        capacity: info.capacity,
        color: info.color,
        system_version: info.system_version,
        warranty_info: info.warranty_info,
        info
    }
}

const buildCheckMeta = () => {
    // 参考 PC 端格式生成 check_meta，确保跨端兼容
    // PC 端使用 result_items 数组存储字段值和显示信息
    const originalMeta = normalizeObject(originalInfo.value.check_meta)
    const meta: Record<string, any> = {
        version: 2,
        template_id: Number(selectedTemplateId.value || 0),
        template_version: Number(selectedTemplateInfo.value?.version ?? originalMeta.template_version ?? 0),
        function_ids: Array.isArray(originalMeta.function_ids) ? [...originalMeta.function_ids] : [],
        fix_ids: Array.isArray(originalMeta.fix_ids) ? [...originalMeta.fix_ids] : [],
        activation_lock: normalizeBoolean(originalMeta.activation_lock ?? originalMeta.activationLock ?? false),
        mdm_lock: normalizeBoolean(originalMeta.mdm_lock ?? originalMeta.mdmLock ?? false),
        custom_fields: normalizeObject(originalMeta.custom_fields),
        result_items: []
    }

    // 构建 result_items 数组（PC 端格式）
    const resultItems: any[] = []

    schemaGroups.value.forEach((group: any) => {
        (group.fields || []).forEach((field: TemplateField) => {
            const value = fieldValues.value[field.field_key]

            // 跳过空值
            if (value === '' || value === undefined || value === null) return
            if (Array.isArray(value) && value.length === 0) return

            // 只保存 result_visible 为 1 的字段
            if (Number(field.result_visible ?? 1) !== 1) return

            // 构建 result_item
            const item: any = {
                field_key: field.field_key,
                field_name: field.field_name,
                component: field.component || 'input',
                value: value,
                values: Array.isArray(value) ? value : [String(value)],
                labels: [],
                option_items: [],
                text: '',
                option_styles: {}
            }

            // 处理选项类型字段
            if (field.options && field.options.length > 0) {
                if (Array.isArray(value)) {
                    // checkbox 多选
                    value.forEach((v: string) => {
                        const option = field.options?.find(opt => String(opt.value) === String(v))
                        if (option) {
                            item.labels.push(option.label || option.name || v)
                            item.option_items.push({
                                value: v,
                                label: option.label || option.name || v
                            })
                        }
                    })
                } else {
                    // radio/select 单选
                    const option = field.options.find(opt => String(opt.value) === String(value))
                    if (option) {
                        item.labels.push(option.label || option.name || value)
                        item.option_items.push({
                            value: String(value),
                            label: option.label || option.name || value
                        })
                    }
                }
            } else {
                // 输入类型字段
                item.labels.push(String(value))
            }

            // 生成显示文本
            if (field.result_template) {
                // 使用模板
                item.text = field.result_template
                    .replace(/\{labels\}/g, item.labels.join('、'))
                    .replace(/\{label\}/g, item.labels.join('、'))
                    .replace(/\{value\}/g, item.labels.join('、'))
            } else {
                // 默认格式
                if (item.labels.length > 0) {
                    item.text = `${field.field_name}${item.labels.join('、')}`
                } else {
                    item.text = `${field.field_name}: ${value}`
                }
            }

            resultItems.push(item)

            // 同时保存到 custom_fields（兼容旧格式）
            if (field.component === 'checkbox') {
                if (Array.isArray(value) && value.length) meta.custom_fields[field.field_key] = value
            } else if (field.component === 'switch') {
                meta.custom_fields[field.field_key] = normalizeBoolean(value)
            } else if (value !== '' && value !== undefined && value !== null) {
                meta.custom_fields[field.field_key] = value
            }
        })
    })

    meta.result_items = resultItems

    // 处理特殊字段
    if (fieldValues.value.activation_lock !== undefined) {
        meta.activation_lock = normalizeBoolean(fieldValues.value.activation_lock)
    }
    if (fieldValues.value.mdm_lock !== undefined) {
        meta.mdm_lock = normalizeBoolean(fieldValues.value.mdm_lock)
    }

    return meta
}

const formatFieldResult = (field: TemplateField, value: any) => {
    if (value === '' || value === undefined || value === null) return ''
    if (field.component === 'switch') {
        if (!normalizeBoolean(value)) return ''
        return field.result_template || `${ field.field_name }开启`
    }

    const labels = getFieldValueLabel(field, value)
    if (!labels) return ''
    const template = field.result_template || ''
    if (template) {
        return template
            .replace(/\{labels\}/g, labels)
            .replace(/\{label\}/g, labels)
            .replace(/\{value\}/g, labels)
    }
    return `${ field.field_name }：${ labels }`
}

const getFieldValueLabel = (field: TemplateField, value: any) => {
    if (Array.isArray(value)) {
        return value.map((item: any) => findOptionLabel(field, item)).filter(Boolean).join('、')
    }
    if (field.component === 'checkbox') return ''
    if ((field.options || []).length) {
        return findOptionLabel(field, value)
    }
    return String(value)
}

const findOptionLabel = (field: TemplateField, value: any) => {
    const target = (field.options || []).find(option => String(option.value) === String(value))
    return target?.label || target?.name || String(value)
}

const handleClose = () => {
    show.value = false
    emit('update:visible', false)
}

const normalizeObject = (value: any) => {
    if (!value) return {}
    if (typeof value === 'string') {
        try {
            const parsed = JSON.parse(value)
            return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {}
        } catch (error) {
            return {}
        }
    }
    if (Array.isArray(value)) return {}
    return typeof value === 'object' ? value : {}
}

const normalizeBoolean = (value: any) => {
    return value === true || value === 1 || value === '1' || value === 'true' || value === '开启' || value === 'On'
}

const normalizeSummaryText = (value: any) => String(value || '').trim()

const formatMoney = (value: number | string) => Number(value || 0).toFixed(2)

const normalizeImageValue = (value: any) => String(value || '')
    .split(',')
    .map((item) => item.trim())
    .filter(Boolean)
    .join(',')

const buildInfoFields = () => {
    const infoFields: Record<string, any> = {}

    Object.keys(fieldValues.value).forEach((key) => {
        const value = fieldValues.value[key]
        if (value === undefined || value === null) return
        if (Array.isArray(value)) {
            infoFields[key] = [...value]
            return
        }
        infoFields[key] = value
    })

    infoFields.capacity = fieldValues.value.capacity || originalInfo.value.capacity || ''
    infoFields.color = fieldValues.value.color || originalInfo.value.color || ''
    infoFields.system_version = fieldValues.value.system_version || originalInfo.value.system_version || ''
    infoFields.warranty_info = fieldValues.value.warranty_info || originalInfo.value.warranty_info || ''

    return infoFields
}

const resolveGoodsCategory = () => {
    const fromInfo = originalInfo.value.goods_category
    if (Array.isArray(fromInfo) && fromInfo.length) {
        return fromInfo.map((item: any) => String(item)).filter(Boolean)
    }

    const fromDevicePath = deviceData.value.category_path
    if (Array.isArray(fromDevicePath) && fromDevicePath.length) {
        return fromDevicePath.map((item: any) => String(item)).filter(Boolean)
    }

    const categoryId = deviceData.value.category_id || originalInfo.value.category_id
    return categoryId ? [String(categoryId)] : []
}
</script>

<style scoped lang="scss">
.check-popup {
    background: #fff;
    height: 88vh;
    max-height: 88vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.check-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 18rpx;
    padding: 28rpx 30rpx 22rpx;
    border-bottom: 1rpx solid #f2f3f5;
    flex-shrink: 0;
}

.check-header__main {
    flex: 1;
    min-width: 0;
}

.check-title {
    font-size: 32rpx;
    font-weight: 700;
    color: #1f2937;
}

.check-model {
    margin-top: 8rpx;
    font-size: 26rpx;
    font-weight: 600;
    color: #334155;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.check-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12rpx;
    margin-top: 14rpx;
}

.check-meta__item {
    display: flex;
    align-items: center;
    gap: 8rpx;
    padding: 6rpx 14rpx;
    border-radius: 999rpx;
    background: #f4f5f7;
}

.check-meta__label {
    font-size: 21rpx;
    color: #94a3b8;
}

.check-meta__value {
    font-size: 23rpx;
    font-weight: 600;
    color: #334155;
}

.check-meta__item--price {
    background: #fff2e9;
}

.check-meta__item--price .check-meta__label {
    color: #fa9a73;
}

.check-meta__item--price .check-meta__value {
    color: #fa5c1e;
}

.check-close {
    flex-shrink: 0;
    font-size: 36rpx;
    color: #94a3b8;
    line-height: 1;
}

.price {
    color: #ea580c;
}

.check-content {
    /* 用 flex 自适应填满"头部+设备信息"之外的剩余空间，而非写死 calc(88vh-340rpx)。
       硬编码估算高度算不准时 scroll-view 会错位、滚动发涩；flex:1+min-height:0 让它拿到精确高度，原生滚动最稳。 */
    flex: 1 1 0;
    min-height: 0;
    padding: 20rpx 30rpx 30rpx;
    box-sizing: border-box;
    background: #f5f6f8;
}

.section,
.section-card,
.loading-card {
    margin-bottom: 24rpx;
    padding: 24rpx 26rpx;
    border-radius: 20rpx;
    background: #ffffff;
    box-shadow: 0 6rpx 24rpx rgba(17, 24, 39, 0.06);
    border: 1rpx solid #f1f2f4;
}

.section-title__name {
    position: relative;
    padding-left: 18rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
}

.section-title__name::before {
    content: '';
    position: absolute;
    left: 0;
    top: 6rpx;
    bottom: 6rpx;
    width: 6rpx;
    border-radius: 4rpx;
    background: #fa5c1e;
}

.group-default-btn {
    flex: 0 0 auto;
    font-size: 24rpx;
    font-weight: 500;
    color: #fa5c1e;
    padding: 6rpx 20rpx;
    border: 1rpx solid #ffd2bd;
    border-radius: 999rpx;
    background: #fff6f1;
}

.group-desc {
    font-size: 22rpx;
    color: #94a3b8;
    margin: -6rpx 0 14rpx;
    line-height: 1.5;
}

.section-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16rpx;
    margin-bottom: 16rpx;
    font-size: 28rpx;
    font-weight: 600;
    color: #1f2937;
}

.section-title__desc,
.section-suffix,
.section-action {
    font-size: 22rpx;
    font-weight: 400;
    color: #64748b;
}

.section-action {
    color: #fa5c1e;
}

.section-tip {
    margin-top: 12rpx;
    font-size: 22rpx;
    color: #64748b;
    line-height: 1.6;
}

.chip-row,
.option-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.chip,
.template-chip,
.option-chip {
    padding: 14rpx 24rpx;
    border-radius: 999rpx;
    background: #f5f7fa;
    color: #475569;
    font-size: 24rpx;
    border: 2rpx solid transparent;
}

.chip--active,
.template-chip--active,
.option-chip--active {
    background: #fff2e9;
    border-color: #fa5c1e;
    color: #fa5c1e;
}

.chip--danger {
    background: #fff1f2;
    color: #e11d48;
}

.chip--active-danger {
    background: #fee2e2;
    border-color: #fca5a5;
    color: #dc2626;
}

.template-scroll {
    white-space: nowrap;
    width: 100%;
}

.template-row {
    display: inline-flex;
    flex-wrap: nowrap;
    gap: 16rpx;
    min-width: 100%;
}

.template-chip {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    max-width: 360rpx;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.loading-card {
    font-size: 24rpx;
    color: #64748b;
}

.field-block + .field-block {
    margin-top: 14rpx;
    padding-top: 14rpx;
    border-top: 1rpx solid #e5e7eb;
}

.field-block--inline {
    display: flex;
    align-items: center;
    gap: 18rpx;
}

.field-block--inline .field-label {
    width: 160rpx;
    min-width: 160rpx;
    margin-bottom: 0;
    line-height: 1.4;
}

.field-block--inline .field-control,
.field-block--inline .field-switch {
    flex: 1;
    min-width: 0;
}

.field-label {
    margin-bottom: 12rpx;
    font-size: 24rpx;
    color: #334155;
    font-weight: 500;
}

.field-required {
    margin-left: 6rpx;
    color: #ef4444;
}

.field-input {
    width: 100%;
    height: 68rpx;
    padding: 0 20rpx;
    border-radius: 12rpx;
    background: #fff;
    border: 1rpx solid #dbe2ea;
    font-size: 26rpx;
    box-sizing: border-box;
}

.field-switch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12rpx 18rpx;
    min-height: 68rpx;
    border-radius: 12rpx;
    background: #fff;
    border: 1rpx solid #dbe2ea;
}

.field-switch__text {
    font-size: 24rpx;
    color: #475569;
}

.summary-preview {
    margin-top: 12rpx;
    padding: 16rpx 18rpx;
    border-radius: 12rpx;
    background: #f8fafc;
    font-size: 22rpx;
    line-height: 1.7;
}

.summary-preview__label {
    color: #64748b;
    margin-bottom: 8rpx;
}

.summary-preview__value {
    color: #334155;
    white-space: pre-wrap;
    word-break: break-all;
    line-height: 1.8;
}

.price-box {
    display: flex;
    align-items: center;
    padding: 0 22rpx;
    height: 84rpx;
    border-radius: 14rpx;
    background: #fff7ed;
    border: 1rpx solid #fed7aa;
}

.price-box__symbol {
    margin-right: 10rpx;
    font-size: 34rpx;
    font-weight: 700;
    color: #ea580c;
}

.price-box__input {
    flex: 1;
    font-size: 34rpx;
    font-weight: 700;
    color: #1f2937;
}

.check-footer {
    display: flex;
    padding: 20rpx 30rpx calc(20rpx + env(safe-area-inset-bottom));
    border-top: 1rpx solid #f2f3f5;
    background: #fff;
    flex-shrink: 0;
}

/* 质检项：每行 标签左 + 选项横向滚动右（参考拍机堂） */
.field-row {
    display: flex;
    align-items: center;
    gap: 16rpx;
    padding: 12rpx 0;
}

.field-row + .field-row {
    border-top: 1rpx solid #f2f4f7;
}

.field-row__label {
    flex: 0 0 auto;
    width: 150rpx;
    font-size: 26rpx;
    color: #303133;
    line-height: 1.4;
}

.field-row__control {
    flex: 1 1 0;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: flex-start;
}

/* 选项类字段：标签在上，选项整行换行铺开（不滑动，最稳） */
.field-row--block {
    flex-direction: column;
    align-items: flex-start;
    gap: 12rpx;
}
.field-row--block .field-row__label {
    width: auto;
}
.field-row--block .field-row__control {
    width: 100%;
    flex: none;
}

.option-scroll {
    width: 100%;
    white-space: nowrap;
}

.option-scroll-row {
    display: inline-flex;
    flex-wrap: nowrap;
    gap: 16rpx;
    padding: 2rpx 0;
}

.option-scroll .option-chip {
    flex: 0 0 auto;
    white-space: nowrap;
    font-size: 26rpx;
    padding: 14rpx 28rpx;
}
</style>
