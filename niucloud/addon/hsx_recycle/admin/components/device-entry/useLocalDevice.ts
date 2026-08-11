import { ref, onBeforeUnmount } from 'vue'
import axios from 'axios'

/**
 * 本地取机信息（优先使用 hsx_device_bridge，兼容旧版大腿手机助手）。
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
    color_index?: number
    system_version?: string
    warranty_info?: string
    battery_health?: string
    battery_cycle_count?: string | number
    product_type?: string
    model_number?: string
    model_candidates: string[]
    raw: any
}

export function useLocalDevice() {
    const fetching = ref(false)
    const autoDetect = ref(false)
    const available = ref<boolean | null>(null) // null=未知, true/false=上次探测结果
    let timer: any = null
    const seen = new Set<string>()

    const bridgeUrl = 'http://127.0.0.1:17890'
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
        try {
            const res = await requestFirst(deviceUrls)
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
        const identity = d?.identity || {}
        const hardware = d?.hardware || {}
        const display = d?.display || {}
        const system = d?.system || {}
        const batteryData = d?.battery || {}
        let battery = ''
        const batteryValue = batteryData?.health_percent ?? d?.battery_health
        if (batteryValue !== undefined && batteryValue !== null && batteryValue !== '') {
            const s = String(batteryValue)
            if (s.includes('%') || /^\d+(\.\d+)?$/.test(s)) {
                const n = parseFloat(s.replace('%', '').trim())
                if (!isNaN(n)) battery = String(n)
            } else {
                battery = s
            }
        }
        const model = display?.device_name || d?.display_name || d?.model_name || d?.model || display?.model_hint || ''
        const modelCandidates = Array.from(new Set([
            model,
            model ? `苹果 ${model}` : '',
            display?.model_hint,
            hardware?.product_type,
            hardware?.model_number,
            hardware?.model_number_full,
        ].map(item => String(item || '').trim()).filter(Boolean)))
        const rawColorIndex = display?.color_index
            ?? hardware?.color_code
            ?? d?.color_index
            ?? d?.color_code
            ?? (/^\d+$/.test(String(display?.color ?? d?.color ?? '').trim()) ? (display?.color ?? d?.color) : undefined)
        const parsedColorIndex = Number(rawColorIndex)
        return {
            model,
            imei: identity?.imei || d?.imei || '',
            imei2: identity?.imei2 || d?.imei2 || '',
            serial_number: identity?.serial_number || d?.serial_number || d?.sn || '',
            capacity: display?.capacity || d?.storage || d?.total_storage || d?.capacity || '',
            color: /^\d+$/.test(String(display?.color ?? d?.color ?? '').trim()) ? '' : (display?.color || d?.color || ''),
            color_index: Number.isInteger(parsedColorIndex) && parsedColorIndex >= 0 ? parsedColorIndex : undefined,
            system_version: system?.version || d?.ios_version || d?.os_version || d?.system_version || d?.android_version || '',
            warranty_info: d?.warranty_info || '',
            battery_health: battery,
            battery_cycle_count: batteryData?.cycle_count ?? d?.battery_cycle_count ?? d?.battery_cycle ?? '',
            product_type: hardware?.product_type || d?.product_type || '',
            model_number: hardware?.model_number || d?.model_number || '',
            model_candidates: modelCandidates,
            raw: d
        }
    }

    /** 友好错误文案 */
    function describeError(error: any): string {
        if (error?.code === 'ECONNABORTED' || error?.message?.includes('timeout')) {
            return '读取设备超时，请确认手机已解锁并信任此电脑'
        }
        if (error?.code === 'ERR_NETWORK' || error?.message?.includes('Network Error')) {
            return '无法连接设备桥接服务：请确认服务已启动、当前正式域名已加入桥接白名单，并允许浏览器访问本地网络'
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
                const scan = await axios.get(`${bridgeUrl}/v1/scan`, { timeout: 5000 })
                const ids = scan.data?.code === 0 ? (scan.data.data || []).map((id: any) => String(id)) : []
                const hasFresh = ids.some((id: string) => !seen.has(id))
                ids.forEach((id: string) => seen.add(id))
                if (hasFresh) onDetect(await fetchConnected())
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
