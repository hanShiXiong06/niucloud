<template>
    <div class="main-container">
  
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-[5px]">
                <span class="text-page-title">{{ pageName }}</span>
                <el-button type="primary" @click="addEvent">
                    {{ t('addCategory') }}
                </el-button>
            </div>
            <el-tabs class="demo-tabs" model-value="/phone_shop_price/goods/category" @tab-change="handleClick">
                <el-tab-pane :label="t('tabGoodsCategory')" name="/phone_shop_price/goods/category" />

            </el-tabs>
            <div class="mt-[10px]">
                <el-table :data="categoryTable.data" ref="tableRef" size="large" v-loading="categoryTable.loading"
                    row-key="category_id" :tree-props="{ hasChildren: 'hasChildren', children: 'child_list' }">
                    <template #empty>
                        <span>{{ !categoryTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column :label="t('categoryName')" min-width="120">
                        <template #default="{ row }">
                            <i class="order-0 iconfont icontuodong vues-rank mr-[8px]"></i>
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
                                                src="@/addon/phone_shop_price/assets/category_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="is_show" :label="t('是否显示')">
                        <template #default="{ row }">
                            <!-- <el-tag class="cursor-pointer" :type="row.is_show != 2 ? 'success' : 'danger'"
                                @click="showClick(row)">{{ row.is_show != 2 ? '是' : '否' }}</el-tag> -->
                            <!-- 使用 el-switch 完成 -->

                            <el-switch v-model="row.is_show" :active-value="1" :inactive-value="0"
                                @change="switchShow(row)"></el-switch>

                        </template>
                    </el-table-column>
                    <el-table-column prop="need_vip" :label="t('是否需要VIP')">
                        <template #default="{ row }">


                            <el-switch v-model="row.need_vip" :active-value="1" :inactive-value="0"
                                @change="switchVip(row)"></el-switch>

                        </template>
                    </el-table-column>

                    <el-table-column :label="t('报价')" width="170" align="left">
                        <template #default="{ row }">
                            <div class="h-[30px]">
                                <el-image class="w-[30px] h-[30px] " @click="previewImage(row)" :src="img(row.images)"
                                    fit="contain">
                                    <template #error>
                                        <div class="image-slot">
                                            <img class="w-[30px] h-[30px]"
                                                src="@/addon/phone_shop_price/assets/category_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                            </div>
                        </template>
                    </el-table-column>

                    <!-- <el-table-column prop="sort" :label="t('sort')" width="120" /> -->
                    <el-table-column :label="t('operation')" fixed="right" align="right" width="200">
                        <template #default="{ row }">

                            <el-button type="primary" v-if="userStore().siteInfo.site_id == row.site_id" link
                                @click="editEvent(row)">{{ t('edit') }}</el-button>


                            <el-button type="primary" v-if="userStore().siteInfo.site_id == row.site_id" link
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

    </div>
</template>

<script lang="ts" setup>
import { reactive, ref, onMounted, nextTick } from 'vue'
import { t } from '@/lang'
// import { updateCategory, editCategory } from '@/addon/phone_shop_price/api/goods'

import { getCategoryTree, deleteRecycleCategory, editRecycleCategory } from '@/addon/phone_shop_price/api/recycle_category'

import { img } from '@/utils/common'
import { ElMessageBox } from 'element-plus'
import categoryEdit from '@/addon/phone_shop_price/views/recycle_category/components/recycle-category-edit.vue'

import { useRoute, useRouter } from 'vue-router'
import Sortable from 'sortablejs'
import { useTemplateRefsList } from '@vueuse/core'
import { cloneDeep } from 'lodash-es'
import userStore from '@/stores/modules/user'


const route = useRoute()
const pageName = route.meta.title
const tableRef = useTemplateRefsList<HTMLElement>()

const images = ref('');

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

    getCategoryTree().then(res => {
        categoryTable.loading = false
        categoryTable.data = res.data
    }).catch(() => {
        categoryTable.loading = false
    })
}
const updateCategoryFn = (params: any) => {
    updateCategory(params).then(res => { })
}
const imageViewer = reactive({
    show: false,
    index: 0
})
const previewImageList = ref([])
const previewImage = (row: any) => {


    imageViewer.show = true

    previewImageList.value = [row.images]
}

const switchShow = (row: any) => {
    const obj = cloneDeep(row)
    delete obj.child_list
    editRecycleCategory(obj)
}

const switchVip = (row: any) => {


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
</style>
