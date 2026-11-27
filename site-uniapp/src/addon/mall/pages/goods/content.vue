<template >
    <view class="bg-[var(--page-bg-color)] min-h-[100vh] " :style="themeColor()">
        <ns-editor ref="edit" @editOk="editOk" placeholder="请输入商品详情..." verify="请输入商品详情" :html="goodsContent"></ns-editor>
    </view>
</template>

<script setup lang="ts">
import { onLoad } from '@dcloudio/uni-app';
import { ref } from 'vue'
import { redirect } from '@/utils/common';
import nsEditor from '@/components/ns-editor/ns-editor.vue'

const goodsContent = ref('')

onLoad(() => {
    goodsContent.value = uni.getStorageSync('editGoodsContent') || '';
})
const editOk = (res: any)  =>{
    uni.setStorageSync('editGoodsContent', res.html);
    if (getCurrentPages().length > 1) {
        uni.navigateBack({
            delta: 1
        });
    } else {
        redirect({ url: '/addon/mall/pages/goods/edit' });
    }
    
}
</script>

<style scoped>

</style>