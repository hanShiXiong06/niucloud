<template>
    <view @touchmove.prevent.stop>
        <u-popup :show="showDialog" @close="showDialog=false" mode="bottom" zIndex="99">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">修改价格</view>
                <view class="px-[30rpx] flex justify-end">
                    <view @click="selectData.isShow = !selectData.isShow" v-if="goodsTable.length > 1">
                        <text class="nc-iconfont nc-icon-fuzhiV6xx1" v-if="!selectData.isShow"></text>
                        <text>{{ selectData.isShow ? '取消批量' : '批量' }}</text>
                    </view>
                </view>
                <scroll-view scroll-y="true" class="h-[600rpx] px-[30rpx] mb-[20rpx] box-border">
                    <view>
                        <view class="mb-[20rpx]" v-for="(item, index) in goodsTable" :key="index">
                            <view class="flex items-center mb-[20rpx]"  @click="handleCheckedChange(item)">
                                <text v-if="selectData.isShow" class="iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 text-primary': item.checked,'bg-[#F5F5F5]':!item.checked}"></text>
                                <view class="flex flex-1">
                                    <up-image class="rounded-[10rpx] overflow-hidden" width="60rpx" height="60rpx" :src="img(item.sku_image)" mode="aspectFill">
                                        <template #error>
                                            <image class="w-[60rpx] h-[60rpx] rounded-[10rpx] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                        </template>
                                    </up-image>
                                    <view class="flex-1 ml-[20rpx] flex flex-col justify-between">
                                        <text class="text-[26rpx]">{{ item.sku_name ? item.sku_name : item.goods.goods_name }}</text>
                                        <text class="text-[24rpx] text-[#999]">库存 {{ item.stock }}</text>
                                    </view>
                                </view>
                            </view>
                            <view class="flex items-center">
                                <view class="mr-[32rpx] w-[34rpx]" v-if="selectData.isShow"></view>
                                <view class="flex-1 bg-[var(--page-bg-color)] p-[10rpx] rounded-[10rpx] flex justify-between items-center">
                                    <text>价格</text>
                                    <input class="flex-1 text-right" maxlength="8" type="text" v-model="item.price"  placeholder="请输入" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" :disabled="activeGoodsCount > 0">
                                    <text class="ml-[10rpx]">元</text>
                                </view>
                            </view>
                            
                        </view>
                    </view>
                </scroll-view>
                <view class="btn-wrap flex justify-between items-center" v-if="selectData.isShow">
                    <view class="mr-[20rpx] flex items-center">
                        <text class="iconfont text-color text-[34rpx] mr-[20rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 text-primary': selectData.allCheck,'bg-[#F5F5F5]': !selectData.allCheck }" @click="handleCheckAllChange()"></text>
                        <text>全选</text>
                    </view>
                    <button class="btn border-[0] rounded-[100rpx] primary-btn-bg mr-0  !w-[300rpx] !h-[60rpx] flex-center" shape="circle" hover-class="none" @click="setBatch">批量修改价格</button>
                </view>
                <view class="btn-wrap !pt-[30rpx]" v-else>
                    <button class="btn border-[0] rounded-[100rpx] primary-btn-bg" shape="circle" hover-class="none" @click="save">确定</button>
                </view>
            </view>
        </u-popup>
        <u-popup :show="showBatchDialog" @close="showBatchDialog=false" mode="bottom" zIndex="999">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">批量修改价格</view>
                <view class="mx-[30rpx] px-[20rpx] h-[80rpx] rounded-[10rpx] flex justify-between items-center border-[2rpx] border-solid border-primary">
                    <text>价格</text>
                    <input class="flex-1 text-right h-full" maxlength="8" type="text" v-model="batchPrice"  placeholder="请输入" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" :autofocus="true" >
                    <text class="ml-[10rpx]">元</text>
                </view>
                <view class="btn-wrap flex justify-between items-center !pt-[30rpx]">
                    <button class="btn flex-1 !leading-[76rpx] mr-[20rpx] !text-[var(--primary-color)] border-[2rpx] border-solid border-[var(--primary-color)] rounded-[100rpx] bg-transparent box-border" hover-class="none" @click="cancel">取消</button>
                    <button class="btn flex-1 border-[0] rounded-[100rpx] primary-btn-bg" shape="circle" hover-class="none" @click="handleBatch">确定</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import {  deepClone, img} from '@/utils/common'
import { getActiveGoodsCount, getGoodsSkuList, editGoodsListPrice } from '@/addon/mall/api/goods'

const showDialog = ref(false);
const showBatchDialog = ref(false);
const batchPrice = ref<any>('')
const goods: any = ref({})
const activeGoodsCount: any = ref(0)

const selectData = reactive({
    isShow: false,
    allCheck: false,
    dataChecked: [],// 选中的规格
})

const emit = defineEmits(['load'])

const loading = ref(true)
const goodsTable = ref<any>([])

/**
 * 获取商品列表
 */
const loadGoodsList = () => {
    loading.value = true

    getGoodsSkuList({
        goods_id: goods.value.goods_id
    }).then((res: any) => {
        loading.value = true
        goodsTable.value = res.data
    }).catch(() => {
        loading.value = true
    })
}


const open = (data: any) => {
    goods.value = {}
    goods.value = data;
    getActiveGoodsCountFn();
    loadGoodsList()
    showDialog.value = true
}


const getActiveGoodsCountFn = ()=>{
    getActiveGoodsCount({
        goods_id: goods.value.goods_id
    }).then((res: any)=>{
        activeGoodsCount.value = res.data;
    })
}


// 正则表达式
const regExp: any = {
    digit: /^\d{0,10}(.?\d{0,2})$/
}

const verify = () => {
    let result = true
    for (let i = 0; i < goodsTable.value.length; i++) {
        const item:any = goodsTable.value[i]

        if (isNaN(item.price) || !regExp.digit.test(item.price)) {
            result = false
            uni.showToast({ title: '销售价格式输入错误', icon: 'none' })
            break
        } else if (item.price < 0) {
            result = false
            uni.showToast({ title: '销售价不能小于0', icon: 'none' })
            break
        } else  if (item.price === '' || item.price == 0) {
            result = false
            uni.showToast({ title: '请输入销售价', icon: 'none' })
            break
        } 
    }

    return result
}

const save = () => {
    if (verify()) {
        let sku_list = <any>[]
        goodsTable.value.forEach((item: any) => {
            sku_list.push({
                sku_id: item.sku_id,
                price: item.price,
                market_price: item.market_price,
                cost_price: item.cost_price
            })
        })
        editGoodsListPrice({
            goods_id: goods.value.goods_id,
            sku_list
        }).then(res => {
            emit('load');
            showDialog.value = false
        })
    }
}
// 单选
const  handleCheckedChange = (data: any) => {
    data.checked = !data.checked
    selectData.dataChecked = goodsTable.value.filter((item: any) => item.checked)
    if(selectData.dataChecked.length == goodsTable.value.length){
        selectData.allCheck = true
    }else{
        selectData.allCheck = false
    }
}

// 全选
const handleCheckAllChange = () => {
    selectData.allCheck = !selectData.allCheck
    goodsTable.value.forEach((item: any) => {
        item.checked = selectData.allCheck
    })
    selectData.dataChecked = goodsTable.value.filter((item: any) => item.checked)
}

const setBatch = () => {
    if(!selectData.dataChecked.length){
        uni.showToast({ title: '请选择商品', icon: 'none' })
        return
    }
    showBatchDialog.value = true
}
const  handleBatch = () => {
    if (batchPrice.value === '') {
        uni.showToast({ title: '请输入价格', icon: 'none' })
        return
    }
    if (isNaN(batchPrice.value) || !regExp.digit.test(batchPrice.value)) {
        uni.showToast({ title: '价格式输入错误', icon: 'none' })
        return
    } 
    if (batchPrice.value < 0) {
        uni.showToast({ title: '价格不能小于0', icon: 'none' })
        return
    } 
    selectData.dataChecked.forEach((item: any) => {
        goodsTable.value.forEach((goods: any) => {
            if(goods.sku_id == item.sku_id){
                goods.price = batchPrice.value
            }
        })
    })
    showBatchDialog.value = false
    batchPrice.value = ''
    selectData.isShow = false
}
const cancel = () => {
    showBatchDialog.value = false
    batchPrice.value = ''
}

defineExpose({
    showDialog,
    open
})
</script>

<style scoped>

</style>