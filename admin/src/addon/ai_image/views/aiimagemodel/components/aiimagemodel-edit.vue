<template>
    <el-drawer v-model="showDialog" :title="formData.id ? t('updateAiimageModel') : t('addAiimageModel')" size="600px"
        :destroy-on-close="true" class="custom-drawer">
        <div class="drawer-content h-full flex flex-col">
            <el-form :model="formData" label-width="100px" ref="formRef" :rules="formRules"
                class="flex-1 overflow-y-auto px-4" v-loading="loading">
                <!-- 基础信息卡片 -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-base font-semibold mb-4 text-gray-700 border-l-4 border-blue-500 pl-3">
                        基础信息
                    </div>

                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="formData.name" clearable :placeholder="t('namePlaceholder')"
                            class="w-full" />
                    </el-form-item>

                    <el-form-item :label="t('logo')" prop="logo">
                        <upload-image v-model="formData.logo" limit="2" />
                    </el-form-item>

                    <el-form-item :label="t('desc')">
                        <el-input v-model="formData.desc" type="textarea" :rows="3" clearable
                            :placeholder="t('descPlaceholder')" class="w-full" />
                    </el-form-item>
                </div>

                <!-- 配置信息卡片 -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-base font-semibold mb-4 text-gray-700 border-l-4 border-green-500 pl-3">
                        配置信息
                    </div>
                    <el-form-item :label="t('上传图像')" prop="is_upload_image">
                        <el-select v-model="formData.is_upload_image" :placeholder="t('是否上传图像')" class="w-full">
                            <el-option label="需要上传" :value="1"></el-option>
                            <el-option label="不需要" :value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item v-if="formData.is_upload_image == 1" :label="t('上传张数')" prop="limit_image">
                        <el-input-number v-model="formData.limit_image" min="1" max="9" :placeholder="t('上传张数')"
                            class="w-full" controls-position="right" />
                    </el-form-item>
                    <el-form-item :label="t('提示词编辑')" prop="is_prompt">
                        <el-select v-model="formData.is_prompt" :placeholder="t('提示词编辑')" class="w-full">
                            <el-option label="允许" :value="1"></el-option>
                            <el-option label="不允许" :value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="formData.status" :placeholder="t('statusPlaceholder')" class="w-full">
                            <el-option label="启用" :value="1"></el-option>
                            <el-option label="禁用" :value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('模型')" prop="model">
                        <el-select v-model="formData.model" :placeholder="t('请选择模型')" class="w-full">
                            <el-option label="nano-banana" value="gemini-2.5-pro-image-preview"></el-option>
                            <el-option label="nano-banana-pro" value="gemini-3-pro-image-preview"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('point')" prop="point">
                        <el-input-number v-model="formData.point" :min="0" :placeholder="t('pointPlaceholder')"
                            class="w-full" controls-position="right" />
                    </el-form-item>

                    <el-form-item :label="t('sort')">
                        <el-input-number v-model="formData.sort" :min="0" :placeholder="t('sortPlaceholder')"
                            class="w-full" controls-position="right" />
                    </el-form-item>
                </div>

                <!-- Prompt信息卡片 -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-base font-semibold mb-4 text-gray-700 border-l-4 border-purple-500 pl-3">
                        Prompt 配置
                    </div>

                    <el-form-item :label="t('prompt')" prop="prompt">
                        <el-input v-model="formData.prompt" type="textarea" :rows="5"
                            :placeholder="t('promptPlaceholder')" class="w-full" />
                    </el-form-item>
                </div>

                <!-- 示例图片卡片 -->
                <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                    <div class="text-base font-semibold mb-4 text-gray-700 border-l-4 border-orange-500 pl-3">
                        示例图片
                    </div>

                    <el-form-item :label="t('demoImage')">
                        <upload-image v-model="formData.demo_image" />
                    </el-form-item>
                </div>

                <!-- 
                <el-form-item :label="t('isVip')">
                    <el-select v-model="formData.is_vip" :placeholder="t('请选择')" class="w-full">
                        <el-option label="是" :value="1"></el-option>
                        <el-option label="否" :value="0"></el-option>
                    </el-select>
                </el-form-item> 
                -->
            </el-form>

            <!-- 底部操作按钮 -->
            <div class="drawer-footer border-t border-gray-200 p-4 bg-white flex justify-end gap-3">
                <el-button @click="showDialog = false" size="large">
                    {{ t('cancel') }}
                </el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)" size="large">
                    {{ t('confirm') }}
                </el-button>
            </div>
        </div>
    </el-drawer>
</template>

<script lang="ts" setup>
import { ref, reactive } from 'vue'
import { useDictionary } from '@/app/api/dict'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { addAiimageModel, editAiimageModel, getAiimageModelInfo } from '@/addon/ai_image/api/aiimagemodel'

let showDialog = ref(false)
const loading = ref(false)

/**
 * 表单数据
 */
const initialFormData = {
    id: '',
    name: '',
    logo: '',
    desc: '',
    prompt: '',
    sort: '',
    demo_image: '',
    status: 1,
    point: 0,
    is_vip: '',
    is_upload_image: 1,
    is_prompt: 0,
    limit_image: 0,
    model: '',
}
const formData: Record<string, any> = reactive({ ...initialFormData })

const formRef = ref<FormInstance>()

// 表单验证规则
const formRules = {
    name: [
        { required: true, message: t('namePlaceholder'), trigger: 'blur' },
    ],
    logo: [
        { required: true, message: t('logoPlaceholder'), trigger: 'blur' },
    ],
    desc: [
        { required: true, message: t('descPlaceholder'), trigger: 'blur' },
    ],
    prompt: [
        { required: true, message: t('promptPlaceholder'), trigger: 'blur' },
    ],
    sort: [
        { required: true, message: t('sortPlaceholder'), trigger: 'blur' },
    ],
    demo_image: [
        { required: true, message: t('demoImagePlaceholder'), trigger: 'blur' },
    ],
    status: [
        { required: true, message: t('statusPlaceholder'), trigger: 'blur' },
    ],
    point: [
        { required: true, message: t('pointPlaceholder'), trigger: 'blur' },
    ],
    is_vip: [
        { required: true, message: t('isVipPlaceholder'), trigger: 'blur' },
    ],
    is_upload_image: [
        { required: true, message: t('选择是否需要上传图像'), trigger: 'blur' },
    ],
    is_prompt: [
        { required: true, message: t('选择是否需要填写提示词'), trigger: 'blur' },
    ],
    limit_image: [
        { required: true, message: t('上传张数'), trigger: 'blur' },
    ],
    model: [
        { required: true, message: t('请选择模型'), trigger: 'blur' },
    ]
}

const emit = defineEmits(['complete'])

/**
 * 确认
 * @param formEl
 */
const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    let save = formData.id ? editAiimageModel : addAiimageModel

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true

            let data = formData

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

// 获取字典数据



const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if (row) {
        const data = await (await getAiimageModelInfo(row.id)).data
        if (data) Object.keys(formData).forEach((key: string) => {
            if (data[key] != undefined) formData[key] = data[key]
        })
    }
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
