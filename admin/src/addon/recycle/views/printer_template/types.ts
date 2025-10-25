export interface TemplateElement {
  id: string
  type: 'TEXT' | 'BC128'
  key: string
  x: number
  y: number
  w: number
  h: number
  r: number
  content: string
  // 条形码特有属性
  s?: number
  n?: number
}

export interface DragData {
  type: string
  key: string
  label: string
}

export interface VariableItem {
  key: string
  label: string
  type: 'TEXT' | 'BC128'
} 