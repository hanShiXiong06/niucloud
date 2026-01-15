<template>
    <view @touchmove.prevent.stop>
        <u-popup :show="goodsCategoryPopup" @close="closeFn" mode="bottom" zIndex="999999">
            <view @touchmove.prevent.stop class="popup-common">
                <view class="title">商品分类</view>
                <view class="flex" v-if="goodsCategoryOptions.length">
                    <scroll-view scroll-y="true" class="h-[50vh] tabs-box flex-shrink-0">
                        <view class="bg-[var(--temp-bg)] h-full">
                            <view class="tab-item" :class="{ 'tab-item-active': index == tabActive }" v-for="(item, index) in goodsCategoryOptions" :key="index" @click="firstLevelClick(index, item)">
								<view class="text-box text-left leading-[1.3] break-words px-[16rpx]">
									{{ item.category_name }}
								</view>
							</view>
                        </view>
                    </scroll-view>
                    <scroll-view scroll-y="true" class="h-[50vh] flex-1">
                        <view class="right-box" v-if="goodsCategoryOptions[tabActive]?.child_list">
                            <view class="right-item" v-for="(item, index) in goodsCategoryOptions[tabActive].child_list" :key="index" @click="handleClick(item)">
                                <text>{{ item.category_name }}</text>
                                <text  class="iconfont text-color text-[34rpx] mr-[20rpx] w-[34rpx] h-[34rpx] rounded-[17rpx]" :class="{ 'iconxuanze1 text-primary':item.checked,'bg-[#F5F5F5]':!item.checked}"></text>
                            </view>
                        </view>

                    </scroll-view>
                </view>
                <view class="empty-page" v-else>
                    <image class="img" :src="img('/static/resource/images/system/empty.png')" mode="aspectFill" />
                    <view class="desc">暂无商品分类</view>
                </view>
                <view class="btn-wrap">
                    <button class="primary-btn-bg btn" @click="addCategory">确定</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { img } from '@/utils/common';
import { getCategoryTree } from '@/app/api/base_site'



const tabActive = ref(0)
const goodsCategoryPopup = ref(false)
const goodsCategoryOptions = ref<any>([])


// 刷新商品分类
getCategoryTree().then((res: any) => {
    let data = res.data
    goodsCategoryOptions.value = data.map((item: any) => {
        item.checked = false
        if(item.child_list){
            item.child_list.forEach((subItem: any) => {
                subItem.checked = false
            })
        } else {
            item.child_list = []
            item.child_list.push({
                category_id: item.category_id,
                category_name: item.category_name,
                checked: false
            })
        }
        return item
        
    })
})

const firstLevelClick = (index: number, item: any) => {
    tabActive.value = index
}
const handleClick = (item: any) => {
    item.checked = !item.checked
}
const closeFn = () => {
    goodsCategoryPopup.value = false
}
const open = (data: any) => {
    if(data.length){
         data.forEach((item: any) => {
            goodsCategoryOptions.value.forEach((val: any) => {
                if(item == val.category_id){
                    val.checked = true
                }
                if(val.child_list){
                	val.child_list.forEach((subVal: any) => {
                        if(item == subVal.category_id){
                            subVal.checked = true
                        }
                    })
                }
            })
         })
    }
    goodsCategoryPopup.value = true
}

const emits = defineEmits(['confirm'])
const addCategory = () => {
    let arr: any = []
    goodsCategoryOptions.value.forEach((item: any) => {
        item.child_list.forEach((item: any) => {
            if(item.checked){
                arr.push(item)
            }
        })
    })
    let obj: any = {}
    obj.category_id = arr.map((item: any) => item.category_id)
    obj.category_name = arr.map((item: any) => item.category_name)
    emits('confirm', obj)
    goodsCategoryPopup.value = false
}

defineExpose({
    open
})
</script>

<style lang="scss" scoped>
.tabs-box {
	width: 168rpx;
	font-size: 26rpx;
}

.tabs-box .tab-item {
	min-height: 56rpx;
    padding: 20rpx 0;
    text-align: center;
    background-color: var(--temp-bg);
    display: flex;
    align-items: center;
    justify-content: center;
	font-size: 24rpx;
}

.tabs-box .tab-item-active {
	position: relative;
	color: var(--primary-color);
	background-color: #fff;
	&::before {
		display: inline-block;
		position: absolute;
		left: 0rpx;
		top: 50%;
		transform: translateY(-50%);
		content: '';
		width: 6rpx;
		height: 48rpx;
		background-color: var(--primary-color);
	}

	&::after {
		display: inline-block;
		position: absolute;
		left: 0rpx;
		top: 50%;
		transform: translateY(-50%);
		content: '';
		width: 6rpx;
		height: 48rpx;
		background-color: var(--primary-color);
	}
}

.scroll-height{
	height: 100%;
}
.right-box{
    padding: 0 16rpx;
    .right-item{
        height: 96rpx;
        box-sizing: border-box;
        display: flex;
        align-items: center;
        justify-content: space-between;
        border-bottom: 1rpx solid var(--temp-bg);
    }
}
.empty-page{
    padding-top: 40rpx;
    height:  500rpx;
}

</style>