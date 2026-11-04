<template>
    <view class="coop-storefront-upload">
        <view class="coop-storefront-upload__row">
            <view class="coop-storefront-upload__title">门头照片<text>选填</text></view>
            <u-upload :multiple="false" :max-count="1" :max-size="5242880" :disabled="disabled || uploading" :capture="['album', 'camera']" @after-read="afterRead" @oversize="error = '门头图片不能超过 5 MB'">
                <view class="coop-storefront-upload__choose" role="button" :aria-label="modelValue ? '更换门头照片' : '添加门头照片'" :aria-disabled="disabled || uploading" :class="{ disabled: disabled || uploading }"><u-loading-icon v-if="uploading" mode="circle" size="14" /><u-icon v-else name="camera" size="17" color="var(--primary-color, #3478cc)" /><text>{{ uploading ? '上传中' : modelValue ? '更换照片' : '添加照片' }}</text></view>
            </u-upload>
        </view>
        <view v-if="modelValue" class="coop-storefront-upload__existing">
            <view class="coop-storefront-upload__preview" role="button" aria-label="预览门头照片" @tap.stop="preview"><image :src="img(modelValue)" mode="aspectFill" /></view>
            <button class="coop-storefront-upload__remove" :disabled="disabled || uploading" @tap.stop="remove">移除</button>
        </view>
        <view v-if="error" class="coop-storefront-upload__error" role="alert">{{ error }}</view>
    </view>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue'
import { img } from '@/utils/common'
import { coopError, uploadCooperationStorefront } from '../../api/cooperation'
const props = withDefaults(defineProps<{ modelValue?: string; disabled?: boolean }>(), { modelValue: '', disabled: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'pending', value: boolean): void }>()
const uploading = ref(false), error = ref('')
let disposed = false
function remove() { if (props.disabled || uploading.value) return; emit('update:modelValue', ''); error.value = '' }
function preview() { if (props.modelValue && !uploading.value) uni.previewImage({ urls: [img(props.modelValue)], current: img(props.modelValue) }) }
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
.coop-storefront-upload { margin: 0 0 12px; padding: 0 0 12px; border-bottom: 1px solid #edf1f6; }.coop-storefront-upload__row { display: flex; justify-content: space-between; align-items: center; gap: 12px; min-height: 48px; }.coop-storefront-upload__title { display: flex; align-items: baseline; gap: 7px; min-width: 0; font-size: 14px; color: #445973; font-weight: 550; }.coop-storefront-upload__title>text { font-weight: 400; color: #91a0b3; font-size: 11px; }.coop-storefront-upload__choose { min-height: 44px; padding: 0 12px; display: flex; align-items: center; justify-content: center; gap: 6px; border: 1px solid #e4ebf5; border-radius: 7px; background: #f4f7fc; box-sizing: border-box; color: var(--primary-color, #3478cc); font-size: 12px; white-space: nowrap; }.coop-storefront-upload__choose.disabled { opacity: .5; }.coop-storefront-upload__existing { display: flex; align-items: center; gap: 14px; padding-top: 8px; }.coop-storefront-upload__preview { width: 72px; height: 54px; overflow: hidden; border-radius: 7px; border: 1px solid #e6edf5; flex: none; }.coop-storefront-upload__preview>image { width: 100%; height: 100%; }.coop-storefront-upload__remove { padding: 0 8px; margin: 0; min-height: 44px; line-height: 44px; font-size: 12px; background: transparent; border: 0; color: #8c7a6c; }.coop-storefront-upload__remove::after { border: 0; }.coop-storefront-upload__error { margin-top: 8px; color: #b36550; font-size: 12px; line-height: 19px; }
</style>
