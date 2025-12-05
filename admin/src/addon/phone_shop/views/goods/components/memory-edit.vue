<template>
    <el-dialog v-model="showDialog" :title="formData.spec_id ? t('editMemory') : t('addMemory')" width="600px"
        :close-on-click-modal="false" :close-on-press-escape="false">
        <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px">
            <el-form-item :label="t('specName')" prop="spec_name">
                <el-input v-model.trim="formData.spec_name" :placeholder="t('specNamePlaceholder')" />
            </el-form-item>
            <el-form-item :label="t('sort')" prop="sort">
                <el-input v-model.trim="formData.sort" :placeholder="t('sortPlaceholder')" />
            </el-form-item>
        </el-form>
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="closeDialog">{{ t('cancel') }}</el-button>
                <el-button type="primary" @click="confirm" :loading="loading">
                    {{ t('confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { addMemory, editMemory } from '@/addon/phone_shop/api/goods'
import type { FormInstance, FormRules } from 'element-plus'


const props = defineProps({
   
})

const emit = defineEmits(['complete'])

const showDialog = ref(false)
const loading = ref(false)
const formRef = ref<FormInstance>()

const formData = reactive({
    spec_id: '',
 
    spec_name: '',
    sort: 0
})

const rules = reactive<FormRules>({
   
    spec_name: [
        { required: true, message: t('specNameRequired'), trigger: 'blur' }
    ],
    sort: [
        { required: true, message: t('sortRequired'), trigger: 'blur' }
    ]
})

const setFormData = (data?: any) => {
    formData.spec_id = data?.spec_id ?? ''
   
    formData.spec_name = data?.spec_name ?? ''
    formData.sort = data?.sort ?? 0
}

const closeDialog = () => {
    showDialog.value = false
}

const confirm = () => {
    if (!formRef.value) return
    formRef.value.validate((valid) => {
        if (valid) {
            loading.value = true
            const request = formData.spec_id ? editMemory(formData.spec_id, formData) : addMemory(formData)
            request.then(() => {
                closeDialog()
                emit('complete')
            }).finally(() => {
                loading.value = false
            })
        }
    })
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style> 