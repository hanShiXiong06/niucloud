import { ref, computed } from 'vue'

/**
 * 主题色 Hook
 * 从后台获取主题色配置，动态应用到页面
 */
export const useThemeColor = () => {
  // 默认主题色（后台未配置时使用）
  const defaultTheme = {
    primary: '#1890ff',
    primaryLight: '#40a9ff',
    primaryDark: '#096dd9',
    gradientStart: '#1890ff',
    gradientEnd: '#096dd9'
  }

  const themeColor = ref(defaultTheme)

  // 从后台获取主题色配置
  const loadThemeColor = async () => {
    try {
      // TODO: 调用后台接口获取主题色配置
      // const res = await getThemeConfig()
      // themeColor.value = res.data

      // 临时从本地存储获取
      const localTheme = uni.getStorageSync('theme_color')
      if (localTheme) {
        themeColor.value = JSON.parse(localTheme)
      }
    } catch (error) {
      console.error('获取主题色失败:', error)
    }
  }

  // 生成 CSS 变量样式
  const themeStyle = computed(() => {
    return {
      '--primary-color': themeColor.value.primary,
      '--primary-light': themeColor.value.primaryLight,
      '--primary-dark': themeColor.value.primaryDark,
      '--gradient-start': themeColor.value.gradientStart,
      '--gradient-end': themeColor.value.gradientEnd,
      '--primary-gradient': `linear-gradient(135deg, ${themeColor.value.gradientStart} 0%, ${themeColor.value.gradientEnd} 100%)`
    }
  })

  return {
    themeColor,
    themeStyle,
    loadThemeColor
  }
}
