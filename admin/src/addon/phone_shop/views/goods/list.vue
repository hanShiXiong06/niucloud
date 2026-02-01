<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">

            <div class="flex justify-between items-center">
                <span class="text-page-title">{{ pageName }}</span>
                <span>
                    <el-button type="primary" @click="addEvent">{{ t('addGoods') }}</el-button>
                 
                </span>
            </div>

            <el-card class="box-card !border-none my-[10px] table-search-wrap" shadow="never">
                <el-form :inline="true" :model="goodsTable.searchParam" ref="searchFormRef">
                    <el-form-item :label="t('商品sn')" prop="goods_name">
                        <el-input v-model="goodsTable.searchParam.sku_no" style="width: 600px"
                            :placeholder="t('请输入商品sn编号,如有多个请以空格隔开,最多支持100个字符')" maxlength="100" clearable />
                    </el-form-item>
                    <el-form-item :label="t('goodsName')" prop="goods_name">
                        <el-input v-model.trim="goodsTable.searchParam.goods_name"
                            :placeholder="t('goodsNamePlaceholder')" maxlength="60" />
                    </el-form-item>
                    <el-form-item :label="t('goodsCategory')" prop="goods_category">
                        <el-cascader v-model="goodsTable.searchParam.goods_category" :options="goodsCategoryOptions"
                            :placeholder="t('goodsCategoryPlaceholder')" clearable
                            :props="{ value: 'value', label: 'label', emitPath: false }" />
                    </el-form-item>
                    <!-- <el-form-item :label="t('goodsType')" prop="goods_type">
                        <el-select v-model="goodsTable.searchParam.goods_type" :placeholder="t('goodsTypePlaceholder')"
                            clearable>
                            <el-option v-for="item in goodsType" :key="item.type" :label="item.name"
                                :value="item.type" />
                        </el-select>
                    </el-form-item> -->

                    <el-form-item :label="t('brand')" prop="brand_id">
                        <el-select v-model="goodsTable.searchParam.brand_id" :placeholder="t('brandPlaceholder')"
                            clearable>
                            <el-option v-for="item in brandOptions" :key="item.brand_id" :label="item.brand_name"
                                :value="item.brand_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('labelIds')" prop="label_ids">
                        <el-select v-model="goodsTable.searchParam.label_ids" :placeholder="t('labelIdsPlaceholder')"
                            clearable>
                            <el-option v-for="item in labelOptions" :key="item.label_id" :label="item.label_name"
                                :value="item.label_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item :label="t('skuPrice')" prop="sku_price">
                        <div class="region-input">
                            <input type="text" :placeholder="t('startPricePlaceholder')" maxlength="10"
                                v-model.trim="goodsTable.searchParam.start_price" @keyup="filterDigit($event)">
                            <span class="separator">-</span>
                            <input type="text" :placeholder="t('endPricePlaceholder')" maxlength="10"
                                v-model.trim="goodsTable.searchParam.end_price" @keyup="filterDigit($event)">
                        </div>
                    </el-form-item>

                    <!--  库龄筛选 -->
                    <el-form-item :label="t('库龄')" prop="inventory_age">
                        <el-select v-model="goodsTable.searchParam.inventory_age" :placeholder="t('请选择库龄')"
                            clearable>
                            <el-option label="0-10天" value="0-10" />
                            <el-option label="11-30天" value="11-30" />
                            <el-option label="30-50天" value="30-50" />
                            <el-option label="50天以上" value="50+" />
                        </el-select>
                    </el-form-item>

                    <el-form-item>
                        <el-button type="primary" @click="loadGoodsList()">{{ t('search') }}</el-button>
                        <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
                    </el-form-item>
                </el-form>
            </el-card>

            <div class="mt-[10px]">
                <el-tabs v-model="goodsTable.searchParam.status" class="goods-tabs" @tab-click="tabHandleClick">
                    <el-tab-pane  v-if="userStore().siteInfo.site_id != '100005'" :label="t('自营')" name="2"></el-tab-pane>
                    <el-tab-pane :label="t('statusOn')" name="1"></el-tab-pane>
                    <el-tab-pane :label="t('statusOff')" name="0"></el-tab-pane>
                    <el-tab-pane :label="t('statusAll')" name=""></el-tab-pane>
                </el-tabs>

                <div class="mb-[10px] flex items-center">
                    <el-checkbox v-model="toggleCheckbox" size="large" class="px-[14px]" @change="toggleChange"
                        :indeterminate="isIndeterminate" />
                    <el-button @click="batchGoodsStatus(1)" size="small" v-if="goodsTable.searchParam.status != '1'">{{
                        t('batchOnGoods') }}</el-button>
                    <el-button @click="batchGoodsStatus(0)" size="small" v-if="goodsTable.searchParam.status != '0'">{{
                        t('batchOffGoods') }}</el-button>
                    <el-button @click="batchDeleteGoods" size="small">{{ t('batchDeleteGoods') }}</el-button>
                    <el-button @click="batchShareGoods" size="small" type="success">批量分享</el-button>
                </div>

                <el-table :data="goodsTable.data" size="large" v-loading="goodsTable.loading" ref="goodsListTableRef"
                    @sort-change="sortChange" @selection-change="handleSelectionChange">
                    <template #empty>
                        <span>{{ !goodsTable.loading ? t('emptyData') : '' }}</span>
                    </template>
                    <el-table-column type="selection" width="55" />

                    <el-table-column prop="goods_id" :label="t('goodsInfo')" min-width="300">
                        <template #default="{ row }">
                            <div class="flex items-center cursor-pointer" @click="previewEvent(row)">
                                <div class="min-w-[70px] h-[70px] flex items-center justify-center">
                                    <el-image v-if="row.goods_cover" class="w-[70px] h-[70px]"
                                        :src="img(row.goods_cover)" fit="contain">
                                        <template #error>
                                            <div class="image-slot">
                                                <img class="w-[70px] h-[70px]"
                                                    src="@/addon/phone_shop/assets/goods_default.png" />
                                            </div>
                                        </template>
                                    </el-image>
                                    <img v-else class="w-[70px] h-[70px]"
                                        src="@/addon/phone_shop/assets/goods_default.png" fit="contain" />
                                </div>
                                <div class="ml-2">
                                    <span :title="row.goods_name" class="multi-hidden">{{ row.goods_name }}</span>
                                    <view class="text-[12px] multi-hidden"> {{ row.sub_title }} </view>
                                    <view>
                                        <el-tag type="success" v-if="row.brand_name" size="small">{{ row.brand_name
                                            }}</el-tag>
                                        <span class="tag text-[12px]" v-if="row.brand_name"> | </span>
                                        <span class="text-primary text-[12px]"> {{ row.goods_type_name }}</span>
                                    </view>

                                </div>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sku_no" :label="t('sn')" min-width="130">
                        <template #default="{ row }">
                            <span :title="row.sku_no">{{ row.goodsSku.sku_no }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column prop="price" :label="t('同行价')" min-width="120" align="right" sortable="custom">
                        <template #default="{ row }">
                            <div class="cursor-pointer price-wrap" @click="editPriceEvent(row)">
                                <span v-if="row.goodsSku.market_price">￥{{ row.goodsSku.market_price
                                    }}</span>
                                <el-icon class="icon-wrap ml-[5px] invisible">
                                    <EditPen />
                                </el-icon>
                            </div>
                        </template>
                    </el-table-column>



                    <el-table-column prop="price" :label="t('skuPrice')" min-width="120" align="right"
                        sortable="custom">
                        <template #default="{ row }">

                            <div class="cursor-pointer price-wrap" @click="editPriceEvent(row)">
                                <span>￥{{ row.goodsSku.price }}</span>
                                <el-icon class="icon-wrap ml-[5px] invisible">
                                    <EditPen />
                                </el-icon>
                            </div>
                        </template>
                    </el-table-column>

                    <el-table-column prop="cost_price" :label="t('成本价')" min-width="120" align="right"
                        sortable="custom" />
                    <el-table-column prop="stock" :label="t('stock')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <div class="cursor-pointer stock-wrap" @click="editStockEvent(row)">
                                <span>{{ row.stock }}</span>
                                <el-icon class="icon-wrap ml-[5px] invisible">
                                    <EditPen />
                                </el-icon>
                            </div>
                        </template>
                    </el-table-column>
                    <!-- <el-table-column prop="sale_num" :label="t('saleNum')" min-width="100" sortable="custom" /> -->
                    <el-table-column prop="status" :label="t('status')" min-width="100">
                        <template #default="{ row }">
                            <div v-if="row.status == 1">{{ t('statusOn') }}</div>
                            <div v-if="row.status == 0">{{ t('statusOff') }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="sort" :label="t('sort')" min-width="120" sortable="custom">
                        <template #default="{ row }">
                            <el-input v-model="row.sort" class="w-[70px]" maxlength="8"
                                @blur="sortInputListener(row.sort, row)" />
                        </template>
                    </el-table-column>

                    <el-table-column prop="site_name" :label="t('来源')" min-width="120" />
                    <el-table-column prop="join_time" v-if="goodsTable.searchParam.status == 1" :label="t('库龄')"
                        min-width="120" />
                    <el-table-column prop="create_time" :label="t('createTime')" min-width="150" sortable="custom">
                        <template #default="{ row }">
                            <div>{{ row.create_time }}</div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="update_time" :label="t('updateTime')" min-width="150" sortable="custom">
                        <template #default="{ row }">
                            <div>{{ row.update_time }}</div>
                        </template>
                    </el-table-column>

                    <el-table-column :label="t('operation')" fixed="right" align="right" min-width="150">
                        <template #default="{ row }">

                                <el-button type="primary" link @click="editEvent(row)">{{ t('edit') }}</el-button>
                                <el-button type="success" link @click="offlineSaleEvent(row)" v-if="row.status == 1 && row.stock > 0">销售</el-button>
                                <el-button type="primary" link @click="spreadEvent(row)">{{ t('spreadGoods')
                                    }}</el-button>

                                <el-button type="primary" v-if="row.status == 1" link @click="statusChange(row, 0)">{{
                                    t('statusActionOff') }}</el-button>
                                <el-button type="primary" v-else link @click="statusChange(row, 1)">{{
                                    t('statusActionOn')
                                }}</el-button>
                                <el-button type="primary" link @click="copyEvent(row)">{{ t('copyGoods') }}</el-button>
                                <el-button type="primary" v-if="row.status != 1" link
                                    @click="deleteEvent(row.goods_id)">{{
                                        t('delete') }}</el-button>

         

                        </template>
                    </el-table-column>

                </el-table>
                <div class="mt-[16px] flex justify-end">
                    <!-- <div class="flex items-center flex-1">
                        <el-checkbox v-model="toggleCheckbox" size="large" class="px-[14px]" @change="toggleChange" :indeterminate="isIndeterminate" />
                        <el-button @click="batchGoodsStatus(1)" size="small">{{ t('batchOnGoods') }}</el-button>
                        <el-button @click="batchGoodsStatus(0)" size="small">{{ t('batchOffGoods') }}</el-button>
                        <el-button @click="batchDeleteGoods" size="small">{{ t('batchDeleteGoods') }}</el-button>
                    </div> -->

                    <el-pagination v-model:current-page="paginationStore.page" v-model:page-size="paginationStore.limit"
                        layout="total, sizes, prev, pager, next, jumper" :total="paginationStore.total"
                        @size-change="handleSizeChange" @current-change="handlePageChange" />
                </div>

            </div>

        </el-card>

        <!-- 商品库存编辑弹出框 -->
        <goods-stock-edit-popup ref="goodsStockEditPopupRef" @load="loadGoodsList" />

        <!-- 商品价格编辑弹出框 -->
        <goods-price-edit-popup ref="goodsPriceEditPopupRef" @load="loadGoodsList" />

        <!-- 商品推广弹出框 -->
        <spread-popup ref="spreadPopupRef" />

        <!-- 会员价弹出框 -->
        <goods-member-price-popup ref="memberPricePopupRef" @load="loadGoodsList" />

        <!-- 线下销售弹出框 -->
        <goods-offline-order-popup ref="offlineSalePopupRef" @success="loadGoodsList" />
    </div>
</template>

<script lang="ts" setup>
import { reactive, ref ,watch } from 'vue'
import { t } from '@/lang'
import { debounce, img, filterDigit } from '@/utils/common'
import { ElMessage, ElMessageBox, ElLoading, FormInstance } from 'element-plus'
import { useRoute, useRouter } from 'vue-router'
import { cloneDeep } from 'lodash-es'
import { useClipboard } from '@vueuse/core'
import goodsMemberPricePopup from '@/addon/phone_shop/views/goods/components/goods-member-price-popup.vue'
import goodsStockEditPopup from '@/addon/phone_shop/views/goods/components/goods-stock-edit-popup.vue'
import goodsPriceEditPopup from '@/addon/phone_shop/views/goods/components/goods-price-edit-popup.vue'
import spreadPopup from '@/components/spread-popup/index.vue'
import goodsOfflineOrderPopup from '@/addon/phone_shop/views/goods/components/goods-offline-order-popup.vue'
import { getGoodsPageList, getCategoryTree, getGoodsType, getBrandList, getLabelList, editGoodsSort, editGoodsStatus, copyGoods, deleteGoods } from '@/addon/phone_shop/api/goods'
import { batchGenerateShortLink } from '@/addon/phone_shop/api/shortlink'
import { getMemberLevelAll } from '@/app/api/member'
import { usePaginationStore } from '@/stores/modules/paginationStore'
import userStore from '@/stores/modules/user'


const router = useRouter()
const route = useRoute()
const pageName = route.meta.title
const repeat = ref(false)
const paginationStore = usePaginationStore();



const goodsTable = reactive({
    page: paginationStore.page,
    limit: paginationStore.limit,
    total: paginationStore.total,
    loading: paginationStore.loading,
    data: [],
    searchParam: {
        goods_name: '',
        goods_category: [],
        goods_type: '',
        brand_id: '',
        label_ids: '',
        start_sale_num: '',
        end_sale_num: '',
        start_price: '',
        end_price: '',
        status: route.query.status || '1',
        order: '',
        sort: '',
        sku_no: '',
        inventory_age: '',
        source: ''  // 商品来源站点筛选
    }
})

const searchFormRef = ref<FormInstance>()

/*hsx
* 页码 及数量 初始化
*
* */
const initPage = () => {
    paginationStore.page = 1
    paginationStore.limit = 10
}

// 正则表达式
const regExp = {
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/
}

// 商品分类
const goodsCategoryOptions: any = reactive([])

// 商品类型
const goodsType: any = reactive([])

// 品牌列表下拉框
const brandOptions: any = reactive([])

// 标签组列表下拉框
const labelOptions: any = reactive([])

// 初始化数据
const initData = () => {
    // 查询商品分类树结构 flag 是否需要进行查询数量
    getCategoryTree({ flag: 1 }).then((res) => {
        const data = res.data
        if (data) {
            const goodsCategoryTree: any = []
            data.forEach((item: any) => {
                const children: any = []
                if (item.child_list) {
                    children.push({
                        value: item.category_id,
                        label: t('全部')
                    })
                    item.child_list.forEach((childItem: any) => {
                        children.push({
                            value: childItem.category_id,
                            label: childItem.category_name + " (" + childItem.goods_count + ")",
                        })
                    })
                }
                goodsCategoryTree.push({
                    value: item.category_id,
                    label: item.category_name + " (" + item.goods_count + ")",
                    children
                })
            })
            goodsCategoryOptions.splice(0, goodsCategoryOptions.length, ...goodsCategoryTree)
        }
    })

    // 商品类型
    getGoodsType().then((res) => {
        const data = res.data
        if (data) {
            for (const k in data) {
                goodsType.push(data[k])
            }
        }
    })

    // 商品品牌
    getBrandList({}).then((res) => {
        const data = res.data
        if (data) {
            brandOptions.push(...data)
        }
    })

    // 商品标签
    getLabelList({}).then((res) => {
        const data = res.data
        if (data) {
            labelOptions.push(...data)
        }
    })
}

initData()

// 当前选中tab页面
const tabHandleClick = (tab: any, event: Event) => {
    const tabName = tab.props.name
    
    // 如果点击的是 "source" 标签（name="2"）
    if (tabName === '2') {
        // 设置 source 为当前站点的 site_id
        goodsTable.searchParam.source = userStore().siteInfo.site_id
        // status 设为空，不按状态筛选（或者设为 '1' 只显示上架的）
        goodsTable.searchParam.status = '1'
    } else {
        // 其他标签清空 source 筛选
        goodsTable.searchParam.source = ''
        goodsTable.searchParam.status = tabName
    }
    
    loadGoodsList()
}

// 批量复选框
const toggleCheckbox = ref()

// 复选框中间状态
const isIndeterminate = ref(false)

// 监听批量复选框事件
const toggleChange = (value: any) => {
    isIndeterminate.value = false
    goodsListTableRef.value.toggleAllSelection()
}

const goodsListTableRef = ref()

// 选中数据
const multipleSelection: any = ref([])

// 监听表格单行选中
const handleSelectionChange = (val: []) => {
    multipleSelection.value = val

    toggleCheckbox.value = false
    if (multipleSelection.value.length > 0 && multipleSelection.value.length < goodsTable.data.length) {
        isIndeterminate.value = true
    } else {
        isIndeterminate.value = false
    }

    if (multipleSelection.value.length == goodsTable.data.length) {
        toggleCheckbox.value = true
    }
}

// 商品预览
const previewEvent = (data: any) => {
    const url = router.resolve({
        path: '/preview/wap',
        query: {
            page: `/addon/phone_shop/pages/goods/detail?goods_id=${data.goods_id}`
        }
    })
    window.open(url.href)
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
        goodsTable.searchParam.order = event.prop
        goodsTable.searchParam.sort = sort
    }
    loadGoodsList()
}

// 监听商品名称输入，实现防抖搜索
watch(
    () => goodsTable.searchParam.sku_no,
    debounce((newValue: string) => {
        // 当商品名称变化时，自动触发搜索
        // 重置到第一页
        // isReset.value = true
        loadGoodsList(1)
    }, 500) // 500ms 防抖延迟
)

// 修改商品上下架状态
const statusChange = (row: any, value: any) => {
    if (value) {
        editGoodsStatus({
            goods_ids: row.goods_id,
            status: value
        }).then((res) => {
            loadGoodsList()
        })
    } else {
        ElMessageBox.confirm(t('statusChangeTips'), t('warning'),
            {
                confirmButtonText: t('confirm'),
                cancelButtonText: t('cancel'),
                type: 'warning'
            }
        ).then(() => {
            editGoodsStatus({
                goods_ids: row.goods_id,
                status: value
            }).then((res) => {
                loadGoodsList()
            })
        })
    }
}

// 批量设置上下架
const batchGoodsStatus = (status: any) => {
    if (multipleSelection.value.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedGoodsTips')}`
        })
        return
    }

    const goodsIds: any = []
    multipleSelection.value.forEach((item: any) => {
        goodsIds.push(item.goods_id)
    })

    editGoodsStatus({
        goods_ids: goodsIds,
        status
    }).then((res) => {
        loadGoodsList()
    })
}

const batchDeleteGoods = () => {
    if (multipleSelection.value.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t('batchEmptySelectedGoodsTips')}`
        })
        return
    }

    ElMessageBox.confirm(t('batchGoodsDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true

        const goodsIds: any = []
        multipleSelection.value.forEach((item: any) => {
            goodsIds.push(item.goods_id)
        })

        deleteGoods({
            goods_ids: goodsIds
        }).then(() => {
            loadGoodsList()
            repeat.value = false
        }).catch(() => {
            repeat.value = false
        })
    })
}

// 修改排序号
const sortInputListener = debounce((sort, row) => {
    if (isNaN(sort) || !regExp.number.test(sort)) {
        ElMessage({
            type: 'warning',
            message: `${t('sortTips')}`
        })
        return
    }
    if (sort > 99999999) {
        row.sort = 99999999
    }
    editGoodsSort({
        goods_id: row.goods_id,
        sort
    }).then((res) => {
        // loadGoodsList();
    })
})

/**
 * 获取商品列表
 */
const loadGoodsList = (page: number = 1) => {
    if (goodsTable.searchParam.start_sale_num && !regExp.digit.test(goodsTable.searchParam.start_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('startSaleNumTips')}`
        })
        return
    }
    if (goodsTable.searchParam.end_sale_num && !regExp.digit.test(goodsTable.searchParam.end_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('endSaleNumTips')}`
        })
        return
    }
    if (Number(goodsTable.searchParam.start_sale_num) > Number(goodsTable.searchParam.end_sale_num)) {
        ElMessage({
            type: 'warning',
            message: `${t('shopSaleNumTips')}`
        })
        return
    }
    if (goodsTable.searchParam.start_price && !regExp.digit.test(goodsTable.searchParam.start_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('startPriceTips')}`
        })
        return
    }
    if (goodsTable.searchParam.end_price && !regExp.digit.test(goodsTable.searchParam.end_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('endPriceTips')}`
        })
        return
    }
    if (Number(goodsTable.searchParam.start_price) > Number(goodsTable.searchParam.end_price)) {
        ElMessage({
            type: 'warning',
            message: `${t('shopPriceTips')}`
        })
        return
    }
    goodsTable.loading = true
    goodsTable.page = page

    const searchData = cloneDeep(goodsTable.searchParam)

    getGoodsPageList({
        page: paginationStore.page,
        limit: paginationStore.limit,
        ...searchData
    }).then(res => {
        goodsTable.loading = false
        goodsTable.data = res.data.data
        goodsTable.total = res.data.total
        paginationStore.setLoading(false)
        // 更新总条目数，这里是假设总条目数为100
        paginationStore.setTotal(res.data.total)
    }).catch(() => {
        goodsTable.loading = false
    })
}
/*
* 页码和数量进行缓存
* */
function handleSizeChange(newSize: number) {
    paginationStore.setLimit(newSize)
    loadGoodsList()
}

function handlePageChange(newPage: number) {
    paginationStore.setPage(newPage)
    loadGoodsList()
}

loadGoodsList()

/**
 * 添加商品
 */
const addEvent = () => {
    router.push('/phone_shop/goods/real_edit')
    // let url = router.resolve({
    //     path: '/phone_shop/goods/real_edit',
    // });
    // window.open(url.href);
}

/**
 * 编辑商品
 * @param data
 */
const editEvent = (data: any) => {
    router.push(data.goods_edit_path + '?goods_id=' + data.goods_id)
    // let url = router.resolve({
    //     path: data.goods_edit_path,
    //     query: {goods_id: data.goods_id}
    // });
    // window.open(url.href);
}

const goodsPriceEditPopupRef: any = ref(null)

// 编辑商品价格
const editPriceEvent = (data: any) => {
    goodsPriceEditPopupRef.value.show(data)
}

const goodsStockEditPopupRef: any = ref(null)

// 编辑商品库存
const editStockEvent = (data: any) => {
    goodsStockEditPopupRef.value.show(data)
}

// 商品推广
const spreadPopupRef = ref(null)


const spreadEvent = (data: any) => {
    const pagePath = '/addon/shop/pages/goods/detail'
    const paramsArr = [
        { name: 'goods_id', value: data.goods_id },
    ];
    const title = '商品推广'
    const folder = 'goods'
    spreadPopupRef.value?.show(pagePath, paramsArr, title, folder);
}

/** ***************** 会员价-start *************************/
// 会员等级
const memberLevel = ref([])
const getMemberLevelAllFn = () => {
    getMemberLevelAll().then(res => {
        memberLevel.value = res.data ? res.data : []
    })
}
getMemberLevelAllFn()

const memberPricePopupRef: any = ref(null)
const memberPriceEvent = (data: any) => {
    memberPricePopupRef.value.show(data, memberLevel.value)
}
/** ***************** 会员价-end *************************/

/** ***************** 线下销售-start *************************/
// 线下销售弹窗
const offlineSalePopupRef: any = ref(null)
const offlineSaleEvent = (data: any) => {

    
    offlineSalePopupRef.value.show(data)
}
/** ***************** 线下销售-end *************************/

// 复制商品
const copyEvent = (data: any) => {
    ElMessageBox.confirm(t('goodsCopyTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true

        copyGoods({
            goods_id: data.goods_id
        }).then((res: any) => {
            if (res.code == 1) {
                loadGoodsList()
            }
            repeat.value = false
        }).catch(err => {
            repeat.value = false
        })
    })
}

// 删除商品
const deleteEvent = (id: number) => {
    ElMessageBox.confirm(t('goodsDeleteTips'), t('warning'),
        {
            confirmButtonText: t('confirm'),
            cancelButtonText: t('cancel'),
            type: 'warning'
        }
    ).then(() => {
        if (repeat.value) return
        repeat.value = true
        deleteGoods({
            goods_ids: id
        }).then(() => {
            loadGoodsList()
            repeat.value = false
        }).catch(() => {
            repeat.value = false
        })
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    goodsTable.searchParam.start_price = ''
    goodsTable.searchParam.end_price = ''
    goodsTable.searchParam.start_sale_num = ''
    goodsTable.searchParam.end_sale_num = ''
    goodsTable.searchParam.sku_no=''
    goodsTable.searchParam.goods_name=''
    goodsTable.searchParam.inventory_age=''


    loadGoodsList()
}

// 初始化剪贴板功能
const { copy, isSupported } = useClipboard()

/**
 * 批量分享商品
 */
const batchShareGoods = async () => {
    // 1. 检查是否有选中商品
    if (multipleSelection.value.length == 0) {
        ElMessage({
            type: 'warning',
            message: '请先选择要分享的商品'
        })
        return
    }

    // 2. 显示加载提示
    const loading = ElLoading.service({
        lock: true,
        text: `正在生成 ${multipleSelection.value.length} 个商品的分享链接...`,
        background: 'rgba(0, 0, 0, 0.7)'
    })

    try {
        // 3. 准备商品列表数据
        const goodsList = multipleSelection.value.map((item: any) => ({
            goods_id: item.goods_id,
            goods_name: item.goods_name,
            sub_title: item.sub_title || ''
        }))

        // 4. 调用批量生成 API
        const res = await batchGenerateShortLink({
            goods_list: goodsList
        })

        loading.close()

        if (res.code !== 1) {
            ElMessage({
                type: 'error',
                message: res.msg || '生成分享链接失败'
            })
            return
        }

        // 5. 处理返回结果
        const successList = res.data.filter((item: any) => item.success)

        if (successList.length === 0) {
            ElMessage({
                type: 'error',
                message: '所有商品的分享链接生成失败，请稍后重试'
            })
            return
        }

        // 6. 格式化分享文本
        // 格式：商品名称(含sub_title) #小程序://xxx/xxxx
        const shareLines = successList.map((item: any) => {
            let title = item.goods_name
            if (item.sub_title) {
                title += ' ' + item.sub_title
            }
            return `${title} ${item.short_link}`
        })

        const shareText = shareLines.join('\n')

        // 7. 复制到剪贴板
        if (isSupported.value) {
            copy(shareText)

            const failedCount = res.data.length - successList.length
            let message = `已成功生成 ${successList.length} 个分享链接并复制到剪贴板`
            if (failedCount > 0) {
                message += `，${failedCount} 个失败`
            }

            ElMessage({
                type: 'success',
                message: message,
                duration: 3000
            })
        } else {
            // 如果不支持复制，显示弹窗让用户手动复制
            ElMessageBox.alert(shareText, '分享链接（请手动复制）', {
                confirmButtonText: '关闭',
                type: 'success'
            })
        }

    } catch (error: any) {
        loading.close()
        ElMessage({
            type: 'error',
            message: '生成分享链接失败：' + (error.message || '未知错误')
        })
    }
}

</script>

<style lang="scss" scoped>
.price-wrap,
.stock-wrap {
    &:hover {
        .icon-wrap {
            visibility: visible;
            color: var(--el-color-primary);
        }
    }
}
</style>
