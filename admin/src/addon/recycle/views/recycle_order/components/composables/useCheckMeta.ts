import { computed, reactive, type ComputedRef } from 'vue'

export interface DictOptionItem {
  name: string
  value: string
}

export interface CheckOptionsGroup {
  screen: DictOptionItem[]
  appearance: DictOptionItem[]
  function: DictOptionItem[]
}

export interface CheckMetaPayload {
  version: number
  battery?: number
  battery_num?: number
  screen_id?: string
  appearance_id?: string
  function_ids: string[]
  activation_lock: boolean
  mdm_lock: boolean
}

interface DeviceCheckMetaSource {
  check_meta?: CheckMetaPayload | string | null
  check_result?: string
  info?: any
}

interface DeviceFormLike {
  check_result: string
  info: any
}

interface UseCheckMetaOptions {
  dictOptions: ComputedRef<CheckOptionsGroup>
  deviceForm: DeviceFormLike
}

interface TemplateSelections {
  battery: number | undefined
  battery_num: number | undefined
  screenId: string
  appearanceId: string
  functionIds: string[]
  activationLock: boolean
  mdmLock: boolean
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

export function useCheckMeta({ dictOptions, deviceForm }: UseCheckMetaOptions) {
  const templateSelections = reactive<TemplateSelections>({
    battery: undefined,
    battery_num: undefined,
    screenId: '',
    appearanceId: '',
    functionIds: [],
    activationLock: false,
    mdmLock: false
  })

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
      appearance: createMap(dictOptions.value.appearance),
      function: createMap(dictOptions.value.function)
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
      appearance: createMap(dictOptions.value.appearance),
      function: createMap(dictOptions.value.function)
    }
  })

  const checkedCount = computed(() => {
    let count = 0
    if (templateSelections.battery !== undefined) count++
    if (templateSelections.battery_num !== undefined) count++
    if (templateSelections.screenId) count++
    if (templateSelections.appearanceId) count++
    count += templateSelections.functionIds.length
    return count
  })

  const buildCheckMeta = (): CheckMetaPayload => {
    return {
      version: 1,
      battery: toOptionalNumber(templateSelections.battery),
      battery_num: toOptionalNumber(templateSelections.battery_num),
      screen_id: templateSelections.screenId || undefined,
      appearance_id: templateSelections.appearanceId || undefined,
      function_ids: templateSelections.functionIds.map((item) => toStringValue(item)),
      activation_lock: !!templateSelections.activationLock,
      mdm_lock: !!templateSelections.mdmLock
    }
  }

  const getSubmitInfo = () => {
    const normalizedInfo = normalizeInfo(deviceForm.info)
    return {
      ...normalizedInfo,
      check_meta: buildCheckMeta()
    }
  }

  const updateCheckResult = () => {
    const checkMeta = buildCheckMeta()
    const results: string[] = []

    const screenName = checkMeta.screen_id ? optionNameById.value.screen[checkMeta.screen_id] : ''
    const appearanceName = checkMeta.appearance_id ? optionNameById.value.appearance[checkMeta.appearance_id] : ''
    const functionNames = checkMeta.function_ids
      .map((id) => optionNameById.value.function[id])
      .filter(Boolean)

    if (checkMeta.battery !== undefined) {
      results.push(`电池健康度${checkMeta.battery}%`)
    }
    if (checkMeta.battery_num !== undefined) {
      results.push(`循环${checkMeta.battery_num}次`)
    }
    if (checkMeta.activation_lock) {
      results.push('激活锁开启')
    }
    if (checkMeta.mdm_lock) {
      results.push('监管锁开启')
    }
    if (screenName) {
      results.push(`屏幕${screenName}`)
    }
    if (appearanceName) {
      results.push(`外观${appearanceName}`)
    }
    if (functionNames.length > 0) {
      results.push(`功能异常: ${functionNames.join('、')}`)
    }

    deviceForm.check_result = results.join('; ')
    deviceForm.info = getSubmitInfo()
  }

  const resetTemplateSelections = () => {
    templateSelections.battery = undefined
    templateSelections.battery_num = undefined
    templateSelections.screenId = ''
    templateSelections.appearanceId = ''
    templateSelections.functionIds = []
    templateSelections.activationLock = false
    templateSelections.mdmLock = false
  }

  const clearAllSelections = () => {
    resetTemplateSelections()
    deviceForm.check_result = ''
    deviceForm.info = getSubmitInfo()
  }

  const applyCheckMeta = (meta: CheckMetaPayload) => {
    templateSelections.battery = toOptionalNumber(meta.battery)
    templateSelections.battery_num = toOptionalNumber(meta.battery_num)
    templateSelections.screenId = toStringValue(meta.screen_id)
    templateSelections.appearanceId = toStringValue(meta.appearance_id)
    templateSelections.functionIds = (meta.function_ids || [])
      .map((item) => toStringValue(item))
      .filter(Boolean)
    templateSelections.activationLock = !!meta.activation_lock
    templateSelections.mdmLock = !!meta.mdm_lock
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

    return {
      version: Number(parsed.version) || 1,
      battery: toOptionalNumber(parsed.battery),
      battery_num: toOptionalNumber(parsed.battery_num ?? parsed.batteryNum),
      screen_id: toStringValue(parsed.screen_id ?? parsed.screenId) || undefined,
      appearance_id: toStringValue(parsed.appearance_id ?? parsed.appearanceId) || undefined,
      function_ids: functionIds,
      activation_lock: !!(parsed.activation_lock ?? parsed.activationLock),
      mdm_lock: !!(parsed.mdm_lock ?? parsed.mdmLock)
    }
  }

  const resolveCheckMeta = (device: DeviceCheckMetaSource): CheckMetaPayload | null => {
    const directMeta = parseCheckMeta(device.check_meta)
    if (directMeta) return directMeta

    const infoMeta = parseCheckMeta(normalizeInfo(device.info).check_meta)
    if (infoMeta) return infoMeta

    return null
  }

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

    const matchedScreenName = Object.keys(optionIdByName.value.screen).find((name) => resultText.includes(`屏幕${name}`))
    if (matchedScreenName) {
      templateSelections.screenId = optionIdByName.value.screen[matchedScreenName]
      matched = true
    }

    const matchedAppearanceName = Object.keys(optionIdByName.value.appearance).find((name) => resultText.includes(`外观${name}`))
    if (matchedAppearanceName) {
      templateSelections.appearanceId = optionIdByName.value.appearance[matchedAppearanceName]
      matched = true
    }

    const functionIds = Object.entries(optionIdByName.value.function)
      .filter(([name]) => resultText.includes(name))
      .map(([, id]) => id)
    if (functionIds.length) {
      templateSelections.functionIds = functionIds
      matched = true
    }

    return matched
  }

  const restoreFromDevice = (device: DeviceCheckMetaSource) => {
    const checkMeta = resolveCheckMeta(device)
    if (checkMeta) {
      applyCheckMeta(checkMeta)
      updateCheckResult()
      return
    }

    const restoredFromLegacy = applyLegacyCheckResult(device.check_result || '')
    if (restoredFromLegacy) {
      updateCheckResult()
      return
    }

    deviceForm.info = getSubmitInfo()
  }

  const selectScreenOption = (option: string | number) => {
    const optionId = toStringValue(option)
    templateSelections.screenId = templateSelections.screenId === optionId ? '' : optionId
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

  const fillCommonResult = () => {
    templateSelections.battery = 85
    const preferredScreenId = optionIdByName.value.screen['完好'] || toStringValue(dictOptions.value.screen[0]?.value)
    const preferredAppearanceId = optionIdByName.value.appearance['轻微磨损'] || toStringValue(dictOptions.value.appearance[0]?.value)
    templateSelections.screenId = preferredScreenId
    templateSelections.appearanceId = preferredAppearanceId
    updateCheckResult()
  }

  return {
    templateSelections,
    checkedCount,
    optionNameById,
    getSubmitInfo,
    updateCheckResult,
    clearAllSelections,
    fillCommonResult,
    restoreFromDevice,
    selectScreenOption,
    selectAppearanceOption,
    toggleFunctionOption
  }
}
