import { computed, ref } from 'vue'
import { getOrderSubmitConfig } from '../api/order'
import { buildRecycleThemeVars, type RecycleThemeColors } from '../utils/theme'

const themeColors = ref<RecycleThemeColors>({})
let loadingPromise: Promise<void> | null = null

export function useRecyclePageTheme() {
  const themeVars = computed(() => buildRecycleThemeVars(themeColors.value))

  const loadTheme = async () => {
    if (loadingPromise) return loadingPromise

    loadingPromise = getOrderSubmitConfig()
      .then((res: any) => {
        themeColors.value = res?.data?.price_detail_theme?.colors || {}
      })
      .catch((error) => {
        console.error('获取回收主题配置失败：', error)
        themeColors.value = {}
      })
      .finally(() => {
        loadingPromise = null
      })

    return loadingPromise
  }

  return {
    themeVars,
    loadTheme
  }
}
