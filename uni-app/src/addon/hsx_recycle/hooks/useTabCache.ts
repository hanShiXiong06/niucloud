import { ref } from 'vue'

/**
 * Tab 缓存管理（插件内可复用）
 * 用于在页面切换时保持 Tab 状态
 */
export function useTabCache(cacheKey: string, defaultTab: number = 0) {
  // 从缓存中读取 Tab 状态
  const getCachedTab = (): number => {
    try {
      const cached = uni.getStorageSync(cacheKey)
      return cached !== undefined && cached !== null ? Number(cached) : defaultTab
    } catch (e) {
      console.error('读取标签缓存失败:', e)
      return defaultTab
    }
  }

  const currentTab = ref(getCachedTab())

  // 切换 Tab 并保存到缓存
  const switchTab = (index: number) => {
    currentTab.value = index

    // 保存到缓存
    try {
      uni.setStorageSync(cacheKey, index)
    } catch (e) {
      console.error('保存标签缓存失败:', e)
    }
  }

  return {
    currentTab,
    switchTab
  }
}
