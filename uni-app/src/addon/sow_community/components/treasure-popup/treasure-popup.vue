<template>
    <view @touchmove.prevent.stop>
        <u-popup  :show="show" @close="show = false"  mode="bottom" :round="10">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="py-[40rpx] flex-center">
                    <view class="text-[30rpx] text-[#333] leading-[42rpx]" :class="{'acive-tab': treasure.params.relate_type == item.value,'ml-[50rpx]': index}" v-for="(item,index) in treasureType" :key="index" @click="handleTab(item)">{{ item.name }}</view>
                </view>
                <view class="flex items-center justify-between px-[20rpx] mb-[30rpx]">
                    <view class="flex-1 search-input h-[66rpx]">
                        <text @click.stop="searchTreasureFn()" class="nc-iconfont nc-icon-sousuo-duanV6xx1 btn !text-[30rpx]"></text>
                        <input class="input" maxlength="50" type="text" v-model="treasure.params.keyword" placeholder="请输入关键字搜索" placeholderClass="text-[var(--text-color-light9)] text-[26rpx]" confirm-type="search" @confirm="searchTreasureFn()">
                        <text v-if="treasure.params.keyword" class="nc-iconfont nc-icon-cuohaoV6xx1 clear" @click="deleteFn()"></text>
                    </view>
                </view>
                <view class="mx-[var(--popup-sidebar-m)]">
                    <scroll-view scroll-y="true" class="h-[50vh]" @scrolltolower="handleLoadMore">
                        <template v-if="treasure.data.length">
                            <view class="flex p-[var(--pad-sidebar-m)] border-solid border-[2rpx] border-[#eee] rounded-[var(--rounded-big)] mb-[30rpx]"   v-for="(item, index) in treasure.data" :key="index" @click="changeOnlyTreasure(item)">
                                <view class="self-center w-[34rpx] mr-[20rpx] h-[60rpx] flex items-center">
                                    <text class=" iconfont text-color text-[34rpx] w-[34rpx] h-[34rpx] rounded-[17rpx]  overflow-hidden flex-shrink-0 border-[2rpx] border-solid border-transparent box-border" :class="{ 'iconxuanze1 text-primary':item.checked,'border-[#bbb]':!item.checked}">
                                    </text>
                                </view>
                                <view class="w-[140rpx] h-[140rpx] flex items-center justify-center rounded-[var(--goods-rounded-small)] overflow-hidden">
                                    <image v-if="item.treasure_image" class="w-[140rpx] h-[140rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" :src="img(item.treasure_image)" mode="aspectFill"></image>
                                    <image v-else class="w-[140rpx] h-[140rpx] rounded-[var(--goods-rounded-small)] overflow-hidden" :src="img('static/resource/images/diy/shop_default.jpg')" mode="aspectFill"></image>
                                </view>
                                <view class="flex flex-1 flex-col justify-between  ml-[20rpx]">
                                    <view class="w-[100%] flex flex-col items-baseline">
                                        <view class="text-[#333] text-[28rpx] max-h-[80rpx] leading-[40rpx] multi-hidden font-500">
                                        {{ item.treasure_name }}
                                        </view>
                                        <view class="box-border mt-[10rpx]  leading-[36rpx] using-hidden text-[var(--text-color-light9)] text-[26rpx]">
                                            {{ item.treasure_sub_name }}
                                        </view>
                                    </view>
                                    <view class="flex justify-between items-end self-end mt-[10rpx] w-[100%]">
                                        <view class="text-[var(--price-text-color)] price-font">
                                            <text class="text-[22rpx] font-500">￥</text>
                                            <text class="text-[36rpx] font-500">{{ parseFloat(item.treasure_price).toFixed(2).split('.')[0] }}</text>
                                            <text class="text-[22rpx] font-500">.{{ parseFloat(item.treasure_price).toFixed(2).split('.')[1] }}</text>
                                        </view>
                                    </view>
                                </view>
                            </view>
                        </template>
                        <view  class="empty-page-popup h-full mt-0" v-else>
                            <image class="img" :src="img('static/resource/images/system/empty.png')" model="aspectFit" />
                            <view class="desc">暂无宝贝</view>
                        </view>
                    </scroll-view>
                </view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="addTreasure">确定({{ num }}/5)</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import {  computed, reactive, ref } from 'vue'
import { deepClone, img } from '@/utils/common';
import { getTreasureType, getTreasureList } from '@/addon/sow_community/api/treasure';

const show = ref(false)
const treasureType = ref<any>([])
const treasure =reactive<any>({
    page: 1,
    limit: 10,
    data: [],
    selected: [],
    selectedData: [],
    selectImg: [],
    last_page: 1,
    params:{
        keyword: '',
        relate_type: ''
    }
})
const num = computed(() => {
    return treasure.selected.length
})

getTreasureType().then((res: any) =>{
    treasureType.value = []
    for(const key in res.data){
        let obj ={
            name: res.data[key],
            value: key
        }
        treasureType.value.push(obj)
    }
    treasure.params.relate_type = treasureType.value.length && treasureType.value[0].value
    getTreasureListFn()
})

const getTreasureListFn = (page: number = 1) =>{
    treasure.page = page
    if(treasure.page > treasure.last_page) return false
    getTreasureList({
        page: treasure.page,
        limit: treasure.limit,
        ...treasure.params
    }).then((res: any) =>{
        let newArr = res.data.data.map((item: any) => {
            item.checked = false
            return item
        })

        if (Number(page) === 1) {
            treasure.data = []; //如果是第一页需手动制空列表
		}
        if(res.data.last_page){
            treasure.last_page = res.data.last_page
        }else{
            treasure.last_page = 1
        }
        treasure.data =  treasure.data.concat(newArr);
        if(treasure.selectedData.length){
            treasure.data.forEach((item: any) => {
                treasure.selectedData.forEach((val: any) => {
                    if(item.treasure_id === val.treasure_id){
                        item.checked = true
                    }
                })
            })
        }
    })
}
// 分页请求
const handleLoadMore = () => {
    treasure.page++
    getTreasureListFn(treasure.page)
}

// 切换tab
const handleTab = (data: any) =>{
    treasure.params.relate_type = data.value
    treasure.page = 1
    getTreasureListFn(treasure.page)
}

const searchTreasureFn = () => {
    treasure.page = 1
    getTreasureListFn(treasure.page)
}
const deleteFn = () =>{
    treasure.params.keyword = ''
    treasure.page = 1
    getTreasureListFn(treasure.page)
}

// 选择宝贝
const changeOnlyTreasure = (data: any) =>{
    let index = treasure.selectedData.findIndex((item: any) => {
        return item.treasure_id === data.treasure_id
    })
    if(index !== -1){
        data.checked = !data.checked
        treasure.selectedData.splice(index, 1)
    }else{
        if(treasure.selectedData.length >= 5){
            uni.showToast({
                title: '最多选择5个宝贝',
                icon: 'none'
            })
            return false
        }
        data.checked = !data.checked
       let obj = treasure.data.filter((item: any) => {
            return item.checked
        })
        let curSelect: any = []
        if(treasure.selectedData.length){
            let selectId =  treasure.selectedData.map((item: any) =>{
                return item.treasure_id
            })
            obj.forEach((val: any) =>{
                if(selectId.indexOf(val.treasure_id) == -1){
                    curSelect.push(val)
                }
            })
           
        }else{
            curSelect = obj
        }
        treasure.selectedData = treasure.selectedData.concat(deepClone(curSelect))
    }
    treasure.selected = treasure.selectedData.map((item: any) => {
        return item.treasure_id
    })
    treasure.selectImg = treasure.selectedData.map((item: any) =>{
        return item.treasure_image
    })

}

const emits = defineEmits(['confirm'])
const addTreasure = () => {
    let obj = {
        treasure_id: treasure.selected,
        treasure_image: treasure.selectImg
    }
    emits('confirm',obj)
    show.value = false
}
const open = (data: any) => {
    treasure.selected = data
    treasure.selected.forEach((item: any) =>{
        treasure.data.forEach((val: any) =>{
            if(val.treasure_id === item){
                val.checked = true
            }
        })
    })
    show.value = true
}
defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.acive-tab{
    position: relative;
    font-weight: 500;
    color: var(--primary-color);
    &::after{
        content: '';
        width: 34rpx;
        height: 6rpx;
        background: var(--primary-color);
        border-radius: 4rpx;
        position: absolute;
        left: 50%;
        transform: translateX(-50%);
        bottom: -10rpx;
    }
}
</style>
<style>
.uni-picker-action.uni-picker-action-confirm{
    color: var(--primary-color) !important;
}
</style>
