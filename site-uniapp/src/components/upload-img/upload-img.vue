<template>
    <view class="flex flex-wrap">
        <template v-if="maxCount == 1">
            <view class="relative" v-if="images.data.length">
                <up-image  width="110rpx" height="110rpx" radius="var(--goods-rounded-big)" :src="img(images.data[0] || '')" mode="aspectFill" @click="imgListPreview(images.data[0])">
                    <template #error>
                        <u-icon name="photo" color="var(--text-color-light9)" size="50"></u-icon>
                    </template>
                </up-image>
                <view class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]" @click.stop="deleteImg()">
                    <text class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
                </view>
            </view>
        </template>
        <template v-else>
            <view v-for="(item, index) in images.data" :class="getImageItemClass(index)">
                <up-image  width="110rpx" height="110rpx" radius="var(--goods-rounded-big)" :src="img(item || '')" mode="aspectFill" @click="imgListPreview(item)">
                    <template #error>
                        <u-icon name="photo" color="var(--text-color-light9)" size="50"></u-icon>
                    </template>
                </up-image>
                <view class="absolute top-0 right-[0] bg-[#373737] flex justify-end h-[28rpx] w-[28rpx] rounded-bl-[40rpx]" @click.stop="deleteImg(index)">
                    <text class="nc-iconfont nc-icon-guanbiV6xx !text-[20rpx] mt-[2rpx] mr-[2rpx] text-[#fff]"></text>
                </view>
            </view>
        </template>
        <view class="w-[110rpx] h-[110rpx]" v-show="images.data.length < maxCount">
            <u-upload @afterRead="afterRead" :maxCount="maxCount" :multiple="prop.multiple">
                <view class="flex items-center justify-center w-[110rpx] h-[110rpx] border-[2rpx] border-dashed border-[#ddd] text-center text-[var(--text-color-light9)] rounded-[var(--goods-rounded-big)]">
                    <view>
                        <view class="nc-iconfont nc-icon-xiangjiV6xx text-[50rpx]"></view>
                        <view class="text-[24rpx] mt-[12rpx]">{{ images.data.length }}/{{ maxCount }}</view>
                    </view>
                </view>
            </u-upload>
        </view>
    </view>
</template>
<script lang="ts" setup>
import { reactive,computed, toRaw, watch } from 'vue';
import { img } from '@/utils/common';
import { uploadImage } from '@/app/api/system'

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
const maxCount = computed(() => {
    return prop.maxCount
})

const emit = defineEmits(['update:modelValue'])

const value: any = computed({
    get() {
        return prop.modelValue
    },
    set(value) {
        emit('update:modelValue', value)
    }
})

const images: Record<string, any> = reactive({
    data: []
})

const getImageItemClass = (index: number) => {
    const base = 'mb-[20rpx] relative'
    return (index + 1) % 5 != 0 ? `${ base } mr-[18rpx]` : base
}

let previewImageList: string[] = reactive([])
const setValue = () => {
    value.value = toRaw(images.data).toString()
    previewImageList = toRaw(images.data).map((url: string) => { return img(url) })
}

watch(() => value.value, () => {
    images.data = String(value.value || '')
        .split(',')
        .map((item: string) => item.trim()) // 去掉每个 url 的首尾空格，避免 " https://..." 被当相对路径导致 404
        .filter((item: string) => item)
    setValue()
}, { immediate: true })


const afterRead = (event: any) => {
    if (prop.multiple) {
        event.file.forEach((file: any) => {
            upload({ file })
        })
    } else {
        upload(event)
    }
}

const upload = (event: any) => {
    if (images.data.length >= maxCount.value) {
        uni.showToast({ title: `最多允许上传${ maxCount.value }张图片`, icon: 'none' })
        return false
    }

    uploadImage({
        filePath: event.file.url,
        name: 'file'
    }).then((res: any) => {
        if(images.data.length < maxCount.value){
            images.data.push(res.data.url)
        }
        setValue()
    }).catch(() => {
    })
}

const deleteImg = (index: number = 0) => {
    images.data.splice(index, 1)
    setValue()
}

//预览图片
const imgListPreview = (item: any) => {
    if (item === '') return false
    uni.previewImage({
        indicator: "number",
        loop: true,
        urls: previewImageList
    })
}
</script>
