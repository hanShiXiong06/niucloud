<template>
    <HsxPage title="设备桥下载" content-width="standard" class="bridge-platform">
        <template #extra>
            <el-button type="primary" :icon="Check" :loading="saving" :disabled="!loaded || !dirty" @click="save">保存配置</el-button>
        </template>
        <el-alert title="平台统一发布，各站点直接使用，无需重复配置。" type="info" :closable="false" show-icon />
        <div v-if="loadError" class="bridge-platform__error" role="alert">
            <el-alert :title="loadError" type="error" :closable="false" />
            <el-button :icon="Refresh" :loading="loading" @click="load">重新加载</el-button>
        </div>
        <el-skeleton v-else-if="!loaded" :rows="6" animated />
        <fieldset v-else :disabled="saving" class="bridge-platform__fields">
            <el-alert v-if="saveError" :title="saveError" type="error" :closable="false" show-icon />
            <DeviceBridgeDownloadSettings v-model="form" />
        </fieldset>
    </HsxPage>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Refresh } from '@element-plus/icons-vue'
import { HsxPage } from '@/addon/hsx_components/core'
import DeviceBridgeDownloadSettings from '../order_config/components/DeviceBridgeDownloadSettings.vue'
import { getPlatformDeviceBridgeConfig, savePlatformDeviceBridgeConfig } from '@/addon/hsx_recycle/api/device_bridge'
import { emptyBridgeDownloads, normalizeBridgeDownloads, safeBridgeUrl, validBridgeVersion } from '@/addon/hsx_recycle/components/device-entry/deviceBridgeSupport'

const form = ref(emptyBridgeDownloads())
const loaded = ref(false)
const loading = ref(false)
const saving = ref(false)
const loadError = ref('')
const saveError = ref('')
const saved = ref('')
const dirty = computed(() => loaded.value && JSON.stringify(form.value) !== saved.value)

async function load() {
    if (loading.value) return
    loading.value = true
    loadError.value = ''
    try {
        const response = await getPlatformDeviceBridgeConfig()
        form.value = normalizeBridgeDownloads(response.data || {})
        saved.value = JSON.stringify(form.value)
        loaded.value = true
    } catch {
        loadError.value = '平台配置加载失败，请重新加载。'
    } finally {
        loading.value = false
    }
}

async function save() {
    if (!loaded.value || saving.value || !dirty.value) return
    saveError.value = ''
    for (const [key, value] of Object.entries(form.value)) {
        if (!value) continue
        const valid = key.endsWith('_version') ? validBridgeVersion(value)
            : Boolean(safeBridgeUrl(value)) && /^https?:\/\//i.test(value)
        if (!valid) {
            saveError.value = key.endsWith('_version') ? '请填写数字版本，如 0.2.0。' : '请填写完整 http(s) 下载或教程地址。'
            return
        }
    }
    const payload = JSON.stringify(form.value)
    saving.value = true
    try {
        await savePlatformDeviceBridgeConfig(JSON.parse(payload))
        saved.value = payload
        ElMessage.success('平台下载配置已保存')
    } catch {
        saveError.value = '保存失败，修改内容已保留，请重试。'
    } finally {
        saving.value = false
    }
}

onBeforeRouteLeave(async () => {
    if (!dirty.value) return true
    try {
        await ElMessageBox.confirm('下载配置尚未保存，确定离开吗？', '未保存的修改', { type: 'warning', confirmButtonText: '离开', cancelButtonText: '继续编辑' })
        return true
    } catch {
        return false
    }
})
onMounted(load)
</script>

<style scoped>
.bridge-platform { max-width: 1060px; }
.bridge-platform__fields { border: 0; padding: 0; margin: 12px 0 0; min-width: 0; }
.bridge-platform__error { display: flex; align-items: center; gap: 12px; margin-top: 20px; }
.el-skeleton { padding-top: 24px; }
</style>
