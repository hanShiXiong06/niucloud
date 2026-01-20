<template>
    <div class="main-container">
        <div class="detail-head">
            <div class="left" @click="back()">
                <span class="iconfont iconxiangzuojiantou !text-xs"></span>
                <span class="ml-[1px]">{{ t('returnToPreviousPage') }}</span>
            </div>
            <span class="adorn">|</span>
            <span class="right">{{ pageName }}</span>
        </div>
        <el-card class="box-card !border-none" shadow="never">
            <el-form :model="formData" label-width="90px" ref="formRef" :rules="formRules" class="page-form">
                <el-form-item :label="t('title')" prop="title">
                    <el-input v-model="formData.title" clearable :placeholder="t('titlePlaceholder')" class="input-width" />
                </el-form-item>
                <el-form-item :label="t('image')">
                    <upload-image v-model="formData.image" />
                </el-form-item>
                <el-form-item :label="t('status')" prop="status">
                    <el-radio-group v-model="formData.status" :placeholder="t('statusPlaceholder')">
                        <el-radio label="1">
                            {{ t('statusIsOpen') }}
                        </el-radio>
                        <el-radio label="0">
                            {{ t('statusNoOpen') }}
                        </el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="t('content')" prop="content">
                    <editor v-model="formData.content" />
                </el-form-item>

            </el-form>
        </el-card>
        <div class="fixed-footer-wrap">
            <div class="fixed-footer">
                <el-button type="primary" @click="onSave(formRef)">{{ t('save') }}</el-button>
                <el-button @click="back()">{{ t('cancel') }}</el-button>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, computed } from 'vue'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { getTkjhkdNoticeInfo, addTkjhkdNotice, editTkjhkdNotice } from '@/addon/tk_jhkd/api/tkjhkd'
import { useRoute } from 'vue-router'

const route = useRoute()
const id: number = parseInt(route.query.id);
const loading = ref(false)
const pageName = route.meta.title

/**
 * 表单数据
 */
const initialFormData = {
    id: 0,
    title: '',
    image: '',
    content: '',
    status: 0,
    addon: ''

}
const formData: Record<string, any> = reactive({ ...initialFormData })

const setFormData = async (id: number = 0) => {
    Object.assign(formData, initialFormData)
    const data = await (await getTkjhkdNoticeInfo(id)).data
    Object.keys(formData).forEach((key: string) => {
        if (data[key] != undefined) formData[key] = data[key]
    })
}
if (id) setFormData(id)

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = computed(() => {
    return {
        title: [
            { required: true, message: t('titlePlaceholder'), trigger: 'blur' }
        ],
        image: [
            { required: true, message: t('imagePlaceholder'), trigger: 'blur' }
        ],
        content: [
            { required: true, message: t('contentPlaceholder'), trigger: 'blur' }
        ],
        status: [
            { required: true, message: t('statusPlaceholder'), trigger: 'blur' }
        ]
    }
})

const onSave = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true
            let data = formData
            console.log('formData', formData)
            const save = id ? editTkjhkdNotice : addTkjhkdNotice
            save(data).then(res => {
                loading.value = false
                console.log('return data', res)
                // history.back()
            }).catch(err => {
                loading.value = false
            })

        }
    })
}

const back = () => {
    history.back()
}
</script>

<style lang="scss" scoped></style>