/** Presentation patches only. Applying one is an explicit, confirmed editor action. */
export const leadHomePresets = [
    { key: 'classic', name: '经典图文', description: '上下图文卡片，保留原有插画素材' },
    { key: 'compact', name: '紧凑双栏', description: '并列入口，留出更多业务空间' },
    { key: 'clean', name: '简约留白', description: '白底文字与小图标，轻量清晰' },
    { key: 'soft', name: '浅色主题', description: '柔和浅色背景与图文入口' }
] as const
const base = {
    primaryColor: '',
    panelColor: '',
    titleColor: '#202124',
    textColor: '#606266',
    subtleColor: '#909399',
    iconColor: '#333333',
    borderColor: '#e4e8ef',
    buttonColor: '',
    buttonTextColor: '#ffffff',
    titleSize: 18,
    textSize: 14,
    contentPadding: 16,
    rowGap: 12,
    itemRadius: 12,
    buttonRadius: 6,
    componentStartBgColor: '#ffffff',
    componentEndBgColor: ''
}
export function leadHomePreset(key: string): Record<string, string | number> | null {
    if (!leadHomePresets.some((item) => item.key === key)) return null
    const details: Record<string, Record<string, string | number>> = {
        classic: { usePresetImages: 1, animate: 1, itemRadius: 12 },
        compact: { usePresetImages: 0, animate: 0, itemRadius: 8, rowGap: 10, borderColor: '#d9e0e7' },
        clean: {
            usePresetImages: 0,
            animate: 0,
            itemRadius: 0,
            contentPadding: 20,
            rowGap: 16,
            titleSize: 20,
            borderColor: 'transparent'
        },
        soft: {
            usePresetImages: 1,
            animate: 1,
            itemRadius: 16,
            componentStartBgColor: '#eef5ff',
            titleColor: '#243b5b',
            textColor: '#617994',
            subtleColor: '#7d91a8',
            iconColor: '#487faf',
            buttonColor: '#398aff',
            borderColor: '#cddff7'
        }
    }
    return { ...base, ...details[key], layoutPreset: key }
}
