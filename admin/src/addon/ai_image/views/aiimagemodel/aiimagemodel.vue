<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <div class="flex flex-items">
                    <el-button type="primary" @click="addEvent">
                        {{ t('addAiimageModel') }}
                    </el-button>
                    <el-button type="primary" :loading="asyncLoading" loadingtext="同步中" @click="asyncModelFn">
                        {{ t('同步智能体') }}
                    </el-button>
                </div>

            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimageModelTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="aiimageModelTable.searchParam.name" :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="aiimageModelTable.searchParam.status" placeholder="请选择">
                            <el-option label="启用" value="1"></el-option>
                            <el-option label="禁用" value="0"></el-option>
                        </el-select>
                    </el-form-item>

                    <!-- <el-form-item :label="t('isVip')" prop="is_vip">
                        <el-input v-model="aiimageModelTable.searchParam.is_vip" :placeholder="t('isVipPlaceholder')" />
                    </el-form-item> -->
                    <el-form-item>
                        <el-button type="primary" @click="loadAiimageModelList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="aiimageModelTable.data" size="large" v-loading="aiimageModelTable.loading">
                    <template #empty>
                        <span>{{ !aiimageModelTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column :label="t('logo')" width="100" align="left">
                        <template #default="{ row }">
                            <el-avatar v-if="row.logo" :src="img(row.logo)" />
                            <el-avatar v-else icon="UserFilled" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="desc" :label="t('desc')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="prompt" :label="t('prompt')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="sort" :label="t('sort')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="status" :label="t('status')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.status == 1" type="success">{{ t('启用') }}</el-tag>
                            <el-tag v-else type="danger">{{ t('禁用') }}</el-tag>
                        </template>
                    </el-table-column>


                    <el-table-column prop="is_upload_image" :label="t('上传图像')" min-width="120"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_upload_image == 1" type="success">{{ t('需要上传') }}</el-tag>
                            <el-tag v-else type="danger">{{ t('不需要上传') }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column prop="point" :label="t('point')" min-width="120" :show-overflow-tooltip="true" />

                    <!-- <el-table-column prop="is_vip" :label="t('isVip')" min-width="120" :show-overflow-tooltip="true" /> -->
                    <el-table-column prop="model" :label="t('模型')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.model == 'gemini-3-pro-image-preview'" type="success">{{
                                t('nano-banana-pro') }}</el-tag>
                            <el-tag v-else type="info">{{ t('nano-banana') }}</el-tag>
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
                    <el-pagination v-model:current-page="aiimageModelTable.page"
                        v-model:page-size="aiimageModelTable.limit" layout="total, sizes, prev, pager, next, jumper"
                        :total="aiimageModelTable.total" @size-change="loadAiimageModelList()"
                        @current-change="loadAiimageModelList" />
                </div>
            </div>

            <edit ref="editAiimageModelDialog" @complete="loadAiimageModelList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimageModelList, deleteAiimageModel, asyncModel } from '@/addon/ai_image/api/aiimagemodel'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/ai_image/views/aiimagemodel/components/aiimagemodel-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;
const asyncLoading = ref(false)
const asyncModelFn = () => {
    asyncLoading.value = true
    asyncModel().then(() => {
        asyncLoading.value = false
        loadAiimageModelList()
    })
}
let aiimageModelTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "name": "",
        "status": "",
        "is_vip": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取智能体列表
 */
const loadAiimageModelList = (page: number = 1) => {
    aiimageModelTable.loading = true
    aiimageModelTable.page = page

    getAiimageModelList({
        page: aiimageModelTable.page,
        limit: aiimageModelTable.limit,
        ...aiimageModelTable.searchParam
    }).then(res => {
        aiimageModelTable.loading = false
        aiimageModelTable.data = res.data.data
        aiimageModelTable.total = res.data.total
    }).catch(() => {
        aiimageModelTable.loading = false
    })
}
loadAiimageModelList()

const editAiimageModelDialog: Record<string, any> | null = ref(null)

/**
 * 添加智能体
 */
const addEvent = () => {
    editAiimageModelDialog.value.setFormData()
    editAiimageModelDialog.value.showDialog = true
}

/**
 * 编辑智能体
 * @param data
 */
const editEvent = (data: any) => {
    editAiimageModelDialog.value.setFormData(data)
    editAiimageModelDialog.value.showDialog = true
}

/**
 * 删除智能体
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimageModelDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimageModel(id).then(() => {
            loadAiimageModelList()
        }).catch(() => {
        })
    })
}



const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadAiimageModelList()
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
