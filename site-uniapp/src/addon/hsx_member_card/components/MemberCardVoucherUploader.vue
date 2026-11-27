<template>
    <view class="mc-uploader">
        <view class="mc-between">
            <view class="mc-grow">
                <view>{{ title }}</view>
                <view v-if="hint" class="mc-small">{{ hint }}</view>
            </view>
            <text class="mc-small">{{ paths.length }}/{{ maxCount }}</text>
        </view>
        <view class="mc-uploader__grid">
            <view v-for="(url, index) in previewUrls" :key="paths[index]" class="mc-uploader__image">
                <image :src="url" mode="aspectFill" @click="preview(index)" />
                <view v-if="!uploading" class="mc-uploader__remove" @click.stop="remove(index)">
                    <u-icon name="close" color="#fff" size="14" />
                </view>
            </view>
            <view v-if="paths.length < maxCount" class="mc-uploader__add" @click="choose">
                <u-loading-icon v-if="uploading" size="23" />
                <u-icon v-else name="camera" color="#65758b" size="23" />
                <text>{{ uploading ? '上传中…' : '添加凭证' }}</text>
            </view>
        </view>
        <view v-if="uploading" class="mc-sub">正在处理图片，请等上传完成后再提交。</view>
    </view>
</template>
<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'

const props = withDefaults(
    defineProps<{
        modelValue?: string | string[]
        title?: string
        hint?: string
        maxCount?: number
    }>(),
    {
        modelValue: '',
        title: '收款凭证（选填）',
        hint: '上传转账截图，方便财务核验',
        maxCount: 3
    }
)

const emit = defineEmits(['update:modelValue', 'change', 'uploading'])
const uploading = ref(false)

const parse = (value: unknown) => {
    if (Array.isArray(value))
        return value
            .map(String)
            .map((item) => item.trim())
            .filter(Boolean)
    const text = String(value || '').trim()
    if (!text) return []
    if (text.startsWith('[')) {
        try {
            const parsed = JSON.parse(text)
            if (Array.isArray(parsed))
                return parsed
                    .map(String)
                    .map((item) => item.trim())
                    .filter(Boolean)
        } catch (_) {}
    }
    return text
        .split(',')
        .map((item) => item.trim())
        .filter(Boolean)
}

const paths = ref<string[]>(parse(props.modelValue).slice(0, props.maxCount))
const value = computed(() => Array.from(new Set(paths.value)).join(','))
const previewUrls = computed(() => paths.value.map((path) => img(path)))

watch(
    () => props.modelValue,
    (next) => {
        const normalized = parse(next).slice(0, props.maxCount).join(',')
        if (normalized !== value.value) paths.value = parse(next).slice(0, props.maxCount)
    }
)
watch(value, (next) => {
    emit('update:modelValue', next)
    emit('change', next)
})
watch(uploading, (next) => emit('uploading', next), { flush: 'sync' })

const preview = (index: number) => {
    if (!previewUrls.value.length) return
    uni.previewImage({ current: previewUrls.value[index] || previewUrls.value[0], urls: previewUrls.value })
}

const remove = (index: number) => {
    if (!uploading.value) paths.value.splice(index, 1)
}

const choose = async () => {
    if (uploading.value || paths.value.length >= props.maxCount) return
    uploading.value = true
    try {
        const result: any = await new Promise((resolve) =>
            uni.chooseImage({
                count: Math.min(9, props.maxCount - paths.value.length),
                sourceType: ['album', 'camera'],
                sizeType: ['compressed'],
                success: resolve,
                fail: (e: any) => {
                    if (!String(e?.errMsg || '').includes('cancel'))
                        uni.showToast({ title: '选择图片失败，可重新选择', icon: 'none' })
                    resolve(null)
                }
            })
        )
        if (!result) return
        const files = result.tempFiles?.length
            ? result.tempFiles
            : (result.tempFilePaths || []).map((path: string) => ({ path }))
        for (const file of files) {
            if (paths.value.length >= props.maxCount) break
            if (!file?.path) continue
            const response: any = await uploadImage({ filePath: String(file.path), name: 'file' })
            const path = String(response?.data?.url || response?.data?.path || '')
            if (!path) throw new Error('上传结果缺少图片地址')
            paths.value.push(path)
        }
    } catch (e: any) {
        uni.showToast({ title: e?.msg || '部分凭证上传失败，已成功的图片会保留', icon: 'none' })
    } finally {
        uploading.value = false
    }
}
</script>
<style scoped lang="scss">
.mc-uploader__grid {
    display: flex;
    flex-wrap: wrap;
    gap: 16rpx;
    margin-top: 20rpx;
}
.mc-uploader__image,
.mc-uploader__add {
    width: 140rpx;
    height: 140rpx;
    border-radius: 12rpx;
    box-sizing: border-box;
}
.mc-uploader__image {
    position: relative;
    overflow: hidden;
}
.mc-uploader__image image {
    width: 100%;
    height: 100%;
    background: #edf1f7;
}
.mc-uploader__remove {
    position: absolute;
    right: 0;
    top: 0;
    width: 56rpx;
    height: 56rpx;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(35, 49, 71, 0.65);
    border-radius: 0 12rpx 0 12rpx;
}
.mc-uploader__add {
    border: 1rpx dashed #bdc8d8;
    background: #f7f9fc;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12rpx;
    font-size: 23rpx;
    color: #65758b;
}
</style>
