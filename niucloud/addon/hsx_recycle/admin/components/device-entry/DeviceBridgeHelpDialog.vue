<template>
    <el-dialog v-model="visible" title="设备桥 · 安装与帮助" width="min(680px, 94vw)" top="6vh"
        append-to-body destroy-on-close @open="load" @close="cancelRequests">
        <div class="bridge-help">
            <section class="bridge-status" :class="{ 'is-connected': health }" aria-live="polite">
                <el-icon :size="24"><CircleCheck v-if="health" /><Connection v-else /></el-icon>
                <div>
                    <strong>{{ checking ? '正在检测本机服务' : health ? '设备桥已连接' : '设备桥尚未连接' }}</strong>
                    <p v-if="health">版本 {{ health.version || '未知' }} · {{ health.device_count }} 台设备</p>
                    <p v-else>{{ healthError || '安装在连接手机的这台电脑上，每台电脑安装一次。' }}</p>
                    <p v-if="health">{{ supportedDevices }}</p>
                </div>
            </section>
            <el-alert v-if="updateVersion" type="warning" :closable="false" show-icon
                :title="`可更新至 ${updateVersion}，下载对应安装包后覆盖安装。`" />
            <el-alert v-if="health?.scan_error || health?.driver?.ready === false" type="warning" :closable="false" show-icon
                :title="health.scan_error || health.driver.message || '服务已启动，但设备驱动暂不可用。'" />

            <section class="bridge-section" v-loading="loadingDownloads">
                <h3>1. 下载安装</h3>
                <div v-if="downloadError" class="bridge-load-error" role="alert">
                    <span>{{ downloadError }}</span>
                    <el-button link type="primary" :icon="Refresh" @click="load">重试</el-button>
                </div>
                <template v-else>
                    <div v-for="item in packages" :key="item.key" class="bridge-package">
                        <el-icon :size="22"><Monitor /></el-icon>
                        <div class="bridge-package__name">
                            <strong>{{ item.label }}</strong>
                            <span>{{ item.version ? `版本 ${item.version}` : '尚未发布版本' }}{{ item.key === computer ? ' · 当前电脑系统' : '' }}</span>
                        </div>
                        <a v-if="item.url" :href="item.url" target="_blank" rel="noopener noreferrer" class="bridge-download el-button el-button--primary">
                            <el-icon><Download /></el-icon><span>下载安装包</span>
                        </a>
                        <el-button v-else disabled :icon="Download">待提供安装包</el-button>
                    </div>
                    <p v-if="packages.some(item => !item.url)" class="bridge-note">安装包由 SaaS 平台统一提供，暂未提供的系统请联系平台管理员。</p>
                    <p class="bridge-note">Mac 包仅适用于 Apple 芯片；Intel Mac 暂无安装包。只安装本站管理员提供的可信文件。</p>
                </template>
            </section>

            <section class="bridge-section">
                <h3>2. 连接手机</h3>
                <el-tabs v-model="phoneTab">
                    <el-tab-pane label="iPhone" name="ios">
                        <ol class="bridge-steps">
                            <li>用支持数据传输的数据线连接电脑，解锁手机。</li>
                            <li>在手机上选择「信任此电脑」，按提示输入锁屏密码。</li>
                            <li>回到此处检测连接，再点击「读取本地设备」。</li>
                        </ol>
                    </el-tab-pane>
                    <el-tab-pane label="安卓 · MTP" name="android">
                        <p class="bridge-note bridge-note--warning">{{ androidHelp }}</p>
                        <ol class="bridge-steps">
                            <li>用数据线连接电脑并解锁手机。</li>
                            <li>在手机 USB 通知里选择「文件传输」，不是仅充电或 USB 网络共享。</li>
                            <li>不需要开启 USB 调试；首次识别后选择标准型号，后续同型号复用绑定。</li>
                        </ol>
                    </el-tab-pane>
                </el-tabs>
            </section>

            <el-collapse class="bridge-faq">
                <el-collapse-item title="装好了，还是连接不上？" name="connection">
                    <p>安装完成后服务会自动启动。Windows 可从开始菜单打开「HSX Device Bridge → 启动设备桥」；Mac 可重新登录电脑后再检测。</p>
                    <p>浏览器询问本地网络权限时请选择允许。已拒绝时，在本站的浏览器权限设置中重新允许。仍无法连接，请把提示发给管理员检查站点白名单。</p>
                </el-collapse-item>
                <el-collapse-item title="服务已连接，却没有手机？" name="device">
                    <p>先解锁手机，确认信任或文件传输，再更换数据线或 USB 接口。Windows 的 iPhone 读取还需要苹果驱动，可在已有的爱思助手中检查驱动。</p>
                    <p>其他读机工具可能占用设备，请先结束它们的读取操作。容量、颜色等未读到的信息需人工补充，读取不会自动保存订单或打印标签。</p>
                </el-collapse-item>
            </el-collapse>
            <el-link v-if="downloads.tutorial_url" :href="downloads.tutorial_url" target="_blank" rel="noopener noreferrer" type="primary" :icon="VideoPlay">查看图文 / 视频教程</el-link>
        </div>
        <template #footer>
            <div class="bridge-footer">
                <el-button :icon="Refresh" :loading="checking" @click="checkHealth">已安装，检测连接</el-button>
                <el-button v-if="health && allowRead" type="primary" :icon="Connection" @click="readDevices">返回并读取</el-button>
                <el-button v-else @click="visible = false">关闭</el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue'
import axios from 'axios'
import { CircleCheck, Connection, Download, Monitor, Refresh, VideoPlay } from '@element-plus/icons-vue'
import { getDeviceBridgeDownloads } from '@/addon/hsx_recycle/api/device_bridge'
import { BRIDGE_BASE_URL, bridgeConnectionError, bridgeUpdateAvailable, detectBridgeComputer, emptyBridgeDownloads, normalizeBridgeDownloads } from './deviceBridgeSupport'

const props = withDefaults(defineProps<{ modelValue: boolean; allowRead?: boolean }>(), { allowRead: true })
const emit = defineEmits<{ (event: 'update:modelValue', value: boolean): void; (event: 'read'): void }>()
const visible = computed({ get: () => props.modelValue, set: value => emit('update:modelValue', value) })
const phoneTab = ref('ios')
const computer = detectBridgeComputer(navigator.userAgent)
const downloads = ref(emptyBridgeDownloads())
const loadingDownloads = ref(false)
const downloadError = ref('')
const checking = ref(false)
const health = ref<any>(null)
const healthError = ref('')
const genericMtp = computed(() => health.value?.capabilities?.android_mtp_scope === 'generic')
const supportedDevices = computed(() => {
    if (!health.value?.capabilities?.android_mtp) return '支持 iPhone；当前版本未开放安卓读取'
    return genericMtp.value ? '支持 iPhone、安卓 MTP 基础读取（Mac）' : '支持 iPhone、三星 MTP（旧版）'
})
const androidHelp = computed(() => {
    if (!health.value?.capabilities?.android_mtp) return '当前服务未开放安卓读取，请安装支持 MTP 的 Mac 包；现有 Windows 包仅支持 iPhone。'
    if (!genericMtp.value) return '此 Mac 版本支持三星 MTP 基础读取；iQOO、魅族等品牌需更新至 0.3.0 或更高版本。'
    return '按 MTP 协议读取，不限制品牌；并非所有型号都提供相同字段。USB 序列号可填入 SN，需核对；不会读取 IMEI、电池健康度，也不保证可查保修。'
})
let healthController: AbortController | null = null
let downloadController: AbortController | null = null
const packages = computed(() => [
    { key: 'windows', label: 'Windows · 64 位', url: downloads.value.windows_url, version: downloads.value.windows_version },
    { key: 'macos_arm64', label: 'macOS · Apple 芯片', url: downloads.value.macos_arm64_url, version: downloads.value.macos_arm64_version }
])
const updateVersion = computed(() => {
    const target = packages.value.find(item => item.key === computer)
    return health.value && target?.url && bridgeUpdateAvailable(health.value.version, target.version) ? target.version : ''
})

async function checkHealth() {
    healthController?.abort()
    const controller = new AbortController()
    healthController = controller
    checking.value = true
    health.value = null
    healthError.value = ''
    try {
        const response = await axios.get(`${BRIDGE_BASE_URL}/v1/health`, { timeout: 5000, signal: controller.signal })
        const data = response.data?.data
        if (response.data?.code !== 0 || data?.service !== 'hsx_device_bridge') {
            healthError.value = '本地端口返回的不是设备桥服务，请联系管理员检查端口占用。'
            return
        }
        if (!controller.signal.aborted) health.value = data
    } catch (error) {
        if (!controller.signal.aborted) healthError.value = bridgeConnectionError(error)
    } finally {
        if (healthController === controller) checking.value = false
    }
}

async function loadDownloads() {
    downloadController?.abort()
    const controller = new AbortController()
    downloadController = controller
    loadingDownloads.value = true
    downloadError.value = ''
    downloads.value = emptyBridgeDownloads()
    try {
        const response = await getDeviceBridgeDownloads(controller.signal)
        if (!controller.signal.aborted) downloads.value = normalizeBridgeDownloads(response.data || {})
    } catch {
        if (!controller.signal.aborted) downloadError.value = '下载信息加载失败，请重试或联系管理员。'
    } finally {
        if (downloadController === controller) loadingDownloads.value = false
    }
}

function load() {
    void loadDownloads()
    void checkHealth()
}
function cancelRequests() {
    healthController?.abort()
    downloadController?.abort()
}
function readDevices() {
    visible.value = false
    emit('read')
}
onBeforeUnmount(cancelRequests)
</script>

<style scoped lang="scss">
.bridge-help { display: flex; flex-direction: column; gap: 16px; max-height: 65vh; overflow-y: auto; padding: 0 4px; color: var(--el-text-color-regular); }
.bridge-help > * { flex-shrink: 0; }
.bridge-status { display: flex; align-items: center; gap: 12px; padding: 14px; border-radius: 6px; background: var(--el-fill-color-light); }
.bridge-status > .el-icon { flex: 0 0 24px; color: var(--el-color-warning); }
.bridge-status.is-connected > .el-icon { color: var(--el-color-success); }
.bridge-status strong { color: var(--el-text-color-primary); font-size: 14px; }
.bridge-status p { margin: 5px 0 0; line-height: 1.6; font-size: 12px; overflow-wrap: anywhere; }
.bridge-section h3 { font-size: 14px; margin: 0 0 10px; color: var(--el-text-color-primary); }
.bridge-package { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.bridge-package > .el-icon { flex-shrink: 0; color: var(--el-text-color-secondary); }
.bridge-package__name { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 5px; }
.bridge-package__name strong { font-size: 14px; font-weight: 500; }
.bridge-package__name span, .bridge-note { font-size: 12px; color: var(--el-text-color-secondary); line-height: 1.7; }
.bridge-note { margin: 8px 0 0; }
.bridge-note--warning { color: var(--el-color-warning-dark-2); margin: 0 0 10px; }
.bridge-download { text-decoration: none; flex-shrink: 0; }
.bridge-download .el-icon + span { margin-left: 6px; }
.bridge-steps { padding-left: 20px; margin: 0; font-size: 13px; line-height: 1.9; }
.bridge-steps li + li { margin-top: 6px; }
.bridge-faq p { margin: 0 0 8px; line-height: 1.8; }
.bridge-footer { display: flex; justify-content: flex-end; flex-wrap: wrap; gap: 10px; }
.bridge-footer .el-button + .el-button { margin-left: 0; }
.bridge-load-error { display: flex; align-items: center; justify-content: space-between; gap: 10px; color: var(--el-color-danger); }
@media (max-width: 480px) {
    .bridge-package { flex-wrap: wrap; }
    .bridge-package__name { flex-basis: calc(100% - 40px); }
    .bridge-package > .el-button { margin-left: 34px; }
}
</style>
