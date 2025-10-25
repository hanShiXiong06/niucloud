<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addRecycleDeviceBrand') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="recycleDeviceBrandTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('siteId')" prop="site_id">
                        <el-input v-model="recycleDeviceBrandTable.searchParam.site_id" :placeholder="t('siteIdPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('brandName')" prop="brand_name">
                        <el-input v-model="recycleDeviceBrandTable.searchParam.brand_name" :placeholder="t('brandNamePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('brandCode')" prop="brand_code">
                        <el-input v-model="recycleDeviceBrandTable.searchParam.brand_code" :placeholder="t('brandCodePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="recycleDeviceBrandTable.searchParam.status" clearable :placeholder="t('statusPlaceholder')">
                            <el-option label="全部" value=""></el-option>
                    
                        </el-select>
                    </el-form-item>
                    
                    <el-form-item :label="t('sort')" prop="sort">
                        <el-input v-model="recycleDeviceBrandTable.searchParam.sort" :placeholder="t('sortPlaceholder')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadRecycleDeviceBrandList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="recycleDeviceBrandTable.data" size="large" v-loading="recycleDeviceBrandTable.loading">
                    <template #empty>
                        <span>{{ !recycleDeviceBrandTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="id" :label="t('id')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="brand_name" :label="t('brandName')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="brand_code" :label="t('brandCode')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="status" :label="t('status')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="recycleDeviceBrandTable.page" v-model:page-size="recycleDeviceBrandTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="recycleDeviceBrandTable.total"
                        @size-change="loadRecycleDeviceBrandList()" @current-change="loadRecycleDeviceBrandList" />
                </div>
            </div>

            <edit ref="editRecycleDeviceBrandDialog" @complete="loadRecycleDeviceBrandList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getRecycleDeviceBrandList, deleteRecycleDeviceBrand } from '@/addon/recycle/api/recycle_device_brand'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/recycle/views/recycle_device_brand/components/recycle-device-brand-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let recycleDeviceBrandTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "site_id":"",
      "brand_name":"",
      "brand_code":"",
      "status":"",
      "sort":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取回收设备品牌列表
 */
const loadRecycleDeviceBrandList = (page: number = 1) => {
    recycleDeviceBrandTable.loading = true
    recycleDeviceBrandTable.page = page

    getRecycleDeviceBrandList({
        page: recycleDeviceBrandTable.page,
        limit: recycleDeviceBrandTable.limit,
         ...recycleDeviceBrandTable.searchParam
    }).then(res => {
        recycleDeviceBrandTable.loading = false
        recycleDeviceBrandTable.data = res.data.data
        recycleDeviceBrandTable.total = res.data.total
    }).catch(() => {
        recycleDeviceBrandTable.loading = false
    })
}
loadRecycleDeviceBrandList()

const editRecycleDeviceBrandDialog: Record<string, any> | null = ref(null)

/**
 * 添加回收设备品牌
 */
const addEvent = () => {
    editRecycleDeviceBrandDialog.value.setFormData()
    editRecycleDeviceBrandDialog.value.showDialog = true
}

/**
 * 编辑回收设备品牌
 * @param data
 */
const editEvent = (data: any) => {
    editRecycleDeviceBrandDialog.value.setFormData(data)
    editRecycleDeviceBrandDialog.value.showDialog = true
}

/**
 * 删除回收设备品牌
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('recycleDeviceBrandDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteRecycleDeviceBrand(id).then(() => {
            loadRecycleDeviceBrandList()
        }).catch(() => {
        })
    })
}

    

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadRecycleDeviceBrandList()
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
