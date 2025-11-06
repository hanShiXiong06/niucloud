<template>
    <div>
        <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
            <el-form :inline="true" :model="searchParam" ref="searchFormRef">
                <el-form-item label="规格名称" prop="spec_name">
                    <el-input v-model="searchParam.spec_name" placeholder="请输入规格名称" clearable />
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
            <el-table-column prop="spec_name" label="规格名称" min-width="200" />
            <el-table-column prop="spec_code" label="规格编码" width="150" />
            <el-table-column prop="sort" label="排序" width="100" />
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
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import {
    getQuotationSpecGradeSpecList,
    setQuotationSpecGradeSpecSyncStatus,
    batchSetQuotationSpecGradeSpecSyncStatus,
} from '@/addon/recycle/api/quotation'

const emit = defineEmits(['refresh-stats'])

// 列表数据
const list = ref([])
const loading = ref(false)
const selected = ref([])
const searchParam = reactive({
    spec_name: '',
    sync_enable: '',
})

// 加载列表
const loadList = async () => {
    loading.value = true
    try {
        const res = await getQuotationSpecGradeSpecList(searchParam)
        list.value = res.data || []
    } catch (error) {
        console.error('加载等级规格列表失败:', error)
    } finally {
        loading.value = false
    }
}

// 设置同步状态
const setSyncStatus = async (row: any) => {
    try {
        await setQuotationSpecGradeSpecSyncStatus(row.id, { sync_enable: row.sync_enable })
        emit('refresh-stats')
    } catch (error) {
        // 恢复原值
        row.sync_enable = row.sync_enable === 1 ? 0 : 1
        console.error('设置等级规格同步状态失败:', error)
    }
}

// 批量设置同步状态
const batchSetSync = async (syncEnable: number) => {
    if (selected.value.length === 0) return
    try {
        await batchSetQuotationSpecGradeSpecSyncStatus({
            ids: selected.value.map((item: any) => item.id),
            sync_enable: syncEnable,
        })
        selected.value = []
        loadList()
        emit('refresh-stats')
    } catch (error) {
        console.error('批量设置等级规格同步状态失败:', error)
    }
}

const handleSelectionChange = (selection: any[]) => {
    selected.value = selection
}

const resetSearch = () => {
    searchParam.spec_name = ''
    searchParam.sync_enable = ''
    loadList()
}

// 格式化时间
const formatTime = (timestamp: number) => {
    if (!timestamp) return ''
    const date = new Date(timestamp * 1000)
    return date.toLocaleString('zh-CN')
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

