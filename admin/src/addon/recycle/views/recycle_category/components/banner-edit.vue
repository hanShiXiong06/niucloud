<template>
    <el-dialog v-model="showDialog" :title="title" width="480" class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form"
            v-loading="loading">
            <el-form-item :label="t('轮播图')" prop="image">

                <upload-image v-model="formData.image" />
            </el-form-item>
            <el-form-item :label="t('排序')" prop="sort">
                <el-input-number v-model="formData.sort" :min="0" class="input-width" />
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
import { addBanner, editBanner, getBannerInfo } from '@/addon/recycle/api/recycle_category'

const showDialog = ref(false)
const loading = ref(false)
const title = ref('')

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    image: '',
    sort: 0
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        image: [
            { required: true, message: t('请输入图片地址'), trigger: 'blur' }
        ]
    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            if (formData.id) {
                editBanner(formData.id, formData).then(() => {
                    loading.value = false
                    showDialog.value = false
                    emit('complete')
                }).catch(() => {
                    loading.value = false
                })
            } else {
                addBanner(formData).then(() => {
                    loading.value = false
                    showDialog.value = false

                    emit('complete')
                }).catch(() => {
                    loading.value = false
                })
            }
        }
    })
}

const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    showDialog.value = true
    loading.value = true

    if (row) {
        title.value = t('编辑轮播图')
        try {
            const { data } = await getBannerInfo(row.id)
            if (data) {
                Object.keys(formData).forEach((key: string) => {
                    if (data[key] !== undefined) {
                        formData[key] = data[key]
                    }
                })
            }
        } catch (error) {
            console.error(error)
            loading.value = false
            return
        }
    } else {
        title.value = t('添加轮播图')
    }
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss">
.diy-dialog-wrap {
    .el-form-item__label {
        height: auto !important;
    }

    .input-width {
        width: 100%;
    }
}
</style>