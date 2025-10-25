<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addRecycleUserAddress') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="recycleUserAddressTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('address')" prop="address">
                        <el-input v-model="recycleUserAddressTable.searchParam.address" :placeholder="t('addressPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('idCard')" prop="id_card">
                        <el-input v-model="recycleUserAddressTable.searchParam.id_card" :placeholder="t('idCardPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('mobile')" prop="mobile">
                        <el-input v-model="recycleUserAddressTable.searchParam.mobile" :placeholder="t('mobilePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="recycleUserAddressTable.searchParam.name" :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('createTime')" prop="create_time">
                        <el-date-picker v-model="recycleUserAddressTable.searchParam.create_time" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    
                    <el-form-item :label="t('updateTime')" prop="update_time">
                        <el-date-picker v-model="recycleUserAddressTable.searchParam.update_time" type="datetimerange" format="YYYY-MM-DD hh:mm:ss"
                            :start-placeholder="t('startDate')" :end-placeholder="t('endDate')" />
                    </el-form-item>
                    
                    <el-form-item>
                        <el-button type="primary" @click="loadRecycleUserAddressList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="recycleUserAddressTable.data" size="large" v-loading="recycleUserAddressTable.loading">
                    <template #empty>
                        <span>{{ !recycleUserAddressTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="id" :label="t('memberId')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="address" :label="t('address')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="id_card" :label="t('idCard')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="mobile" :label="t('mobile')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true"/>
                    
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="recycleUserAddressTable.page" v-model:page-size="recycleUserAddressTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="recycleUserAddressTable.total"
                        @size-change="loadRecycleUserAddressList()" @current-change="loadRecycleUserAddressList" />
                </div>
            </div>

            <edit ref="editRecycleUserAddressDialog" @complete="loadRecycleUserAddressList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getRecycleUserAddressList, deleteRecycleUserAddress, getWithMemberList } from '@/addon/recycle/api/recycle_user_address'
import { img } from '@/utils/common'
import { ElMessageBox,FormInstance } from 'element-plus'
import Edit from '@/addon/recycle/views/recycle_user_address/components/recycle-user-address-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let recycleUserAddressTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam:{
      "member_id":"",
      "address":"",
      "id_card":"",
      "mobile":"",
      "card_pic":"",
      "name":"",
      "create_time":[],
      "update_time":[]
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
    

/**
 * 获取用户退货地址列表
 */
const loadRecycleUserAddressList = (page: number = 1) => {
    recycleUserAddressTable.loading = true
    recycleUserAddressTable.page = page

    getRecycleUserAddressList({
        page: recycleUserAddressTable.page,
        limit: recycleUserAddressTable.limit,
         ...recycleUserAddressTable.searchParam
    }).then(res => {
        recycleUserAddressTable.loading = false
        recycleUserAddressTable.data = res.data.data
        recycleUserAddressTable.total = res.data.total
    }).catch(() => {
        recycleUserAddressTable.loading = false
    })
}
loadRecycleUserAddressList()

const editRecycleUserAddressDialog: Record<string, any> | null = ref(null)

/**
 * 添加用户退货地址
 */
const addEvent = () => {
    editRecycleUserAddressDialog.value.setFormData()
    editRecycleUserAddressDialog.value.showDialog = true
}

/**
 * 编辑用户退货地址
 * @param data
 */
const editEvent = (data: any) => {
    editRecycleUserAddressDialog.value.setFormData(data)
    editRecycleUserAddressDialog.value.showDialog = true
}

/**
 * 删除用户退货地址
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('recycleUserAddressDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteRecycleUserAddress(id).then(() => {
            loadRecycleUserAddressList()
        }).catch(() => {
        })
    })
}

    
    const memberIdList = ref([])
    const setMemberIdList = async () => {
    memberIdList.value = await (await getWithMemberList({})).data
    }
    setMemberIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadRecycleUserAddressList()
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
