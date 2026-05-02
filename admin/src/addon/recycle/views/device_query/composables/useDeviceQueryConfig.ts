import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  addDeviceQueryConfig,
  deleteDeviceQueryConfig,
  editDeviceQueryConfig,
  getDeviceQueryChannelBalance,
  getDeviceQueryConfigList,
  saveDeviceQueryConfigCenter,
  testDeviceQueryConnection,
  updateDeviceQueryConfigStatus
} from '@/addon/recycle/api/device_query_config'

export const providerOptions = [
  { label: '3023 路径接口', value: 'path_query' },
  { label: '爱查服务ID接口', value: 'service_id_query' }
]

export const endpointTypeOptions = [
  { label: '路径', value: 'path' },
  { label: '服务ID', value: 'service_id' }
]

export const resultHandlerOptions = [
  { label: '保修信息', value: 'coverage' },
  { label: '激活锁', value: 'activationlock' },
  { label: '监管锁', value: 'mdm' },
  { label: '通用展示', value: 'generic' }
]

const defaultBaseConfig = () => ({
  enabled: 1,
  cache_enabled: 1,
  default_cache_ttl: 2592000,
  default_channel_key: '3023_main'
})

const defaultServiceForm = () => ({
  code: '',
  name: '',
  category: 'other',
  query_type: 'imei',
  cost_price: 0,
  cache_ttl: 0,
  sort: 0,
  enabled: 1,
  show_in_check: 0,
  result_handler: 'generic',
  create_mapping: 1,
  mapping_channel_key: '',
  mapping_endpoint_type: 'path',
  mapping_endpoint_value: '',
  mapping_query_param: 'sn',
  _editing: false
})

const defaultChannelForm = () => ({
  key: '',
  name: '',
  provider: 'path_query',
  enabled: 1,
  priority: 100,
  base_url: '',
  method: 'GET',
  token: '',
  auth_type: 'header',
  auth_key: 'key',
  service_id_key: 'key',
  timeout: 300,
  connect_timeout: 10,
  balance_warning: 20,
  _editing: false
})

const defaultMappingForm = () => ({
  service_code: '',
  channel_key: '',
  enabled: 1,
  endpoint_type: 'path',
  endpoint_value: '',
  query_param: 'imei',
  cost_price: 0,
  retry_on: [410, 502, 503],
  switch_on_404: 0,
  switch_on_no_data: 0,
  _index: -1,
  _editing: false
})

const normalizeRetryOn = (value: any) => {
  if (Array.isArray(value)) return value
  if (typeof value === 'string') {
    return value.split(',').map(item => Number(item.trim())).filter(Boolean)
  }
  return [410, 502, 503]
}

const unwrapResponseData = (res: any) => {
  if (res?.data?.list || res?.data?.config) return res.data
  if (res?.data?.data?.list || res?.data?.data?.config) return res.data.data
  return res?.data || res || {}
}

const categoryAlias: Record<string, string> = {
  苹果: 'apple',
  华为: 'huawei',
  荣耀: 'honor',
  小米: 'xiaomi',
  OPPO: 'oppo',
  vivo: 'vivo',
  三星: 'samsung',
  真我: 'realme',
  努比亚: 'nubia',
  中兴: 'zte',
  IMEI: 'imei',
  条码: 'item',
  IP: 'ip',
  号码: 'phone'
}

const queryTypeAlias: Record<string, string> = {
  phone: 'phone',
  ip: 'ip',
  barcode: 'barcode'
}

const getCategoryByName = (name: string) => {
  const hit = Object.keys(categoryAlias).find(keyword => name.includes(keyword))
  return hit ? categoryAlias[hit] : 'other'
}

const getResultHandlerByName = (name: string) => {
  if (name.includes('保修') || name.toLowerCase().includes('coverage')) return 'coverage'
  if (name.includes('激活锁') || name.toLowerCase().includes('activationlock')) return 'activationlock'
  if (name.includes('监管锁') || name.toLowerCase().includes('mdm')) return 'mdm'
  return 'generic'
}

const getQueryType = (name: string, endpointValue: string) => {
  const source = `${name} ${endpointValue}`.toLowerCase()
  const queryType = Object.keys(queryTypeAlias).find(keyword => source.includes(keyword))
  if (queryType) return queryTypeAlias[queryType]
  if (source.includes('/ip/') || source.includes('?ip=')) return 'ip'
  if (source.includes('/phone/') || source.includes('?phone=')) return 'phone'
  if (source.includes('/item/barcode')) return 'barcode'
  return 'imei'
}

const getQueryParam = (queryType: string) => {
  if (queryType === 'phone') return 'phone'
  if (queryType === 'ip') return 'ip'
  if (queryType === 'barcode') return 'barcode'
  return 'sn'
}

const getEndpointTypeByChannel = (channel: any) => {
  return channel?.provider === 'service_id_query' ? 'service_id' : 'path'
}

const normalizeCode = (name: string, endpointValue: string) => {
  const endpointCode = endpointValue
    .replace(/^https?:\/\/[^/]+/i, '')
    .replace(/\?.*$/, '')
    .replace(/^\/+/, '')
    .replace(/[^a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()
  if (endpointCode) return endpointCode

  return name
    .replace(/[（）()]/g, '_')
    .replace(/[^\u4e00-\u9fa5a-zA-Z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase() || `service_${Date.now()}`
}

const getServiceIdCode = (name: string, serviceId: string) => {
  const category = getCategoryByName(name)
  const handler = getResultHandlerByName(name)
  const suffixes = [
    name.includes('容量') ? 'capacity' : '',
    name.includes('颜色') ? 'color' : '',
    name.includes('预激活') ? 'activation' : '',
    name.includes('备用') ? 'backup' : '',
    name.includes('极速') ? 'essentials' : '',
    name.includes('旗舰') ? 'ultimate' : ''
  ].filter(Boolean)

  return [category, handler, ...suffixes, serviceId]
    .filter(Boolean)
    .join('_')
    .replace(/[^a-zA-Z0-9_]+/g, '_')
    .replace(/^_+|_+$/g, '')
    .toLowerCase()
}

const stripName = (value: string) => {
  return value
    .replace(/\s+https?:\/\/\S+/i, '')
    .replace(/\s+\/[a-zA-Z0-9][^\s，,]*$/, '')
    .replace(/\s+[A-Za-z][A-Za-z0-9 +/()-]*$/g, '')
    .replace(/[:：]\s*$/, '')
    .trim()
}

const parseUrlRecord = (name: string, url: string) => {
  const endpointValue = url.match(/[?&]key=([^&#\s]+)/)?.[1] || ''
  if (!endpointValue) return null
  const queryType = getQueryType(name, endpointValue)
  return {
    name,
    code: getServiceIdCode(name, endpointValue),
    category: getCategoryByName(name),
    query_type: queryType,
    result_handler: getResultHandlerByName(name),
    endpoint_type: 'service_id',
    endpoint_value: endpointValue,
    query_param: getQueryParam(queryType)
  }
}

const parsePathRecord = (name: string, path: string) => {
  const endpointValue = path.match(/\/[a-zA-Z0-9][a-zA-Z0-9/_-]*/)?.[0] || ''
  if (!endpointValue) return null
  const queryType = getQueryType(name, endpointValue)
  return {
    name,
    code: normalizeCode(name, endpointValue),
    category: getCategoryByName(name),
    query_type: queryType,
    result_handler: getResultHandlerByName(name),
    endpoint_type: 'path',
    endpoint_value: endpointValue,
    query_param: getQueryParam(queryType)
  }
}

const uniqueRows = (rows: any[]) => {
  const seen = new Set<string>()
  return rows.filter(row => {
    const key = `${row.name}|${row.endpoint_type}|${row.endpoint_value}`
    if (seen.has(key)) return false
    seen.add(key)
    return true
  })
}

const parseServiceDocument = (text: string, fallbackChannelKey = '') => {
  const lines = text
    .split(/\r?\n/)
    .map(item => item.trim())
    .filter(Boolean)
  const rows: any[] = []
  let pendingName = ''

  lines.forEach((line) => {
    const url = line.match(/https?:\/\/\S+/i)?.[0] || ''
    if (url) {
      const name = stripName(line.replace(url, '')) || pendingName
      const record = parseUrlRecord(name, url)
      if (record) rows.push(record)
      pendingName = ''
      return
    }

    const path = line.match(/\/[a-zA-Z0-9][a-zA-Z0-9/_-]*/)?.[0] || ''
    if (path) {
      const name = stripName(line.slice(0, line.indexOf(path))) || pendingName
      const record = parsePathRecord(name, path)
      if (record) rows.push(record)
      pendingName = ''
      return
    }

    if (!/^地址[:：]?$/.test(line)) {
      pendingName = stripName(line)
    }
  })

  return uniqueRows(rows).map((row, index) => ({
    ...row,
    channel_key: fallbackChannelKey,
    cost_price: 0,
    cache_ttl: 0,
    sort: index + 1,
    enabled: 1,
    show_in_check: row.result_handler === 'coverage' ? 1 : 0,
    switch_on_no_data: 0
  }))
}

export function useDeviceQueryConfig() {
  const loading = ref(false)
  const activeTab = ref('services')
  const services = ref<any[]>([])
  const channels = ref<any[]>([])
  const mappings = ref<any[]>([])
  const config = reactive(defaultBaseConfig())

  const serviceDialogVisible = ref(false)
  const channelDialogVisible = ref(false)
  const mappingDialogVisible = ref(false)
  const serviceImportText = ref('')
  const parsedServiceRows = ref<any[]>([])
  const importingParsedServices = ref(false)
  const serviceForm = reactive<any>(defaultServiceForm())
  const channelForm = reactive<any>(defaultChannelForm())
  const mappingForm = reactive<any>(defaultMappingForm())

  const serviceDialogTitle = computed(() => serviceForm._editing ? '编辑查询项' : '新增查询项')
  const channelDialogTitle = computed(() => channelForm._editing ? '编辑渠道' : '新增渠道')
  const mappingDialogTitle = computed(() => mappingForm._editing ? '编辑接口映射' : '新增接口映射')

  const serviceOptions = computed(() => services.value.map(item => ({
    label: `${item.name} (${item.code})`,
    value: item.code
  })))

  const channelOptions = computed(() => channels.value.map(item => ({
    label: `${item.name} (${item.key})`,
    value: item.key
  })))

  const getChannel = (channelKey: string) => channels.value.find(item => item.key === channelKey) || null
  const getService = (serviceCode: string) => services.value.find(item => item.code === serviceCode) || null

  const getEndpointTypeByChannelKey = (channelKey: string) => {
    return getEndpointTypeByChannel(getChannel(channelKey))
  }

  const findChannelByEndpointType = (endpointType: string) => {
    const provider = endpointType === 'service_id' ? 'service_id_query' : 'path_query'
    const defaultChannel = getChannel(config.default_channel_key)
    if (defaultChannel?.provider === provider) return defaultChannel
    return channels.value.find(item => item.provider === provider) || null
  }

  const getMappingGuide = (record: any) => {
    const channel = getChannel(record.channel_key)
    if (!channel) return '请选择可用渠道'
    if (channel.provider === 'path_query') {
      return '3023 路径渠道的接口值必须是 /apple/coverage 这类路径，不能填 10101 或完整 URL'
    }
    if (channel.provider === 'service_id_query') {
      return '爱查服务ID渠道的接口值应填写 10101 这类服务 ID，不能填 /apple/coverage 或完整 URL'
    }
    return ''
  }

  const validateMappingRecord = (record: any) => {
    if (!record.channel_key || !record.endpoint_value || !record.query_param) {
      ElMessage.warning('请补全接口映射的渠道、接口值和参数名')
      return false
    }

    const channel = getChannel(record.channel_key)
    if (!channel) {
      ElMessage.warning('选择的渠道不存在，请先配置渠道')
      return false
    }

    const endpointValue = String(record.endpoint_value || '').trim()
    if (/^https?:\/\//i.test(endpointValue)) {
      ElMessage.warning('接口值不要填写完整 URL。3023 填 /apple/coverage，爱查填 10101')
      return false
    }

    if (channel.provider === 'path_query') {
      if (record.endpoint_type !== 'path') {
        ElMessage.warning('当前渠道是路径接口，接口类型必须选择“路径”')
        return false
      }
      if (!endpointValue.startsWith('/')) {
        ElMessage.warning('3023 路径接口的接口值必须以 / 开头，例如 /apple/coverage')
        return false
      }
    }

    if (channel.provider === 'service_id_query') {
      if (record.endpoint_type !== 'service_id') {
        ElMessage.warning('当前渠道是服务ID接口，接口类型必须选择“服务ID”')
        return false
      }
      if (endpointValue.startsWith('/')) {
        ElMessage.warning('爱查服务ID接口的接口值应填写 10101 这类 ID，不要填写 /apple/coverage')
        return false
      }
    }

    return true
  }

  const getMappingProblem = (mapping: any) => {
    const service = getService(String(mapping.service_code || ''))
    if (!service) return '查询项不存在'

    const channel = getChannel(String(mapping.channel_key || ''))
    if (!channel) return '渠道不存在'
    if (!channel.enabled) return '渠道未启用'
    if (!mapping.enabled) return '映射未启用'
    if (!mapping.endpoint_value) return '接口值为空'
    if (!mapping.query_param) return '参数名为空'

    const endpointValue = String(mapping.endpoint_value || '').trim()
    if (/^https?:\/\//i.test(endpointValue)) return '接口值填写了完整 URL'
    if (channel.provider === 'path_query' && mapping.endpoint_type !== 'path') return '3023 路径渠道必须选择路径接口'
    if (channel.provider === 'path_query' && !endpointValue.startsWith('/')) return '3023 路径渠道的接口值必须以 / 开头'
    if (channel.provider === 'service_id_query' && mapping.endpoint_type !== 'service_id') return '服务ID渠道必须选择服务ID接口'
    if (channel.provider === 'service_id_query' && endpointValue.startsWith('/')) return '服务ID渠道不能填写 / 开头的路径'

    return ''
  }

  const configDiagnostics = computed(() => {
    const enabledChannels = channels.value.filter(item => Number(item.enabled) === 1)
    const mappingCountByService = mappings.value.reduce((map: Record<string, number>, mapping) => {
      if (Number(mapping.enabled) === 1) {
        map[mapping.service_code] = (map[mapping.service_code] || 0) + 1
      }
      return map
    }, {})
    const serviceProblems = services.value
      .filter(service => Number(service.enabled) === 1 && Number(mappingCountByService[service.code] || 0) === 0)
      .map(service => ({
        type: 'warning',
        title: `${service.name} 没有接口映射`,
        desc: '这个查询项不会出现在质检弹窗，也无法执行查询。',
        action: '补映射'
      }))
    const mappingProblems = mappings.value
      .map((mapping, index) => ({
        index,
        mapping,
        problem: getMappingProblem(mapping)
      }))
      .filter(item => item.problem)
      .map(item => ({
        type: 'danger',
        title: `${getService(item.mapping.service_code)?.name || item.mapping.service_code || '未知查询项'} 映射异常`,
        desc: item.problem,
        action: '改映射'
      }))
    const visibleServices = services.value.filter(service => (
      Number(service.enabled) === 1
      && Number(service.show_in_check) === 1
      && Number(mappingCountByService[service.code] || 0) > 0
    ))

    const problems = [
      ...(enabledChannels.length ? [] : [{
        type: 'danger',
        title: '没有启用的查询渠道',
        desc: '请先新增并启用 3023 或爱查助手渠道。',
        action: '配渠道'
      }]),
      ...serviceProblems,
      ...mappingProblems,
      ...(visibleServices.length ? [] : [{
        type: 'warning',
        title: '质检弹窗暂无可用查询按钮',
        desc: '至少需要一个“启用 + 质检显示 + 有映射”的查询项。',
        action: '开按钮'
      }])
    ]

    return {
      channel_total: channels.value.length,
      enabled_channel_total: enabledChannels.length,
      service_total: services.value.length,
      mapping_total: mappings.value.length,
      visible_service_total: visibleServices.length,
      problems,
      invalid_mapping_indexes: mappings.value
        .map((mapping, index) => ({ index, problem: getMappingProblem(mapping) }))
        .filter(item => item.problem)
        .map(item => item.index)
    }
  })

  const upsertServiceLocal = (record: Record<string, any>) => {
    const index = services.value.findIndex(item => item.code === record.code)
    if (index >= 0) {
      services.value.splice(index, 1, { ...services.value[index], ...record })
    } else {
      services.value.push(record)
    }
  }

  const upsertChannelLocal = (record: Record<string, any>) => {
    const index = channels.value.findIndex(item => item.key === record.key)
    if (index >= 0) {
      channels.value.splice(index, 1, { ...record, token: channels.value[index].token || record.token || '' })
    } else {
      channels.value.push(record)
    }
  }

  const upsertMappingLocal = (record: Record<string, any>) => {
    const index = mappings.value.findIndex(item => (
      item.service_code === record.service_code
      && item.channel_key === record.channel_key
      && item.endpoint_value === record.endpoint_value
    ))
    if (index >= 0) {
      mappings.value.splice(index, 1, { ...mappings.value[index], ...record })
    } else {
      mappings.value.push(record)
    }
  }

  const create3023Example = async () => {
    const oldChannel = getChannel('3023_main')
    upsertChannelLocal({
      key: '3023_main',
      name: '3023主渠道',
      provider: 'path_query',
      enabled: 1,
      priority: 100,
      base_url: 'http://api.3023data.com',
      method: 'GET',
      token: oldChannel?.token || '',
      auth_type: 'header',
      auth_key: 'key',
      service_id_key: 'key',
      timeout: 300,
      connect_timeout: 10,
      balance_warning: 20
    })
    upsertServiceLocal({
      code: 'apple_coverage',
      name: '苹果保修查询',
      category: 'apple',
      query_type: 'sn',
      cost_price: 0,
      cache_ttl: 2592000,
      sort: 10,
      enabled: 1,
      show_in_check: 1,
      result_handler: 'coverage'
    })
    upsertMappingLocal({
      service_code: 'apple_coverage',
      channel_key: '3023_main',
      enabled: 1,
      endpoint_type: 'path',
      endpoint_value: '/apple/coverage',
      query_param: 'sn',
      cost_price: 0,
      retry_on: [410, 502, 503],
      switch_on_404: 0,
      switch_on_no_data: 0
    })
    config.default_channel_key = config.default_channel_key || '3023_main'
    await saveConfigCollections()
    await loadData()
    activeTab.value = 'channels'
    ElMessage.success('已创建 3023 示例，请编辑渠道并填写 API Key 后再测试')
  }

  const createGkdtExample = async () => {
    const oldChannel = getChannel('gkdt_main')
    upsertChannelLocal({
      key: 'gkdt_main',
      name: '爱查助手',
      provider: 'service_id_query',
      enabled: 1,
      priority: 90,
      base_url: 'https://api-srv.gkdt.com/inquiry/async',
      method: 'GET',
      token: oldChannel?.token || '',
      auth_type: 'query',
      auth_key: 'token',
      service_id_key: 'key',
      timeout: 300,
      connect_timeout: 10,
      balance_warning: 20
    })
    upsertServiceLocal({
      code: 'apple_coverage',
      name: '苹果保修查询',
      category: 'apple',
      query_type: 'sn',
      cost_price: 0,
      cache_ttl: 2592000,
      sort: 10,
      enabled: 1,
      show_in_check: 1,
      result_handler: 'coverage'
    })
    upsertMappingLocal({
      service_code: 'apple_coverage',
      channel_key: 'gkdt_main',
      enabled: 1,
      endpoint_type: 'service_id',
      endpoint_value: '10101',
      query_param: 'sn',
      cost_price: 0,
      retry_on: [410, 502, 503],
      switch_on_404: 0,
      switch_on_no_data: 0
    })
    config.default_channel_key = config.default_channel_key || 'gkdt_main'
    await saveConfigCollections()
    await loadData()
    activeTab.value = 'channels'
    ElMessage.success('已创建爱查示例，请编辑渠道并确认密钥参数名后再测试')
  }

  const cleanInvalidMappings = async () => {
    if (!configDiagnostics.value.invalid_mapping_indexes.length) {
      ElMessage.info('当前没有需要清理的异常映射')
      return
    }
    await ElMessageBox.confirm(
      `将删除 ${configDiagnostics.value.invalid_mapping_indexes.length} 条异常映射，查询项和渠道不会删除。确定继续吗？`,
      '清理异常映射',
      { type: 'warning' }
    )
    mappings.value = mappings.value.filter((_, index) => !configDiagnostics.value.invalid_mapping_indexes.includes(index))
    await saveConfigCollections()
    await loadData()
    ElMessage.success('异常映射已清理')
  }

  const loadData = async () => {
    loading.value = true
    try {
      const res = await getDeviceQueryConfigList({ page: 1, limit: 500 })
      const payload = unwrapResponseData(res)
      const fullConfig = payload.config || {}
      services.value = payload.list || []
      channels.value = fullConfig.channels || []
      mappings.value = fullConfig.mappings || []
      Object.assign(config, {
        enabled: fullConfig.enabled ?? 1,
        cache_enabled: fullConfig.cache_enabled ?? 1,
        default_cache_ttl: fullConfig.default_cache_ttl ?? 2592000,
        default_channel_key: fullConfig.default_channel_key ?? '3023_main'
      })
    } catch (e) {
      ElMessage.error('加载设备查询配置失败')
    } finally {
      loading.value = false
    }
  }

  const saveConfigCollections = async () => {
    await saveDeviceQueryConfigCenter({
      ...config,
      services: services.value,
      channels: channels.value,
      mappings: mappings.value
    })
  }

  const openServiceDialog = (row?: any) => {
    const defaultForm = defaultServiceForm()
    const defaultChannel = channels.value.find(item => item.key === config.default_channel_key) || channels.value[0] || {}
    Object.assign(serviceForm, defaultForm, row ? { ...row, _editing: true } : {
      mapping_channel_key: defaultChannel.key || '',
      mapping_endpoint_type: getEndpointTypeByChannel(defaultChannel),
      mapping_query_param: 'sn'
    })
    if (!row) {
      serviceImportText.value = ''
      parsedServiceRows.value = []
    }
    serviceDialogVisible.value = true
  }

  const parseServiceImportText = () => {
    if (!serviceImportText.value.trim()) {
      ElMessage.warning('请先粘贴接口文档内容')
      return
    }

    const fallbackChannelKey = config.default_channel_key || channels.value[0]?.key || ''
    parsedServiceRows.value = parseServiceDocument(serviceImportText.value, fallbackChannelKey).map(row => {
      const matchedChannel = findChannelByEndpointType(row.endpoint_type)
      return {
        ...row,
        channel_key: matchedChannel?.key || row.channel_key || '',
        _guide: matchedChannel ? '' : `没有找到${row.endpoint_type === 'service_id' ? '服务ID' : '路径'}类型渠道，请先新增渠道`
      }
    })
    if (!parsedServiceRows.value.length) {
      ElMessage.warning('没有识别到可导入的接口，请确认内容里包含 URL 或 /apple/coverage 这类路径')
      return
    }

    ElMessage.success(`已解析 ${parsedServiceRows.value.length} 个查询项`)
  }

  const applyParsedServiceToForm = (row: any) => {
    Object.assign(serviceForm, {
      code: row.code,
      name: row.name,
      category: row.category,
      query_type: row.query_type,
      cost_price: row.cost_price,
      cache_ttl: row.cache_ttl,
      sort: row.sort,
      enabled: row.enabled,
      show_in_check: row.show_in_check,
      result_handler: row.result_handler,
      create_mapping: 1,
      mapping_channel_key: row.channel_key,
      mapping_endpoint_type: row.endpoint_type,
      mapping_endpoint_value: row.endpoint_value,
      mapping_query_param: row.query_param,
      _editing: false
    })
  }

  const importParsedServices = async () => {
    if (!parsedServiceRows.value.length) {
      ElMessage.warning('请先解析接口文档')
      return
    }

    const invalidRow = parsedServiceRows.value.find(row => !row.name || !row.code || !row.channel_key || !row.endpoint_value)
    if (invalidRow) {
      ElMessage.warning('请补全查询项、编码、渠道和接口值')
      return
    }
    const invalidMapping = parsedServiceRows.value.find(row => !validateMappingRecord(row))
    if (invalidMapping) return

    importingParsedServices.value = true
    try {
      for (const row of parsedServiceRows.value) {
        await addDeviceQueryConfig({
          code: row.code,
          name: row.name,
          category: row.category,
          query_type: row.query_type,
          cost_price: row.cost_price,
          cache_ttl: row.cache_ttl,
          sort: row.sort,
          enabled: row.enabled,
          show_in_check: row.show_in_check,
          result_handler: row.result_handler
        })
      }

      const nextMappings = parsedServiceRows.value.map(row => ({
        service_code: row.code,
        channel_key: row.channel_key,
        enabled: 1,
        endpoint_type: row.endpoint_type,
        endpoint_value: row.endpoint_value,
        query_param: row.query_param,
        cost_price: row.cost_price,
        retry_on: [410, 502, 503],
        switch_on_404: 0,
        switch_on_no_data: row.switch_on_no_data || 0
      }))

      mappings.value = [
        ...mappings.value.filter(item => !nextMappings.some(next => (
          next.service_code === item.service_code
          && next.channel_key === item.channel_key
          && next.endpoint_value === item.endpoint_value
        ))),
        ...nextMappings
      ]
      await saveConfigCollections()

      ElMessage.success(`已导入 ${parsedServiceRows.value.length} 个查询项`)
      serviceDialogVisible.value = false
      serviceImportText.value = ''
      parsedServiceRows.value = []
      await loadData()
    } catch (e) {
      ElMessage.error('批量导入失败，请检查是否存在重复编码')
    } finally {
      importingParsedServices.value = false
    }
  }

  const saveService = async () => {
    const payload = { ...serviceForm }
    delete payload._editing
    const shouldCreateMapping = !serviceForm._editing && !!serviceForm.create_mapping
    if (shouldCreateMapping && !validateMappingRecord({
      channel_key: serviceForm.mapping_channel_key,
      endpoint_type: serviceForm.mapping_endpoint_type,
      endpoint_value: serviceForm.mapping_endpoint_value,
      query_param: serviceForm.mapping_query_param
    })) return
    delete payload.create_mapping
    delete payload.mapping_channel_key
    delete payload.mapping_endpoint_type
    delete payload.mapping_endpoint_value
    delete payload.mapping_query_param
    try {
      if (serviceForm._editing) {
        await editDeviceQueryConfig(serviceForm.code, payload)
      } else {
        await addDeviceQueryConfig(payload)
      }

      if (shouldCreateMapping) {
        const serviceRecord = {
          code: payload.code,
          name: payload.name,
          category: payload.category,
          query_type: payload.query_type,
          cost_price: payload.cost_price,
          cache_ttl: payload.cache_ttl,
          sort: payload.sort,
          enabled: payload.enabled,
          show_in_check: payload.show_in_check,
          result_handler: payload.result_handler
        }
        const nextMapping = {
          service_code: serviceForm.code,
          channel_key: serviceForm.mapping_channel_key,
          enabled: 1,
          endpoint_type: serviceForm.mapping_endpoint_type,
          endpoint_value: serviceForm.mapping_endpoint_value,
          query_param: serviceForm.mapping_query_param,
          cost_price: serviceForm.cost_price,
          retry_on: [410, 502, 503],
          switch_on_404: 0,
          switch_on_no_data: 0
        }
        mappings.value = [
          ...mappings.value.filter(item => !(
            item.service_code === nextMapping.service_code
            && item.channel_key === nextMapping.channel_key
            && item.endpoint_value === nextMapping.endpoint_value
          )),
          nextMapping
        ]
        const serviceIndex = services.value.findIndex(item => item.code === serviceForm.code)
        if (serviceIndex >= 0) {
          services.value.splice(serviceIndex, 1, serviceRecord)
        } else {
          services.value.push(serviceRecord)
        }
        await saveConfigCollections()
      }

      ElMessage.success('查询项已保存')
      serviceDialogVisible.value = false
      await loadData()
    } catch (e) {
      ElMessage.error('查询项保存失败')
    }
  }

  const saveInlineService = async (row: any) => {
    try {
      await editDeviceQueryConfig(row.code, {
        code: row.code,
        name: row.name,
        category: row.category,
        query_type: row.query_type,
        cost_price: row.cost_price,
        cache_ttl: row.cache_ttl,
        sort: row.sort,
        enabled: row.enabled,
        show_in_check: row.show_in_check,
        result_handler: row.result_handler || 'generic'
      })
      ElMessage.success('质检显示已更新')
    } catch (e) {
      ElMessage.error('保存失败')
      await loadData()
    }
  }

  const toggleService = async (row: any) => {
    try {
      await updateDeviceQueryConfigStatus(row.code, row.enabled ? 0 : 1)
      ElMessage.success('查询项状态已更新')
      await loadData()
    } catch (e) {
      ElMessage.error('状态更新失败')
    }
  }

  const testService = async (row: any) => {
    if (Number(row.mapping_count || 0) <= 0) {
      ElMessage.warning('这个查询项还没有接口映射，请先补映射')
      return
    }
    try {
      const queryType = String(row.query_type || 'imei').toUpperCase()
      const defaultPlaceholder = queryType === 'SN' ? '请输入序列号' : `请输入${queryType || 'IMEI/SN'}`
      const { value } = await ElMessageBox.prompt('测试会真实请求第三方接口，可能产生扣费。请输入一个真实串号后再测试。', `测试 ${row.name}`, {
        type: 'warning',
        inputPattern: /\S+/,
        inputErrorMessage: defaultPlaceholder,
        inputPlaceholder: defaultPlaceholder,
        confirmButtonText: '开始测试',
        cancelButtonText: '取消'
      })
      const res = await testDeviceQueryConnection(row.code, {
        query_code: String(value || '').trim(),
        query_type: row.query_type || ''
      })
      const payload = unwrapResponseData(res)
      const result = payload.test_result || payload
      const channelName = result.channel_name ? `，渠道：${result.channel_name}` : ''
      const cost = result.third_cost !== undefined ? `，成本：¥${result.third_cost}` : ''
      ElMessage.success(payload.message || `测试成功${channelName}${cost}`)
    } catch (error: any) {
      if (error === 'cancel' || error === 'close') return
      ElMessage.error(error?.message || '测试失败，请检查渠道、接口映射和密钥')
    }
  }

  const queryChannelBalance = async (row: any) => {
    if (!row.key) {
      ElMessage.warning('渠道键为空，无法查询余额')
      return
    }
    if (row.provider !== 'path_query') {
      ElMessage.warning('当前只支持 3023 路径渠道查询余额')
      return
    }
    try {
      const res = await getDeviceQueryChannelBalance(row.key)
      const payload = unwrapResponseData(res)
      ElMessage.success(`${payload.channel_name || row.name} 余额：¥${Number(payload.balance || 0).toFixed(3)}，耗时 ${payload.response_time || 0}ms`)
    } catch (error: any) {
      ElMessage.error(error?.message || '余额查询失败，请检查渠道地址和 API Key')
    }
  }

  const deleteService = async (row: any) => {
    await ElMessageBox.confirm(`确认删除查询项 ${row.name} 吗？关联接口映射也会一起删除。`, '提示', { type: 'warning' })
    try {
      services.value = services.value.filter(item => item.code !== row.code)
      mappings.value = mappings.value.filter(item => item.service_code !== row.code)
      await saveConfigCollections()
      ElMessage.success('查询项已删除')
      await loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }

  const openChannelDialog = (row?: any) => {
    Object.assign(channelForm, defaultChannelForm(), row ? { ...row, _editing: true } : {})
    channelDialogVisible.value = true
  }

  const saveChannel = async () => {
    if (!channelForm.key || !channelForm.name) {
      ElMessage.warning('请填写渠道键和渠道名称')
      return
    }

    const record = { ...channelForm }
    delete record._editing
    const index = channels.value.findIndex(item => item.key === record.key)
    if (index >= 0) {
      channels.value.splice(index, 1, record)
    } else {
      channels.value.push(record)
    }
    if (!config.default_channel_key || !channels.value.some(item => item.key === config.default_channel_key)) {
      config.default_channel_key = record.key
    }

    try {
      await saveConfigCollections()
      ElMessage.success('渠道已保存')
      channelDialogVisible.value = false
      await loadData()
    } catch (e) {
      ElMessage.error('渠道保存失败')
    }
  }

  const deleteChannel = async (row: any) => {
    await ElMessageBox.confirm(`确认删除渠道 ${row.name} 吗？相关映射也会不可用。`, '提示', { type: 'warning' })
    channels.value = channels.value.filter(item => item.key !== row.key)
    if (config.default_channel_key === row.key) {
      config.default_channel_key = channels.value[0]?.key || ''
    }
    try {
      await saveConfigCollections()
      ElMessage.success('渠道已删除')
      await loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }

  const openMappingDialog = (row?: any, index = -1) => {
    Object.assign(mappingForm, defaultMappingForm(), row ? {
      ...row,
      retry_on: normalizeRetryOn(row.retry_on),
      _index: index,
      _editing: true
    } : {})
    if (mappingForm.channel_key) {
      mappingForm.endpoint_type = getEndpointTypeByChannelKey(mappingForm.channel_key)
    } else {
      const channel = findChannelByEndpointType(mappingForm.endpoint_type)
      mappingForm.channel_key = channel?.key || ''
    }
    mappingDialogVisible.value = true
  }

  const saveMapping = async () => {
    if (!mappingForm.service_code) {
      ElMessage.warning('请选择查询项')
      return
    }
    if (!validateMappingRecord(mappingForm)) return

    const record = { ...mappingForm, retry_on: normalizeRetryOn(mappingForm.retry_on) }
    delete record._index
    delete record._editing
    if (mappingForm._editing && mappingForm._index >= 0) {
      mappings.value.splice(mappingForm._index, 1, record)
    } else {
      mappings.value.push(record)
    }

    try {
      await saveConfigCollections()
      ElMessage.success('接口映射已保存')
      mappingDialogVisible.value = false
      await loadData()
    } catch (e) {
      ElMessage.error('接口映射保存失败')
    }
  }

  const deleteMapping = async (_row: any, index: number) => {
    await ElMessageBox.confirm('确认删除这个接口映射吗？', '提示', { type: 'warning' })
    mappings.value.splice(index, 1)
    try {
      await saveConfigCollections()
      ElMessage.success('接口映射已删除')
      await loadData()
    } catch (e) {
      ElMessage.error('删除失败')
    }
  }

  const saveBaseConfig = async () => {
    try {
      await saveConfigCollections()
      ElMessage.success('基础配置已保存')
      await loadData()
    } catch (e) {
      ElMessage.error('保存失败')
    }
  }

  const resetBaseConfig = () => Object.assign(config, defaultBaseConfig())

  return {
    loading,
    activeTab,
    services,
    channels,
    mappings,
    config,
    serviceDialogVisible,
    channelDialogVisible,
    mappingDialogVisible,
    serviceImportText,
    parsedServiceRows,
    importingParsedServices,
    serviceForm,
    channelForm,
    mappingForm,
    serviceDialogTitle,
    channelDialogTitle,
    mappingDialogTitle,
    serviceOptions,
    channelOptions,
    configDiagnostics,
    loadData,
    create3023Example,
    createGkdtExample,
    cleanInvalidMappings,
    openServiceDialog,
    parseServiceImportText,
    applyParsedServiceToForm,
    importParsedServices,
    saveService,
    saveInlineService,
    toggleService,
    testService,
    deleteService,
    queryChannelBalance,
    openChannelDialog,
    saveChannel,
    deleteChannel,
    openMappingDialog,
    getEndpointTypeByChannelKey,
    getMappingGuide,
    saveMapping,
    deleteMapping,
    saveBaseConfig,
    resetBaseConfig
  }
}
