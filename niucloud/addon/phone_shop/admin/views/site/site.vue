<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addSite') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="siteTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('siteId')" prop="site_id">
                        <el-input v-model="siteTable.searchParam.site_id" :placeholder="t('siteIdPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('siteName')" prop="site_name">
                        <el-input v-model="siteTable.searchParam.site_name" :placeholder="t('siteNamePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('client')" prop="client">
                        <el-input v-model="siteTable.searchParam.client" :placeholder="t('clientPlaceholder')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadSiteList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="siteTable.data" size="large" v-loading="siteTable.loading">
                    <template #empty>
                        <span>{{ !siteTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="site_name" :label="t('siteName')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="client" :label="t('client')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column :label="t('categoryStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in category_statusList">
                                <div v-if="item.value == row.category_status">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('brandStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in brand_statusList">
                                <div v-if="item.value == row.brand_status">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('labelGroupStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in label_group_statusList">
                                <div v-if="item.value == row.label_group_status">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('labelStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in label_statusList">
                                <div v-if="item.value == row.label_status">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('serviceStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in service_statusList">
                                <div v-if="item.value == row.service_status">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('priceStatus')" min-width="180" align="center"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in price_statusList">
                                <div v-if="item.value == row.price_status">{{ item.name }}</div>
                            </div>
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
                    <el-pagination v-model:current-page="siteTable.page" v-model:page-size="siteTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="siteTable.total"
                        @size-change="loadSiteList()" @current-change="loadSiteList" />
                </div>
            </div>

            <edit ref="editSiteDialog" @complete="loadSiteList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getSiteList, deleteSite } from '@/addon/phone_shop/api/site'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/phone_shop/views/site/components/site-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let siteTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "site_id": "",
        "site_name": "",
        "client": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据
const category_statusList = ref([] as any[])
const category_statusDictList = async () => {
    category_statusList.value = await (await useDictionary('status')).data.dictionary
}
category_statusDictList();
const brand_statusList = ref([] as any[])
const brand_statusDictList = async () => {
    brand_statusList.value = await (await useDictionary('status')).data.dictionary
}
brand_statusDictList();
const label_group_statusList = ref([] as any[])
const label_group_statusDictList = async () => {
    label_group_statusList.value = await (await useDictionary('status')).data.dictionary
}
label_group_statusDictList();
const label_statusList = ref([] as any[])
const label_statusDictList = async () => {
    label_statusList.value = await (await useDictionary('status')).data.dictionary
}
label_statusDictList();
const service_statusList = ref([] as any[])
const service_statusDictList = async () => {
    service_statusList.value = await (await useDictionary('status')).data.dictionary
}
const price_statusDictList = async () => {
    price_statusList.value = await (await useDictionary('status')).data.dictionary
}
// price_status
service_statusDictList();

/**
 * 获取站点(二手)管理列表
 */
const loadSiteList = (page: number = 1) => {
    siteTable.loading = true
    siteTable.page = page

    getSiteList({
        page: siteTable.page,
        limit: siteTable.limit,
        ...siteTable.searchParam
    }).then(res => {
        siteTable.loading = false
        siteTable.data = res.data.data
        siteTable.total = res.data.total
    }).catch(() => {
        siteTable.loading = false
    })
}
loadSiteList()

const editSiteDialog: Record<string, any> | null = ref(null)

/**
 * 添加站点(二手)管理
 */
const addEvent = () => {
    editSiteDialog.value.setFormData()
    editSiteDialog.value.showDialog = true
}

/**
 * 编辑站点(二手)管理
 * @param data
 */
const editEvent = (data: any) => {

    editSiteDialog.value.setFormData(data)
    editSiteDialog.value.showDialog = true
}

/**
 * 删除站点(二手)管理
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('siteDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteSite(id).then(() => {
            loadSiteList()
        }).catch(() => {
        })
    })
}



const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadSiteList()
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
