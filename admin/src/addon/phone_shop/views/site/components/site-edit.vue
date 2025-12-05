<template>
    <el-dialog v-model="showDialog" :title="formData.id ? t('updateSite') : t('addSite')" width="50%"
        class="diy-dialog-wrap" :destroy-on-close="true">
        <el-form :model="formData" label-width="120px" ref="formRef" :rules="formRules" class="page-form"
            v-loading="loading">
            <el-form-item :label="t('siteId')" prop="site_id">
                <el-select :disabled="!!formData.id" v-model="formData.site_id" filterable remote reserve-keyword
                    placeholder="请输入关键词搜索站点" :remote-method="remoteMethod" :loading="siteLoading" class="input-width">
                    <el-option v-for="item in siteOptions" :key="item.site_id"
                        :label="item.site_id + ' - ' + item.site_name" :value="item.site_id">
                        <span>{{ item.site_id }} - {{ item.site_name }}</span>
                    </el-option>
                </el-select>
            </el-form-item>

            <el-form-item :label="t('client')" prop="client">
                <el-switch v-model="formData.client" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>


            <el-form-item :label="t('categoryStatus')" prop="category_status">
                <el-switch v-model="formData.category_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>

            <el-form-item :label="t('brandStatus')" prop="brand_status">
                <el-switch v-model="formData.brand_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>

            <el-form-item :label="t('labelGroupStatus')" prop="label_group_status">
                <el-switch v-model="formData.label_group_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>

            <el-form-item :label="t('labelStatus')" prop="label_status">
                <el-switch v-model="formData.label_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>

            <el-form-item :label="t('serviceStatus')" prop="service_status">
                <el-switch v-model="formData.service_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>

            <el-form-item :label="t('priceStatus')" prop="price_status">
                <el-switch v-model="formData.price_status" :active-value="1" :inactive-value="0" active-text="启用"
                    inactive-text="禁用" />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                <el-button type="primary" :loading="loading" @click="confirm(formRef)">{{
                    t('confirm')
                    }}</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script lang="ts" setup>
import { ref, reactive, computed, watch, onMounted } from 'vue'
import { useDictionary } from '@/app/api/dict'
import { t } from '@/lang'
import type { FormInstance } from 'element-plus'
import { addSite, editSite, getSiteInfo, getSiteListAll } from '@/addon/phone_shop/api/site'

const showDialog = ref(false)
const loading = ref(false)
const siteLoading = ref(false)
const siteOptions = ref([])



const remoteMethod = async (query: string) => {
    if (query !== '') {
        siteLoading.value = true
        try {
            const res = await getSiteListAll({
                page: 1,
                limit: 20,
                keywords: query
            })
            siteOptions.value = res.data.data
        } catch (error) {
            console.error('获取站点列表失败:', error)
        } finally {
            siteLoading.value = false
        }
    } else {
        siteOptions.value = []
    }
}

const initSiteList = async () => {
    siteLoading.value = true
    try {
        const res = await getSiteListAll({
            page: 1,
            limit: 20
        })
        siteOptions.value = res.data.data
    } catch (error) {
        console.error('获取站点列表失败:', error)
    } finally {
        siteLoading.value = false
    }
}

onMounted(() => {
    initSiteList()
})

const initialFormData = {
    id: '',
    site_id: '',
    site_name: '',
    client: 1,
    category_status: 1,
    brand_status: 1,
    label_group_status: 1,
    label_status: 1,
    service_status: 1,
    price_status: 1
}

const formData: Record<string, any> = reactive({ ...initialFormData })
const formRef = ref<FormInstance>()

const formRules = computed(() => {
    return {
        site_id: [
            { required: true, message: t('siteIdPlaceholder'), trigger: 'blur' }
        ],
        site_name: [
            { required: true, message: t('siteNamePlaceholder'), trigger: 'blur' }
        ],
        client: [
            { required: true, message: t('clientPlaceholder'), trigger: 'blur' }
        ],
        category_status: [
            { required: true, message: t('categoryStatusPlaceholder'), trigger: 'blur' }
        ],
        brand_status: [
            { required: true, message: t('brandStatusPlaceholder'), trigger: 'blur' }
        ],
        label_group_status: [
            { required: true, message: t('labelGroupStatusPlaceholder'), trigger: 'blur' }
        ],
        label_status: [
            { required: true, message: t('labelStatusPlaceholder'), trigger: 'blur' }
        ],
        service_status: [
            { required: true, message: t('serviceStatusPlaceholder'), trigger: 'blur' }
        ],
        price_status: [
            { required: true, message: t('priceStatusPlaceholder'), trigger: 'blur' }
        ]
    }
})

const emit = defineEmits(['complete'])

const confirm = async (formEl: FormInstance | undefined) => {
    if (loading.value || !formEl) return
    const save = formData.id ? editSite : addSite

    await formEl.validate(async (valid) => {
        if (valid) {
            loading.value = true
            try {
                await save(formData)
                loading.value = false
                showDialog.value = false
                emit('complete')
            } catch (error) {
                console.error('保存失败:', error)
                loading.value = false
            }
        }
    })
}
// 监听site_id变化，自动更新site_name
watch(() => formData.site_id, (newVal) => {
    if (newVal) {
        const selectedSite = siteOptions.value.find(item => item.site_id == newVal)
        if (selectedSite) {
            formData.site_name = selectedSite.site_name
        }
    }
})
const setFormData = async (row: any = null) => {
    Object.assign(formData, initialFormData)
    loading.value = true
    if (row) {
        try {
            const data = await (await getSiteInfo(row.id)).data
            if (data) {
                Object.keys(formData).forEach((key: string) => {
                    if (data[key] !== undefined) {
                        formData[key] = data[key]
                    }
                })
            }
        } catch (error) {
            console.error('获取站点信息失败:', error)
        }
    }
    console.log(formData)
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