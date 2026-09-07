<template>
    <HsxPage padding="none" class="main-container">
        <el-card class="!border-none" shadow="never">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>商品目录</template>
                <template #subtitle>统一维护一级品类、可选子品类、品牌、系列和型号；采购、库存与商城共同消费这套目录。</template>
                <template #extra><el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button></template>
            </HsxTitle>

            <el-tabs v-model="activeTab" class="mt-5">
                <el-tab-pane label="产品目录" name="catalog">
                    <div class="catalog-intro">
                        <div>
                            <div class="font-medium text-gray-900">标准产品模板 + 本站独立目录</div>
                            <div class="mt-1 text-sm text-gray-500">同款产品可复用标准模板；名称、分类、显示状态仍由当前站点独立管理。</div>
                        </div>
                        <div class="flex gap-2">
                            <input ref="catalogFileInput" class="hidden" type="file" accept=".xlsx,.xls" @change="handleCatalogFile" />
                            <el-button type="primary" :icon="Plus" @click="openCatalogProduct()">新增型号</el-button>
                            <el-button :icon="Upload" :loading="catalogImporting" @click="openCatalogImport">异步导入商品目录</el-button>
                            <el-button @click="openCatalogTaskDialog">导入记录</el-button>
                            <el-button :icon="Download" :loading="catalogExporting" @click="exportCatalog">导出本站目录</el-button>
                        </div>
                    </div>

                    <div v-if="latestCatalogTask" class="mb-4 rounded-lg border border-blue-100 bg-blue-50/50 px-4 py-3">
                        <div class="flex items-center justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="truncate font-medium text-gray-900">{{ latestCatalogTask.file_name }}</span>
                                    <el-tag :type="latestCatalogTask.status_type" effect="plain">{{ latestCatalogTask.status_name }}</el-tag>
                                </div>
                                <div class="mt-1 text-xs text-gray-500">{{ latestCatalogTask.message || '后台任务已创建' }}</div>
                            </div>
                            <div class="w-[280px]">
                                <el-progress :percentage="Number(latestCatalogTask.progress || 0)" :stroke-width="8" />
                                <div class="mt-1 text-right text-xs text-gray-400">{{ latestCatalogTask.processed_rows || 0 }} / {{ latestCatalogTask.total_rows || 0 }} 行</div>
                            </div>
                        </div>
                    </div>

                    <div class="catalog-summary-grid">
                        <div><span>产品模板</span><strong>{{ catalogSummary.products || 0 }}</strong></div>
                        <div><span>启用产品</span><strong>{{ catalogSummary.enabled || 0 }}</strong></div>
                        <div><span>品牌</span><strong>{{ catalogSummary.brands || 0 }}</strong></div>
                        <div><span>系列</span><strong>{{ catalogSummary.series || 0 }}</strong></div>
                    </div>

                    <div class="catalog-filter-bar">
                        <el-input v-model.trim="catalogQuery.keyword" :prefix-icon="Search" clearable placeholder="搜索型号、品牌或系列" class="!w-[300px]" @keyup.enter="loadCatalog(true)" />
                        <el-select v-model="catalogQuery.brand_name" clearable filterable placeholder="全部品牌" class="!w-[160px]">
                            <el-option v-for="name in catalogFilters.brands" :key="name" :label="name" :value="name" />
                        </el-select>
                        <el-select v-model="catalogQuery.series_name" clearable filterable placeholder="全部系列" class="!w-[180px]">
                            <el-option v-for="name in catalogFilters.series" :key="name" :label="name" :value="name" />
                        </el-select>
                        <el-button type="primary" @click="loadCatalog(true)">查询</el-button>
                        <el-button @click="resetCatalogFilter">重置</el-button>
                    </div>

                    <div class="catalog-tree-shell" v-loading="catalogLoading">
                        <div class="catalog-tree-head">
                            <div>
                                <strong>产品目录树</strong>
                                <span>按“一级品类 → 可选一级子分类 → 品牌 → 系列 → 型号”逐级展开；排序值越大越靠前</span>
                            </div>
                            <el-tag effect="plain" type="info">目录唯一，不再维护旧分类表</el-tag>
                        </div>
                        <el-empty v-if="!catalogTree.length && !catalogLoading" description="暂无产品目录，可直接导入 Excel" />
                        <el-tree
                            v-else
                            :key="catalogTreeKey"
                            :data="catalogTree"
                            :props="catalogTreeProps"
                            :load="loadCatalogTreeNode"
                            lazy
                            node-key="node_key"
                            :expand-on-click-node="false"
                            class="catalog-product-tree"
                        >
                            <template #default="{ data }">
                                <div class="catalog-tree-node" :class="`catalog-tree-node--${data.node_type}`">
                                    <div class="catalog-tree-node__main">
                                        <span class="catalog-tree-node__icon">{{ catalogNodeIcon(data.node_type) }}</span>
                                        <div class="min-w-0">
                                            <div class="catalog-tree-node__title">{{ data.label || '未命名' }}</div>
                                            <div v-if="data.node_type === 'product'" class="catalog-tree-node__meta">
                                                <span v-if="data.path_text">{{ data.path_text }}</span>
                                                <span>排序 {{ Number(data.sort || 0) }}</span>
                                            </div>
                                            <div v-else class="catalog-tree-node__meta">
                                                <span>{{ catalogNodeHint(data.node_type) }}</span>
                                                <span>组内最高排序 {{ Number(data.sort || 0) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="catalog-tree-node__side">
                                        <el-tag v-if="data.node_type !== 'product'" size="small" effect="plain">{{ data.product_count || 0 }} 个型号</el-tag>
                                        <el-tag v-else :type="Number(data.is_enabled) === 1 ? 'success' : 'info'" size="small">{{ Number(data.is_enabled) === 1 ? '启用' : '停用' }}</el-tag>
                                        <el-button link type="primary" @click.stop="openCatalogSort(data)">排序</el-button>
                                        <template v-if="data.node_type === 'product'">
                                            <el-button link type="primary" @click.stop="openCatalogProduct(data)">编辑</el-button>
                                            <el-button link type="danger" @click.stop="removeCatalogProduct(data)">删除</el-button>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </el-tree>
                    </div>
                </el-tab-pane>

                <el-tab-pane label="规格" name="spec">
                    <div class="mb-4 flex justify-end">
                        <el-button type="primary" :icon="Plus" @click="openGroup()">新增规格组</el-button>
                    </div>

                    <el-table :data="specGroups" v-loading="specLoading" row-key="id" size="large">
                        <el-table-column type="expand">
                            <template #default="{ row }">
                                <div class="px-12 py-3">
                                    <div class="mb-3 flex items-center justify-between">
                                        <span class="font-medium">{{ row.label }} 规格值</span>
                                        <el-button type="primary" link :icon="Plus" @click="openItem(row)">新增规格值</el-button>
                                    </div>
                                    <el-table :data="row.items || []" border size="small" empty-text="暂无规格值">
                                        <el-table-column prop="label" label="规格值" min-width="180" />
                                        <el-table-column prop="sort" label="排序" width="100" />
                                        <el-table-column label="操作" width="150" align="center">
                                            <template #default="{ row: item }">
                                                <el-button type="primary" link @click="openItem(row, item)">编辑</el-button>
                                                <el-button type="danger" link @click="removeItem(item)">删除</el-button>
                                            </template>
                                        </el-table-column>
                                    </el-table>
                                </div>
                            </template>
                        </el-table-column>
                        <el-table-column prop="label" label="规格组" min-width="180" />
                        <el-table-column label="参与标题" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.title_part ? 'success' : 'info'">{{ row.title_part ? '参与' : '不参与' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="规格值数量" width="120">
                            <template #default="{ row }">{{ row.items?.length || 0 }}</template>
                        </el-table-column>
                        <el-table-column prop="sort" label="排序" width="100" />
                        <el-table-column label="操作" width="180" align="center">
                            <template #default="{ row }">
                                <el-button type="primary" link @click="openGroup(row)">编辑</el-button>
                                <el-button type="danger" link @click="removeGroup(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>

                <el-tab-pane label="成色" name="grade">
                    <div class="mb-4 flex justify-end">
                        <el-button type="primary" :icon="Plus" @click="openGrade()">新增成色</el-button>
                    </div>
                    <el-table :data="grades" v-loading="specLoading" row-key="id" size="large">
                        <el-table-column prop="label" label="成色名称" min-width="220" />
                        <el-table-column prop="sort" label="排序" width="100" />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag type="success">启用</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="160" align="center">
                            <template #default="{ row }">
                                <el-button type="primary" link @click="openGrade(row)">编辑</el-button>
                                <el-button type="danger" link @click="removeGrade(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <HsxDialog :confirm-loading="groupDialog.loading" v-model="groupDialog.visible" :title="groupDialog.form.id ? '编辑规格组' : '新增规格组'" width="520px" :destroy-on-close="false">
            <el-form label-width="100px">
                <el-form-item label="规格名称" required><el-input v-model.trim="groupDialog.form.label" placeholder="如：苹果内存、安卓内存、颜色" /></el-form-item>
                <el-form-item label="参与标题"><el-switch v-model="groupDialog.form.title_part" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="groupDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="groupDialog.loading" @click="groupDialog.visible = false">取消</el-button>
                <el-button :disabled="groupDialog.loading" type="primary" :loading="groupDialog.loading" @click="submitGroup">保存</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="itemDialog.loading" v-model="itemDialog.visible" :title="itemDialog.form.id ? '编辑规格值' : '新增规格值'" width="520px" :destroy-on-close="false">
            <el-form label-width="100px">
                <el-form-item label="所属规格">{{ itemDialog.groupLabel }}</el-form-item>
                <el-form-item label="规格值" required><el-input v-model.trim="itemDialog.form.item_value" placeholder="如：128G、8+256G" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="itemDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="itemDialog.loading" @click="itemDialog.visible = false">取消</el-button>
                <el-button :disabled="itemDialog.loading" type="primary" :loading="itemDialog.loading" @click="submitItem">保存</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="gradeDialog.loading" v-model="gradeDialog.visible" :title="gradeDialog.form.id ? '编辑成色' : '新增成色'" width="520px" :destroy-on-close="false">
            <el-form label-width="100px">
                <el-form-item label="成色名称" required><el-input v-model.trim="gradeDialog.form.grade_name" placeholder="如：99新、全新、小花" /></el-form-item>
                <el-form-item label="状态"><el-switch v-model="gradeDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="gradeDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="gradeDialog.loading" @click="gradeDialog.visible = false">取消</el-button>
                <el-button :disabled="gradeDialog.loading" type="primary" :loading="gradeDialog.loading" @click="submitGrade">保存</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="catalogProductDialog.loading" v-model="catalogProductDialog.visible" :title="catalogProductDialog.form.site_product_id ? '编辑商品型号' : '新增商品型号'" width="620px" append-to-body destroy-on-close>
            <HsxNotice default-expanded type="info" :closable="false" show-icon class="mb-5">
                <template #title>排序值越大越靠前；品类、品牌和系列节点按其子型号的最高排序值排列。</template>
            </HsxNotice>
            <el-form label-width="104px">
                <el-form-item label="商品品类" required>
                    <el-input v-model.trim="catalogProductDialog.form.category_path" maxlength="255" placeholder="一级品类，或 一级品类/子品类" />
                    <div class="mt-1 text-xs text-gray-400">最多两层，例如：手机 或 智能数码/智能手表</div>
                </el-form-item>
                <el-form-item label="品牌"><el-input v-model.trim="catalogProductDialog.form.brand_name" maxlength="100" placeholder="例如：苹果" /></el-form-item>
                <el-form-item label="系列"><el-input v-model.trim="catalogProductDialog.form.series_name" maxlength="100" placeholder="例如：iPhone 17系列" /></el-form-item>
                <el-form-item label="商品型号" required><el-input v-model.trim="catalogProductDialog.form.product_name" maxlength="150" placeholder="例如：苹果 iPhone 17 Pro Max" /></el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="catalogProductDialog.form.sort" :min="-999999" :max="999999" class="!w-[200px]" />
                </el-form-item>
                <el-form-item label="状态"><el-switch v-model="catalogProductDialog.form.is_enabled" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="catalogProductDialog.loading" @click="catalogProductDialog.visible = false">取消</el-button>
                <el-button :disabled="catalogProductDialog.loading" type="primary" :loading="catalogProductDialog.loading" @click="submitCatalogProduct">保存</el-button>
            </template>
        </HsxDialog>

        <HsxDialog :confirm-loading="catalogSortDialog.loading" v-model="catalogSortDialog.visible" title="调整目录排序" width="460px" append-to-body :destroy-on-close="false">
            <div class="mb-4 rounded-md bg-gray-50 px-4 py-3">
                <div class="text-xs text-gray-400">当前节点</div>
                <div class="mt-1 font-medium text-gray-900">{{ catalogSortDialog.node.label || '未命名' }}</div>
                <div v-if="catalogSortDialog.node.node_type !== 'product'" class="mt-1 text-xs text-gray-500">调整分组时会整体平移子型号排序，并保留组内原有顺序。</div>
            </div>
            <el-form label-width="92px">
                <el-form-item label="排序值">
                    <el-input-number v-model="catalogSortDialog.sort" :min="-999999" :max="999999" class="!w-[220px]" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button :disabled="catalogSortDialog.loading" @click="catalogSortDialog.visible = false">取消</el-button>
                <el-button :disabled="catalogSortDialog.loading" type="primary" :loading="catalogSortDialog.loading" @click="submitCatalogSort">保存排序</el-button>
            </template>
        </HsxDialog>

        <HsxDialog v-model="catalogTaskDialogVisible" title="商品目录导入记录" width="960px" append-to-body :destroy-on-close="false">
            <div class="mb-3 flex items-center justify-between">
                <div class="text-sm text-gray-500">Excel 由后台每 500 行分批处理，关闭窗口不会中断。</div>
                <el-button :loading="catalogTaskLoading" @click="loadCatalogTasks">刷新</el-button>
            </div>
            <el-table v-loading="catalogTaskLoading" :data="catalogTasks" height="460" empty-text="暂无商品目录导入任务">
                <el-table-column label="文件" min-width="190" show-overflow-tooltip>
                    <template #default="{ row }">
                        <div class="font-medium text-gray-900">{{ row.file_name }}</div>
                        <div class="mt-1 text-xs text-gray-400">{{ row.operator_name || '未知操作人' }} · {{ row.create_at_text }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }"><el-tag :type="row.status_type" effect="plain">{{ row.status_name }}</el-tag></template>
                </el-table-column>
                <el-table-column label="进度" min-width="210">
                    <template #default="{ row }">
                        <el-progress :percentage="Number(row.progress || 0)" :stroke-width="8" />
                        <div class="mt-1 text-xs text-gray-400">{{ row.processed_rows || 0 }} / {{ row.total_rows || 0 }} 行</div>
                    </template>
                </el-table-column>
                <el-table-column label="导入结果" min-width="190">
                    <template #default="{ row }">
                        <div>新增 {{ row.created_count || 0 }} · 更新 {{ row.updated_count || 0 }}</div>
                        <div class="mt-1 text-xs text-gray-400">新增产品 {{ row.created_count || 0 }} · 更新 {{ row.updated_count || 0 }} · 冲突 {{ row.result_json?.master_conflicts || 0 }} · 跳过 {{ row.skipped_count || 0 }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="提示" min-width="220" show-overflow-tooltip>
                    <template #default="{ row }"><span :class="row.error_message ? 'text-red-500' : 'text-gray-500'">{{ row.error_message || row.message || '-' }}</span></template>
                </el-table-column>
                <el-table-column label="操作" width="130" fixed="right">
                    <template #default="{ row }">
                        <el-button v-if="['pending', 'failed'].includes(row.status)" link type="primary" @click="retryCatalogTask(row)">重试</el-button>
                        <el-button v-if="!['queued', 'processing'].includes(row.status)" link type="danger" @click="removeCatalogTask(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-4 flex justify-end">
                <el-pagination
                    v-model:current-page="catalogTaskPage.page"
                    :page-size="catalogTaskPage.limit"
                    :total="catalogTaskPage.total"
                    layout="total, prev, pager, next"
                    @current-change="loadCatalogTasks"
                />
            </div>
            <template #footer><el-button @click="catalogTaskDialogVisible = false">关闭</el-button></template>
        </HsxDialog>
    </HsxPage>
</template>

<script setup lang="ts">
import { HsxTitle, HsxPage, HsxDialog, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { ElMessageBox } from 'element-plus'
import { Download, Plus, Refresh, Search, Upload } from '@element-plus/icons-vue'
import * as XLSX from 'xlsx'
import {
    deleteErpGoodsCatalogProduct,
    deleteErpGoodsGrade,
    deleteErpGoodsSpecGroup,
    deleteErpGoodsSpecItem,
    exportErpGoodsCatalog,
    deleteErpGoodsCatalogImportTask,
    getErpGoodsCatalogHierarchy,
    getErpGoodsCatalogSummary,
    getErpGoodsCatalogImportTasks,
    getErpGoodsSpecMeta,
    retryErpGoodsCatalogImportTask,
    saveErpGoodsCatalogProduct,
    saveErpGoodsGrade,
    saveErpGoodsSpecGroup,
    saveErpGoodsSpecItem,
    sortErpGoodsCatalogNode,
    uploadErpGoodsCatalogImport
} from '@/addon/hsx_erp/api/erp'
const hsxFeedback = useFeedback()


const activeTab = ref('catalog')
const loading = ref(false)
const specLoading = ref(false)
const catalogLoading = ref(false)
const catalogExporting = ref(false)
const catalogImporting = ref(false)
const catalogFileInput = ref<HTMLInputElement>()
const catalogTaskDialogVisible = ref(false)
const catalogTaskLoading = ref(false)
const catalogTasks = ref<any[]>([])
const catalogTaskPage = reactive({ page: 1, limit: 10, total: 0 })
const catalogTaskHadRunning = ref(false)
let catalogTaskTimer: ReturnType<typeof window.setInterval> | null = null
const catalogTree = ref<any[]>([])
const catalogTreeKey = ref(0)
const catalogTreeProps = { label: 'label', children: 'child_list', isLeaf: 'is_leaf' }
const catalogSummary = ref<any>({ products: 0, enabled: 0, brands: 0, series: 0 })
const catalogFilters = ref<any>({ brands: [], series: [] })
const catalogQuery = reactive<any>({ keyword: '', brand_name: '', series_name: '' })
const catalogProductDialog = reactive<any>({
    visible: false,
    loading: false,
    form: {
        site_product_id: 0,
        category_path: '',
        brand_name: '',
        series_name: '',
        product_name: '',
        sort: 0,
        is_enabled: 1
    }
})
const catalogSortDialog = reactive<any>({ visible: false, loading: false, sort: 0, node: {} })
const specGroups = ref<any[]>([])
const grades = ref<any[]>([])
const groupDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { id: 0, label: '', title_part: 1, sort: 0 }
})
const itemDialog = reactive<any>({
    visible: false,
    loading: false,
    groupLabel: '',
    form: { id: 0, group_id: 0, item_value: '', sort: 0 }
})
const gradeDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { id: 0, grade_name: '', status: 1, sort: 0 }
})

const latestCatalogTask = computed(() => catalogTasks.value.find(item => ['queued', 'processing', 'pending'].includes(item.status)) || catalogTasks.value[0] || null)

onMounted(loadAll)
onBeforeUnmount(() => {
    stopCatalogTaskPolling()
})

async function loadAll() {
    loading.value = true
    try {
        await Promise.all([loadSpecs(), loadCatalog(), loadCatalogSummary(), loadCatalogTasks()])
    } finally {
        loading.value = false
    }
}

function openCatalogImport() {
    catalogFileInput.value?.click()
}

async function handleCatalogFile(event: Event) {
    const input = event.target as HTMLInputElement
    const file = input.files?.[0]
    if (!file) return
    try {
        await submitCatalogFile(file)
    } finally {
        input.value = ''
    }
}

async function submitCatalogFile(file: File) {
    await ElMessageBox.confirm(
        `将“${file.name}”创建为后台导入任务。系统会自动识别表头并每 500 行分批处理，不再限制 10000 条，是否继续？`,
        '异步导入商品目录',
        { type: 'info', confirmButtonText: '创建导入任务' }
    )
    catalogImporting.value = true
    try {
        const formData = new FormData()
        formData.append('file', file)
        formData.append('source_key', 'excel_product_catalog')
        const res: any = await uploadErpGoodsCatalogImport(formData)
        const result = res?.data || {}
        if (result.async) hsxFeedback.success(result.message || '商品目录已进入后台导入队列')
        else hsxFeedback.warning(result.message || '任务已创建，请检查后台队列配置')
        activeTab.value = 'catalog'
        catalogTaskDialogVisible.value = true
        catalogTaskPage.page = 1
        await loadCatalogTasks()
    } finally {
        catalogImporting.value = false
    }
}

async function openCatalogTaskDialog() {
    catalogTaskDialogVisible.value = true
    catalogTaskPage.page = 1
    await loadCatalogTasks()
}

async function loadCatalogTasks() {
    if (catalogTaskLoading.value) return
    catalogTaskLoading.value = true
    try {
        const res: any = await getErpGoodsCatalogImportTasks({ page: catalogTaskPage.page, limit: catalogTaskPage.limit })
        const data = res?.data || {}
        catalogTasks.value = data.data || data.list || []
        catalogTaskPage.total = Number(data.total || catalogTasks.value.length)
        const hasRunning = catalogTasks.value.some(item => ['queued', 'processing'].includes(item.status))
        if (catalogTaskHadRunning.value && !hasRunning) await Promise.all([loadCatalog(true), loadCatalogSummary()])
        catalogTaskHadRunning.value = hasRunning
        if (hasRunning) startCatalogTaskPolling()
        else stopCatalogTaskPolling()
    } finally {
        catalogTaskLoading.value = false
    }
}

async function retryCatalogTask(row: any) {
    const res: any = await retryErpGoodsCatalogImportTask(row.id)
    const result = res?.data || {}
    if (result.async) hsxFeedback.success(result.message || '任务已重新进入队列')
    else hsxFeedback.warning(result.message || '任务暂未进入队列')
    await loadCatalogTasks()
}

async function removeCatalogTask(row: any) {
    await ElMessageBox.confirm(`删除“${row.file_name}”的导入记录和原始文件？已导入的目录数据不会删除。`, '删除导入记录', { type: 'warning' })
    await deleteErpGoodsCatalogImportTask(row.id)
    await loadCatalogTasks()
}

function startCatalogTaskPolling() {
    if (catalogTaskTimer) return
    catalogTaskTimer = window.setInterval(() => {
        if (!catalogTaskLoading.value) loadCatalogTasks()
    }, 2000)
}

function stopCatalogTaskPolling() {
    if (!catalogTaskTimer) return
    window.clearInterval(catalogTaskTimer)
    catalogTaskTimer = null
}

async function loadCatalog(_reset = false) {
    catalogLoading.value = true
    try {
        const res: any = await getErpGoodsCatalogHierarchy({
            node_type: 'root',
            ...catalogQuery,
            limit: 300,
            include_filters: 1,
            include_disabled: 1
        })
        const data = res?.data || {}
        catalogTree.value = Array.isArray(data.list) ? data.list : []
        catalogFilters.value = data.filters || { brands: [], series: [] }
        catalogTreeKey.value++
    } finally {
        catalogLoading.value = false
    }
}

async function loadCatalogTreeNode(node: any, resolve: (rows: any[]) => void) {
    if (Number(node.level || 0) === 0) return resolve(catalogTree.value)
    const data = node.data || {}
    if (Number(data.is_leaf || 0) === 1) return resolve([])
    try {
        const res: any = await getErpGoodsCatalogHierarchy({
            node_type: data.node_type,
            category_path: data.category_path || '',
            brand_name: data.brand_name || '',
            series_name: data.series_name || '',
            limit: 500,
            include_disabled: 1
        })
        resolve(Array.isArray(res?.data?.list) ? res.data.list : [])
    } catch {
        resolve([])
    }
}

async function loadCatalogSummary() {
    const res: any = await getErpGoodsCatalogSummary()
    catalogSummary.value = res?.data || { products: 0, enabled: 0, brands: 0, series: 0 }
}

function resetCatalogFilter() {
    Object.assign(catalogQuery, { keyword: '', brand_name: '', series_name: '' })
    loadCatalog(true)
}

function catalogNodeIcon(nodeType: string) {
    return ({ category: '类', brand: '牌', series: '系', product: '型' } as Record<string, string>)[nodeType] || '目'
}

function catalogNodeHint(nodeType: string) {
    return ({ category: '商品品类', brand: '品牌', series: '产品系列' } as Record<string, string>)[nodeType] || '目录节点'
}

function openCatalogProduct(row: any = {}) {
    Object.assign(catalogProductDialog.form, {
        site_product_id: Number(row.site_product_id || 0),
        category_path: row.category_path || '',
        brand_name: row.brand_name || '',
        series_name: row.series_name || '',
        product_name: row.product_name || row.label || '',
        sort: Number(row.sort || 0),
        is_enabled: Number(row.is_enabled ?? 1) === 0 ? 0 : 1
    })
    catalogProductDialog.visible = true
}

async function submitCatalogProduct() {
    const form = catalogProductDialog.form
    if (!String(form.category_path || '').trim()) return hsxFeedback.warning('请输入商品品类')
    if (!String(form.product_name || '').trim()) return hsxFeedback.warning('请输入商品型号')
    catalogProductDialog.loading = true
    try {
        await saveErpGoodsCatalogProduct(Number(form.site_product_id || 0), {
            category_path: String(form.category_path || '').trim(),
            brand_name: String(form.brand_name || '').trim(),
            series_name: String(form.series_name || '').trim(),
            product_name: String(form.product_name || '').trim(),
            sort: Number(form.sort || 0),
            is_enabled: Number(form.is_enabled) === 0 ? 0 : 1
        })
        catalogProductDialog.visible = false
        await Promise.all([loadCatalog(true), loadCatalogSummary()])
    } finally {
        catalogProductDialog.loading = false
    }
}

async function removeCatalogProduct(row: any) {
    await ElMessageBox.confirm(
        `确认删除型号“${row.label || row.product_name || ''}”？已进入采购或库存业务的型号不能删除，只能停用。`,
        '删除商品型号',
        { type: 'warning', confirmButtonText: '确认删除' }
    )
    await deleteErpGoodsCatalogProduct(Number(row.site_product_id || 0))
    await Promise.all([loadCatalog(true), loadCatalogSummary()])
}

function openCatalogSort(row: any) {
    catalogSortDialog.node = { ...row }
    catalogSortDialog.sort = Number(row.sort || 0)
    catalogSortDialog.visible = true
}

async function submitCatalogSort() {
    const node = catalogSortDialog.node || {}
    catalogSortDialog.loading = true
    try {
        await sortErpGoodsCatalogNode({
            node_type: node.node_type || '',
            category_path: node.category_path || '',
            brand_name: node.brand_name || '',
            series_name: node.series_name || '',
            site_product_id: Number(node.site_product_id || 0),
            sort: Number(catalogSortDialog.sort || 0)
        })
        catalogSortDialog.visible = false
        await loadCatalog(true)
    } finally {
        catalogSortDialog.loading = false
    }
}

async function exportCatalog() {
    catalogExporting.value = true
    try {
        const res: any = await exportErpGoodsCatalog()
        const rows = (res?.data || []).map((row: any) => ({
            品类: row.category_path || '', 品类ID: row.category_source_id || '',
            品牌: row.brand_name || '', 品牌ID: row.brand_source_id || '',
            系列: row.series_name || '', 型号: row.product_name || '', 产品ID: row.source_product_id || '',
            数据来源: row.source_key || '', 是否启用: Number(row.is_enabled ?? 1), 排序: Number(row.sort || 0)
        }))
        const workbook = XLSX.utils.book_new()
        XLSX.utils.book_append_sheet(workbook, XLSX.utils.json_to_sheet(rows), '商品目录')
        XLSX.writeFile(workbook, `ERP商品目录_${new Date().toISOString().slice(0, 10)}.xlsx`)
    } finally {
        catalogExporting.value = false
    }
}

async function loadSpecs() {
    specLoading.value = true
    try {
        const res: any = await getErpGoodsSpecMeta()
        specGroups.value = res?.data?.groups || []
        grades.value = res?.data?.grades || []
    } finally {
        specLoading.value = false
    }
}

function openGroup(row: any = {}) {
    Object.assign(groupDialog.form, {
        id: Number(row.id || 0),
        label: row.label || '',
        title_part: row.title_part ? 1 : 0,
        sort: row.sort ?? 0
    })
    groupDialog.visible = true
}

async function submitGroup() {
    if (!groupDialog.form.label) return hsxFeedback.warning('请填写规格名称')
    groupDialog.loading = true
    try {
        await saveErpGoodsSpecGroup(groupDialog.form.id, { ...groupDialog.form })
        hsxFeedback.success('规格组已保存')
        groupDialog.visible = false
        await loadSpecs()
    } finally {
        groupDialog.loading = false
    }
}

async function removeGroup(row: any) {
    await ElMessageBox.confirm(`确认删除规格组「${row.label}」？组内规格值也会删除。`, '删除规格组', { type: 'warning' })
    await deleteErpGoodsSpecGroup(Number(row.id))
    hsxFeedback.success('规格组已删除')
    await loadSpecs()
}

function openItem(group: any, item: any = {}) {
    itemDialog.groupLabel = group.label
    Object.assign(itemDialog.form, {
        id: Number(item.id || 0),
        group_id: Number(group.id),
        item_value: item.label || item.value || '',
        sort: item.sort ?? 0
    })
    itemDialog.visible = true
}

async function submitItem() {
    if (!itemDialog.form.item_value) return hsxFeedback.warning('请填写规格值')
    itemDialog.loading = true
    try {
        await saveErpGoodsSpecItem(itemDialog.form.id, { ...itemDialog.form })
        hsxFeedback.success('规格值已保存')
        itemDialog.visible = false
        await loadSpecs()
    } finally {
        itemDialog.loading = false
    }
}

async function removeItem(row: any) {
    await ElMessageBox.confirm(`确认删除规格值「${row.label}」？`, '删除规格值', { type: 'warning' })
    await deleteErpGoodsSpecItem(Number(row.id))
    hsxFeedback.success('规格值已删除')
    await loadSpecs()
}

function openGrade(row: any = {}) {
    Object.assign(gradeDialog.form, {
        id: Number(row.id || 0),
        grade_name: row.label || row.value || '',
        status: row.status ?? 1,
        sort: row.sort ?? 0
    })
    gradeDialog.visible = true
}

async function submitGrade() {
    if (!gradeDialog.form.grade_name) return hsxFeedback.warning('请填写成色名称')
    gradeDialog.loading = true
    try {
        await saveErpGoodsGrade(gradeDialog.form.id, { ...gradeDialog.form })
        hsxFeedback.success('成色已保存')
        gradeDialog.visible = false
        await loadSpecs()
    } finally {
        gradeDialog.loading = false
    }
}

async function removeGrade(row: any) {
    await ElMessageBox.confirm(`确认删除成色「${row.label}」？`, '删除成色', { type: 'warning' })
    await deleteErpGoodsGrade(Number(row.id))
    hsxFeedback.success('成色已删除')
    await loadSpecs()
}
</script>

<style scoped>
.catalog-intro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
    border: 1px solid #dbeafe;
    border-radius: 8px;
}

.catalog-summary-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.catalog-summary-grid > div {
    padding: 16px 18px;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.catalog-summary-grid span,
.catalog-summary-grid strong {
    display: block;
}

.catalog-summary-grid span {
    color: #64748b;
    font-size: 13px;
}

.catalog-summary-grid strong {
    margin-top: 6px;
    color: #0f172a;
    font-size: 24px;
}

.catalog-filter-bar {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    margin: 16px 0;
    padding: 12px;
    background: #f8fafc;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
}

.catalog-tree-shell {
    min-height: 360px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
}

.catalog-tree-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 15px 18px;
    background: #f8fafc;
    border-bottom: 1px solid #e5e7eb;
}

.catalog-tree-head strong,
.catalog-tree-head span {
    display: block;
}

.catalog-tree-head strong {
    color: #0f172a;
    font-size: 15px;
}

.catalog-tree-head span {
    margin-top: 3px;
    color: #64748b;
    font-size: 12px;
}

.catalog-product-tree {
    padding: 8px 12px 16px;
    --el-tree-node-content-height: auto;
}

.catalog-product-tree :deep(.el-tree-node__content) {
    min-height: 58px;
    margin-top: 4px;
    padding-right: 10px;
    border: 1px solid transparent;
    border-radius: 7px;
}

.catalog-product-tree :deep(.el-tree-node__content:hover) {
    background: #f8fafc;
    border-color: #e2e8f0;
}

.catalog-product-tree :deep(.el-tree-node__expand-icon) {
    color: #64748b;
    font-size: 15px;
}

.catalog-tree-node {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    min-width: 0;
    gap: 16px;
    padding: 8px 0;
}

.catalog-tree-node__main {
    display: flex;
    align-items: center;
    min-width: 0;
    gap: 10px;
}

.catalog-tree-node__icon {
    display: inline-flex;
    flex: 0 0 30px;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    color: #2563eb;
    font-size: 12px;
    font-weight: 700;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    border-radius: 7px;
}

.catalog-tree-node--brand .catalog-tree-node__icon {
    color: #7c3aed;
    background: #f5f3ff;
    border-color: #ddd6fe;
}

.catalog-tree-node--series .catalog-tree-node__icon {
    color: #b45309;
    background: #fffbeb;
    border-color: #fde68a;
}

.catalog-tree-node--product .catalog-tree-node__icon {
    color: #047857;
    background: #ecfdf5;
    border-color: #a7f3d0;
}

.catalog-tree-node__title {
    overflow: hidden;
    color: #1e293b;
    font-size: 14px;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.catalog-tree-node__meta {
    display: flex;
    overflow: hidden;
    gap: 12px;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.catalog-tree-node__side {
    flex: 0 0 auto;
}

@media (max-width: 1100px) {
    .category-workspace {
        grid-template-columns: 1fr;
    }

    .category-detail-panel {
        position: static;
        min-height: 0;
    }
}

@media (max-width: 760px) {
    .category-toolbar,
    .category-panel-head {
        align-items: stretch;
        flex-direction: column;
    }

    .category-node__count,
    .category-node__quick-actions {
        display: none;
    }

    .catalog-intro {
        align-items: stretch;
        flex-direction: column;
    }

    .catalog-summary-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .catalog-tree-head {
        align-items: flex-start;
        flex-direction: column;
    }
}
</style>
