<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-[20px]">{{ pageName }}</span>
            </div>
            <div class="mt-[10px]">
                <el-card class="!border-none" shadow="never">
                    <el-alert type="warning" :title="t('alert')" :closable="false" show-icon />
                </el-card>
                <el-table :data="tkjhkdBrandTable.data" size="large" v-loading="tkjhkdBrandTable.loading">
                    <template #empty>
                        <span>{{ !tkjhkdBrandTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <!-- <el-table-column prop="site_id" :label="t('siteId')" min-width="120" /> -->
                    <el-table-column prop="delivery_type" :label="t('deliveryType')" width="240" />
                    <el-table-column prop="name" :label="t('name')" width="180" />
                    <el-table-column :label="t('logo')" width="240" align="left">
                        <template #default="{ row }">
                            <el-avatar v-if="row.logo" :src="img(row.logo)" />
                            <el-avatar v-else icon="UserFilled" />
                        </template>
                    </el-table-column>
                    <!-- <el-table-column prop="status" :label="t('status')" min-width="120" />
                    <el-table-column prop="addon" :label="t('addon')" min-width="120" /> -->

                    <!-- <el-table-column :label="t('operation')" fixed="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="danger" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                       </template>
                    </el-table-column> -->

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="tkjhkdBrandTable.page" v-model:page-size="tkjhkdBrandTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="tkjhkdBrandTable.total"
                        @size-change="loadTkjhkdBrandList()" @current-change="loadTkjhkdBrandList" />
                </div>
            </div>

            <tkjhkd-brand-edit ref="editTkjhkdBrandDialog" @complete="loadTkjhkdBrandList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { getTkjhkdBrandList } from '@/addon/tk_jhkd/api/tkjhkd'
import { img } from '@/utils/common'
import { ElMessageBox } from 'element-plus'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let tkjhkdBrandTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: []
})

/**
 * 获取聚合快递品牌列表列表
 */
const loadTkjhkdBrandList = (page: number = 1) => {
    tkjhkdBrandTable.loading = true
    tkjhkdBrandTable.page = page

    getTkjhkdBrandList({
        page: tkjhkdBrandTable.page,
        limit: tkjhkdBrandTable.limit
    }).then(res => {
        tkjhkdBrandTable.loading = false
        tkjhkdBrandTable.data = res.data.data
        tkjhkdBrandTable.total = res.data.total
    }).catch(() => {
        tkjhkdBrandTable.loading = false
    })
}
loadTkjhkdBrandList()

const editTkjhkdBrandDialog: Record<string, any> | null = ref(null)

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadTkjhkdBrandList()
}
</script>

<style lang="scss" scoped></style>