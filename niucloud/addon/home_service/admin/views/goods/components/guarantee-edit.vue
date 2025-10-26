<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateO2oGoodsguarantee') : t('addO2oGoodsguarantee')" width="500px" :destroy-on-close="true">
        <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form" v-loading="loading">
            <el-form-item :label="t('guaranteeName')" prop="guarantee_title">
                <el-input v-model.trim="formData.guarantee_title" clearable :placeholder="t('guaranteeNamePlaceholder')" class="input-width" />
            </el-form-item>
            <el-form-item :label="t('image')" prop="guarantee_image">
                <upload-image v-model="formData.guarantee_image" />
            </el-form-item>
			<el-form-item :label="t('settledguarantee')" prop="guarantee_content">
				<el-input v-model.trim="formData.guarantee_content" type="textarea" clearable :placeholder="t('settledguaranteePlaceholder')" class="input-width" />
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
import { editGuarantee ,addGuarantee} from '@/addon/home_service/api/guarantee'
import { filterNumber } from '@/utils/common'

const showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    guarantee_title: '',
    guarantee_image: '',
    guarantee_content: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        guarantee_title: [
            { required: true, message: t('guaranteeNamePlaceholder'), trigger: 'blur' }
        ],
		guarantee_content:[
			{ required: true, message: t('settledguaranteePlaceholder'), trigger: 'blur' }
		],
		guarantee_image:[
			{ required: true, message: t('imagePlaceholder'), trigger: 'blur' }
		],
    }
})

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    const save = formData.id > 0 ? editGuarantee : addGuarantee

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

const guarantee_list = ref([])
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)

    // guarantee_list.value = await (await getguarantee({ type: 1 })).data

    loading.value = true
    if (row) {
		console.log(row)
        // const data = await (await getguaranteeInfo(row.id)).data
        if (row) {
            Object.keys(formData).forEach((key: string) => {
                if (row[key] != undefined) formData[key] = row[key]
            })
        }
    }
    loading.value = false
}

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped></style>
