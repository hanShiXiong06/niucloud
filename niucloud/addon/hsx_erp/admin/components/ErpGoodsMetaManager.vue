<template>
    <div>
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <div class="text-base font-semibold text-gray-900">商品资料维护</div>
                <div class="mt-1 text-sm text-gray-500">在当前业务流程中维护分类、规格和成色，保存后可直接回到开单继续选择。</div>
            </div>
            <el-button :icon="Refresh" :loading="loading" @click="loadAll">刷新</el-button>
        </div>

        <el-tabs v-model="activeTab">
            <el-tab-pane label="分类" name="category">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <el-input v-model.trim="categoryQuery.keyword" clearable placeholder="搜索分类名称" class="!w-[240px]" @keyup.enter="loadCategories" />
                        <el-select v-model="categoryQuery.level" clearable placeholder="全部层级" class="!w-[130px]" @change="loadCategories">
                            <el-option label="一级" :value="1" />
                            <el-option label="二级" :value="2" />
                            <el-option label="三级" :value="3" />
                        </el-select>
                        <el-button @click="loadCategories">查询</el-button>
                    </div>
                    <el-button type="primary" :icon="Plus" @click="openCategory()">新增分类</el-button>
                </div>

                <el-table :data="categoryTree" v-loading="categoryLoading" row-key="category_id" size="default" default-expand-all max-height="480">
                    <el-table-column prop="category_name" label="分类名称" min-width="180" />
                    <el-table-column prop="category_full_name" label="完整路径" min-width="240" show-overflow-tooltip />
                    <el-table-column label="层级" width="76">
                        <template #default="{ row }">{{ row.level }}级</template>
                    </el-table-column>
                    <el-table-column label="显示" width="86">
                        <template #default="{ row }">
                            <el-tag :type="row.is_show === 1 ? 'success' : 'info'" effect="plain">{{ row.is_show === 1 ? '显示' : '隐藏' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sort" label="排序" width="76" />
                    <el-table-column label="操作" width="210" fixed="right" align="center">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="openCategory(row)">编辑</el-button>
                            <el-button v-if="Number(row.level || 1) < 3" type="primary" link @click="openCategory({ pid: row.category_id })">新增下级</el-button>
                            <el-button type="danger" link @click="removeCategory(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="规格" name="spec">
                <div class="mb-3 flex justify-end">
                    <el-button type="primary" :icon="Plus" @click="openGroup()">新增规格组</el-button>
                </div>

                <el-table :data="specGroups" v-loading="specLoading" row-key="id" size="default" max-height="480">
                    <el-table-column type="expand">
                        <template #default="{ row }">
                            <div class="px-8 py-3">
                                <div class="mb-3 flex items-center justify-between">
                                    <span class="font-medium">{{ row.label }} 规格值</span>
                                    <el-button type="primary" link :icon="Plus" @click="openItem(row)">新增规格值</el-button>
                                </div>
                                <el-table :data="row.items || []" border size="small" empty-text="暂无规格值">
                                    <el-table-column prop="label" label="规格值" min-width="160" />
                                    <el-table-column prop="sort" label="排序" width="90" />
                                    <el-table-column label="操作" width="140" align="center">
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
                    <el-table-column label="参与标题" width="110">
                        <template #default="{ row }">
                            <el-tag :type="row.title_part ? 'success' : 'info'" effect="plain">{{ row.title_part ? '参与' : '不参与' }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column label="规格值" width="100">
                        <template #default="{ row }">{{ row.items?.length || 0 }}</template>
                    </el-table-column>
                    <el-table-column prop="sort" label="排序" width="90" />
                    <el-table-column label="操作" width="160" align="center">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="openGroup(row)">编辑</el-button>
                            <el-button type="danger" link @click="removeGroup(row)">删除</el-button>
                        </template>
                    </el-table-column>
                </el-table>
            </el-tab-pane>

            <el-tab-pane label="成色" name="grade">
                <div class="mb-3 flex justify-end">
                    <el-button type="primary" :icon="Plus" @click="openGrade()">新增成色</el-button>
                </div>
                <el-table :data="grades" v-loading="specLoading" row-key="id" size="default" max-height="480">
                    <el-table-column prop="label" label="成色名称" min-width="220" />
                    <el-table-column prop="sort" label="排序" width="100" />
                    <el-table-column label="状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="Number(row.status ?? 1) === 1 ? 'success' : 'info'" effect="plain">{{ Number(row.status ?? 1) === 1 ? '启用' : '停用' }}</el-tag>
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

        <el-dialog v-model="categoryDialog.visible" :title="categoryDialog.form.category_id ? '编辑分类' : '新增分类'" width="560px" append-to-body>
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

        <el-dialog v-model="groupDialog.visible" :title="groupDialog.form.id ? '编辑规格组' : '新增规格组'" width="520px" append-to-body>
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

        <el-dialog v-model="itemDialog.visible" :title="itemDialog.form.id ? '编辑规格值' : '新增规格值'" width="520px" append-to-body>
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

        <el-dialog v-model="gradeDialog.visible" :title="gradeDialog.form.id ? '编辑成色' : '新增成色'" width="520px" append-to-body>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Plus, Refresh } from '@element-plus/icons-vue'
import {
    deleteErpGoodsCategory,
    deleteErpGoodsGrade,
    deleteErpGoodsSpecGroup,
    deleteErpGoodsSpecItem,
    getErpGoodsCategoryTree,
    getErpGoodsSpecMeta,
    saveErpGoodsCategory,
    saveErpGoodsGrade,
    saveErpGoodsSpecGroup,
    saveErpGoodsSpecItem
} from '@/addon/hsx_erp/api/erp'

const props = withDefaults(defineProps<{ active?: string }>(), { active: 'category' })
const emit = defineEmits<{ (e: 'saved'): void }>()

const activeTab = ref(props.active)
const loading = ref(false)
const categoryLoading = ref(false)
const specLoading = ref(false)
const categoryTree = ref<any[]>([])
const specGroups = ref<any[]>([])
const grades = ref<any[]>([])
const categoryQuery = reactive({ keyword: '', level: '' as any })
const categoryDialog = reactive<any>({ visible: false, loading: false, form: { category_id: 0, category_name: '', pid: 0, is_show: 1, sort: 0 } })
const groupDialog = reactive<any>({ visible: false, loading: false, form: { id: 0, label: '', title_part: 1, sort: 0 } })
const itemDialog = reactive<any>({ visible: false, loading: false, groupLabel: '', form: { id: 0, group_id: 0, item_value: '', sort: 0 } })
const gradeDialog = reactive<any>({ visible: false, loading: false, form: { id: 0, grade_name: '', status: 1, sort: 0 } })

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

watch(() => props.active, value => {
    if (value) activeTab.value = value
})

onMounted(loadAll)

async function loadAll() {
    loading.value = true
    try {
        await Promise.all([loadCategories(), loadSpecs()])
    } finally {
        loading.value = false
    }
}

async function loadCategories() {
    categoryLoading.value = true
    try {
        const res: any = await getErpGoodsCategoryTree({ ...categoryQuery })
        categoryTree.value = Array.isArray(res?.data) ? res.data : []
    } finally {
        categoryLoading.value = false
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
        emit('saved')
    } finally {
        categoryDialog.loading = false
    }
}

async function removeCategory(row: any) {
    await ElMessageBox.confirm(`确认删除分类「${row.category_full_name || row.category_name}」？子分类也会一并删除。`, '删除分类', { type: 'warning' })
    await deleteErpGoodsCategory(Number(row.category_id))
    ElMessage.success('分类已删除')
    await loadCategories()
    emit('saved')
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
        emit('saved')
    } finally {
        groupDialog.loading = false
    }
}

async function removeGroup(row: any) {
    await ElMessageBox.confirm(`确认删除规格组「${row.label}」？组内规格值也会删除。`, '删除规格组', { type: 'warning' })
    await deleteErpGoodsSpecGroup(Number(row.id))
    ElMessage.success('规格组已删除')
    await loadSpecs()
    emit('saved')
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
        emit('saved')
    } finally {
        itemDialog.loading = false
    }
}

async function removeItem(row: any) {
    await ElMessageBox.confirm(`确认删除规格值「${row.label}」？`, '删除规格值', { type: 'warning' })
    await deleteErpGoodsSpecItem(Number(row.id))
    ElMessage.success('规格值已删除')
    await loadSpecs()
    emit('saved')
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
        emit('saved')
    } finally {
        gradeDialog.loading = false
    }
}

async function removeGrade(row: any) {
    await ElMessageBox.confirm(`确认删除成色「${row.label}」？`, '删除成色', { type: 'warning' })
    await deleteErpGoodsGrade(Number(row.id))
    ElMessage.success('成色已删除')
    await loadSpecs()
    emit('saved')
}
</script>
