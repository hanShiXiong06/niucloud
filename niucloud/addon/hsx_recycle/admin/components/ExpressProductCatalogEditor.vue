<template>
    <div class="express-product-editor">
        <div class="editor-toolbar">
            <div>
                <div class="toolbar-title">快递产品配置</div>
                <div class="toolbar-desc">按产品分类展开并选择常用服务；导入的数据默认关闭，由客户自行启用。</div>
            </div>
            <div class="toolbar-actions">
                <el-input
                    v-model="keyword"
                    clearable
                    placeholder="搜索产品名称或编号"
                    class="search-input"
                    :prefix-icon="Search"
                />
                <input ref="fileInput" type="file" accept=".xls,.xlsx" class="hidden-file" @change="handleFileChange" />
                <el-button :loading="downloading" @click="downloadTemplate">
                    <el-icon><Download /></el-icon>
                    下载模板
                </el-button>
                <el-button @click="openImportDialog">
                    <el-icon><Upload /></el-icon>
                    导入产品
                </el-button>
                <el-button @click="historyVisible = true">导入记录</el-button>
                <el-button type="primary" :loading="saving" @click="saveProducts">
                    <el-icon><Check /></el-icon>
                    保存配置
                </el-button>
            </div>
        </div>

        <div class="import-guide">
            <div><span>1</span><p><strong>下载标准模板</strong><small>列名和填写说明已内置</small></p></div>
            <el-icon><ArrowRight /></el-icon>
            <div><span>2</span><p><strong>填写并上传</strong><small>支持 xls / xlsx，最大 20MB</small></p></div>
            <el-icon><ArrowRight /></el-icon>
            <div><span>3</span><p><strong>查看异步结果</strong><small>重复编号跳过，新产品默认停用</small></p></div>
        </div>

        <div v-if="latestTask" class="import-task" :class="`is-${latestTask.status}`">
            <div class="task-main">
                <div class="task-title">
                    <span>{{ latestTask.file_name }}</span>
                    <el-tag size="small" :type="taskMeta(latestTask.status).type">{{ taskMeta(latestTask.status).label }}</el-tag>
                </div>
                <div class="task-message">{{ latestTask.message }}</div>
                <el-progress
                    v-if="['queued', 'processing'].includes(latestTask.status)"
                    :percentage="Number(latestTask.progress || 0)"
                    :stroke-width="6"
                />
            </div>
            <div class="task-counts">
                <span>新增 {{ latestTask.created_count || 0 }}</span>
                <span>跳过 {{ latestTask.skipped_count || 0 }}</span>
                <el-button link type="primary" @click="openTaskDetail(latestTask)">查看详情</el-button>
                <el-button
                    v-if="['pending', 'failed'].includes(latestTask.status)"
                    link
                    type="primary"
                    @click="retryTask(latestTask.task_id)"
                >重试</el-button>
            </div>
        </div>

        <div class="catalog-summary">
            <div><strong>{{ catalog.total || 0 }}</strong><span>全部产品</span></div>
            <div><strong class="success">{{ catalog.enabled_count || 0 }}</strong><span>已启用</span></div>
            <div><strong>{{ Math.max(0, (catalog.total || 0) - (catalog.enabled_count || 0)) }}</strong><span>未启用</span></div>
        </div>

        <el-skeleton v-if="loading" :rows="6" animated class="mt-[16px]" />
        <el-empty v-else-if="!catalog.tree.length" description="暂无快递产品，请导入 Excel" />
        <template v-else>
            <el-tabs v-model="activeTab" class="catalog-tabs">
                <el-tab-pane v-for="tab in catalog.tabs" :key="tab.key" :name="tab.key">
                    <template #label>
                        <span>{{ tab.label }}</span>
                        <span class="tab-count">{{ tab.count }}</span>
                    </template>
                </el-tab-pane>
            </el-tabs>

            <div class="tree-toolbar">
                <span>当前分类：{{ activeTabLabel }}</span>
                <div>
                    <el-button size="small" @click="updateNodeProducts(activeRoot, 0)">全部停用</el-button>
                    <el-button size="small" type="primary" plain @click="updateNodeProducts(activeRoot, 1)">全部启用</el-button>
                </div>
            </div>

            <div class="tree-panel">
                <el-tree
                    ref="treeRef"
                    :data="activeTree"
                    node-key="key"
                    :props="{ children: 'children', label: 'label' }"
                    :filter-node-method="filterNode"
                    :render-after-expand="true"
                    :expand-on-click-node="false"
                    empty-text="当前分类没有匹配的产品"
                >
                    <template #default="{ data }">
                        <div v-if="data.node_type === 'category'" class="category-node">
                            <div class="category-name">
                                <el-icon><FolderOpened /></el-icon>
                                <span>{{ data.label }}</span>
                            </div>
                            <div class="category-actions" @click.stop>
                                <span class="category-count">{{ data.product_count || 0 }} 个产品</span>
                                <el-button link type="info" @click="updateNodeProducts(data, 0)">停用</el-button>
                                <el-button link type="primary" @click="updateNodeProducts(data, 1)">启用</el-button>
                            </div>
                        </div>
                        <div v-else class="product-node" @click.stop>
                            <div class="product-info">
                                <span class="product-code">{{ data.product_code }}</span>
                                <div>
                                    <div class="product-name">{{ data.product_name }}</div>
                                    <div class="product-path">{{ data.category_path }}</div>
                                </div>
                            </div>
                            <div class="product-actions">
                                <span class="action-label">启用</span>
                                <el-switch
                                    :model-value="Number(data.status)"
                                    :active-value="1"
                                    :inactive-value="0"
                                    @change="value => updateProduct(data.product_code, 'status', value)"
                                />
                                <span class="action-label ml-[14px]">排序</span>
                                <el-input-number
                                    :model-value="Number(data.sort || 0)"
                                    :min="0"
                                    :max="9999"
                                    controls-position="right"
                                    size="small"
                                    @change="value => updateProduct(data.product_code, 'sort', value || 0)"
                                />
                            </div>
                        </div>
                    </template>
                </el-tree>
            </div>
        </template>

        <el-dialog v-model="importVisible" title="导入快递产品" width="620px" destroy-on-close>
            <div class="import-dialog-body">
                <div class="template-panel">
                    <div class="template-icon"><el-icon><Document /></el-icon></div>
                    <div class="template-copy"><strong>先使用标准模板整理数据</strong><span>不要修改表头；产品编号、产品名称为必填。重复产品编号不会覆盖已有配置。</span></div>
                    <el-button :loading="downloading" @click="downloadTemplate">下载模板</el-button>
                </div>
                <div class="file-picker" :class="{ 'has-file': selectedFile }" @click="triggerFileSelect">
                    <el-icon class="picker-icon"><UploadFilled /></el-icon>
                    <template v-if="selectedFile">
                        <strong>{{ selectedFile.name }}</strong>
                        <span>{{ formatFileSize(selectedFile.size) }} · 点击重新选择</span>
                    </template>
                    <template v-else>
                        <strong>选择填写完成的 Excel</strong>
                        <span>支持 .xls、.xlsx，文件大小不超过 20MB</span>
                    </template>
                </div>
                <el-alert type="warning" :closable="false" show-icon title="导入采用异步任务，关闭弹窗不影响处理；完成后可在任务详情查看新增、跳过及错误行。" />
            </div>
            <template #footer>
                <el-button @click="importVisible = false">取消</el-button>
                <el-button type="primary" :loading="importing" :disabled="!selectedFile" @click="startImport">开始导入</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="historyVisible" title="导入记录" width="780px">
            <el-table :data="taskHistory" max-height="420" @row-click="openTaskDetail">
                <el-table-column prop="file_name" label="文件" min-width="210" show-overflow-tooltip />
                <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag size="small" :type="taskMeta(row.status).type">{{ taskMeta(row.status).label }}</el-tag></template></el-table-column>
                <el-table-column prop="created_count" label="新增" width="80" />
                <el-table-column prop="skipped_count" label="跳过" width="80" />
                <el-table-column prop="operator_name" label="操作人" width="100" />
                <el-table-column label="操作" width="80"><template #default="{ row }"><el-button link type="primary" @click.stop="openTaskDetail(row)">详情</el-button></template></el-table-column>
            </el-table>
            <el-empty v-if="!taskHistory.length" :image-size="70" description="暂无导入记录" />
        </el-dialog>

        <el-dialog v-model="taskDetailVisible" title="导入任务详情" width="680px">
            <template v-if="taskDetail">
                <div class="detail-head">
                    <div><strong>{{ taskDetail.file_name }}</strong><span>{{ taskDetail.message || '-' }}</span></div>
                    <el-tag :type="taskMeta(taskDetail.status).type">{{ taskMeta(taskDetail.status).label }}</el-tag>
                </div>
                <el-descriptions :column="3" border class="mt-[16px]">
                    <el-descriptions-item label="数据总行">{{ taskDetail.total_rows || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="已处理">{{ taskDetail.processed_rows || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="导入进度">{{ taskDetail.progress || 0 }}%</el-descriptions-item>
                    <el-descriptions-item label="新增">{{ taskDetail.created_count || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="跳过">{{ taskDetail.skipped_count || 0 }}</el-descriptions-item>
                    <el-descriptions-item label="错误">{{ taskDetail.error_count || 0 }}</el-descriptions-item>
                </el-descriptions>
                <div v-if="taskDetail.errors?.length" class="error-list">
                    <div class="error-title">未导入的数据</div>
                    <div v-for="(error, index) in taskDetail.errors" :key="index" class="error-row">
                        <span>第 {{ error.line || '-' }} 行</span><p>{{ error.reason || error.message || '数据格式错误' }}</p>
                    </div>
                </div>
                <el-empty v-else :image-size="64" description="没有错误数据" />
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { ArrowRight, Check, Document, Download, FolderOpened, Search, Upload, UploadFilled } from '@element-plus/icons-vue'
import {
    batchUpdateYisuProduct,
    downloadExpressProductTemplate,
    getExpressProductCatalog,
    getExpressProductImportTask,
    getExpressProductImportTasks,
    importExpressProducts,
    retryExpressProductImportTask
} from '@/addon/hsx_recycle/api/yisu'

const props = withDefaults(defineProps<{
    provider?: string
    compact?: boolean
}>(), {
    provider: 'yisu',
    compact: false
})

const emit = defineEmits<{
    (event: 'saved'): void
}>()

const loading = ref(false)
const saving = ref(false)
const importing = ref(false)
const downloading = ref(false)
const keyword = ref('')
const activeTab = ref('')
const fileInput = ref<HTMLInputElement>()
const treeRef = ref<any>()
const latestTask = ref<any>(null)
const importVisible = ref(false)
const taskDetailVisible = ref(false)
const historyVisible = ref(false)
const selectedFile = ref<File | null>(null)
const taskHistory = ref<any[]>([])
const taskDetail = ref<any>(null)
let pollTimer: ReturnType<typeof setTimeout> | null = null

const catalog = reactive<any>({
    tabs: [],
    tree: [],
    list: [],
    total: 0,
    enabled_count: 0
})

const activeRoot = computed(() => catalog.tree.find((item: any) => item.key === activeTab.value))
const activeTree = computed(() => activeRoot.value?.children || [])
const activeTabLabel = computed(() => catalog.tabs.find((item: any) => item.key === activeTab.value)?.label || '全部')

const loadCatalog = async () => {
    loading.value = true
    try {
        const res: any = await getExpressProductCatalog(props.provider)
        const data = res.data || {}
        Object.assign(catalog, {
            tabs: data.tabs || [],
            tree: data.tree || [],
            list: data.list || [],
            total: Number(data.total || 0),
            enabled_count: Number(data.enabled_count || 0)
        })
        if (!catalog.tabs.some((item: any) => item.key === activeTab.value)) {
            activeTab.value = catalog.tabs[0]?.key || ''
        }
        await nextTick()
        treeRef.value?.filter(keyword.value)
    } catch (error: any) {
        ElMessage.error(error.msg || error.message || '快递产品加载失败')
    } finally {
        loading.value = false
    }
}

const updateProduct = (productCode: string, field: 'status' | 'sort', value: any) => {
    const product = catalog.list.find((item: any) => String(item.product_code) === String(productCode))
    if (product) product[field] = Number(value || 0)
    const updateTree = (nodes: any[]) => {
        nodes.forEach(node => {
            if (node.node_type === 'product' && String(node.product_code) === String(productCode)) node[field] = Number(value || 0)
            if (node.children?.length) updateTree(node.children)
        })
    }
    updateTree(catalog.tree)
    catalog.enabled_count = catalog.list.filter((item: any) => Number(item.status) === 1).length
}

const updateNodeProducts = (node: any, status: 0 | 1) => {
    if (!node) return
    const codes: string[] = []
    const collect = (current: any) => {
        if (current?.node_type === 'product') {
            codes.push(String(current.product_code))
            return
        }
        ;(current?.children || []).forEach(collect)
    }
    collect(node)
    codes.forEach(code => updateProduct(code, 'status', status))
    ElMessage.success(`已${status === 1 ? '启用' : '停用'} ${codes.length} 个产品，保存后生效`)
}

const saveProducts = async () => {
    saving.value = true
    try {
        await batchUpdateYisuProduct({
            provider: props.provider,
            products: catalog.list.map((item: any) => ({
                provider: props.provider,
                product_code: item.product_code,
                product_name: item.product_name,
                category_path: item.category_path,
                express_type: item.express_type,
                logo: item.logo || '',
                status: Number(item.status || 0),
                sort: Number(item.sort || 0),
                source: item.source || 'config'
            }))
        })
        ElMessage.success('快递产品配置已保存')
        emit('saved')
        await loadCatalog()
    } finally {
        saving.value = false
    }
}

const handleFileChange = async (event: Event) => {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    input.value = ''
    if (!file) return
    if (!/\.(xls|xlsx)$/i.test(file.name)) return ElMessage.warning('只支持上传 xls、xlsx 文件')
    if (file.size > 20 * 1024 * 1024) return ElMessage.warning('文件大小不能超过 20MB')
    selectedFile.value = file
}

const startImport = async () => {
    const file = selectedFile.value
    if (!file) return
    importing.value = true
    try {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('provider', props.provider)
        const res: any = await importExpressProducts(formData)
        const taskId = String(res.data?.task_id || '')
        showTaskMessage(res.data?.queue_enabled === false, res.data?.message || '导入任务已创建')
        if (taskId) {
            await loadTask(taskId)
            schedulePoll(taskId)
        }
        importVisible.value = false
        openTaskDetail(latestTask.value)
        selectedFile.value = null
    } finally {
        importing.value = false
    }
}

const downloadTemplate = async () => {
    downloading.value = true
    try {
        const blob: any = await downloadExpressProductTemplate()
        const url = window.URL.createObjectURL(blob instanceof Blob ? blob : new Blob([blob]))
        const link = document.createElement('a')
        link.href = url
        link.download = '快递产品导入模板.xlsx'
        document.body.appendChild(link)
        link.click()
        link.remove()
        window.URL.revokeObjectURL(url)
        ElMessage.success('模板已下载')
    } catch (error: any) {
        ElMessage.error(error?.message || '模板下载失败')
    } finally { downloading.value = false }
}

const loadLatestTask = async () => {
    const res: any = await getExpressProductImportTasks()
    taskHistory.value = res.data || []
    latestTask.value = taskHistory.value[0] || null
    if (latestTask.value && ['queued', 'processing'].includes(latestTask.value.status)) {
        schedulePoll(latestTask.value.task_id)
    }
}

const loadTask = async (taskId: string) => {
    const res: any = await getExpressProductImportTask(taskId)
    latestTask.value = res.data || null
    const index = taskHistory.value.findIndex(item => item.task_id === latestTask.value?.task_id)
    if (index >= 0) taskHistory.value.splice(index, 1, latestTask.value)
    else if (latestTask.value) taskHistory.value.unshift(latestTask.value)
    if (taskDetail.value?.task_id === latestTask.value?.task_id) taskDetail.value = latestTask.value
    if (latestTask.value && ['completed', 'partial'].includes(latestTask.value.status)) await loadCatalog()
}

const schedulePoll = (taskId: string) => {
    if (pollTimer) clearTimeout(pollTimer)
    pollTimer = setTimeout(async () => {
        try {
            await loadTask(taskId)
            if (latestTask.value && ['queued', 'processing'].includes(latestTask.value.status)) schedulePoll(taskId)
        } catch (_) {
            // 下一次进入页面仍可读取保存在 sys_config 中的任务状态。
        }
    }, 1800)
}

const retryTask = async (taskId: string) => {
    const res: any = await retryExpressProductImportTask(taskId)
    showTaskMessage(res.data?.queue_enabled === false, res.data?.message || '任务已重新提交')
    await loadTask(taskId)
    schedulePoll(taskId)
}

const filterNode = (value: string, data: any) => {
    if (!value) return true
    if (data.node_type === 'category') return true
    const text = `${data.product_code || ''} ${data.product_name || ''} ${data.category_path || ''}`.toLowerCase()
    return text.includes(value.trim().toLowerCase())
}

const taskMeta = (status: string) => ({
    pending: { label: '等待执行', type: 'warning' },
    queued: { label: '已排队', type: 'warning' },
    processing: { label: '导入中', type: 'primary' },
    completed: { label: '已完成', type: 'success' },
    partial: { label: '部分跳过', type: 'warning' },
    failed: { label: '失败', type: 'danger' }
} as Record<string, any>)[status] || { label: status || '未知', type: 'info' }

const triggerFileSelect = () => fileInput.value?.click()
const openImportDialog = () => { selectedFile.value = null; importVisible.value = true }
const openTaskDetail = (task: any) => { taskDetail.value = task || latestTask.value; taskDetailVisible.value = true }
const formatFileSize = (size: number) => size >= 1024 * 1024 ? `${(size / 1024 / 1024).toFixed(2)} MB` : `${Math.max(1, Math.round(size / 1024))} KB`
const showTaskMessage = (warning: boolean, message: string) => {
    if (warning) ElMessage.warning(message)
    else ElMessage.success(message)
}

watch(keyword, value => treeRef.value?.filter(value))
watch(activeTab, async () => {
    await nextTick()
    treeRef.value?.filter(keyword.value)
})

onMounted(async () => {
    await Promise.all([loadCatalog(), loadLatestTask()])
})

onBeforeUnmount(() => {
    if (pollTimer) clearTimeout(pollTimer)
})

defineExpose({ reload: loadCatalog, save: saveProducts })
</script>

<style scoped lang="scss">
.express-product-editor {
    --catalog-border: #e5e7eb;
    --catalog-muted: #6b7280;
}

.editor-toolbar,
.product-node,
.category-node,
.import-task,
.catalog-summary {
    display: flex;
    align-items: center;
}

.editor-toolbar {
    justify-content: space-between;
    gap: 20px;
}

.toolbar-title {
    color: #111827;
    font-size: 16px;
    font-weight: 600;
}

.toolbar-desc {
    margin-top: 5px;
    color: var(--catalog-muted);
    font-size: 13px;
}

.toolbar-actions {
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-input { width: 230px; }
.hidden-file { display: none; }

.import-guide { display: flex; align-items: center; gap: 16px; margin-top: 16px; padding: 14px 18px; border: 1px solid #e5e7eb; border-radius: 8px; background: #fafcff; }
.import-guide > div { display: flex; align-items: center; flex: 1; gap: 10px; }.import-guide > div > span { display: flex; align-items: center; justify-content: center; flex: 0 0 26px; height: 26px; border-radius: 50%; color: #2563eb; background: #dbeafe; font-size: 12px; font-weight: 700; }.import-guide p { display: flex; min-width: 0; margin: 0; flex-direction: column; }.import-guide strong { color: #334155; font-size: 13px; }.import-guide small { margin-top: 2px; color: #94a3b8; font-size: 11px; }.import-guide > .el-icon { color: #cbd5e1; }

.import-task {
    justify-content: space-between;
    gap: 24px;
    margin-top: 14px;
    padding: 12px 14px;
    border: 1px solid #dbeafe;
    border-radius: 8px;
    background: #f8fbff;
}

.task-main { min-width: 0; flex: 1; }
.task-title { display: flex; align-items: center; gap: 8px; color: #1f2937; font-weight: 500; }
.task-message { margin: 4px 0 6px; color: var(--catalog-muted); font-size: 12px; }
.task-counts { display: flex; gap: 12px; white-space: nowrap; color: var(--catalog-muted); font-size: 12px; }

.catalog-summary {
    gap: 28px;
    margin-top: 16px;
    padding: 12px 16px;
    border-radius: 8px;
    background: #f8fafc;
}

.catalog-summary > div { display: flex; align-items: baseline; gap: 7px; }
.catalog-summary strong { color: #111827; font-size: 20px; }
.catalog-summary strong.success { color: #16a34a; }
.catalog-summary span { color: var(--catalog-muted); font-size: 12px; }

.catalog-tabs { margin-top: 14px; }
.tab-count { margin-left: 6px; padding: 1px 6px; border-radius: 9px; background: #f1f5f9; color: #64748b; font-size: 11px; }

.tree-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-height: 42px;
    padding: 0 12px;
    border: 1px solid var(--catalog-border);
    border-bottom: none;
    border-radius: 8px 8px 0 0;
    background: #f8fafc;
    color: #64748b;
    font-size: 12px;
}

.tree-panel {
    min-height: 240px;
    padding: 8px 0;
    border: 1px solid var(--catalog-border);
    border-radius: 0 0 8px 8px;
}

:deep(.el-tree-node__content) {
    min-height: 48px;
    height: auto;
    padding-right: 14px;
    border-bottom: 1px solid #f1f5f9;
}

:deep(.el-tree-node:last-child > .el-tree-node__content) { border-bottom: none; }
:deep(.el-tree-node__content:hover) { background: #f8fafc; }

.category-node,
.product-node {
    flex: 1;
    min-width: 0;
    justify-content: space-between;
}

.category-name { display: flex; align-items: center; gap: 7px; color: #334155; font-weight: 600; }
.category-actions { display: flex; align-items: center; gap: 4px; }
.category-count { color: #94a3b8; font-size: 12px; }
.product-info { display: flex; min-width: 0; align-items: center; gap: 12px; }
.product-code { min-width: 54px; padding: 3px 7px; border-radius: 5px; background: #eff6ff; color: #2563eb; text-align: center; font-size: 12px; }
.product-name { overflow: hidden; max-width: 440px; color: #1f2937; text-overflow: ellipsis; white-space: nowrap; }
.product-path { margin-top: 2px; color: #94a3b8; font-size: 11px; }
.product-actions { display: flex; align-items: center; flex-shrink: 0; }
.action-label { margin-right: 7px; color: #64748b; font-size: 12px; }
.product-actions :deep(.el-input-number) { width: 104px; }

.import-dialog-body { display: flex; flex-direction: column; gap: 16px; }.template-panel { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 8px; background: #f8fafc; }.template-icon { display: flex; align-items: center; justify-content: center; flex: 0 0 42px; height: 42px; border-radius: 8px; color: #16a34a; background: #dcfce7; font-size: 22px; }.template-copy { display: flex; min-width: 0; flex: 1; flex-direction: column; }.template-copy strong { color: #1f2937; font-size: 13px; }.template-copy span { margin-top: 4px; color: #64748b; font-size: 11px; line-height: 1.5; }.file-picker { display: flex; align-items: center; justify-content: center; min-height: 150px; padding: 20px; border: 1px dashed #bfdbfe; border-radius: 10px; background: #f8fbff; cursor: pointer; flex-direction: column; transition: .2s; }.file-picker:hover,.file-picker.has-file { border-color: #3b82f6; background: #eff6ff; }.picker-icon { color: #3b82f6; font-size: 34px; }.file-picker strong { margin-top: 10px; color: #1e293b; font-size: 14px; }.file-picker span { margin-top: 5px; color: #94a3b8; font-size: 12px; }.detail-head { display: flex; align-items: center; justify-content: space-between; gap: 16px; }.detail-head > div { display: flex; min-width: 0; flex-direction: column; }.detail-head strong { overflow: hidden; color: #111827; text-overflow: ellipsis; white-space: nowrap; }.detail-head span { margin-top: 4px; color: #94a3b8; font-size: 12px; }.error-list { overflow: hidden; margin-top: 16px; border: 1px solid #fee2e2; border-radius: 8px; }.error-title { padding: 10px 12px; color: #991b1b; background: #fef2f2; font-size: 13px; font-weight: 600; }.error-row { display: flex; gap: 14px; padding: 9px 12px; border-top: 1px solid #fef2f2; font-size: 12px; }.error-row span { flex: 0 0 70px; color: #dc2626; }.error-row p { margin: 0; color: #64748b; }

@media (max-width: 1100px) {
    .editor-toolbar { align-items: flex-start; flex-direction: column; }
    .toolbar-actions { width: 100%; flex-wrap: wrap; }
    .search-input { flex: 1; min-width: 220px; }
    .import-guide { align-items: flex-start; flex-direction: column; }.import-guide > .el-icon { display: none; }
}
</style>
