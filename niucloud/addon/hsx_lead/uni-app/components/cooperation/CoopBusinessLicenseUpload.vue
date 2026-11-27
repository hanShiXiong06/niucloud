<template>
    <view class="coop-license-upload">
        <view class="coop-license-upload__heading"><text>营业执照</text><text class="coop-license-upload__optional">{{ required ? '必填' : '选填' }}</text><view class="coop-license-upload__privacy"><u-icon name="lock" size="12" color="#71849c" /><text>仅本人和平台可查看</text></view></view>
        <view class="coop-license-upload__content">
            <view v-if="modelValue" class="coop-license-upload__tile" role="button" aria-label="预览营业执照" :aria-disabled="uploading || reading" @tap.stop="preview">
                <image v-if="previewSource" :src="previewSource" mode="aspectFit" />
                <view v-else class="coop-license-upload__placeholder"><u-icon name="file-text" size="27" color="#7890af" /><text>{{ reading ? '读取中' : '已上传' }}</text></view>
                <button class="coop-license-upload__more" :disabled="disabled || uploading" aria-label="营业执照操作" @tap.stop="openActions"><view><u-icon name="more-dot-fill" size="18" color="#fff" /></view></button>
                <view v-if="uploading" class="coop-license-upload__busy" role="status"><u-loading-icon mode="circle" size="20" color="#fff" /><text>上传中</text></view>
            </view>
            <u-upload ref="uploader" :multiple="false" :max-count="1" :max-size="5242880" :custom-style="{ display: modelValue ? 'none' : 'block' }" :disabled="disabled || uploading" :capture="['album', 'camera']" @after-read="afterRead" @oversize="error = '营业执照图片不能超过5MB'">
                <view class="coop-license-upload__tile coop-license-upload__choose" role="button" aria-label="添加营业执照" :aria-disabled="disabled || uploading"><template v-if="uploading"><u-loading-icon mode="circle" size="21" /><text>上传中</text></template><template v-else><u-icon name="camera" size="26" color="#6386b6" /><text>上传营业执照</text></template></view>
            </u-upload>
            <view class="coop-license-upload__caption"><text>{{ modelValue ? '已添加1张证照' : '上传清晰完整的证照照片' }}</text><text>JPG / PNG / WebP · 5MB以内</text></view>
        </view>
        <view v-if="error" class="coop-license-upload__error" role="alert">{{ error }}<text v-if="modelValue && !uploading && !reading" role="button" aria-label="重试读取营业执照" @tap="loadPreview">重新读取</text></view>
    </view>
</template>
<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue'
import { coopError, previewCooperationBusinessLicense, uploadCooperationBusinessLicense } from '../../api/cooperation'
const props = withDefaults(defineProps<{ modelValue?: string; disabled?: boolean; required?: boolean }>(), { modelValue: '', disabled: false, required: false })
const emit = defineEmits<{ (event: 'update:modelValue', value: string): void; (event: 'pending', value: boolean): void }>()
const uploader = ref<{ chooseFile: () => void } | null>(null), uploading = ref(false), reading = ref(false), error = ref(''), previewSource = ref('')
let disposed = false, generation = 0, localFile = ''
function clearPreview() {
    previewSource.value = ''
    // #ifdef MP-WEIXIN
    if (localFile) { try { uni.getFileSystemManager().unlinkSync(localFile) } catch (_) {} }
    // #endif
    localFile = ''
}
function imageSource(value: string): string {
    const match = /^data:image\/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$/.exec(value)
    if (!match) throw new Error('营业执照预览格式无效，请重新读取')
    // #ifdef MP-WEIXIN
    const runtime = (globalThis as any).wx
    if (!runtime?.env?.USER_DATA_PATH) throw new Error('当前环境无法预览证照，请重新打开小程序')
    localFile = runtime.env.USER_DATA_PATH + '/hsx-lead-license-' + Date.now() + '-' + Math.random().toString(36).slice(2) + '.' + match[1]
    uni.getFileSystemManager().writeFileSync(localFile, match[2], 'base64')
    return localFile
    // #endif
    // #ifndef MP-WEIXIN
    return value
    // #endif
}
async function loadPreview() {
    const token = props.modelValue, version = ++generation; clearPreview()
    if (!token) return
    reading.value = true; error.value = ''
    try {
        const response: any = await previewCooperationBusinessLicense(token)
        if (disposed || version !== generation || token !== props.modelValue) return
        if (!response.data?.has_image || typeof response.data.data_url !== 'string') throw new Error('营业执照暂不可预览，请重新读取')
        previewSource.value = imageSource(response.data.data_url)
    } catch (cause) { if (!disposed && version === generation) error.value = coopError(cause, '营业执照读取未完成，请重试') }
    finally { if (!disposed && version === generation) reading.value = false }
}
function preview() {
    if (uploading.value || reading.value) return
    if (previewSource.value) uni.previewImage({ urls: [previewSource.value], current: previewSource.value })
    else void loadPreview()
}
function openActions() {
    if (props.disabled || uploading.value || disposed) return
    uni.showActionSheet({ itemList: ['更换营业执照', '移除营业执照'], success: result => {
        if (props.disabled || uploading.value || disposed) return
        if (result.tapIndex === 0) { if (typeof uploader.value?.chooseFile === 'function') uploader.value.chooseFile(); else error.value = '暂时无法选择图片，请重新打开页面' }
        else if (result.tapIndex === 1) uni.showModal({ title: '移除营业执照？', content: '保存门店资料后生效，不会自动改变认证资格。', confirmText: '移除', success: value => { if (value.confirm && !disposed && !props.disabled && !uploading.value) { emit('update:modelValue', ''); error.value = '' } } })
    } })
}
async function afterRead(event: any) {
    if (props.disabled || uploading.value) return
    const file = Array.isArray(event.file) ? event.file[0] : event.file
    error.value = ''
    if (!file?.url) { error.value = '未读取到完整证照图片，请重新选择'; return }
    if (Number(file.size) > 5242880) { error.value = '营业执照图片不能超过5MB'; return }
    uploading.value = true; emit('pending', true)
    try { const result: any = await uploadCooperationBusinessLicense(file.url); if (!/^[a-f0-9]{64}$/.test(result.data?.token || '')) throw new Error('未返回有效证照凭据，请重新上传'); if (!disposed) emit('update:modelValue', result.data.token) }
    catch (cause) { if (!disposed) error.value = coopError(cause, '证照上传未完成，原证照已保留，请重试') }
    finally { if (!disposed) { uploading.value = false; emit('pending', false) } }
}
watch(() => props.modelValue, loadPreview, { immediate: true })
onBeforeUnmount(() => { disposed = true; generation++; clearPreview() })
</script>
<style scoped lang="scss">
.coop-license-upload { padding: 16px 0; border-bottom: 1px solid #edf1f6; }
.coop-license-upload__heading { display: flex; align-items: center; flex-wrap: wrap; gap: 7px; margin-bottom: 12px; font-size: 14px; line-height: 22px; color: #34465f; font-weight: 550; }
.coop-license-upload__optional { color: #91a0b3; font-size: 11px; font-weight: 400; }
.coop-license-upload__privacy { display: flex; align-items: center; gap: 4px; flex-basis: 100%; color: #71849c; font-weight: 400; font-size: 11px; }
.coop-license-upload__content { display: flex; align-items: center; gap: 12px; min-width: 0; }
.coop-license-upload__tile { position: relative; flex-shrink: 0; width: 128px; height: 92px; border: 1px solid #dfe7f2; border-radius: 10px; overflow: hidden; background: #f5f8fc; box-sizing: border-box; }
.coop-license-upload__tile>image { display: block; width: 100%; height: 100%; }
.coop-license-upload__placeholder,.coop-license-upload__choose { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #6a85aa; font-size: 12px; line-height: 18px; }
.coop-license-upload__placeholder { height: 100%; }
.coop-license-upload__choose { border-style: dashed; }
.coop-license-upload__caption { display: flex; flex-direction: column; gap: 7px; min-width: 0; color: #71829a; font-size: 11px; line-height: 18px; }
.coop-license-upload__more { position: absolute; top: 0; right: 0; width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; padding: 0; margin: 0; border: 0; background: transparent; z-index: 1; }
.coop-license-upload__more>view { width: 26px; height: 26px; display: flex; align-items: center; justify-content: center; border-radius: 50%; background: rgba(34,47,65,.55); }
.coop-license-upload__more::after { border: 0; }
.coop-license-upload__busy { position: absolute; inset: 0; z-index: 2; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 8px; color: #fff; background: rgba(28,43,65,.48); font-size: 12px; }
.coop-license-upload__error { margin-top: 9px; color: #b36550; font-size: 12px; line-height: 20px; }.coop-license-upload__error>text { display: inline-flex; align-items: center; min-height: 44px; margin-left: 10px; color: var(--primary-color,#3478cc); }
@media(max-width:350px) { .coop-license-upload__content { flex-direction: column; align-items: flex-start; }.coop-license-upload__caption { gap: 2px; } }
</style>
