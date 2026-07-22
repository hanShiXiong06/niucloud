import { reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import {
    apiThirdPartyConfig,
    apiThirdPartyConfigOverview,
    apiThirdPartyConfigSave,
    apiThirdPartyConfigTest
} from '@/addon/hsx_recycle/api/third_party'

const clone = <T>(value: T): T => JSON.parse(JSON.stringify(value))

export function useThirdPartyServiceConfig(capability: string, defaults: Record<string, any>) {
    const loading = ref(false)
    const saving = ref(false)
    const testing = ref(false)
    const form = reactive<any>(clone(defaults))
    const capabilityInfo = ref<any>({})

    const load = async () => {
        loading.value = true
        try {
            const [configRes, overviewRes] = await Promise.all([
                apiThirdPartyConfig(),
                apiThirdPartyConfigOverview()
            ])
            Object.keys(form).forEach(key => delete form[key])
            Object.assign(form, clone(configRes.data?.[capability] || defaults))
            capabilityInfo.value = (overviewRes.data?.capabilities || [])
                .find((item: any) => item.key === capability) || {}
        } finally {
            loading.value = false
        }
    }

    const save = async () => {
        saving.value = true
        try {
            await apiThirdPartyConfigSave({ [capability]: clone(form) })
            ElMessage.success('配置已保存并生效')
            await load()
        } finally {
            saving.value = false
        }
    }

    const test = async () => {
        testing.value = true
        try {
            const res = await apiThirdPartyConfigTest(capability)
            const data = res.data || {}
            if (data.success) ElMessage.success(data.message || '连接测试成功')
            else ElMessage.warning(data.message || '当前配置不可用')
            await load()
            return data
        } catch (error: any) {
            ElMessage.error(error?.message || '连接测试失败')
            return null
        } finally {
            testing.value = false
        }
    }

    return { form, loading, saving, testing, capabilityInfo, load, save, test }
}
