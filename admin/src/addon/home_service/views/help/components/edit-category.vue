<template>
    <el-dialog v-model="showDialog" :title="popTitle" width="500px" :destroy-on-close="true">
        <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
            <el-form-item :label="t('name')" prop="category_name">
                <el-input v-model="formData.category_name" clearable :placeholder="t('namePlaceholder')" class="input-width" maxlength="30" />
            </el-form-item>
            <el-form-item :label="t('sort')" prop="sort">
                <el-input-number v-model="formData.sort" :min="0" />
            </el-form-item>

            <el-form-item :label="t('isShow')">
                <el-radio-group v-model="formData.is_show" :placeholder="t('isShowPlaceholder')">
                    <el-radio :label="1">{{ t('show') }}</el-radio>
                    <el-radio :label="0">{{ t('hidden') }}</el-radio>
                </el-radio-group>
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
import { addhelpCategory, edithelpCategory, gethelpCategoryInfo } from '@/addon/home_service/api/help'

let popTitle: string = ''

const showDialog = ref(false)
const loading = ref(true)

/**
 * 表单数据
 */
const initialFormData = {
    category_id: '',
    category_name: '',
    sort: '',
    is_show: 1
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        category_name: [
            { required: true, message: t('namePlaceholder'), trigger: 'blur' },
            {
                validator: (rule: any, value: string, callback: any) => {
                    if (value.length > 20) {
                        callback(new Error(t('nameMax')))
                    }

                    callback()
                },
                trigger: 'blur'
            }
        ],
        sort: [
            {
                validator: (rule: any, value: string | number, callback: any) => {
                    if (value === '' || isNaN(value as number)) {
                        callback(new Error(t('sortNumber')))
                    }
                    if (parseInt(value as string) > 10000) {
                        callback(new Error(t('sortBetween')))
                    }
                    callback()
                },
                trigger: 'blur'
            }
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
    const save = formData.category_id ? edithelpCategory : addhelpCategory

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            const data = formData

            save(data).then(res => {
                loading.value = false
                showDialog.value = false
                emit('complete')

            }).catch(() => {
                loading.value = false
            })
        }
    })
}

const setFormData = async (row: any = null) => {

    loading.value = true
    popTitle = t('addhelpCategory')
    if (row) {
		    Object.assign(formData, row)
        popTitle = t('updatehelpCategory')
    }else{
      // formData = initialFormData
      Object.assign(formData, initialFormData)
    }

    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style>
