import { ref } from 'vue'
import { getCheckTemplateSchema } from '@/addon/hsx_recycle/api/check-template'

type Device = Record<string, any>
type OptionLabels = Record<string, Record<string, string>>

const asObject = (value: any): Record<string, any> => {
    if (typeof value === 'string') {
        try { value = JSON.parse(value) } catch { return {} }
    }
    return value && typeof value === 'object' && !Array.isArray(value) ? value : {}
}

const positiveId = (value: any) => {
    const id = Number(value)
    return Number.isSafeInteger(id) && id > 0 ? id : 0
}

const templateIdOf = (device: Device) => positiveId(device.check_template_id)
    || positiveId(asObject(asObject(device.info).check_meta).template_id)

const deviceKey = (device: Device) => `${positiveId(device.id)}:${templateIdOf(device)}`

/** 每台设备独立解释选项值；只在一次加载内复用明确相同模板的请求。 */
export function useDeviceOptionLabels() {
    const deviceLabels = ref<Record<string, OptionLabels>>({})
    let version = 0

    const resetOptionLabels = () => {
        version++
        deviceLabels.value = {}
    }

    const fetchLabels = async (device: Device): Promise<OptionLabels> => {
        const templateId = templateIdOf(device)
        const deviceId = positiveId(device.id)
        if (!templateId && !deviceId) return {}
        try {
            const res: any = await getCheckTemplateSchema(templateId ? { template_id: templateId } : { device_id: deviceId })
            const data = res?.data || {}
            if (templateId) {
                if (positiveId(data.template?.id) !== templateId) return {}
            } else if (!data.resolve?.matched || data.resolve.source_type !== 'model_dict') {
                // 全局默认模板不能证明它就是这台设备保存编号时使用的模板。
                return {}
            }
            const map: OptionLabels = Object.create(null)
            const groups = Array.isArray(data.groups) ? data.groups : []
            groups.forEach((group: any) => {
                const fields = Array.isArray(group.fields) ? group.fields : []
                fields.forEach((field: any) => {
                    if (!field.field_key || !Array.isArray(field.options)) return
                    const options: Record<string, string> = Object.create(null)
                    field.options.forEach((option: any) => {
                        if (option.value === undefined || option.value === null) return
                        options[String(option.value)] = String(option.label || option.name || option.value)
                    })
                    map[String(field.field_key)] = options
                })
            })
            return map
        } catch {
            // 单台解析失败时保留原值，不能借用同订单其他设备的字典。
            return {}
        }
    }

    const loadOptionLabels = async (devices: Device[]) => {
        resetOptionLabels()
        const currentVersion = version
        const requests = new Map<string, Promise<OptionLabels>>()
        let cursor = 0
        const worker = async () => {
            while (cursor < devices.length && currentVersion === version) {
                const device = devices[cursor++]
                const templateId = templateIdOf(device)
                const requestKey = templateId ? `template:${templateId}` : `device:${positiveId(device.id)}`
                const key = deviceKey(device)
                if (!requests.has(requestKey)) requests.set(requestKey, fetchLabels(device))
                const map = await requests.get(requestKey)!
                if (currentVersion === version) deviceLabels.value[key] = map
            }
        }
        // 避免多设备订单瞬间产生大量并发请求。
        await Promise.all(Array.from({ length: Math.min(devices.length, 4) }, () => worker()))
    }

    const resolveOptionLabel = (device: Device, fieldKey: string, value: any): string => {
        if (value === undefined || value === null || value === '') return ''
        const options = deviceLabels.value[deviceKey(device)]?.[fieldKey]
        const resolve = (item: any) => options?.[String(item)] ?? String(item)
        return Array.isArray(value) ? value.map(resolve).join('、') : resolve(value)
    }

    return { loadOptionLabels, resolveOptionLabel, resetOptionLabels }
}
