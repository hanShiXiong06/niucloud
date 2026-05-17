export type DeviceQueryEndpointType = 'path' | 'service_id'
export type DeviceQueryProviderType = 'path_query' | 'service_id_query'
export type DeviceQueryResultHandler = 'coverage' | 'activationlock' | 'mdm' | 'generic'

export interface DeviceQueryProviderPreset {
  key: string
  name: string
  provider: DeviceQueryProviderType
  endpoint_type: DeviceQueryEndpointType
  base_url: string
  auth_type: 'header' | 'query' | 'none'
  auth_key: string
  service_id_key: string
  priority: number
  enabled: number
  guide: string
  register_guide: string
  document_guide: string
  recharge_guide: string
  secret_guide: string
}

export interface DeviceQueryServicePreset {
  provider_key: string
  provider_name: string
  category_label: string
  category: string
  code: string
  name: string
  query_type: string
  query_param: string
  result_handler: DeviceQueryResultHandler
  endpoint_type: DeviceQueryEndpointType
  endpoint_value: string
  cost_price: number
  cost_label: string
  cache_ttl: number
  sort: number
  enabled: number
  show_in_check: number
  switch_on_no_data: number
  sample_fields?: string[]
}

export const providerPresets: DeviceQueryProviderPreset[] = [
  {
    key: 'gkdt_main',
    name: '爱查助手',
    provider: 'service_id_query',
    endpoint_type: 'service_id',
    base_url: 'https://api-srv.gkdt.com/inquiry/async',
    auth_type: 'query',
    auth_key: 'token',
    service_id_key: 'key',
    priority: 90,
    enabled: 0,
    guide: '接口映射填写 10101 这类服务 ID；渠道密钥字段按服务商实际文档填写，服务 ID 字段为 key。',
    register_guide: '先到爱查助手服务商后台注册/开通企业账号，确认已开通设备查询 API 权限。',
    document_guide: '在服务商后台查看或下载接口文档，确认接口地址、token 参数名、服务 ID 列表和扣费规则。',
    recharge_guide: '在服务商后台充值或购买接口套餐。余额不足时第三方通常会返回失败或空结果。',
    secret_guide: '在服务商后台的 API 密钥、接口配置或开发者中心复制 token。这里不内置密钥，必须由客户自己填写。'
  },
  {
    key: '3023_main',
    name: '3023Data',
    provider: 'path_query',
    endpoint_type: 'path',
    base_url: 'http://api.3023data.com',
    auth_type: 'header',
    auth_key: 'key',
    service_id_key: 'key',
    priority: 100,
    enabled: 1,
    guide: '接口映射填写 /apple/coverage 这类路径；3023 通常使用 Header 鉴权，字段名 key。',
    register_guide: '先到 3023Data 服务商后台注册账号并完成认证，确认账号已开通需要使用的接口。',
    document_guide: '在服务商后台查看或下载接口文档，确认接口路径、请求方式、Header 字段名和返回结构。',
    recharge_guide: '在服务商后台充值余额或购买对应接口套餐。查询会按服务商规则扣费。',
    secret_guide: '在服务商后台的 API Key、开发者中心或接口密钥页面复制 key。这里不提供默认密钥，必须由客户自己填写。'
  }
]

const day = 86400

const serviceMeta: Record<string, Partial<DeviceQueryServicePreset>> = {
  apple_model: { result_handler: 'generic', show_in_check: 0, cache_ttl: 365 * day },
  apple_coverage: { result_handler: 'coverage', show_in_check: 1, cache_ttl: 30 * day },
  apple_coverage_capacity: { result_handler: 'coverage', show_in_check: 1, cache_ttl: 30 * day },
  apple_activationlock: { result_handler: 'activationlock', show_in_check: 1, cache_ttl: 30 * day },
  apple_mdm: { result_handler: 'mdm', show_in_check: 0, cache_ttl: 30 * day },
  huawei_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  honor_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  xiaomi_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  oppo_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  vivo_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  samsung_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  realme_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  nubia_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  motorola_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  zte_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  oneplus_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  meizu_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  asus_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  sony_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  dji_coverage: { result_handler: 'coverage', cache_ttl: 30 * day },
  imei_blacklist: { cache_ttl: 7 * day },
  imei_att: { cache_ttl: 7 * day },
  imei_t_mobile: { cache_ttl: 7 * day },
  imei_verizon: { cache_ttl: 7 * day },
  item_barcode: { cache_ttl: 365 * day },
  ip_location: { cache_ttl: 365 * day },
  phone_location: { cache_ttl: 365 * day }
}

const queryParamByType: Record<string, string> = {
  sn: 'sn',
  imei: 'imei',
  barcode: 'barcode',
  ip: 'ip',
  phone: 'phone'
}

const getProviderPreset = (providerKey: string) => {
  const provider = providerPresets.find(item => item.key === providerKey)
  if (!provider) throw new Error(`Unknown provider preset: ${providerKey}`)
  return provider
}

const getCategory = (label: string, code: string) => {
  if (label === '苹果') return 'apple'
  if (label === '安卓') return 'android'
  if (code.startsWith('imei_')) return 'imei'
  return 'other'
}

const preset = (
  providerKey: string,
  categoryLabel: string,
  name: string,
  endpointValue: string,
  code: string,
  queryType = 'imei',
  costLabel = '',
  sampleFields: string[] = []
): DeviceQueryServicePreset => {
  const provider = getProviderPreset(providerKey)
  const meta = serviceMeta[code] || {}
  const resultHandler = (meta.result_handler || (name.includes('保修') ? 'coverage' : name.includes('激活锁') ? 'activationlock' : name.includes('监管锁') ? 'mdm' : 'generic')) as DeviceQueryResultHandler
  return {
    provider_key: provider.key,
    provider_name: provider.name,
    category_label: categoryLabel,
    category: getCategory(categoryLabel, code),
    code,
    name,
    query_type: queryType,
    query_param: queryParamByType[queryType] || 'imei',
    result_handler: resultHandler,
    endpoint_type: provider.endpoint_type,
    endpoint_value: endpointValue,
    cost_price: Number(String(costLabel).match(/[\d.]+/)?.[0] || 0),
    cost_label: costLabel,
    cache_ttl: Number(meta.cache_ttl ?? (resultHandler === 'coverage' ? 30 * day : 7 * day)),
    sort: 100,
    enabled: 1,
    show_in_check: Number(meta.show_in_check ?? (resultHandler === 'coverage' ? 0 : 0)),
    switch_on_no_data: 1,
    sample_fields: sampleFields
  }
}

export const deviceQueryServicePresets: DeviceQueryServicePreset[] = [
  preset('gkdt_main', '苹果', '苹果保修查询（容量/颜色）', '10102', 'apple_coverage_capacity', 'sn', '', ['activated', 'capacity', 'color', 'coverage_status', 'purchase_date', 'network_lock']),
  preset('gkdt_main', '苹果', '苹果保修查询', '10101', 'apple_coverage', 'sn', '', ['activated', 'coverage_status', 'coverage_date', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果保修查询（备用）', '10103', 'apple_coverage_backup', 'sn', '', ['capacity', 'color', 'coverage_status', 'purchase_date']),
  preset('gkdt_main', '苹果', '苹果激活锁查询', '10104', 'apple_activationlock', 'sn', '', ['locked', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果ID黑白查询', '10105', 'apple_icloud', 'sn', '', ['icloud', 'locked', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果维修状态查询', '10106', 'apple_repair', 'sn', '', ['status', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果型号配置查询', '10107', 'apple_model_configuration', 'sn', '', ['model', 'capacity', 'color', 'configuration']),
  preset('gkdt_main', '苹果', '苹果IMEI转序列号', '10108', 'apple_imei_to_serial', 'imei', '', ['imei', 'sn', 'model']),
  preset('gkdt_main', '苹果', '苹果IMEI2查询', '10109', 'apple_imei2', 'imei', '', ['imei', 'imei2', 'sn', 'model']),
  preset('gkdt_main', '苹果', '苹果激活锁查询（备用）', '10110', 'apple_activationlock_backup', 'sn', '', ['locked', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果验机报告（网络锁）', '10111', 'apple_details_network_lock', 'sn', '', ['fmi', 'icloud', 'sim_lock', 'blacklist_status', 'repair_status']),
  preset('gkdt_main', '苹果', '苹果验机报告（网络锁/运营商）', '10112', 'apple_details_network_carrier', 'imei', '', ['carrier', 'sim_lock', 'icloud', 'imei2', 'purchase_country']),
  preset('gkdt_main', '苹果', '苹果验机报告（购买日期）', '10113', 'apple_details_purchase_date', 'imei', '', ['purchase_date', 'fmi', 'icloud', 'sim_card', 'product_type']),
  preset('gkdt_main', '苹果', '苹果验机报告（产品类型）', '10114', 'apple_details_product_type', 'imei', '', ['product_type', 'network_type', 'part_number', 'sim_lock']),
  preset('gkdt_main', '苹果', '苹果验机报告（旗舰版）', '10115', 'apple_details_ultimate', 'imei', '', ['model_description', 'purchase_date', 'sim_lock', 'fmi', 'policy_id']),
  preset('gkdt_main', '苹果', '苹果网络锁查询', '10116', 'apple_simlock', 'imei', '', ['sim_lock', 'network', 'model', 'imei']),
  preset('gkdt_main', '苹果', '苹果运营商查询', '10117', 'apple_carrier', 'imei', '', ['carrier', 'sim_lock', 'coverage_status']),
  preset('gkdt_main', '苹果', '苹果购买日期查询', '10118', 'apple_purchase_date', 'imei', '', ['purchase_date', 'coverage_status', 'fmi']),
  preset('gkdt_main', '苹果', '苹果购买日期查询（DOP）', '10119', 'apple_purchase_date_dop', 'sn', '', ['purchase_date', 'bright_star', 'replaced']),
  preset('gkdt_main', '苹果', '型号/销售地查询', '10120', 'apple_country', 'sn', '', ['model', 'purchase_country', 'type']),
  preset('gkdt_main', '苹果', '型号/销售地查询（全部设备）', '10121', 'apple_country_all', 'sn', '', ['description', 'model', 'purchase_country', 'type']),
  preset('gkdt_main', '苹果', '苹果型号号码查询', '10122', 'apple_partnumber', 'sn', '', ['part_number', 'part_country', 'part_description']),
  preset('gkdt_main', '苹果', 'Mac激活锁查询', '10123', 'apple_mac_activationlock', 'sn', '', ['locked', 'model', 'capacity', 'color']),
  preset('gkdt_main', '苹果', 'Mac配置查询', '10124', 'apple_mac_configuration', 'sn', '', ['configuration', 'model', 'sn']),
  preset('gkdt_main', '苹果', '苹果监管锁查询', '10125', 'apple_mdm', 'sn', '', ['mdmlock', 'locked', 'fmi', 'warranty']),
  preset('gkdt_main', '苹果', '苹果型号查询', '10126', 'apple_model', 'imei', '', ['model', 'capacity', 'color', 'validate_status']),
  preset('gkdt_main', '苹果', '苹果机型查询', '10127', 'apple_device_model', 'imei', '', ['identifier', 'model', 'model_number']),
  preset('gkdt_main', '苹果', '苹果容量/颜色查询', '10128', 'apple_capacity_color', 'sn', '', ['capacity', 'color', 'description', 'model']),
  preset('gkdt_main', '苹果', '苹果维修进度查询', '10129', 'apple_repair_progress', 'sn', '', ['status', 'model', 'sn']),
  preset('gkdt_main', '安卓', '华为保修查询', '20101', 'huawei_coverage', 'imei', '', ['coverage', 'coverage_mode', 'purchase_date', 'model']),
  preset('gkdt_main', '安卓', '小米保修查询', '20201', 'xiaomi_coverage', 'imei', '', ['coverage', 'activationlock_locked', 'purchase_date', 'model']),
  preset('gkdt_main', '安卓', '小米账号锁查询', '20202', 'xiaomi_activationlock', 'imei', '', ['locked', 'lost', 'email', 'phone']),
  preset('gkdt_main', '安卓', 'OPPO保修查询', '20301', 'oppo_coverage', 'imei', '', ['coverage', 'purchase_date', 'color', 'ram', 'rom']),
  preset('gkdt_main', '安卓', 'OPPO保修查询（官网版）', '20302', 'oppo_coverage_official', 'imei', '', ['brand', 'coverage', 'replacement', 'skucode']),
  preset('gkdt_main', '安卓', 'vivo保修查询', '20401', 'vivo_coverage', 'imei', '', ['coverage_status', 'coverage_date', 'purchase_date']),
  preset('gkdt_main', '安卓', '三星保修查询', '20501', 'samsung_coverage', 'imei', '', ['activation_date', 'coverage_status', 'model_number']),
  preset('gkdt_main', '安卓', '三星销售地查询', '20502', 'samsung_country', 'imei', '', ['purchase_country', 'model', 'sn']),
  preset('gkdt_main', '安卓', '荣耀保修查询', '20601', 'honor_coverage', 'imei', '', ['coverage', 'covered', 'purchase_date']),
  preset('gkdt_main', '安卓', '一加保修查询', '20701', 'oneplus_coverage', 'imei', '', ['coverage', 'purchase_country', 'imei2']),
  preset('gkdt_main', '安卓', '魅族保修查询', '20801', 'meizu_coverage', 'imei', '', ['coverage', 'model', 'sn']),
  preset('gkdt_main', '安卓', 'realme保修查询', '20901', 'realme_coverage', 'imei', '', ['coverage', 'purchase_date', 'color']),
  preset('gkdt_main', '安卓', '努比亚保修查询', '21001', 'nubia_coverage', 'imei', '', ['coverage', 'capacity', 'color', 'model_name']),
  preset('gkdt_main', '安卓', '华硕保修查询', '21101', 'asus_coverage', 'imei', '', ['coverage_status', 'coverage_date', 'series']),
  preset('gkdt_main', '安卓', '索尼保修查询', '21201', 'sony_coverage', 'imei', '', ['brand', 'coverage_status', 'manufacturer']),
  preset('gkdt_main', '其他', 'GSMA黑白查询', '90101', 'imei_blacklist', 'imei', '', ['status', 'model', 'imei']),
  preset('gkdt_main', '其他', '美国黑名单查询', '90102', 'imei_blacklist_us', 'imei', '', ['status', 'description', 'model']),
  preset('gkdt_main', '其他', 'AT&T黑白查询', '90103', 'imei_att', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'T-Mobile黑白查询', '90104', 'imei_t_mobile', 'imei', '', ['status', 'esim', 'model']),
  preset('gkdt_main', '其他', 'Verizon黑白查询', '90105', 'imei_verizon', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'TracFone黑白查询', '90106', 'imei_tracfone', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'KDDI黑白查询', '90107', 'imei_kddi', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'SoftBank黑白查询', '90108', 'imei_softbank', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'DOCOMO黑白查询', '90109', 'imei_docomo', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', 'UQ Mobile黑白查询', '90110', 'imei_uq_mobile', 'imei', '', ['status', 'description']),
  preset('gkdt_main', '其他', '大疆保修查询', '90905', 'dji_coverage', 'sn', '', ['activation_status', 'warranty_status', 'repair_count']),
  preset('3023_main', '苹果', '苹果保修查询', '/apple/coverage', 'apple_coverage', 'sn', '0.4-0.8元'),
  preset('3023_main', '苹果', '苹果保修查询（容量/颜色）', '/apple/coverage-capacity', 'apple_coverage_capacity', 'sn', '1元'),
  preset('3023_main', '苹果', '苹果保修查询（预激活）', '/apple/coverage-activation', 'apple_coverage_activation', 'sn', '1.2元'),
  preset('3023_main', '苹果', '苹果保修查询（备用）', '/apple/coverage-backup', 'apple_coverage_backup', 'sn', '1.2元'),
  preset('3023_main', '苹果', '激活锁查询', '/apple/activationlock', 'apple_activationlock', 'sn', '0.4元'),
  preset('3023_main', '苹果', 'ID黑白查询', '/apple/icloud', 'apple_icloud', 'sn', '0.8元'),
  preset('3023_main', '苹果', '序列号转换', '/apple/serial', 'apple_serial', 'imei', '1元'),
  preset('3023_main', '苹果', '维修状态查询', '/apple/repair', 'apple_repair', 'sn', '0.2元'),
  preset('3023_main', '苹果', '网络锁查询', '/apple/simlock', 'apple_simlock', 'imei', '1元'),
  preset('3023_main', '苹果', '运营商查询', '/apple/carrier', 'apple_carrier', 'imei', '1.2元'),
  preset('3023_main', '苹果', '销售地查询', '/apple/country', 'apple_country', 'imei', '1.2元'),
  preset('3023_main', '苹果', '型号号码查询', '/apple/partnumber', 'apple_partnumber', 'sn', '1.6元'),
  preset('3023_main', '苹果', '监管锁查询', '/apple/mdm', 'apple_mdm', 'sn', '8元'),
  preset('3023_main', '苹果', 'Mac激活锁查询', '/apple/mac-activationlock', 'apple_mac_activationlock', 'sn', '2元'),
  preset('3023_main', '苹果', '苹果验机报告', '/apple/details', 'apple_details', 'sn', '2.5元'),
  preset('3023_main', '苹果', '苹果验机报告（极速版）', '/apple/details-essentials', 'apple_details_essentials', 'sn', '2.5-3.5元'),
  preset('3023_main', '苹果', '苹果验机报告（旗舰版）', '/apple/details-ultimate', 'apple_details_ultimate', 'sn', '3.5元'),
  preset('3023_main', '苹果', '苹果型号查询', '/apple/model', 'apple_model', 'imei', '0.05元'),
  preset('3023_main', '安卓', '华为保修查询', '/huawei/coverage', 'huawei_coverage', 'imei', '0.4元'),
  preset('3023_main', '安卓', '荣耀保修查询', '/honor/coverage', 'honor_coverage', 'imei', '0.4元'),
  preset('3023_main', '安卓', '小米保修查询', '/xiaomi/coverage', 'xiaomi_coverage', 'imei', '1元'),
  preset('3023_main', '安卓', 'OPPO保修查询', '/oppo/coverage', 'oppo_coverage', 'imei', '0.8元'),
  preset('3023_main', '安卓', 'vivo保修查询', '/vivo/coverage', 'vivo_coverage', 'imei', '1元'),
  preset('3023_main', '安卓', '三星保修查询', '/samsung/coverage', 'samsung_coverage', 'imei', '1元'),
  preset('3023_main', '安卓', '真我保修查询', '/realme/coverage', 'realme_coverage', 'imei', '0.8元'),
  preset('3023_main', '安卓', '努比亚保修查询', '/nubia/coverage', 'nubia_coverage', 'imei', '1元'),
  preset('3023_main', '安卓', 'moto保修查询', '/motorola/coverage', 'motorola_coverage', 'imei', '1元'),
  preset('3023_main', '安卓', '中兴保修查询', '/zte/coverage', 'zte_coverage', 'imei', '0.6元'),
  preset('3023_main', '安卓', '小米激活锁查询', '/xiaomi/activationlock', 'xiaomi_activationlock', 'imei', '0.02元'),
  preset('3023_main', '其他', 'IMEI查询（型号）', '/imei/model', 'imei_model', 'imei', '0.2元'),
  preset('3023_main', '其他', 'IMEI查询（生产日期）', '/imei/manufacture', 'imei_manufacture', 'imei', '0.6元'),
  preset('3023_main', '其他', 'IMEI查询（黑名单）', '/imei/blacklist', 'imei_blacklist', 'imei', '0.4元'),
  preset('3023_main', '其他', 'AT&T状态查询', '/imei/att', 'imei_att', 'imei', '0.8元'),
  preset('3023_main', '其他', 'T-Mobile状态查询', '/imei/t-mobile', 'imei_t_mobile', 'imei', '0.8元'),
  preset('3023_main', '其他', 'Verizon状态查询', '/imei/verizon', 'imei_verizon', 'imei', '0.6元'),
  preset('3023_main', '其他', '条码查询', '/item/barcode', 'item_barcode', 'barcode', '0.02元'),
  preset('3023_main', '其他', 'IP地址查询', '/ip/location', 'ip_location', 'ip', '0.001元'),
  preset('3023_main', '其他', '号码归属地查询', '/phone/location', 'phone_location', 'phone', '0.001元')
].map((item, index) => ({
  ...item,
  sort: index + 10
}))

export const providerPresetOptions = providerPresets.map(item => ({
  label: item.name,
  value: item.key
}))

export const categoryPresetOptions = [
  { label: '全部分类', value: '' },
  { label: '苹果', value: '苹果' },
  { label: '安卓', value: '安卓' },
  { label: '其他', value: '其他' }
]
