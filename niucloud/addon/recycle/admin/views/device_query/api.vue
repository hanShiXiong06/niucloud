<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ t('device_query.api.title') }}</span>
              
            </div>

            <div class="mt-[10px]">
                <el-table :data="list" size="large" v-loading="loading">
                    <el-table-column :label="t('device_query.api.listName')" prop="name" min-width="200" />
                    <el-table-column :label="t('device_query.api.version')" prop="version" width="100" />
                    <el-table-column :label="t('device_query.api.status')" prop="status" min-width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.status === 1 ? 'success' : 'danger'">
                                {{ row.status === 1 ? t('device_query.api.enable') : t('device_query.api.disable') }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('device_query.api.apiAddress')" prop="api_list" min-width="100">
                      
                    </el-table-column>
                    <el-table-column :label="t('device_query.api.price')" prop="remark" min-width="100">
                      
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

        <!-- API清单详情对话框 -->
       
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { t } from '@/lang'
import {
    getDeviceQueryApiList,
    getDeviceQueryApiInfo,
    deleteDeviceQueryApi,
    initDefaultApiList
} from '@/addon/recycle/api/device_query_api'

const loading = ref(false)
const list = ref([])
const total = ref(0)

const viewData = ref(null)
const activeTab = ref('')

// 查询参数
const queryParams = reactive({
    page: 1,
    limit: 10
})

// 加载列表数据
const loadList = async () => {
    loading.value = true
    try {
        const data = await getDeviceQueryApiList(queryParams)
        list.value = data.data.data
        total.value = data.data.total
    } catch (error) {
        console.error(error)
    }
    loading.value = false
}




// 添加
const addEvent = () => {
    ElMessage.info(t('device_query.api.developingFeature'))
}

// 编辑
const editEvent = (row: any) => {
    ElMessage.info(t('device_query.api.developingFeature'))
}

// 删除
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('device_query.api.deleteConfirm'), t('device_query.api.warning'), {
        confirmButtonText: t('device_query.api.confirmText'),
        cancelButtonText: t('device_query.api.cancelText'),
        type: 'warning'
    }).then(async () => {
        try {
            await deleteDeviceQueryApi(id)
            ElMessage.success(t('device_query.api.deleteSuccess'))
            await loadList()
        } catch (error) {
            console.error(error)
        }
    })
}

// 初始化默认API清单
const initDefaultApi = async () => {
    try {
        await initDefaultApiList()
        ElMessage.success(t('device_query.api.initSuccess'))
        await loadList()
    } catch (error) {
        console.error(error)
    }
}



// 格式化时间
const formatTime = (timestamp: number) => {
    return new Date(timestamp * 1000).toLocaleString()
}

// 获取分类名称
const getCategoryName = (category: string) => {
    return t(`device_query.api.categories.${category}`) || category
}

// 格式化API列表
const formatApiList = (apis: any) => {
    return Object.entries(apis).map(([key, api]: [string, any]) => ({
        key,
        name: api.name,
        endpoint: api.endpoint,
        price: api.price
    }))
}

onMounted(() => {
    loadList()
})
</script>

<script lang="ts">
export default {
    name: 'DeviceQueryApi'
}
</script> 