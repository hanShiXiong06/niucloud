<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex items-center justify-between">
                <span class="text-page-title">成色等级</span>
                <el-button type="primary" @click="openEdit()">新增成色</el-button>
            </div>
            <div class="mt-1 text-sm text-gray-400">维护成色名称、说明和展示图片，供商品录入与商城筛选统一使用。</div>

            <el-table class="mt-4" :data="list" v-loading="loading" size="large" empty-text="暂无成色等级，点右上角新增">
                <el-table-column label="等级图片" width="92">
                    <template #default="{ row }">
                        <el-image v-if="row.grade_image" class="grade-image" :src="img(row.grade_image)" fit="cover" :preview-src-list="[img(row.grade_image)]" preview-teleported />
                        <div v-else class="grade-image grade-image--empty">暂无</div>
                    </template>
                </el-table-column>
                <el-table-column label="成色名称" min-width="160" prop="grade_name" />
                <el-table-column label="等级描述" min-width="280" show-overflow-tooltip>
                    <template #default="{ row }">
                        <span :class="row.grade_desc ? 'text-[#475569]' : 'text-[#c0c4cc]'">{{ row.grade_desc || '未填写描述' }}</span>
                    </template>
                </el-table-column>
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

        <el-dialog v-model="dialog.visible" :title="dialog.form.grade_id ? '编辑成色' : '新增成色'" width="560px" destroy-on-close>
            <el-form label-width="92px">
                <el-form-item label="成色名称" required>
                    <el-input v-model.trim="dialog.form.grade_name" placeholder="如 九九新靓机 / 95新花机" maxlength="50" />
                </el-form-item>
                <el-form-item label="等级图片">
                    <upload-image v-model="dialog.form.grade_image" :limit="1" />
                    <div class="form-tip">用于商城筛选或等级说明，建议上传清晰的方形图片。</div>
                </el-form-item>
                <el-form-item label="等级描述">
                    <el-input v-model="dialog.form.grade_desc" type="textarea" :rows="4" maxlength="500" show-word-limit placeholder="说明该等级的外观、使用痕迹或适用标准" />
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
import { img } from '@/utils/common'

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

const emptyForm = () => ({ grade_id: 0, grade_name: '', grade_desc: '', grade_image: '', sort: 0, status: 1 })
const dialog = reactive<any>({ visible: false, saving: false, form: emptyForm() })
const openEdit = (row: any = null) => {
    dialog.form = row ? {
        grade_id: row.grade_id,
        grade_name: row.grade_name,
        grade_desc: row.grade_desc || '',
        grade_image: row.grade_image || '',
        sort: row.sort,
        status: row.status
    } : emptyForm()
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

<style scoped>
.grade-image {
    width: 52px;
    height: 52px;
    border-radius: 8px;
    border: 1px solid #e5e7eb;
}

.grade-image--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #c0c4cc;
    background: #f8fafc;
    font-size: 12px;
}

.form-tip {
    width: 100%;
    margin-top: 6px;
    color: #94a3b8;
    font-size: 12px;
    line-height: 1.5;
}
</style>
