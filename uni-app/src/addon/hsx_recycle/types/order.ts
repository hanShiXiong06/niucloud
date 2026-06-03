/**
 * 回收订单相关类型定义
 */

// 设备信息
export interface Device {
  imei: string
  user_sn?: string
  model?: string
  initial_price?: string
}

// 订单表单
export interface OrderForm {
  count: number
  express_no: string
  customer_name: string
  customer_phone: string
  telphone: string
  comment: string
  delivery_type: number  // 1-邮寄 2-自送
  devices?: Device[]
}

// 平台快递表单
export interface PlatformDeliveryForm {
  sender_name: string
  sender_mobile: string
  province: string
  city: string
  district: string
  area_text: string
  detail_address: string
  pickup_time: string
  weight: string
  provider?: string
  provider_name?: string
  product_code?: string
  product_name?: string
}

// 商家信息
export interface ShopInfo {
  name: string
  mobile: string
  business_hours?: string
  lat: string
  lng: string
  full_address: string
  address: string
}

// 地址解析结果
export interface AddressParts {
  province: string
  city: string
  district: string
  areaText: string
  detailAddress: string
}

// 订单提交参数
export interface SubmitOrderParams {
  form: OrderForm
  usePlatformDelivery: boolean
  platformDeliveryForm?: PlatformDeliveryForm
  isAgreeRecycle: boolean
}

// 快递配置（统一快递服务）
export interface ExpressConfig {
  sender_name: string
  sender_mobile: string
  sender_province: string
  sender_city: string
  sender_district: string
  sender_address: string
  product_code?: string
  weight?: number
  package_count?: number
  pickup_time?: string
  estimated_cost?: number
}

// 地址信息（从地址选择页返回）
export interface AddressInfo {
  id: number
  name: string
  mobile: string
  province?: string
  city?: string
  district?: string
  area?: string
  address?: string
  full_address?: string
  is_default?: number
}

// ========== 订单列表相关类型 ==========

// 订单列表中的设备信息
export interface OrderDevice {
  id: number
  imei: string
  user_sn?: string
  model: string
  brand: string
  initial_price: string
  final_price: string
  status: number
  status_name: string
  remark?: string
}

// 订单列表项
export interface OrderListItem {
  id: number
  order_no: string
  status: number
  status_name: string
  delivery_type: string  // "1"-邮寄 "2"-自送 (API返回字符串)
  delivery_type_name: string
  express_no: string
  express_company?: string
  create_at: string  // API 返回的是 create_at
  update_at: string  // API 返回的是 update_at
  count: number  // API 返回的是 count (设备数量)
  devices: OrderDevice[]
  remark?: string  // API 返回的是 remark
  cancel_reason?: string
  customer_name?: string
  customer_phone?: string
  // 收货地址信息（邮寄订单）
  sender_name?: string
  sender_mobile?: string
  sender_address?: string
  // 商家信息（自送订单）
  shop_name?: string
  shop_address?: string
}

// 订单筛选条件
export interface OrderFilters {
  status: string  // 订单状态: 'all'-全部, '1'-待签收, '2'-已签收, '3'-质检中, '4'-已质检, '5'-待确认, '6'-待打款, '7'-已完成, '8'-已关闭, '9'-已取消
  delivery_type: number  // 0-全部 1-邮寄 2-自送
  search_keyword: string
}

// 订单状态信息
export interface OrderStatusInfo {
  text: string
  color: string
  bgColor: string
}

// Mescroll 配置
export interface MescrollUpOption {
  auto: boolean
  page: {
    num: number
    size: number
  }
  noMoreSize: number
  empty: {
    tip: string
  }
}

// ========== 订单详情相关类型 ==========

// 订单详情中的设备信息（比列表中更详细）
export interface OrderDetailDevice {
  id: number
  imei: string
  user_sn?: string
  model: string
  brand?: string
  initial_price: string
  final_price: string
  status: number
  status_name: string
  remark?: string
  capacity?: string
  color?: string
  check_result?: string
  check_result_seller?: string
  check_status: number
  check_at?: number
  check_images?: string
  check_images_seller?: string
  check_images_seller_thumb_small?: string[]
  price_remark?: string
  info?: any
  consignment_order_id?: number
  consignmentOrder?: {
    id?: number
    consignment_no?: string
    status?: number
    status_name?: string
  }
  create_at: number
  update_at?: number
}

// 订单详情信息
export interface OrderDetailInfo {
  id: number
  order_no: string
  status: number
  status_name: string
  delivery_type: string  // "1"-邮寄 "2"-自送
  delivery_type_name: string
  express_no?: string
  express_company?: string
  create_at: string
  update_at?: string
  devices: OrderDetailDevice[]
  remark?: string
  cancel_reason?: string
  // 用户信息
  send_username?: string
  telphone?: string
  member?: {
    nickname?: string
    mobile?: string
  }
  // 收货地址信息（邮寄订单）
  sender_name?: string
  sender_mobile?: string
  sender_address?: string
  // 商家信息（自送订单）
  shop_name?: string
  shop_address?: string
}

// 设备状态映射
export interface DeviceStatusMap {
  [key: string]: string
}

// 订单状态映射
export interface OrderStatusMap {
  [key: string]: string
}
