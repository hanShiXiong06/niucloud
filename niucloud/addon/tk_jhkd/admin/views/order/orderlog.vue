<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-[20px]">{{ pageName }}</span>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="orderLogTable.searchParam" ref="searchFormRef">
                    <el-form-item label="订单号" prop="order_id">
                        <el-input v-model="orderLogTable.searchParam.order_id" placeholder="请输入订单号" />
                    </el-form-item>
                    <el-form-item :label="t('action')" prop="action">
                        <el-input v-model="orderLogTable.searchParam.action" :placeholder="t('actionPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('createTime')" prop="create_time">
                        <el-input v-model="orderLogTable.searchParam.create_time"
                            :placeholder="t('createTimePlaceholder')" />
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadOrderLogList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="orderLogTable.data" size="large" v-loading="orderLogTable.loading">
                    <template #empty>
                        <span>{{ !orderLogTable.loading ? t('emptyData') : '' }}</span>
                    </template>

                    <el-table-column prop="order_id" label="订单号" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="action" :label="t('action')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="main_type" :label="t('mainType')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <!-- <el-table-column prop="order_status" :label="t('orderStatus')" min-width="120"
                        :show-overflow-tooltip="true" /> -->


                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="orderLogTable.page" v-model:page-size="orderLogTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="orderLogTable.total"
                        @size-change="loadOrderLogList()" @current-change="loadOrderLogList" />
                </div>
            </div>

            <edit ref="editOrderLogDialog" @complete="loadOrderLogList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getOrderLogList, deleteOrderLog, } from '@/addon/tk_jhkd/api/order'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/tk_jhkd/views/order/components/orderlog-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let orderLogTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "order_id": "",
        "action": "",
        "main_type": "",
        "create_time": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取订单日志列表
 */
const loadOrderLogList = (page: number = 1) => {
    orderLogTable.loading = true
    orderLogTable.page = page

    getOrderLogList({
        page: orderLogTable.page,
        limit: orderLogTable.limit,
        ...orderLogTable.searchParam
    }).then(res => {
        orderLogTable.loading = false
        orderLogTable.data = res.data.data
        orderLogTable.total = res.data.total
    }).catch(() => {
        orderLogTable.loading = false
    })
}
loadOrderLogList()

const editOrderLogDialog: Record<string, any> | null = ref(null)

/**
 * 添加订单日志
 */
const addEvent = () => {
    editOrderLogDialog.value.setFormData()
    editOrderLogDialog.value.showDialog = true
}

/**
 * 编辑订单日志
 * @param data
 */
const editEvent = (data: any) => {
    editOrderLogDialog.value.setFormData(data)
    editOrderLogDialog.value.showDialog = true
}

/**
 * 删除订单日志
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('orderLogDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteOrderLog(id).then(() => {
            loadOrderLogList()
        }).catch(() => {
        })
    })
}



const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadOrderLogList()
}
</script>

<style lang="scss" scoped>
/* 多行超出隐藏 */
.multi-hidden {
    word-break: break-all;
    text-overflow: ellipsis;
    overflow: hidden;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
}
</style>
