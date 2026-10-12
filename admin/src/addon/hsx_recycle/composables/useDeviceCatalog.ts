import { computed, onMounted, ref } from 'vue'
import { getDeviceCatalogConfig, type CatalogPolicy } from '@/addon/hsx_recycle/api/device_catalog'

/** 不缓存跨站点权限；未读到配置时禁用维护动作，避免误操作。 */
export function useDeviceCatalog() {
    const catalogPolicy = ref<CatalogPolicy>()
    const catalogError = ref('')
    const catalogReadOnly = computed(() => !catalogPolicy.value || catalogPolicy.value.read_only)
    const loadCatalog = async () => {
        catalogError.value = ''
        try {
            const response = await getDeviceCatalogConfig()
            if (!response?.data || typeof response.data.read_only !== 'boolean') throw new Error('invalid catalog config')
            catalogPolicy.value = response.data
        } catch {
            catalogPolicy.value = undefined
            catalogError.value = '资料库配置加载失败，维护操作暂不可用，请重试。'
        }
    }
    onMounted(loadCatalog)
    return { catalogPolicy, catalogReadOnly, catalogError, loadCatalog }
}
