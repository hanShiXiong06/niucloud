<template>
    <view class="scan-code-input">
        <u-input
            :modelValue="modelValue"
            :type="type"
            :maxlength="maxlength"
            :placeholder="placeholder"
            :clearable="clearable"
            :disabled="disabled"
            :border="border"
            :inputAlign="inputAlign"
            :fontSize="fontSize"
            :placeholderClass="placeholderClass"
            @update:modelValue="handleInput"
            @confirm="emit('confirm')"
        >
            <template #suffix>
                <view class="scan-code-input__scan" @click.stop="handleScan">
                    <text class="iconfont iconsaoma"></text>
                    <text v-if="showScanText" class="scan-code-input__text">{{ scanText }}</text>
                </view>
            </template>
        </u-input>
    </view>
</template>

<script setup lang="ts">
const props = withDefaults(defineProps<{
    modelValue: string | number
    placeholder?: string
    maxlength?: number
    type?: string
    disabled?: boolean
    clearable?: boolean
    onlyFromCamera?: boolean
    border?: string
    inputAlign?: string
    fontSize?: string
    placeholderClass?: string
    showScanText?: boolean
    scanText?: string
}>(), {
    placeholder: '请输入或扫码录入',
    maxlength: -1,
    type: 'text',
    disabled: false,
    clearable: true,
    onlyFromCamera: false,
    border: 'none',
    inputAlign: 'right',
    fontSize: '28rpx',
    placeholderClass: 'text-[var(--text-color-light9)] text-[28rpx]',
    showScanText: false,
    scanText: '扫码'
})

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void
    (event: 'scan', value: string, raw: any): void
    (event: 'confirm'): void
}>()

const handleInput = (value: any) => {
    emit('update:modelValue', String(value ?? ''))
}

const handleScan = () => {
    if (props.disabled) return

    uni.scanCode({
        onlyFromCamera: props.onlyFromCamera,
        scanType: ['qrCode', 'barCode'],
        success: (res: any) => {
            const value = normalizeScanResult(res?.result || '')
            if (!value) {
                uni.showToast({ title: '未识别到有效内容', icon: 'none' })
                return
            }
            emit('update:modelValue', value)
            emit('scan', value, res)
        },
        fail: (error: any) => {
            if (String(error?.errMsg || '').includes('cancel')) return
            uni.showToast({ title: error?.errMsg || '扫码失败', icon: 'none' })
        }
    })
}

const normalizeScanResult = (value: any) => {
    const text = decodeURIComponent(String(value || '').trim())
    if (!text) return ''

    const queryMatch = text.match(/[?&](?:imei|sn|code|id)=([^&#]+)/i)
    if (queryMatch?.[1]) return decodeURIComponent(queryMatch[1]).trim()

    return text
}
</script>

<style scoped lang="scss">
.scan-code-input {
    width: 100%;
    flex: 1;
    min-width: 0;
}

.scan-code-input :deep(.u-input) {
    width: 100%;
}

.scan-code-input :deep(.u-input__content) {
    width: 100%;
    min-width: 0;
}

.scan-code-input :deep(.u-input__content__field-wrapper) {
    flex: 1;
    min-width: 0;
}

.scan-code-input__scan {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 56rpx;
    height: 56rpx;
    padding-left: 12rpx;
    color: var(--primary-color);
    font-size: 34rpx;
}

.scan-code-input__text {
    margin-left: 6rpx;
    font-size: 22rpx;
}
</style>
