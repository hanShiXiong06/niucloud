<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">{{ t('添加商家地址') }}</el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="shopAddressTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('手机号')" prop="mobile">
                        <el-input v-model.trim="shopAddressTable.searchParam.mobile" :placeholder="t('请输入手机号')" />
                    </el-form-item>
                    <el-form-item :label="t('地址')" prop="full_address">
                        <el-input v-model.trim="shopAddressTable.searchParam.full_address" :placeholder="t('请输入地址')" />
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadShopAddressList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="shopAddressTable.data" size="large" v-loading="shopAddressTable.loading">
                    <template #empty>
                        <span>{{ !shopAddressTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="contact_name" :label="t('联系人')" min-width="120" />
                    <el-table-column prop="mobile" :label="t('手机号')" min-width="120" />
                    <el-table-column prop="full_address" :label="t('地址')" min-width="120" :show-overflow-tooltip="true" />
                    <el-table-column prop="is_delivery_address" :label="t('addressType')" min-width="120" align="left">
                        <template #default="{ row }">
                            <div v-if="row.is_delivery_address">
                                {{ t('发货地址') }}
                                <el-tag size="small" v-if="row.is_default_delivery">{{ t('默认') }}</el-tag>
                            </div>
                            <div v-if="row.is_refund_address">
                                {{ t('退货地址') }}
                                <el-tag size="small" v-if="row.is_default_refund">{{ t('默认') }}</el-tag>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('操作')" fixed="right" min-width="120" align="right">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('编辑') }}</el-button>
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="shopAddressTable.page"
                                   v-model:page-size="shopAddressTable.limit"
                                   layout="total, sizes, prev, pager, next, jumper" :total="shopAddressTable.total"
                                   @size-change="loadShopAddressList()" @current-change="loadShopAddressList" />
                </div>
            </div>

        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { getShopAddressList, deleteShopAddress } from '@/addon/recycle/api/shop_address'
import { ElMessageBox, FormInstance } from 'element-plus'
import { useRouter, useRoute } from 'vue-router'
import { setTablePageStorage, getTablePageStorage } from "@/utils/common"

const route = useRoute()
const pageName = route.meta.title

const shopAddressTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        mobile: '',
        full_address: ''
    }
})

const searchFormRef = ref<FormInstance>()

/**
 * 获取商家地址库列表
 */
const loadShopAddressList = (page: number = 1) => {
    shopAddressTable.loading = true
    shopAddressTable.page = page

    getShopAddressList({
        page: shopAddressTable.page,
        limit: shopAddressTable.limit,
        ...shopAddressTable.searchParam
    }).then(res => {
        shopAddressTable.loading = false
        shopAddressTable.data = res.data.data
        shopAddressTable.total = res.data.total
        setTablePageStorage(shopAddressTable.page, shopAddressTable.limit, shopAddressTable.searchParam)
    }).catch(() => {
        shopAddressTable.loading = false
    })
}
loadShopAddressList(getTablePageStorage(shopAddressTable.searchParam).page)

const router = useRouter()

/**
 * 添加商家地址库
 */
const addEvent = () => {
    console.log(11);
    
    router.push('/recycle/address/edit')
}

/**
 * 编辑商家地址库
 * @param data
 */
const editEvent = (data: any) => {
    router.push('/recycle/address/edit?id=' + data.id)
}

/**
 * 删除商家地址库
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('shopAddressDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        deleteShopAddress(id).then(() => {
            loadShopAddressList()
        }).catch(() => {
        })
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadShopAddressList()
}
</script>

<style lang="scss" scoped>
</style>
