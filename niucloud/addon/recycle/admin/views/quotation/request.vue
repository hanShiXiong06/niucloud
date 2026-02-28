<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <el-button type="primary" @click="sendRequestEvent">
                    发送报价请求
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="table.searchParam" ref="searchFormRef">
                    <el-form-item label="报价ID" prop="quotation_id">
                        <el-input v-model="table.searchParam.quotation_id" placeholder="请输入报价ID" />
                    </el-form-item>
                    <el-form-item label="价格名称" prop="price_name">
                        <el-input v-model="table.searchParam.price_name" placeholder="请输入价格名称" />
                    </el-form-item>
                    <el-form-item label="请求状态" prop="request_status">
                        <el-select v-model="table.searchParam.request_status" clearable placeholder="请选择请求状态">
                            <el-option label="全部" value=""></el-option>
                            <el-option label="成功" :value="1"></el-option>
                            <el-option label="失败" :value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadList()">查询</el-button>
                        <el-button @click="resetForm(searchFormRef)">重置</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="table.data" width="100%" v-loading="table.loading">
                    <template #empty>
                        <span>{{ !table.loading ? '暂无数据' : '' }}</span>
                    </template>
                    <el-table-column prop="id" label="ID" min-width="80" />
                    <el-table-column prop="quotation_id" label="报价ID" min-width="120" />
                    <el-table-column prop="price_name" label="价格名称" min-width="150" />
                    <el-table-column prop="request_status" label="请求状态" min-width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.request_status === 1 ? 'success' : 'danger'">
                                {{ row.request_status === 1 ? '成功' : '失败' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_at" label="创建时间" min-width="200" show-overflow-tooltip />
                    <el-table-column prop="update_at" label="更新时间" min-width="180" />
                    <el-table-column label="操作" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="viewInfoEvent(row)">查看详情</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="table.page" v-model:page-size="table.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="table.total"
                        @size-change="loadList()" @current-change="loadList" />
                </div>
            </div>

            <!-- 详情对话框 -->
            <el-dialog v-model="infoDialogVisible" title="请求详情" width="70%" :destroy-on-close="true">
                <el-descriptions width="100%" :column="2" border v-if="infoData">
                    <el-descriptions-item label="ID">{{ infoData.id }}</el-descriptions-item>
                    <el-descriptions-item label="报价ID">{{ infoData.quotation_id }}</el-descriptions-item>
                    <el-descriptions-item label="价格名称">{{ infoData.price_name }}</el-descriptions-item>
                    <el-descriptions-item label="请求状态">
                        <el-tag :type="infoData.request_status === 1 ? 'success' : 'danger'">
                            {{ infoData.request_status === 1 ? '成功' : '失败' }}
                        </el-tag>
                    </el-descriptions-item>
                    <el-descriptions-item label="请求URL" show-overflow-tooltip :span="2" >
                        <el-tooltip :content="infoData.request_url" placement="top">
                            <div class="break-all">
                                {{ infoData.request_url }}
                            </div>
                        </el-tooltip>

                    </el-descriptions-item>
                    <el-descriptions-item label="请求参数" :span="2">
                        <json-preview :data="infoData.request_params" label="请求参数" />
                    </el-descriptions-item>
                    <el-descriptions-item label="响应数据" :span="2">
                        <json-preview :data="infoData.response_data" label="响应数据" />
                    </el-descriptions-item>
                    <el-descriptions-item label="错误信息" :span="2">{{ infoData.error_message || '-' }}</el-descriptions-item>
                    <el-descriptions-item label="创建时间">{{ infoData.create_at }}</el-descriptions-item>
                </el-descriptions>
            </el-dialog>

            <!-- 发送请求对话框 -->
            <el-dialog v-model="sendDialogVisible" title="发送报价请求" width="50%" :destroy-on-close="true">
                <el-form :model="sendForm" label-width="120px" ref="sendFormRef">
                    <el-form-item label="报价单配置ID" prop="config_id">
                        <el-input-number v-model="sendForm.config_id" :min="1" placeholder="请输入报价单配置ID" style="width: 100%" />
                    </el-form-item>
                </el-form>
                <template #footer>
                    <span class="dialog-footer">
                        <el-button @click="sendDialogVisible = false">取消</el-button>
                        <el-button type="primary" :loading="sendLoading" @click="confirmSend">确定</el-button>
                    </span>
                </template>
            </el-dialog>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import type { FormInstance } from 'element-plus'
import { getQuotationRequestList, getQuotationRequestInfo, sendQuotationRequest } from '@/addon/recycle/api/quotation'
import JsonPreview from './components/json-preview.vue'

const route = useRoute()
const pageName = route.meta.title

const table = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        quotation_id: '',
        price_name: '',
        request_status: ''
    }
})

const searchFormRef = ref<FormInstance>()
const sendFormRef = ref<FormInstance>()
const infoDialogVisible = ref(false)
const sendDialogVisible = ref(false)
const sendLoading = ref(false)
const infoData = ref<any>(null)

const sendForm = reactive({
    config_id: null
})

/**
 * 获取列表
 */
const loadList = (page: number = 1) => {
    table.loading = true
    table.page = page

    getQuotationRequestList({
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
loadList()

/**
 * 查看详情
 */
const viewInfoEvent = async (row: any) => {
    try {
        const res = await getQuotationRequestInfo(row.id)
        infoData.value = res.data
        infoDialogVisible.value = true
    } catch (error) {
        console.error(error)
    }
}

/**
 * 发送请求
 */
const sendRequestEvent = () => {
    sendForm.config_id = null
    sendDialogVisible.value = true
}

const confirmSend = async () => {
    if (!sendForm.config_id) {
        return
    }
    sendLoading.value = true
    sendQuotationRequest({ config_id: sendForm.config_id }).then(() => {
        sendLoading.value = false
        sendDialogVisible.value = false
        loadList()
    }).catch(() => {
        sendLoading.value = false
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadList()
}
</script>

<style lang="scss" scoped>
.break-all {
    word-break: break-all;
}
</style>
