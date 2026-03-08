import themeConfig from './theme.json'

/**
 * 获取主题色配置
 * @returns CSS 变量对象
 */
export function getThemeColor() {
    return themeConfig
}

/**
 * 应用主题色到元素
 * @returns 样式对象
 */
export function applyThemeColor() {
    const theme: Record<string, string> = {}
    Object.keys(themeConfig).forEach(key => {
        theme[key] = themeConfig[key as keyof typeof themeConfig]
    })
    return theme
}

/**
 * 获取单个主题色值
 * @param key 主题色键名
 * @returns 颜色值
 */
export function getThemeColorValue(key: keyof typeof themeConfig): string {
    return themeConfig[key] || ''
}
