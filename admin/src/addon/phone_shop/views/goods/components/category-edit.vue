<template>
    <el-dialog v-model="showDialog" :title="title" width="480" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
            <el-form-item :label="t('categoryName')" prop="category_name">
                <el-input v-model.trim="formData.category_name" clearable :placeholder="t('categoryNamePlaceholder')" class="input-width" maxlength="10" show-word-limit />
            </el-form-item>
            <el-form-item :label="t('pid')" prop="pid">
                <el-cascader v-model="formData.pid" :options="optionList" :props="categoryProps" clearable filterable
                             :disabled="!!formData.child_count" :placeholder="'顶级分类（不选即一级）'" class="input-width" />
            </el-form-item>
            <el-form-item :label="t('image')">
                <upload-image v-model="formData.image" />
            </el-form-item>

            <el-form-item :label="t('isShow')" prop="is_show">
                <el-switch v-model="formData.is_show" class="input-width" :active-value="1" :inactive-value="2" />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">{{ t('confirm') }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { addCategory, editCategory, getCategoryInfo, getCategoryTree } from '@/addon/phone_shop/api/goods'

// 级联选择器属性：选任意层级节点作为上级，返回该节点 category_id
const categoryProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, emitPath: false }

const showDialog = ref(false)
const loading = ref(false)
const title = ref('')

/**
 * 表单数据
 */
const initialFormData = {
    category_id: '',
    category_name: '',
    image: '',
    pid: 0,
    is_show: 1,
    child_count: 0,
    // sort: 9999,
    level: 1
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        category_id: [
            { required: true, message: t('categoryIdPlaceholder'), trigger: 'blur' }
        ],
        category_name: [
            { required: true, message: t('categoryNamePlaceholder'), trigger: 'blur' }
        ]
        // pid 不必填：不选即顶级（一级）分类
    }
})

const optionList = ref<any[]>([])
const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    const save = formData.category_id ? editCategory : addCategory

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            const data = { ...formData, pid: formData.pid || 0 } // 未选上级 = 顶级(一级)

            save(data).then(res => {
                loading.value = false
                showDialog.value = false
                emit('complete')
            }).catch(err => {
                loading.value = false
            })
        }
    })
}

// 递归剔除"自己及其子树"，避免把自己设为上级
const filterSelf = (list: any[], selfId: any): any[] => {
    return (list || [])
        .filter((el: any) => el.category_id != selfId)
        .map((el: any) => ({ ...el, child_list: filterSelf(el.child_list || [], selfId) }))
}

// 获取完整分类树作为上级候选（支持选到二级 → 建三级）
const getCategoryAllFn = () => {
    getCategoryTree().then((res: any) => {
        optionList.value = filterSelf(res.data || [], formData.category_id)
    })
}

const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true

    if (row) {
        title.value = t('updateCategory')
        const data = await (await getCategoryInfo(row.category_id)).data
        if (data) {
            Object.keys(formData).forEach((key: string) => {
                if (data[key] != undefined) formData[key] = data[key]
            })
        }
    } else {
        title.value = t('addCategory')
    }
    getCategoryAllFn()
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style>
<style lang="scss">
.diy-dialog-wrap .el-form-item__label {
    height: auto !important;
}
</style>
