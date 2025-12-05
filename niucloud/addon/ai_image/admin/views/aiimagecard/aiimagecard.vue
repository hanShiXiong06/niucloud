<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('生成卡密') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="aiimageCardTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('cardNum')" prop="card_num">
                        <el-input v-model="aiimageCardTable.searchParam.card_num"
                            :placeholder="t('cardNumPlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('isUse')" prop="is_use">
                        <el-select v-model="aiimageCardTable.searchParam.is_use" clearable
                            :placeholder="t('isUsePlaceholder')">
                            <el-option label="未使用" value="0"></el-option>
                            <el-option label="已使用" value="1"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('isExport')" prop="is_export">
                        <el-select v-model="aiimageCardTable.searchParam.is_export" clearable>
                            <el-option label="未分配" value="0"></el-option>
                            <el-option label="已分配" value="1"></el-option>
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('pid')" prop="pid">
                        <el-select class="input-width" v-model="aiimageCardTable.searchParam.pid" clearable
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

                    <el-form-item>
                        <el-button type="primary" @click="loadAiimageCardList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                        <el-button type="primary" @click="exportEvent">{{ t('export') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <el-button @click="deleteSelectEvent()">删除选中</el-button>

            <div class="mt-[10px]">
                <el-table @selection-change="handleSelectionChange" :data="aiimageCardTable.data" size="large"
                    v-loading="aiimageCardTable.loading">
                    <template #empty>
                        <span>{{ !aiimageCardTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column type="selection" width="85" />
                    <el-table-column prop="card_num" :label="t('卡密')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="point" :label="t('point')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="is_use" :label="t('isUse')" min-width="120" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_use == 0" type="success">未使用</el-tag>
                            <el-tag v-else type="danger">已使用</el-tag>
                        </template>
                    </el-table-column>

                    <!-- <el-table-column prop="use_time" :label="t('useTime')" min-width="120"
                        :show-overflow-tooltip="true" /> -->

                    <el-table-column prop="is_export" :label="t('isExport')" min-width="120"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag v-if="row.is_export == 0" type="success">未分配</el-tag>
                            <el-tag v-else type="danger">已分配</el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column prop="pid_name" :label="t('pid')" min-width="120" :show-overflow-tooltip="true" />
                    <el-table-column prop="expire_time" :label="t('到期时间')" min-width="120"
                        :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <span v-if="row.expire_time != 0">{{ row.expire_time }}</span>
                            <el-tag v-if="row.expire_time == 0">永久</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="create_time" :label="t('生成时间')" min-width="120"
                        :show-overflow-tooltip="true" />
                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="aiimageCardTable.page"
                        v-model:page-size="aiimageCardTable.limit" layout="total, sizes, prev, pager, next, jumper"
                        :total="aiimageCardTable.total" @size-change="loadAiimageCardList()"
                        @current-change="loadAiimageCardList" />
                </div>
            </div>
            <export-sure ref="exportSureDialog" :show="flag" type="ai_image_card"
                :searchParam="{ ...aiimageCardTable, data: undefined }" @close="handleClose" />
            <edit ref="editAiimageCardDialog" @complete="loadAiimageCardList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { useDictionary } from '@/app/api/dict'
import { getAiimageCardList, deleteAiimageCard, getWithMemberList, delselect } from '@/addon/ai_image/api/aiimagecard'
import { img } from '@/utils/common'
import { ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/ai_image/views/aiimagecard/components/aiimagecard-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;
/**
 * 导出
 */
const exportSureDialog = ref(null)
const flag = ref(false)
const handleClose = (val) => {
    flag.value = val
}
const exportEvent = () => {
    flag.value = true
}
let aiimageCardTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "card_num": "",
        "is_use": "",
        "is_export": "",
        "pid": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([]);
const handleSelectionChange = (val) => {
    selectData.value = val.map((item) => item.id);
};
const deleteSelectEvent = async () => {
    if (selectData.value.length > 0) {
        delselect(selectData.value).then((res) => {
            loadAiimageCardList();
        });
    } else {
        ElMessageBox.confirm("请先选择要删除的数据", t("warning"), {
            confirmButtonText: t("confirm"),
            cancelButtonText: t("cancel"),
            type: "warning",
        });
    }
};

// 字典数据


/**
 * 获取卡密兑换列表
 */
const loadAiimageCardList = (page: number = 1) => {
    aiimageCardTable.loading = true
    aiimageCardTable.page = page

    getAiimageCardList({
        page: aiimageCardTable.page,
        limit: aiimageCardTable.limit,
        ...aiimageCardTable.searchParam
    }).then(res => {
        aiimageCardTable.loading = false
        aiimageCardTable.data = res.data.data
        aiimageCardTable.total = res.data.total
    }).catch(() => {
        aiimageCardTable.loading = false
    })
}
loadAiimageCardList()

const editAiimageCardDialog: Record<string, any> | null = ref(null)

/**
 * 添加卡密兑换
 */
const addEvent = () => {
    editAiimageCardDialog.value.setFormData()
    editAiimageCardDialog.value.showDialog = true
}

/**
 * 编辑卡密兑换
 * @param data
 */
const editEvent = (data: any) => {
    editAiimageCardDialog.value.setFormData(data)
    editAiimageCardDialog.value.showDialog = true
}

/**
 * 删除卡密兑换
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('aiimageCardDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteAiimageCard(id).then(() => {
            loadAiimageCardList()
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
    loadAiimageCardList()
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
