<template>
    <view>
        <u-popup ref="popupRef"  :show="show" mode="center" @close="show = false" :round="30" zIndex="99999" :safeAreaInsetBottom="false">
            <view class="bg-[#fff] flex flex-col justify-between w-[610rpx] h-[306rpx] rounded-[30rpx]  box-border px-[40rpx] pt-[70rpx] pb-[40rpx] relative overflow-hidden">
                <view class="flex-center mb-[80rpx] text-[#333]">{{ title }}</view>
                <view class="flex-between-center">
                    <view class="w-[250rpx] h-[66rpx] rounded-full bg-[#eee] flex-center text-[26rpx] font-500 text-[#333]" @click="show = false">取消</view>
                    <view class="w-[250rpx] h-[66rpx] rounded-full primary-btn-bg flex-center text-[26rpx] font-500 text-[#fff]" @click="save">确定</view>
                </view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref} from 'vue';

const prop = defineProps({
    title: {
        type: String,
        default: ''
    },
})

const show = ref(false)

const emit = defineEmits(['confirm'])

const save = () => {
    emit('confirm')
    show.value = false
}
const open = () => {
    show.value = true
}

defineExpose({
    open
})
</script>

<style scoped>

</style>
