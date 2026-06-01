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
  sample_value?: string
}

// 构建示例数据映射（从动态变量列表）
export function buildSampleData(groups: VariableGroup[]): Record<string, string> {
  const data: Record<string, string> = {}
  groups.forEach(g => g.items.forEach(v => { data[v.key] = v.sampleValue || v.sample_value || '' }))
  return data
}

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

// 获取字体信息
export function getFontDef(fontId: number): FontDef {
  return FONT_LIST.find(f => f.id === fontId) || FONT_LIST[8] // 默认 font 9
}

// 生成唯一 ID
export function genId(): string {
  return 'el_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 6)
}
