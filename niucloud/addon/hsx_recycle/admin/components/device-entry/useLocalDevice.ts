import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'

/**
 * 本地取机信息（依赖本地小程序，默认 http://localhost:8080）。
 * 浏览器无法直接读取插入手机的硬件信息，必须经由本地服务暴露的接口获取。
 * 提供：手动读取 fetchConnected、字段映射 mapToRow、可选自动检测轮询 startAuto/stopAuto。
 */
export interface LocalMappedDevice {
    model: string
    imei: string
    imei2?: string
    serial_number?: string
    capacity?: string
    color?: string
    system_version?: string
    warranty_info?: string
    battery_health?: string
    raw: any
}

export function useLocalDevice() {
    const fetching = ref(false)
    const autoDetect = ref(false)
    const available = ref<boolean | null>(null) // null=未知, true/false=上次探测结果
    let timer: any = null
    const seen = new Set<string>()

    // 开发环境走 vite 代理，生产直连本地服务（避免使用 import.meta，兼容插件打包环境）
    const apiUrl = (): string => {
        const isLocalHost = typeof window !== 'undefined'
            && !!window.location
            && (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1')
        return isLocalHost
            ? '/api/local-device/connected?raw=1'
            : 'http://localhost:8080/api/devices/connected?raw=1'
    }

    const deviceKey = (d: any) => String(d?.imei || d?.serial_number || d?.sn || d?.udid || JSON.stringify(d))

    /** 拉取已连接设备（原始数组）。失败抛错由调用方提示 */
    async function fetchConnected(): Promise<any[]> {
        fetching.value = true
        try {
            const res = await axios.get(apiUrl(), { timeout: 15000 })
            if (res.data?.code !== 0) {
                throw new Error(res.data?.message || '读取本地设备失败')
            }
            available.value = true
            return res.data.data || []
        } catch (e: any) {
            available.value = false
            throw e
        } finally {
            fetching.value = false
        }
    }

    /** 本地设备字段 → 行字段映射 */
    function mapToRow(d: any): LocalMappedDevice {
        let battery = ''
        if (d?.battery_health) {
            const s = String(d.battery_health)
            if (s.includes('%') || /^\d+(\.\d+)?$/.test(s)) {
                const n = parseFloat(s.replace('%', '').trim())
                if (!isNaN(n)) battery = String(n)
            } else {
                battery = s
            }
        }
        return {
            model: d?.display_name || d?.model_name || d?.model || '',
            imei: d?.imei || '',
            imei2: d?.imei2 || '',
            serial_number: d?.serial_number || d?.sn || '',
            capacity: d?.storage || d?.total_storage || d?.capacity || '',
            color: d?.color || '',
            system_version: d?.ios_version || d?.os_version || d?.system_version || d?.android_version || '',
            warranty_info: d?.warranty_info || '',
            battery_health: battery,
            raw: d
        }
    }

    /** 友好错误文案 */
    function describeError(error: any): string {
        if (error?.code === 'ECONNABORTED' || error?.message?.includes('timeout')) {
            return '连接本地服务超时（15秒），请检查本地取机程序是否正常运行'
        }
        if (error?.code === 'ERR_NETWORK' || error?.message?.includes('Network Error')) {
            return '无法连接到本地取机程序，请确认其已启动（默认 http://localhost:8080）'
        }
        return error?.message || '读取本地设备失败'
    }

    /**
     * 开启自动检测：每隔 interval 轮询一次，发现"新"设备（按 imei/sn 去重）即回调。
     * 静默失败，不打扰用户（本地服务未开时不弹错）。
     */
    function startAuto(onDetect: (devices: any[]) => void, interval = 3000) {
        autoDetect.value = true
        seen.clear()
        const tick = async () => {
            if (!autoDetect.value) return
            try {
                const list = await fetchConnected()
                const fresh = list.filter(d => !seen.has(deviceKey(d)))
                list.forEach(d => seen.add(deviceKey(d)))
                if (fresh.length) onDetect(fresh)
            } catch {
                // 自动模式静默忽略
            } finally {
                if (autoDetect.value) timer = setTimeout(tick, interval)
            }
        }
        tick()
    }

    function stopAuto() {
        autoDetect.value = false
        if (timer) {
            clearTimeout(timer)
            timer = null
        }
    }

    onBeforeUnmount(stopAuto)

    return { fetching, autoDetect, available, fetchConnected, mapToRow, describeError, startAuto, stopAuto }
}
