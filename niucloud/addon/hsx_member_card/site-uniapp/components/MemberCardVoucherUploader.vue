<template>
    <view class="mt-[16rpx] rounded-[15rpx] bg-[#f7f9fc] p-[18rpx]">
        <view class="flex items-start justify-between gap-[16rpx]">
            <view class="flex min-w-0 flex-1 flex-col gap-[5rpx]">
                <text class="text-[25rpx] font-semibold leading-[1.4] text-[#475569]">{{ title }}</text>
                <text v-if="hint" class="text-[21rpx] leading-[1.45] text-[#8290a5]">{{ hint }}</text>
            </view>
            <text class="text-[21rpx] text-[#8290a5]">{{ paths.length }}/{{ maxCount }}</text>
        </view>
        <view class="mt-[15rpx] flex flex-wrap gap-[12rpx]">
            <view v-for="(url, index) in previewUrls" :key="paths[index]" class="relative h-[108rpx] w-[108rpx] overflow-hidden rounded-[12rpx]">
                <image class="h-full w-full bg-[#e2e8f0]" :src="url" mode="aspectFill" @click="preview(index)" />
                <view class="absolute right-[5rpx] top-[5rpx] flex h-[32rpx] w-[32rpx] items-center justify-center rounded-full bg-[rgba(15,23,42,.68)]" @click.stop="remove(index)">
                    <u-icon name="close" color="#ffffff" size="11" />
                </view>
            </view>
            <view
                v-if="paths.length < maxCount"
                class="flex h-[108rpx] w-[108rpx] flex-col items-center justify-center gap-[7rpx] rounded-[12rpx] border-2 border-dashed border-[#cbd5e1] text-[21rpx] text-[#64748b]"
                @click="choose"
            >
                <u-loading-icon v-if="uploading" size="20" />
                <template v-else>
                    <u-icon name="camera" color="#64748b" size="21" />
                    <text>上传凭证</text>
                </template>
            </view>
        </view>
    </view>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { uploadImage } from '@/app/api/system'
import { img } from '@/utils/common'

const props = withDefaults(defineProps<{
    modelValue?: string | string[]
    title?: string
    hint?: string
    maxCount?: number
}>(), {
    modelValue: '',
    title: '收款凭证（选填）',
    hint: '上传转账截图，方便财务核验',
    maxCount: 3,
})

const emit = defineEmits(['update:modelValue', 'change', 'uploading'])
const uploading = ref(false)

const parse = (value: unknown) => {
    if (Array.isArray(value)) return value.map(String).map(item => item.trim()).filter(Boolean)
    const text = String(value || '').trim()
    if (!text) return []
    if (text.startsWith('[')) {
        try {
            const parsed = JSON.parse(text)
            if (Array.isArray(parsed)) return parsed.map(String).map(item => item.trim()).filter(Boolean)
        } catch (_) {}
    }
    return text.split(',').map(item => item.trim()).filter(Boolean)
}

const paths = ref<string[]>(parse(props.modelValue).slice(0, props.maxCount))
const value = computed(() => Array.from(new Set(paths.value)).join(','))
const previewUrls = computed(() => paths.value.map(path => img(path)))

watch(() => props.modelValue, next => {
    const normalized = parse(next).slice(0, props.maxCount).join(',')
    if (normalized !== value.value) paths.value = parse(next).slice(0, props.maxCount)
})
watch(value, next => {
    emit('update:modelValue', next)
    emit('change', next)
})
watch(uploading, next => emit('uploading', next))

const preview = (index: number) => {
    if (!previewUrls.value.length) return
    uni.previewImage({ current: previewUrls.value[index] || previewUrls.value[0], urls: previewUrls.value })
}

const remove = (index: number) => paths.value.splice(index, 1)

const choose = async () => {
    if (uploading.value || paths.value.length >= props.maxCount) return
    const result: any = await new Promise(resolve => {
        uni.chooseImage({
            count: Math.min(9, props.maxCount - paths.value.length),
            sourceType: ['album', 'camera'],
            sizeType: ['compressed'],
            success: resolve,
            fail: (error: any) => {
                if (!String(error?.errMsg || '').includes('cancel')) {
                    uni.showToast({ title: '选择图片失败', icon: 'none' })
                }
                resolve(null)
            },
        })
    })
    if (!result) return

    const files = Array.isArray(result.tempFiles) && result.tempFiles.length
        ? result.tempFiles
        : (result.tempFilePaths || []).map((path: string) => ({ path }))

    uploading.value = true
    try {
        for (const file of files) {
            if (paths.value.length >= props.maxCount) break
            const filePath = String(file?.path || '')
            if (!filePath) continue
            const response: any = await uploadImage({ filePath, name: 'file' })
            const path = String(response?.data?.url || response?.data?.path || '')
            if (!path) throw new Error('上传结果缺少图片地址')
            paths.value.push(path)
        }
    } catch (error: any) {
        uni.showToast({ title: error?.message || error?.msg || '凭证上传失败', icon: 'none' })
    } finally {
        uploading.value = false
    }
}
</script>
