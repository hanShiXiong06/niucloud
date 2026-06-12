<template>
  <PremiumTheme class="model-dict-page">
    <el-card shadow="never">
      <div class="page-head">
        <div>
          <span class="text-page-title">设备分类</span>
          <div class="page-subtitle">按动态层级组织设备分类；签收时只选择最末级节点。</div>
        </div>
        <div class="page-actions">
          <el-button type="primary" plain @click="openImportDialog">导入数据</el-button>
          <el-button type="primary" plain @click="openQuickDialog">快速录入</el-button>
          <el-button type="primary" plain @click="openTemplateDialog()">通用模板</el-button>
          <el-button type="primary" @click="openCreateRoot">新增分类</el-button>
        </div>
      </div>

      <div class="toolbar">
        <el-input
          v-model="query.keyword"
          placeholder="搜索分类、路径或外部ID"
          clearable
          class="toolbar-keyword"
        />
        <el-select v-model="query.status" placeholder="状态" clearable class="toolbar-status">
          <el-option label="启用" :value="1" />
          <el-option label="停用" :value="0" />
        </el-select>
        <el-button type="primary" @click="loadTree">刷新</el-button>
      </div>

      <div v-loading="loading" class="virtual-tree-panel">
        <div class="tree-head">
          <span>名称</span>
          <span>完整路径</span>
          <span>可选择</span>
          <span>排序</span>
          <span>状态</span>
          <span class="tree-head__action">操作</span>
        </div>
        <el-empty v-if="!loading && !filteredTree.length" description="暂无分类，请先快速录入" />
        <el-tree-v2
          v-else
          :data="filteredTree"
          :props="treeProps"
          :height="treeHeight"
          :item-size="54"
          :expand-on-click-node="false"
          class="model-virtual-tree"
        >
          <template #default="{ data }">
            <div class="tree-row">
              <div class="tree-cell tree-cell--name">
                <el-tag size="small" :type="levelTagType(data.level)" effect="plain">{{ levelName(data.level) }}</el-tag>
                <span class="tree-node-name">{{ data.node_name }}</span>
                <el-tag v-if="isLeaf(data) && Number(data.is_hot || 0) === 1" size="small" type="danger" effect="plain">热门</el-tag>
              </div>
              <div class="tree-cell tree-cell--path">{{ data.model_full_name || data.node_name }}</div>
              <div class="tree-cell tree-cell--center">
                <el-tag v-if="isLeaf(data)" type="success" effect="plain">是</el-tag>
                <el-tag v-else type="info" effect="plain">结构</el-tag>
              </div>
              <div class="tree-cell tree-cell--center">{{ data.sort || 0 }}</div>
              <div class="tree-cell tree-cell--center">
                <el-tag :type="Number(data.status) === 1 ? 'success' : 'info'">
                  {{ Number(data.status) === 1 ? '启用' : '停用' }}
                </el-tag>
              </div>
              <div class="tree-cell tree-cell--actions">
                <el-button v-if="Number(data.level) < maxLevel" type="primary" link @click.stop="openCreateChild(data)">
                  添加下级
                </el-button>
                <el-button type="primary" link @click.stop="openTemplateDialog(data)">模板配置</el-button>
                <el-button type="primary" link @click.stop="openSortDialog(data)">排序</el-button>
                <el-button v-if="isLeaf(data)" type="primary" link @click.stop="openEdit(data)">编辑</el-button>
                <el-button type="danger" link :disabled="!isLeaf(data)" @click.stop="handleDelete(data)">
                  删除
                </el-button>
              </div>
            </div>
          </template>
        </el-tree-v2>
      </div>
    </el-card>

    <el-dialog v-model="editDialogVisible" :title="dialogTitle" width="560px">
      <el-form :model="editForm" label-width="90px">
        <el-form-item label="分类路径" required>
          <el-input
            v-model="editForm.path_text"
            placeholder="如：智能数码/智能手表/苹果/Ultra 系列/Apple Watch Ultra 2"
          />
        </el-form-item>
        <el-form-item label="排序">
          <el-input-number v-model="editForm.sort" :min="0" :controls="false" />
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="editForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="editDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="saveEdit">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="sortDialogVisible" title="调整分类排序" width="480px">
      <div class="sort-target">
        <div class="sort-target__label">当前节点</div>
        <div class="sort-target__name">{{ sortForm.path_text || '-' }}</div>
      </div>
      <el-alert
        type="warning"
        :closable="false"
        show-icon
        class="sort-alert"
        title="保存后会同步当前节点及所有下级节点排序，不会调整热门状态。"
      />
      <el-form :model="sortForm" label-width="90px">
        <el-form-item label="排序值">
          <el-input-number v-model="sortForm.sort" :controls="false" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="sortDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="sortSaving" @click="saveSort">保存</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="quickDialogVisible" title="快速录入设备分类" width="680px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="每行一个完整路径，支持多层级。重复数据会先提示，不会只创建一部分。"
      />
      <el-input
        v-model="quickContent"
        type="textarea"
        :rows="12"
        class="quick-textarea"
        placeholder="手机/苹果/iPhone 16 系列/iPhone 16 Pro Max&#10;智能数码/智能手表/苹果/Ultra 系列/Apple Watch Ultra 2&#10;智能数码/游戏机/任天堂/Switch 系列/Switch OLED"
      />
      <template #footer>
        <el-button @click="quickDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitQuickAdd">批量创建</el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="importDialogVisible" title="导入设备分类" width="760px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="支持 CSV、TSV 或 JSON。表头请包含：品类、品类ID、品牌、品牌ID、系列、型号、产品ID、热门。"
      />
      <el-form label-width="90px" class="import-form">
        <el-form-item label="数据来源">
          <el-input v-model="importSource" placeholder="如：recycle_spider" />
        </el-form-item>
        <el-form-item label="选择文件">
          <el-upload
            drag
            action="#"
            accept=".csv,.tsv,.json,application/json,text/csv,text/tab-separated-values"
            :auto-upload="false"
            :show-file-list="false"
            :on-change="handleImportFileChange"
            class="import-upload"
          >
            <div class="import-upload__main">
              <div class="import-upload__title">{{ importFileName || '点击或拖拽文件到这里' }}</div>
              <div class="import-upload__tip">CSV/TSV 使用首行作为表头；JSON 支持数组或 { rows: [] }</div>
            </div>
          </el-upload>
        </el-form-item>
      </el-form>

      <div v-if="importRows.length" class="import-preview">
        <div class="import-preview__head">
          <span>已识别 {{ importRows.length }} 行</span>
          <el-tag v-if="importRows.length > importPreviewLimit" type="info" effect="plain">
            仅预览前 {{ importPreviewLimit }} 行
          </el-tag>
        </div>
        <el-table :data="importRows.slice(0, importPreviewLimit)" size="small" height="260">
          <el-table-column prop="品类" label="品类" min-width="160" />
          <el-table-column prop="品类ID" label="品类ID" width="90" />
          <el-table-column prop="品牌" label="品牌" width="110" />
          <el-table-column prop="品牌ID" label="品牌ID" width="90" />
          <el-table-column prop="系列" label="系列" min-width="120" />
          <el-table-column prop="型号" label="型号" min-width="180" />
          <el-table-column prop="产品ID" label="产品ID" width="100" />
          <el-table-column prop="热门" label="热门" width="80" />
        </el-table>
      </div>

      <template #footer>
        <el-button @click="importDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="importing" :disabled="!importRows.length" @click="submitImport">
          确认导入
        </el-button>
      </template>
    </el-dialog>

    <el-dialog v-model="templateDialogVisible" title="模板绑定配置" width="720px">
      <div class="binding-summary">
        <div>
          <div class="binding-summary__label">配置对象</div>
          <div class="binding-summary__title">{{ templateTargetName }}</div>
        </div>
        <el-tag :type="templateBindingInfo.effective?.matched ? 'success' : 'info'" effect="plain">
          {{ templateBindingInfo.effective?.matched ? '已有生效规则' : '未配置规则' }}
        </el-tag>
      </div>

      <el-alert
        v-if="templateBindingInfo.effective?.matched"
        type="success"
        :closable="false"
        show-icon
        class="binding-effective"
        :title="`当前生效：${templateBindingInfo.effective.source_name || '通用兜底'}`"
        :description="effectiveTemplateDescription"
      />
      <el-alert
        v-else
        type="warning"
        :closable="false"
        show-icon
        class="binding-effective"
        title="当前没有可用绑定"
        description="打印会继续使用打印场景默认模板，质检会继续使用默认质检模板。"
      />

      <el-form :model="templateForm" label-width="110px" class="binding-form">
        <el-form-item label="质检模板">
          <el-select v-model="templateForm.check_template_id" clearable filterable placeholder="请选择质检模板">
            <el-option
              v-for="item in templateBindingInfo.check_template_options"
              :key="item.id"
              :label="checkTemplateLabel(item)"
              :value="item.id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="标签模板">
          <el-select v-model="templateForm.print_template_id" clearable filterable placeholder="请选择打印标签模板">
            <el-option
              v-for="item in templateBindingInfo.print_template_options"
              :key="item.template_id"
              :label="printTemplateLabel(item)"
              :value="item.template_id"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="允许下级继承">
          <el-switch v-model="templateForm.inherit_enabled" :active-value="1" :inactive-value="0" />
          <span class="form-tip">关闭后，下级分类不会继承当前规则。</span>
        </el-form-item>
        <el-form-item label="状态">
          <el-switch v-model="templateForm.status" :active-value="1" :inactive-value="0" />
        </el-form-item>
        <el-form-item label="备注">
          <el-input v-model="templateForm.remark" type="textarea" :rows="3" placeholder="如：iPhone 15 Pro 专用标签、平板通用质检模板" />
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="templateDialogVisible = false">取消</el-button>
        <el-button :disabled="!templateBindingInfo.binding?.id" @click="handleResetTemplateBinding">恢复继承</el-button>
        <el-button type="primary" :loading="templateSaving" @click="handleSaveTemplateBinding">保存</el-button>
      </template>
    </el-dialog>
  </PremiumTheme>
</template>

<script setup lang="ts">
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  addRecycleDeviceModelDict,
  deleteRecycleDeviceModelDict,
  editRecycleDeviceModelDict,
  getRecycleDeviceModelDictTree,
  getTemplateBindingInfo,
  importExternalRecycleDeviceModelDict,
  quickAddRecycleDeviceModelDict,
  resetTemplateBinding,
  saveTemplateBinding,
  updateRecycleDeviceModelDictSort,
} from '@/addon/hsx_recycle/api/recycle_device_model_dict'

const loading = ref(false)
const saving = ref(false)
const tree = ref<any[]>([])
const treeHeight = 620
const maxLevel = 8
const treeProps = {
  value: 'id',
  label: 'node_name',
  children: 'child_list',
}
const query = reactive({
  keyword: '',
  status: '',
})

const editDialogVisible = ref(false)
const quickDialogVisible = ref(false)
const importDialogVisible = ref(false)
const templateDialogVisible = ref(false)
const sortDialogVisible = ref(false)
const quickContent = ref('')
const importFileName = ref('')
const importRows = ref<any[]>([])
const importSource = ref('recycle_spider')
const importing = ref(false)
const importPreviewLimit = 20
const parentContext = ref<any>(null)
const templateTarget = ref<any>(null)
const templateSaving = ref(false)
const sortSaving = ref(false)
const templateBindingInfo = ref<any>({
  binding: {},
  effective: {},
  check_template_options: [],
  print_template_options: [],
})
const editForm = reactive<any>({
  id: 0,
  path_text: '',
  status: 1,
  sort: 0,
})
const sortForm = reactive<any>({
  id: 0,
  path_text: '',
  sort: 0,
})
const templateForm = reactive<any>({
  target_type: 'global',
  target_id: 0,
  scene_key: 'manual_device_label',
  check_template_id: '',
  print_template_id: '',
  inherit_enabled: 1,
  status: 1,
  remark: '',
  sort: 0,
})

const dialogTitle = computed(() => {
  if (editForm.id) return '编辑分类'
  if (parentContext.value) return '添加下级分类'
  return '新增分类'
})

const filteredTree = computed(() => {
  const keyword = query.keyword.trim().toLowerCase()
  const status = query.status
  const filterNodes = (nodes: any[]): any[] => {
    return nodes.reduce((result: any[], node: any) => {
      const children = filterNodes(Array.isArray(node.child_list) ? node.child_list : [])
      const matchedKeyword = !keyword
        || String(node.node_name || '').toLowerCase().includes(keyword)
        || String(node.model_full_name || '').toLowerCase().includes(keyword)
        || String(node.source_node_id || '').toLowerCase().includes(keyword)
      const matchedStatus = status === '' || status === null || Number(node.status) === Number(status)
      if ((matchedKeyword && matchedStatus) || children.length) {
        result.push({ ...node, child_list: children })
      }
      return result
    }, [])
  }
  return filterNodes(tree.value)
})

const isLeaf = (row: any) => !Array.isArray(row.child_list) || row.child_list.length === 0

const templateTargetName = computed(() => {
  if (!templateTarget.value) return '通用兜底'
  return templateTarget.value.model_full_name || templateTarget.value.node_name || '-'
})

const effectiveTemplateDescription = computed(() => {
  const effective = templateBindingInfo.value.effective || {}
  const segments = [
    effective.check_template_name ? `质检：${effective.check_template_name}` : '',
    effective.print_template_name ? `标签：${effective.print_template_name}` : '',
  ].filter(Boolean)
  return segments.length ? segments.join('，') : '该规则暂未绑定具体模板。'
})

const levelName = (level: number) => {
  return `L${Number(level) || 1}`
}

const levelTagType = (level: number) => {
  if (Number(level) === 1) return 'primary'
  if (Number(level) === 2) return 'warning'
  return 'success'
}

const splitPath = (row: any) => {
  return String(row.model_full_name || row.node_name || '').split('/').filter(Boolean)
}

const checkTemplateLabel = (item: any) => {
  return [
    item.template_name || `质检模板 #${item.id}`,
    item.scene ? `场景：${item.scene}` : '',
    Number(item.is_default || 0) === 1 ? '默认' : '',
  ].filter(Boolean).join(' / ')
}

const printTemplateLabel = (item: any) => {
  const size = item.width && item.height ? `${item.width}x${item.height}` : ''
  return [
    item.template_name || `标签模板 #${item.template_id}`,
    size,
    Number(item.is_default || 0) === 1 ? '默认' : '',
  ].filter(Boolean).join(' / ')
}

const resetEditForm = () => {
  Object.assign(editForm, {
    id: 0,
    path_text: '',
    status: 1,
    sort: 0,
  })
}

const loadTree = async () => {
  loading.value = true
  try {
    const res = await getRecycleDeviceModelDictTree()
    tree.value = res.data || []
  } finally {
    loading.value = false
  }
}

const openCreateRoot = () => {
  parentContext.value = null
  resetEditForm()
  editDialogVisible.value = true
}

const openCreateChild = (row: any) => {
  parentContext.value = row
  resetEditForm()
  editForm.path_text = `${row.model_full_name || row.node_name}/`
  editDialogVisible.value = true
}

const openEdit = (row: any) => {
  if (!isLeaf(row)) {
    ElMessage.warning('请编辑最末级分类节点，上级节点通过新增下级维护')
    return
  }
  parentContext.value = null
  resetEditForm()
  const parts = splitPath(row)
  Object.assign(editForm, {
    id: row.id,
    path_text: parts.join('/'),
    status: Number(row.status ?? 1),
    sort: Number(row.sort || 0),
  })
  editDialogVisible.value = true
}

const saveEdit = async () => {
  const path = String(editForm.path_text || '').split('/').map(item => item.trim()).filter(Boolean)
  if (path.length < 2) {
    ElMessage.warning('请至少填写两级分类路径')
    return
  }
  saving.value = true
  try {
    const payload = {
      path,
      status: editForm.status,
      sort: editForm.sort,
    }
    if (editForm.id) {
      await editRecycleDeviceModelDict(editForm.id, payload)
    } else {
      await addRecycleDeviceModelDict(payload)
    }
    editDialogVisible.value = false
    await loadTree()
  } finally {
    saving.value = false
  }
}

const handleDelete = async (row: any) => {
  if (!isLeaf(row)) {
    ElMessage.warning('该节点存在下级，请先删除下级分类')
    return
  }
  await ElMessageBox.confirm(`确定删除 ${row.model_full_name || row.node_name} 吗？`, '删除确认', {
    type: 'warning',
  })
  await deleteRecycleDeviceModelDict(row.id)
  await loadTree()
}

const openSortDialog = (row: any) => {
  Object.assign(sortForm, {
    id: row.id,
    path_text: row.model_full_name || row.node_name || '',
    sort: Number(row.sort || 0),
  })
  sortDialogVisible.value = true
}

const saveSort = async () => {
  if (!sortForm.id) {
    ElMessage.warning('请选择要调整的分类节点')
    return
  }
  sortSaving.value = true
  try {
    await updateRecycleDeviceModelDictSort({
      sort_list: [{
        id: sortForm.id,
        sort: Number(sortForm.sort || 0),
        cascade: 1,
      }],
    })
    ElMessage.success('排序已更新')
    sortDialogVisible.value = false
    await loadTree()
  } finally {
    sortSaving.value = false
  }
}

const openQuickDialog = () => {
  quickDialogVisible.value = true
}

const submitQuickAdd = async () => {
  if (!quickContent.value.trim()) {
    ElMessage.warning('请输入要录入的分类路径')
    return
  }
  saving.value = true
  try {
    const res = await quickAddRecycleDeviceModelDict({ content: quickContent.value })
    const data = res.data || {}
    if (data.duplicates?.length) {
      ElMessageBox.alert(data.duplicates.join('\n'), '发现重复分类', {
        confirmButtonText: '我知道了',
        customClass: 'model-dict-duplicate-alert',
      })
      return
    }
    ElMessage.success(`已创建 ${data.created_count || 0} 个分类节点`)
    quickContent.value = ''
    quickDialogVisible.value = false
    await loadTree()
  } finally {
    saving.value = false
  }
}

const openImportDialog = () => {
  importDialogVisible.value = true
}

const handleImportFileChange = async (file: any) => {
  const raw = file.raw
  if (!raw) return
  importFileName.value = raw.name || ''
  try {
    const text = await readFileText(raw)
    importRows.value = parseImportRows(text, raw.name || '')
    if (!importRows.value.length) {
      ElMessage.warning('没有识别到可导入的数据')
      return
    }
    ElMessage.success(`已识别 ${importRows.value.length} 行数据`)
  } catch (error: any) {
    importRows.value = []
    ElMessage.error(error.message || '文件解析失败')
  }
}

const submitImport = async () => {
  if (!importRows.value.length) {
    ElMessage.warning('请先选择要导入的文件')
    return
  }
  importing.value = true
  try {
    const res = await importExternalRecycleDeviceModelDict({
      source: importSource.value || 'recycle_spider',
      rows: importRows.value,
    })
    const data = res.data || {}
    const message = `新增 ${data.created_count || 0} 行，更新 ${data.updated_count || 0} 行，跳过 ${data.skipped_count || 0} 行`
    if (data.skipped?.length) {
      await ElMessageBox.alert(data.skipped.slice(0, 30).map((item: any) => `第${item.line}行：${item.reason}`).join('\n'), message, {
        confirmButtonText: '我知道了',
        customClass: 'model-dict-duplicate-alert',
      })
    } else {
      ElMessage.success(message)
    }
    importDialogVisible.value = false
    importRows.value = []
    importFileName.value = ''
    await loadTree()
  } finally {
    importing.value = false
  }
}

const readFileText = (file: File): Promise<string> => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader()
    reader.onload = () => resolve(String(reader.result || ''))
    reader.onerror = () => reject(new Error('读取文件失败'))
    reader.readAsText(file, 'utf-8')
  })
}

const parseImportRows = (text: string, fileName: string): any[] => {
  const content = text.replace(/^\uFEFF/, '').trim()
  if (!content) return []
  if (fileName.toLowerCase().endsWith('.json') || content.startsWith('[') || content.startsWith('{')) {
    const parsed = JSON.parse(content)
    const rows = Array.isArray(parsed) ? parsed : parsed.rows
    if (!Array.isArray(rows)) {
      throw new Error('JSON 格式错误，请使用数组或 { rows: [] }')
    }
    return rows.filter(item => item && typeof item === 'object')
  }
  return parseDelimitedRows(content)
}

const parseDelimitedRows = (content: string): any[] => {
  const firstLine = content.split(/\r?\n/, 1)[0] || ''
  const delimiter = firstLine.includes('\t') ? '\t' : ','
  const rows = parseDelimited(content, delimiter)
  if (rows.length < 2) return []
  const headers = rows[0].map(item => item.trim())
  return rows.slice(1)
    .filter(row => row.some(cell => String(cell || '').trim() !== ''))
    .map(row => headers.reduce((result: Record<string, string>, key, index) => {
      result[key] = String(row[index] || '').trim()
      return result
    }, {}))
}

const parseDelimited = (content: string, delimiter: string): string[][] => {
  const rows: string[][] = []
  let row: string[] = []
  let cell = ''
  let quoted = false

  for (let index = 0; index < content.length; index++) {
    const char = content[index]
    const next = content[index + 1]
    if (char === '"' && quoted && next === '"') {
      cell += '"'
      index++
      continue
    }
    if (char === '"') {
      quoted = !quoted
      continue
    }
    if (char === delimiter && !quoted) {
      row.push(cell)
      cell = ''
      continue
    }
    if ((char === '\n' || char === '\r') && !quoted) {
      if (char === '\r' && next === '\n') index++
      row.push(cell)
      rows.push(row)
      row = []
      cell = ''
      continue
    }
    cell += char
  }
  row.push(cell)
  rows.push(row)
  return rows
}

const resetTemplateForm = (row?: any) => {
  Object.assign(templateForm, {
    target_type: row?.id ? 'model_dict' : 'global',
    target_id: row?.id || 0,
    scene_key: 'manual_device_label',
    check_template_id: '',
    print_template_id: '',
    inherit_enabled: 1,
    status: 1,
    remark: '',
    sort: 0,
  })
}

const openTemplateDialog = async (row?: any) => {
  templateTarget.value = row || null
  resetTemplateForm(row)
  templateDialogVisible.value = true
  await loadTemplateBinding()
}

const loadTemplateBinding = async () => {
  const res = await getTemplateBindingInfo({
    target_type: templateForm.target_type,
    target_id: templateForm.target_id,
    scene_key: templateForm.scene_key,
  })
  const data = res.data || {}
  templateBindingInfo.value = {
    binding: data.binding || {},
    effective: data.effective || {},
    check_template_options: data.check_template_options || [],
    print_template_options: data.print_template_options || [],
  }

  if (data.binding?.id) {
    Object.assign(templateForm, {
      check_template_id: data.binding.check_template_id || '',
      print_template_id: data.binding.print_template_id || '',
      inherit_enabled: Number(data.binding.inherit_enabled ?? 1),
      status: Number(data.binding.status ?? 1),
      remark: data.binding.remark || '',
      sort: Number(data.binding.sort || 0),
    })
  }
}

const handleSaveTemplateBinding = async () => {
  if (!templateForm.check_template_id && !templateForm.print_template_id) {
    ElMessage.warning('请至少选择一个质检模板或标签模板')
    return
  }
  templateSaving.value = true
  try {
    await saveTemplateBinding({
      ...templateForm,
      check_template_id: templateForm.check_template_id || 0,
      print_template_id: templateForm.print_template_id || 0,
    })
    await loadTemplateBinding()
  } finally {
    templateSaving.value = false
  }
}

const handleResetTemplateBinding = async () => {
  await ElMessageBox.confirm('恢复继承后，本节点不再保留独立模板配置，继续使用上级或通用兜底规则。', '恢复继承', {
    type: 'warning',
  })
  await resetTemplateBinding({
    target_type: templateForm.target_type,
    target_id: templateForm.target_id,
    scene_key: templateForm.scene_key,
  })
  resetTemplateForm(templateTarget.value)
  await loadTemplateBinding()
}

onMounted(() => loadTree())
</script>

<style scoped>
.model-dict-page {
  padding: 16px;
}

.page-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 12px;
}

.page-subtitle {
  margin-top: 6px;
  color: #64748b;
  font-size: 13px;
}

.page-actions,
.toolbar {
  display: flex;
  align-items: center;
  gap: 10px;
}

.toolbar {
  margin: 10px 0 12px;
}

.toolbar-keyword {
  width: 300px;
}

.toolbar-status {
  width: 130px;
}

.quick-textarea {
  margin-top: 12px;
}

.import-form {
  margin-top: 16px;
}

.import-upload {
  width: 100%;
}

.import-upload :deep(.el-upload),
.import-upload :deep(.el-upload-dragger) {
  width: 100%;
}

.import-upload__main {
  padding: 12px 0;
}

.import-upload__title {
  color: #334155;
  font-size: 15px;
  font-weight: 600;
}

.import-upload__tip {
  margin-top: 8px;
  color: #64748b;
  font-size: 12px;
}

.import-preview {
  margin-top: 14px;
}

.import-preview__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 8px;
  color: #334155;
  font-size: 13px;
}

.virtual-tree-panel {
  border: 1px solid #ebeef5;
  border-radius: 6px;
  overflow-x: auto;
  overflow-y: hidden;
}

.tree-head,
.tree-row {
  display: grid;
  grid-template-columns: minmax(230px, 1.2fr) minmax(260px, 1.5fr) 90px 80px 90px 300px;
  align-items: center;
  column-gap: 12px;
  width: 100%;
}

.tree-head {
  height: 44px;
  min-width: 1000px;
  padding: 0 16px;
  color: #606266;
  font-size: 14px;
  font-weight: 600;
  background: #f8fafc;
  border-bottom: 1px solid #ebeef5;
}

.tree-head__action {
  text-align: right;
}

.model-virtual-tree {
  width: 100%;
}

:deep(.model-virtual-tree .el-tree-node__content) {
  height: 54px;
  border-bottom: 1px solid #f1f5f9;
}

:deep(.model-virtual-tree .el-tree-node__content:hover) {
  background: #f8fafc;
}

.tree-row {
  min-width: 1000px;
  padding-right: 16px;
}

.tree-cell {
  min-width: 0;
  color: #334155;
  font-size: 14px;
}

.tree-cell--name {
  display: flex;
  align-items: center;
  gap: 10px;
}

.tree-node-name,
.tree-cell--path {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tree-cell--path {
  color: #475569;
}

.tree-cell--center {
  text-align: center;
}

.tree-cell--actions {
  display: flex;
  justify-content: flex-end;
  align-items: center;
  gap: 8px;
}

.binding-summary {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  margin-bottom: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
}

.binding-summary__label {
  color: #64748b;
  font-size: 12px;
}

.binding-summary__title {
  margin-top: 4px;
  color: #0f172a;
  font-size: 16px;
  font-weight: 600;
}

.binding-effective {
  margin-bottom: 14px;
}

.binding-form :deep(.el-select) {
  width: 100%;
}

.form-tip {
  margin-left: 10px;
  color: #64748b;
  font-size: 12px;
}

.sort-target {
  padding: 12px 14px;
  margin-bottom: 12px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 6px;
}

.sort-target__label {
  color: #64748b;
  font-size: 12px;
}

.sort-target__name {
  margin-top: 4px;
  color: #0f172a;
  font-size: 15px;
  font-weight: 600;
  word-break: break-all;
}

.sort-alert {
  margin-bottom: 14px;
}
</style>
