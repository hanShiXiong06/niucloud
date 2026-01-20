<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addKdapiApi') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="kdapiApiTable.searchParam" ref="searchFormRef">

                    <el-form-item label="会员" prop="member_id">
                        <el-select class="input-width" v-model="kdapiApiTable.searchParam.member_id" clearable
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

                    <el-form-item :label="t('status')" prop="status">
                        <el-select class="input-width" v-model="kdapiApiTable.searchParam.status" clearable
                            :placeholder="t('statusPlaceholder')">
                            <el-option label="请选择" value=""></el-option>
                            <el-option v-for="(item, index) in statusList" :key="index" :label="item" :value="index" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadKdapiApiList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="kdapiApiTable.data" size="large" v-loading="kdapiApiTable.loading">
                    <template #empty>
                        <span>{{ !kdapiApiTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="member_id_name" :label="t('memberId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="rate" :label="t('rate')" min-width="120" :show-overflow-tooltip="true" >
                        <template #default="{ row }">
                            <div>{{ row.rate }}%</div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="api_key" :label="t('apiKey')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="api_secret" :label="t('apiSecret')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="status_name" :label="t('status')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="qps" :label="t('qps')" min-width="120" :show-overflow-tooltip="true" />
<!-- 
                    <el-table-column prop="limit" :label="t('limit')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="num" :label="t('num')" min-width="120" :show-overflow-tooltip="true" /> -->

                    <!-- <el-table-column prop="commission" :label="t('commission')" min-width="120"
                        :show-overflow-tooltip="true" /> -->

                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="kdapiApiTable.page" v-model:page-size="kdapiApiTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="kdapiApiTable.total"
                        @size-change="loadKdapiApiList()" @current-change="loadKdapiApiList" />
                </div>
            </div>

            <edit ref="editKdapiApiDialog" @complete="loadKdapiApiList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getKdapiApiList, deleteKdapiApi, getWithMemberList, getStatus } from '@/addon/kd_api/api/kdapi_api'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/kd_api/views/kdapi_api/components/kdapi-api-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;
const statusList = ref()
getStatus().then((res: any) => {
    statusList.value = res.data;
})
let kdapiApiTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "member_id": "",
        "api_key": "",
        "status": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取api对接列表
 */
const loadKdapiApiList = (page: number = 1) => {
    kdapiApiTable.loading = true
    kdapiApiTable.page = page

    getKdapiApiList({
        page: kdapiApiTable.page,
        limit: kdapiApiTable.limit,
        ...kdapiApiTable.searchParam
    }).then(res => {
        kdapiApiTable.loading = false
        kdapiApiTable.data = res.data.data
        kdapiApiTable.total = res.data.total
    }).catch(() => {
        kdapiApiTable.loading = false
    })
}
loadKdapiApiList()

const editKdapiApiDialog: Record<string, any> | null = ref(null)

/**
 * 添加api对接
 */
const addEvent = () => {
    editKdapiApiDialog.value.setFormData()
    editKdapiApiDialog.value.showDialog = true
}

/**
 * 编辑api对接
 * @param data
 */
const editEvent = (data: any) => {
    editKdapiApiDialog.value.setFormData(data)
    editKdapiApiDialog.value.showDialog = true
}

/**
 * 删除api对接
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm('删除数据会停止开放平台的接口，请确认是否删除？', t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteKdapiApi(id).then(() => {
            loadKdapiApiList()
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

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadKdapiApiList()
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
