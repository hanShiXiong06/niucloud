<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ t('device_query.config.title') }}</span>
                <el-button
                    type="primary"
                    @click="addEvent"
                    class="w-[100px]"
                >
                    {{ t('add') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="queryParams" ref="searchFormRef" class="search-form">
                    <el-form-item :label="t('device_query.config.name')" prop="name">
                        <el-input
                            v-model="queryParams.name"
                            :placeholder="t('device_query.config.namePlaceholder')"
                            class="input-width"
                        />
                    </el-form-item>
                    <el-form-item :label="t('device_query.config.status')" prop="status">
                        <el-select v-model="queryParams.status" :placeholder="t('device_query.config.statusPlaceholder')" class="input-width">
                            <el-option :label="t('device_query.config.all')" value="" />
                            <el-option :label="t('device_query.config.enable')" :value="1" />
                            <el-option :label="t('device_query.config.disable')" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="list" size="large" v-loading="loading">
                    <el-table-column :label="t('device_query.config.name')" prop="name" min-width="120" />
                    <el-table-column :label="t('device_query.config.baseUrl')" prop="base_url" min-width="200" />
                    <el-table-column :label="t('device_query.config.timeout')" prop="timeout" min-width="80">
                        <template #default="{ row }">
                            {{ row.timeout }}{{ t('device_query.config.timeoutUnit') }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.config.cacheTime')" prop="cache_time" min-width="80">
                        <template #default="{ row }">
                            {{ Math.floor(row.cache_time / 3600) }}h
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.config.dailyLimit')" prop="daily_limit" min-width="80" />
                  
                    <el-table-column :label="t('device_query.config.balance')" prop="balance" min-width="80">
                        <template #default="{ row }">
                            ¥{{ row.balance }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.config.status')" prop="status" min-width="80">
                        <template #default="{ row }">
                            <el-switch
                                v-model="row.status"
                                :active-value="1"
                                :inactive-value="0"
                                @change="editStatusEvent(row)"
                            />
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.config.createTime')" prop="create_at" min-width="120">
                        <template #default="{ row }">
                            {{ timeStampTurnTime(row.create_at) }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('action')" fixed="right" align="right" min-width="120">
                        <template #default="{ row }">
 
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>

                <div class="mt-[16px] flex justify-end">
                    <el-pagination
                        v-model:current-page="queryParams.page"
                        v-model:page-size="queryParams.limit"
                        layout="total, sizes, prev, pager, next, jumper"
                        :total="total"
                        @size-change="loadList"
                        @current-change="loadList"
                    />
                </div>
            </div>
        </el-card>

        <!-- 添加/编辑对话框 -->
        <el-dialog v-model="showDialog" :title="dialogTitle" width="60%" :close-on-click-modal="false">
            <el-form ref="formRef" :model="formData" :rules="formRules" label-width="120px">
                <el-form-item :label="t('device_query.config.name')" prop="name">
                    <el-input v-model="formData.name" :placeholder="t('device_query.config.namePlaceholder')" />
                </el-form-item>
                <el-form-item :label="t('device_query.config.apiKey')" prop="api_key">
                    <el-input v-model="formData.api_key" :placeholder="t('device_query.config.apiKeyPlaceholder')" type="password" show-password />
                </el-form-item>
                <el-form-item :label="t('device_query.config.baseUrl')" prop="base_url">
                    <el-input v-model="formData.base_url" :placeholder="t('device_query.config.baseUrlPlaceholder')" />
                </el-form-item>
                <el-form-item :label="t('device_query.config.enabledApis')" prop="enabled_apis">
                    <el-select 
                        v-model="formData.enabled_apis" 
                        :placeholder="t('device_query.config.enabledApisPlaceholder')" 
                        style="width: 100%"
                        @visible-change="loadApiList"
                    >
                        <el-option
                            v-for="api in apiOptions"
                            :key="api.value"
                            :label="api.label"
                            :value="api.value"
                        />
                    </el-select>
                    <div class="text-xs text-gray-500 mt-1">选择的接口将以字符串格式保存</div>
                </el-form-item>
                <el-form-item :label="t('device_query.config.timeout')" prop="timeout">
                    <el-input-number v-model="formData.timeout" :min="1" :max="300" />
                    <span class="ml-2 text-gray-500">{{ t('device_query.config.timeoutUnit') }}</span>
                </el-form-item>
                <el-form-item :label="t('device_query.config.maxRetry')" prop="max_retry">
                    <el-input-number v-model="formData.max_retry" :min="0" :max="10" />
                </el-form-item>
                <el-form-item :label="t('device_query.config.cacheTime')" prop="cache_time">
                    <el-input-number v-model="formData.cache_time" :min="0" :max="86400" />
                    <span class="ml-2 text-gray-500">{{ t('device_query.config.cacheTimeUnit') }}</span>
                </el-form-item>
                <el-form-item :label="t('device_query.config.dailyLimit')" prop="daily_limit">
                    <el-input-number v-model="formData.daily_limit" :min="0" />
                </el-form-item>
                <el-form-item :label="t('device_query.config.remark')" prop="remark">
                    <el-input v-model="formData.remark" type="textarea" :rows="3" :placeholder="t('device_query.config.remarkPlaceholder')" />
                </el-form-item>
            </el-form>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showDialog = false">{{ t('common.cancel') }}</el-button>
                    <el-button type="primary" @click="save">{{ t('common.save') }}</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import { t } from '@/lang'
import { timeStampTurnTime } from '@/utils/common'
import {
    getDeviceQueryConfigList,
    getDeviceQueryConfigInfo,
    addDeviceQueryConfig,
    editDeviceQueryConfig,
    deleteDeviceQueryConfig,
    updateDeviceQueryConfigStatus,
    testDeviceQueryConnection
} from '@/addon/recycle/api/device_query_config'
import {
    getDeviceQueryApiList
} from '@/addon/recycle/api/device_query_api'

const loading = ref(false)
const list = ref([])
const total = ref(0)
const showDialog = ref(false)
const dialogTitle = ref('')
const searchFormRef = ref()
const formRef = ref()
const apiOptions = ref<Array<{label: string, value: string}>>([])

// 查询参数
const queryParams = reactive({
    name: '',
    status: '',
    page: 1,
    limit: 10
})

// 表单数据
const formData = reactive({
    id: 0,
    name: '',
    api_key: '',
    base_url: 'http://api.3023data.com',
    enabled_apis: '',
    timeout: 30,
    max_retry: 3,
    cache_time: 3600,
    daily_limit: 1000,
    balance: 0.00,
    status: 1,
    remark: ''
})

// 表单验证规则
const formRules = {
    name: [{ required: true, message: t('device_query.config.nameRequired'), trigger: 'blur' }],
    api_key: [{ required: true, message: t('device_query.config.apiKeyRequired'), trigger: 'blur' }],
    base_url: [{ required: true, message: t('device_query.config.baseUrlRequired'), trigger: 'blur' }]
}

// 加载API列表数据
const loadApiList = async (visible?: boolean) => {
    if (!visible || apiOptions.value.length > 0) return
    
    try {
        const response = await getDeviceQueryApiList({ page: 1, limit: 100 })
        const apiList = response.data.data || []
       
        
        
        // 将API数据转换为选项格式
        const options: Array<{label: string, value: string}> = []
        apiList.forEach((item: any) => {
            if (item && typeof item === 'object') {
                item.label = item.name
                item.value = item.api_list
                options.push(item)
            }
        })
        console.log(options);

        
        apiOptions.value = options
    } catch (error) {
        console.error('加载API列表失败:', error)
    }
}

// 获取启用的API列表
const getEnabledApisList = (enabledApis: string): string[] => {
    if (!enabledApis || enabledApis.trim() === '') {
        return []
    }
    
    // 单个API字符串，直接返回
    return [enabledApis.trim()]
}

// 重置表单
const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadList()
}

// 加载列表数据
const loadList = async (page: number = 1) => {
    loading.value = true
    queryParams.page = page
    try {
        const data = await getDeviceQueryConfigList(queryParams)
        list.value = data.data.data
        total.value = data.data.total
    } catch (error) {
        console.error(error)
    } finally {
        loading.value = false
    }
}

// 添加
const addEvent = () => {
    dialogTitle.value = t('device_query.config.addDialog')
    showDialog.value = true
    resetFormData()
}

// 编辑
const editEvent = async (row: any) => {
    dialogTitle.value = t('device_query.config.editDialog')
    showDialog.value = true
    try {
        const data = await getDeviceQueryConfigInfo(row.id)
        const configData = data.data
        
        // 处理enabled_apis字段 - 字符串格式，不需要JSON解析
        if (configData.enabled_apis) {
            // 如果是字符串，直接使用
            if (typeof configData.enabled_apis === 'string') {
                configData.enabled_apis = configData.enabled_apis
            } else {
                configData.enabled_apis = ''
            }
        } else {
            configData.enabled_apis = ''
        }
        
        Object.assign(formData, configData)
    } catch (error) {
        console.error(error)
    }
}

// 删除
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('device_query.config.deleteConfirm'), t('device_query.config.warning'), {
        confirmButtonText: t('device_query.config.confirmText'),
        cancelButtonText: t('device_query.config.cancelText'),
        type: 'warning'
    }).then(async () => {
        try {
            await deleteDeviceQueryConfig(id)
            ElMessage.success(t('device_query.config.deleteSuccess'))
            await loadList()
        } catch (error) {
            console.error(error)
        }
    })
}

// 修改状态
const editStatusEvent = async (row: any) => {
    try {
        await updateDeviceQueryConfigStatus(row.id, row.status)
        ElMessage.success(t('device_query.config.statusUpdateSuccess'))
    } catch (error) {
        row.status = row.status === 1 ? 0 : 1
        console.error(error)
    }
}

// 测试连接
const testConnectionEvent = async (row: any) => {
    try {
        const data = await testDeviceQueryConnection(row.id)
        if (data.data.success) {
            ElMessage.success(`${t('device_query.config.connectionTestSuccess').replace('{time}', data.data.response_time)}`)
        } else {
            ElMessage.error(`${t('device_query.config.connectionTestFailed').replace('{message}', data.data.message)}`)
        }
    } catch (error) {
        console.error(error)
    }
}

// 保存
const save = async () => {
    await formRef.value.validate()
    try {
        // 准备保存数据 - enabled_apis直接保存为字符串
        const saveData = {
            ...formData,
            enabled_apis: formData.enabled_apis || ''
        }
        
        if (formData.id) {
            await editDeviceQueryConfig(formData.id, saveData)
            ElMessage.success(t('device_query.config.editSuccess'))
        } else {
            await addDeviceQueryConfig(saveData)
            ElMessage.success(t('device_query.config.addSuccess'))
        }
        showDialog.value = false
        await loadList()
    } catch (error) {
        console.error(error)
    }
}

// 重置表单数据
const resetFormData = () => {
    Object.assign(formData, {
        id: 0,
        name: '',
        api_key: '',
        base_url: 'http://api.3023data.com',
        enabled_apis: '',
        timeout: 30,
        max_retry: 3,
        cache_time: 3600,
        daily_limit: 1000,
        balance: 0.00,
        status: 1,
        remark: ''
    })
    formRef.value?.resetFields()
}

onMounted(() => {
    loadList()
})
</script>

<script lang="ts">
export default {
    name: 'DeviceQueryConfig'
}
</script>

<style lang="scss" scoped>
.input-width {
    width: 180px;
}
</style> 