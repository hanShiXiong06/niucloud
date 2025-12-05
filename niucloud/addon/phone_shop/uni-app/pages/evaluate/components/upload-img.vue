<template>
    <view class="flex flex-wrap">
        <view v-for="(item, index) in value"
            :class="['mb-[18rpx] relative', (index + 1) % 3 === 0 ? '' : 'mr-[18rpx]']">
            <u--image class="rounded-[10rpx] overflow-hidden" width="204rpx" height="204rpx" :src="img(item || '')"
                model="aspectFill">
                <template #error>
                    <u-icon name="photo" color="#999" size="50"></u-icon>
                </template>
            </u--image>
            <text
                class="nc-iconfont nc-icon-guanbiV6xx absolute top-0 right-[5rpx] text-[#fff] bg-[#888] rounded-bl-[16rpx]"
                @click.stop="deleteImg(index)"></text>
        </view>
        <view class="w-[204rpx] h-[204rpx]" v-show="value.length < maxCount">
            <u-upload @afterRead="afterRead" :maxCount="maxCount" :multiple="prop.multiple">
                <view
                    class="flex items-center justify-center w-[204rpx] h-[204rpx] border-[2rpx] border-dashed border-[#ebebec] text-center text-[#888]">
                    <view v-if="!isUploading">
                        <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx]"></view>
                        <view class="text-[24rpx] mt-[10rpx]">{{ value.length }}/{{ maxCount }}</view>
                    </view>
                    <view v-else class="upload-loading">
                        <u-loading-icon></u-loading-icon>
                        <view class="text-[24rpx] mt-[10rpx]">上传中...</view>
                    </view>
                </view>
            </u-upload>
        </view>

        <!-- #ifdef MP-WEIXIN -->
        <!-- 小程序隐私协议 -->
        <wx-privacy-popup ref="wxPrivacyPopupRef"></wx-privacy-popup>
        <!-- #endif -->
    </view>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system'

const isUploading = ref(false);
const uploadingCount = ref(0);

const prop = defineProps({
    modelValue: {
        type: String || Array,
    },
    maxCount: {
        type: Number,
        default: 9
    },
    multiple: {
        type: Boolean,
        default: false
    }
})

const emit = defineEmits(['update:modelValue'])

const value = computed({
    get() {
        return prop.modelValue
    },
    set(value) {
        emit('update:modelValue', value)
    }
})

const maxCount = computed(() => {
    return prop.maxCount
})

const afterRead = (event: any) => {
    if (prop.multiple) {
        uploadingCount.value = event.file.length;
        isUploading.value = true;
        event.file.forEach(file => {
            upload({ file })
        })
    } else {
        uploadingCount.value = 1;
        isUploading.value = true;
        upload(event)
    }
}

const upload = async (event: any) => {
    if (value.value?.length >= maxCount.value) {
        uni.showToast({ title: `最多允许上传${maxCount.value}张图片`, icon: 'none' })
        return false
    }

    try {
        const res = await uploadImage({
            filePath: event.file.url,
            name: 'file'
        });

        if (value.value?.length < maxCount.value) {
            value.value.push(res.data.url);
        }

        // 减少上传计数
        uploadingCount.value--;
        if (uploadingCount.value === 0) {
            isUploading.value = false;
            uni.showToast({
                title: '上传成功',
                icon: 'success',
                duration: 1500
            });
        }
    } catch (error) {
        uploadingCount.value--;
        if (uploadingCount.value === 0) {
            isUploading.value = false;
        }
        uni.showToast({
            title: '上传失败',
            icon: 'error',
            duration: 1500
        });
    }
}

const deleteImg = (index: number) => {
    value.value.splice(index, 1)
}
</script>

<style lang="scss" scoped>
.upload-loading {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;

    :deep(.u-loading-icon) {
        width: 50rpx;
        height: 50rpx;
    }
}
</style>
