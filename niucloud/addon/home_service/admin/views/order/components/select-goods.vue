<template>
    <el-dialog v-model="dialogGoodsVisible" :title="t('选择服务')"  width="500px">
        <div v-loading="loading" class="min-h-[100px] px-[10px]">
            <div v-if="goodsData">
                <!-- 使用 skuList 显示规格 -->
                <div v-if="goodsData.skuList && goodsData.skuList.length > 0" class="flex flex-col mb-[10px]">
                    <span class="mb-[10px] text-[16px]">规格</span>
                    <div class="flex flex-wrap">
                        <span class="box-border cursor-pointer bg-[#f2f2f2] text-[12px] px-[22px] text-center h-[28px] leading-[28px] flex-center mr-[10px] mb-[10px] border-1 border-solid rounded-[25px] border-[var(--temp-bg)]"
                            :class="{'!border-[var(--el-color-primary)] text-[var(--el-color-primary)] !bg-[var(--el-color-primary-light-8)]': currSpec.skuId == item.sku_id}"
                            v-for="(item,index) in goodsData.skuList" :key="index" @click="changeSku(item)">{{item.sku_name}}</span>
                    </div>
                </div>
                <!-- 库存和数量已隐藏，数量固定为1 -->
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer">
                <el-button @click="cancelFn">{{t('cancel')}}</el-button>
                <el-button type="primary" @click="selectGoodsConfirm">{{t('confirm')}}</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { t } from '@/lang'
import { cloneDeep } from 'lodash-es'
import goodsLimit from './goods-limit.vue'
import { getGoodsSkuInfo } from '@/addon/home_service/api/marketing'
import { ElMessage } from 'element-plus'

const dialogGoodsVisible = ref(false)
const goodsData: any = ref(null)
const goodsLimitRef: any = ref(null)
const currSpec: any = ref({
    skuId: '',
    unit: '',
    stock: 0,
    name: []
})
const loading = ref(true)

const emit = defineEmits(['confirm'])

// 服务限购
const cartData = ref([]) // 加入购物车的数据

const open = (data:any, member_id:any, select:any) => {
    loading.value = true
    currSpec.value.skuId = ''
    currSpec.value.stock = 0
    currSpec.value.name = []
    currSpec.value.unit = ''
    cartData.value = select || []
    getSkuInfoFn(data.goodsSku.sku_id, member_id)
    dialogGoodsVisible.value = true
}

const getSkuInfoFn = (sku_id:any, member_id:any) => {
    loading.value = true
    goodsData.value = null
    getGoodsSkuInfo({
        sku_id,
        member_id
    }).then((res: any) => {
        goodsData.value = cloneDeep(res.data)
        
        // 使用 skuList 设置当前规格信息
        if (goodsData.value.skuList && goodsData.value.skuList.length > 0) {
            const defaultSku = goodsData.value.skuList.find((item: any) => item.is_default == 1) || goodsData.value.skuList[0]
            currSpec.value.skuId = defaultSku.sku_id
            currSpec.value.stock = defaultSku.stock || 0
            currSpec.value.name = defaultSku.sku_name ? [defaultSku.sku_name] : []
        }

        currSpec.value.unit = goodsData.value.goods?.unit || ''

        // 设置当前规格下的库存
        goodsData.value.goods.sku_stock = currSpec.value.stock

        setCartDataFn()
        loading.value = false
    }).catch(() => {
    })
}

// 切换规格
const changeSku = (skuItem: any) => {
    currSpec.value.skuId = skuItem.sku_id
    currSpec.value.stock = skuItem.stock || 0
    currSpec.value.name = skuItem.sku_name ? [skuItem.sku_name] : []

    // 设置当前规格下的库存
    goodsData.value.goods.sku_stock = currSpec.value.stock

    setCartDataFn()
}

// 设置加入购物车的数据
const setCartDataFn = () => {
    cartData.value.forEach((item: any, index) => {
        if (item.goods_id == goodsData.value.goods.goods_id && item.sku_id == currSpec.value.skuId) {
            goodsData.value.goods.card_num = item.num
        }
    })
}

// 取消
const cancelFn = () => {
    dialogGoodsVisible.value = false
}

// 确定
const selectGoodsConfirm = () => {
    const num = 1 // 固定数量为1
    goodsData.value.skuList.forEach((skuItem: any, skuIndex: any, skuArr: any) => {
        if (skuItem.sku_id == currSpec.value.skuId) {
            skuArr[skuIndex].num = num
        }
    })
    dialogGoodsVisible.value = false
    emit('confirm', goodsData.value)
}

defineExpose({
    dialogGoodsVisible,
    open
})
</script>

<style lang="scss" scoped>

</style>
