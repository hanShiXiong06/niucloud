<template>
    <section class="bridge-settings">
        <p>所有站点共用。支持网盘分享页或下载直链，留空则不提供该安装包。</p>
        <el-form label-position="top">
            <div v-for="item in platforms" :key="item.urlKey" class="bridge-settings__row">
                <el-form-item :label="item.label + '安装包地址'" :error="urlError(modelValue[item.urlKey])">
                    <el-input :model-value="modelValue[item.urlKey]" maxlength="2048" clearable
                        :placeholder="item.placeholder" @update:model-value="value => update(item.urlKey, value)" />
                </el-form-item>
                <el-form-item label="安装包版本" :error="versionError(modelValue[item.versionKey])">
                    <el-input :model-value="modelValue[item.versionKey]" maxlength="40" clearable
                        placeholder="如 0.2.0" @update:model-value="value => update(item.versionKey, value)" />
                </el-form-item>
            </div>
            <el-form-item label="图文 / 视频教程地址（选填）" :error="urlError(modelValue.tutorial_url)">
                <el-input :model-value="modelValue.tutorial_url" maxlength="2048" clearable
                    placeholder="https://...；不填也有内置安装步骤" @update:model-value="value => update('tutorial_url', value)" />
            </el-form-item>
        </el-form>
    </section>
</template>

<script setup lang="ts">
import { safeBridgeUrl, validBridgeVersion } from '@/addon/hsx_recycle/components/device-entry/deviceBridgeSupport'
import type { DeviceBridgeDownloads } from '@/addon/hsx_recycle/components/device-entry/deviceBridgeSupport'

const props = defineProps<{ modelValue: DeviceBridgeDownloads }>()
const emit = defineEmits<{ (event: 'update:modelValue', value: DeviceBridgeDownloads): void }>()
const platforms = [
    { label: 'Windows 64 位', urlKey: 'windows_url', versionKey: 'windows_version', placeholder: 'https://.../hsx_device_bridge-版本-windows-x64-setup.exe' },
    { label: 'Mac Apple 芯片', urlKey: 'macos_arm64_url', versionKey: 'macos_arm64_version', placeholder: 'https://.../hsx_device_bridge-版本-macos-arm64.pkg' }
] as const
const update = (key: keyof DeviceBridgeDownloads, value: string) => emit('update:modelValue', { ...props.modelValue, [key]: value.trim() })
const urlError = (value: string) => value && (!safeBridgeUrl(value) || !/^https?:\/\//i.test(value)) ? '请填写完整 http(s) 地址' : ''
const versionError = (value: string) => value && !validBridgeVersion(value) ? '请填写数字版本，如 0.2.0' : ''
</script>

<style scoped>
.bridge-settings { grid-column: 1 / -1; padding: 20px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.bridge-settings > p { margin: 8px 0 18px; font-size: 12px; color: var(--el-text-color-secondary); line-height: 1.8; }
.bridge-settings__row { display: grid; grid-template-columns: minmax(0, 1fr) 180px; gap: 20px; }
@media (max-width: 640px) { .bridge-settings__row { grid-template-columns: minmax(0, 1fr); gap: 0; } }
</style>
