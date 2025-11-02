<template>
    <div>
        <el-form :inline="true" :model="goodsTable.searchParam" ref="searchFormRef">
            <el-form-item :label="t('goodsSelectPopupGoodsName')" prop="keyword" class="form-item-wrap">
                <el-input v-model.trim="goodsTable.searchParam.keyword" :placeholder="t('goodsSelectPopupGoodsNamePlaceholder')" maxlength="60" />
            </el-form-item>
            <el-form-item :label="t('goodsSelectPopupGoodsCategory')" prop="goods_category" class="form-item-wrap">
                <el-cascader v-model="goodsTable.searchParam.goods_category" :options="goodsCategoryOptions" :placeholder="t('goodsSelectPopupGoodsCategoryPlaceholder')" clearable :props="{ value: 'value', label: 'label', emitPath:false }" />
            </el-form-item>
            <el-form-item :label="t('goodsSelectPopupGoodsType')" prop="goods_type" class="form-item-wrap">
                <el-select v-model="goodsTable.searchParam.goods_type" :placeholder="t('goodsSelectPopupGoodsTypePlaceholder')" clearable>
                    <el-option v-for="item in goodsType" :key="item.type" :label="item.name" :value="item.type" />
                </el-select>
            </el-form-item>
            <el-form-item class="form-item-wrap">
                <el-button type="primary" @click="loadGoodsList()">{{ t('search') }}</el-button>
                <el-button @click="resetForm(searchFormRef)">{{ t('reset') }}</el-button>
            </el-form-item>
        </el-form>

        <div class="table w-[100%]" v-loading="goodsTable.loading">
            <div class="table-head flex items-center bg-[#f5f7f9] py-[8px]">
                <div class="w-[3%]"></div>
                <div class="w-[7%]"></div>
                <div class="w-[50%]">服务名称</div>
                <div class="w-[20%]">价格</div>
                <div class="w-[20%]">销量</div>
            </div>
            <div class="table-body h-[350px] overflow-y-auto">
                <div v-for="(row,rowIndex) in goodsTable.data" :key="rowIndex" class="flex flex-col">
                    <!-- 内容 -->
                    <div class="flex items-center border-solid border-[#e5e7eb] py-[5px] border-b-[1px]">
                        <div class="w-[3%]"></div>
                        <div class="w-[7%]">
                            <el-checkbox v-model="row.secondLevelCheckAll" @change="secondLevelHandleCheckAllChange($event,row)" />
                        </div>
                        <div class="flex items-center cursor-pointer w-[50%]">
                            <div class="min-w-[60px] h-[60px] flex items-center justify-center">
                                <el-image v-if="row.goods_cover_thumb_small" class="w-[60px] h-[60px]" :src="img(row.goods_cover_thumb_small)" fit="contain">
                                    <template #error>
                                        <div class="image-slot">
                                            <img class="w-[60px] h-[60px]" src="@/addon/home_service/assets/goods_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                                <img v-else class="w-[60px] h-[60px]" src="@/addon/home_service/assets/goods_default.png" fit="contain" />
                            </div>
                            <div class="ml-2 flex flex-col items-start">
                                <span :title="row.goods_name" class="multi-hidden leading-[1.4]">{{ row.goods_name }}</span>
                                <span class="text-primary text-[12px]">{{ row.goods_type_name }}</span>
                            </div>
                        </div>
                        <div class="w-[20%]">{{ (row.goodsSku && row.goodsSku.price) ? ('￥' + row.goodsSku.price) : '-' }}</div>
                        <div class="w-[20%]">{{ row.sale_num ?? '-' }}</div>
                    </div>
                </div>

                <div v-if="!goodsTable.data.length && !goodsTable.loading" class="h-[60px] flex items-center justify-center border-solid border-[#e5e7eb] py-[12px] border-b-[1px]">{{ t('emptyData') }}</div>
            </div>
        </div>

        <div class="mt-[16px] flex">
            <div class="flex items-center flex-1"></div>
            <el-pagination v-model:current-page="goodsTable.page" v-model:page-size="goodsTable.limit"
                           layout="total, sizes, prev, pager, next, jumper" :total="goodsTable.total"
                           @size-change="loadGoodsList()" @current-change="loadGoodsList" />
        </div>

    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import { ref, reactive, computed, nextTick } from 'vue'
import { cloneDeep } from 'lodash-es'
import { img, deepClone } from '@/utils/common'
import { ElMessage, FormInstance } from 'element-plus'
import { getGoodsSelectPageList, getGoodsSkuNoPageList, getCategoryTree, getGoodsType } from '@/addon/home_service/api/goods'

const max = 1 // 单选
const min = 1

const replacePrefix = 'goods_'

const goodsIds: any = ref<number[]>([])

// 已选服务列表
const selectGoods: any = reactive({})

// 已选服务列表id
const selectGoodsId: any = reactive<number[]>([])

// 已选服务数量
const selectGoodsNum: any = computed(() => {
    return Object.keys(selectGoods).length
})

const goodsTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as any[],
    searchParam: {
        keyword: '',
        goods_category: [] as any[],
        goods_ids: '',
        verify_goods_ids: '',
        goods_type: '',
        is_gift: 0
    }
})

const searchFormRef = ref<FormInstance>()

// 服务分类
const goodsCategoryOptions: any = reactive([])

// 服务类型
const goodsType: any = reactive([])

// 初始化数据
const initData = () => {
    // 查询服务分类树结构
    getCategoryTree().then((res) => {
        const data = res.data
        if (data) {
            const goodsCategoryTree: any = []
            data.forEach((item: any) => {
                const children: any = []
                if (item.child_list) {
                    item.child_list.forEach((childItem: any) => {
                        children.push({
                            value: childItem.category_id,
                            label: childItem.category_name
                        })
                    })
                }
                goodsCategoryTree.push({
                    value: item.category_id,
                    label: item.category_name,
                    children
                })
            })
            goodsCategoryOptions.splice(0, goodsCategoryOptions.length, ...goodsCategoryTree)
        }
    })

    // 服务类型
    getGoodsType().then((res) => {
        const data = res.data
        if (data) {
            for (const k in data) {
                goodsType.push(data[k])
            }
        }
    })
}

// 二级复选框（单选）
const secondLevelHandleCheckAllChange = (isSelect: boolean, row: any) => {
    // 清空之前选择，保证单选
    selectGoodsId.splice(0, selectGoodsId.length)
    for (const k in selectGoods) delete selectGoods[k]

    if (isSelect) {
        selectGoodsId.push(row.goods_id)
        selectGoods[replacePrefix + row.goods_id] = deepClone(row)
    } else {
        // 未选中，删除当前服务
        const idx = selectGoodsId.indexOf(row.goods_id)
        if (idx !== -1) selectGoodsId.splice(idx, 1)
        delete selectGoods[replacePrefix + row.goods_id]
    }

    // 更新行选中态
    setGoodsSelected()
}

/**
 * 获取服务列表
 */
const loadGoodsList = (page: number = 1, callback: any = null) => {
    goodsTable.loading = true
    goodsTable.data = []
    goodsTable.page = page

    const searchData = cloneDeep(goodsTable.searchParam)

    getGoodsSelectPageList({
        page: goodsTable.page,
        limit: goodsTable.limit,
        ...searchData
    }).then(res => {
        const goodsTableData = cloneDeep(res.data.data)
        goodsTableData.forEach((item: any) => {
            item.secondLevelCheckAll = false
        })

        if (callback) callback(res.data.verify_goods_ids, res.data.select_goods_list)
        setGoodsSelected()

        goodsTable.data = goodsTableData
        goodsTable.total = res.data.total
        goodsTable.loading = false

    }).catch(() => {
        goodsTable.loading = false
    })

}

// 表格设置选中状态 spu
const setGoodsSelected = () => {
    nextTick(() => {
        for (let i = 0; i < goodsTable.data.length; i++) {
            goodsTable.data[i].secondLevelCheckAll = false
            if (selectGoods[replacePrefix + goodsTable.data[i].goods_id]) {
                goodsTable.data[i].secondLevelCheckAll = true
            }
        }
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadGoodsList()
}

const init = () => {
    for (const k in selectGoods) delete selectGoods[k]

    // 纠正数据，并赋值已选服务
    goodsTable.searchParam.verify_goods_ids = goodsIds.value

    getGoodsSkuNoPageListFn()

    loadGoodsList(1, (verify_ids: any) => {
        // 第一次加载时纠正数据并赋值
        if (goodsIds.value && goodsIds.value.length) {
            goodsIds.value.splice(0, goodsIds.value.length, ...verify_ids)
            selectGoodsId.splice(0, selectGoodsId.length, ...verify_ids)
            if (Object.keys(selectGoods).length) {
                for (const key in selectGoods) {
                    const num = Number(key.split(replacePrefix)[1])
                    if (goodsIds.value.indexOf(num) == -1) {
                        delete selectGoods[key]
                    }
                }
            }
        }
    })
}

const getGoodsSkuNoPageListFn = () => {
    const searchData = cloneDeep(goodsTable.searchParam)
    getGoodsSkuNoPageList({ ...searchData }).then((res: any) => {
        const selectGoodsData = res.data
        // 赋值已选择的服务（spu）
        for (let i = 0; i < selectGoodsData.length; i++) {
            if (goodsIds.value.indexOf(selectGoodsData[i].goods_id) != -1) {
                selectGoods[replacePrefix + selectGoodsData[i].goods_id] = selectGoodsData[i]
            }
        }

        if (Object.keys(selectGoods).length && goodsIds.value.length) {
            for (const key in selectGoods) {
                const num = Number(key.split(replacePrefix)[1])
                if (goodsIds.value.indexOf(num) == -1) {
                    delete selectGoods[key]
                }
            }
        }

        setGoodsSelected()
    })
}

initData()
init()

// 供 diy-link 通过 ref 调用
const getData = () => {
    if (min && selectGoodsNum.value < min) {
        ElMessage({ type: 'warning', message: `至少选择${ min }个` })
        return null
    }

    if (max && max > 0 && selectGoodsNum.value && selectGoodsNum.value > max) {
        ElMessage({ type: 'warning', message: `最多只能选择${ max }个` })
        return null
    }

    const ids: any[] = []
    for (const k in selectGoods) {
        ids.push(parseInt(k.replace(replacePrefix, '')))
    }

    goodsIds.value.splice(0, goodsIds.value.length, ...ids)
    const goodsInfo = selectGoods['goods_' + goodsIds.value[0]]
    if (!goodsInfo) {
        ElMessage({ type: 'warning', message: '请选择一个服务' })
        return null
    }
    return {
        name: 'HOME_SERVICE_GOODS',
        parent: 'HOME_SERVICE',
        title: goodsInfo.goods_name,
        url: `/addon/home_service/user/pages/goods/detail?goods_id=${ goodsInfo.goods_id }`,
        action: ''
    }
}

defineExpose({ getData })
</script>

<style lang="scss" scoped>
.form-item-wrap {
    margin-right: 10px !important;
    margin-bottom: 10px !important;

    &.last-child {
        margin-right: 0 !important;
    }
}
</style>