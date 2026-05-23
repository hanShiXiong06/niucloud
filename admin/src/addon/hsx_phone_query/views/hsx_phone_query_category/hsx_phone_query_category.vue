<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-lg">{{ pageName }}</span>
                <div class="flex items-center gap-[8px]">
                    <el-button @click="repairItems">修复渠道信息</el-button>
                    <el-button @click="syncItems(0)">同步查询项</el-button>
                    <el-button type="primary" @click="addEvent">
                        {{ t('addHsxPhoneQueryCategory') }}
                    </el-button>
                </div>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="hsxPhoneQueryCategoryTable.searchParam" ref="searchFormRef">

                    <el-form-item label="查询渠道" prop="channel_key">
                        <el-select class="w-[220px]" v-model="hsxPhoneQueryCategoryTable.searchParam.channel_key" clearable
                            placeholder="请选择查询渠道">
                            <el-option label="全部渠道" value=""></el-option>
                            <el-option v-for="item in channelList" :key="item.value" :label="item.name"
                                :value="item.value" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('typeId')" prop="type_id">
                        <el-select class="w-[280px]" v-model="hsxPhoneQueryCategoryTable.searchParam.type_id" clearable
                            :placeholder="t('typeIdPlaceholder')">
                            <el-option label="全部" value=""></el-option>
                            <el-option v-for="(item, index) in type_idList" :key="index" :label="item.name"
                                :value="item.value" />
                        </el-select>
                    </el-form-item>

                    <el-form-item :label="t('name')" prop="name">
                        <el-input v-model="hsxPhoneQueryCategoryTable.searchParam.name"
                            :placeholder="t('namePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('price')" prop="price">
                        <el-input v-model="hsxPhoneQueryCategoryTable.searchParam.price"
                            :placeholder="t('pricePlaceholder')" />
                    </el-form-item>
                    <el-form-item :label="t('isShow')" prop="is_show">
                        <el-select v-model="hsxPhoneQueryCategoryTable.searchParam.is_show" clearable>
                            <el-option label="显示" :value="1" />
                            <el-option label="隐藏" :value="0" />
                        </el-select>
                    </el-form-item>
                    <el-form-item>
                        <el-button type="primary" @click="loadHsxPhoneQueryCategoryList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-table :data="hsxPhoneQueryCategoryTable.data" size="large" row-key="id"
                    v-loading="hsxPhoneQueryCategoryTable.loading" @row-drop="handleRowDrop">
                    <template #empty>
                        <span>{{ !hsxPhoneQueryCategoryTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column type="drag" width="40" />
                    <el-table-column prop="id" :label="t('id')" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column label="查询渠道" min-width="140" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <el-tag effect="plain" :type="row.channel_key === '3023_main' ? 'success' : 'info'">
                                {{ row.channel_name || row.channel_key || '-' }}
                            </el-tag>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('typeId')" min-width="180" align="center" :show-overflow-tooltip="true">
                        <template #default="{ row }">
                            <div v-for="(item, index) in type_idList">
                                <div v-if="item.value == row.type_id">{{ item.name }}</div>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="name" :label="t('name')" min-width="120" :show-overflow-tooltip="true" />
                    
                    <el-table-column prop="service_code" label="服务编码" min-width="160" :show-overflow-tooltip="true" />
                    
                    <el-table-column prop="query_param" label="查询参数" min-width="100" :show-overflow-tooltip="true" />

                    <el-table-column prop="price" :label="t('price')" min-width="120" :show-overflow-tooltip="true" />
                    
                    <el-table-column prop="cost_price" label="成本价" min-width="120" :show-overflow-tooltip="true" />

                    <el-table-column prop="sort" :label="t('sort')" min-width="120">
                        <template #default="{ row }">
                            <el-input-number v-model="row.sort" :min="0" :max="999"
                                @change="(value) => handleSortChange(row.id, value)" />
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('isShow')" min-width="120">
                        <template #default="{ row }">
                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0"
                                @change="(value) => handleShowChange(row.id, value)" />
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" min-width="120">
                        <template #default="{ row }">
                            <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                            <el-button type="primary" link @click="deleteEvent(row.id)">{{ t('delete')
                                }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <el-pagination v-model:current-page="hsxPhoneQueryCategoryTable.page"
                        v-model:page-size="hsxPhoneQueryCategoryTable.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="hsxPhoneQueryCategoryTable.total"
                        @size-change="loadHsxPhoneQueryCategoryList()"
                        @current-change="loadHsxPhoneQueryCategoryList" />
                </div>
            </div>

            <edit ref="editHsxPhoneQueryCategoryDialog" @complete="refreshCurrentPage" />
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref } from 'vue'
import { t } from '@/lang'
import { getHsxPhoneQueryCategoryList, deleteHsxPhoneQueryCategory, modifySort, modifyShow, repairHsxPhoneQueryItems, syncHsxPhoneQueryItems } from '@/addon/hsx_phone_query/api/hsx_phone_query_category'

import { ElMessage, ElMessageBox, FormInstance } from 'element-plus'
import Edit from '@/addon/hsx_phone_query/views/hsx_phone_query_category/components/hsx-phone-query-category-edit.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
const pageName = route.meta.title;

let hsxPhoneQueryCategoryTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [],
    searchParam: {
        "type_id": "",
        "channel_key": "",
        "name": "",
        "price": "",
        "is_show": ""
    }
})

const searchFormRef = ref<FormInstance>()

// 选中数据
const selectData = ref<any[]>([])

const type_idList = ref([
    { name: '苹果查询', value: 1 },
    { name: '安卓查询', value: 2 },
    { name: '其他查询', value: 9 }
])

const channelList = ref([
    { name: '爱查助手', value: 'gkdt_main' },
    { name: '3023Data', value: '3023_main' }
])

/**
 * 获取分类列表
 */
const loadHsxPhoneQueryCategoryList = (page: number = 1) => {
    hsxPhoneQueryCategoryTable.loading = true
    hsxPhoneQueryCategoryTable.page = page


    getHsxPhoneQueryCategoryList({
        page: hsxPhoneQueryCategoryTable.page,
        limit: hsxPhoneQueryCategoryTable.limit,
        ...hsxPhoneQueryCategoryTable.searchParam
    }).then(res => {
        hsxPhoneQueryCategoryTable.loading = false
        hsxPhoneQueryCategoryTable.data = res.data.data
        hsxPhoneQueryCategoryTable.total = res.data.total
    }).catch(() => {
        hsxPhoneQueryCategoryTable.loading = false
    })
}
loadHsxPhoneQueryCategoryList()

const refreshCurrentPage = () => {
    loadHsxPhoneQueryCategoryList(hsxPhoneQueryCategoryTable.page)
}

const editHsxPhoneQueryCategoryDialog: Record<string, any> | null = ref(null)

/**
 * 添加分类
 */
const addEvent = () => {
    editHsxPhoneQueryCategoryDialog.value.setFormData()
    editHsxPhoneQueryCategoryDialog.value.showDialog = true
}

/**
 * 编辑分类
 * @param data
 */
const editEvent = (data: any) => {


    editHsxPhoneQueryCategoryDialog.value.setFormData(data)
    editHsxPhoneQueryCategoryDialog.value.showDialog = true
}

/**
 * 删除分类
 */
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('hsxPhoneQueryCategoryDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        deleteHsxPhoneQueryCategory(id).then(() => {
            refreshCurrentPage()
        }).catch(() => {
        })
    })
}



const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadHsxPhoneQueryCategoryList()
}

// 处理拖拽排序
const handleRowDrop = ({ newIndex, oldIndex }) => {
    const currentRow = hsxPhoneQueryCategoryTable.data[oldIndex]
    const targetRow = hsxPhoneQueryCategoryTable.data[newIndex]

    // 交换排序值
    const tempSort = currentRow.sort
    currentRow.sort = targetRow.sort
    targetRow.sort = tempSort

    Promise.all([
        modifySort(currentRow.id, currentRow.sort),
        modifySort(targetRow.id, targetRow.sort)
    ]).then(() => {
        ElMessage.success('排序已保存')
    }).catch((err) => {
        ElMessage.error(err?.message || err?.msg || '排序保存失败')
    }).finally(() => {
        refreshCurrentPage()
    })
}

// 处理排序变化
const handleSortChange = (id: number, value: number) => {
    modifySort(id, value).then(() => {
        ElMessage.success('排序已保存')
    }).catch((err) => {
        ElMessage.error(err?.message || err?.msg || '排序保存失败')
    }).finally(() => {
        refreshCurrentPage()
    })
}

// 处理显示状态变化
const handleShowChange = (id: number, value: number) => {
    modifyShow(id, value).then(() => {
        ElMessage.success(value ? '查询项目已显示' : '查询项目已隐藏')
    }).catch((err) => {
        ElMessage.error(err?.message || err?.msg || '显示状态保存失败')
    }).finally(() => {
        refreshCurrentPage()
    })
}

const syncItems = (overwrite = 0) => {
    ElMessageBox.confirm('将从 resource/query_items.php 同步缺失查询项目，默认不会覆盖你已手动修改的售价。是否继续？', '同步查询项',
        {
            confirmButtonText: '继续同步',
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        syncHsxPhoneQueryItems(overwrite).then((res) => {
            const data = res.data || {}
            ElMessage.success(`同步完成：新增 ${data.created || 0}，更新 ${data.updated || 0}，跳过 ${data.skipped || 0}`)
            loadHsxPhoneQueryCategoryList()
        })
    }).catch(() => {})
}

const repairItems = () => {
    ElMessageBox.confirm('将根据系统接口目录修复渠道名称、查询参数和基础分类，不会修改售价。是否继续？', '修复渠道信息',
        {
            confirmButtonText: '开始修复',
            cancelButtonText: t('cancel'),
            type: 'warning',
        }
    ).then(() => {
        repairHsxPhoneQueryItems().then((res) => {
            ElMessage.success(`修复完成：${res.data?.fixed || 0} 条`)
            loadHsxPhoneQueryCategoryList()
        })
    }).catch(() => {})
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

/* 拖拽排序样式 */
:deep(.el-table__row.dragging) {
    opacity: 0.5;
    cursor: move;
}

:deep(.el-table__row.drop-over) {
    background-color: var(--el-color-primary-light-9);
}
</style>
