<template>
    <PremiumTheme class="recycle-category">
        <!-- 分类列表 -->
        <el-card shadow="never">
            <div class="flex justify-between items-center mb-[5px]">
                <span class="text-page-title">{{ pageName }}</span>
                <div class="flex items-center">
                    <el-date-picker v-model="historyDate" type="date" :placeholder="t('查看历史日期（默认最新）')"
                        value-format="YYYY-MM-DD" :disabled-date="disabledFutureDate" :clearable="true"
                        class="mr-2" style="width: 220px" @change="loadCategoryList" />
                    <el-tag v-if="isHistoryMode" type="warning" effect="plain" class="mr-2">
                        {{ t('历史查看模式 · 只读') }}
                    </el-tag>
                    <el-button class="ml-2 mr-2" type="primary" @click="addEvent" :disabled="isHistoryMode">
                        {{ t('添加报价单') }}
                    </el-button>
                </div>


            </div>
            <el-tabs class="demo-tabs" model-value="/hsx_recycle/goods/category" @tab-change="handleClick">
                <el-tab-pane :label="t('报价分类')" name="/hsx_recycle/goods/category" />

            </el-tabs>
            <div class="mt-[10px]">
                <el-table :data="categoryTable.data" ref="tableRef" size="large" v-loading="categoryTable.loading"
                    row-key="category_id" :tree-props="{ hasChildren: 'hasChildren', children: 'child_list' }">
                    <template #empty>
                        <EmptyState
                            v-if="!categoryTable.loading"
                            icon="folder"
                            title="暂无回收分类"
                            description="添加分类与报价配置后，C 端用户才能选择品类下单。"
                        />
                    </template>
                    <el-table-column :label="t('categoryName')" min-width="120">
                        <template #default="{ row }">
                            <i v-if="!isHistoryMode" class="order-0 iconfont icontuodong vues-rank mr-[8px]"></i>
                            <span class="order-2">{{ row.category_name }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('image')" width="170" align="left">
                        <template #default="{ row }">
                            <div class="h-[30px]">
                                <el-image class="w-[30px] h-[30px] " :src="img(row.image)" fit="contain">
                                    <template #error>
                                        <div class="image-slot">
                                            <img class="w-[30px] h-[30px]"
                                                src="@/addon/hsx_recycle/assets/category_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="is_show" :label="t('是否显示')">
                        <template #default="{ row }">

                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0"
                                :disabled="isHistoryMode" @change="switchShow(row)"></el-switch>

                        </template>
                    </el-table-column>

                    <el-table-column prop="need_vip" :label="t('热门')">
                        <template #default="{ row }">

                            <el-switch v-model="row.need_vip" :active-value="1" :inactive-value="0"
                                :disabled="isHistoryMode" @change="switchShow(row)"></el-switch>

                        </template>
                    </el-table-column>


                    <el-table-column :label="t('报价')" width="170" align="left">
                        <template #default="{ row }">
                            <div class="h-[30px]" v-if="row.images">
                                <el-image class="w-[30px] h-[30px] " @click="previewImage(row)" :src="img(row.images)"
                                    fit="contain">
                                    <template #error>
                                        <div class="image-slot">
                                            <img class="w-[30px] h-[30px]"
                                                src="@/addon/hsx_recycle/assets/category_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                            </div>
                            <span v-else class="text-gray-400 text-xs">{{ t('暂无') }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column :label="t('operation')" fixed="right" align="right" width="260">
                        <template #default="{ row }">

                            <el-button type="primary" link @click="openHistoryDrawer(row)">
                                {{ t('历史报价单') }}
                            </el-button>

                            <el-button type="primary" link
                                :disabled="isHistoryMode"
                                @click="editEvent(row)">{{ t('edit') }}</el-button>


                            <el-button type="primary" link
                                :disabled="isHistoryMode"
                                @click="deleteEvent(row)">{{ t('delete') }}</el-button>
                        </template>
                    </el-table-column>

                </el-table>
            </div>

            <category-edit ref="editCategoryDialog" @complete="loadCategoryList" />
        </el-card>

        <!-- 商品分类推广弹出框 -->
        <goods-category-spread-popup ref="goodsCategorySpreadPopupRef" />

        <el-image-viewer :url-list="previewImageList" v-if="imageViewer.show" @close="imageViewer.show = false"
            :initial-index="imageViewer.index" :zoom-rate="1" />

        <!-- 历史报价单抽屉 -->
        <el-drawer v-model="historyDrawer.show" :title="historyDrawer.title" size="520px" :destroy-on-close="true">
            <div v-loading="historyDrawer.loading">
                <div v-if="!historyDrawer.list.length && !historyDrawer.loading"
                    class="text-center text-gray-400 py-10">
                    {{ t('该分类暂无历史报价单') }}
                </div>
                <el-timeline v-else>
                    <el-timeline-item v-for="item in historyDrawer.list" :key="item.id"
                        :timestamp="(item.create_time)" placement="top">
                        <el-card shadow="hover">
                            <div class="flex items-start gap-3">
                                <el-image v-if="item.images" class="w-[80px] h-[80px] flex-shrink-0 cursor-pointer"
                                    :src="img(item.images)" fit="cover"
                                    @click="previewHistoryImage(item)" />
                                <div class="flex-1 text-sm">
                                    <div class="text-gray-600">
                                        {{ t('操作人') }}：{{ item.operator_name || t('系统') }}
                                    </div>
                                    <div v-if="item.remark" class="text-gray-500 mt-1">
                                        {{ t('备注') }}：{{ item.remark }}
                                    </div>
                                    <div class="text-gray-400 mt-1">
                                        {{ t('浏览量') }}：{{ item.view_count || 0 }}
                                    </div>
                                </div>
                            </div>
                        </el-card>
                    </el-timeline-item>
                </el-timeline>
                <div v-if="historyDrawer.total > historyDrawer.pageSize" class="flex justify-center mt-4">
                    <el-pagination
                        v-model:current-page="historyDrawer.page"
                        :page-size="historyDrawer.pageSize"
                        :total="historyDrawer.total"
                        layout="prev, pager, next"
                        @current-change="loadHistoryPage"
                    />
                </div>
            </div>
        </el-drawer>


    </PremiumTheme>
</template>

<script lang="ts" setup>
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { reactive, ref, onMounted, nextTick, computed } from 'vue'
import { t } from '@/lang'
// import { updateCategory, editCategory } from '@/addon/hsx_recycle/api/goods'

import { getCategoryTree, deleteRecycleCategory, editRecycleCategory, updateRecycleCategory, getQuoteHistoryByCategory } from '@/addon/hsx_recycle/api/recycle_category'

import { img } from '@/utils/common'
import { ElMessageBox } from 'element-plus'
import categoryEdit from '@/addon/hsx_recycle/views/recycle_category/components/recycle-category-edit.vue'
import EmptyState from '@/addon/hsx_recycle/components/empty-state/index.vue'

import { useRoute, useRouter } from 'vue-router'
import Sortable from 'sortablejs'
import { useTemplateRefsList } from '@vueuse/core'
import { cloneDeep } from 'lodash-es'




const route = useRoute()
const pageName = route.meta.title
const tableRef = useTemplateRefsList<HTMLElement>()


// 历史日期：为空表示「最新」；选了日期表示进入历史只读模式
const historyDate = ref<string>('')
const isHistoryMode = computed(() => !!historyDate.value)

const disabledFutureDate = (d: Date) => {
    return d.getTime() > Date.now()
}

const categoryTable = reactive({
    loading: true,
    data: []
})

onMounted(() => {
    nextTick(() => {
        rowDrop()
    })
    loadCategoryList()
})

// 拖拽
const activeRows = ref<any[]>([])
// 拖拽排序
const rowDrop = () => {
    const tbody = tableRef.value.$el.querySelector('.el-table__body-wrapper tbody')
    Sortable.create(tbody, {
        handle: '.vues-rank',
        animation: 300,
        onMove: ({ dragged, related }) => {
            const oldRow = activeRows.value[dragged.rowIndex] // 移动的那个元素
            const newRow = activeRows.value[related.rowIndex] // 新的元素
            if (oldRow.pid !== newRow.pid) { // 移动的元素与新元素父级id不相同
                return false // 不允许跨级拖动
            }
        },
        onStart: () => { // 开始拖拽前把树形结构数据扁平化
            activeRows.value = treeToTile(cloneDeep(categoryTable.data)) // 把树形的结构转为列表再进行拖拽
        },
        onEnd: e => {
            const oldRow = activeRows.value[e.oldIndex] // 移动的那个元素
            const newRow = activeRows.value[e.newIndex] // 新的元素

            if (e.oldIndex === e.newIndex || oldRow.pid !== newRow.pid) return false

            const index = activeRows.value.indexOf(oldRow)

            if (index < 0) return false

            const currRow = activeRows.value.splice(e.oldIndex, 1)[0]
            activeRows.value.splice(e.newIndex, 0, currRow)
            const pid = newRow.pid
            const currentRows = activeRows.value.filter(c => c.pid === pid)?.map((item, index) => {
                if (item.level === 1 && item.category_id === currRow.category_id) {
                    categoryTable.data = categoryTable.data.filter(c => c.category_id !== currRow.category_id)
                    categoryTable.data.splice(index, 0, currRow)
                }
                if (item.level === 2 && item.category_id === currRow.category_id) {
                    const treeIndex = categoryTable.data.findIndex(el => el.category_id === item.pid)
                    const obj = cloneDeep(categoryTable.data[treeIndex].child_list.filter(c => c.category_id !== currRow.category_id))
                    categoryTable.data[treeIndex].child_list = []
                    categoryTable.data[treeIndex].child_list.push(...obj)
                    categoryTable.data[treeIndex].child_list.splice(index, 0, currRow)
                }
                return {
                    category_id: item.category_id, // 当前行的唯一标识
                    sort: 9999 - index
                }
            })
            updateCategoryFn({ category_sort_array: currentRows })
        }
    })
}

/**
  * 将树数据转化为平铺数据
  * @param <Array> treeData当前要转的id
  * @param <String> childKey 子级字段
  * @return <Array> 返回数据
  */
const treeToTile = (treeData: any, childKey = 'child_list') => {
    const arr: Array<any> = []
    const expanded = (data: any) => {
        if (data && data.length > 0) {
            data.filter((d: any) => d).forEach((e: any) => {
                arr.push(e)
                expanded(e[childKey] || [])
            })
        }
    }
    expanded(treeData)
    return arr
}

/**
 * 获取商品分类列表
 */
const loadCategoryList = () => {
    categoryTable.loading = true

    const params: Record<string, any> = {}
    if (historyDate.value) params.date = historyDate.value

    getCategoryTree(params).then(res => {
        categoryTable.loading = false
        categoryTable.data = res.data
    }).catch(() => {
        categoryTable.loading = false
    })
}
const updateCategoryFn = (params: any) => {
    updateRecycleCategory(params).then(res => { })
}
const imageViewer = reactive({
    show: false,
    index: 0
})
const previewImageList = ref([])
const previewImage = (row: any) => {
    imageViewer.show = true
    // 判断 row.images 中是否是完整路径, 如果是 则不管 如果不是则通过 配置的图片路径 添加完中的路径
    previewImageList.value = [getImageUrl(row.images)]
}

const getImageUrl = (url) => {
    // 检查 URL 是否已经包含 http 或 https
    if (url.startsWith('http://') || url.startsWith('https://')) {
        // URL 已经包含协议，直接返回
        return url;
    } else {
        // URL 不包含协议，添加 VITE_IMG_DOMAIN 前缀
        return import.meta.env.VITE_IMG_DOMAIN + url;
    }
};

// 历史报价单抽屉
const historyDrawer = reactive<{
    show: boolean
    title: string
    loading: boolean
    list: any[]
    categoryId: number
    page: number
    pageSize: number
    total: number
}>({
    show: false,
    title: '',
    loading: false,
    list: [],
    categoryId: 0,
    page: 1,
    pageSize: 20,
    total: 0
})

const openHistoryDrawer = (row: any) => {
    historyDrawer.show = true
    historyDrawer.title = `${row.category_name} · ${t('历史报价单')}`
    historyDrawer.categoryId = row.category_id
    historyDrawer.page = 1
    historyDrawer.list = []
    historyDrawer.total = 0
    loadHistoryPage(1)
}

const loadHistoryPage = (page: number) => {
    historyDrawer.page = page
    historyDrawer.loading = true
    getQuoteHistoryByCategory(historyDrawer.categoryId, {
        page: historyDrawer.page,
        limit: historyDrawer.pageSize
    }).then((res: any) => {
        historyDrawer.list = res.data?.data || res.data || []
        historyDrawer.total = res.data?.total || historyDrawer.list.length
        historyDrawer.loading = false
    }).catch(() => {
        historyDrawer.loading = false
    })
}

const previewHistoryImage = (item: any) => {
    if (!item.images) return
    imageViewer.show = true
    previewImageList.value = [getImageUrl(item.images)]
}

const formatTime = (ts: number) => {
    if (!ts) return ''
    const d = new Date(ts * 1000)
    const pad = (n: number) => n.toString().padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

const switchShow = (row: any) => {
    const obj = cloneDeep(row)
    delete obj.child_list
    editRecycleCategory(obj)
}


const editCategoryDialog: Record<string, any> | null = ref(null)

/**
 * 添加商品分类
 */
const addEvent = () => {
    editCategoryDialog.value.setFormData()
    editCategoryDialog.value.showDialog = true
}

/**
 * 编辑商品分类
 * @param data
 */
const editEvent = (data: any) => {
    editCategoryDialog.value.setFormData(data)
    editCategoryDialog.value.showDialog = true
}

/**
 * 删除商品分类
 */
const deleteEvent = (row: any) => {
    ElMessageBox.confirm(!row.child_list || !row.child_list.length ? t('categoryDeleteTips') : t('categoryDeleteTips1'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        deleteRecycleCategory(row.category_id).then(() => {
            loadCategoryList()
        }).catch(() => {
        })
    })
}



const router = useRouter()

const handleClick = (path: string) => {
    router.push({ path })
}



</script>

<style lang="scss" scoped>
:deep(.el-table__row) {
    >.el-table__cell:nth-child(1) {
        .cell {
            display: flex;
            align-items: center;

            .el-table__expand-icon,
            .el-table__placeholder {
                order: 1;
            }

        }

    }
}

upload-image {
    width: 30px;
    height: 30px;
}

.recycle-category {
    .config-header,
    .banner-header {
        display: flex;
        justify-content: space-between;
        align-items: center;

        .title {
            font-size: 16px;
            font-weight: bold;
        }
    }
}
</style>
