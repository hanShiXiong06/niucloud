<template>
    <view @touchmove.prevent.stop>
        <u-popup :show="show" @close="show = false">
            <view class="popup-common" @touchmove.prevent.stop>
                <view class="title">社区分类</view>
                <scroll-view scroll-y="true" class="h-[450rpx] px-[30rpx] box-border">
                    <template v-if="categoryList.length">
                        <u-radio-group v-model="categoryId" placement="column" iconPlacement="right">
                            <u-radio activeColor="var(--primary-color)" :labelSize="'30rpx'" labelColor="#333" :customStyle="{marginBottom: '34rpx'}" v-for="(item, index) in categoryList" :key="index" :label="item.category_name" :name="item.category_id"></u-radio>
                        </u-radio-group>
                    </template>
                    <view  class="empty-page-popup !mt-0" v-else>
                        <image class="img" :src="img('static/resource/images/system/empty.png')" model="aspectFit" />
                        <view class="desc">暂无分类</view>
                    </view>
                </scroll-view>
                <view class="btn-wrap" v-if="categoryList.length">
                    <button class="primary-btn-bg btn" @click="save">确定</button>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { img } from '@/utils/common';
import { getCategoryList } from '@/addon/sow_community/api/follow';

const show = ref(false)
const categoryId = ref(0)
const categoryName = ref('')
// 社区分类
const categoryList = ref<any>([])
const getCategoryListFn = (callback: any = null) =>{
    getCategoryList().then((res: any) =>{
        categoryList.value = res.data
    })
}
getCategoryListFn()

const emit = defineEmits(['confirm'])

const save = () => {
    categoryName.value = categoryList.value.filter((item: any) => item.category_id == categoryId.value)[0].category_name
    const data = {
        category_id: categoryId.value,
        category_name: categoryName.value
    }
    emit('confirm', data)
    show.value = false
}

const open = (id: any) => {
    categoryId.value = id
    show.value = true
}

defineExpose({
    open
})
</script>

<style scoped>

</style>
