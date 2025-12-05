<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addAiimagePackage') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimagePackageTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="aiimagePackageTable.searchParam.name" :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="aiimagePackageTable.searchParam.status" placeholder="请选择">
                            <el-option label="启用" value="1"></el-option>
                            <el-option label="禁用" value="0"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadAiimagePackageList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="aiimagePackageTable.data" size="large" v-loading="aiimagePackageTable.loading">
                    <template #empty>
                        <span>{{ !aiimagePackageTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column :label="t('image')" width="100" align="left">
                        <template #default="{ row }">
                            <el-avatar v-if="row.image" :src="img(row.image)" />
                            <el-avatar v-else icon="UserFilled" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="price" :label="t('price')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="point" :label="t('point')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="num" :label="t('num')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="type" :label="t('type')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.type == 'card'" type="info">卡密</el-tag>
                            <el-tag v-if="row.type == 'point'" type="success">充值</el-tag>
                        </template>
                    </el-table-column>

                    <!-- <el-table-column prop="day" :label="t('day')" min-width="120" :show-overflow-tooltip="true" /> -->
                    <!-- 
                    <el-table-column prop="limit" :label="t('limit')" min-width="120" :show-overflow-tooltip="true" /> -->


                    <el-table-column prop="status" :label="t('status')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.status == 1" type="success">启用</el-tag>
                            <el-tag v-if="row.status == 0" type="info">禁用</el-tag>

                        </template>
                    </el-table-column>
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="aiimagePackageTable.page"
                        v-model:page-size="aiimagePackageTable.limit" layout="total, sizes, prev, pager, next, jumper"
                        :total="aiimagePackageTable.total" @size-change="loadAiimagePackageList()"
                        @current-change="loadAiimagePackageList" />
                </div>
            </div>

            <edit ref="editAiimagePackageDialog" @complete="loadAiimagePackageList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimagePackageList, deleteAiimagePackage } from '@/addon/ai_image/api/aiimagepackage'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/ai_image/views/aiimagepackage/components/aiimagepackage-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let aiimagePackageTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "name": "",
        "type": "",
        "status": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取套餐列列表
 */
const loadAiimagePackageList = (page: number = 1) => {
    aiimagePackageTable.loading = true
    aiimagePackageTable.page = page

    getAiimagePackageList({
        page: aiimagePackageTable.page,
        limit: aiimagePackageTable.limit,
        ...aiimagePackageTable.searchParam
    }).then(res => {
        aiimagePackageTable.loading = false
        aiimagePackageTable.data = res.data.data
        aiimagePackageTable.total = res.data.total
    }).catch(() => {
        aiimagePackageTable.loading = false
    })
}
loadAiimagePackageList()

const editAiimagePackageDialog: Record<string, any> | null = ref(null)

/**
 * 添加套餐列
 */
const addEvent = () => {
    editAiimagePackageDialog.value.setFormData()
    editAiimagePackageDialog.value.showDialog = true
}

/**
 * 编辑套餐列
 * @param data
 */
const editEvent = (data: any) => {
    editAiimagePackageDialog.value.setFormData(data)
    editAiimagePackageDialog.value.showDialog = true
}

/**
 * 删除套餐列
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimagePackageDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimagePackage(id).then(() => {
            loadAiimagePackageList()
        }).catch(() => {
        })
    })
}



const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadAiimagePackageList()
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
