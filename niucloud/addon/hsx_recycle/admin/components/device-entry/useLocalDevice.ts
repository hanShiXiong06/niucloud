import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'
import { mapLocalDevice } from './deviceReadings'
import { BRIDGE_BASE_URL } from './deviceBridgeSupport'

/**
 * 本地取机信息（优先使用 hsx_device_bridge，兼容旧版大腿手机助手）。
 * 浏览器无法直接读取插入手机的硬件信息，必须经由本地服务暴露的接口获取。
 * 提供：手动读取 fetchConnected、字段映射 mapToRow、可选自动检测轮询 startAuto/stopAuto。
 */
export type { LocalMappedDevice } from './deviceReadings'

export function useLocalDevice() {
    const fetching = ref(false)
    const autoDetect = ref(false)
    const available = ref<boolean | null>(null) // null=未知, true/false=上次探测结果
    const readWarnings = ref<string[]>([])
    let timer: any = null
    let generation = 0
    let completedDeviceIds: string[] | null = null
    const seen = new Set<string>()

    const bridgeUrl = BRIDGE_BASE_URL
    const deviceUrls = [
        `${bridgeUrl}/v1/devices`,
        'http://localhost:8080/api/devices/connected?raw=1'
    ]

    async function requestFirst(urls: string[]): Promise<any> {
        let lastError: any = null
        for (const url of urls) {
            try {
                const res = await axios.get(url, { timeout: 30000 })
                if (res.data?.code !== 0) throw new Error(res.data?.message || '读取本地设备失败')
                return res
            } catch (error: any) {
                lastError = error
                const status = Number(error?.response?.status || 0)
                if (url.startsWith(bridgeUrl) && status > 0 && status !== 404) {
                    throw new Error(error?.response?.data?.message || error?.message || '读取本地设备失败')
                }
            }
        }
        throw lastError || new Error('无法连接本地设备桥接服务')
    }

    /** 拉取已连接设备（原始数组）。失败抛错由调用方提示 */
    async function fetchConnected(): Promise<any[]> {
        fetching.value = true
        readWarnings.value = []
        completedDeviceIds = null
        try {
            const res = await requestFirst(deviceUrls)
            available.value = true
            const devices = Array.isArray(res.data.data) ? res.data.data : []
            readWarnings.value = (Array.isArray(res.data.warnings) ? res.data.warnings : [])
                .map((item: any) => String(item?.message || '')).filter(Boolean)
            if (typeof res.data.partial === 'boolean') {
                completedDeviceIds = devices.map((item: any) => String(item?.device_id || '')).filter(Boolean)
            }
            return devices
        } catch (e: any) {
            available.value = false
            readWarnings.value = [describeError(e)]
            throw e
        } finally {
            fetching.value = false
        }
    }

    const mapToRow = mapLocalDevice

    /** 友好错误文案 */
    function describeError(error: any): string {
        if (error?.code === 'ECONNABORTED' || error?.message?.includes('timeout')) {
            return '读取设备超时，请确认手机已解锁，并检查信任 / 文件传输状态；可打开安装与帮助排查'
        }
        if (error?.code === 'ERR_NETWORK' || error?.message?.includes('Network Error')) {
            return '未连接到设备桥，请先安装或启动服务，并允许浏览器访问本地网络'
        }
        return error?.message || '读取本地设备失败'
    }

    /**
     * 开启自动检测：每隔 interval 轮询一次，发现"新"设备（按 imei/sn 去重）即回调。
     * 失败原因供页面内提示使用，不重复弹出通知。
     */
    function startAuto(onDetect: (devices: any[]) => void | Promise<void>, interval = 3000) {
        stopAuto()
        const current = generation
        autoDetect.value = true
        seen.clear()
        const tick = async () => {
            if (!autoDetect.value || current !== generation) return
            try {
                if (fetching.value) return
                const scan = await axios.get(`${bridgeUrl}/v1/scan`, { timeout: 5000 })
                if (!autoDetect.value || current !== generation) return
                if (scan.data?.code !== 0) throw new Error(scan.data?.message || '扫描设备失败')
                const ids = (Array.isArray(scan.data.data) ? scan.data.data : []).map((id: any) => String(id))
                for (const id of seen) if (!ids.includes(id)) seen.delete(id)
                const hasFresh = ids.some((id: string) => !seen.has(id))
                if (hasFresh) {
                    const devices = await fetchConnected()
                    if (!autoDetect.value || current !== generation) return
                    await onDetect(devices)
                    if (!autoDetect.value || current !== generation) return
                    // A failed phone must remain eligible for the next poll.
                    const completed = completedDeviceIds ?? ids
                    completed.forEach((id: string) => seen.add(id))
                } else {
                    readWarnings.value = (Array.isArray(scan.data.warnings) ? scan.data.warnings : [])
                        .map((item: any) => String(item?.message || '')).filter(Boolean)
                }
            } catch (error) {
                if (autoDetect.value && current === generation) readWarnings.value = [describeError(error)]
            } finally {
                if (autoDetect.value && current === generation) timer = setTimeout(tick, interval)
            }
        }
        tick()
    }

    function stopAuto() {
        generation += 1
        autoDetect.value = false
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    onBeforeUnmount(stopAuto)

    return { fetching, autoDetect, available, readWarnings, fetchConnected, mapToRow, describeError, startAuto, stopAuto }
}
