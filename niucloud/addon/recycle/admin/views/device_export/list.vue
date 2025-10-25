<template>
    <!--回收设备导出-->
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
            </div>

            <el-card class="box-card !border-none my-[20px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="deviceTableData.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('imei')" prop="imei">
                        <el-input v-model.trim="deviceTableData.searchParam.imei" class="!w-[200px]" :placeholder="t('请输入IMEI')" />
                    </el-form-item>

                    <el-form-item :label="t('型号名称')" prop="model">
                        <el-input v-model.trim="deviceTableData.searchParam.model" class="!w-[200px]" :placeholder="t('请输入型号名称')" />
                    </el-form-item>

                    <el-form-item :label="t('分类')" prop="category_id">
                        <el-select v-model="deviceTableData.searchParam.category_id" clearable :placeholder="t('请选择分类')" class="input-width">
                            <el-option :label="t('请选择分类')" value="" />
                            <el-option :label="item.name" :value="item.id" v-for="item in categoryList" :key="item.id" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('回收时间')" prop="update_at">
                        <el-date-picker 
                            v-model="deviceTableData.searchParam.update_at" 
                            type="datetimerange" 
                            value-format="YYYY-MM-DD HH:mm:ss" 
                            :start-placeholder="t('startDate')" 
                            :end-placeholder="t('endDate')"
                            format="YYYY-MM-DD HH:mm"
                            date-format="YYYY/MM/DD ddd"
                            time-format="A hh:mm"
                        />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="loadDeviceList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                        <el-button type="primary" @click="exportEvent" :disabled="deviceTableData.total === 0">{{ t('export') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="deviceTableData.data" size="large" v-loading="deviceTableData.loading">
                    <template #empty>
                        <span>{{ !deviceTableData.loading ? t('emptyData') : '' }}</span>
                    </template>
                    
                    <el-table-column prop="imei" :label="t('imei')" min-width="120" />
                    <el-table-column prop="model" :label="t('型号')" min-width="150" show-overflow-tooltip />
                    <el-table-column prop="category_name" :label="t('分类')" min-width="100" align="center" />
                    <el-table-column prop="final_price" :label="t('定价')" min-width="100" align="right">
                        <template #default="{ row }">
                            ¥{{ row.final_price || '0.00' }}
                        </template>
                    </el-table-column>
                    <el-table-column prop="status_name" :label="t('status')" min-width="100" align="center">
                        <template #default="{ row }">
                            <el-tag type="success">{{ row.status_name }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('回收时间')" min-width="150" align="center">
                        <template #default="{ row }">
                            {{ row.update_at || '' }}
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('订单编号')" min-width="180" show-overflow-tooltip>
                        <template #default="{ row }">
                            {{ row.order?.order_no || '' }}
                        </template>
                    </el-table-column>
                </el-table>
                
                <div class="mt-[16px] flex justify-end">
                    <el-pagination 
                        v-model:current-page="deviceTableData.page" 
                        v-model:page-size="deviceTableData.limit"
                        layout="total, sizes, prev, pager, next, jumper" 
                        :total="deviceTableData.total"
                        @size-change="loadDeviceList()" 
                        @current-change="loadDeviceList" 
                    />
                </div>
            </div>
            
            <export-sure ref="exportSureDialog" :show="flag" type="recycle_device" :searchParam="deviceTableData.searchParam" @close="handleClose" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { FormInstance } from 'element-plus'
import { useRoute } from 'vue-router'
import { getRecycleDeviceList } from '@/addon/recycle/api/device_export'

const route = useRoute()
const pageName = route.meta.title

const deviceTableData = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        imei: '',
        model: '',
        category_id: '',
        update_at: [],
        status: 5  // 固定为已回收状态
    }
})

const searchFormRef = ref<FormInstance>()

// 设备分类列表
const categoryList = ref([
    { id: 1, name: '手机' },
    { id: 2, name: '平板' },
    { id: 3, name: '笔记本' },
    { id: 4, name: '手表' },
    { id: 5, name: '其他' }
])

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadDeviceList()
}

/**
 * 获取设备列表
 */
const loadDeviceList = () => {
    deviceTableData.loading = true
    const searchParam = { 
        ...deviceTableData.searchParam,
        page: deviceTableData.page,
        limit: deviceTableData.limit 
    }
    
    getRecycleDeviceList(searchParam).then((res: any) => {
        deviceTableData.loading = false
        deviceTableData.data = res.data.data
        deviceTableData.total = res.data.total
    }).catch(() => {
        deviceTableData.loading = false
    })
}

/**
 * 设备导出
 */
const exportSureDialog = ref(null)
const flag = ref(false)
const handleClose = (val) => {
    flag.value = val
}
const exportEvent = () => {
    flag.value = true
}

// 初始化加载
loadDeviceList()
</script>

<style lang="scss" scoped></style>
