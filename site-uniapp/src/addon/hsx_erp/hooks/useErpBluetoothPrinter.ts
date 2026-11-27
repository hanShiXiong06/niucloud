import { ref } from 'vue'

export type ErpBluetoothDevice = {
    deviceId: string
    name: string
    RSSI?: number
}

const delay = (time: number) => new Promise((resolve) => setTimeout(resolve, time))

/**
 * ERP 蓝牙打印传输层。
 *
 * 业务页只关心“发现设备/发送任务”，不感知 BLE 服务和特征值细节。
 * 当前使用 UTF-8 字节；打印机需开启 UTF-8/Unicode 中文模式。厂商专用 GBK
 * 编码可以后续通过 driver adapter 注入，不侵入业务页面。
 */
export function useErpBluetoothPrinter() {
    const discovering = ref(false)
    const connecting = ref(false)
    const devices = ref<ErpBluetoothDevice[]>([])

    function call(name: string, options: Record<string, any> = {}): Promise<any> {
        return new Promise((resolve, reject) => {
            const api = (uni as any)[name]
            if (typeof api !== 'function') {
                reject(new Error('当前运行环境不支持蓝牙打印'))
                return
            }
            api({ ...options, success: resolve, fail: reject })
        })
    }

    function mergeDevices(found: any[]) {
        for (const raw of found || []) {
            const deviceId = String(raw?.deviceId || '')
            const name = String(raw?.name || raw?.localName || '').trim()
            if (!deviceId || !name) continue
            const item = { deviceId, name, RSSI: Number(raw?.RSSI || 0) }
            const index = devices.value.findIndex((device) => device.deviceId === deviceId)
            if (index >= 0) devices.value.splice(index, 1, item)
            else devices.value.push(item)
        }
        devices.value.sort((a, b) => Number(b.RSSI || -999) - Number(a.RSSI || -999))
    }

    async function startDiscovery() {
        devices.value = []
        discovering.value = true
        try {
            try { await call('closeBluetoothAdapter') } catch (_) {}
            await call('openBluetoothAdapter')
            const uniAny = uni as any
            if (typeof uniAny.onBluetoothDeviceFound === 'function') {
                uniAny.onBluetoothDeviceFound((result: any) => mergeDevices(result?.devices || []))
            }
            await call('startBluetoothDevicesDiscovery', { allowDuplicatesKey: false, interval: 500 })
            await delay(4500)
            const result = await call('getBluetoothDevices')
            mergeDevices(result?.devices || [])
        } finally {
            discovering.value = false
            try { await call('stopBluetoothDevicesDiscovery') } catch (_) {}
        }
    }

    async function send(device: ErpBluetoothDevice, payload: Record<string, any>) {
        connecting.value = true
        const deviceId = device.deviceId
        try {
            await call('createBLEConnection', { deviceId, timeout: 12000 })
            const serviceResult = await call('getBLEDeviceServices', { deviceId })
            const services = (serviceResult?.services || []).filter((item: any) => item?.isPrimary !== false)
            let target: any = null
            for (const service of services) {
                const characteristicResult = await call('getBLEDeviceCharacteristics', { deviceId, serviceId: service.uuid })
                const characteristic = (characteristicResult?.characteristics || []).find((item: any) => item?.properties?.write || item?.properties?.writeNoResponse)
                if (characteristic) {
                    target = { serviceId: service.uuid, characteristicId: characteristic.uuid }
                    break
                }
            }
            if (!target) throw new Error('未找到可写入的蓝牙打印通道，请确认设备已开机且支持 BLE')

            let bytes = utf8Bytes(String(payload?.content || ''))
            if (String(payload?.format || '').startsWith('escpos')) {
                bytes = new Uint8Array([0x1b, 0x40, ...Array.from(bytes), 0x0a, 0x0a])
            }
            const chunkSize = 20
            for (let offset = 0; offset < bytes.length; offset += chunkSize) {
                const chunk = bytes.slice(offset, Math.min(offset + chunkSize, bytes.length))
                await call('writeBLECharacteristicValue', {
                    deviceId,
                    serviceId: target.serviceId,
                    characteristicId: target.characteristicId,
                    value: chunk.buffer,
                })
                await delay(35)
            }
            return true
        } finally {
            connecting.value = false
            try { await call('closeBLEConnection', { deviceId }) } catch (_) {}
        }
    }

    return { discovering, connecting, devices, startDiscovery, send }
}

function utf8Bytes(value: string): Uint8Array {
    const encoded = unescape(encodeURIComponent(value))
    const bytes = new Uint8Array(encoded.length)
    for (let index = 0; index < encoded.length; index += 1) bytes[index] = encoded.charCodeAt(index)
    return bytes
}
