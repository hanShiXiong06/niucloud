<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addMemory') }}
                </el-button>
            </div>

            <el-tabs model-value="/phone_shop/goods/memory" @tab-change="handleClick">
                <el-tab-pane :label="t('tabMemoryGroup')" name="/phone_shop/goods/memory_group" />
                <el-tab-pane :label="t('tabMemory')" name="/phone_shop/goods/memory" />
            </el-tabs>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="memoryTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('specName')" prop="spec_name">
                        <el-input v-model.trim="memoryTable.searchParam.spec_name"
                            :placeholder="t('specNamePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('groupName')" prop="group_id">
                        <el-select v-model="memoryTable.searchParam.group_id" :placeholder="t('groupNamePlaceholder')"
                            clearable>
                            <el-option v-for="item in groupList" :key="item.group_id" :label="item.group_name"
                                :value="item.group_id" />
                        </el-select>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadMemoryList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="memoryTable.data" size="large" v-loading="memoryTable.loading"
                    @sort-change="sortChange">
                    <template #empty>
                        <span>{{ !memoryTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="spec_name" :label="t('specName')" min-width="120" />

                    <el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <el-input v-model.trim="row.sort" :disabled="userStore().siteInfo.site_id !== row.site_id"
                                class="!w-[100px]" maxlength="8" @blur="sortInputListener(row.sort, row)" />
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" v-if="userStore().siteInfo.site_id == row.site_id" link
                                @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" v-if="userStore().siteInfo.site_id == row.site_id" link
                                @click="deleteEvent(row.spec_id)">{{ t('delete')
                                }}</el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="memoryTable.page" v-model:page-size="memoryTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="memoryTable.total"
                        @size-change="loadMemoryList()" @current-change="loadMemoryList" />
                </div>
            </div>

            <memory-edit ref="editMemoryDialog" @complete="loadMemoryList" :groupList="groupList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { getMemoryList, deleteMemory, getMemoryGroupList } from '@/addon/phone_shop/api/goods'
import { ElMessageBox, FormInstance, ElMessage } from 'element-plus'
import MemoryEdit from '@/addon/phone_shop/views/goods/components/memory-edit.vue'
import { useRoute, useRouter } from 'vue-router'
import { debounce } from '@/utils/common'
import userStore from '@/stores/modules/user'

const route = useRoute()
const router = useRouter()
const pageName = route.meta.title

const handleClick = (path: string) => {
    router.push({ path })
}

const memoryTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        spec_name: '',
        group_id: '',
        order: '',
        sort: ''
    }
})

const groupList: any = reactive([]);

const searchFormRef = ref<FormInstance>()

// 监听排序
const sortChange = (event: any) => {
    let sort = ''
    if (event.order == 'ascending') {
        sort = 'asc'
    } else if (event.order == 'descending') {
        sort = 'desc'
    }
    if (sort) {
        memoryTable.searchParam.order = event.prop
        memoryTable.searchParam.sort = sort
    }
    loadMemoryList()
}

/**
 * 获取内存规格列表
 */
const loadMemoryList = (page: number = 1) => {
    memoryTable.loading = true
    memoryTable.page = page

    getMemoryList({
        page: memoryTable.page,
        limit: memoryTable.limit,
        ...memoryTable.searchParam
    }).then(res => {
        memoryTable.loading = false
        memoryTable.data = res.data.data
        memoryTable.total = res.data.total
    }).catch(() => {
        memoryTable.loading = false
    })
}

const initData = async () => {
    await getMemoryGroupList({}).then((res: any) => {
        console.log(res)
        const data = res.data.data
        if (data) {
            groupList.push(...data)
        }
    })
    await loadMemoryList()
}

initData();

const editMemoryDialog: Record<string, any> | null = ref(null)

/**
 * 添加内存规格
 */
const addEvent = () => {
    editMemoryDialog.value.setFormData()
    editMemoryDialog.value.showDialog = true
}

/**
 * 编辑内存规格
 * @param data
 */
const editEvent = (data: any) => {
    editMemoryDialog.value.setFormData(data)
    editMemoryDialog.value.showDialog = true
}

/**
 * 删除内存规格
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('memoryDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        deleteMemory(id).then(() => {
            loadMemoryList()
        }).catch(() => {
        })
    })
}

// 修改排序号
const sortInputListener = debounce((sort, row) => {
    if (isNaN(sort) || !/^\d{0,8}$/.test(sort)) {
        ElMessage({
            type: 'warning',
            message: `${t('sortTips')}`
        })
        return
    }
    if (sort > 99999999) {
        row.sort = 99999999
    }
})

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadMemoryList()
}
</script>

<style lang="scss" scoped></style>
