import { computed, reactive, type ComputedRef } from 'vue'

export interface DictOptionItem {
  name: string
  value: string
}

export interface CheckOptionsGroup {
  screen: DictOptionItem[]      // 外屏规格 (recycle_display)
  indisplay: DictOptionItem[]   // 内屏规格 (recycle_indisplay)
  appearance: DictOptionItem[]  // 中框规格 (recycle_appearance)
  function: DictOptionItem[]    // 功能规格 (recycle_function)
  fix: DictOptionItem[]         // 维修规格 (recycle_fix)
}

export interface CheckMetaPayload {
  version: number
  battery?: number
  battery_num?: number
  screen_id?: string
  indisplay_id?: string
  appearance_id?: string
  function_ids: string[]
  fix_ids: string[]
  activation_lock: boolean
  mdm_lock: boolean
  custom_fields?: Record<string, any>
  template_id?: number | string
  template_version?: number | string
}

export interface CheckTemplateField {
  id?: number | string
  field_key: string
  field_name: string
  component: string
  unit?: string
  placeholder?: string
  default_value?: any
  is_show?: number
  sort?: number
  extra_config?: Record<string, any> | string | null
  result_visible?: number
  result_template?: string
  options?: DictOptionItem[]
}

interface DeviceCheckMetaSource {
  check_meta?: CheckMetaPayload | string | null
  check_result?: string
  check_result_seller?: string
  info?: any
}

interface DeviceFormLike {
  check_result: string
  check_result_seller: string
  info: any
  system_version?: string
  warranty_info?: string
  capacity?: string
  color?: string
}

const BUILT_IN_FIELD_KEYS = [
  'battery',
  'battery_num',
  'screen_id',
  'indisplay_id',
  'appearance_id',
  'function_ids',
  'fix_ids',
  'activation_lock',
  'mdm_lock'
] as const

const FORM_FIELD_KEYS = [
  'capacity',
  'color',
  'system_version',
  'warranty_info'
] as const

const RESERVED_FIELD_KEYS = new Set<string>([
  ...BUILT_IN_FIELD_KEYS,
  ...FORM_FIELD_KEYS
])

interface UseCheckMetaOptions {
  dictOptions: ComputedRef<CheckOptionsGroup>
  deviceForm: DeviceFormLike
  fieldConfigByKey?: ComputedRef<Record<string, CheckTemplateField>>
  templateInfo?: ComputedRef<Record<string, any> | null>
  shouldRestoreMeta?: (meta: CheckMetaPayload) => boolean
}

interface TemplateSelections {
  battery: number | undefined
  battery_num: number | undefined
  screenId: string
  indisplayId: string
  appearanceId: string
  functionIds: string[]
  fixIds: string[]
  activationLock: boolean
  mdmLock: boolean
  customFields: Record<string, any>
}

type BuiltInFieldKey = typeof BUILT_IN_FIELD_KEYS[number]

function toOptionalNumber(value: any): number | undefined {
  if (value === '' || value === null || value === undefined) return undefined
  const parsed = Number(value)
  if (Number.isNaN(parsed)) return undefined
  return parsed
}

function toStringValue(value: any): string {
  if (value === null || value === undefined) return ''
  return String(value)
}

export function normalizeInfo(rawInfo: any): Record<string, any> {
  if (!rawInfo) return {}
  if (typeof rawInfo === 'string') {
    try {
      const parsed = JSON.parse(rawInfo)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      console.warn('解析设备 info 失败:', error)
      return {}
    }
  }
  return typeof rawInfo === 'object' ? { ...rawInfo } : {}
}

function isEmptyValue(value: any): boolean {
  if (value === '' || value === null || value === undefined) return true
  if (value === false) return true
  return Array.isArray(value) && value.length === 0
}

function toBooleanValue(value: any): boolean {
  return value === true ||
    value === 1 ||
    value === '1' ||
    value === 'true' ||
    value === '开启' ||
    value === '有锁' ||
    value === 'On'
}

function optionLabels(options: DictOptionItem[] = [], value: any): string[] {
  const values = Array.isArray(value) ? value.map((item) => toStringValue(item)) : [toStringValue(value)]
  const map: Record<string, string> = {}
  options.forEach((item) => {
    map[toStringValue(item.value)] = item.name
  })
  return values.map((item) => map[item] || item).filter(Boolean)
}

function renderResultTemplate(template: string, value: any, labels: string[], field?: CheckTemplateField): string {
  const valueText = Array.isArray(value) ? value.join('、') : toStringValue(value)
  const labelText = labels[0] || valueText
  return template
    .replace(/\{value\}/g, valueText)
    .replace(/\{label\}/g, labelText)
    .replace(/\{labels\}/g, labels.join('、'))
    .replace(/\{name\}/g, field?.field_name || '')
    .replace(/\{unit\}/g, field?.unit || '')
}

export function useCheckMeta({ dictOptions, deviceForm, fieldConfigByKey, templateInfo, shouldRestoreMeta }: UseCheckMetaOptions) {
  const templateSelections = reactive<TemplateSelections>({
    battery: undefined,
    battery_num: undefined,
    screenId: '',
    indisplayId: '',
    appearanceId: '',
    functionIds: [],
    fixIds: [],
    activationLock: false,
    mdmLock: false,
    customFields: {}
  })

  const builtInValueGetters: Record<BuiltInFieldKey, () => any> = {
    battery: () => templateSelections.battery,
    battery_num: () => templateSelections.battery_num,
    screen_id: () => templateSelections.screenId,
    indisplay_id: () => templateSelections.indisplayId,
    appearance_id: () => templateSelections.appearanceId,
    function_ids: () => templateSelections.functionIds,
    fix_ids: () => templateSelections.fixIds,
    activation_lock: () => templateSelections.activationLock,
    mdm_lock: () => templateSelections.mdmLock
  }

  const getFieldValue = (fieldKey: string) => {
    const getter = builtInValueGetters[fieldKey as BuiltInFieldKey]
    if (getter) return getter()
    if (fieldKey === 'capacity') return deviceForm.capacity
    if (fieldKey === 'color') return deviceForm.color
    if (fieldKey === 'system_version') return deviceForm.system_version
    if (fieldKey === 'warranty_info') return deviceForm.warranty_info
    return templateSelections.customFields[fieldKey]
  }

  // ==================== 字典映射 ====================

  const optionNameById = computed(() => {
    const createMap = (options: DictOptionItem[]) => {
      const map: Record<string, string> = {}
      options.forEach((item) => {
        map[toStringValue(item.value)] = item.name
      })
      return map
    }

    return {
      screen: createMap(dictOptions.value.screen),
      indisplay: createMap(dictOptions.value.indisplay),
      appearance: createMap(dictOptions.value.appearance),
      function: createMap(dictOptions.value.function),
      fix: createMap(dictOptions.value.fix)
    }
  })

  const optionIdByName = computed(() => {
    const createMap = (options: DictOptionItem[]) => {
      const map: Record<string, string> = {}
      options.forEach((item) => {
        map[item.name] = toStringValue(item.value)
      })
      return map
    }

    return {
      screen: createMap(dictOptions.value.screen),
      indisplay: createMap(dictOptions.value.indisplay),
      appearance: createMap(dictOptions.value.appearance),
      function: createMap(dictOptions.value.function),
      fix: createMap(dictOptions.value.fix)
    }
  })

  // ==================== 计数 ====================

  const checkedCount = computed(() => {
    const fieldMap = fieldConfigByKey?.value || {}
    return Object.keys(fieldMap).filter(fieldKey => !isEmptyValue(getFieldValue(fieldKey))).length
  })

  // ==================== 构建 / 提交 ====================

  const buildCheckMeta = (): CheckMetaPayload => {
    const fieldMap = fieldConfigByKey?.value || {}
    const normalizedCustomFields = Object.entries(templateSelections.customFields).reduce<Record<string, any>>((fields, [fieldKey, value]) => {
      if (!RESERVED_FIELD_KEYS.has(fieldKey) && !isEmptyValue(value)) {
        fields[fieldKey] = value
      }
      return fields
    }, {})
    const schemaFields: Record<string, any> = {}
    Object.keys(fieldMap).forEach((fieldKey) => {
      if (RESERVED_FIELD_KEYS.has(fieldKey)) return
      const value = getFieldValue(fieldKey)
      if (!isEmptyValue(value)) schemaFields[fieldKey] = value
    })

    return {
      version: 2,
      battery: toOptionalNumber(templateSelections.battery),
      battery_num: toOptionalNumber(templateSelections.battery_num),
      screen_id: templateSelections.screenId || undefined,
      indisplay_id: templateSelections.indisplayId || undefined,
      appearance_id: templateSelections.appearanceId || undefined,
      function_ids: templateSelections.functionIds.map((item) => toStringValue(item)),
      fix_ids: templateSelections.fixIds.map((item) => toStringValue(item)),
      activation_lock: !!templateSelections.activationLock,
      mdm_lock: !!templateSelections.mdmLock,
      custom_fields: {
        ...normalizedCustomFields,
        ...schemaFields
      },
      template_id: templateInfo?.value?.id,
      template_version: templateInfo?.value?.version
    }
  }

  const getSubmitInfo = () => {
    const normalizedInfo = normalizeInfo(deviceForm.info)
    return {
      ...normalizedInfo,
      capacity: deviceForm.capacity || normalizedInfo.capacity || '',
      color: deviceForm.color || normalizedInfo.color || '',
      system_version: deviceForm.system_version || normalizedInfo.system_version || '',
      warranty_info: deviceForm.warranty_info || normalizedInfo.warranty_info || '',
      check_meta: buildCheckMeta()
    }
  }

  // ==================== 结果文本生成 ====================

  const updateCheckResult = () => {
    const checkMeta = buildCheckMeta()
    const results: string[] = []
    const fieldMap = fieldConfigByKey?.value || {}

    const pushResult = (text: string) => {
      const value = text.trim()
      if (value) results.push(value)
    }

    const pushKnownResult = (fieldKey: string, value: any, labels: string[], fallback: string) => {
      const field = fieldMap[fieldKey]
      if (!field || Number(field.result_visible) !== 1) return
      if (isEmptyValue(value)) return
      if (field?.result_template) {
        const text = renderResultTemplate(field.result_template, value, labels, field)
        if (text) pushResult(text)
        return
      }
      if (fallback) pushResult(fallback)
    }

    const screenName = checkMeta.screen_id ? optionNameById.value.screen[checkMeta.screen_id] : ''
    const indisplayName = checkMeta.indisplay_id ? optionNameById.value.indisplay[checkMeta.indisplay_id] : ''
    const appearanceName = checkMeta.appearance_id ? optionNameById.value.appearance[checkMeta.appearance_id] : ''
    const functionNames = checkMeta.function_ids
      .map((id) => optionNameById.value.function[id])
      .filter(Boolean)
    const fixNames = checkMeta.fix_ids
      .map((id) => optionNameById.value.fix[id])
      .filter(Boolean)

    ;(['capacity', 'color', 'system_version', 'warranty_info'] as const).forEach((fieldKey) => {
      const value = deviceForm[fieldKey]
      if (isEmptyValue(value)) return
      pushKnownResult(fieldKey, value, [toStringValue(value)], '')
    })

    if (checkMeta.battery !== undefined) {
      pushKnownResult('battery', checkMeta.battery, [toStringValue(checkMeta.battery)], `电池健康度${checkMeta.battery}%`)
    }
    if (checkMeta.battery_num !== undefined) {
      pushKnownResult('battery_num', checkMeta.battery_num, [toStringValue(checkMeta.battery_num)], `循环${checkMeta.battery_num}次`)
    }
    if (checkMeta.activation_lock) {
      pushKnownResult('activation_lock', true, ['开启'], '激活锁开启')
    }
    if (checkMeta.mdm_lock) {
      pushKnownResult('mdm_lock', true, ['开启'], '监管锁开启')
    }
    if (screenName) {
      pushKnownResult('screen_id', checkMeta.screen_id, [screenName], `外屏${screenName}`)
    }
    if (indisplayName) {
      pushKnownResult('indisplay_id', checkMeta.indisplay_id, [indisplayName], `内屏${indisplayName}`)
    }
    if (appearanceName) {
      pushKnownResult('appearance_id', checkMeta.appearance_id, [appearanceName], `中框${appearanceName}`)
    }
    if (functionNames.length > 0) {
      pushKnownResult('function_ids', checkMeta.function_ids, functionNames, `功能: ${functionNames.join('、')}`)
    }
    if (fixNames.length > 0) {
      pushKnownResult('fix_ids', checkMeta.fix_ids, fixNames, `维修记录: ${fixNames.join('、')}`)
    }

    Object.entries(templateSelections.customFields).forEach(([fieldKey, value]) => {
      if (RESERVED_FIELD_KEYS.has(fieldKey)) return
      if (isEmptyValue(value)) return
      const field = fieldMap[fieldKey]
      if (!field || Number(field.result_visible) !== 1) return
      const labels = optionLabels(field.options || [], value)
      if (field.result_template) {
        const text = renderResultTemplate(field.result_template, value, labels, field)
        if (text) pushResult(text)
        return
      }
      pushResult(`${field.field_name}: ${labels.length ? labels.join('、') : toStringValue(value)}${field.unit || ''}`)
    })

    // 从 info 中读取保修信息，保证不会被质检选项覆盖丢失
    const info = normalizeInfo(deviceForm.info)
    const coverage = info.coverage
    if (coverage) {
      const status = coverage.status || ''
      if (status === 'Out Of Warranty') {
        pushResult('保修: 过保')
      } else if (status === 'Not Activated' || !coverage.date) {
        pushResult('保修: 未激活')
      } else {
        pushResult(`保修: 在保 到期${coverage.date}`)
      }
    }

    deviceForm.check_result_seller = Array.from(new Set(results)).join(';\n')
    deviceForm.info = getSubmitInfo()
  }

  // ==================== 重置 / 清空 ====================

  const resetTemplateSelections = () => {
    templateSelections.battery = undefined
    templateSelections.battery_num = undefined
    templateSelections.screenId = ''
    templateSelections.indisplayId = ''
    templateSelections.appearanceId = ''
    templateSelections.functionIds = []
    templateSelections.fixIds = []
    templateSelections.activationLock = false
    templateSelections.mdmLock = false
    templateSelections.customFields = {}
  }

  const clearAllSelections = () => {
    resetTemplateSelections()
    deviceForm.check_result_seller = ''
    deviceForm.info = getSubmitInfo()
  }

  // ==================== 从元数据恢复 ====================

  const applyCheckMeta = (meta: CheckMetaPayload) => {
    templateSelections.battery = toOptionalNumber(meta.battery)
    templateSelections.battery_num = toOptionalNumber(meta.battery_num)
    templateSelections.screenId = toStringValue(meta.screen_id)
    templateSelections.indisplayId = toStringValue(meta.indisplay_id)
    templateSelections.appearanceId = toStringValue(meta.appearance_id)
    templateSelections.functionIds = (meta.function_ids || [])
      .map((item) => toStringValue(item))
      .filter(Boolean)
    templateSelections.fixIds = (meta.fix_ids || [])
      .map((item) => toStringValue(item))
      .filter(Boolean)
    templateSelections.activationLock = toBooleanValue(meta.activation_lock)
    templateSelections.mdmLock = toBooleanValue(meta.mdm_lock)
    templateSelections.customFields = Object.entries(meta.custom_fields || {}).reduce<Record<string, any>>((fields, [fieldKey, value]) => {
      if (!RESERVED_FIELD_KEYS.has(fieldKey) && !isEmptyValue(value)) {
        fields[fieldKey] = value
      }
      return fields
    }, {})
  }

  const parseCheckMeta = (source: any): CheckMetaPayload | null => {
    if (!source) return null

    let parsed = source
    if (typeof source === 'string') {
      try {
        parsed = JSON.parse(source)
      } catch (error) {
        console.warn('解析 check_meta 失败:', error)
        return null
      }
    }

    if (!parsed || typeof parsed !== 'object') return null

    const functionIds = Array.isArray(parsed.function_ids ?? parsed.functionIds)
      ? (parsed.function_ids ?? parsed.functionIds).map((item: any) => toStringValue(item)).filter(Boolean)
      : []

    const fixIds = Array.isArray(parsed.fix_ids ?? parsed.fixIds)
      ? (parsed.fix_ids ?? parsed.fixIds).map((item: any) => toStringValue(item)).filter(Boolean)
      : []

    return {
      version: Number(parsed.version) || 1,
      battery: toOptionalNumber(parsed.battery),
      battery_num: toOptionalNumber(parsed.battery_num ?? parsed.batteryNum),
      screen_id: toStringValue(parsed.screen_id ?? parsed.screenId) || undefined,
      indisplay_id: toStringValue(parsed.indisplay_id ?? parsed.indisplayId) || undefined,
      appearance_id: toStringValue(parsed.appearance_id ?? parsed.appearanceId) || undefined,
      function_ids: functionIds,
      fix_ids: fixIds,
      activation_lock: toBooleanValue(parsed.activation_lock ?? parsed.activationLock),
      mdm_lock: toBooleanValue(parsed.mdm_lock ?? parsed.mdmLock),
      custom_fields: typeof parsed.custom_fields === 'object' && parsed.custom_fields ? { ...parsed.custom_fields } : {},
      template_id: parsed.template_id,
      template_version: parsed.template_version
    }
  }

  const resolveCheckMeta = (device: DeviceCheckMetaSource): CheckMetaPayload | null => {
    const directMeta = parseCheckMeta(device.check_meta)
    if (directMeta) return directMeta

    const infoMeta = parseCheckMeta(normalizeInfo(device.info).check_meta)
    if (infoMeta) return infoMeta

    return null
  }

  // ==================== 旧文本结果兼容解析 ====================

  const applyLegacyCheckResult = (resultText: string): boolean => {
    if (!resultText) return false
    let matched = false
    resetTemplateSelections()

    const batteryMatch = resultText.match(/电池健康度\s*(\d{1,3})%/)
    if (batteryMatch) {
      templateSelections.battery = toOptionalNumber(batteryMatch[1])
      matched = true
    }

    const batteryNumMatch = resultText.match(/循环\s*(\d+)\s*次/)
    if (batteryNumMatch) {
      templateSelections.battery_num = toOptionalNumber(batteryNumMatch[1])
      matched = true
    }

    if (resultText.includes('激活锁开启')) {
      templateSelections.activationLock = true
      matched = true
    }

    if (resultText.includes('监管锁开启')) {
      templateSelections.mdmLock = true
      matched = true
    }

    // 兼容旧版 "屏幕xxx" 和新版 "外屏xxx" 格式
    const matchedScreenName = Object.keys(optionIdByName.value.screen).find(
      (name) => resultText.includes(`外屏${name}`) || resultText.includes(`屏幕${name}`)
    )
    if (matchedScreenName) {
      templateSelections.screenId = optionIdByName.value.screen[matchedScreenName]
      matched = true
    }

    // 内屏规格
    const matchedIndisplayName = Object.keys(optionIdByName.value.indisplay).find(
      (name) => resultText.includes(`内屏${name}`)
    )
    if (matchedIndisplayName) {
      templateSelections.indisplayId = optionIdByName.value.indisplay[matchedIndisplayName]
      matched = true
    }

    // 兼容旧版 "外观xxx" 和新版 "中框xxx" 格式
    const matchedAppearanceName = Object.keys(optionIdByName.value.appearance).find(
      (name) => resultText.includes(`中框${name}`) || resultText.includes(`外观${name}`)
    )
    if (matchedAppearanceName) {
      templateSelections.appearanceId = optionIdByName.value.appearance[matchedAppearanceName]
      matched = true
    }

    // 功能异常
    const functionIds = Object.entries(optionIdByName.value.function)
      .filter(([name]) => resultText.includes(name))
      .map(([, id]) => id)
    if (functionIds.length) {
      templateSelections.functionIds = functionIds
      matched = true
    }

    // 维修规格
    const fixIds = Object.entries(optionIdByName.value.fix)
      .filter(([name]) => resultText.includes(name))
      .map(([, id]) => id)
    if (fixIds.length) {
      templateSelections.fixIds = fixIds
      matched = true
    }

    return matched
  }

  // ==================== 从设备数据恢复 ====================

  const restoreFromDevice = (device: DeviceCheckMetaSource) => {
    const checkMeta = resolveCheckMeta(device)
    if (checkMeta) {
      if (!shouldRestoreMeta || shouldRestoreMeta(checkMeta)) {
        applyCheckMeta(checkMeta)
        updateCheckResult()
      } else {
        resetTemplateSelections()
        deviceForm.check_result_seller = ''
        deviceForm.info = getSubmitInfo()
      }
      return
    }

    // 旧数据回退：优先从 check_result_seller 解析，再 fallback 到 check_result
    const restoredFromLegacy = applyLegacyCheckResult(
      device.check_result_seller || device.check_result || ''
    )
    if (restoredFromLegacy) {
      updateCheckResult()
      return
    }

    deviceForm.info = getSubmitInfo()
  }

  return {
    templateSelections,
    checkedCount,
    optionNameById,
    getSubmitInfo,
    updateCheckResult,
    clearAllSelections,
    restoreFromDevice
  }
}
