<template>
    <div>
        <el-form :inline="true" :model="cardTable.searchParam" ref="searchFormRef">
            <el-form-item label="次卡名称" prop="keyword" class="form-item-wrap">
                <el-input v-model.trim="cardTable.searchParam.keyword" placeholder="请输入次卡名称" maxlength="60" />
            </el-form-item>
            <el-form-item class="form-item-wrap">
                <el-button type="primary" @click="loadCardList()">查询</el-button>
                <el-button @click="resetForm(searchFormRef)">重置</el-button>
            </el-form-item>
        </el-form>

        <div class="table w-[100%]" v-loading="cardTable.loading">
            <div class="table-head flex items-center bg-[#f5f7f9] py-[8px]">
                <div class="w-[3%]"></div>
                <div class="w-[7%]"></div>
                <div class="w-[50%]">次卡名称</div>
                <div class="w-[20%]">价格</div>
                <div class="w-[20%]">销量</div>
            </div>
            <div class="table-body h-[350px] overflow-y-auto">
                <div v-for="(row,rowIndex) in cardTable.data" :key="rowIndex" class="flex flex-col">
                    <!-- 内容 -->
                    <div class="flex items-center border-solid border-[#e5e7eb] py-[5px] border-b-[1px]">
                        <div class="w-[3%]"></div>
                        <div class="w-[7%]">
                            <el-checkbox v-model="row.secondLevelCheckAll" @change="secondLevelHandleCheckAllChange($event,row)" />
                        </div>
                        <div class="flex items-center cursor-pointer w-[50%]">
                            <div class="min-w-[60px] h-[60px] flex items-center justify-center">
                                <el-image v-if="row.card_image" class="w-[60px] h-[60px]" :src="img(row.card_image)" fit="contain">
                                    <template #error>
                                        <div class="image-slot">
                                            <img class="w-[60px] h-[60px]" src="@/addon/home_service/assets/goods_default.png" />
                                        </div>
                                    </template>
                                </el-image>
                                <img v-else class="w-[60px] h-[60px]" src="@/addon/home_service/assets/goods_default.png" fit="contain" />
                            </div>
                            <div class="ml-2 flex flex-col items-start">
                                <span :title="row.card_name" class="multi-hidden leading-[1.4]">{{ row.card_name }}</span>
                                <span class="text-primary text-[12px]">ID: {{ row.card_id }}</span>
                            </div>
                        </div>
                        <div class="w-[20%]">{{ row.price ? ('￥' + row.price) : '-' }}</div>
                        <div class="w-[20%]">{{ row.sale_num ?? '-' }}</div>
                    </div>
                </div>

                <div v-if="!cardTable.data.length && !cardTable.loading" class="h-[60px] flex items-center justify-center border-solid border-[#e5e7eb] py-[12px] border-b-[1px]">暂无数据</div>
            </div>
        </div>

        <div class="mt-[16px] flex">
            <div class="flex items-center flex-1"></div>
            <el-pagination v-model:current-page="cardTable.page" v-model:page-size="cardTable.limit"
                           layout="total, sizes, prev, pager, next, jumper" :total="cardTable.total"
                           @size-change="loadCardList()" @current-change="loadCardList" />
        </div>

    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import { ref, reactive, computed, nextTick } from 'vue'
import { cloneDeep } from 'lodash-es'
import { img, deepClone } from '@/utils/common'
import { ElMessage, FormInstance } from 'element-plus'
import { getCardSelectPageList } from '@/addon/home_service/api/goods'

const max = 1 // 单选
const min = 1

const replacePrefix = 'card_'

const cardIds: any = ref<number[]>([])

// 已选卡列表
const selectCard: any = reactive({})

// 已选卡 id 列表
const selectCardId: any = reactive<number[]>([])

// 已选数量
const selectCardNum: any = computed(() => {
    return Object.keys(selectCard).length
})

const cardTable = reactive({
    page: 1,
    limit: 10,
    total: 0,
    loading: true,
    data: [] as any[],
    searchParam: {
        keyword: ''
    }
})

const searchFormRef = ref<FormInstance>()

// 二级复选框（单选）
const secondLevelHandleCheckAllChange = (isSelect: boolean, row: any) => {
    // 清空之前选择，保证单选
    selectCardId.splice(0, selectCardId.length)
    for (const k in selectCard) delete selectCard[k]

    if (isSelect) {
        selectCardId.push(row.card_id)
        selectCard[replacePrefix + row.card_id] = deepClone(row)
    } else {
        const idx = selectCardId.indexOf(row.card_id)
        if (idx !== -1) selectCardId.splice(idx, 1)
        delete selectCard[replacePrefix + row.card_id]
    }

    setCardSelected()
}

/**
 * 获取次卡列表
 */
const loadCardList = (page: number = 1) => {
    cardTable.loading = true
    cardTable.data = []
    cardTable.page = page

    const searchData = cloneDeep(cardTable.searchParam)

    getCardSelectPageList({
        page: cardTable.page,
        limit: cardTable.limit,
        ...searchData
    }).then(res => {
        const tableData = cloneDeep(res.data.data)
        tableData.forEach((item: any) => {
            item.secondLevelCheckAll = false
        })
        setCardSelected()
        cardTable.data = tableData
        cardTable.total = res.data.total
        cardTable.loading = false
    }).catch(() => {
        cardTable.loading = false
    })
}

// 表格设置选中状态
const setCardSelected = () => {
    nextTick(() => {
        for (let i = 0; i < cardTable.data.length; i++) {
            cardTable.data[i].secondLevelCheckAll = false
            if (selectCard[replacePrefix + cardTable.data[i].card_id]) {
                cardTable.data[i].secondLevelCheckAll = true
            }
        }
    })
}

const resetForm = (formEl: FormInstance | undefined) => {
    if (!formEl) return
    formEl.resetFields()
    loadCardList()
}

// 初始化
const init = () => {
    for (const k in selectCard) delete selectCard[k]
    loadCardList(1)
}

init()

// 暴露给 diy-link
const getData = () => {
    if (min && selectCardNum.value < min) {
        ElMessage({ type: 'warning', message: `至少选择${ min }个` })
        return null
    }

    if (max && max > 0 && selectCardNum.value && selectCardNum.value > max) {
        ElMessage({ type: 'warning', message: `最多只能选择${ max }个` })
        return null
    }

    const ids: any[] = []
    for (const k in selectCard) {
        ids.push(parseInt(k.replace(replacePrefix, '')))
    }

    cardIds.value.splice(0, cardIds.value.length, ...ids)
    const cardInfo = selectCard['card_' + cardIds.value[0]]
    if (!cardInfo) {
        ElMessage({ type: 'warning', message: '请选择一个次卡' })
        return null
    }
    return {
        name: 'HOME_SERVICE_CARD',
        parent: 'HOME_SERVICE',
        title: cardInfo.card_name,
        url: `/addon/home_service/user/pages/card/detail?card_id=${ cardInfo.card_id }`,
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