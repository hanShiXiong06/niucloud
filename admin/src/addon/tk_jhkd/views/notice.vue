<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-[20px]">{{ pageName }}</span>
                <el-button type="primary" class="w-[100px]" @click="addEvent">
                    {{ t('addNotice') }}
                </el-button>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="tkjhkdNoticeTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('title')" prop="title">
                        <el-input v-model="tkjhkdNoticeTable.searchParam.title" :placeholder="t('titlePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select class="" v-model="tkjhkdNoticeTable.searchParam.status" clearable
                            :placeholder="t('statusPlaceholder')">
                            <el-option label="全部" value=""></el-option>

                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadTkjhkdNoticeList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="tkjhkdNoticeTable.data" size="large" v-loading="tkjhkdNoticeTable.loading">
                    <template #empty>
                        <span>{{ !tkjhkdNoticeTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="title" :label="t('title')" min-width="120" />
                    <el-table-column :label="t('image')" width="100" align="left">
                        <template #default="{ row }">
                            <el-avatar v-if="row.image" :src="img(row.image)" />
                            <el-avatar v-else icon="UserFilled" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" :label="t('status')" min-width="120" />
                    <el-table-column prop="addon" :label="t('addon')" min-width="120" />

                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="danger" link @click="deleteEvent(row.id)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="tkjhkdNoticeTable.page" v-model:page-size="tkjhkdNoticeTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="tkjhkdNoticeTable.total"
                        @size-change="loadTkjhkdNoticeList()" @current-change="loadTkjhkdNoticeList" />
                </div>
            </div>


        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, watch } from 'vue'
import { t } from '@/lang'
import { getTkjhkdNoticeList, deleteTkjhkdNotice } from '@/addon/tk_jhkd/api/tkjhkd'
import { img } from '@/utils/common'
import { ElMessageBox } from 'element-plus'
import { useRouter } from 'vue-router'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let tkjhkdNoticeTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "title": "",
        "status": "",
        "create_time": "",
        "update_time": "",
        "addon": ""
    }
})

const searchFormRef = ref<FormInstance>()

/**
 * 获取聚合快递通知列表列表
 */
const loadTkjhkdNoticeList = (page: number = 1) => {
    tkjhkdNoticeTable.loading = true
    tkjhkdNoticeTable.page = page

    getTkjhkdNoticeList({
        page: tkjhkdNoticeTable.page,
        limit: tkjhkdNoticeTable.limit,
        ...tkjhkdNoticeTable.searchParam
    }).then(res => {
        tkjhkdNoticeTable.loading = false
        tkjhkdNoticeTable.data = res.data.data
        tkjhkdNoticeTable.total = res.data.total
    }).catch(() => {
        tkjhkdNoticeTable.loading = false
    })
}
loadTkjhkdNoticeList()

const router = useRouter()

/**
 * 添加聚合快递通知列表
 */
const addEvent = () => {
    router.push('/tk_jhkd/noticeedit')
}

/**
 * 编辑聚合快递通知列表
 * @param data
 */
const editEvent = (data: any) => {
    router.push('/tk_jhkd/noticeedit?id=' + data.id)
}

/**
 * 删除聚合快递通知列表
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('tkjhkdNoticeDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteTkjhkdNotice(id).then(() => {
            loadTkjhkdNoticeList()
        }).catch(() => {
        })
    })
}


const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadTkjhkdNoticeList()
}
</script>

<style lang="scss" scoped></style>