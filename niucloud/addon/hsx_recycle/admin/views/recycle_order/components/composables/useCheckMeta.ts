import { computed, reactive, type ComputedRef } from 'vue'

export interface DictOptionItem {
  name: string
  value: string
  label?: string
  is_default?: number
  extra_config?: Record<string, any> | string | null
}

export interface CheckResultStyle {
  text_color?: string
  background_color?: string
  border_color?: string
}

export interface CheckResultItemMeta {
  field_key: string
  field_name: string
  component: string
  value: any
  values: string[]
  labels: string[]
  option_items?: CheckResultOptionItemMeta[]
  text: string
  style?: CheckResultStyle
  option_styles?: Record<string, CheckResultStyle>
}

export interface CheckResultOptionItemMeta {
  value: string
  label: string
  style?: CheckResultStyle
}

export interface CheckOptionsGroup {
  screen: DictOptionItem[]      // 外屏规格
  indisplay: DictOptionItem[]   // 内屏规格
  appearance: DictOptionItem[]  // 中框规格
  function: DictOptionItem[]    // 功能规格
  fix: DictOptionItem[]         // 维修规格
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
  result_items?: CheckResultItemMeta[]
  template_id?: number | string
  template_version?: number | string
}

export interface CheckTemplateField {
  id?: number | string
  field_key: string
  field_name: string
  component: string
  selection_mode?: string
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
    map[toStringValue(item.value)] = item.name || item.label || toStringValue(item.value)
  })
  return values.map((item) => map[item] || item).filter(Boolean)
}

function normalizeExtraConfig(config: any): Record<string, any> {
  if (!config) return {}
  if (typeof config === 'string') {
    try {
      const parsed = JSON.parse(config)
      return parsed && typeof parsed === 'object' ? parsed : {}
    } catch (error) {
      return {}
    }
  }
  return typeof config === 'object' ? { ...config } : {}
}

function sanitizeStyle(style: any): CheckResultStyle | undefined {
  const normalized = normalizeExtraConfig(style)
  const result: CheckResultStyle = {
    text_color: normalized.text_color || '',
    background_color: normalized.background_color || normalized.bg_color || '',
    border_color: normalized.border_color || ''
  }
  if (!result.text_color && !result.background_color && !result.border_color) return undefined
  return result
}

function optionStyle(option?: DictOptionItem): CheckResultStyle | undefined {
  if (!option) return undefined
  const extra = normalizeExtraConfig(option.extra_config)
  return sanitizeStyle(extra.result_style || extra.option_style || extra)
}

function optionStyleMap(options: DictOptionItem[] = [], value: any): Record<string, CheckResultStyle> {
  const values = Array.isArray(value) ? value.map(item => toStringValue(item)) : [toStringValue(value)]
  const optionByValue = new Map(options.map(option => [toStringValue(option.value), option]))
  return values.reduce<Record<string, CheckResultStyle>>((map, item) => {
    const style = optionStyle(optionByValue.get(item))
    if (style) map[item] = style
    return map
  }, {})
}

function buildOptionItems(options: DictOptionItem[] = [], value: any, labels: string[]): CheckResultOptionItemMeta[] {
  const values = Array.isArray(value) ? value.map(item => toStringValue(item)).filter(Boolean) : [toStringValue(value)].filter(Boolean)
  const optionByValue = new Map(options.map(option => [toStringValue(option.value), option]))
  return values.map((item, index) => {
    const option = optionByValue.get(item)
    const label = labels[index] || option?.name || option?.label || item
    const style = optionStyle(option)
    return {
      value: item,
      label,
      ...(style ? { style } : {})
    }
  })
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

  const getFieldOptions = (fieldKey: string): DictOptionItem[] => {
    const field = fieldConfigByKey?.value?.[fieldKey]
    return field?.options || []
  }

  const buildResultItem = (fieldKey: string, value: any, labels: string[], text: string): CheckResultItemMeta | null => {
    const field = fieldConfigByKey?.value?.[fieldKey]
    if (!field || isEmptyValue(value) || !text.trim()) return null
    const options = getFieldOptions(fieldKey)
    const optionStyles = optionStyleMap(options, value)
    const values = Array.isArray(value) ? value.map(item => toStringValue(item)).filter(Boolean) : [toStringValue(value)].filter(Boolean)
    const optionItems = buildOptionItems(options, value, labels)
    return {
      field_key: fieldKey,
      field_name: field.field_name,
      component: field.component,
      value,
      values,
      labels,
      option_items: optionItems,
      text: text.trim(),
      style: optionItems.length === 1 ? optionItems[0].style : undefined,
      option_styles: optionStyles
    }
  }

  const buildResultItems = (): CheckResultItemMeta[] => {
    const fieldMap = fieldConfigByKey?.value || {}
    const items: CheckResultItemMeta[] = []
    const appendItem = (fieldKey: string, value: any, labels: string[], fallback: string) => {
      const field = fieldMap[fieldKey]
      if (!field || Number(field.result_visible) !== 1 || isEmptyValue(value)) return
      const text = field.result_template
        ? renderResultTemplate(field.result_template, value, labels, field)
        : fallback
      const item = buildResultItem(fieldKey, value, labels, text)
      if (item) items.push(item)
    }

    ;(['capacity', 'color', 'system_version', 'warranty_info'] as const).forEach((fieldKey) => {
      const value = deviceForm[fieldKey]
      if (!isEmptyValue(value)) appendItem(fieldKey, value, [toStringValue(value)], '')
    })

    if (templateSelections.battery !== undefined) appendItem('battery', templateSelections.battery, [toStringValue(templateSelections.battery)], `电池健康度${templateSelections.battery}%`)
    if (templateSelections.battery_num !== undefined) appendItem('battery_num', templateSelections.battery_num, [toStringValue(templateSelections.battery_num)], `循环${templateSelections.battery_num}次`)
    if (templateSelections.activationLock) appendItem('activation_lock', true, ['开启'], '激活锁开启')
    if (templateSelections.mdmLock) appendItem('mdm_lock', true, ['开启'], '监管锁开启')

    const screenName = templateSelections.screenId ? optionLabels(getFieldOptions('screen_id'), templateSelections.screenId)[0] : ''
    const indisplayName = templateSelections.indisplayId ? optionLabels(getFieldOptions('indisplay_id'), templateSelections.indisplayId)[0] : ''
    const appearanceName = templateSelections.appearanceId ? optionLabels(getFieldOptions('appearance_id'), templateSelections.appearanceId)[0] : ''
    const functionNames = optionLabels(getFieldOptions('function_ids'), templateSelections.functionIds)
    const fixNames = optionLabels(getFieldOptions('fix_ids'), templateSelections.fixIds)

    if (screenName) appendItem('screen_id', templateSelections.screenId, [screenName], `外屏${screenName}`)
    if (indisplayName) appendItem('indisplay_id', templateSelections.indisplayId, [indisplayName], `内屏${indisplayName}`)
    if (appearanceName) appendItem('appearance_id', templateSelections.appearanceId, [appearanceName], `中框${appearanceName}`)
    if (functionNames.length) appendItem('function_ids', templateSelections.functionIds, functionNames, `功能: ${functionNames.join('、')}`)
    if (fixNames.length) appendItem('fix_ids', templateSelections.fixIds, fixNames, `维修记录: ${fixNames.join('、')}`)

    Object.entries(templateSelections.customFields).forEach(([fieldKey, value]) => {
      if (RESERVED_FIELD_KEYS.has(fieldKey) || isEmptyValue(value)) return
      const field = fieldMap[fieldKey]
      if (!field || Number(field.result_visible) !== 1) return
      const labels = optionLabels(field.options || [], value)
      const fallback = `${field.field_name}: ${labels.length ? labels.join('、') : toStringValue(value)}${field.unit || ''}`
      appendItem(fieldKey, value, labels, fallback)
    })

    return items
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
      result_items: buildResultItems(),
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

  // ==================== 应用模板默认值 ====================

  // 模板字段默认值:选项 is_default 优先,其次字段 default_value
  const resolveFieldDefault = (field: CheckTemplateField): any => {
    const options = field.options || []
    const isMultiple = field.component === 'checkbox' || field.selection_mode === 'multiple'
    if (options.length) {
      const defaults = options
        .filter((option) => Number(option.is_default) === 1)
        .map((option) => toStringValue(option.value))
      if (defaults.length) return isMultiple ? defaults : defaults[0]
    }
    if (!isEmptyValue(field.default_value)) {
      const value = toStringValue(field.default_value)
      return isMultiple ? [value] : value
    }
    return undefined
  }

  const applyTemplateDefaults = () => {
    const fieldMap = fieldConfigByKey?.value || {}
    let applied = false
    Object.entries(fieldMap).forEach(([fieldKey, field]) => {
      if (FORM_FIELD_KEYS.includes(fieldKey as any)) return
      const value = resolveFieldDefault(field)
      if (value === undefined || isEmptyValue(value)) return
      applied = true
      switch (fieldKey) {
        case 'battery': templateSelections.battery = toOptionalNumber(value); break
        case 'battery_num': templateSelections.battery_num = toOptionalNumber(value); break
        case 'screen_id': templateSelections.screenId = toStringValue(value); break
        case 'indisplay_id': templateSelections.indisplayId = toStringValue(value); break
        case 'appearance_id': templateSelections.appearanceId = toStringValue(value); break
        case 'function_ids': templateSelections.functionIds = Array.isArray(value) ? value : [toStringValue(value)]; break
        case 'fix_ids': templateSelections.fixIds = Array.isArray(value) ? value : [toStringValue(value)]; break
        case 'activation_lock': templateSelections.activationLock = toBooleanValue(value); break
        case 'mdm_lock': templateSelections.mdmLock = toBooleanValue(value); break
        default: templateSelections.customFields[fieldKey] = value
      }
    })
    if (applied) updateCheckResult()
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

    // 空数组 [] 也是 object 且 truthy(PHP 空数组序列化结果),不能当成有效 meta,否则会顶掉模板默认值
    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) return null

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
      result_items: Array.isArray(parsed.result_items) ? parsed.result_items : [],
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

  // meta 是否真的填过东西:全空的占位 meta(如代下单工单)不应顶掉模板默认值
  const metaHasSelections = (meta: CheckMetaPayload): boolean => {
    if (meta.battery !== undefined || meta.battery_num !== undefined) return true
    if (meta.screen_id || meta.indisplay_id || meta.appearance_id) return true
    if ((meta.function_ids || []).length || (meta.fix_ids || []).length) return true
    if (meta.activation_lock || meta.mdm_lock) return true
    return Object.values(meta.custom_fields || {}).some((value) => !isEmptyValue(value))
  }

  const restoreFromDevice = (device: DeviceCheckMetaSource) => {
    const checkMeta = resolveCheckMeta(device)
    // 仅当 meta 属于当前模板且确有勾选时才恢复;否则(空占位 / 模板不匹配)落到默认值预填
    if (checkMeta && (!shouldRestoreMeta || shouldRestoreMeta(checkMeta)) && metaHasSelections(checkMeta)) {
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

    // 没有任何历史质检数据时(含代下单空占位 meta),按模板默认选项预填
    resetTemplateSelections()
    applyTemplateDefaults()
    deviceForm.info = getSubmitInfo()
  }

  return {
    templateSelections,
    checkedCount,
    optionNameById,
    getSubmitInfo,
    updateCheckResult,
    clearAllSelections,
    applyTemplateDefaults,
    restoreFromDevice
  }
}
