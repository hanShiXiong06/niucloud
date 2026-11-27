<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{pageName}}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addLabel') }}
                </el-button>
            </div>

            <el-tabs model-value="/phone_shop/goods/label" @tab-change="handleClick">
                <el-tab-pane :label="t('tabGoodsLabel')" name="/phone_shop/goods/label" />
                <el-tab-pane :label="t('tabGoodsLabelGroup')" name="/phone_shop/goods/label/group" />
            </el-tabs>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="labelTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('labelName')" prop="label_name">
                        <el-input v-model.trim="labelTable.searchParam.label_name" :placeholder="t('labelNamePlaceholder')"  />
                    </el-form-item>
                    <el-form-item :label="t('groupName')" prop="group_id">
                        <el-select v-model="labelTable.searchParam.group_id" :placeholder="t('groupNamePlaceholder')" clearable>
                            <el-option v-for="item in groupList" :key="item.group_id" :label="item.group_name" :value="item.group_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('status')" prop="status">
                        <el-select v-model="labelTable.searchParam.status" :placeholder="t('statusAll')" clearable class="!w-[140px]">
                            <el-option :label="t('statusOn')" :value="1" />
                            <el-option :label="t('statusOff')" :value="0" />
                        </el-select>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadLabelList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <div class="label-batch-bar">
                    <span>{{ t('selectedCurrentPage') }} {{ selectedLabelIds.length }} {{ t('labelUnit') }}</span>
                    <el-button size="small" :disabled="!selectedLabelIds.length || labelTable.loading || isRepeat || batchBusy" @click="batchStatusEvent(1)">{{ t('batchStatusOn') }}</el-button>
                    <el-button size="small" :disabled="!selectedLabelIds.length || labelTable.loading || isRepeat || batchBusy" @click="batchStatusEvent(0)">{{ t('batchStatusOff') }}</el-button>
                    <el-button v-if="selectedLabelIds.length" size="small" link :disabled="batchBusy" @click="clearSelection">{{ t('clearSelection') }}</el-button>
                </div>
                <el-alert v-if="batchError" :title="batchError" type="warning" show-icon class="mb-[10px]" @close="batchError = ''" />
                <el-table ref="labelListTableRef" :data="labelTable.data" row-key="label_id" size="large" v-loading="labelTable.loading" @sort-change="sortChange" @selection-change="handleSelectionChange">
                    <el-table-column type="selection" width="48" fixed="left" :selectable="() => !batchBusy && !isRepeat" />
                    <template #empty>
                        <span>{{ !labelTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column prop="label_name" :label="t('label')" min-width="120">
                        <template #default="{ row }">
                            <div v-if="row.style_type == 'diy'" class="inline-block px-[10px] text-[12px] rounded-[4px] box-border whitespace-nowrap h-[28px] leading-[28px]"
                                 :style="{ color : row.color_json.text_color, backgroundColor : row.color_json.bg_color, border : row.color_json.border_color ? '1px solid ' + row.color_json.border_color : 'none' }">
                                <span>{{ row.label_name }}</span>
                            </div>
                            <img v-else-if="row.style_type == 'icon'" class="block h-[28px] rounded-[4px] object-cover" :src="img(row.icon)" />
                        </template>
                    </el-table-column>
                    <el-table-column prop="group_name" :label="t('groupName')" min-width="120">
                        <template #default="{ row }">
                            <span v-if="row.group">{{ row.group.group_name }}</span>
                            <span v-else>--</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="status" :label="t('status')" min-width="80" :show-overflow-tooltip="true" >
                        <template #default="{ row }">
                            <el-tag type="success" v-if="row.status == 1" @click="modifyLabelStatusEvent(row.label_id, 0)" class="cursor-pointer">{{ t('statusOn') }}</el-tag>
                            <el-tag type="info" v-else @click="modifyLabelStatusEvent(row.label_id, 1)" class="cursor-pointer">{{ t('statusOff') }}</el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="memo" :label="t('memo')" min-width="200" />
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <el-input v-model.trim="row.sort" class="!w-[100px]" maxlength="8" @blur="sortInputListener(row.sort, row)" />
                        </template>
                    </el-table-column>

                    <el-table-column prop="create_time" :label="t('createTime')" min-width="100" sortable="custom">
                        <template #default="{ row }">
                            <div>{{ row.create_time }}</div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" min-width="120">
                       <template #default="{ row }">
                           <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                           <el-button type="primary" link @click="deleteEvent(row.label_id)">{{ t('delete') }}</el-button>
                           <el-button type="primary" link @click="copyEvent(row)">{{ t('copyLabel') }}</el-button>
                       </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="labelTable.page" v-model:page-size="labelTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="labelTable.total"
                        @size-change="loadLabelList()" @current-change="loadLabelList" />
                </div>
            </div>

            <label-edit ref="editLabelDialog" @complete="loadLabelList(getTablePageStorage(labelTable.searchParam).page)" :groupList="groupList" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { getLabelPageList, deleteLabel, modifyLabelSort, getLabelGroupList, modifyLabelStatus, copyLabel } from '@/addon/phone_shop/api/goods'
import { ElMessageBox, FormInstance, ElMessage } from 'element-plus'
import LabelEdit from '@/addon/phone_shop/views/goods/components/label-edit.vue'
import { useRoute, useRouter } from 'vue-router'
import { debounce, img, setTablePageStorage, getTablePageStorage } from '@/utils/common'

const route = useRoute()
const router = useRouter()
const pageName = route.meta.title

const handleClick = (path: string) => {
    router.push({ path })
}

const labelTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        label_name: '',
        group_id: '',
        status: '' as '' | 0 | 1,
        order: '',
        sort: ''
    }
})

const groupList: any = reactive([])

const searchFormRef = ref<FormInstance>()
const labelListTableRef = ref<any>()
const selectedLabelIds = ref<number[]>([])
const batchBusy = ref(false)
const batchError = ref('')
let labelRequestId = 0

const handleSelectionChange = (rows: any[]) => {
    selectedLabelIds.value = Array.from(new Set(rows.map(row => Number(row.label_id)).filter(id => Number.isInteger(id) && id > 0)))
}
const clearSelection = () => {
    selectedLabelIds.value = []
    labelListTableRef.value?.clearSelection()
}

// 监听排序
const sortChange = (event: any) => {
    let sort = ''
    if (event.order == 'ascending') {
        sort = 'asc'
    } else if (event.order == 'descending') {
        sort = 'desc'
    }
    if (sort) {
        labelTable.searchParam.order = event.prop
        labelTable.searchParam.sort = sort
    }
    loadLabelList()
}

/**
 * 获取商品标签列表
 */
const loadLabelList = (page: number = 1) => {
    const requestId = ++labelRequestId
    clearSelection()
    labelTable.loading = true
    labelTable.page = page

    getLabelPageList({
        page: labelTable.page,
        limit: labelTable.limit,
        ...labelTable.searchParam
    }).then(res => {
        if (requestId !== labelRequestId) return
        labelTable.loading = false
        labelTable.data = res.data.data
        labelTable.total = res.data.total
        const lastPage = Math.max(1, Math.ceil(labelTable.total / labelTable.limit))
        if (labelTable.page > lastPage) return loadLabelList(lastPage)
        setTablePageStorage(labelTable.page, labelTable.limit, labelTable.searchParam)
    }).catch(() => {
        if (requestId === labelRequestId) labelTable.loading = false
    })
}

const initData = () => {
    getLabelGroupList({}).then((res:any) => {
        const data = res.data
        if (data) {
            groupList.push(...data)
        }
    })
    loadLabelList(getTablePageStorage(labelTable.searchParam).page)
}

initData()

const editLabelDialog: Record<string, any> | null = ref(null)

/**
 * 添加商品标签
 */
const addEvent = () => {
    editLabelDialog.value.setFormData()
    editLabelDialog.value.showDialog = true
}

/**
 * 编辑商品标签
 * @param data
 */
const editEvent = (data: any) => {
    editLabelDialog.value.setFormData(data)
    editLabelDialog.value.showDialog = true
}

/**
 * 删除商品标签
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('labelDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        deleteLabel(id).then(() => {
            loadLabelList(getTablePageStorage(labelTable.searchParam).page)
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
    modifyLabelSort({
        label_id: row.label_id,
        sort
    }).then((res) => {
        loadLabelList(getTablePageStorage(labelTable.searchParam).page)
    })
})

const isRepeat = ref(false)

// 修改状态
const modifyLabelStatusEvent = (label_id: any, status: any) => {
    if (isRepeat.value || batchBusy.value) return
    isRepeat.value = true

    modifyLabelStatus({
        label_id,
        status
    }).then((res) => {
        loadLabelList(getTablePageStorage(labelTable.searchParam).page)
    }).catch(() => {}).finally(() => { isRepeat.value = false })
}

const batchStatusEvent = async (status: 0 | 1) => {
    if (batchBusy.value || isRepeat.value || labelTable.loading || !selectedLabelIds.value.length) return
    const ids = [...selectedLabelIds.value]
    batchBusy.value = true
    batchError.value = ''
    let submitted = false
    try {
        const actionName = t(status === 1 ? 'statusOn' : 'statusOff')
        await ElMessageBox.confirm(t('batchStatusConfirm').replace('{count}', String(ids.length)).replace('{status}', actionName), t('warning'), {
            confirmButtonText: t('confirm'), cancelButtonText: t('cancel'), type: 'warning'
        })
        submitted = true
        const res: any = await modifyLabelStatus({ label_ids: ids, status }, { showSuccessMessage: false })
        if (Number(res.code) !== 1) throw new Error(res.msg || t('batchStatusUnknown'))
        if (!res.data || Number(res.data.requested_count) !== ids.length || Number(res.data.status) !== status
            || !Number.isInteger(Number(res.data.changed_count)) || Number(res.data.changed_count) < 0 || Number(res.data.changed_count) > ids.length) {
            throw new Error(t('batchStatusUnknown'))
        }
        ElMessage.success(t('batchStatusSuccess').replace('{count}', String(res.data.requested_count)).replace('{changed}', String(res.data.changed_count)).replace('{status}', actionName))
        loadLabelList(labelTable.page)
    } catch (error: any) {
        if (submitted) batchError.value = error?.msg || error?.message || t('batchStatusUnknown')
    } finally {
        batchBusy.value = false
    }
}
// 复制商品
const copyEvent = (data: any) => {
    ElMessageBox.confirm(t('labelCopyTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (isRepeat.value) return
        isRepeat.value = true

        copyLabel({
            label_id: data.label_id
        }).then((res: any) => {
            if (res.code == 1) {
                loadLabelList(getTablePageStorage(labelTable.searchParam).page)
            }
            isRepeat.value = false
        }).catch(() => {
            isRepeat.value = false
        })
    })
}
const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadLabelList()
}
</script>

<style scoped>
.label-batch-bar { display: flex; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 12px; }
.label-batch-bar > span { color: var(--el-text-color-secondary); font-size: 13px; margin-right: 8px; }
.label-batch-bar :deep(.el-button + .el-button) { margin-left: 0; }
</style>

<style lang="scss" scoped>
</style>
