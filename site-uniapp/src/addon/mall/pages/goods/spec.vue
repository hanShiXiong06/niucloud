<template>
    <view class="bg-[#fff] min-h-[100vh] " :style="themeColor()">
        <view class="p-[20rpx]">
            <view class="mb-[20rpx]" v-if="goodsSpecFormat.length">
                <view v-for="(item, index) in goodsSpecFormat" :key="index">
                    <view class="flex items-center justify-between mb-[20rpx]">
                        <text>{{ item.spec_name }}</text>
                        <view class="flex">
                            <view class="text-[24rpx] bg-[var(--page-bg-color)] text-[#333] px-[20rpx] py-[8rpx] rounded-[8rpx] mr-[20rpx]" @click="updateSpec(index,'edit')">修改</view>
                            <view class="text-[24rpx] bg-[var(--page-bg-color)] text-[#333] px-[20rpx] py-[8rpx] rounded-[8rpx]" @click="deleteSpec(index)">删除</view>
                        </view>
                    </view>
                    <view>
                        <view class="flex items-center mb-[20rpx]" v-for="(specValue, specIndex) in item.values" :key="specValue.id">
                            <view class="w-[44rpx] mr-[20rpx]">
                                <text class="nc-iconfont nc-icon-jianshaoV6mm text-primary text-[44rpx]" v-if="item.values[specIndex+1]?.id" @click="deleteSpecValue(index, specIndex)"></text>
                            </view>
                            <view class="flex-1 search-input h-[80rpx] rounded-[20rpx]">
                                <input class="input" maxlength="50" type="text" v-model="specValue.spec_value_name"  placeholder="请输入" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]" @blur="addSpecValue(index,item)" @input="specValueNameInputListener">
                            </view>
                        </view>
                    </view>
                </view>
            </view>
            <view class="flex items-center mb-[30rpx]">
                <view class="w-[220rpx] h-[70rpx] text-[26rpx] text-primary bg-[var(--primary-color-light)] rounded-[8rpx] flex-center" @click="addSpec">新增规格类型</view>
            </view>
            <view v-show="Object.keys(goodsSkuData).length">
                <view class="flex items-center justify-between mb-[20rpx]">
                    <text>设置价格库存</text>
                    <view @click="selectData.isShow = !selectData.isShow">
                        <text class="nc-iconfont nc-icon-fuzhiV6xx1" v-if="!selectData.isShow"></text>
                        <text>{{ selectData.isShow ? '取消批量' : '批量' }}</text>
                    </view>
                </view>
                <view>
                    <view class="flex items-center mb-[20rpx]" v-for="(item, key, index) in goodsSkuData" :key="key" @click="handleSingle(item,key)"> 
                        <text v-if="selectData.isShow" class="self-center iconfont text-color text-[34rpx] mr-[32rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 text-primary': item.checked,'bg-[#F5F5F5]':!item.checked}" @click.stop="handleCheckedCitiesChange(item)"></text>
                       <view class="bg-[var(--page-bg-color)] p-[20rpx] box-border h-[100rpx] flex-1 rounded-[20rpx] flex items-center">
                            <view class="flex-1 flex">
                                <up-image class="rounded-[10rpx] overflow-hidden" width="60rpx" height="60rpx" :src="img(item.sku_image)" mode="aspectFill">
                                    <template #error>
                                        <image class="w-[60rpx] h-[60rpx] rounded-[10rpx] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                    </template>
                                </up-image>
                                <view class="flex-1 ml-[20rpx] text-[28rpx]">{{ item.spec_name_show }}</view>
                                <view class="flex flex-col justify-between items-center mr-[16rpx]">
                                    <text class="price-font text-[28rpx]" v-if="item.price">￥{{ moneyFormat(item.price) }}</text>
                                    <text class="text-[24rpx] text-[#999]" v-if="item.stock">库存 {{ item.stock }}</text>
                                </view>
                            </view>
                            <text class="nc-iconfont nc-icon-xiaV6xx"></text>
                       </view> 
                    </view>
                </view>
            </view>
        </view>
        <view class="w-full footer">
            
            <view class="bg-[#fff] py-[var(--top-m)] px-[var(--sidebar-m)] flex justify-between items-center fixed bottom-0 left-0 right-0 box-border" v-if="selectData.isShow">
                <view class="mr-[20rpx] flex items-center">
                    <text class="iconfont text-color text-[34rpx] mr-[20rpx] w-[34rpx] h-[34rpx] rounded-[17rpx] overflow-hidden flex-shrink-0" :class="{ 'iconxuanze1 text-primary': selectData.skuCheckAll,'bg-[#F5F5F5]': !selectData.skuCheckAll }" @click="skuHandleCheckAllChange()"></text>
                    <text>全选</text>
                </view>
                <view class="flex items-center flex-1">
                    <button class="btn flex-1 !leading-[76rpx] mr-[20rpx] !text-[var(--primary-color)] border-[2rpx] border-solid border-[var(--primary-color)] rounded-[16rpx] bg-transparent box-border" hover-class="none" @click="handleBatch">设置价格库存</button>
                    <button class="btn flex-1 border-[0] rounded-[16rpx] primary-btn-bg !text-[#fff]" shape="circle" hover-class="none" @click="selectData.isShow = false">完成设置</button>
                </view>
            </view>
            <view class="bg-[#fff] py-[var(--top-m)] px-[var(--sidebar-m)] footer w-full fixed bottom-0 left-0 right-0 box-border" v-else>
                <button hover-class="none"  class="primary-btn-bg !text-[#fff] h-[80rpx] leading-[80rpx] rounded-[16rpx] text-[26rpx] font-500" @click="handlesave"  :loading="operateLoading">保存</button>
            </view>
        </view>
        <!-- 规格项 -->
        <u-popup :show="showSpecPopup" @close="showSpecPopup = false" :closeOnClickOverlay="false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">规格类型</view>
                <view class="px-[30rpx]">
                    <view class="search-input h-[80rpx] rounded-[20rpx]">
                        <input class="input" maxlength="50" type="text" v-model="goodsSpecInput"  placeholder="请输入" placeholderClass="text-[var(--text-color-light9)] text-[24rpx]">
                        <text v-if="goodsSpecInput" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="goodsSpecInput=''"></text>
                    </view>
                </view>
                <view class="btn-wrap flex justify-between items-center !pt-[30rpx]">
                    <button class="btn flex-1 !leading-[76rpx] mr-[20rpx] !text-[var(--primary-color)] border-[2rpx] border-solid border-[var(--primary-color)] bg-transparent box-border" hover-class="none" @click="cancel">取消</button>
                    <button class="btn flex-1 border-[0] primary-btn-bg" shape="circle" hover-class="none" @click="saveSpec">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 批量设置 -->
        <u-popup :show="showBatchPopup" @close="showBatchPopup = false" :closeOnClickOverlay="false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">批量设置</view>
                <scroll-view scroll-y="true" class="h-[500rpx] px-[30rpx] box-border">
                    <u-form labelPosition="left" :model="batchOperation" errorType='toast' labelWidth="160rpx" ref="formRef">
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="价格" prop="prcie" :border-bottom="false">
                                <u-input v-model="batchOperation.price" border="none" maxlength="40" placeholder="请输入价格"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="库存" prop="stock" :border-bottom="false">
                                <u-input v-model="batchOperation.stock" border="none" maxlength="40" placeholder="请输入库存"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="划线价" prop="market_price" :border-bottom="false">
                                <u-input v-model="batchOperation.market_price" border="none" maxlength="40" placeholder="请输入划线价"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="成本价" prop="cost_price" :border-bottom="false">
                                <u-input v-model="batchOperation.cost_price" border="none" maxlength="40" placeholder="请输入成本价"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]" v-if="goodsType == 'real'">
                            <u-form-item label="重量" prop="weight" :border-bottom="false">
                                <u-input v-model="batchOperation.weight" border="none" maxlength="40" placeholder="请输入重量"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]" v-if="goodsType == 'real'">
                            <u-form-item label="体积" prop="weight" :border-bottom="false">
                                <u-input v-model="batchOperation.weight" border="none" maxlength="40" placeholder="请输入体积"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="商品编码" prop="sku_no" :border-bottom="false">
                                <u-input v-model="batchOperation.sku_no" border="none" maxlength="40" placeholder="请输入商品编码"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                    </u-form>
                </scroll-view>
                <view class="btn-wrap flex justify-between items-center !pt-[30rpx]">
                    <button class="btn flex-1 !leading-[76rpx] mr-[20rpx] !text-[var(--primary-color)] border-[2rpx] border-solid border-[var(--primary-color)]  bg-transparent box-border" hover-class="none" @click="showBatchPopup = false">取消</button>
                    <button class="btn flex-1 border-[0]  primary-btn-bg" shape="circle" hover-class="none" @click="saveBatch">确定</button>
                </view>
            </view>
        </u-popup>
        <!-- 单项设置 -->
         <u-popup :show="showSinglePopup" @close="showSinglePopup = false" :closeOnClickOverlay="false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">价格库存</view>
                <scroll-view scroll-y="true" class="h-[500rpx] px-[30rpx] box-border">
                    <u-form labelPosition="left" :model="singleOperation" errorType='toast' labelWidth="160rpx" ref="formRef">
                        <view class="flex-center mb-[30rpx]">
                            <upload-img  v-model="singleOperation.sku_image" :max-count="1" :multiple="true"  />
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="价格" prop="prcie" :border-bottom="false" required>
                                <u-input v-model="singleOperation.price" border="none" maxlength="40" placeholder="请输入价格"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="库存" prop="stock" :border-bottom="false" required>
                                <u-input v-model="singleOperation.stock" border="none" maxlength="40" placeholder="请输入库存"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="划线价" prop="market_price" :border-bottom="false">
                                <u-input v-model="singleOperation.market_price" border="none" maxlength="40" placeholder="请输入划线价"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="成本价" prop="cost_price" :border-bottom="false">
                                <u-input v-model="singleOperation.cost_price" border="none" maxlength="40" placeholder="请输入成本价"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]" v-if="goodsType == 'real'">
                            <u-form-item label="重量" prop="weight" :border-bottom="false">
                                <u-input v-model="singleOperation.weight" border="none" maxlength="40" placeholder="请输入重量"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]" v-if="goodsType == 'real'">
                            <u-form-item label="体积" prop="weight" :border-bottom="false">
                                <u-input v-model="singleOperation.weight" border="none" maxlength="40" placeholder="请输入体积"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                        <view class="h-[88rpx] flex w-full items-center px-[30rpx] rounded-[var(--goods-rounded-mid)] box-border border-solid border-[#aaa] border-[1rpx] mb-[30rpx]">
                            <u-form-item label="商品编码" prop="sku_no" :border-bottom="false">
                                <u-input v-model="singleOperation.sku_no" border="none" maxlength="40" placeholder="请输入商品编码"  class="!bg-transparent"  fontSize="26rpx" placeholderClass="!text-[var(--text-color-light9)] text-[26rpx]" />
                            </u-form-item>
                        </view>
                    </u-form>
                </scroll-view>
                <view class="btn-wrap flex justify-between items-center !pt-[30rpx]">
                    <button class="btn flex-1 !leading-[76rpx] mr-[20rpx] !text-[var(--primary-color)] border-[2rpx] border-solid border-[var(--primary-color)]  bg-transparent box-border" hover-class="none" @click="showSinglePopup = false">取消</button>
                    <button class="btn flex-1 border-[0] primary-btn-bg" shape="circle" hover-class="none" @click="saveSingle">确定</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref, computed, reactive } from 'vue'
import { debounce, deepClone, img, moneyFormat, redirect } from '@/utils/common'
import { onLoad } from '@dcloudio/uni-app'

const operateLoading = ref(false)
const goodsSpecInput = ref('')
const showSpecPopup = ref(false) // 规格项弹窗
const showBatchPopup = ref(false) // 批量设置弹窗
const curType = ref('')
const curIndex = ref(-1)
const activeGoodsCount: any = ref(0)
const goodsType = ref('')
const selectData: any = reactive({
    isShow: false, // 是否显示
    skuCheckAll :false,// 是否全选
    skuCheckedCities: [],// 选中的规格
}) 
const goodsSpecFormat: any = reactive([]) // 规格项/规格值
const goodsSkuData: any = reactive({}) // 商品SKU规格数据

onLoad((option: any) => {
    activeGoodsCount.value = option.active_goods_count || 0; // 活动商品数量
    goodsType.value = option.goods_type || ''; // 商品规格类型
    // 规格项
	let goodsSpec = uni.getStorageSync('editGoodsSpecFormat') ? JSON.parse(uni.getStorageSync('editGoodsSpecFormat')) : [];
    
    if(goodsSpec.length){
        goodsSpec.forEach((item: any) => {
            item.values.push({
                id:generateRandom(),
                spec_value_name: ''
            })
        })
    }
     goodsSpecFormat.splice(0, goodsSpecFormat.length, ...goodsSpec);
    // 多规格数据
    let skuData =  uni.getStorageSync('editGoodsSkuData') ? JSON.parse(uni.getStorageSync('editGoodsSkuData')) : {};
    for (let key in goodsSkuData){
        delete goodsSkuData[key];
    }
    Object.assign(goodsSkuData, skuData)
})

const isDisabledPrice = () => {
    if (activeGoodsCount.value > 0) {
        return true;
    }
    return false;
}

// 生成随机数
const generateRandom = (len: number = 5) => {
    return Number(Math.random().toString().substr(3, len) + Date.now()).toString(36)
}
const addSpec = () => {
    // 商品正在参与营销活动，禁止操作
    if (isDisabledPrice()) {
        uni.showToast({ title: '商品正在参与营销活动，禁止操作', icon: 'none' })
        return
    }
    if (goodsSpecFormat.length > 4) {
        uni.showToast({ title: '最多添加5个规格类型', icon: 'none' })
        return
    }
    showSpecPopup.value = true
    goodsSpecInput.value = ''

}
const saveSpec = () => {
    if(!goodsSpecInput.value){
        uni.showToast({ title: '请输入规格类型', icon: 'none' })
        return
    }
    if (curType.value === 'edit') {
        goodsSpecFormat[curIndex.value].spec_name = goodsSpecInput.value
        goodsSpecInput.value = ''
        curType.value = ''
        curIndex.value = -1
        showSpecPopup.value = false
        return
    }
    goodsSpecFormat.push({
        id: generateRandom(),
        spec_name: goodsSpecInput.value,
        values: [
            {
                id: generateRandom(),
                spec_value_name: ''
            }
        ]
    })
    showSpecPopup.value = false
    goodsSpecInput.value = ''
    
}

const cancel = () => {
    showSpecPopup.value = false
}

// 修改规格项
const updateSpec = (index: any,type: string) => {
    curType.value = type
    curIndex.value = index
    goodsSpecInput.value = goodsSpecFormat[index].spec_name
    showSpecPopup.value = true
}

// 删除规格项
const deleteSpec = (index: any) => {
    if (isDisabledPrice()) {
        uni.showToast({ title: '商品正在参与营销活动，禁止操作', icon: 'none' })
        return
    }
    goodsSpecFormat.splice(index, 1)
    // 渲染商品规格数据、表格、统计库存变化
    refreshGoodsSkuData()
}

// 添加规格值
const addSpecValue = (index: any, data: any) => {
    // 商品正在参与营销活动，禁止操作
    if (isDisabledPrice()) {
        uni.showToast({ title: '商品正在参与营销活动，禁止操作', icon: 'none' })
        return
    }
    let count = data.values.filter((item: any) => item.spec_value_name == '').length;
    if(!count){
        goodsSpecFormat[index].values.push({
            id: generateRandom(),
            spec_value_name: ''
        })
        specValueNameInputListener()
    }
    
}
// 删除规格值
const deleteSpecValue = (index: number, specIndex: number) => {
    // 商品正在参与营销活动，禁止操作
    if (isDisabledPrice()) {
        uni.showToast({ title: '商品正在参与营销活动，禁止操作', icon: 'none' })
        return
    }
    goodsSpecFormat[index].values.splice(specIndex, 1)
    // 渲染商品规格数据、表格、统计库存变化
    refreshGoodsSkuData()
}

// 监听规格值变化
const specValueNameInputListener = debounce((value) => {
    // 渲染商品规格数据、表格
    refreshGoodsSkuData()
})
// 追加刷新商品sku数据
const appendRefreshGoodsSkuData = reactive<any>({
    // 重量
    weight: {
        value: '',
        regExp: 'special',
        message: '重量(kg)格式输入错误'
    },
    // 体积
    volume: {
        value: '',
        regExp: 'special',
        message: '体积(m³)格式输入错误'
    }
})

// 刷新商品规格数据
const refreshGoodsSkuData = () => {
    const arr = goodsSpecFormat
    const tempGoodsSkuData = deepClone(goodsSkuData)// 记录原始数据，后续用作对比
    let skuData: any = {}
    let tempIndex = 0;
    for (const spec of arr) {
        let item_prop_arr: any = {}
        if (Object.keys(skuData).length > 0) {
            for (const ele_2 in skuData) {
                for (let ele_3 of spec.values) {
                    let sku_spec = deepClone(skuData[ele_2].sku_spec)// 防止对象引用
                    if(ele_3.spec_value_name){
                        sku_spec.push(ele_3)
                        item_prop_arr['sku_' + tempIndex] = {
                            spec_name: `${skuData[ele_2].spec_name} ${ele_3.spec_value_name}`,
                            spec_name_show: `${skuData[ele_2].spec_name}/${ele_3.spec_value_name}`,
                            sku_spec,
                            sku_image: '',
                            price: '',
                            market_price: '',
                            cost_price: '',
                            stock: '',
                            sku_no: '',
                            is_default: 0,
                            is_sell: 0,
                            checked: false,
                        }
                        if(goodsType.value == 'real'){
                            for (let key in appendRefreshGoodsSkuData) {
                                item_prop_arr['sku_' + tempIndex][key] = appendRefreshGoodsSkuData[key].value;
                            }
                        }
                    
                        tempIndex++;
                    }
                    
                }
            }
        } else {
            for (let ele_1 of spec.values) {
                let spec_name = ele_1.spec_value_name
                if(spec_name){
                    item_prop_arr['sku_' + tempIndex] = {
                        spec_name: spec_name,
                        spec_name_show: spec_name,
                        sku_spec: [ele_1],
                        sku_image: '',
                        price: '',
                        market_price: '',
                        cost_price: '',
                        stock: '',
                        sku_no: '',
                        is_default: 0,
                        is_sell: 0,
                        checked: false,
                    }
                    if(goodsType.value == 'real'){
                        for (let key in appendRefreshGoodsSkuData) {
                            item_prop_arr['sku_' + tempIndex][key] = appendRefreshGoodsSkuData[key].value;
                        }
                    }
                    tempIndex++;
                }
            }
        }

        skuData = Object.keys(item_prop_arr).length > 0 ? item_prop_arr : skuData
    }

    // 比对已存在的规格项/值，并且赋值
    for (const tempKey in tempGoodsSkuData) {
        for (const key in skuData) {
            const count = matchSkuSpecCount(tempGoodsSkuData[tempKey].sku_spec, skuData[key].sku_spec)
            if (count === skuData[key].sku_spec.length) {
                // 匹配成功后，要同步最新的规格项名称、规格值集合
                const specName = skuData[key].spec_name
                const skuSpec = skuData[key].sku_spec
                Object.assign(skuData[key], tempGoodsSkuData[tempKey])
                skuData[key].spec_name = specName
                skuData[key].sku_spec = skuSpec
                break
            }
        }
    }

    for (const item in goodsSkuData) {
        delete goodsSkuData[item]
    }

    let firstSpec = ''

    for (const key in skuData) {
        if (firstSpec == '') {
            firstSpec = key
            skuData[key].is_default = 1
        } else {
            skuData[key].is_default = 0
        }
        skuData[key].is_sell = 1
        goodsSkuData[key] = skuData[key]
    }
    // console.log(goodsSkuData)
    selectData.skuCheckAll = false;// 是否全选
    selectData.skuCheckedCities = []// 选中的规格
}
// 匹配规格值
const matchSkuSpecCount = (oVal: any, nVal: any) => {
    let count = 0// 匹配次数，与规格值相等时为匹配成功
    for (let i = 0; i < oVal.length; i++) {
        for (let j = 0; j < nVal.length; j++) {
            if (oVal[i].id === nVal[j].id) {
                count++
                break
            }
        }
    }
    return count
}
// 规格全选
const skuHandleCheckAllChange = () => {
    selectData.skuCheckAll = !selectData.skuCheckAll
    if(selectData.skuCheckAll){
        selectData.skuCheckedCities = Object.keys(goodsSkuData)
        for (const key in goodsSkuData) {
            goodsSkuData[key].checked = true
        }
    } else {
        for (const key in goodsSkuData) {
            goodsSkuData[key].checked = false
        }
        selectData.skuCheckedCities = []
    }
}
//单个规格选中
const handleCheckedCitiesChange = (item: any) => {
    item.checked = !item.checked
    selectData.skuCheckedCities = Object.keys(goodsSkuData).filter(key => goodsSkuData[key].checked)
    const checkedCount = selectData.skuCheckedCities.length
    selectData.skuCheckAll = checkedCount === Object.keys(goodsSkuData).length
}

// 批量设置规格

const batchOperation: any = reactive({
    spec: '', // 所选规格id，空为全部
    price: '', // 销售价
    market_price: '', // 划线价
    cost_price: '', // 成本价
    min_price: '', // 最低价
    max_price: '', // 最高价
    stock: '', // 库存
    sku_no: '' // 商品编码
})

// 正则表达式
const regExp: any = {
    required: /[\S]+/,
    number: /^\d{0,10}$/,
    digit: /^\d{0,10}(.?\d{0,2})$/,
    special: /^\d{0,10}(.?\d{0,3})$/
}
const handleBatch = () => {
    if(selectData.skuCheckedCities.length === 0){
        uni.showToast({ title: '请选择要设置的规格', icon: 'none' })
        return
    }
    showBatchPopup.value = true
}
// 批量设置确认
const saveBatch = () => {
    // 验证输入内容
    if (batchOperation.price && (isNaN(batchOperation.price) || !regExp.digit.test(batchOperation.price))) {
        uni.showToast({ title: '销售价格式输入错误', icon: 'none' })
        return
    }
    if (batchOperation.market_price && (isNaN(batchOperation.market_price) || !regExp.digit.test(batchOperation.market_price))) {
        uni.showToast({ title: '划线价格式输入错误', icon: 'none' })
        return
    }
    if (batchOperation.cost_price && (isNaN(batchOperation.cost_price) || !regExp.digit.test(batchOperation.cost_price))) {
        uni.showToast({ title: '成本价格式输入错误', icon: 'none' })
        return
    }
    if (batchOperation.stock && (isNaN(batchOperation.stock) || !regExp.number.test(batchOperation.stock))) {
        uni.showToast({ title: '库存格式输入错误', icon: 'none' })
        return
    }

    if(goodsType.value == 'real'){
        for (let field in appendRefreshGoodsSkuData) {
            let reg = regExp[appendRefreshGoodsSkuData[field].regExp]
            let message = appendRefreshGoodsSkuData[field].message

            if (batchOperation[field] && (isNaN(batchOperation[field]) || !reg.test(batchOperation[field]))) {
                uni.showToast({ title: message, icon: 'none' })
                return
            }

        }
    }
    // 设置全部规格
    selectData.skuCheckedCities.forEach((key: any) => {
        if (batchOperation.price) goodsSkuData[key].price = batchOperation.price
        if (batchOperation.market_price) goodsSkuData[key].market_price = batchOperation.market_price
        if (batchOperation.cost_price) goodsSkuData[key].cost_price = batchOperation.cost_price
        if (batchOperation.min_price) goodsSkuData[key].min_price = batchOperation.min_price
        if (batchOperation.max_price) goodsSkuData[key].max_price = batchOperation.max_price
        if (batchOperation.stock) goodsSkuData[key].stock = batchOperation.stock

        for (let field in appendRefreshGoodsSkuData) {
            if (batchOperation[field]) goodsSkuData[key][field] = batchOperation[field]
        }

        if (batchOperation.sku_no) goodsSkuData[key].sku_no = batchOperation.sku_no
    })

    // 保存完清空
    batchOperation.price = ''
    batchOperation.market_price = ''
    batchOperation.cost_price = ''
    batchOperation.min_price = ''
    batchOperation.max_price = ''
    batchOperation.stock = ''
    batchOperation.sku_no = ''

    if(goodsType.value == 'real'){
        for (let field in appendRefreshGoodsSkuData) {
            batchOperation[field] = '';
        }
    }
    showBatchPopup.value = false
}

// 单个设置
const showSinglePopup = ref(false)
const singleOperation: any = reactive({
    sku_image: '', // 图片
    spec: '', // 所选规格id，空为全部
    price: '', // 销售价
    market_price: '', // 划线价
    cost_price: '', // 成本价
    min_price: '', // 最低价
    max_price: '', // 最高价
    stock: '', // 库存
    sku_no: '' // 商品编码
})
const curSingleKey: any = ref('')
const handleSingle = (item: any,key: any) => {
    if(selectData.isShow) return;
    curSingleKey.value = key
    showSinglePopup.value = true
    singleOperation.sku_image = item.sku_image
    singleOperation.price = item.price
    singleOperation.market_price = item.market_price
    singleOperation.cost_price = item.cost_price
    singleOperation.min_price = item.min_price
    singleOperation.max_price = item.max_price
    singleOperation.stock = item.stock
    singleOperation.sku_no = item.sku_no
}
const saveSingle = () => {
    // 验证输入内容
    if (singleOperation.price && (isNaN(singleOperation.price) || !regExp.digit.test(singleOperation.price))) {
        uni.showToast({ title: '销售价格式输入错误', icon: 'none' })
        return
    }
    if (singleOperation.market_price && (isNaN(singleOperation.market_price) || !regExp.digit.test(singleOperation.market_price))) {
        uni.showToast({ title: '划线价格式输入错误', icon: 'none' })
        return
    }
    if (singleOperation.cost_price && (isNaN(singleOperation.cost_price) || !regExp.digit.test(singleOperation.cost_price))) {
        uni.showToast({ title: '成本价格式输入错误', icon: 'none' })
        return
    }
    if (singleOperation.stock && (isNaN(singleOperation.stock) || !regExp.number.test(singleOperation.stock))) {
        uni.showToast({ title: '库存格式输入错误', icon: 'none' })
        return
    }

    if(goodsType.value == 'real'){
        for (let field in appendRefreshGoodsSkuData) {
            let reg = regExp[appendRefreshGoodsSkuData[field].regExp]
            let message = appendRefreshGoodsSkuData[field].message

            if (singleOperation[field] && (isNaN(singleOperation[field]) || !reg.test(singleOperation[field]))) {
                uni.showToast({ title: message, icon: 'none' })
                return
            }

        }
    }
    if (singleOperation.sku_image) goodsSkuData[curSingleKey.value].sku_image = singleOperation.sku_image
    if (singleOperation.price) goodsSkuData[curSingleKey.value].price = singleOperation.price
    if (singleOperation.market_price) goodsSkuData[curSingleKey.value].market_price = singleOperation.market_price
    if (singleOperation.cost_price) goodsSkuData[curSingleKey.value].cost_price = singleOperation.cost_price
    if (singleOperation.min_price) goodsSkuData[curSingleKey.value].min_price = singleOperation.min_price
    if (singleOperation.max_price) goodsSkuData[curSingleKey.value].max_price = singleOperation.max_price
    if (singleOperation.stock) goodsSkuData[curSingleKey.value].stock = singleOperation.stock

    for (let field in appendRefreshGoodsSkuData) {
        if (singleOperation[field]) goodsSkuData[curSingleKey.value][field] = singleOperation[field]
    }

    if (singleOperation.sku_no) goodsSkuData[curSingleKey.value].sku_no = singleOperation.sku_no

    // 保存完清空
    singleOperation.sku_image = ''
    singleOperation.price = ''
    singleOperation.market_price = ''
    singleOperation.cost_price = ''
    singleOperation.min_price = ''
    singleOperation.max_price = ''
    singleOperation.stock = ''
    singleOperation.sku_no = ''

    if(goodsType.value == 'real'){
        for (let field in appendRefreshGoodsSkuData) {
            singleOperation[field] = '';
        }
    }
    showSinglePopup.value = false
}
const handlesave = () => {
    verify(() => {
        uni.setStorageSync('editGoodsSkuData', JSON.stringify(goodsSkuData));
        uni.setStorageSync('editGoodsSpecFormat', JSON.stringify(goodsSpecFormat));
        uni.navigateBack({
            delta: 1
        });
    })
}

const verify = (callback: any) => {
    let specVerify = true
    let repeatSpecNameArr: any = [];
    let repeatSpecValueNameArr: any = [];
    for (let i = 0; i < goodsSpecFormat.length; i++) {
        const spec = goodsSpecFormat[i]
        if (repeatSpecNameArr.indexOf(spec.spec_name) > -1) {
            specVerify = false
            uni.showToast({ title: '规格项不能重复', icon: 'none' })
            break
        } else {
            repeatSpecNameArr.push(spec.spec_name);
        }
        if(spec.values.length){
            let specData = spec.values.filter(item => item.spec_value_name != '')
            for (let v = 0; v < specData.length; v++) {
                const value = specData[v]

                if (repeatSpecValueNameArr.indexOf(value.spec_value_name) > -1) {
                    specVerify = false
                    uni.showToast({ title: '规格值不能重复', icon: 'none' })
                    break
                } else {
                    repeatSpecValueNameArr.push(value.spec_value_name);
                }
            }
        }else{
            specVerify = false
            uni.showToast({ title: '规格值不能为空', icon: 'none' })
        }
        

        if (!specVerify) break
    }

    if (!specVerify) {
        return
    }

    for (const k in goodsSkuData) {
        if (goodsSkuData[k].price == 0) {
            uni.showToast({ title: '价格不能为空', icon: 'none' })
            return
        } 
        if (goodsSkuData[k].stock == 0) {
            uni.showToast({ title: '库存不能为空', icon: 'none' })
            return
        }
        
    }

    let isHasDefaultSpec = false; // 是否存在默认规格
    for (const k in goodsSkuData) {
        if (goodsSkuData[k].is_default) {
            isHasDefaultSpec = true;
        }
    }

    if (!isHasDefaultSpec) {
        uni.showToast({ title: '商品缺少默认规格', icon: 'none' })
        return
    }

    let isHasSellSpec = false; // 是否存在在售规格商品
    for (const k in goodsSkuData) {
        if (goodsSkuData[k].is_sell) {
            isHasSellSpec = true;
        }
    }

    if (!isHasSellSpec) {
        uni.showToast({ title: '商品至少有一个规格售卖', icon: 'none' })
        return
    }
    callback && callback()
}

</script>

<style lang="scss" scoped>
:deep(.u-form-item__body__left__content__label) {
    font-size: 28rpx !important;
}
.footer {
    height: calc(80rpx + var(--top-m) + var(--top-m) + constant(safe-area-inset-bottom)) !important;
    height: calc(80rpx + var(--top-m) + var(--top-m) + env(safe-area-inset-bottom)) !important;
}
.btn{
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 260rpx;
    height: 66rpx;
    font-size: 26rpx;
    color: var(--primary-color);
    border: 2rpx solid var(--primary-color);
    border-radius: 16rpx;
}
</style>