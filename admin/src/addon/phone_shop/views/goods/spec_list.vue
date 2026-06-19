<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex items-center justify-between">
                <span class="text-page-title">规格管理</span>
                <el-button type="primary" @click="openGroup()">新增规格分组</el-button>
            </div>
            <div class="mt-1 text-sm text-gray-400">把"内存/表盘尺寸/规格"按分类配好，建品时按货源分类自动给对应规格供选择。</div>

            <el-table class="mt-4" :data="groups" v-loading="loading" size="large" empty-text="暂无规格分组，点右上角新增">
                <el-table-column label="绑定分类" min-width="160">
                    <template #default="{ row }">{{ categoryName(row.category_id) }}</template>
                </el-table-column>
                <el-table-column label="规格标签" width="120">
                    <template #default="{ row }"><el-tag effect="plain">{{ row.label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="规格值" min-width="260">
                    <template #default="{ row }">
                        <el-tag v-for="it in row.items" :key="it.item_id" class="mr-1 mb-1" type="info" effect="light">{{ it.item_value }}</el-tag>
                        <span v-if="!row.items || !row.items.length" class="text-gray-300">未配置子项</span>
                    </template>
                </el-table-column>
                <el-table-column label="排序" width="80" prop="sort" />
                <el-table-column label="操作" width="200" align="right" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openItems(row)">管理规格值</el-button>
                        <el-button type="primary" link @click="openGroup(row)">编辑</el-button>
                        <el-button type="danger" link @click="removeGroup(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 分组弹窗 -->
        <el-dialog v-model="groupDialog.visible" :title="groupDialog.form.group_id ? '编辑规格分组' : '新增规格分组'" width="460px">
            <el-form label-width="90px">
                <el-form-item label="绑定分类" required>
                    <el-cascader v-model="groupDialog.catPath" :options="catOptions" :props="cascaderProps" clearable
                        class="w-full" placeholder="选择该规格适用的商品分类" @change="onCatChange" />
                </el-form-item>
                <el-form-item label="规格标签" required>
                    <el-input v-model.trim="groupDialog.form.label" placeholder="手机填「内存」，手表填「表盘尺寸」，也可填「规格」" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="groupDialog.form.sort" :min="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="groupDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="groupDialog.saving" @click="saveGroup">保存</el-button>
            </template>
        </el-dialog>

        <!-- 子项弹窗 -->
        <el-dialog v-model="itemDialog.visible" :title="`管理规格值 · ${itemDialog.label}`" width="460px">
            <div class="flex gap-2">
                <el-input v-model.trim="itemDialog.newValue" placeholder="如 128G / 8+128 / 46MM" @keyup.enter="addItem" />
                <el-button type="primary" :loading="itemDialog.adding" @click="addItem">添加</el-button>
            </div>
            <div class="mt-3">
                <div v-for="it in itemDialog.items" :key="it.item_id" class="mb-2 flex items-center gap-2">
                    <el-input v-model.trim="it.item_value" size="small" class="!w-40" @blur="saveItem(it)" />
                    <el-button type="danger" link size="small" @click="removeItem(it)">删除</el-button>
                </div>
                <div v-if="!itemDialog.items.length" class="text-sm text-gray-300">还没有规格值，上面添加</div>
            </div>
            <template #footer>
                <el-button type="primary" @click="closeItems">完成</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCategoryTree } from '@/addon/phone_shop/api/goods'
import { getSpecGroups, addSpecGroup, editSpecGroup, delSpecGroup, addSpecItem, editSpecItem, delSpecItem } from '@/addon/phone_shop/api/spec'

const loading = ref(false)
const groups = ref<any[]>([])
const catOptions = ref<any[]>([])
const catMap = ref<Record<number, string>>({})
const cascaderProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, emitPath: true }

const categoryName = (id: number) => catMap.value[id] || ('分类#' + id)

const buildCatMap = (list: any[], prefix = '') => {
    list.forEach((c: any) => {
        const name = prefix ? `${prefix} / ${c.category_name}` : c.category_name
        catMap.value[c.category_id] = name
        if (c.child_list && c.child_list.length) buildCatMap(c.child_list, name)
    })
}

const loadCategories = async () => {
    const res: any = await getCategoryTree()
    catOptions.value = res.data || []
    catMap.value = {}
    buildCatMap(catOptions.value)
}

const loadGroups = async () => {
    loading.value = true
    try {
        const res: any = await getSpecGroups()
        groups.value = res.data || []
    } finally {
        loading.value = false
    }
}

// 分组增改
const groupDialog = reactive<any>({ visible: false, saving: false, catPath: [], form: { group_id: 0, category_id: 0, label: '内存', sort: 0 } })
const onCatChange = (path: any) => { groupDialog.form.category_id = Array.isArray(path) && path.length ? Number(path[path.length - 1]) : 0 }
const openGroup = (row: any = null) => {
    if (row) {
        groupDialog.form = { group_id: row.group_id, category_id: row.category_id, label: row.label, sort: row.sort }
        groupDialog.catPath = row.category_id ? [row.category_id] : []
    } else {
        groupDialog.form = { group_id: 0, category_id: 0, label: '内存', sort: 0 }
        groupDialog.catPath = []
    }
    groupDialog.visible = true
}
const saveGroup = async () => {
    if (!groupDialog.form.category_id) return ElMessage.warning('请选择绑定分类')
    if (!groupDialog.form.label) return ElMessage.warning('请填写规格标签')
    groupDialog.saving = true
    try {
        if (groupDialog.form.group_id) await editSpecGroup(groupDialog.form.group_id, groupDialog.form)
        else await addSpecGroup(groupDialog.form)
        groupDialog.visible = false
        loadGroups()
    } finally {
        groupDialog.saving = false
    }
}
const removeGroup = async (row: any) => {
    await ElMessageBox.confirm(`确认删除分组「${row.label}」及其全部规格值吗？`, '提示', { type: 'warning' })
    await delSpecGroup(row.group_id)
    loadGroups()
}

// 子项管理
const itemDialog = reactive<any>({ visible: false, adding: false, group_id: 0, label: '', items: [] as any[], newValue: '' })
const openItems = (row: any) => {
    itemDialog.group_id = row.group_id
    itemDialog.label = row.label
    itemDialog.items = (row.items || []).map((x: any) => ({ ...x }))
    itemDialog.newValue = ''
    itemDialog.visible = true
}
const addItem = async () => {
    if (!itemDialog.newValue) return
    itemDialog.adding = true
    try {
        const res: any = await addSpecItem({ group_id: itemDialog.group_id, item_value: itemDialog.newValue, sort: itemDialog.items.length })
        itemDialog.items.push({ item_id: res.data, item_value: itemDialog.newValue })
        itemDialog.newValue = ''
    } finally {
        itemDialog.adding = false
    }
}
const saveItem = async (it: any) => {
    if (!it.item_value) return
    await editSpecItem(it.item_id, { item_value: it.item_value, sort: it.sort || 0 })
}
const removeItem = async (it: any) => {
    await delSpecItem(it.item_id)
    itemDialog.items = itemDialog.items.filter((x: any) => x.item_id !== it.item_id)
}
const closeItems = () => { itemDialog.visible = false; loadGroups() }

onMounted(() => { loadCategories(); loadGroups() })
</script>
