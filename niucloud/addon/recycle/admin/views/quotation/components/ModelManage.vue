<template>
    <div>
        <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
            <el-form :inline="true" :model="searchParam" ref="searchFormRef">
                <el-form-item label="商品名称" prop="goods_name">
                    <el-input v-model="searchParam.goods_name" placeholder="请输入商品名称" clearable />
                </el-form-item>
                <el-form-item label="同步状态" prop="sync_enable">
                    <el-select v-model="searchParam.sync_enable" clearable placeholder="请选择同步状态">
                        <el-option label="全部" value=""></el-option>
                        <el-option label="同步" :value="1"></el-option>
                        <el-option label="不同步" :value="0"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <div class="mb-2">
            <el-button type="primary" @click="requestModel">请求型号</el-button>
            <el-button type="primary" @click="batchSetSync(1)" :disabled="selected.length === 0">
                批量开启同步
            </el-button>
            <el-button @click="batchSetSync(0)" :disabled="selected.length === 0">
                批量关闭同步
            </el-button>
        </div>

        <el-table :data="list" size="large" v-loading="loading" @selection-change="handleSelectionChange">
            <el-table-column type="selection" width="55" />
            <el-table-column prop="id" label="ID" width="80" />
            <el-table-column prop="goods_id" label="商品ID" width="100" />
            <el-table-column prop="goods_name" label="商品名称" min-width="200" />
            <el-table-column prop="sync_enable" label="同步状态" width="120">
                <template #default="{ row }">
                    <el-switch
                        v-model="row.sync_enable"
                        :active-value="1"
                        :inactive-value="0"
                        @change="setSyncStatus(row)"
                    />
                </template>
            </el-table-column>
            <el-table-column prop="create_at" label="创建时间" width="180">
                <template #default="{ row }">
                    {{ formatTime(row.create_at) }}
                </template>
            </el-table-column>
        </el-table>

        <!-- 请求型号对话框 -->
        <el-dialog v-model="requestDialogVisible" title="请求型号" width="50%" :destroy-on-close="true">
            <el-form :model="requestForm" label-width="120px" ref="requestFormRef">
                <el-form-item label="报价单配置" prop="config_id" :rules="[{ required: true, message: '请选择报价单配置', trigger: 'change' }]">
                    <el-select v-model="requestForm.config_id" placeholder="请选择报价单配置" filterable style="width: 100%">
                        <el-option
                            v-for="item in quotationConfigList"
                            :key="item.id"
                            :label="`${item.config_name || '配置'}-${item.price_name} (ID: ${item.id})`"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </el-form>
            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="requestDialogVisible = false">取消</el-button>
                    <el-button type="primary" :loading="requestLoading" @click="confirmRequest">确定</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import type { FormInstance } from 'element-plus'
import {
    getQuotationSpecModelList,
    setQuotationSpecModelSyncStatus,
    batchSetQuotationSpecModelSyncStatus,
    sendQuotationRequest,
    getQuotationConfigList,
} from '@/addon/recycle/api/quotation'
import { ElMessage } from 'element-plus'

const emit = defineEmits(['refresh-stats'])

// 列表数据
const list = ref([])
const loading = ref(false)
const selected = ref([])
const searchParam = reactive({
    goods_name: '',
    sync_enable: '',
})

// 请求型号相关
const requestDialogVisible = ref(false)
const requestLoading = ref(false)
const requestForm = reactive({
    config_id: null as number | null,
})
const requestFormRef = ref<FormInstance>()
const quotationConfigList = ref<Array<{ id: number, quotation_id: number, price_name: string, config_name: string }>>([])

// 加载列表
const loadList = async () => {
    loading.value = true
    try {
        const res = await getQuotationSpecModelList(searchParam)
        list.value = res.data || []
    } catch (error) {
        console.error('加载型号列表失败:', error)
    } finally {
        loading.value = false
    }
}

// 设置同步状态
const setSyncStatus = async (row: any) => {
    try {
        await setQuotationSpecModelSyncStatus(row.id, { sync_enable: row.sync_enable })
        emit('refresh-stats')
    } catch (error) {
        // 恢复原值
        row.sync_enable = row.sync_enable === 1 ? 0 : 1
        console.error('设置型号同步状态失败:', error)
    }
}

// 批量设置同步状态
const batchSetSync = async (syncEnable: number) => {
    if (selected.value.length === 0) return
    try {
        await batchSetQuotationSpecModelSyncStatus({
            ids: selected.value.map((item: any) => item.id),
            sync_enable: syncEnable,
        })
        selected.value = []
        loadList()
        emit('refresh-stats')
    } catch (error) {
        console.error('批量设置型号同步状态失败:', error)
    }
}

const handleSelectionChange = (selection: any[]) => {
    selected.value = selection
}

const resetSearch = () => {
    searchParam.goods_name = ''
    searchParam.sync_enable = ''
    loadList()
}

// 格式化时间
const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return date.toLocaleString('zh-CN')
}

// 请求型号
const requestModel = async () => {
    // 加载报价单配置列表
    await loadQuotationConfigList()
    requestForm.config_id = null
    requestDialogVisible.value = true
}

// 加载报价单配置列表
const loadQuotationConfigList = async () => {
    try {
        const res = await getQuotationConfigList({ limit: 100 })
        quotationConfigList.value = res.data.data || []
        
        // 如果还有更多数据，继续获取
        if (res.data.total > 100) {
            const totalPages = Math.ceil(res.data.total / 100)
            for (let page = 2; page <= totalPages; page++) {
                const nextRes = await getQuotationConfigList({ limit: 100, page })
                quotationConfigList.value.push(...(nextRes.data.data || []))
            }
        }
    } catch (error) {
        console.error('加载报价单配置列表失败:', error)
        ElMessage.error('加载报价单配置列表失败')
    }
}

// 确认请求
const confirmRequest = async () => {
    if (!requestFormRef.value) return
    
    await requestFormRef.value.validate(async (valid: boolean) => {
        if (!valid) return
        
        if (!requestForm.config_id) {
            ElMessage.warning('请选择报价单配置')
            return
        }
        
        requestLoading.value = true
        try {
            await sendQuotationRequest({ config_id: requestForm.config_id })
            ElMessage.success('请求型号成功')
            requestDialogVisible.value = false
            // 刷新列表
            loadList()
            emit('refresh-stats')
        } catch (error) {
            console.error('请求型号失败:', error)
        } finally {
            requestLoading.value = false
        }
    })
}

// 初始化
onMounted(() => {
    loadList()
})

// 暴露方法供父组件调用
defineExpose({
    loadList,
})
</script>

<style scoped>
</style>

