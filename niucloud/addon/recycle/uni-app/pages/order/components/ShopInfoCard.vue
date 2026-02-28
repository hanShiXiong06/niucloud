<template>
  <view class="bg-white rounded-lg p-3 mt-3 border-l-4" style="border-color: #B8C8C2;">
    <view class="flex items-center justify-between mb-2">
      <view class="flex items-center gap-1">
        <up-icon name="bag" size="16" color="#657A6E"></up-icon>
        <text class="text-sm font-medium" style="color: #657A6E;">商家信息</text>
      </view>
      <view class="flex items-center gap-1 px-2 py-1 bg-blue-50 rounded text-xs text-blue" @click="handleCopy">
        <up-icon size="14" name="cut" color="#3b82f6" />
        <text>一键复制</text>
      </view>
    </view>

    <view v-if="shopInfo" class="space-y-2">
      <view class="flex items-center text-sm">
        <text class="text-gray-500 w-20">商家名称：</text>
        <text class="text-gray-900 flex-1">{{ shopInfo.name }}</text>
      </view>
      <view v-if="shopInfo.mobile" class="flex items-center text-sm">
        <text class="text-gray-500 w-20">联系电话：</text>
        <text class="text-gray-900 flex-1">{{ shopInfo.mobile }}</text>
      </view>
      <view class="flex items-center text-sm  ">
        <text class="text-gray-500 w-20">商家地址：</text>
        <text class="text-gray-900 flex-1 text-xs">{{ shopInfo.full_address || shopInfo.address }}</text>
      
       <svg  @click="handleOpenLocation" t="1769238211140" class="icon" viewBox="0 0 1024 1024" version="1.1" xmlns="http://www.w3.org/2000/svg" p-id="11744" width="20" height="20"><path d="M1004.567156 933.701818l-54.225454-176.64A116.363636 116.363636 0 0 0 838.865338 674.909091h-79.36a23.272727 23.272727 0 0 0 0 46.545454h79.36a69.818182 69.818182 0 0 1 66.792727 49.338182l54.225455 176.64a23.272727 23.272727 0 0 1-23.272727 30.021818H86.225338a23.272727 23.272727 0 0 1-23.272727-30.021818l54.225454-176.64A69.818182 69.818182 0 0 1 185.134429 721.454545h83.083636a23.272727 23.272727 0 0 0 0-46.545454H185.134429a116.363636 116.363636 0 0 0-111.243636 82.152727L19.432611 933.701818A69.818182 69.818182 0 0 0 86.225338 1024h851.549091a69.818182 69.818182 0 0 0 66.792727-90.298182z" fill="#0078FF" p-id="11745"></path><path d="M512.116247 791.272727c185.018182 0 325.818182-327.447273 325.818182-491.287272S691.316247 0 512.116247 0s-325.818182 134.516364-325.818182 299.985455S327.098065 791.272727 512.116247 791.272727z m-279.272727-491.287272C232.84352 160.116364 358.050793 46.545455 512.116247 46.545455s279.272727 113.570909 279.272728 253.44c0 75.869091-34.443636 188.276364-85.643637 279.272727C647.098065 684.683636 576.581702 744.727273 512.116247 744.727273s-134.981818-60.043636-193.629091-164.770909C267.054429 488.727273 232.84352 375.621818 232.84352 299.985455z" fill="#0078FF" p-id="11746"></path><path d="M512.116247 465.454545a139.636364 139.636364 0 1 0-139.636363-139.636363 139.636364 139.636364 0 0 0 139.636363 139.636363z m0-232.727272a93.090909 93.090909 0 1 1-93.090909 93.090909 93.090909 93.090909 0 0 1 93.090909-93.090909z" fill="#0078FF" p-id="11747"></path></svg></view>
    </view>
  </view>
</template>

<script setup lang="ts">
import type { ShopInfo } from '../../../types/order'

interface Props {
  shopInfo: ShopInfo
}

defineProps<Props>()

const emit = defineEmits<{
  copy: []
  'open-location': []
}>()

const handleCopy = () => {
  emit('copy')
}

const handleOpenLocation = () => {
  emit('open-location')
}
</script>

<style scoped lang="scss">
.space-y-2 > view:not(:last-child) {
  margin-bottom: 0.5rem;
}

.text-blue {
  color: #3b82f6;
}
</style>
