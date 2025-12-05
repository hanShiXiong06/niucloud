<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>

            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimageOrderTable.searchParam" ref="searchFormRef">

                    <el-form-item label="会员" prop="member_id">
                        <el-select class="input-width" v-model="aiimageOrderTable.searchParam.member_id" clearable
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


                    <el-form-item :label="t('packageId')" prop="package_id">
                        <el-select class="w-[280px]" v-model="aiimageOrderTable.searchParam.package_id" clearable
                            :placeholder="t('packageIdPlaceholder')">
                            <el-option v-for="(item, index) in packageIdList" :key="index" :label="item['name']"
                                :value="item['id']" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('orderId')" prop="order_id">
                        <el-input v-model="aiimageOrderTable.searchParam.order_id"
                            :placeholder="t('orderIdPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="aiimageOrderTable.searchParam.name" :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-input v-model="aiimageOrderTable.searchParam.status"
                            :placeholder="t('statusPlaceholder')" />
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadAiimageOrderList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="aiimageOrderTable.data" size="large" v-loading="aiimageOrderTable.loading">
                    <template #empty>
                        <span>{{ !aiimageOrderTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="member_id_name" :label="t('memberId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="package_id_name" :label="t('packageId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="order_id" :label="t('orderId')" min-width="120"
                        :show-overflow-tooltip="true" />

                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true" />
                    <el-table-column prop="order_money" :label="t('订单价格')" min-width="120"
                        :show-overflow-tooltip="true" />
                    <el-table-column prop="status_name" :label="t('status')" min-width="120"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.status == 1" type="info">{{ t('待支付') }}</el-tag>
                            <el-tag v-else-if="row.status == 10" type="success">{{ t('已完成') }}</el-tag>
                            <el-tag v-else-if="row.status == -1" type="warning">{{ t('已关闭') }}</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column prop="create_time" :label="t('创建时间')" min-width="120"
                        :show-overflow-tooltip="true" />
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">

                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="aiimageOrderTable.page"
                        v-model:page-size="aiimageOrderTable.limit" layout="total, sizes, prev, pager, next, jumper"
                        :total="aiimageOrderTable.total" @size-change="loadAiimageOrderList()"
                        @current-change="loadAiimageOrderList" />
                </div>
            </div>

            <edit ref="editAiimageOrderDialog" @complete="loadAiimageOrderList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimageOrderList, deleteAiimageOrder, getWithMemberList, getWithAiimagePackageList } from '@/addon/ai_image/api/aiimageorder'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/ai_image/views/aiimageorder/components/aiimageorder-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let aiimageOrderTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "member_id": "",
        "package_id": "",
        "order_id": "",
        "name": "",
        "image": "",
        "status": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

// 字典数据


/**
 * 获取订单列列表
 */
const loadAiimageOrderList = (page: number = 1) => {
    aiimageOrderTable.loading = true
    aiimageOrderTable.page = page

    getAiimageOrderList({
        page: aiimageOrderTable.page,
        limit: aiimageOrderTable.limit,
        ...aiimageOrderTable.searchParam
    }).then(res => {
        aiimageOrderTable.loading = false
        aiimageOrderTable.data = res.data.data
        aiimageOrderTable.total = res.data.total
    }).catch(() => {
        aiimageOrderTable.loading = false
    })
}
loadAiimageOrderList()

const editAiimageOrderDialog: Record<string, any> | null = ref(null)

/**
 * 添加订单列
 */
const addEvent = () => {
    editAiimageOrderDialog.value.setFormData()
    editAiimageOrderDialog.value.showDialog = true
}

/**
 * 编辑订单列
 * @param data
 */
const editEvent = (data: any) => {
    editAiimageOrderDialog.value.setFormData(data)
    editAiimageOrderDialog.value.showDialog = true
}

/**
 * 删除订单列
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimageOrderDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimageOrder(id).then(() => {
            loadAiimageOrderList()
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
const packageIdList = ref([])
const setPackageIdList = async () => {
    packageIdList.value = await (await getWithAiimagePackageList({})).data
}
setPackageIdList()

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadAiimageOrderList()
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
