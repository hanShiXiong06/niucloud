<template>
    <view class="recycle-image-uploader">
        <view v-for="(item, index) in imageItems" :key="`${ item.path || item.url }-${ index }`" class="uploader-item">
            <image class="uploader-thumb" :src="item.url" mode="aspectFill" @click="previewImages(index)" />
            <view v-if="item.status === 'uploading'" class="uploader-mask">上传中</view>
            <view class="uploader-delete" @click.stop="removeImage(index)">
                <text class="nc-iconfont nc-icon-cuohaoV6xx1 text-[22rpx] text-[#fff]"></text>
            </view>
        </view>

        <view
            v-if="imageItems.length < maxCount"
            class="uploader-add"
            @click="handleChooseImage"
        >
            <text class="nc-iconfont nc-icon-xiangjiV6xx text-[34rpx] text-[#999]"></text>
            <text class="uploader-add__text">{{ addText }}</text>
        </view>

    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'
import { previewImages as openPreview } from '@/addon/hsx_recycle/utils/preview'

interface ImageItem {
    url: string
    path: string
    status: 'success' | 'uploading'
}

const props = withDefaults(defineProps<{
    modelValue?: string
    maxCount?: number
    multiple?: boolean
    addText?: string
    failText?: string
}>(), {
    modelValue: '',
    maxCount: 9,
    multiple: true,
    addText: '添加图片',
    failText: '图片上传失败'
})

const emit = defineEmits(['update:modelValue', 'change', 'uploading'])

const imageItems = ref<ImageItem[]>([])

const uploading = computed(() => imageItems.value.some(item => item.status === 'uploading'))
const remainCount = computed(() => Math.max(props.maxCount - imageItems.value.length, 1))

watch(() => props.modelValue, (value) => {
    const currentValue = toValue()
    if (String(value || '') === currentValue) return
    imageItems.value = String(value || '')
        .split(',')
        .filter(Boolean)
        .slice(0, props.maxCount)
        .map((path: string) => ({
            url: img(path),
            path,
            status: 'success'
        }))
}, { immediate: true })

watch(uploading, (value) => {
    emit('uploading', value)
})

const handleChooseImage = () => {
    if (imageItems.value.length >= props.maxCount) {
        uni.showToast({ title: `最多允许上传${ props.maxCount }张图片`, icon: 'none' })
        return
    }

    uni.chooseImage({
        count: props.multiple ? Math.min(remainCount.value, 9) : 1,
        sourceType: ['album', 'camera'],
        sizeType: ['compressed', 'original'],
        success: (res) => {
            const files = Array.isArray(res.tempFiles) ? res.tempFiles : []
            files.forEach((file: any) => {
                const filePath = file?.path || ''
                if (filePath) uploadOne(filePath)
            })
        },
        fail: (error: any) => {
            if (error?.errMsg && !String(error.errMsg).includes('cancel')) {
                uni.showToast({
                    title: error.errMsg,
                    icon: 'none'
                })
            }
        }
    })
}

const uploadOne = async (filePath: string) => {
    if (imageItems.value.length >= props.maxCount) {
        uni.showToast({ title: `最多允许上传${ props.maxCount }张图片`, icon: 'none' })
        return
    }

    const item: ImageItem = { url: filePath, path: '', status: 'uploading' }
    imageItems.value.push(item)
    const index = imageItems.value.length - 1

    try {
        const res: any = await uploadImage({ filePath, name: 'file' })
        const path = res?.data?.url || res?.data?.path || ''
        if (!path) throw new Error('empty upload url')
        imageItems.value[index] = {
            url: img(path),
            path,
            status: 'success'
        }
        syncValue()
    } catch (error: any) {
        imageItems.value.splice(index, 1)
        uni.showToast({
            title: error?.msg || error?.message || error?.errMsg || props.failText,
            icon: 'none'
        })
        syncValue()
    }
}

const removeImage = (index: number) => {
    imageItems.value.splice(index, 1)
    syncValue()
}

const previewImages = (index: number) => {
    const urls = imageItems.value
        .filter(item => item.status === 'success')
        .map(item => item.url)
    openPreview(urls, index)
}

const toValue = () => {
    return imageItems.value
        .filter(item => item.status === 'success' && item.path)
        .map(item => item.path)
        .join(',')
}

const syncValue = () => {
    const value = toValue()
    emit('update:modelValue', value)
    emit('change', value)
}

defineExpose({
    uploading,
    getValue: toValue
})
</script>

<style scoped lang="scss">
.recycle-image-uploader {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
}

.uploader-item,
.uploader-add {
    position: relative;
    width: 150rpx;
    height: 150rpx;
    border-radius: 12rpx;
    overflow: hidden;
}

.uploader-thumb {
    width: 100%;
    height: 100%;
    background: #f3f4f6;
}

.uploader-mask {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.55);
    color: #fff;
    font-size: 22rpx;
}

.uploader-delete {
    position: absolute;
    top: 8rpx;
    right: 8rpx;
    width: 36rpx;
    height: 36rpx;
    border-radius: 50%;
    background: rgba(15, 23, 42, 0.72);
    display: flex;
    align-items: center;
    justify-content: center;
}

.uploader-add {
    border: 2rpx dashed #cbd5e1;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.uploader-add__text {
    margin-top: 8rpx;
    font-size: 22rpx;
    color: #8c8c8c;
}
</style>
