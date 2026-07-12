<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">商品资料</div>
                    <div class="mt-1 text-sm text-gray-500">管理 ERP 自有分类、规格和成色。分类按三级路径展示，录入和查找更直观。</div>
                </div>
                <el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button>
            </div>

            <el-tabs v-model="activeTab" class="mt-5">
                <el-tab-pane label="分类" name="category">
                    <div v-if="categorySync.providers?.length" class="mb-4 rounded-lg border border-blue-100 bg-blue-50/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex items-center gap-2 font-medium text-gray-900">
                                    ERP 与 {{ categorySync.providers[0]?.name || '商城' }}分类
                                    <el-tag :type="categorySync.config?.initialized ? 'success' : 'warning'" effect="plain">
                                        {{ categorySync.config?.initialized ? '已绑定' : '待初始化' }}
                                    </el-tag>
                                </div>
                                <div class="mt-1 text-sm text-gray-500">
                                    ERP 与商城保留各自分类 ID，通过映射关联。已绑定 {{ categorySync.mapped_count || 0 }} 项<span v-if="categorySync.failed_count">，{{ categorySync.failed_count }} 项异常</span>。
                                </div>
                                <div v-if="!categorySync.config?.initialized" class="mt-2 text-xs text-amber-600">已有商城客户请选择导入；新客户可让 ERP 分类同步到商城。首次操作不会删除任一端分类。</div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <template v-if="!categorySync.config?.initialized">
                                    <el-button :loading="syncLoading" @click="runCategorySync('pull')">已有商城分类，导入并绑定</el-button>
                                    <el-button type="primary" :loading="syncLoading" @click="runCategorySync('push')">ERP 为主，同步到商城</el-button>
                                </template>
                                <el-button v-else type="primary" plain :loading="syncLoading" @click="runCategorySync('reconcile')">双向校准</el-button>
                            </div>
                        </div>
                    </div>
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-2">
                            <el-input v-model.trim="categoryQuery.keyword" clearable placeholder="搜索分类名称或路径" class="!w-[280px]" @keyup.enter="loadCategories" />
                            <el-button @click="loadCategories">查询</el-button>
                            <el-tag effect="plain">共 {{ categoryRows.length }} 个分类</el-tag>
                        </div>
                        <el-button type="primary" :icon="Plus" @click="openCategory()">新增一级分类</el-button>
                    </div>

                    <el-table :data="categoryRows" v-loading="categoryLoading" row-key="category_id" size="large" empty-text="暂无分类">
                        <el-table-column label="一级分类" min-width="160" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.level_names[0] || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="二级分类" min-width="160" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.level_names[1] || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="三级分类" min-width="180" show-overflow-tooltip>
                            <template #default="{ row }">{{ row.level_names[2] || '-' }}</template>
                        </el-table-column>
                        <el-table-column label="来源" width="120">
                            <template #default="{ row }">
                                <el-tag :type="row.source_plugin === 'phone_shop' ? 'success' : 'info'" effect="plain">
                                    {{ row.source_plugin === 'phone_shop' ? '商城' : 'ERP' }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="当前层级" width="100">
                            <template #default="{ row }">{{ row.level }}级</template>
                        </el-table-column>
                        <el-table-column label="显示" width="90">
                            <template #default="{ row }">
                                <el-tag :type="row.is_show === 1 ? 'success' : 'info'">{{ row.is_show === 1 ? '显示' : '隐藏' }}</el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column prop="sort" label="排序" width="90" />
                        <el-table-column label="操作" width="220" fixed="right" align="center">
                            <template #default="{ row }">
                                <el-button type="primary" link @click="openCategory(row)">编辑</el-button>
                                <el-button v-if="Number(row.level || 1) < 3" type="primary" link @click="openCategory({ pid: row.category_id })">新增下级</el-button>
                                <el-button type="danger" link @click="removeCategory(row)">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
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

        <el-dialog v-model="categoryDialog.visible" :title="categoryDialog.form.category_id ? '编辑分类' : '新增分类'" width="560px">
            <el-form label-width="100px">
                <el-form-item label="上级分类">
                    <el-tree-select
                        v-model="categoryDialog.form.pid"
                        :data="categoryParentOptions"
                        :props="{ label: 'category_name', value: 'category_id', children: 'child_list' }"
                        check-strictly
                        clearable
                        class="w-full"
                        node-key="category_id"
                        placeholder="不选则为一级分类"
                    />
                </el-form-item>
                <el-form-item label="分类名称" required><el-input v-model.trim="categoryDialog.form.category_name" maxlength="40" /></el-form-item>
                <el-form-item label="显示"><el-switch v-model="categoryDialog.form.is_show" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="categoryDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="categoryDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="categoryDialog.loading" @click="submitCategory">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="groupDialog.visible" :title="groupDialog.form.id ? '编辑规格组' : '新增规格组'" width="520px">
            <el-form label-width="100px">
                <el-form-item label="规格名称" required><el-input v-model.trim="groupDialog.form.label" placeholder="如：苹果内存、安卓内存、颜色" /></el-form-item>
                <el-form-item label="参与标题"><el-switch v-model="groupDialog.form.title_part" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="groupDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="groupDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="groupDialog.loading" @click="submitGroup">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="itemDialog.visible" :title="itemDialog.form.id ? '编辑规格值' : '新增规格值'" width="520px">
            <el-form label-width="100px">
                <el-form-item label="所属规格">{{ itemDialog.groupLabel }}</el-form-item>
                <el-form-item label="规格值" required><el-input v-model.trim="itemDialog.form.item_value" placeholder="如：128G、8+256G" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="itemDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="itemDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="itemDialog.loading" @click="submitItem">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="gradeDialog.visible" :title="gradeDialog.form.id ? '编辑成色' : '新增成色'" width="520px">
            <el-form label-width="100px">
                <el-form-item label="成色名称" required><el-input v-model.trim="gradeDialog.form.grade_name" placeholder="如：99新、全新、小花" /></el-form-item>
                <el-form-item label="状态"><el-switch v-model="gradeDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="gradeDialog.form.sort" :min="0" :controls="false" class="!w-[180px]" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="gradeDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="gradeDialog.loading" @click="submitGrade">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import {
    deleteErpGoodsCategory,
    deleteErpGoodsGrade,
    deleteErpGoodsSpecGroup,
    deleteErpGoodsSpecItem,
    getErpGoodsCategoryTree,
    getErpCategorySyncStatus,
    getErpGoodsSpecMeta,
    saveErpGoodsCategory,
    saveErpGoodsGrade,
    saveErpGoodsSpecGroup,
    saveErpGoodsSpecItem,
    syncErpCategories
} from '@/addon/hsx_erp/api/erp'

const activeTab = ref('category')
const loading = ref(false)
const categoryLoading = ref(false)
const specLoading = ref(false)
const categoryTree = ref<any[]>([])
const categorySync = ref<any>({ providers: [], config: {} })
const syncLoading = ref(false)
const specGroups = ref<any[]>([])
const grades = ref<any[]>([])
const categoryQuery = reactive({ keyword: '' })

const categoryDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { category_id: 0, category_name: '', pid: 0, is_show: 1, sort: 0 }
})
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

const categoryParentOptions = computed(() => {
    const clone = JSON.parse(JSON.stringify(categoryTree.value || []))
    const disabledId = Number(categoryDialog.form.category_id || 0)
    const mark = (rows: any[]) => rows.forEach(row => {
        if (disabledId > 0 && Number(row.category_id) === disabledId) row.disabled = true
        if (Number(row.level || 1) >= 3) row.disabled = true
        if (Array.isArray(row.child_list)) mark(row.child_list)
    })
    mark(clone)
    return clone
})

const categoryRows = computed(() => flattenCategoryRows(categoryTree.value))

onMounted(loadAll)

async function loadAll() {
    loading.value = true
    try {
        await Promise.all([loadCategories(), loadSpecs(), loadCategorySyncStatus()])
    } finally {
        loading.value = false
    }
}

async function loadCategorySyncStatus() {
    const res: any = await getErpCategorySyncStatus()
    categorySync.value = res?.data || { providers: [], config: {} }
}

async function runCategorySync(action: 'pull' | 'push' | 'reconcile') {
    const wording = action === 'pull' ? '从商城导入并绑定分类' : action === 'push' ? '将 ERP 分类同步到商城' : '双向校准 ERP 与商城分类'
    await ElMessageBox.confirm(`${wording}？系统按分类路径匹配，不会删除任一端已有分类。`, '分类同步确认', {
        type: 'warning', confirmButtonText: '确认执行'
    })
    syncLoading.value = true
    try {
        const provider = categorySync.value.providers?.[0]?.key || 'phone_shop'
        const res: any = await syncErpCategories({ action, provider })
        const result = res?.data || {}
        ElMessage.success(`同步完成：导入 ${result.pulled || 0}，推送 ${result.pushed || 0}，异常 ${result.failed || 0}`)
        await Promise.all([loadCategories(), loadCategorySyncStatus()])
    } finally {
        syncLoading.value = false
    }
}

async function loadCategories() {
    categoryLoading.value = true
    try {
        const res: any = await getErpGoodsCategoryTree({ keyword: categoryQuery.keyword })
        categoryTree.value = Array.isArray(res?.data) ? res.data : []
    } finally {
        categoryLoading.value = false
    }
}

function flattenCategoryRows(rows: any[], parents: string[] = []): any[] {
    const list: any[] = []
    ;(rows || []).forEach(row => {
        const names = [...parents, row.category_name || '']
        list.push({ ...row, level_names: names })
        if (Array.isArray(row.child_list) && row.child_list.length) {
            list.push(...flattenCategoryRows(row.child_list, names))
        }
    })
    return list
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

function openCategory(row: any = {}) {
    Object.assign(categoryDialog.form, {
        category_id: Number(row.category_id || 0),
        category_name: row.category_name || '',
        pid: Number(row.pid || 0),
        is_show: row.is_show ?? 1,
        sort: row.sort ?? 0
    })
    categoryDialog.visible = true
}

async function submitCategory() {
    if (!categoryDialog.form.category_name) return ElMessage.warning('请填写分类名称')
    categoryDialog.loading = true
    try {
        await saveErpGoodsCategory(categoryDialog.form.category_id, {
            category_name: categoryDialog.form.category_name,
            pid: categoryDialog.form.pid || 0,
            is_show: categoryDialog.form.is_show,
            sort: categoryDialog.form.sort,
            source_plugin: 'erp'
        })
        ElMessage.success('分类已保存')
        categoryDialog.visible = false
        await loadCategories()
    } finally {
        categoryDialog.loading = false
    }
}

async function removeCategory(row: any) {
    await ElMessageBox.confirm(`确认删除分类「${row.category_full_name || row.category_name}」？子分类也会一并删除。`, '删除分类', { type: 'warning' })
    await deleteErpGoodsCategory(Number(row.category_id))
    ElMessage.success('分类已删除')
    await loadCategories()
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
    if (!groupDialog.form.label) return ElMessage.warning('请填写规格名称')
    groupDialog.loading = true
    try {
        await saveErpGoodsSpecGroup(groupDialog.form.id, { ...groupDialog.form })
        ElMessage.success('规格组已保存')
        groupDialog.visible = false
        await loadSpecs()
    } finally {
        groupDialog.loading = false
    }
}

async function removeGroup(row: any) {
    await ElMessageBox.confirm(`确认删除规格组「${row.label}」？组内规格值也会删除。`, '删除规格组', { type: 'warning' })
    await deleteErpGoodsSpecGroup(Number(row.id))
    ElMessage.success('规格组已删除')
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
    if (!itemDialog.form.item_value) return ElMessage.warning('请填写规格值')
    itemDialog.loading = true
    try {
        await saveErpGoodsSpecItem(itemDialog.form.id, { ...itemDialog.form })
        ElMessage.success('规格值已保存')
        itemDialog.visible = false
        await loadSpecs()
    } finally {
        itemDialog.loading = false
    }
}

async function removeItem(row: any) {
    await ElMessageBox.confirm(`确认删除规格值「${row.label}」？`, '删除规格值', { type: 'warning' })
    await deleteErpGoodsSpecItem(Number(row.id))
    ElMessage.success('规格值已删除')
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
    if (!gradeDialog.form.grade_name) return ElMessage.warning('请填写成色名称')
    gradeDialog.loading = true
    try {
        await saveErpGoodsGrade(gradeDialog.form.id, { ...gradeDialog.form })
        ElMessage.success('成色已保存')
        gradeDialog.visible = false
        await loadSpecs()
    } finally {
        gradeDialog.loading = false
    }
}

async function removeGrade(row: any) {
    await ElMessageBox.confirm(`确认删除成色「${row.label}」？`, '删除成色', { type: 'warning' })
    await deleteErpGoodsGrade(Number(row.id))
    ElMessage.success('成色已删除')
    await loadSpecs()
}
</script>
