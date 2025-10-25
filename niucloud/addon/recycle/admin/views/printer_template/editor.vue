<template>
  <div class="xml-editor-container">
    <!-- 顶部工具栏 -->
    <div class="editor-toolbar bg-white border-b border-gray-200 px-4 py-3 flex items-center justify-between">
      <div class="flex items-center space-x-4">
        <el-button @click="goBack" icon="ArrowLeft" size="small">
          返回
        </el-button>
        <el-divider direction="vertical" />
        <h2 class="text-lg font-medium">
          {{ isEdit ? '编辑模板' : '新建模板' }}
        </h2>
        <el-tag v-if="templateInfo.template_name" type="info" size="small">
          {{ templateInfo.template_name }}
        </el-tag>
      </div>
      
      <div class="flex items-center space-x-2">
        <el-button @click="formatXML" icon="Promotion" size="small">
          格式化XML
        </el-button>
        <el-button @click="validateXML" icon="CircleCheck" size="small" type="info">
          验证XML
        </el-button>
        <el-button @click="previewTemplate" icon="View" size="small" :loading="previewing">
          预览
        </el-button>
        <el-button @click="testPrint" icon="Printer" size="small" type="warning" :loading="testing">
          测试打印
        </el-button>
        <el-button @click="showSaveDialog" icon="Document" size="small" type="primary" :loading="saving">
          保存模板
        </el-button>
      </div>
    </div>

    <!-- 主编辑区域 -->
    <div class="editor-content flex h-full">
      <!-- 左侧：基本信息和XML编辑器 -->
      <div class="editor-main flex-1 p-4 overflow-y-auto">
        <!-- 模板基本信息 -->
        <div class="template-info bg-white rounded-lg border border-gray-200 p-4 mb-4">
          <h3 class="text-sm font-medium text-gray-700 mb-3">模板信息</h3>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-gray-700 mb-1">模板名称</label>
              <el-input 
                v-model="templateInfo.template_name" 
                placeholder="请输入模板名称" 
                size="small"
                :disabled="!isEdit && !isNew"
              />
            </div>
            
            <div class="col-span-2">
              <label class="block text-xs font-medium text-gray-700 mb-1">模板描述</label>
              <el-input 
                v-model="templateInfo.description" 
                type="textarea" 
                :rows="2"
                placeholder="请输入模板描述" 
                size="small"
                :disabled="!isEdit && !isNew"
              />
            </div>
          </div>
        </div>

        <!-- XML编辑器 -->
        <div class="xml-editor bg-white rounded-lg border border-gray-200 p-4">
          <div class="flex items-center justify-between mb-3">
            <label class="block text-sm font-medium text-gray-700">XML模板内容</label>
            <div class="text-xs text-gray-500">
              行数: {{ lineCount }} | 字符数: {{ charCount }}
            </div>
          </div>
          
          <div class="relative">
            <el-input
              v-model="xmlContent"
              type="textarea"
              :rows="20"
              placeholder="请输入XML模板内容..."
              class="xml-textarea"
              @input="handleInput"
              @blur="autoFormat"
            />
            
            <!-- 代码行号 -->
            <div class="line-numbers absolute left-2 top-2 text-xs text-gray-400 pointer-events-none select-none">
              <div v-for="n in lineCount" :key="n" class="line-number">
                {{ n }}
              </div>
            </div>
          </div>

          <!-- 快速插入按钮 -->
          <div class="quick-insert mt-4">
            <div class="text-sm font-medium text-gray-700 mb-2">快速插入变量</div>
            <div class="flex flex-wrap gap-2">
              <el-button
                v-for="variable in quickVariables"
                :key="variable.key"
                @click="insertVariable(variable)"
                size="small"
                plain
                :icon="variable.isBarcode ? 'Printer' : 'Document'"
              >
                {{ variable.label }}
              </el-button>
            </div>
          </div>
        </div>
      </div>

      <!-- 右侧：预览和帮助 -->
      <div class="editor-sidebar w-80 bg-gray-50 border-l border-gray-200 p-4 overflow-y-auto">
        <!-- 实时预览 -->
        <div class="preview-section mb-6">
          <h3 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
            <el-icon class="mr-1"><View /></el-icon>
            实时预览
          </h3>
          <div class="preview-content bg-white border border-gray-200 rounded p-4 text-xs font-mono">
            <pre v-if="formattedXML" class="whitespace-pre-wrap text-gray-800">{{ formattedXML }}</pre>
            <div v-else class="text-gray-400 text-center py-8">输入XML内容后显示预览</div>
          </div>
        </div>

        <!-- XML语法帮助 -->
        <div class="help-section">
          <h3 class="text-sm font-medium text-gray-700 mb-2 flex items-center">
            <el-icon class="mr-1"><QuestionFilled /></el-icon>
            语法帮助
          </h3>
          <div class="help-content bg-white border border-gray-200 rounded p-4 text-xs space-y-3">
            <div>
              <div class="font-medium text-gray-800 mb-1">基本结构:</div>
              <div class="block bg-gray-100 p-2 rounded text-xs" v-html="exampleTemplate"></div>
            </div>
            
            <div>
              <div class="font-medium text-gray-800 mb-1">可用变量:</div>
              <div class="space-y-1">
                <div v-for="variable in availableVariables" :key="variable.key" class="text-gray-600">
                  <code>{{ variable.key }}</code> - {{ variable.description }}
                </div>
              </div>
            </div>

            <div>
              <div class="font-medium text-gray-800 mb-1">标签说明:</div>
              <div class="space-y-1 text-gray-600">
                <div><code>SIZE</code> - 纸张尺寸(宽,高)mm</div>
                <div><code>TEXT</code> - 文本元素，属性：x,y,w,h,r</div>
                <div><code>BC128</code> - 条形码，属性：x,y,h,s,n,w,r</div>
                <div><code>QR</code> - 二维码，属性：x,y,w,h,r</div>
              </div>
            </div>

            <div>
              <div class="font-medium text-gray-800 mb-1">属性说明:</div>
              <div class="space-y-1 text-gray-600 text-xs">
                <div><code>x,y</code> - 位置坐标(像素)</div>
                <div><code>w,h</code> - 宽度高度倍数</div>
                <div><code>r</code> - 旋转角度(0,90,180,270)</div>
                <div><code>s</code> - 条码宽度倍数</div>
                <div><code>n</code> - 是否显示文字(0/1)</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 保存对话框 -->
    <el-dialog v-model="saveDialogVisible" title="保存模板" width="400px">
      <el-form :model="templateInfo" label-width="80px">
        <el-form-item label="模板名称" required>
          <el-input v-model="templateInfo.template_name" placeholder="请输入模板名称" />
        </el-form-item>

        <el-form-item label="模板描述">
          <el-input v-model="templateInfo.description" type="textarea" :rows="3" placeholder="请输入模板描述" />
        </el-form-item>
        <el-form-item label="设为默认">
          <el-switch v-model="templateInfo.is_default" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="saveDialogVisible = false">取消</el-button>
        <el-button type="primary" @click="saveTemplate" :loading="saving">保存</el-button>
      </template>
    </el-dialog>

    <!-- 预览对话框 -->
    <el-dialog v-model="previewDialogVisible" title="模板预览" width="600px">
      <div class="preview-container bg-gray-100 p-4 rounded">
        <div v-if="previewContent" v-html="previewContent" class="preview-result"></div>
        <div v-else class="text-center text-gray-500 py-8">暂无预览内容</div>
      </div>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, nextTick } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { View, QuestionFilled } from '@element-plus/icons-vue'
import { 
  getTemplateInfo, 
  addTemplate, 
  editTemplate, 
  getTemplateTypeList,
  previewTemplate as previewTemplateAPI,
  testPrintTemplate
} from '../../api/printer_template'

const router = useRouter()
const route = useRoute()

// 响应式数据
const xmlContent = ref('')
const saving = ref(false)
const previewing = ref(false)
const testing = ref(false)
const templateId = ref(route.params.id as string || '')
const isEdit = computed(() => !!templateId.value && templateId.value !== 'new')
const isNew = computed(() => !templateId.value || templateId.value === 'new')
const saveDialogVisible = ref(false)
const previewDialogVisible = ref(false)
const previewContent = ref('')

// 模板信息
const templateInfo = ref({
  template_name: '',
  template_type: 'device_label',
  description: '',
  is_default: false,
  content: ''
})

// 模板类型列表
const templateTypes = ref([
  { label: '回收订单', value: 'recycle_order' },
  { label: '设备标签', value: 'device_label' },
  { label: '检测报告', value: 'check_report' }
])

// 计算属性
const lineCount = computed(() => {
  return xmlContent.value.split('\n').length
})

const charCount = computed(() => {
  return xmlContent.value.length
})

const formattedXML = computed(() => {
  if (!xmlContent.value.trim()) return ''
  try {
    return formatXMLString(xmlContent.value)
  } catch (error) {
    return xmlContent.value
  }
})

const exampleTemplate = computed(() => {
  return `&lt;PAGE&gt;<br/>
&nbsp;&nbsp;&lt;SIZE&gt;58,40&lt;/SIZE&gt;<br/>
&nbsp;&nbsp;&lt;TEXT x="4" y="12"&gt;{{变量}}&lt;/TEXT&gt;<br/>
&lt;/PAGE&gt;`
})

// 快速插入变量
const quickVariables = ref([
  { key: 'device_id', label: '设备ID' },
  { key: 'model', label: '设备型号' },
  { key: 'imei', label: 'IMEI', isBarcode: true },
  { key: 'order_no', label: '订单号' },
  { key: 'price_staff_name', label: '询价员' },
  { key: 'check_date', label: '检测日期' },
  { key: 'check_staff', label: '检测员' },
  { key: 'check_result', label: '检测结果' },
  { key: 'total_price', label: '总价格' },
  { key: 'create_time', label: '创建时间' }
])

// 可用变量说明
const availableVariables = ref([
  { key: 'device_id', description: '设备ID编号' },
  { key: 'model', description: '设备型号' },
  { key: 'imei', description: 'IMEI串号' },
  { key: 'order_no', description: '订单编号' },
  { key: 'price_staff_name', description: '询价员姓名' },
  { key: 'check_date', description: '检测日期' },
  { key: 'check_staff', description: '检测员姓名' },
  { key: 'check_result', description: '检测结果' },
  { key: 'total_price', description: '总价格' },
  { key: 'create_time', description: '创建时间' }
])

// 默认模板
const defaultTemplate = `<PAGE>
  <SIZE>58,40</SIZE>
  <TEXT x="4" y="12" w="1" h="1" r="0">{{device_id}}</TEXT>
  <TEXT x="4" y="36" w="1" h="1" r="0">{{model}}</TEXT>
  <TEXT x="4" y="60" w="1" h="1" r="0">{{check_date}}</TEXT>
  <TEXT x="4" y="84" w="1" h="1" r="0">{{check_staff}}</TEXT>
  <BC128 x="4" y="108" h="32" s="1" n="1" w="2" r="0">{{imei}}</BC128>
  <TEXT x="4" y="156" w="1" h="1" r="0">{{check_result}}</TEXT>
</PAGE>`

// 方法
const goBack = () => {
  router.go(-1)
}

const handleInput = () => {
  // 实时更新内容
  templateInfo.value.content = xmlContent.value
}

const autoFormat = () => {
  if (xmlContent.value.trim()) {
    try {
      xmlContent.value = formatXMLString(xmlContent.value)
    } catch (error) {
      // 静默处理格式化错误
    }
  }
}

const formatXML = () => {
  try {
    xmlContent.value = formatXMLString(xmlContent.value)
    ElMessage.success('XML格式化成功')
  } catch (error) {
    ElMessage.error('XML格式化失败，请检查语法')
  }
}

const formatXMLString = (xml: string): string => {
  if (!xml.trim()) return xml
  
  try {
    const parser = new DOMParser()
    const doc = parser.parseFromString(xml, 'text/xml')
    
    if (doc.documentElement.nodeName === 'parsererror') {
      throw new Error('XML语法错误')
    }
    
    return formatNode(doc.documentElement, 0)
  } catch (error) {
    throw error
  }
}

const formatNode = (node: Element, indent: number): string => {
  const indentStr = '  '.repeat(indent)
  let result = `${indentStr}<${node.nodeName}`
  
  // 添加属性
  for (let i = 0; i < node.attributes.length; i++) {
    const attr = node.attributes[i]
    result += ` ${attr.name}="${attr.value}"`
  }
  
  // 处理子节点
  const textContent = node.textContent?.trim() || ''
  const hasOnlyText = node.children.length === 0 && textContent
  
  if (hasOnlyText) {
    result += `>${textContent}</${node.nodeName}>`
  } else if (node.children.length === 0) {
    result += '/>'
  } else {
    result += '>\n'
    
    for (let i = 0; i < node.children.length; i++) {
      result += formatNode(node.children[i], indent + 1) + '\n'
    }
    
    result += `${indentStr}</${node.nodeName}>`
  }
  
  return result
}

const validateXML = () => {
  if (!xmlContent.value.trim()) {
    ElMessage.warning('请输入XML内容')
    return false
  }
  
  try {
    const parser = new DOMParser()
    const doc = parser.parseFromString(xmlContent.value, 'text/xml')
    
    if (doc.documentElement.nodeName === 'parsererror') {
      throw new Error('XML语法错误')
    }
    
    // 检查是否有PAGE根元素
    if (doc.documentElement.nodeName !== 'PAGE') {
      throw new Error('根元素必须是PAGE')
    }
    
    ElMessage.success('XML验证通过')
    return true
  } catch (error) {
    ElMessage.error('XML验证失败: ' + (error as Error).message)
    return false
  }
}

const insertVariable = (variable: any) => {
  const textarea = document.querySelector('.xml-textarea textarea') as HTMLTextAreaElement
  if (!textarea) return
  
  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const before = xmlContent.value.substring(0, start)
  const after = xmlContent.value.substring(end)
  
  let insertText = ''
  if (variable.isBarcode) {
    insertText = `<BC128 x="4" y="12" h="32" s="1" n="1" w="2" r="0">{{${variable.key}}}</BC128>`
  } else {
    insertText = `<TEXT x="4" y="12" w="1" h="1" r="0">{{${variable.key}}}</TEXT>`
  }
  
  xmlContent.value = before + insertText + after
  
  nextTick(() => {
    textarea.focus()
    textarea.setSelectionRange(start + insertText.length, start + insertText.length)
  })
}

const loadTemplateData = async () => {
  if (!isEdit.value) return
  
  try {
    const response = await getTemplateInfo(Number(templateId.value))
    const data = response.data
    
    templateInfo.value = {
      template_name: data.template_name || '',
      template_type: data.template_type || 'device_label',
      description: data.description || '',
      is_default: data.is_default || false,
      content: data.content || ''
    }
    
    xmlContent.value = data.content || defaultTemplate
    
    ElMessage.success('模板加载成功')
  } catch (error) {
    ElMessage.error('模板加载失败')
    console.error('Load template error:', error)
  }
}

const loadTemplateTypes = async () => {
  try {
    const response = await getTemplateTypeList()
    if (response.data && Array.isArray(response.data)) {
      templateTypes.value = response.data
    }
  } catch (error) {
    console.error('Load template types error:', error)
  }
}

const showSaveDialog = () => {
  if (!validateXML()) return
  
  templateInfo.value.content = xmlContent.value
  saveDialogVisible.value = true
}

const saveTemplate = async () => {
  if (!templateInfo.value.template_name) {
    ElMessage.warning('请输入模板名称')
    return
  }
  
  if (!templateInfo.value.template_type) {
    ElMessage.warning('请选择模板类型')
    return
  }
  
  if (!xmlContent.value.trim()) {
    ElMessage.warning('请输入模板内容')
    return
  }
  
  if (!validateXML()) return
  
  try {
    saving.value = true
    templateInfo.value.content = xmlContent.value
    
    let response
    if (isEdit.value) {
      response = await editTemplate(Number(templateId.value), templateInfo.value)
    } else {
      response = await addTemplate(templateInfo.value)
    }
    
    ElMessage.success('模板保存成功')
    saveDialogVisible.value = false
    
    // 如果是新建，跳转到编辑页面
    if (isNew.value && response.data?.id) {
      router.replace(`/addon/recycle/printer_template/editor/${response.data.id}`)
    }
    
  } catch (error) {
    ElMessage.error('模板保存失败')
    console.error('Save template error:', error)
  } finally {
    saving.value = false
  }
}

const previewTemplate = async () => {
  if (!validateXML()) return
  
  try {
    previewing.value = true
    
    if (isEdit.value) {
      const response = await previewTemplateAPI(Number(templateId.value))
      previewContent.value = response.data || ''
    } else {
      // 新建模板的预览逻辑
      previewContent.value = `<div class="template-preview">${formatXMLString(xmlContent.value)}</div>`
    }
    
    previewDialogVisible.value = true
    
  } catch (error) {
    ElMessage.error('预览失败')
    console.error('Preview error:', error)
  } finally {
    previewing.value = false
  }
}

const testPrint = async () => {
  if (!isEdit.value) {
    ElMessage.warning('请先保存模板再进行测试打印')
    return
  }
  
  if (!validateXML()) return
  
  try {
    testing.value = true
    
    const testData = {
      device_id: 'TEST001',
      model: '测试设备',
      imei: '123456789012345',
      order_no: 'ORDER001',
      price_staff_name: '测试员',
      check_date: '2024-01-01',
      check_staff: '检测员',
      check_result: '良好',
      total_price: '1000.00',
      create_time: '2024-01-01 12:00:00'
    }
    
    await testPrintTemplate(Number(templateId.value), testData)
    ElMessage.success('测试打印已发送到打印机')
    
  } catch (error) {
    ElMessage.error('测试打印失败')
    console.error('Test print error:', error)
  } finally {
    testing.value = false
  }
}

onMounted(async () => {
  // 加载模板类型
  await loadTemplateTypes()
  
  // 如果有模板ID，加载现有模板
  if (isEdit.value) {
    await loadTemplateData()
  } else {
    // 新建模板，使用默认模板
    xmlContent.value = defaultTemplate
    templateInfo.value.content = defaultTemplate
  }
})
</script>

<style scoped>
.xml-editor-container {
  @apply h-screen flex flex-col bg-gray-50;
}

.editor-content {
  @apply flex-1 overflow-hidden;
}

.xml-textarea :deep(.el-textarea__inner) {
  @apply font-mono text-sm leading-relaxed pl-12;
  @apply border-gray-300 focus:border-blue-500;
  resize: none;
}

.line-numbers {
  line-height: 1.5;
  padding-top: 11px;
}

.line-number {
  @apply text-right pr-2;
  height: 21px;
}

.preview-content {
  max-height: 300px;
  overflow-y: auto;
}

.help-content {
  max-height: 400px;
  overflow-y: auto;
}

.template-info {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.xml-editor {
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

code {
  @apply bg-gray-100 px-1 py-0.5 rounded text-xs font-mono;
}

.preview-container {
  max-height: 500px;
  overflow-y: auto;
}

.preview-result {
  @apply font-mono text-sm;
}
</style> 