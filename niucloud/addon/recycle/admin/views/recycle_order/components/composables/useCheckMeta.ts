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

interface UseCheckMetaOptions {
  dictOptions: ComputedRef<CheckOptionsGroup>
  deviceForm: DeviceFormLike
  fieldConfigByKey?: ComputedRef<Record<string, CheckTemplateField>>
  templateInfo?: ComputedRef<Record<string, any> | null>
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
  return Array.isArray(value) && value.length === 0
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

export function useCheckMeta({ dictOptions, deviceForm, fieldConfigByKey, templateInfo }: UseCheckMetaOptions) {
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
    let count = 0
    if (templateSelections.battery !== undefined) count++
    if (templateSelections.battery_num !== undefined) count++
    if (templateSelections.screenId) count++
    if (templateSelections.indisplayId) count++
    if (templateSelections.appearanceId) count++
    count += templateSelections.functionIds.length
    count += templateSelections.fixIds.length
    Object.values(templateSelections.customFields).forEach((value) => {
      if (!isEmptyValue(value)) count++
    })
    return count
  })

  // ==================== 构建 / 提交 ====================

  const buildCheckMeta = (): CheckMetaPayload => {
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
      custom_fields: { ...templateSelections.customFields },
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

    const pushKnownResult = (fieldKey: string, value: any, labels: string[], fallback: string) => {
      const field = fieldMap[fieldKey]
      if (field && Number(field.result_visible) !== 1) return
      if (field?.result_template) {
        const text = renderResultTemplate(field.result_template, value, labels, field)
        if (text) results.push(text)
        return
      }
      if (fallback) results.push(fallback)
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
      if (isEmptyValue(value)) return
      const field = fieldMap[fieldKey]
      if (!field || Number(field.result_visible) !== 1) return
      const labels = optionLabels(field.options || [], value)
      if (field.result_template) {
        const text = renderResultTemplate(field.result_template, value, labels, field)
        if (text) results.push(text)
      return
      }
      results.push(`${field.field_name}: ${labels.length ? labels.join('、') : toStringValue(value)}${field.unit || ''}`)
    })

    // 从 info 中读取保修信息，保证不会被质检选项覆盖丢失
    const info = normalizeInfo(deviceForm.info)
    const coverage = info.coverage
    if (coverage) {
      const status = coverage.status || ''
      if (status === 'Out Of Warranty') {
        results.push('保修: 过保')
      } else if (status === 'Not Activated' || !coverage.date) {
        results.push('保修: 未激活')
      } else {
        results.push(`保修: 在保 到期${coverage.date}`)
      }
    }

    deviceForm.check_result_seller = results.join(';\n')
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
    templateSelections.activationLock = !!meta.activation_lock
    templateSelections.mdmLock = !!meta.mdm_lock
    templateSelections.customFields = { ...(meta.custom_fields || {}) }
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
      activation_lock: !!(parsed.activation_lock ?? parsed.activationLock),
      mdm_lock: !!(parsed.mdm_lock ?? parsed.mdmLock),
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
      applyCheckMeta(checkMeta)
      updateCheckResult()
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

  // ==================== 单选/多选操作 ====================

  const selectScreenOption = (option: string | number) => {
    const optionId = toStringValue(option)
    templateSelections.screenId = templateSelections.screenId === optionId ? '' : optionId
    updateCheckResult()
  }

  const selectIndisplayOption = (option: string | number) => {
    const optionId = toStringValue(option)
    templateSelections.indisplayId = templateSelections.indisplayId === optionId ? '' : optionId
    updateCheckResult()
  }

  const selectAppearanceOption = (option: string | number) => {
    const optionId = toStringValue(option)
    templateSelections.appearanceId = templateSelections.appearanceId === optionId ? '' : optionId
    updateCheckResult()
  }

  const toggleFunctionOption = (option: string | number) => {
    const optionId = toStringValue(option)
    const index = templateSelections.functionIds.indexOf(optionId)
    if (index > -1) {
      templateSelections.functionIds.splice(index, 1)
    } else {
      templateSelections.functionIds.push(optionId)
    }
    updateCheckResult()
  }

  const toggleFixOption = (option: string | number) => {
    const optionId = toStringValue(option)
    const index = templateSelections.fixIds.indexOf(optionId)
    if (index > -1) {
      templateSelections.fixIds.splice(index, 1)
    } else {
      templateSelections.fixIds.push(optionId)
    }
    updateCheckResult()
  }

  const setCustomFieldValue = (fieldKey: string, value: any) => {
    templateSelections.customFields[fieldKey] = value
    updateCheckResult()
  }

  return {
    templateSelections,
    checkedCount,
    optionNameById,
    setCustomFieldValue,
    getSubmitInfo,
    updateCheckResult,
    clearAllSelections,
    restoreFromDevice,
    selectScreenOption,
    selectIndisplayOption,
    selectAppearanceOption,
    toggleFunctionOption,
    toggleFixOption
  }
}
