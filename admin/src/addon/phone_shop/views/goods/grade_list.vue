<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex items-center justify-between">
                <span class="text-page-title">成色等级</span>
                <el-button type="primary" @click="openEdit()">新增成色</el-button>
            </div>
            <div class="mt-1 text-sm text-gray-400">扁平的成色等级（九九新靓机 / 95新花机 / 九八新…），建品时供选择。</div>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无成色等级，点右上角新增">
                <el-table-column label="成色名称" min-width="200" prop="grade_name" />
                <el-table-column label="排序" width="100" prop="sort" />
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status ? 'success' : 'info'" effect="light">{{ row.status ? '启用' : '停用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="160" align="right" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openEdit(row)">编辑</el-button>
                        <el-button type="danger" link @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="dialog.visible" :title="dialog.form.grade_id ? '编辑成色' : '新增成色'" width="420px">
            <el-form label-width="80px">
                <el-form-item label="成色名称" required>
                    <el-input v-model.trim="dialog.form.grade_name" placeholder="如 九九新靓机 / 95新花机" maxlength="50" />
                </el-form-item>
                <el-form-item label="排序">
                    <el-input-number v-model="dialog.form.sort" :min="0" />
                </el-form-item>
                <el-form-item label="状态">
                    <el-switch v-model="dialog.form.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="dialog.saving" @click="save">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getGrades, addGrade, editGrade, delGrade } from '@/addon/phone_shop/api/spec'

const loading = ref(false)
const list = ref<any[]>([])

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getGrades()
        list.value = res.data || []
    } finally {
        loading.value = false
    }
}

const dialog = reactive<any>({ visible: false, saving: false, form: { grade_id: 0, grade_name: '', sort: 0, status: 1 } })
const openEdit = (row: any = null) => {
    dialog.form = row ? { grade_id: row.grade_id, grade_name: row.grade_name, sort: row.sort, status: row.status }
        : { grade_id: 0, grade_name: '', sort: 0, status: 1 }
    dialog.visible = true
}
const save = async () => {
    if (!dialog.form.grade_name) return ElMessage.warning('请填写成色名称')
    dialog.saving = true
    try {
        if (dialog.form.grade_id) await editGrade(dialog.form.grade_id, dialog.form)
        else await addGrade(dialog.form)
        dialog.visible = false
        loadList()
    } finally {
        dialog.saving = false
    }
}
const remove = async (row: any) => {
    await ElMessageBox.confirm(`确认删除成色「${row.grade_name}」吗？`, '提示', { type: 'warning' })
    await delGrade(row.grade_id)
    loadList()
}

onMounted(loadList)
</script>
