<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex items-center justify-between">
                <span class="text-page-title">规格管理</span>
                <el-button type="primary" @click="openGroup()">新增规格分组</el-button>
            </div>
            <div class="mt-1 text-sm text-gray-400">分组名称直接显示在商城规格筛选中，例如苹果内存规格、安卓内存规格、手表规格；选项仅来自这里，不再混入商品中的其他值。</div>

            <el-table class="mt-4" :data="groups" v-loading="loading" size="large" empty-text="暂无规格分组，点右上角新增">
                <el-table-column label="绑定分类" min-width="160">
                    <template #default="{ row }">{{ categoryNames(row) }}</template>
                </el-table-column>
                <el-table-column label="分组名称" min-width="170" show-overflow-tooltip>
                    <template #default="{ row }"><el-tag effect="plain">{{ row.label }}</el-tag></template>
                </el-table-column>
                <el-table-column label="规格值" min-width="260">
                    <template #default="{ row }">
                        <el-tag v-for="it in row.items" :key="it.item_id" class="mr-1 mb-1" type="info" effect="light">{{ it.item_value }}</el-tag>
                        <span v-if="!row.items || !row.items.length" class="text-gray-300">未配置子项</span>
                    </template>
                </el-table-column>
                <el-table-column label="排序（小的靠前）" width="140" prop="sort" />
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
                    <el-cascader v-model="groupDialog.catVals" :options="catOptions" :props="cascaderProps" clearable
                        collapse-tags collapse-tags-tooltip class="w-full" placeholder="选择该规格适用的一个或多个分类（品牌）" />
                </el-form-item>
                <el-form-item label="分组名称" required>
                    <el-input v-model.trim="groupDialog.form.label" maxlength="50" show-word-limit placeholder="例如：苹果内存规格、安卓内存规格、手表规格" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="groupDialog.form.sort" :min="0" :max="999999" :precision="0" />
                    <span class="ml-2 text-xs text-gray-400">数字越小越靠前</span>
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="groupDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="groupDialog.saving" @click="saveGroup">保存</el-button>
            </template>
        </el-dialog>

        <!-- 子项弹窗 -->
        <el-dialog v-model="itemDialog.visible" :title="`管理规格值 · ${itemDialog.label}`" width="600px" :close-on-click-modal="false" :before-close="beforeCloseItems">
            <div class="flex gap-2">
                <el-input v-model.trim="itemDialog.newValue" maxlength="50" :disabled="itemDialog.saving" placeholder="如 128G / 8+128 / 46MM" @keyup.enter="addItem" />
                <el-button type="primary" :loading="itemDialog.adding" :disabled="itemDialog.saving" @click="addItem">添加</el-button>
            </div>
            <div class="mt-2 text-xs text-gray-400">按排序数字从小到大显示；相同排序按添加顺序显示。修改后点击保存，或在完成时统一保存。</div>
            <div class="mt-3">
                <div v-for="it in itemDialog.items" :key="it.item_id" class="mb-2 flex items-center gap-2">
                    <el-input v-model.trim="it.item_value" size="small" maxlength="50" :disabled="itemDialog.saving || it._saving" placeholder="规格值" class="min-w-0 flex-1" />
                    <el-input-number v-model="it.sort" :min="0" :max="999999" :precision="0" size="small" :disabled="itemDialog.saving || it._saving" class="!w-[110px]" aria-label="规格排序" />
                    <el-button type="primary" link size="small" :loading="it._saving" :disabled="itemDialog.saving || !itemChanged(it)" @click="saveItem(it)">保存</el-button>
                    <el-button type="danger" link size="small" :disabled="itemDialog.saving || it._saving || it._deleting" @click="removeItem(it)">删除</el-button>
                </div>
                <div v-if="!itemDialog.items.length" class="text-sm text-gray-300">还没有规格值，上面添加</div>
            </div>
            <template #footer>
                <el-button type="primary" :loading="itemDialog.saving" :disabled="itemDialog.adding || itemDialog.items.some((it: any) => it._saving || it._deleting)" @click="closeItems">保存并完成</el-button>
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
const cascaderProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, multiple: true, emitPath: false }

const categoryName = (id: number) => catMap.value[id] || ('分类#' + id)
const categoryNames = (row: any) => {
    const ids = row.category_ids
        ? String(row.category_ids).split(',')
        : (row.category_id ? [String(row.category_id)] : [])
    return ids.map((id: any) => catMap.value[Number(id)] || ('分类#' + id)).join('、') || '—'
}

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
const groupDialog = reactive<any>({ visible: false, saving: false, catVals: [] as number[], form: { group_id: 0, label: '', sort: 0 } })
const openGroup = (row: any = null) => {
    if (row) {
        groupDialog.form = { group_id: row.group_id, label: row.label, sort: row.sort }
        groupDialog.catVals = row.category_ids
            ? String(row.category_ids).split(',').map((x: any) => Number(x)).filter(Boolean)
            : (row.category_id ? [Number(row.category_id)] : [])
    } else {
        groupDialog.form = { group_id: 0, label: '', sort: 0 }
        groupDialog.catVals = []
    }
    groupDialog.visible = true
}
const saveGroup = async () => {
    if (!groupDialog.catVals.length) return ElMessage.warning('请选择绑定分类')
    if (!groupDialog.form.label) return ElMessage.warning('请填写分组名称')
    const payload = {
        ...groupDialog.form,
        category_ids: groupDialog.catVals.join(','),
        category_id: groupDialog.catVals[0]
    }
    groupDialog.saving = true
    try {
        if (groupDialog.form.group_id) await editSpecGroup(groupDialog.form.group_id, payload)
        else await addSpecGroup(payload)
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
const itemDialog = reactive<any>({ visible: false, adding: false, saving: false, group_id: 0, label: '', items: [] as any[], newValue: '' })
const itemChanged = (it: any) => it.item_value !== it._savedValue || Number(it.sort) !== it._savedSort
const itemRow = (it: any) => ({ ...it, sort: Number(it.sort || 0), _savedValue: it.item_value, _savedSort: Number(it.sort || 0), _saving: false, _deleting: false })
const openItems = (row: any) => {
    itemDialog.group_id = row.group_id
    itemDialog.label = row.label
    itemDialog.items = (row.items || []).map(itemRow)
    itemDialog.newValue = ''
    itemDialog.visible = true
}
const addItem = async () => {
    if (!itemDialog.newValue || itemDialog.adding || itemDialog.saving) return
    itemDialog.adding = true
    const value = itemDialog.newValue
    try {
        const sort = Math.min(999999, Math.max(-1, ...itemDialog.items.map((it: any) => Number(it.sort || 0))) + 1)
        const res: any = await addSpecItem({ group_id: itemDialog.group_id, item_value: value, sort })
        itemDialog.items.push(itemRow({ item_id: res.data, item_value: value, sort }))
        if (itemDialog.newValue === value) itemDialog.newValue = ''
    } finally {
        itemDialog.adding = false
    }
}
const saveItem = async (it: any) => {
    if (it._saving) return false
    if (!String(it.item_value || '').trim()) { ElMessage.warning('规格值不能为空'); return false }
    if (!Number.isInteger(Number(it.sort)) || Number(it.sort) < 0 || Number(it.sort) > 999999) { ElMessage.warning('排序请填写0至999999的整数'); return false }
    it._saving = true
    const value = String(it.item_value).trim(), sort = Number(it.sort)
    try {
        await editSpecItem(it.item_id, { item_value: value, sort })
        it.item_value = value
        it._savedValue = value
        it._savedSort = sort
        return true
    } catch { return false } finally { it._saving = false }
}
const removeItem = async (it: any) => {
    if (it._deleting || it._saving || itemDialog.saving) return
    it._deleting = true
    try {
        await delSpecItem(it.item_id)
        itemDialog.items = itemDialog.items.filter((x: any) => x.item_id !== it.item_id)
    } finally { it._deleting = false }
}
const closeItems = async () => {
    if (itemDialog.saving || itemDialog.adding || itemDialog.items.some((it: any) => it._saving || it._deleting)) return
    itemDialog.saving = true
    try {
        for (const it of itemDialog.items) if (itemChanged(it) && !await saveItem(it)) return
        itemDialog.visible = false
        await loadGroups()
    } finally { itemDialog.saving = false }
}
const beforeCloseItems = async (done: () => void) => {
    if (itemDialog.saving || itemDialog.adding || itemDialog.items.some((it: any) => it._saving || it._deleting)) return
    if (itemDialog.items.some(itemChanged)) {
        try { await ElMessageBox.confirm('有未保存的规格修改，关闭将丢弃这些修改。确认关闭？', '提示', { type: 'warning' }) } catch { return }
    }
    done()
    loadGroups()
}

onMounted(() => { loadCategories(); loadGroups() })
</script>
