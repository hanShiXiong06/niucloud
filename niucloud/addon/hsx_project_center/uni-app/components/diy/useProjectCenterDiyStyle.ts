import { computed, type ComputedRef } from 'vue'
import { img } from '@/utils/common'

type DiyComponentRef = ComputedRef<Record<string, any>>

const radiusValue = (value: unknown) => {
    const number = Number(value)
    return Number.isFinite(number) ? Math.max(0, number) * 2 + 'rpx' : undefined
}

/**
 * 消费牛云 DIY 通用组件样式，保持项目中心组件与装修器的配置契约一致。
 */
export const useProjectCenterDiyStyle = (component: DiyComponentRef, fallbackColor: ComputedRef<string>) => {
    const cardStyle = computed<Record<string, string>>(() => {
        const value = component.value || {}
        const style: Record<string, string> = { position: 'relative', backgroundColor: fallbackColor.value }
        if (value.componentStartBgColor) {
            style.background = value.componentEndBgColor
                ? `linear-gradient(${value.componentGradientAngle || 'to bottom'},${value.componentStartBgColor},${value.componentEndBgColor})`
                : String(value.componentStartBgColor)
        }
        if (value.componentBgUrl) {
            const url = String(img(value.componentBgUrl)).replace(/'/g, "\\'")
            style.backgroundImage = `url('${url}')`
            style.backgroundSize = 'cover'
            style.backgroundPosition = 'center'
            style.backgroundRepeat = 'no-repeat'
        }
        const topRadius = radiusValue(value.topRounded)
        const bottomRadius = radiusValue(value.bottomRounded)
        if (topRadius !== undefined) {
            style.borderTopLeftRadius = topRadius
            style.borderTopRightRadius = topRadius
        }
        if (bottomRadius !== undefined) {
            style.borderBottomLeftRadius = bottomRadius
            style.borderBottomRightRadius = bottomRadius
        }
        return style
    })

    const maskAlpha = computed(() => {
        const value = Number(component.value?.componentBgAlpha || 0)
        return Number.isFinite(value) ? Math.min(1, Math.max(0, value / 10)) : 0
    })
    const showMask = computed(() => Boolean(component.value?.componentBgUrl) && maskAlpha.value > 0)
    const maskStyle = computed(() => ({ backgroundColor: `rgba(0,0,0,${maskAlpha.value})` }))

    return { cardStyle, showMask, maskStyle }
}

/** 将颜色选择器产生的 hex/rgb/rgba 颜色安全转换为指定透明度。 */
export const colorWithAlpha = (input: unknown, alpha = 0.08, fallback = '#315CF5') => {
    const color = String(input || fallback).trim()
    const opacity = Math.min(1, Math.max(0, Number(alpha) || 0))
    const hex = color.match(/^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/i)
    if (hex) {
        let value = hex[1]
        if (value.length === 3 || value.length === 4) value = value.slice(0, 3).split('').map(char => char + char).join('')
        else value = value.slice(0, 6)
        const number = Number.parseInt(value, 16)
        return `rgba(${(number >> 16) & 255},${(number >> 8) & 255},${number & 255},${opacity})`
    }
    const rgb = color.match(/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)/i)
    if (rgb) return `rgba(${Math.min(255, Number(rgb[1]))},${Math.min(255, Number(rgb[2]))},${Math.min(255, Number(rgb[3]))},${opacity})`
    return colorWithAlpha(fallback, opacity, '#315CF5')
}
