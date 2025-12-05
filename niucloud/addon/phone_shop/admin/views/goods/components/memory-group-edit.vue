<template>
    <el-dialog v-model="showDialog" :title="formData.group_id ? t('editMemoryGroup') : t('addMemoryGroup')"
        width="600px" :close-on-click-modal="false" :close-on-press-escape="false">
        <el-form ref="formRef" :model="formData" :rules="rules" label-width="120px">
            <el-form-item :label="t('groupName')" prop="group_name">
                <el-input v-model.trim="formData.group_name" :placeholder="t('groupNamePlaceholder')" />
            </el-form-item>
            <el-form-item :label="t('sort')" prop="sort">
                <el-input v-model.trim="formData.sort" :placeholder="t('sortPlaceholder')" />
            </el-form-item>
            <el-form-item :label="t('memorySpecs')" prop="memory_ids">
                <el-checkbox-group v-model="formData.memory_ids" class="memory-checkbox-group">
                    <el-checkbox v-for="item in memoryList" :key="item.spec_id" :label="item.spec_id" border>
                        {{ item.spec_name }}
                    </el-checkbox>
                </el-checkbox-group>
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
import { reactive, ref, onMounted } from 'vue'
import { t } from '@/lang'
import { addMemoryGroup, editMemoryGroup, getMemoryList } from '@/addon/phone_shop/api/goods'
import type { FormInstance, FormRules } from 'element-plus'
import userStore from '@/stores/modules/user'

interface MemorySpec {
    spec_id: number
    spec_name: string
    site_id: number
    sort: number
    create_time: string
    update_time: string
}

const emit = defineEmits(['complete'])

const showDialog = ref(false)
const loading = ref(false)
const formRef = ref<FormInstance>()
const memoryList = ref<MemorySpec[]>([])

const formData = reactive({
    group_id: '',
    group_name: '',
    sort: 0,
    memory_ids: [] as number[],
    site_id: userStore()?.siteInfo?.site_id || 0
})

const rules = reactive<FormRules>({
    group_name: [
        { required: true, message: t('groupNameRequired'), trigger: 'blur' }
    ],
    sort: [
        { required: true, message: t('sortRequired'), trigger: 'blur' }
    ],
    memory_ids: [
        { required: true, message: t('memorySpecsRequired'), trigger: 'change', type: 'array' }
    ]
})

const getMemoryListData = async () => {
    try {
        const res: any = await getMemoryList({ limit: 100 })
        if (res.code === 1 && res.data.data) {
            memoryList.value = res.data.data
        }
    } catch (error) {
        console.error('获取内存规格列表失败:', error)
    }
}

const setFormData = (data?: any) => {
    formData.group_id = data?.group_id ?? ''
    formData.group_name = data?.group_name ?? ''
    formData.sort = data?.sort ?? 0
    formData.memory_ids = data?.memory_ids ? data.memory_ids.split(',').map(Number) : []
}

const closeDialog = () => {
    showDialog.value = false
    formData.memory_ids = []
}

const confirm = () => {
    if (!formRef.value) return
    formRef.value.validate((valid) => {
        if (valid) {
            loading.value = true
            const submitData = {
                ...formData,
                memory_ids: formData.memory_ids.join(',')
            }
            const request = formData.group_id
                ? editMemoryGroup(Number(formData.group_id), submitData)
                : addMemoryGroup(submitData)
            request.then(() => {
                closeDialog()
                emit('complete')
            }).finally(() => {
                loading.value = false
            })
        }
    })
}

onMounted(() => {
    getMemoryListData()
})

defineExpose({
    showDialog,
    setFormData
})
</script>

<style lang="scss" scoped>
.memory-checkbox-group {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;

    .el-checkbox {
        margin-right: 0;
    }
}
</style>