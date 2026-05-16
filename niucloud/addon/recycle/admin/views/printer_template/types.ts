// 模板元素类型
export type ElementType = 'text' | 'qrcode' | 'barcode' | 'line' | 'rectangle' | 'image'

// 条形码类型
export type BarcodeType = 'BC128' | 'BC39'

// QR 纠错等级
export type QRErrorLevel = 'L' | 'M' | 'Q' | 'H'

// 模板元素
export interface TemplateElement {
  id: string
  type: ElementType
  x: number           // dot 单位（内部存储）
  y: number           // dot 单位
  content?: string    // 文本/QR/条形码内容

  // 文本属性
  font?: number       // 1-9
  width_scale?: number  // 1-10
  height_scale?: number // 1-10
  rotation?: number   // 0/90/180/270

  // 条形码属性
  barcode_type?: BarcodeType
  height?: number     // dot
  narrow_width?: number
  wide_width?: number
  human_readable?: number // 0/1

  // QR 码属性
  size?: number       // 1-10
  error_level?: QRErrorLevel
  quiet_zone?: number // 0-10，二维码静区模块数

  // 线条属性
  width?: number      // dot

  // 矩形属性
  xe?: number         // dot
  ye?: number         // dot
  style?: number      // 线宽 1-10

  // 图片属性
  img_width?: number  // 20-100
}

// 模板数据
export interface TemplateData {
  width: number       // mm
  height: number      // mm
  copies: number      // 打印份数
  margin: { top: number; right: number; bottom: number; left: number }
  elements: TemplateElement[]
}

// 字体定义
export interface FontDef {
  id: number
  name: string
  charWidth: number   // dot
  charHeight: number  // dot
  desc: string
}

// 字体列表（芯烨云打印机）
export const FONT_LIST: FontDef[] = [
  { id: 1, name: '英文小', charWidth: 8, charHeight: 12, desc: '8×12dot (1×1.5mm)' },
  { id: 2, name: '英文中', charWidth: 12, charHeight: 20, desc: '12×20dot (1.5×2.5mm)' },
  { id: 3, name: '英文中大', charWidth: 16, charHeight: 24, desc: '16×24dot (2×3mm)' },
  { id: 4, name: '英文大', charWidth: 32, charHeight: 32, desc: '32×32dot (4×4mm)' },
  { id: 5, name: '英文5', charWidth: 24, charHeight: 32, desc: '24×32dot (3×4mm)' },
  { id: 6, name: '英文6', charWidth: 16, charHeight: 32, desc: '16×32dot (2×4mm)' },
  { id: 7, name: '英文7', charWidth: 24, charHeight: 24, desc: '24×24dot (3×3mm)' },
  { id: 8, name: '英文8', charWidth: 12, charHeight: 24, desc: '12×24dot (1.5×3mm)' },
  { id: 9, name: '中文标准', charWidth: 24, charHeight: 24, desc: '24×24dot (3×3mm) 最常用' },
]

// 变量分类
export interface VariableGroup {
  label: string
  items: VariableItem[]
}

export interface VariableItem {
  key: string
  label: string
  sampleValue: string
}

// 变量列表
export const VARIABLE_GROUPS: VariableGroup[] = [
  {
    label: '订单信息',
    items: [
      { key: 'order_no', label: '订单号', sampleValue: 'RC20260101120000' },
      { key: 'order_id', label: '订单ID', sampleValue: '12345' },
      { key: 'customer_name', label: '客户姓名', sampleValue: '张三' },
      { key: 'member_nickname', label: '会员昵称', sampleValue: '小明' },
      { key: 'customer_phone', label: '客户电话', sampleValue: '138****8000' },
      { key: 'pay_type', label: '支付方式', sampleValue: '支付宝' },
      { key: 'pay_account', label: '支付账号', sampleValue: '138****8000' },
      { key: 'total_amount', label: '订单总金额', sampleValue: '5000.00' },
      { key: 'device_count', label: '设备数量', sampleValue: '1' },
      { key: 'order_status', label: '订单状态', sampleValue: '已完成' },
      { key: 'order_remark', label: '订单备注', sampleValue: '无' },
    ]
  },
  {
    label: '设备基本信息',
    items: [
      { key: 'device_id', label: '设备ID', sampleValue: '12345' },
      { key: 'imei', label: 'IMEI', sampleValue: '867851234567890' },
      { key: 'imei2', label: 'IMEI2', sampleValue: '867851234567891' },
      { key: 'sn', label: 'SN序列号', sampleValue: 'C02XG0FHJHD5' },
      { key: 'model', label: '型号', sampleValue: 'iPhone 14 Pro Max' },
      { key: 'category_name', label: '分类', sampleValue: '手机' },
      { key: 'system_version', label: '系统版本', sampleValue: 'iOS 17.3.1' },
      { key: 'warranty_info', label: '保修信息', sampleValue: '2025-12-31' },
      { key: 'capacity', label: '内存/规格', sampleValue: '256GB' },
      { key: 'color', label: '颜色', sampleValue: '深空黑色' },
      { key: 'device_index', label: '设备序号', sampleValue: '1' },
      { key: 'device_total', label: '设备总数', sampleValue: '4' },
      { key: 'device_number', label: '设备编号', sampleValue: '1/4' },
      //电池状态
      { key: 'battery', label: '电池', sampleValue: '100' },
    ]
  },
  {
    label: '质检信息',
    items: [
      { key: 'check_result', label: '质检结果', sampleValue: '外观良好功能正常' },
      { key: 'check_result_seller', label: '卖家可见质检', sampleValue: '外观良好' },
      { key: 'check_result_buyer', label: '买家可见质检', sampleValue: '功能正常' },
      { key: 'check_staff', label: '质检员', sampleValue: '李四' },
      { key: 'check_date', label: '质检日期', sampleValue: '2026-01-01' },
      { key: 'check_time', label: '质检时间', sampleValue: '2026-01-01 12:00' },
      { key: 'check_status', label: '质检状态', sampleValue: '已质检' },
    ]
  },
  {
    label: '价格信息',
    items: [
      { key: 'initial_price', label: '预估价格', sampleValue: '5000.00' },
      { key: 'final_price', label: '最终价格', sampleValue: '4800.00' },
      { key: 'sell_price', label: '卖货价格', sampleValue: '5200.00' },
      { key: 'price', label: '价格', sampleValue: '4800.00' },
      { key: 'before_price', label: '之前定价', sampleValue: '4500.00' },
      { key: 'price_remark', label: '价格备注', sampleValue: '市场行情调整' },
      { key: 'price_staff', label: '定价员', sampleValue: '王五' },
      { key: 'price_time', label: '定价时间', sampleValue: '2026-01-02 10:00' },
    ]
  },
  {
    label: '快递信息',
    items: [
      { key: 'express_company', label: '快递公司', sampleValue: '顺丰速运' },
      { key: 'express_no', label: '快递单号', sampleValue: 'SF1234567890' },
      { key: 'delivery_type', label: '发货方式', sampleValue: '快递' },
      { key: 'delivery_fee', label: '快递费用', sampleValue: '15.00' },
      { key: 'delivery_status', label: '快递状态', sampleValue: '已签收' },
      { key: 'delivery_operator', label: '快递操作人', sampleValue: '张三' },
    ]
  },
  {
    label: '状态信息',
    items: [
      { key: 'status', label: '设备状态', sampleValue: '已回收' },
      { key: 'status_name', label: '状态名称', sampleValue: '已回收' },
      { key: 'final_status', label: '最终状态', sampleValue: '已确认' },
    ]
  },
  {
    label: '时间信息',
    items: [
      { key: 'create_time', label: '创建时间', sampleValue: '2026-01-01 12:00' },
      { key: 'update_time', label: '更新时间', sampleValue: '2026-01-02 15:30' },
      { key: 'sign_time', label: '签收时间', sampleValue: '2026-01-01 18:00' },
      { key: 'complete_time', label: '完成时间', sampleValue: '2026-01-03 10:00' },
      { key: 'pay_time', label: '打款时间', sampleValue: '2026-01-03 14:00' },
      { key: 'current_time', label: '当前时间', sampleValue: '2026-01-01 12:00:00' },
      { key: 'current_date', label: '当前日期', sampleValue: '2026-01-01' },
      { key: 'date', label: '日期', sampleValue: '2026-01-01' },
      { key: 'time', label: '时间', sampleValue: '12:00:00' },
    ]
  },
  {
    label: '其他信息',
    items: [
      { key: 'remark', label: '备注', sampleValue: '无' },
      { key: 'member_id', label: '会员ID', sampleValue: '10001' },
      { key: 'site_name', label: '站点名称', sampleValue: '回收中心' },
      { key: 'staff_name', label: '员工姓名', sampleValue: '李四' },
    ]
  }
]

// 构建示例数据映射
export const SAMPLE_DATA: Record<string, string> = {}
VARIABLE_GROUPS.forEach(g => g.items.forEach(v => { SAMPLE_DATA[v.key] = v.sampleValue }))

// 纸张预设
export const PAPER_PRESETS: Record<string, { name: string; width: number; height: number }> = {
  '40x30': { name: '40×30mm', width: 40, height: 30 },
  '50x30': { name: '50×30mm', width: 50, height: 30 },
  '58x40': { name: '58×40mm', width: 58, height: 40 },
  '60x45': { name: '60×45mm', width: 60, height: 45 },
  '80x50': { name: '80×50mm', width: 80, height: 50 },
  '100x50': { name: '100×50mm', width: 100, height: 50 },
}

// 单位转换工具
export const Units = {
  mmToDot: (mm: number) => mm * 8,
  dotToMm: (dot: number) => dot / 8,
  dotToPx: (dot: number) => dot * 0.5,
  pxToDot: (px: number) => px / 0.5,
  mmToPx: (mm: number) => mm * 4,
  pxToMm: (px: number) => px / 4,
}

// 用示例数据替换变量
export function replaceVariables(text: string): string {
  if (!text) return ''
  return text.replace(/\{\{(\w+)\}\}/g, (_, key) => SAMPLE_DATA[key] || `{{${key}}}`)
}

// 获取字体信息
export function getFontDef(fontId: number): FontDef {
  return FONT_LIST.find(f => f.id === fontId) || FONT_LIST[8] // 默认 font 9
}

// 生成唯一 ID
export function genId(): string {
  return 'el_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 6)
}
