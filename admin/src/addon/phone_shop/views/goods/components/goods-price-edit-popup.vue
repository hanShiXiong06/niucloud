<template>
    <div>
        <el-dialog v-model="showDialog" :title="t('editPricePopupTitle')" width="800px" :destroy-on-close="true" :close-on-click-modal="false">

            <div class="flex items-center mb-[10px]">
                <div class="min-w-[70px] h-[70px] flex items-center justify-center">
                    <el-image v-if="goods.goods_cover_thumb_small" class="w-[70px] h-[70px]" :src="img(goods.goods_cover_thumb_small)" fit="contain">
                        <template #error>
                            <div class="image-slot">
                                <img class="w-[70px] h-[70px]" src="@/addon/phone_shop/assets/goods_default.png" />
                            </div>
                        </template>
                    </el-image>
                    <img v-else class="w-[70px] h-[70px]" src="@/addon/phone_shop/assets/goods_default.png" fit="contain" />
                </div>
                <div class="ml-2">
                    <span :title="goods.goods_name" class="multi-hidden">{{ goods.goods_name }}</span>
                    <span class="text-primary text-[12px]">{{ goods.goods_type_name }}</span>
                </div>
            </div>

            <!-- 批量设置 -->
            <div class="batch-operation-sku" v-if="goodsTable.data.length > 1">
                <label>{{ t('batchOperationSku') }}</label>

                <div v-if="batchOperation.field">
                    <el-input v-model.trim="batchOperation.value" clearable :placeholder="t(batchOperation.field)" class="set-input" maxlength="8" :autofocus="true" />
                    <el-button type="primary" @click="saveBatch">{{ t('confirm') }}</el-button>
                    <el-button @click="clearBatch">{{ t('cancel') }}</el-button>
                </div>
                <div v-else>
                    <el-button type="primary" link @click="setBatchField('price')" v-if="activeGoodsCount == 0">{{ t('price') }}</el-button>
                    <el-button type="primary" link @click="setBatchField('market_price')">{{ t('marketPrice') }}</el-button>
                    <el-button type="primary" link @click="setBatchField('cost_price')">{{ t('costPrice') }}</el-button>
                </div>
            </div>

            <el-table :data="goodsTable.data" size="large" v-loading="goodsTable.loading" max-height="400">
                <template #empty>
                    <span>{{ !goodsTable.loading ? t('emptyData') : '' }}</span>
                </template>

                <el-table-column prop="sku_name" :label="t('skuName')" min-width="120" v-if="goodsTable.data.length > 1" />
                <el-table-column prop="price" :label="t('price')" min-width="120">
                    <template #default="{ row }">
                        <el-input v-model.trim="row.price" clearable placeholder="0.00" maxlength="8" :disabled="activeGoodsCount > 0" />
                    </template>
                </el-table-column>

                <el-table-column prop="market_price" :label="t('marketPrice')" min-width="120">
                    <template #default="{ row }">
                        <el-input v-model.trim="row.market_price" clearable placeholder="0.00" maxlength="8" />
                    </template>
                </el-table-column>

                <el-table-column prop="cost_price" :label="t('costPrice')" min-width="120">
                    <template #default="{ row }">
                        <el-input v-model.trim="row.cost_price" clearable placeholder="0.00" maxlength="8" />
                    </template>
                </el-table-column>

                <!-- 会员价(仅当商品启用了"指定会员价"时渲染,各等级一列) -->
                <el-table-column v-for="(lv, li) in (hasMemberPrice ? memberLevels : [])" :key="'mp' + li" :label="lv.level_name + '会员价'" min-width="130">
                    <template #default="{ row }">
                        <el-input v-model.trim="row[lkey(lv)]" clearable placeholder="0.00" maxlength="8" />
                    </template>
                </el-table-column>

            </el-table>

            <template #footer>
                <span class="dialog-footer">
                    <el-button @click="showDialog = false">{{ t('cancel') }}</el-button>
                    <el-button type="primary" @click="save">{{ t('confirm') }}</el-button>
                </span>
            </template>
        </el-dialog>
    </div>
</template>

<script lang="ts" setup>
import { t } from '@/lang'
import { ref, reactive, computed } from 'vue'
import { img } from '@/utils/common'
import { ElMessage } from 'element-plus'

import {
    getActiveGoodsCount,
    getGoodsSkuList,
    editGoodsListPrice,
    editGoodsListMemberPrice
} from '@/addon/phone_shop/api/goods'

const goods: any = reactive({})
const activeGoodsCount: any = ref(0)

const showDialog = ref(false)

// 会员等级 + 会员价 JSON 键(统一 level_no,回退 level_id)
const memberLevels: any = ref([])
const lkey = (lv: any): string => 'level_' + (lv.level_no || lv.level_id)
// 仅"指定会员价"(fixed_price)且有等级时,才在表格里渲染会员价列
const hasMemberPrice = computed(() => goods.member_discount === 'fixed_price' && memberLevels.value.length > 0)

const emit = defineEmits(['load'])

const goodsTable = reactive({
    loading: true,
    data: []
})

const batchOperation: any = reactive({
    field: '', // 当前编辑的字段
    value: '' // 输入值
})

const setBatchField = (value: string, regexp: string) => {
    batchOperation.field = value
    batchOperation.value = ''
}

const clearBatch = () => {
    batchOperation.field = ''
    batchOperation.value = ''
}

const saveBatch = () => {
    if (batchOperation.value.length == 0) {
        ElMessage({
            type: 'warning',
            message: `${t(batchOperation.field + 'Placeholder')}`
        })
        return
    }

    if (!regExp.digit.test(batchOperation.value)) {
        ElMessage({
            type: 'warning',
            message: `${t(batchOperation.field + 'Tips')}`
        })
        return
    }

    if (batchOperation.value < 0) {
        ElMessage({
            type: 'warning',
            message: `${t(batchOperation.field + 'NotZeroTips')}`
        })
        return
    }

    goodsTable.data.forEach((item: any) => {
        item[batchOperation.field] = batchOperation.value
    })

    clearBatch()
}

/**
 * 获取商品列表
 */
const loadGoodsList = () => {
    goodsTable.loading = true

    getGoodsSkuList({
        goods_id: goods.goods_id
    }).then(res => {
        goodsTable.loading = false
        const data = res.data || []
        // 预填会员价:按 level_no 取,兼容老 level_id;无则用零售价兜底
        data.forEach((item: any) => {
            let mp: any = {}
            try { mp = item.member_price ? JSON.parse(item.member_price) : {} } catch (e) { mp = {} }
            memberLevels.value.forEach((lv: any) => {
                const v = mp[lkey(lv)] ?? mp[`level_${lv.level_id}`] ?? null
                item[lkey(lv)] = parseFloat(v != null && v !== '' ? v : item.price).toFixed(2)
            })
        })
        goodsTable.data = data
    }).catch(() => {
        goodsTable.loading = false
    })
}

const show = (data: any, levels: any = []) => {
    Object.assign(goods, data)
    memberLevels.value = levels || []
    getActiveGoodsCountFn();
    loadGoodsList()
    showDialog.value = true
}

const getActiveGoodsCountFn = ()=>{
    getActiveGoodsCount({
        goods_id: goods.goods_id
    }).then((res)=>{
        activeGoodsCount.value = res.data;
    })
}

// 正则表达式
const regExp: any = {
    digit: /^\d{0,10}(.?\d{0,2})$/
}

const verify = () => {
    let result = true
    for (let i = 0; i < goodsTable.data.length; i++) {
        const item:any = goodsTable.data[i]

        if (isNaN(item.price) || !regExp.digit.test(item.price)) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('priceTips')}`
            })
            break
        } else if (item.price < 0) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('priceNotZeroTips')}`
            })
            break
        }

        if (isNaN(item.market_price) || !regExp.digit.test(item.market_price)) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('marketPriceTips')}`
            })
            break
        } else if (item.market_price < 0) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('marketPriceNotZeroTips')}`
            })
            break
        }

        if (isNaN(item.cost_price) || !regExp.digit.test(item.cost_price)) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('costPriceTips')}`
            })
            break
        } else if (item.cost_price < 0) {
            result = false
            ElMessage({
                type: 'warning',
                message: `${t('costPriceNotZeroTips')}`
            })
            break
        }
    }

    return result
}

// 校验会员价:>0 且不大于零售价
const verifyMember = (): boolean => {
    for (const item of goodsTable.data as any[]) {
        for (const lv of memberLevels.value) {
            const val = parseFloat(item[lkey(lv)])
            if (isNaN(val) || val <= 0) {
                ElMessage.warning(`[${lv.level_name}]会员价必须大于 0`)
                return false
            }
            if (val > parseFloat(item.price)) {
                ElMessage.warning(`[${lv.level_name}]会员价不能大于零售价`)
                return false
            }
        }
    }
    return true
}

const save = () => {
    if (!verify()) return
    if (hasMemberPrice.value && !verifyMember()) return

    const sku_list = <any>[]
    goodsTable.data.forEach((item: any) => {
        sku_list.push({
            sku_id: item.sku_id,
            price: item.price,
            market_price: item.market_price,
            cost_price: item.cost_price
        })
    })

    // 价格保存 + (有会员价时)会员价保存,两个请求都成功才算完成,一次确定生效
    const tasks: Promise<any>[] = [
        editGoodsListPrice({ goods_id: goods.goods_id, sku_list })
    ]
    if (hasMemberPrice.value) {
        const member_sku_list = goodsTable.data.map((item: any) => {
            const obj: any = { sku_id: item.sku_id, member_price: {} }
            memberLevels.value.forEach((lv: any) => { obj.member_price[lkey(lv)] = item[lkey(lv)] })
            return obj
        })
        tasks.push(editGoodsListMemberPrice({
            goods_id: goods.goods_id,
            member_discount: 'fixed_price',
            sku_list: member_sku_list
        }))
    }

    Promise.all(tasks).then(() => {
        emit('load')
        showDialog.value = false
    })
}

defineExpose({
    showDialog,
    show
})
</script>

<style lang="scss" scoped>
.form-item-wrap {
    margin-right: 10px !important;

    &.last-child {
        margin-right: 0 !important;
    }
}

.batch-operation-sku {
    display: flex;
    margin-bottom: 16px;
    background-color: #ffffff;
    -ms-flex-wrap: nowrap;
    flex-wrap: nowrap;
    -webkit-box-align: center;
    -ms-flex-align: center;
    align-items: center;

    label {
        font-size: 14px;
        margin-right: 10px;
        height: 20px;
    }

    .set-input {
        max-width: 150px;
        min-width: 60px;
        margin-right: 10px;
    }
}
</style>
