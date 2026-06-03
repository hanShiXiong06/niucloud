<template>
  <div class="model-dict-page">
    <el-card shadow="never">
      <div class="page-head">
        <div>
          <span class="text-page-title">回收型号字典</span>
          <div class="page-subtitle">签收和代下单时按品牌、系列、型号快速选择；只有最末级型号会进入选择器。</div>
        </div>
        <div class="page-actions">
          <el-button type="primary" plain @click="openQuickDialog">快速录入</el-button>
          <el-button type="primary" @click="openCreateRoot">新增型号</el-button>
        </div>
      </div>

      <div class="toolbar">
        <el-input
          v-model="query.keyword"
          placeholder="搜索品牌、系列、型号"
          clearable
          class="toolbar-keyword"
        />
        <el-select v-model="query.status" placeholder="状态" clearable class="toolbar-status">
          <el-option label="启用" :value="1" />
          <el-option label="停用" :value="0" />
        </el-select>
        <el-button type="primary" @click="loadTree">刷新</el-button>
      </div>

      <el-table
        v-loading="loading"
        :data="filteredTree"
        row-key="id"
        size="large"
        :tree-props="{ children: 'child_list' }"
        height="calc(100vh - 280px)"
      >
        <template #empty>
          <span>{{ loading ? '' : '暂无型号，请先快速录入' }}</span>
        </template>
        <el-table-column label="名称" min-width="240">
          <template #default="{ row }">
            <div class="node-name">
              <el-tag size="small" :type="levelTagType(row.level)" effect="plain">{{ levelName(row.level) }}</el-tag>
              <span>{{ row.node_name }}</span>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="完整路径" min-width="280">
          <template #default="{ row }">
            <span class="path-text">{{ row.model_full_name || row.node_name }}</span>
          </template>
        </el-table-column>
        <el-table-column label="可选择" width="100" align="center">
          <template #default="{ row }">
            <el-tag v-if="isLeaf(row)" type="success" effect="plain">是</el-tag>
            <el-tag v-else type="info" effect="plain">结构</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="排序" width="90" align="center">
          <template #default="{ row }">{{ row.sort || 0 }}</template>
        </el-table-column>
        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row }">
            <el-tag :type="Number(row.status) === 1 ? 'success' : 'info'">
              {{ Number(row.status) === 1 ? '启用' : '停用' }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" fixed="right" align="right" width="260">
          <template #default="{ row }">
            <el-button v-if="Number(row.level) < 3" type="primary" link @click="openCreateChild(row)">
              添加下级
            </el-button>
            <el-button v-if="isLeaf(row)" type="primary" link @click="openEdit(row)">编辑</el-button>
            <el-button
              type="danger"
              link
              :disabled="!isLeaf(row)"
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <el-dialog v-model="editDialogVisible" :title="dialogTitle" width="560px">
      <el-form :model="editForm" label-width="90px">
        <el-form-item label="品牌" required>
          <el-input v-model="editForm.brand_name" :disabled="Boolean(parentContext && parentContext.level >= 1)" placeholder="如：苹果" />
        </el-form-item>
        <el-form-item label="系列">
          <el-input v-model="editForm.series_name" :disabled="Boolean(parentContext && parentContext.level >= 2)" placeholder="如：iPhone，可选" />
        </el-form-item>
        <el-form-item label="型号" required>
          <el-input v-model="editForm.model_name" placeholder="如：iPhone 15 Pro" />
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

    <el-dialog v-model="quickDialogVisible" title="快速录入型号" width="680px">
      <el-alert
        type="info"
        :closable="false"
        show-icon
        title="每行一个型号，支持：苹果/iPhone15 或 苹果/iPhone/iPhone15。重复数据会先提示，不会只创建一部分。"
      />
      <el-input
        v-model="quickContent"
        type="textarea"
        :rows="12"
        class="quick-textarea"
        placeholder="苹果/iPhone 15&#10;苹果/iPhone/iPhone 15 Pro&#10;iPad/iPad 数字系列/iPad 10"
      />
      <template #footer>
        <el-button @click="quickDialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitQuickAdd">批量创建</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  addRecycleDeviceModelDict,
  deleteRecycleDeviceModelDict,
  editRecycleDeviceModelDict,
  getRecycleDeviceModelDictTree,
  quickAddRecycleDeviceModelDict,
} from '@/addon/hsx_recycle/api/recycle_device_model_dict'

const loading = ref(false)
const saving = ref(false)
const tree = ref<any[]>([])
const query = reactive({
  keyword: '',
  status: '',
})

const editDialogVisible = ref(false)
const quickDialogVisible = ref(false)
const quickContent = ref('')
const parentContext = ref<any>(null)
const editForm = reactive<any>({
  id: 0,
  brand_name: '',
  series_name: '',
  model_name: '',
  status: 1,
  sort: 0,
})

const dialogTitle = computed(() => {
  if (editForm.id) return '编辑型号'
  if (parentContext.value) return `添加${Number(parentContext.value.level) === 1 ? '系列/型号' : '型号'}`
  return '新增型号'
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

const levelName = (level: number) => {
  if (Number(level) === 1) return '品牌'
  if (Number(level) === 2) return '系列/型号'
  return '型号'
}

const levelTagType = (level: number) => {
  if (Number(level) === 1) return 'primary'
  if (Number(level) === 2) return 'warning'
  return 'success'
}

const splitPath = (row: any) => {
  return String(row.model_full_name || row.node_name || '').split('/').filter(Boolean)
}

const resetEditForm = () => {
  Object.assign(editForm, {
    id: 0,
    brand_name: '',
    series_name: '',
    model_name: '',
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
  const parts = splitPath(row)
  editForm.brand_name = parts[0] || ''
  if (Number(row.level) >= 2) {
    editForm.series_name = parts[1] || ''
  }
  editDialogVisible.value = true
}

const openEdit = (row: any) => {
  if (!isLeaf(row)) {
    ElMessage.warning('请编辑最末级型号，品牌和系列通过新增下级维护')
    return
  }
  parentContext.value = null
  resetEditForm()
  const parts = splitPath(row)
  Object.assign(editForm, {
    id: row.id,
    brand_name: parts[0] || '',
    series_name: parts.length === 3 ? parts[1] : '',
    model_name: parts[parts.length - 1] || '',
    status: Number(row.status ?? 1),
    sort: Number(row.sort || 0),
  })
  editDialogVisible.value = true
}

const saveEdit = async () => {
  if (!editForm.brand_name || !editForm.model_name) {
    ElMessage.warning('请填写品牌和型号')
    return
  }
  saving.value = true
  try {
    if (editForm.id) {
      await editRecycleDeviceModelDict(editForm.id, editForm)
    } else {
      await addRecycleDeviceModelDict(editForm)
    }
    editDialogVisible.value = false
    await loadTree()
  } finally {
    saving.value = false
  }
}

const handleDelete = async (row: any) => {
  if (!isLeaf(row)) {
    ElMessage.warning('该节点存在下级，请先删除下级型号')
    return
  }
  await ElMessageBox.confirm(`确定删除 ${row.model_full_name || row.node_name} 吗？`, '删除确认', {
    type: 'warning',
  })
  await deleteRecycleDeviceModelDict(row.id)
  await loadTree()
}

const openQuickDialog = () => {
  quickDialogVisible.value = true
}

const submitQuickAdd = async () => {
  if (!quickContent.value.trim()) {
    ElMessage.warning('请输入要录入的型号')
    return
  }
  saving.value = true
  try {
    const res = await quickAddRecycleDeviceModelDict({ content: quickContent.value })
    const data = res.data || {}
    if (data.duplicates?.length) {
      ElMessageBox.alert(data.duplicates.join('\n'), '发现重复型号', {
        confirmButtonText: '我知道了',
        customClass: 'model-dict-duplicate-alert',
      })
      return
    }
    ElMessage.success(`已创建 ${data.created_count || 0} 个型号`)
    quickContent.value = ''
    quickDialogVisible.value = false
    await loadTree()
  } finally {
    saving.value = false
  }
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
.toolbar,
.node-name {
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

.path-text {
  color: #475569;
}

.quick-textarea {
  margin-top: 12px;
}
</style>
