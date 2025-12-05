<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>

            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimageCreateTable.searchParam" ref="searchFormRef">



                    <el-form-item label="会员" prop="member_id">
                        <el-select class="input-width" v-model="aiimageCreateTable.searchParam.member_id" clearable
                            :placeholder="t('memberIdPlaceholder')">
                            <div class="mt-2 mb-2 ml-4">
                                <el-input @change="change" v-model="keyword" style="width: 200px"
                                    placeholder="搜索会员支持昵称/会员名">
                                    <template #append>搜索 </template></el-input>
                            </div>
                            <el-option label="请选择" value=""></el-option>
                            <el-option v-for="(item, index) in memberIdList" :key="index" :label="item['nickname']"
                                :value="item['member_id']" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('modelId')" prop="model_id">
                        <el-select class="w-[280px]" v-model="aiimageCreateTable.searchParam.model_id" clearable
                            :placeholder="t('modelIdPlaceholder')">
                            <el-option v-for="(item, index) in modelIdList" :key="index" :label="item['name']"
                                :value="item['id']" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="aiimageCreateTable.searchParam.status" clearable>
                            <el-option label="请选择" value=""></el-option>
                            <el-option label="进行中" value="0"></el-option>
                            <el-option label="成功" value="1"></el-option>
                            <el-option label="失败" value="2"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('createTime')" prop="create_time">
                        <el-date-picker v-model="aiimageCreateTable.searchParam.create_time" type="datetimerange"
                            format="YYYY-MM-DD hh:mm:ss" :start-placeholder="t('startDate')"
                            :end-placeholder="t('endDate')" />
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadAiimageCreateList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="aiimageCreateTable.data" size="large" v-loading="aiimageCreateTable.loading">
                    <template #empty>
                        <span>{{ !aiimageCreateTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="member_id_name" :label="t('memberId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="model_id_name" :label="t('modelId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="prompt" :label="t('prompt')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="image_urls" :label="t('imageUrls')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="aspect_ratio" :label="t('aspectRatio')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="images" :label="t('images')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="status" :label="t('status')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.status == 0" type="success">{{ t('进行中') }}</el-tag>
                            <el-tag v-if="row.status == 1" type="success">{{ t('成功') }}</el-tag>
                            <el-tag v-if="row.status == 2" type="danger">{{ t('失败') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="point" :label="t('point')" min-width="120" :show-overflow-tooltip="true" />



                    <el-table-column prop="msg" :label="t('msg')" min-width="120" :show-overflow-tooltip="true" />


                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="aiimageCreateTable.page"
                        v-model:page-size="aiimageCreateTable.limit" layout="total, sizes, prev, pager, next, jumper"
                        :total="aiimageCreateTable.total" @size-change="loadAiimageCreateList()"
                        @current-change="loadAiimageCreateList" />
                </div>
            </div>

            <edit ref="editAiimageCreateDialog" @complete="loadAiimageCreateList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimageCreateList, deleteAiimageCreate, getWithMemberList, getWithAiimageModelList } from '@/addon/ai_image/api/aiimagecreate'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/ai_image/views/aiimagecreate/components/aiimagecreate-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let aiimageCreateTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "member_id": "",
        "model_id": "",
        "status": "",
        "create_time": []
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取作品列列表
 */
const loadAiimageCreateList = (page: number = 1) => {
    aiimageCreateTable.loading = true
    aiimageCreateTable.page = page

    getAiimageCreateList({
        page: aiimageCreateTable.page,
        limit: aiimageCreateTable.limit,
        ...aiimageCreateTable.searchParam
    }).then(res => {
        aiimageCreateTable.loading = false
        aiimageCreateTable.data = res.data.data
        aiimageCreateTable.total = res.data.total
    }).catch(() => {
        aiimageCreateTable.loading = false
    })
}
loadAiimageCreateList()

const editAiimageCreateDialog: Record<string, any> | null = ref(null)

/**
 * 添加作品列
 */
const addEvent = () => {
    editAiimageCreateDialog.value.setFormData()
    editAiimageCreateDialog.value.showDialog = true
}

/**
 * 编辑作品列
 * @param data
 */
const editEvent = (data: any) => {
    editAiimageCreateDialog.value.setFormData(data)
    editAiimageCreateDialog.value.showDialog = true
}

/**
 * 删除作品列
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimageCreateDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimageCreate(id).then(() => {
            loadAiimageCreateList()
        }).catch(() => {
        })
    })
}


const change = () => {
    setMemberIdList();
};
const keyword = ref();
const memberIdList = ref([]);
const setMemberIdList = async () => {
    memberIdList.value = await (
        await getWithMemberList({ keyword: keyword.value })
    ).data.data;
};
setMemberIdList();
const modelIdList = ref([])
const setModelIdList = async () => {
    modelIdList.value = await (await getWithAiimageModelList({})).data
}
setModelIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadAiimageCreateList()
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
