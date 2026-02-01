<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addBusinessActive') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="businessActiveTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('siteId')" prop="site_id">
                        <el-input v-model="businessActiveTable.searchParam.site_id" :placeholder="t('siteIdPlaceholder')" />
                    </el-form-item>
                    
                    <el-form-item :label="t('businessId')" prop="business_id">
                        <el-select class="w-[280px]" v-model="businessActiveTable.searchParam.business_id" clearable :placeholder="t('businessIdPlaceholder')">
                           <el-option
                                       v-for="(item, index) in businessIdList"
                                       :key="index"
                                       :label="item['name']"
                                       :value="item['id']"
                                   />
                        </el-select>
                    </el-form-item>
                    
                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="businessActiveTable.searchParam.name" :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('createTime')" prop="create_time">
                        <el-date-picker v-model="businessActiveTable.searchParam.create_time" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="loadBusinessActiveList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="businessActiveTable.data" size="large" v-loading="businessActiveTable.loading">
                    <template #empty>
                        <span>{{ !businessActiveTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="business_id_name" :label="t('businessId')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="desc" :label="t('desc')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="gift" :label="t('gift')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="image" :label="t('image')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="contect" :label="t('contect')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="businessActiveTable.page" v-model:page-size="businessActiveTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="businessActiveTable.total"
                        @size-change="loadBusinessActiveList()" @current-change="loadBusinessActiveList" />
                </div>
            </div>

            <edit ref="editBusinessActiveDialog" @complete="loadBusinessActiveList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getBusinessActiveList, deleteBusinessActive, getWithBusinessList } from '@/addon/fast_pay/api/businessactive'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/fast_pay/views/businessactive/components/businessactive-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let businessActiveTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "site_id":"",
      "business_id":"",
      "name":"",
      "create_time":""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取商户活动列表
 */
const loadBusinessActiveList = (page: number = 1) => {
    businessActiveTable.loading = true
    businessActiveTable.page = page

    getBusinessActiveList({
        page: businessActiveTable.page,
        limit: businessActiveTable.limit,
         ...businessActiveTable.searchParam
    }).then(res => {
        businessActiveTable.loading = false
        businessActiveTable.data = res.data.data
        businessActiveTable.total = res.data.total
    }).catch(() => {
        businessActiveTable.loading = false
    })
}
loadBusinessActiveList()

const editBusinessActiveDialog: Record<string, any> | null = ref(null)

/**
 * 添加商户活动
 */
const addEvent = () => {
    editBusinessActiveDialog.value.setFormData()
    editBusinessActiveDialog.value.showDialog = true
}

/**
 * 编辑商户活动
 * @param data
 */
const editEvent = (data: any) => {
    editBusinessActiveDialog.value.setFormData(data)
    editBusinessActiveDialog.value.showDialog = true
}

/**
 * 删除商户活动
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('businessActiveDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteBusinessActive(id).then(() => {
            loadBusinessActiveList()
        }).catch(() => {
        })
    })
}

    
    const businessIdList = ref([])
    const setBusinessIdList = async () => {
    businessIdList.value = await (await getWithBusinessList({})).data
    }
    setBusinessIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadBusinessActiveList()
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
