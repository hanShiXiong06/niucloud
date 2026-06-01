<template>
  <div class="visual-editor-container">
    <!-- 顶部工具栏 -->
    <div class="editor-toolbar">
      <div class="toolbar-left">
        <el-button @click="goBack" :icon="ArrowLeft" size="small">返回</el-button>
        <el-divider direction="vertical" />
        <h2 class="toolbar-title">{{ isEdit ? '编辑模板' : '新建模板' }}</h2>
        <el-tag v-if="templateInfo.template_name" type="info" size="small">{{ templateInfo.template_name }}</el-tag>
      </div>
      <div class="toolbar-right">
        <el-select v-model="paperSize" @change="handlePaperSizeChange" size="small" style="width:150px">
          <el-option label="自定义" value="custom" />
          <el-option v-for="(s, k) in PAPER_PRESETS" :key="k" :label="s.name" :value="k" />
        </el-select>
        <template v-if="paperSize === 'custom'">
          <span class="text-muted">宽</span>
          <el-input-number v-model="templateData.width" :min="20" :max="200" size="small" style="width:90px" @change="onCanvasSizeChange" />
          <span class="text-muted">高</span>
          <el-input-number v-model="templateData.height" :min="20" :max="200" size="small" style="width:90px" @change="onCanvasSizeChange" />
          <span class="text-muted">mm</span>
        </template>
        <el-button @click="testPrint" v-if="isEdit" :icon="Printer" size="small" type="warning" :loading="testing">测试打印</el-button>
        <el-button @click="goPrintScene" :icon="Setting" size="small">打印场景</el-button>
        <el-button @click="showSaveDialog" :icon="Document" size="small" type="primary" :loading="saving">保存模板</el-button>
      </div>
    </div>

    <!-- 主编辑区域 -->
    <div class="editor-main">
      <!-- 左侧工具栏 -->
      <div class="panel-left">
        <div class="panel-section">
          <div class="panel-title">元素工具</div>
          <div class="tool-grid">
            <div class="tool-btn" @click="addElement('text')"><el-icon><EditPen /></el-icon><span>文本</span></div>
            <div class="tool-btn" @click="addElement('qrcode')"><el-icon><Grid /></el-icon><span>二维码</span></div>
            <div class="tool-btn" @click="addElement('barcode')"><el-icon><Minus /></el-icon><span>条形码</span></div>
            <div class="tool-btn" @click="addElement('line')"><el-icon><SemiSelect /></el-icon><span>线条</span></div>
            <div class="tool-btn" @click="addElement('rectangle')"><el-icon><FullScreen /></el-icon><span>矩形</span></div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-title">配置流程</div>
          <div class="flow-steps">
            <div><strong>1</strong><span>设计标签内容和尺寸</span></div>
            <div><strong>2</strong><span>保存为对应模板类型</span></div>
            <div><strong>3</strong><span>到打印场景选择模板、打印机和份数</span></div>
            <div><strong>4</strong><span>测试打印后启用业务场景</span></div>
          </div>
        </div>

        <div class="panel-section">
          <div class="panel-title">变量列表</div>
          <div v-for="group in variableGroups" :key="group.label" class="var-group">
            <div class="var-group-label">{{ group.label }}</div>
            <div class="var-items">
              <el-tag
                v-for="v in group.items" :key="v.key"
                size="small" class="var-tag" effect="plain"
                @click="insertVariable(v.key)"
                :title="'{{' + v.key + '}} → ' + v.sampleValue"
              >{{ v.label }}</el-tag>
            </div>
          </div>
        </div>
      </div>

      <!-- 画布区域 -->
      <div class="panel-canvas" ref="canvasWrapRef">
        <div class="canvas-scroll">
          <div
            class="canvas-paper"
            :style="canvasStyle"
            @mousedown.self="onCanvasMouseDown"
            ref="canvasRef"
          >
            <!-- 网格背景 -->
            <svg class="canvas-grid" :width="canvasW" :height="canvasH">
              <defs>
                <pattern id="grid" :width="Units.mmToPx(1)" :height="Units.mmToPx(1)" patternUnits="userSpaceOnUse">
                  <path :d="`M ${Units.mmToPx(1)} 0 L 0 0 0 ${Units.mmToPx(1)}`" fill="none" stroke="#e5e7eb" stroke-width="0.5"/>
                </pattern>
                <pattern id="grid5" :width="Units.mmToPx(5)" :height="Units.mmToPx(5)" patternUnits="userSpaceOnUse">
                  <rect :width="Units.mmToPx(5)" :height="Units.mmToPx(5)" fill="url(#grid)"/>
                  <path :d="`M ${Units.mmToPx(5)} 0 L 0 0 0 ${Units.mmToPx(5)}`" fill="none" stroke="#d1d5db" stroke-width="0.8"/>
                </pattern>
              </defs>
              <rect width="100%" height="100%" fill="url(#grid5)" />
            </svg>

            <!-- 元素渲染 -->
            <div
              v-for="el in templateData.elements" :key="el.id"
              class="canvas-element"
              :class="{ selected: selectedId === el.id }"
              :style="getElementStyle(el)"
              @mousedown.stop="onElementMouseDown($event, el)"
              >
                <div
                  v-if="el.type === 'qrcode'"
                  class="qr-safe-zone"
                  :style="getQrSafeZoneStyle(el)"
                ></div>
                <!-- 文本 -->
                <div v-if="el.type === 'text'" class="el-text" :style="getTextStyle(el)">
                <template v-if="shouldUsePrinterTextPreview(el)">
                  <span
                    v-for="(part, index) in getTextPreviewRuns(el)"
                    :key="index"
                    class="el-text-run"
                    :style="{ width: part.width + 'px' }"
                  >
                    <span class="el-text-run__inner" :style="part.innerStyle">{{ part.text }}</span>
                  </span>
                </template>
                <template v-else>{{ getDisplayText(el) }}</template>
              </div>
              <!-- 二维码 -->
              <canvas v-if="el.type === 'qrcode'" :ref="(r) => setQrRef(el.id, r)" class="el-qr"></canvas>
              <!-- 条形码 -->
              <svg v-if="el.type === 'barcode'" :ref="(r) => setBcRef(el.id, r)" class="el-barcode"></svg>
              <!-- 线条 -->
              <div v-if="el.type === 'line'" class="el-line" :style="getLineStyle(el)"></div>
              <!-- 矩形 -->
              <div v-if="el.type === 'rectangle'" class="el-rect" :style="getRectStyle(el)"></div>

              <!-- resize 手柄 -->
              <template v-if="selectedId === el.id">
                <div class="resize-handle nw" @mousedown.stop="onResizeStart($event, el, 'nw')"></div>
                <div class="resize-handle ne" @mousedown.stop="onResizeStart($event, el, 'ne')"></div>
                <div class="resize-handle sw" @mousedown.stop="onResizeStart($event, el, 'sw')"></div>
                <div class="resize-handle se" @mousedown.stop="onResizeStart($event, el, 'se')"></div>
              </template>
            </div>
          </div>
          <div class="canvas-info">宽 {{ templateData.width }}mm × 高 {{ templateData.height }}mm | {{ templateData.elements.length }} 个元素</div>
        </div>
      </div>

      <!-- 右侧属性面板 -->
      <div class="panel-right">
        <template v-if="selectedElement">
          <div class="panel-title">属性 - {{ typeLabel(selectedElement.type) }}</div>

          <el-form label-width="70px" size="small" class="prop-form">
            <el-form-item label="X (mm)">
              <el-input-number v-model="propX" :min="0" :step="0.5" :precision="1" @change="onPropXChange" />
            </el-form-item>
            <el-form-item label="Y (mm)">
              <el-input-number v-model="propY" :min="0" :step="0.5" :precision="1" @change="onPropYChange" />
            </el-form-item>

            <!-- 文本属性 -->
            <template v-if="selectedElement.type === 'text'">
              <el-form-item label="内容">
                <el-input v-model="selectedElement.content" type="textarea" :rows="3" @input="onContentChange" />
              </el-form-item>
              <el-form-item label="字体">
                <el-select v-model="selectedElement.font" @change="renderAll">
                  <el-option v-for="f in FONT_LIST" :key="f.id" :value="f.id" :label="`${f.id} - ${f.name} (${f.desc})`" />
                </el-select>
              </el-form-item>
              <el-form-item label="宽度倍率">
                <el-input-number v-model="selectedElement.width_scale" :min="1" :max="10" @change="renderAll" />
              </el-form-item>
              <el-form-item label="高度倍率">
                <el-input-number v-model="selectedElement.height_scale" :min="1" :max="10" @change="renderAll" />
              </el-form-item>
              <el-form-item label="旋转">
                <el-select v-model="selectedElement.rotation" @change="renderAll">
                  <el-option :value="0" label="0°" />
                  <el-option :value="90" label="90°" />
                  <el-option :value="180" label="180°" />
                  <el-option :value="270" label="270°" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 二维码属性 -->
            <template v-if="selectedElement.type === 'qrcode'">
              <el-form-item label="内容">
                <el-input v-model="selectedElement.content" type="textarea" :rows="2" @input="onContentChange" />
              </el-form-item>
              <el-form-item label="大小">
                <el-input-number v-model="selectedElement.size" :min="1" :max="10" @change="renderAll" />
              </el-form-item>
              <el-form-item label="纠错">
                <el-select v-model="selectedElement.error_level" @change="renderAll">
                  <el-option value="L" label="L - 低" />
                  <el-option value="M" label="M - 中" />
                  <el-option value="Q" label="Q - 较高" />
                  <el-option value="H" label="H - 高" />
                </el-select>
              </el-form-item>
              <el-form-item label="静区">
                <el-input-number v-model="selectedElement.quiet_zone" :min="0" :max="10" @change="renderAll" />
                <div class="prop-tip">二维码四周安全留白，建议保留 4 个模块，贴边或被文字侵入会影响扫码。</div>
              </el-form-item>
            </template>

            <!-- 条形码属性 -->
            <template v-if="selectedElement.type === 'barcode'">
              <el-form-item label="内容">
                <el-input v-model="selectedElement.content" type="textarea" :rows="2" @input="onContentChange" />
              </el-form-item>
              <el-form-item label="类型">
                <el-select v-model="selectedElement.barcode_type" @change="renderAll">
                  <el-option value="BC128" label="Code 128" />
                  <el-option value="BC39" label="Code 39" />
                </el-select>
              </el-form-item>
              <el-form-item label="高度(dot)">
                <el-input-number v-model="selectedElement.height" :min="20" :max="200" @change="renderAll" />
              </el-form-item>
              <el-form-item label="窄条宽">
                <el-input-number v-model="selectedElement.narrow_width" :min="1" :max="5" @change="renderAll" />
              </el-form-item>
              <el-form-item label="宽条宽">
                <el-input-number v-model="selectedElement.wide_width" :min="1" :max="10" @change="renderAll" />
              </el-form-item>
              <el-form-item label="显示文字">
                <el-switch v-model="selectedElement.human_readable" :active-value="1" :inactive-value="0" @change="renderAll" />
              </el-form-item>
              <el-form-item label="旋转">
                <el-select v-model="selectedElement.rotation" @change="renderAll">
                  <el-option :value="0" label="0°" />
                  <el-option :value="90" label="90°" />
                  <el-option :value="180" label="180°" />
                  <el-option :value="270" label="270°" />
                </el-select>
              </el-form-item>
            </template>

            <!-- 线条属性 -->
            <template v-if="selectedElement.type === 'line'">
              <el-form-item label="宽度(dot)">
                <el-input-number v-model="selectedElement.width" :min="1" :max="999" @change="renderAll" />
              </el-form-item>
              <el-form-item label="高度(dot)">
                <el-input-number v-model="selectedElement.height" :min="1" :max="999" @change="renderAll" />
              </el-form-item>
            </template>

            <!-- 矩形属性 -->
            <template v-if="selectedElement.type === 'rectangle'">
              <el-form-item label="终点X(mm)">
                <el-input-number v-model="propXe" :min="0" :step="0.5" :precision="1" @change="onPropXeChange" />
              </el-form-item>
              <el-form-item label="终点Y(mm)">
                <el-input-number v-model="propYe" :min="0" :step="0.5" :precision="1" @change="onPropYeChange" />
              </el-form-item>
              <el-form-item label="线宽">
                <el-input-number v-model="selectedElement.style" :min="1" :max="10" @change="renderAll" />
              </el-form-item>
            </template>
          </el-form>

          <div class="prop-actions">
            <el-button type="danger" size="small" @click="deleteElement" plain>删除元素</el-button>
          </div>
        </template>
        <div v-else class="panel-empty">点击画布上的元素进行编辑<br/>或从左侧添加新元素</div>
      </div>
    </div>

    <!-- 保存对话框 -->
    <el-dialog v-model="saveDialogVisible" title="保存模板" width="min(560px, calc(100vw - 32px))" :close-on-click-modal="false">
      <div class="save-guide">
        <div class="save-guide__title">模板只负责标签内容</div>
        <div class="save-guide__text">打印机、自动打印、打印份数请在“打印场景”中配置，避免同一规则在多个地方冲突。</div>
      </div>
      <el-form :model="saveForm" label-width="90px" size="default" class="save-form">
        <el-form-item label="模板名称" required>
          <el-input v-model="saveForm.template_name" placeholder="请输入模板名称" />
        </el-form-item>
        <el-form-item label="模板类型">
          <el-select v-model="saveForm.template_type" style="width:100%">
            <el-option value="device_label" label="设备标签" />
            <el-option value="order_receipt" label="订单小票" />
            <el-option value="consignment_receipt" label="代卖凭证" />
            <el-option value="return_label" label="退回标签" />
            <el-option value="custom" label="自定义模板" />
          </el-select>
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="saveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="handleSave" :loading="saving">保存</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, nextTick, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ElMessage } from 'element-plus'
import { ArrowLeft, EditPen, Grid, Minus, SemiSelect, FullScreen, Printer, Document, Setting } from '@element-plus/icons-vue'
import QRCode from 'qrcode'
import JsBarcode from 'jsbarcode'
import { getTemplateInfo, addTemplate, editTemplate, testPrintTemplate, getTemplateVariables } from '@/addon/hsx_recycle/api/printer_template'
import {
  type TemplateElement, type TemplateData, type VariableGroup,
  FONT_LIST, PAPER_PRESETS,
  Units, getFontDef, genId
} from './types'

const route = useRoute()
const router = useRouter()

// 状态
const isEdit = computed(() => !!(route.query.id || route.params.id))
const templateId = computed(() => Number(route.query.id || route.params.id) || 0)
const templateInfo = ref<Record<string, any>>({})
const saving = ref(false)
const testing = ref(false)
const saveDialogVisible = ref(false)
const selectedId = ref<string>('')
const canvasRef = ref<HTMLElement>()
const canvasWrapRef = ref<HTMLElement>()

// 动态变量列表
const variableGroups = ref<VariableGroup[]>([])
const sampleData = computed(() => {
  const data: Record<string, string> = {}
  variableGroups.value.forEach(g => g.items.forEach(v => { data[v.key] = v.sampleValue || v.sample_value || '' }))
  return data
})
const replaceVariables = (text: string): string => {
  if (!text) return ''
  return text.replace(/\{\{(\w+)\}\}/g, (_, key) => sampleData.value[key] || `{{${key}}}`)
}

// QR/Barcode ref 映射
const qrRefs: Record<string, HTMLCanvasElement> = {}
const bcRefs: Record<string, SVGElement> = {}
const HALF_WIDTH_PREVIEW_SCALE = 0.92

function setQrRef(id: string, el: any) { if (el) qrRefs[id] = el }
function setBcRef(id: string, el: any) { if (el) bcRefs[id] = el }

// 模板数据
const templateData = reactive<TemplateData>({
  width: 58,
  height: 40,
  copies: 1,
  margin: { top: 0, right: 0, bottom: 0, left: 0 },
  elements: []
})

// 纸张选择
const paperSize = ref('58x40')

// 保存表单
const saveForm = reactive({
  template_name: '',
  template_type: 'device_label'
})

// 画布尺寸 px
const canvasW = computed(() => Units.mmToPx(templateData.width))
const canvasH = computed(() => Units.mmToPx(templateData.height))
const canvasStyle = computed(() => ({
  width: canvasW.value + 'px',
  height: canvasH.value + 'px'
}))

// 选中元素
const selectedElement = computed(() => templateData.elements.find(e => e.id === selectedId.value))

// 属性面板 mm 值
const propX = computed({
  get: () => selectedElement.value ? +Units.dotToMm(selectedElement.value.x).toFixed(1) : 0,
  set: () => {}
})
const propY = computed({
  get: () => selectedElement.value ? +Units.dotToMm(selectedElement.value.y).toFixed(1) : 0,
  set: () => {}
})
const propXe = computed({
  get: () => selectedElement.value?.xe ? +Units.dotToMm(selectedElement.value.xe).toFixed(1) : 0,
  set: () => {}
})
const propYe = computed({
  get: () => selectedElement.value?.ye ? +Units.dotToMm(selectedElement.value.ye).toFixed(1) : 0,
  set: () => {}
})

function onPropXChange(val: number) { if (selectedElement.value) selectedElement.value.x = Units.mmToDot(val) }
function onPropYChange(val: number) { if (selectedElement.value) selectedElement.value.y = Units.mmToDot(val) }
function onPropXeChange(val: number) { if (selectedElement.value) selectedElement.value.xe = Units.mmToDot(val) }
function onPropYeChange(val: number) { if (selectedElement.value) selectedElement.value.ye = Units.mmToDot(val) }

function onContentChange() { nextTick(() => renderAll()) }

// 类型标签
function typeLabel(t: string) {
  const m: Record<string, string> = { text: '文本', qrcode: '二维码', barcode: '条形码', line: '线条', rectangle: '矩形', image: '图片' }
  return m[t] || t
}

// 纸张切换
function handlePaperSizeChange(key: string) {
  if (key !== 'custom') {
    const p = PAPER_PRESETS[key]
    templateData.width = p.width
    templateData.height = p.height
  }
}
function onCanvasSizeChange() {
  paperSize.value = 'custom'
}

// 添加元素
function addElement(type: string) {
  const id = genId()
  const base: TemplateElement = { id, type: type as any, x: 40, y: 40 }

  switch (type) {
    case 'text':
      Object.assign(base, { content: '示例文本', font: 9, width_scale: 1, height_scale: 1, rotation: 0 })
      break
    case 'qrcode':
      Object.assign(base, { content: '{{imei}}', size: 4, error_level: 'M', quiet_zone: 4 })
      break
    case 'barcode':
      Object.assign(base, { content: '{{imei}}', barcode_type: 'BC128', height: 60, narrow_width: 2, wide_width: 4, human_readable: 1, rotation: 0 })
      break
    case 'line':
      Object.assign(base, { width: 200, height: 2 })
      break
    case 'rectangle':
      Object.assign(base, { xe: 200, ye: 160, style: 2 })
      break
  }

  templateData.elements.push(base)
  selectedId.value = id
  nextTick(() => renderAll())
}

// 删除元素
function deleteElement() {
  const idx = templateData.elements.findIndex(e => e.id === selectedId.value)
  if (idx >= 0) {
    templateData.elements.splice(idx, 1)
    selectedId.value = ''
  }
}

// 插入变量
function insertVariable(key: string) {
  if (!selectedElement.value) {
    ElMessage.warning('请先选中一个文本/二维码/条形码元素')
    return
  }
  const el = selectedElement.value
  if (['text', 'qrcode', 'barcode'].includes(el.type)) {
    el.content = (el.content || '') + `{{${key}}}`
    nextTick(() => renderAll())
  }
}

// ========== 画布渲染 ==========

function getElementStyle(el: TemplateElement): Record<string, string> {
  const left = Units.dotToPx(el.x) + 'px'
  const top = Units.dotToPx(el.y) + 'px'
  return { position: 'absolute', left, top }
}

function getTextStyle(el: TemplateElement): Record<string, string> {
  const fd = getFontDef(el.font || 9)
  const ws = el.width_scale || 1
  const hs = el.height_scale || 1
  const fontSize = Units.dotToPx(fd.charHeight * hs)
  const usePrinterPreview = shouldUsePrinterTextPreview(el)
  const letterSpacing = !usePrinterPreview && ws > 1 ? Units.dotToPx(fd.charWidth * (ws - 1)) + 'px' : '0px'
  const transform = el.rotation ? `rotate(${el.rotation}deg)` : ''
  return {
    display: usePrinterPreview ? 'inline-flex' : 'inline-block',
    alignItems: 'flex-start',
    fontSize: fontSize + 'px',
    lineHeight: fontSize + 'px',
    fontFamily: el.font === 9 ? '"Songti SC","STSong","SimSun","宋体",monospace' : 'monospace',
    letterSpacing,
    whiteSpace: 'nowrap',
    transform,
    transformOrigin: 'top left'
  }
}

function getDisplayText(el: TemplateElement): string {
  return replaceVariables(el.content || '')
}

function shouldUsePrinterTextPreview(el: TemplateElement): boolean {
  return (el.font || 9) === 9
}

function isHalfWidthChar(char: string): boolean {
  const code = char.codePointAt(0) || 0
  return code <= 0x00ff || (code >= 0xff61 && code <= 0xffdc) || (code >= 0xffe8 && code <= 0xffee)
}

function getTextPreviewRuns(el: TemplateElement) {
  const fd = getFontDef(el.font || 9)
  const widthScale = el.width_scale || 1
  const runs: Array<{ text: string; width: number; innerStyle: Record<string, string> }> = []

  Array.from(getDisplayText(el)).forEach((char) => {
    const halfWidth = isHalfWidthChar(char)
    const last = runs[runs.length - 1]
    const width = Units.dotToPx((halfWidth ? fd.charWidth / 2 : fd.charWidth) * widthScale)
    const scaleX = (halfWidth ? HALF_WIDTH_PREVIEW_SCALE : 1) * widthScale

    if (last && last.innerStyle.transform === `scaleX(${scaleX})`) {
      last.text += char
      last.width += width
      return
    }

    runs.push({
      text: char,
      width,
      innerStyle: {
        transform: `scaleX(${scaleX})`,
        transformOrigin: 'left top'
      }
    })
  })

  return runs
}

function getLineStyle(el: TemplateElement): Record<string, string> {
  return {
    width: Units.dotToPx(el.width || 100) + 'px',
    height: Units.dotToPx(el.height || 2) + 'px',
    background: '#000'
  }
}

function getRectStyle(el: TemplateElement): Record<string, string> {
  const w = Units.dotToPx((el.xe || 0) - el.x)
  const h = Units.dotToPx((el.ye || 0) - el.y)
  const bw = (el.style || 1) + 'px'
  return {
    width: Math.abs(w) + 'px',
    height: Math.abs(h) + 'px',
    border: `${bw} solid #000`,
    boxSizing: 'border-box'
  }
}

// 渲染 QR 和条形码
async function renderAll() {
  await nextTick()
  for (const el of templateData.elements) {
    if (el.type === 'qrcode') await renderQr(el)
    if (el.type === 'barcode') renderBarcode(el)
  }
}

async function renderQr(el: TemplateElement) {
  const canvas = qrRefs[el.id]
  if (!canvas) return
  const text = replaceVariables(el.content || '') || 'SAMPLE'
  const errorLevel = (el.error_level || 'M') as any
  const pxSize = getQrPreviewSizePx(text, Number(el.size || 4), errorLevel)
  canvas.width = pxSize
  canvas.height = pxSize
  canvas.style.width = pxSize + 'px'
  canvas.style.height = pxSize + 'px'
  try {
    await QRCode.toCanvas(canvas, text, {
      width: pxSize,
      margin: 0,
      errorCorrectionLevel: errorLevel
    })
  } catch { /* ignore invalid content */ }
}

function getQrPreviewSizePx(text: string, size: number, errorLevel: string): number {
  const moduleCount = getQrModuleCount(text, errorLevel)
  const moduleSizeDot = Math.max(1, Math.min(10, Math.round(size || 4)))
  return Math.max(8, Math.round(Units.dotToPx(moduleCount * moduleSizeDot)))
}

function getQrSafeZoneStyle(el: TemplateElement): Record<string, string> {
  const text = replaceVariables(el.content || '') || 'SAMPLE'
  const errorLevel = (el.error_level || 'M') as any
  const moduleCount = getQrModuleCount(text, errorLevel)
  const moduleSizeDot = Math.max(1, Math.min(10, Math.round(el.size || 4)))
  const quietZone = Math.max(0, Math.min(10, Math.round(el.quiet_zone ?? 4)))
  const quietPx = Units.dotToPx(moduleSizeDot * quietZone)
  const qrPx = Units.dotToPx(moduleCount * moduleSizeDot)
  return {
    position: 'absolute',
    left: -quietPx + 'px',
    top: -quietPx + 'px',
    width: qrPx + quietPx * 2 + 'px',
    height: qrPx + quietPx * 2 + 'px'
  }
}

function getQrModuleCount(text: string, errorLevel: string): number {
  try {
    const qr = (QRCode as any).create(text || 'SAMPLE', { errorCorrectionLevel: errorLevel })
    return Number(qr?.modules?.size || 21)
  } catch {
    return 21
  }
}

function renderBarcode(el: TemplateElement) {
  const svg = bcRefs[el.id]
  if (!svg) return
  const text = replaceVariables(el.content || '') || '0000000000'
  const h = Units.dotToPx(el.height || 60)
  try {
    JsBarcode(svg, text, {
      format: el.barcode_type === 'BC39' ? 'CODE39' : 'CODE128',
      height: h,
      width: (el.narrow_width || 2) * 0.5,
      displayValue: el.human_readable === 1,
      margin: 0,
      fontSize: 10
    })
    if (el.rotation) {
      svg.style.transform = `rotate(${el.rotation}deg)`
      svg.style.transformOrigin = 'top left'
    } else {
      svg.style.transform = ''
    }
  } catch { /* ignore */ }
}

// ========== 拖拽 ==========

let dragState: { el: TemplateElement; startX: number; startY: number; origX: number; origY: number } | null = null

function onElementMouseDown(e: MouseEvent, el: TemplateElement) {
  selectedId.value = el.id
  dragState = { el, startX: e.clientX, startY: e.clientY, origX: el.x, origY: el.y }
  document.addEventListener('mousemove', onDragMove)
  document.addEventListener('mouseup', onDragEnd)
}

function onDragMove(e: MouseEvent) {
  if (!dragState) return
  const dx = (e.clientX - dragState.startX) / 0.5  // px → dot
  const dy = (e.clientY - dragState.startY) / 0.5
  dragState.el.x = Math.max(0, Math.round(dragState.origX + dx))
  dragState.el.y = Math.max(0, Math.round(dragState.origY + dy))
}

function onDragEnd() {
  dragState = null
  document.removeEventListener('mousemove', onDragMove)
  document.removeEventListener('mouseup', onDragEnd)
}

function onCanvasMouseDown() {
  selectedId.value = ''
}

// ========== Resize ==========

let resizeState: {
  el: TemplateElement; handle: string
  startX: number; startY: number
  origX: number; origY: number
  origW: number; origH: number
  origXe: number; origYe: number
  origSize: number; origHeight: number
  origWs: number; origHs: number
} | null = null

function onResizeStart(e: MouseEvent, el: TemplateElement, handle: string) {
  resizeState = {
    el, handle,
    startX: e.clientX, startY: e.clientY,
    origX: el.x, origY: el.y,
    origW: el.width || 0, origH: el.height || 0,
    origXe: el.xe || 0, origYe: el.ye || 0,
    origSize: el.size || 4,
    origWs: el.width_scale || 1, origHs: el.height_scale || 1
  }
  document.addEventListener('mousemove', onResizeMove)
  document.addEventListener('mouseup', onResizeEnd)
}

function onResizeMove(e: MouseEvent) {
  if (!resizeState) return
  const { el, handle, startX, startY } = resizeState
  const dxPx = e.clientX - startX
  const dyPx = e.clientY - startY
  const dxDot = dxPx / 0.5
  const dyDot = dyPx / 0.5

  if (el.type === 'text') {
    // 文本：调整宽高倍率
    if (handle.includes('e') || handle === 'se' || handle === 'ne') {
      el.width_scale = Math.max(1, Math.min(10, Math.round(resizeState.origWs + dxDot / 24)))
    }
    if (handle.includes('s') || handle === 'se' || handle === 'sw') {
      el.height_scale = Math.max(1, Math.min(10, Math.round(resizeState.origHs + dyDot / 24)))
    }
  } else if (el.type === 'qrcode') {
    const delta = Math.max(dxDot, dyDot)
    const moduleCount = getQrModuleCount(replaceVariables(el.content || '') || 'SAMPLE', el.error_level || 'M')
    el.size = Math.max(1, Math.min(10, Math.round(resizeState.origSize + delta / moduleCount)))
  } else if (el.type === 'barcode') {
    if (handle.includes('s') || handle.includes('n')) {
      el.height = Math.max(20, Math.round(resizeState.origH + dyDot))
    }
  } else if (el.type === 'line') {
    if (handle.includes('e')) el.width = Math.max(1, Math.round(resizeState.origW + dxDot))
    if (handle.includes('s')) el.height = Math.max(1, Math.round(resizeState.origH + dyDot))
  } else if (el.type === 'rectangle') {
    if (handle.includes('e')) el.xe = Math.max(el.x + 8, Math.round(resizeState.origXe + dxDot))
    if (handle.includes('s')) el.ye = Math.max(el.y + 8, Math.round(resizeState.origYe + dyDot))
  }

  renderAll()
}

function onResizeEnd() {
  resizeState = null
  document.removeEventListener('mousemove', onResizeMove)
  document.removeEventListener('mouseup', onResizeEnd)
}

// ========== 保存 ==========

function showSaveDialog() {
  saveForm.template_name = templateInfo.value.template_name || ''
  saveForm.template_type = templateInfo.value.template_type || 'device_label'
  saveDialogVisible.value = true
}

async function handleSave() {
  if (!saveForm.template_name) {
    ElMessage.warning('请输入模板名称')
    return
  }
  saving.value = true
  try {
    // 打印份数和打印机由打印场景控制，模板保存时只保存标签内容。
    templateData.copies = 1
    templateData.margin = { top: 0, right: 0, bottom: 0, left: 0 }

    const payload: Record<string, any> = {
      template_name: saveForm.template_name,
      template_type: saveForm.template_type,
      width: templateData.width,
      height: templateData.height,
      template_data: JSON.parse(JSON.stringify(templateData)),
      printer_id: 0,
      trigger_event: '',
      status: 1
    }

    if (isEdit.value) {
      await editTemplate(templateId.value, payload)
    } else {
      const res = await addTemplate(payload)
      if (res.data?.id) {
        router.replace({ query: { id: res.data.id } })
      }
    }
    saveDialogVisible.value = false
  } catch (e: any) {
    console.error('保存模板失败', e)
  } finally {
    saving.value = false
  }
}

// ========== 测试打印 ==========

async function testPrint() {
  if (!templateId.value) return
  testing.value = true
  try {
    await testPrintTemplate(templateId.value, {})
  } catch (e: any) {
    console.error('测试打印失败', e)
  } finally {
    testing.value = false
  }
}

// ========== 初始化 ==========

function goBack() {
  router.back()
}

function goPrintScene() {
  router.push('/recycle/print_scene/list')
}

async function loadVariables() {
  try {
    const res = await getTemplateVariables()
    const data = res.data || res || []
    variableGroups.value = Array.isArray(data) ? data.map((g: any) => ({
      label: g.label,
      items: (g.items || []).map((v: any) => ({
        key: v.key,
        label: v.label,
        sampleValue: v.sample_value || v.sampleValue || ''
      }))
    })) : []
  } catch (e) {
    variableGroups.value = []
  }
}

async function loadTemplate() {
  if (!templateId.value) return
  try {
    const res = await getTemplateInfo(templateId.value)
    const info = res.data || res
    templateInfo.value = info

    // 加载模板数据
    const td = info.template_data || info.content || {}
    if (td.width) templateData.width = td.width
    if (td.height) templateData.height = td.height
    if (td.copies) templateData.copies = td.copies
    if (td.margin) templateData.margin = { ...td.margin }
    if (td.elements?.length) {
      templateData.elements = td.elements.map((e: any) => ({ ...e, id: e.id || genId() }))
    }

    // 匹配纸张预设
    const matchKey = Object.keys(PAPER_PRESETS).find(k => {
      const p = PAPER_PRESETS[k]
      return p.width === templateData.width && p.height === templateData.height
    })
    paperSize.value = matchKey || 'custom'

    nextTick(() => renderAll())
  } catch (e: any) {
    console.error('加载模板失败', e)
  }
}

onMounted(() => {
  loadVariables()
  loadTemplate()
})

// 监听元素变化重新渲染 QR/Barcode
watch(() => templateData.elements.length, () => nextTick(() => renderAll()))
</script>

<style scoped>
.visual-editor-container {
  display: flex;
  flex-direction: column;
  height: calc(100vh - 120px);
  max-height: calc(100vh - 120px);
  box-sizing: border-box;
  overflow: hidden;
  background: #f5f5f5;
}

/* 顶部工具栏 */
.editor-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 16px;
  background: #fff;
  border-bottom: 1px solid #e5e7eb;
  flex-shrink: 0;
}
.toolbar-left, .toolbar-right {
  display: flex;
  align-items: center;
  gap: 8px;
}
.toolbar-title {
  font-size: 16px;
  font-weight: 500;
  margin: 0;
}
.text-muted { color: #999; }

/* 主编辑区域 */
.editor-main {
  display: flex;
  flex: 1;
  min-height: 0;
  overflow: hidden;
}

/* 左侧面板 */
.panel-left {
  width: 240px;
  background: #fff;
  border-right: 1px solid #e5e7eb;
  overflow-y: auto;
  flex-shrink: 0;
  padding: 12px;
  box-sizing: border-box;
  min-height: 0;
}
.panel-section { margin-bottom: 16px; }
.panel-title {
  font-size: 13px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
  padding-bottom: 4px;
  border-bottom: 1px solid #eee;
}

.tool-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 6px;
}
.tool-btn {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  padding: 10px 4px;
  border: 1px solid #e5e7eb;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
  color: #555;
  transition: all 0.15s;
}
.tool-btn:hover {
  border-color: #409eff;
  color: #409eff;
  background: #ecf5ff;
}

.flow-steps {
  display: grid;
  gap: 8px;
}

.flow-steps div {
  display: grid;
  grid-template-columns: 22px minmax(0, 1fr);
  align-items: start;
  gap: 8px;
  color: #606266;
  font-size: 12px;
  line-height: 1.5;
}

.flow-steps strong {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 20px;
  height: 20px;
  border-radius: 50%;
  background: #ecf5ff;
  color: #409eff;
  font-size: 12px;
}

.var-group { margin-bottom: 10px; }
.var-group-label {
  font-size: 12px;
  color: #999;
  margin-bottom: 4px;
}
.var-items { display: flex; flex-wrap: wrap; gap: 4px; }
.var-tag { cursor: pointer; }
.var-tag:hover { color: #409eff; border-color: #409eff; }

/* 画布区域 */
.panel-canvas {
  flex: 1;
  min-width: 0;
  min-height: 0;
  overflow: auto;
  display: flex;
  justify-content: center;
  padding: 24px;
  box-sizing: border-box;
}
.canvas-scroll {
  display: flex;
  flex-direction: column;
  align-items: center;
  min-width: max-content;
}
.canvas-paper {
  position: relative;
  background: #fff;
  box-shadow: 0 2px 12px rgba(0,0,0,0.12);
  border: 1px solid #ddd;
  overflow: hidden;
}
.canvas-grid {
  position: absolute;
  top: 0; left: 0;
  pointer-events: none;
}
.canvas-info {
  margin-top: 8px;
  font-size: 12px;
  color: #999;
  text-align: center;
}

/* 元素 */
.canvas-element {
  cursor: move;
  user-select: none;
}
.canvas-element.selected {
  outline: 2px solid #409eff;
  outline-offset: 1px;
}
.el-text {
  color: #000;
  pointer-events: none;
}
.el-text-run {
  display: inline-block;
  flex: 0 0 auto;
  min-width: 0;
  overflow: visible;
  white-space: pre;
}
.el-text-run__inner {
  display: inline-block;
  white-space: pre;
}
.el-qr, .el-barcode { display: block; pointer-events: none; }
.el-line { pointer-events: none; }
.el-rect { pointer-events: none; }
.qr-safe-zone {
  border: 1px dashed rgba(245, 108, 108, 0.75);
  background: rgba(245, 108, 108, 0.08);
  box-sizing: border-box;
  pointer-events: none;
}

.prop-tip {
  width: 100%;
  margin-top: 4px;
  color: #909399;
  font-size: 12px;
  line-height: 1.45;
}

/* Resize 手柄 */
.resize-handle {
  position: absolute;
  width: 8px;
  height: 8px;
  background: #409eff;
  border: 1px solid #fff;
  border-radius: 2px;
  z-index: 10;
}
.resize-handle.nw { top: -4px; left: -4px; cursor: nw-resize; }
.resize-handle.ne { top: -4px; right: -4px; cursor: ne-resize; }
.resize-handle.sw { bottom: -4px; left: -4px; cursor: sw-resize; }
.resize-handle.se { bottom: -4px; right: -4px; cursor: se-resize; }

/* 右侧属性面板 */
.panel-right {
  width: 300px;
  background: #fff;
  border-left: 1px solid #e5e7eb;
  overflow-y: auto;
  flex-shrink: 0;
  padding: 12px;
  box-sizing: border-box;
  min-height: 0;
}
.prop-form :deep(.el-form-item) { margin-bottom: 12px; }
.prop-form :deep(.el-input-number) { width: 100%; }
.prop-form :deep(.el-select) { width: 100%; }
.prop-actions {
  margin-top: 16px;
  padding-top: 12px;
  border-top: 1px solid #eee;
  text-align: center;
}
.panel-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 200px;
  color: #999;
  font-size: 13px;
  text-align: center;
  line-height: 1.8;
}

.save-guide {
  margin-bottom: 16px;
  padding: 12px;
  border: 1px solid #d9ecff;
  border-radius: 6px;
  background: #f4f8ff;
}

.save-guide__title {
  margin-bottom: 4px;
  color: #303133;
  font-weight: 600;
}

.save-guide__text {
  color: #606266;
  line-height: 1.6;
}

.save-form :deep(.el-select) {
  width: 100%;
}
</style>
