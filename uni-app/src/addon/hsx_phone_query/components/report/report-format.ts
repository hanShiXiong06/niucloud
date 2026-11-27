export type ReportField = {
  key: string
  label: string
  value: any
  type: 'text' | 'image' | 'object'
}

export type ReportModel = {
  title: string
  subtitle: string
  queryCode: string
  displayCode: string
  createdAt: string
  image: string
  statusTags: Array<{ label: string; value: string; tone: string }>
  summary: Array<{ label: string; value: string }>
  fields: ReportField[]
}

const LABELS: Record<string, string> = {
  sn: '序列号',
  imei: 'IMEI',
  imei2: 'IMEI2',
  type: '查询类型',
  model: '设备型号',
  capacity: '容量',
  color: '颜色',
  image: '设备图片',
  manufacturer: '制造商',
  manufacture: '生产信息',
  date: '日期',
  warranty: '保修状态',
  coverage: '保修状态',
  coverage_status: '保修状态',
  coverage_description: '保障情况',
  coverage_date: '保修到期',
  coverage_days_remaining: '剩余保修天数',
  coverage_denied: '拒保记录',
  purchase_date: '激活/购买日期',
  purchase_validated: '有效购买日期',
  apple_care: 'AppleCare+',
  model_number: '型号号码',
  activationlock: '激活锁',
  fmi: '查找我的 iPhone',
  locked: '激活锁状态',
  icloud: 'ID状态',
  simlock: '网络锁',
  carrier: '运营商',
  country: '销售地',
  mdm: '监管锁',
  blacklist: '黑名单',
}

const TITLE_KEYS = ['model', '机型', '型号', 'device_model', 'product', 'product_name']
const CAPACITY_KEYS = ['capacity', '容量', 'storage']
const COLOR_KEYS = ['color', '颜色']
const IMAGE_KEYS = ['image', 'img', 'picture', 'pic', 'product_image']
const HIDDEN_KEYS = ['image', 'img', 'picture', 'pic', 'product_image']

export function buildReportModel(detail: any, config: any = {}): ReportModel {
  const info = normalizeInfo(detail?.info)
  const displayFields = normalizeDisplayFields(detail?.display_info)
  const displaySummary = Array.isArray(detail?.display_summary) ? detail.display_summary : []
  const displayStatusTags = Array.isArray(detail?.display_status_tags) ? detail.display_status_tags : []
  const title = detail?.display_title || pickFirst(info, TITLE_KEYS) || detail?.type_name || '查询报告'
  const capacity = pickFirstFromSummary(displaySummary, '容量') || pickFirst(info, CAPACITY_KEYS)
  const color = pickFirstFromSummary(displaySummary, '颜色') || pickFirst(info, COLOR_KEYS)
  const image = detail?.display_image || pickFirst(info, IMAGE_KEYS)
  const maskCode = Number(config?.detail?.mask_query_code || 0) === 1
  const queryCode = String(detail?.sn || info.sn || info.imei || '')

  const summary = (displaySummary.length ? displaySummary : [
    { label: '查询项目', value: detail?.type_name || '' },
    { label: '容量', value: capacity },
    { label: '颜色', value: color },
    { label: '查询时间', value: detail?.create_time || '' },
  ]).filter(item => hasValue(item.value))

  const fields = (displayFields.length ? displayFields : flattenInfo(info, config)).filter(field => {
    if (!Number(config?.detail?.show_device_image || 1) && field.type === 'image') return false
    if (!Number(config?.detail?.show_empty_fields || 0) && !hasValue(field.display_value ?? field.value)) return false
    return !['model', '机型', '型号', 'capacity', '容量', 'color', '颜色'].includes(field.key)
  })

  return {
    title: String(title),
    subtitle: detail?.display_subtitle || [capacity, color].filter(Boolean).join(' / ') || detail?.type_name || '',
    queryCode,
    displayCode: maskCode ? maskQueryCode(queryCode) : queryCode,
    createdAt: detail?.create_time || '',
    image: image || '',
    statusTags: displayStatusTags.length ? displayStatusTags : buildStatusTags(info),
    summary,
    fields,
  }
}

export function formatLabel(key: string): string {
  return LABELS[key] || LABELS[key.toLowerCase()] || key
}

export function hasValue(value: any): boolean {
  if (value === null || value === undefined) return false
  if (typeof value === 'string') return value.trim() !== ''
  if (Array.isArray(value)) return value.length > 0
  if (typeof value === 'object') return Object.keys(value).length > 0
  return true
}

export function isImageUrl(value: any): boolean {
  return typeof value === 'string' && /^(https?:\/\/|\/static\/|\/upload\/|\/addon\/)/i.test(value)
}

export function stringifyValue(value: any): string {
  if (value === null || value === undefined || value === '') return '--'
  if (typeof value === 'object') {
    return Object.entries(value)
      .filter(([, item]) => hasValue(item))
      .map(([key, item]) => `${formatLabel(key)}：${stringifyValue(item)}`)
      .join('，') || '--'
  }
  return String(value)
}

export function displayFieldValue(field: any): string {
  return field?.display_value || stringifyValue(field?.value)
}

export function maskQueryCode(value: string): string {
  if (!value || value.length <= 6) return value
  return `${value.slice(0, 3)}****${value.slice(-4)}`
}

function normalizeInfo(info: any): Record<string, any> {
  if (!info) return {}
  if (typeof info === 'object') return info
  try {
    return JSON.parse(info)
  } catch (e) {
    return {}
  }
}

function pickFirst(info: Record<string, any>, keys: string[]): string {
  for (const key of keys) {
    if (hasValue(info[key])) return String(info[key])
  }
  return ''
}

function pickFirstFromSummary(summary: any[], label: string): string {
  const item = summary.find(row => row?.label === label)
  return item?.value ? String(item.value) : ''
}

function normalizeDisplayFields(fields: any[]): ReportField[] {
  if (!Array.isArray(fields)) return []
  return fields.map((field: any) => ({
    key: String(field.key || field.label || ''),
    label: String(field.label || field.key || ''),
    value: field.value,
    display_value: field.display_value,
    type: field.type || (isImageUrl(field.value) ? 'image' : 'text'),
  } as any))
}

function flattenInfo(info: Record<string, any>, config: any): ReportField[] {
  const showRaw = Number(config?.detail?.show_raw_result || 0) === 1
  const fields: ReportField[] = []
  Object.entries(info).forEach(([key, value]) => {
    if (!showRaw && HIDDEN_KEYS.includes(key)) {
      fields.push({ key, label: formatLabel(key), value, type: 'image' })
      return
    }
    fields.push({
      key,
      label: formatLabel(key),
      value,
      type: isImageUrl(value) ? 'image' : typeof value === 'object' ? 'object' : 'text',
    })
  })
  return fields
}

function buildStatusTags(info: Record<string, any>) {
  const candidates = [
    ['warranty', '保修', info.warranty || info.coverage || info['保修到期']],
    ['activation', '激活锁', info.activationlock || info['激活锁']],
    ['mdm', '监管锁', info.mdm || info['监管锁']],
    ['simlock', '网络锁', info.simlock || info['网络锁']],
    ['blacklist', '黑名单', info.blacklist || info['黑名单']],
  ]

  return candidates
    .filter(([, , value]) => hasValue(value))
    .map(([key, label, value]) => ({
      label: String(label),
      value: stringifyValue(value),
      tone: resolveTone(String(key), stringifyValue(value)),
    }))
}

function resolveTone(key: string, value: string): string {
  const text = value.toLowerCase()
  if (text.includes('off') || text.includes('关闭') || text.includes('正常') || text.includes('clean')) return 'success'
  if (text.includes('on') || text.includes('开启') || text.includes('lost') || text.includes('black')) return 'danger'
  if (key === 'warranty' && (text.includes('过期') || text.includes('expired'))) return 'warning'
  return 'neutral'
}
