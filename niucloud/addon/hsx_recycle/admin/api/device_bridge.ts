import request from '@/utils/request'
import type { DeviceBridgeDownloads } from '../components/device-entry/deviceBridgeSupport'

export function getDeviceBridgeDownloads(signal?: AbortSignal) {
    return request.get('recycle/device_bridge/downloads', { timeout: 8000, showErrorMessage: false, signal })
}

export function getPlatformDeviceBridgeConfig() {
    return request.get('recycle/platform/device_bridge', { showErrorMessage: false })
}

export function savePlatformDeviceBridgeConfig(data: DeviceBridgeDownloads) {
    return request.post('recycle/platform/device_bridge', data)
}
