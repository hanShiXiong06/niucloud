<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addRecycleDeviceModel') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="recycleDeviceModelTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('brandId')" prop="brand_id">
                        <el-input v-model="recycleDeviceModelTable.searchParam.brand_id" :placeholder="t('brandIdPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('modelName')" prop="model_name">
                        <el-input v-model="recycleDeviceModelTable.searchParam.model_name" :placeholder="t('modelNamePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('networkModel')" prop="network_model">
                        <el-input v-model="recycleDeviceModelTable.searchParam.network_model" :placeholder="t('networkModelPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('capacity')" prop="capacity">
                        <el-input v-model="recycleDeviceModelTable.searchParam.capacity" :placeholder="t('capacityPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('deviceType')" prop="device_type">
                        <el-input v-model="recycleDeviceModelTable.searchParam.device_type" :placeholder="t('deviceTypePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-input v-model="recycleDeviceModelTable.searchParam.status" :placeholder="t('statusPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('createAt')" prop="create_at">
                        <el-date-picker v-model="recycleDeviceModelTable.searchParam.create_at" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    
                    <el-form-item :label="t('updateAt')" prop="update_at">
                        <el-date-picker v-model="recycleDeviceModelTable.searchParam.update_at" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="loadRecycleDeviceModelList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="recycleDeviceModelTable.data" size="large" v-loading="recycleDeviceModelTable.loading">
                    <template #empty>
                        <span>{{ !recycleDeviceModelTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="brand_id" :label="t('brandId')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="model_name" :label="t('modelName')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="network_model" :label="t('networkModel')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="capacity" :label="t('capacity')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="device_type" :label="t('deviceType')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="status" :label="t('status')" min-width="120" :show-overflow-tooltip="true"/>
                    
                     <el-table-column :label="t('createAt')" min-width="180" align="center" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            {{ row.create_at || '' }}
                        </template>
                    </el-table-column>
                    
                     <el-table-column :label="t('updateAt')" min-width="180" align="center" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            {{ row.update_at || '' }}
                        </template>
                    </el-table-column>
                    
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="recycleDeviceModelTable.page" v-model:page-size="recycleDeviceModelTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="recycleDeviceModelTable.total"
                        @size-change="loadRecycleDeviceModelList()" @current-change="loadRecycleDeviceModelList" />
                </div>
            </div>

            <edit ref="editRecycleDeviceModelDialog" @complete="loadRecycleDeviceModelList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getRecycleDeviceModelList, deleteRecycleDeviceModel } from '@/addon/recycle/api/recycle_device_model'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/recycle/views/recycle_device_model/components/recycle-device-model-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let recycleDeviceModelTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "brand_id":"",
      "model_name":"",
      "network_model":"",
      "capacity":"",
      "device_type":"",
      "status":"",
      "create_at":[],
      "update_at":[]
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取回收设备型号列表
 */
const loadRecycleDeviceModelList = (page: number = 1) => {
    recycleDeviceModelTable.loading = true
    recycleDeviceModelTable.page = page

    getRecycleDeviceModelList({
        page: recycleDeviceModelTable.page,
        limit: recycleDeviceModelTable.limit,
         ...recycleDeviceModelTable.searchParam
    }).then(res => {
        recycleDeviceModelTable.loading = false
        recycleDeviceModelTable.data = res.data.data
        recycleDeviceModelTable.total = res.data.total
    }).catch(() => {
        recycleDeviceModelTable.loading = false
    })
}
loadRecycleDeviceModelList()

const editRecycleDeviceModelDialog: Record<string, any> | null = ref(null)

/**
 * 添加回收设备型号
 */
const addEvent = () => {
    editRecycleDeviceModelDialog.value.setFormData()
    editRecycleDeviceModelDialog.value.showDialog = true
}

/**
 * 编辑回收设备型号
 * @param data
 */
const editEvent = (data: any) => {
    editRecycleDeviceModelDialog.value.setFormData(data)
    editRecycleDeviceModelDialog.value.showDialog = true
}

/**
 * 删除回收设备型号
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('recycleDeviceModelDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteRecycleDeviceModel(id).then(() => {
            loadRecycleDeviceModelList()
        }).catch(() => {
        })
    })
}

    

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadRecycleDeviceModelList()
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
