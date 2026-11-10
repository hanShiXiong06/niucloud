<template>
    <view class="coop-storefront-upload">
        <view class="coop-storefront-upload__row">
            <view class="coop-storefront-upload__title">门头照片<text>{{ required ? '必填' : '选填' }}</text></view>
            <view class="coop-storefront-upload__controls">
                <view v-if="modelValue" class="coop-storefront-upload__tile coop-storefront-upload__preview" role="button" aria-label="预览门头照片" :aria-disabled="uploading" @tap.stop="preview">
                    <image v-if="!imageFailed" :src="img(modelValue)" mode="aspectFill" @error="imageFailed = true" />
                    <view v-else class="coop-storefront-upload__fallback"><u-icon name="photo" size="24" color="#9aabbe" /></view>
                    <button class="coop-storefront-upload__more" :disabled="disabled || uploading" aria-label="门头照片操作" @tap.stop="openActions"><view><u-icon name="more-dot-fill" size="19" color="#fff" /></view></button>
                    <view v-if="uploading" class="coop-storefront-upload__busy" role="status"><u-loading-icon mode="circle" size="20" color="#fff" /><text>上传中</text></view>
                </view>
                <u-upload ref="uploader" :multiple="false" :max-count="1" :max-size="5242880" :custom-style="{ display: modelValue ? 'none' : 'block' }" :disabled="disabled || uploading" :capture="['album', 'camera']" @after-read="afterRead" @oversize="error = '门头图片不能超过 5 MB'">
                    <view class="coop-storefront-upload__tile coop-storefront-upload__choose" role="button" aria-label="添加门头照片" :aria-disabled="disabled || uploading" :class="{ disabled: disabled || uploading }"><template v-if="uploading"><u-loading-icon mode="circle" size="21" color="var(--primary-color, #3478cc)" /><text>上传中</text></template><template v-else><u-icon name="camera" size="26" color="var(--primary-color, #3478cc)" /><text>添加照片</text></template></view>
                </u-upload>
            </view>
        </view>
        <view v-if="error" class="coop-storefront-upload__error" role="alert">{{ error }}</view>
    </view>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { img } from '@/utils/common'
import { coopError, uploadCooperationStorefront } from '../../api/cooperation'
const props = withDefaults(defineProps<{ modelValue?: string; disabled?: boolean; required?: boolean }>(), { modelValue: '', disabled: false, required: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'pending', value: boolean): void }>()
const uploading = ref(false), error = ref(''), imageFailed = ref(false), uploader = ref<{ chooseFile: () => void } | null>(null)
let disposed = false
watch(() => props.modelValue, () => { imageFailed.value = false })
function remove() {
    if (props.disabled || uploading.value) return
    uni.showModal({ title: '移除门头照片？', content: '保存后门店将不再展示这张照片，原文件不会删除。', confirmText: '移除', success: result => { if (result.confirm && !disposed && !props.disabled && !uploading.value) { emit('update:modelValue', ''); error.value = '' } } })
}
function preview() { if (props.modelValue && !uploading.value) uni.previewImage({ urls: [img(props.modelValue)], current: img(props.modelValue) }) }
function openActions() {
    if (props.disabled || uploading.value || disposed || !props.modelValue) return
    uni.showActionSheet({ itemList: ['更换照片', '移除照片'], success: result => {
        if (props.disabled || uploading.value || disposed) return
        if (result.tapIndex === 0) {
            if (typeof uploader.value?.chooseFile === 'function') uploader.value.chooseFile()
            else error.value = '暂时无法选择照片，请重新打开页面'
        } else if (result.tapIndex === 1) remove()
    } })
}
async function afterRead(event: any) {
    if (props.disabled || uploading.value) return
    const file = Array.isArray(event.file) ? event.file[0] : event.file
    error.value = ''
    if (!file?.url) { error.value = '未读取到图片，请重新选择'; return }
    if (Number(file.size) > 5242880) { error.value = '门头图片不能超过 5 MB'; return }
    if (file.type && !['image', 'image/jpeg', 'image/png', 'image/webp'].includes(String(file.type).toLowerCase())) { error.value = '请选择 JPG、PNG 或 WebP 图片'; return }
    uploading.value = true; emit('pending', true)
    try {
        const response: any = await uploadCooperationStorefront(file.url)
        const value = response.data?.path
        if (typeof value !== 'string' || !value) throw new Error('上传未返回有效门头图片，请重新选择')
        if (!disposed) emit('update:modelValue', value)
    } catch (cause) { if (!disposed) error.value = coopError(cause, '门头上传未完成，原照片已保留，请重试') }
    finally { if (!disposed) { uploading.value = false; emit('pending', false) } }
}
onBeforeUnmount(() => { disposed = true })
</script>
<style scoped lang="scss">
.coop-storefront-upload { margin: 0 0 10px; padding: 0 0 16px; border-bottom: 1px solid #edf1f6; }
.coop-storefront-upload__row { display: block; }
.coop-storefront-upload__title { display: flex; align-items: baseline; gap: 7px; font-size: 14px; line-height: 22px; color: #34465f; font-weight: 550; margin-bottom: 12px; }
.coop-storefront-upload__title>text { font-weight: 400; color: #91a0b3; font-size: 11px; }
.coop-storefront-upload__controls { display: block; width: 112px; max-width: 100%; }
.coop-storefront-upload__tile { width: 112px; height: 96px; max-width: 100%; position: relative; border-radius: 12px; box-sizing: border-box; overflow: hidden; }
.coop-storefront-upload__choose { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; border: 1px dashed #bfcfe4; background: #f3f7fc; color: #5d7fae; font-size: 12px; line-height: 18px; white-space: nowrap; }
.coop-storefront-upload__choose.disabled { opacity: .5; }
.coop-storefront-upload__preview { border: 1px solid #e1e8f1; background: #eef3f9; }
.coop-storefront-upload__preview>image { display: block; width: 100%; height: 100%; }
.coop-storefront-upload__fallback { height: 100%; display: flex; align-items: center; justify-content: center; background: #f0f4f9; }
.coop-storefront-upload__more { position: absolute; top: 0; right: 0; padding: 0; margin: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: transparent; border: 0; z-index: 1; }
.coop-storefront-upload__more>view { width: 26px; height: 26px; border-radius: 50%; background: rgba(34, 47, 65, .55); display: flex; align-items: center; justify-content: center; }
.coop-storefront-upload__more::after { border: 0; }
.coop-storefront-upload__more[disabled] { opacity: .4; }
.coop-storefront-upload__busy { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; background: rgba(28, 43, 65, .48); color: #fff; font-size: 12px; line-height: 18px; z-index: 2; }
.coop-storefront-upload__error { margin-top: 8px; color: #b36550; font-size: 12px; line-height: 19px; }
</style>
