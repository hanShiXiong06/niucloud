<template>
    <div>
        <div class="flex justify-between items-center mb-4">
            <span class="text-lg">报价单配置管理</span>
            <el-button type="primary" @click="addEvent">添加报价单配置</el-button>
        </div>

        <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
            <el-form :inline="true" :model="searchParam" ref="searchFormRef">
                <el-form-item label="报价ID" prop="quotation_id">
                    <el-input v-model="searchParam.quotation_id" placeholder="请输入报价ID" />
                </el-form-item>
                <el-form-item label="价格名称" prop="price_name">
                    <el-input v-model="searchParam.price_name" placeholder="请输入价格名称" />
                </el-form-item>
                <el-form-item label="状态" prop="is_enable">
                    <el-select v-model="searchParam.is_enable" clearable placeholder="请选择状态">
                        <el-option label="全部" value=""></el-option>
                        <el-option label="启用" :value="1"></el-option>
                        <el-option label="禁用" :value="0"></el-option>
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList()">查询</el-button>
                    <el-button @click="resetForm">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <div class="mt-[10px]">
            <el-table :data="table.data" size="large" v-loading="table.loading">
                <template #empty>
                    <span>{{ !table.loading ? '暂无数据' : '' }}</span>
                </template>
                <el-table-column prop="id" label="ID" min-width="80" />
                <el-table-column prop="quotation_id" label="报价ID" min-width="120" />
                <el-table-column prop="price_name" label="价格名称" min-width="150" />
                <el-table-column prop="config_name" label="配置名称" min-width="150" />
                <el-table-column prop="is_enable" label="状态" min-width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_enable === 1 ? 'success' : 'danger'">
                            {{ row.is_enable === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="auto_request" label="自动请求" min-width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.auto_request === 1 ? 'success' : 'info'">
                            {{ row.auto_request === 1 ? '是' : '否' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="request_time" label="请求时间" min-width="120" />
                <el-table-column prop="create_time" label="创建时间" min-width="180" />
                <el-table-column label="操作" fixed="right" min-width="200">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="editEvent(row)">编辑</el-button>
                        <el-button type="primary" link @click="modifyStatusEvent(row)">
                            {{ row.is_enable === 1 ? '禁用' : '启用' }}
                        </el-button>
                        <el-button type="primary" link @click="deleteEvent(row.id)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-[16px] flex justify-end">
                <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                    layout="total, sizes, prev, pager, next, jumper" :total="table.total"
                    @size-change="loadList()" @current-change="loadList" />
            </div>
        </div>

        <edit ref="editDialog" @complete="loadList" />
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import type { FormInstance } from 'element-plus'
import { ElMessageBox } from 'element-plus'
import { getQuotationConfigList, deleteQuotationConfig, modifyQuotationConfigStatus } from '@/addon/recycle/api/quotation'
import Edit from '@/addon/recycle/views/quotation/components/quotation-config-edit.vue'

const searchFormRef = ref<FormInstance>()
const editDialog = ref<InstanceType<typeof Edit>>()

const table = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        quotation_id: '',
        price_name: '',
        is_enable: ''
    }
})

const searchParam = table.searchParam

/**
 * 获取列表
 */
const loadList = (page: number = 1) => {
    table.loading = true
    table.page = page

    getQuotationConfigList({
        page: table.page,
        limit: table.limit,
        ...table.searchParam
    }).then(res => {
        table.loading = false
        table.data = res.data.data
        table.total = res.data.total
    }).catch(() => {
        table.loading = false
    })
}

/**
 * 添加
 */
const addEvent = () => {
    editDialog.value?.setFormData()
    editDialog.value!.showDialog = true
}

/**
 * 编辑
 */
const editEvent = (data: any) => {
    editDialog.value?.setFormData(data)
    editDialog.value!.showDialog = true
}

/**
 * 删除
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm('确定要删除该报价单配置吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning',
    }).then(() => {
        deleteQuotationConfig(id).then(() => {
            loadList()
        }).catch(() => {})
    })
}

/**
 * 修改状态
 */
const modifyStatusEvent = (row: any) => {
    const status = row.is_enable === 1 ? 0 : 1
    modifyQuotationConfigStatus(row.id, { is_enable: status }).then(() => {
        loadList()
    }).catch(() => {})
}

const resetForm = () => {
    if (!searchFormRef.value) return
    searchFormRef.value.resetFields()
    loadList()
}

// 初始化
loadList()

// 暴露方法供父组件调用
defineExpose({
    loadList,
})
</script>

<style scoped>
</style>

