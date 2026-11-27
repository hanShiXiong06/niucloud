import {
    computed,
    inject,
    onBeforeUnmount,
    onMounted,
    provide,
    ref,
    type ComputedRef,
    type InjectionKey,
    type Ref
} from 'vue'
import type {
    MobileAdaptiveHeightClass,
    MobileAdaptiveSnapshot,
    MobileAdaptiveWidthClass,
    MobileResponsiveValue,
    MobileSafeAreaInsets
} from '../types'

export interface UseAdaptiveLayoutOptions {
    initialWidth?: number
    initialHeight?: number
    mediumBreakpoint?: number
    expandedBreakpoint?: number
    largeBreakpoint?: number
    extraLargeBreakpoint?: number
    listen?: boolean
}

export interface MobileAdaptiveLayoutState {
    windowWidth: Ref<number>
    windowHeight: Ref<number>
    pixelRatio: Ref<number>
    safeAreaInsets: Ref<MobileSafeAreaInsets>
    widthClass: ComputedRef<MobileAdaptiveWidthClass>
    heightClass: ComputedRef<MobileAdaptiveHeightClass>
    isCompact: ComputedRef<boolean>
    isMedium: ComputedRef<boolean>
    isExpanded: ComputedRef<boolean>
    isWide: ComputedRef<boolean>
    isLandscape: ComputedRef<boolean>
    snapshot: ComputedRef<MobileAdaptiveSnapshot>
    refresh: () => MobileAdaptiveSnapshot
    start: () => void
    stop: () => void
}

const defaultInsets = (): MobileSafeAreaInsets => ({ top: 0, right: 0, bottom: 0, left: 0 })
const adaptiveLayoutKey: InjectionKey<MobileAdaptiveLayoutState> = Symbol('hsx-adaptive-layout')

/** 兼容微信不同基础库的 safeAreaInsets / safeArea 返回结构。 */
export function resolveMobileSafeAreaInsets(info: any, width: number, height: number): MobileSafeAreaInsets {
    const direct = info?.safeAreaInsets || {}
    const safeArea = info?.safeArea || {}
    const number = (value: unknown) => Number.isFinite(Number(value)) ? Number(value) : 0
    // 旧版微信的 safeArea 使用屏幕坐标，新版 safeAreaInsets 才是可直接使用的边距。
    const coordinateWidth = number(info?.screenWidth) || width
    const coordinateHeight = number(info?.screenHeight) || height
    const derivedRight = safeArea.right === undefined ? 0 : Math.max(0, coordinateWidth - number(safeArea.right))
    const derivedBottom = safeArea.bottom === undefined ? 0 : Math.max(0, coordinateHeight - number(safeArea.bottom))

    return {
        top: Math.max(0, number(direct.top), number(safeArea.top)),
        right: Math.max(0, number(direct.right), derivedRight),
        bottom: Math.max(0, number(direct.bottom), derivedBottom),
        left: Math.max(0, number(direct.left), number(safeArea.left))
    }
}

export function classifyAdaptiveWidth(
    width: number,
    breakpoints: Pick<UseAdaptiveLayoutOptions, 'mediumBreakpoint' | 'expandedBreakpoint' | 'largeBreakpoint' | 'extraLargeBreakpoint'> = {}
): MobileAdaptiveWidthClass {
    const medium = breakpoints.mediumBreakpoint ?? 600
    const expanded = breakpoints.expandedBreakpoint ?? 840
    const large = breakpoints.largeBreakpoint ?? 1200
    const extraLarge = breakpoints.extraLargeBreakpoint ?? 1600
    if (width >= extraLarge) return 'extra-large'
    if (width >= large) return 'large'
    if (width >= expanded) return 'expanded'
    if (width >= medium) return 'medium'
    return 'compact'
}

export function classifyAdaptiveHeight(height: number): MobileAdaptiveHeightClass {
    if (height >= 900) return 'expanded'
    if (height >= 480) return 'medium'
    return 'compact'
}

export function resolveAdaptiveValue<T>(
    value: MobileResponsiveValue<T> | undefined,
    widthClass: MobileAdaptiveWidthClass,
    fallback: T
): T {
    if (value === undefined) return fallback
    if (typeof value !== 'object' || value === null || Array.isArray(value)) return value as T
    const values = value as Partial<Record<MobileAdaptiveWidthClass, T>>
    const order: MobileAdaptiveWidthClass[] = ['compact', 'medium', 'expanded', 'large', 'extra-large']
    const index = order.indexOf(widthClass)
    for (let current = index; current >= 0; current -= 1) {
        const resolved = values[order[current]]
        if (resolved !== undefined) return resolved
    }
    return fallback
}

function getWindowSnapshot(fallbackWidth: number, fallbackHeight: number): MobileAdaptiveSnapshot {
    const fallback: MobileAdaptiveSnapshot = {
        windowWidth: fallbackWidth,
        windowHeight: fallbackHeight,
        pixelRatio: 1,
        safeAreaInsets: defaultInsets(),
        widthClass: classifyAdaptiveWidth(fallbackWidth),
        heightClass: classifyAdaptiveHeight(fallbackHeight),
        isLandscape: fallbackWidth > fallbackHeight
    }
    if (typeof uni === 'undefined' || typeof uni.getWindowInfo !== 'function') return fallback
    try {
        const info = uni.getWindowInfo() as any
        const width = Number(info.windowWidth) || fallbackWidth
        const height = Number(info.windowHeight) || fallbackHeight
        return {
            windowWidth: width,
            windowHeight: height,
            pixelRatio: Number(info.pixelRatio) || 1,
            safeAreaInsets: resolveMobileSafeAreaInsets(info, width, height),
            widthClass: classifyAdaptiveWidth(width),
            heightClass: classifyAdaptiveHeight(height),
            isLandscape: width > height
        }
    } catch {
        return fallback
    }
}

export function useAdaptiveLayout(options: UseAdaptiveLayoutOptions = {}): MobileAdaptiveLayoutState {
    const initialWidth = options.initialWidth ?? 375
    const initialHeight = options.initialHeight ?? 667
    const initial = getWindowSnapshot(initialWidth, initialHeight)
    const windowWidth = ref(initial.windowWidth)
    const windowHeight = ref(initial.windowHeight)
    const pixelRatio = ref(initial.pixelRatio)
    const safeAreaInsets = ref(initial.safeAreaInsets)
    let listening = false

    const breakpointOptions = {
        mediumBreakpoint: options.mediumBreakpoint,
        expandedBreakpoint: options.expandedBreakpoint,
        largeBreakpoint: options.largeBreakpoint,
        extraLargeBreakpoint: options.extraLargeBreakpoint
    }
    const widthClass = computed(() => classifyAdaptiveWidth(windowWidth.value, breakpointOptions))
    const heightClass = computed(() => classifyAdaptiveHeight(windowHeight.value))
    const isCompact = computed(() => widthClass.value === 'compact')
    const isMedium = computed(() => widthClass.value === 'medium')
    const isExpanded = computed(() => ['expanded', 'large', 'extra-large'].includes(widthClass.value))
    const isWide = computed(() => !isCompact.value)
    const isLandscape = computed(() => windowWidth.value > windowHeight.value)
    const snapshot = computed<MobileAdaptiveSnapshot>(() => ({
        windowWidth: windowWidth.value,
        windowHeight: windowHeight.value,
        pixelRatio: pixelRatio.value,
        safeAreaInsets: safeAreaInsets.value,
        widthClass: widthClass.value,
        heightClass: heightClass.value,
        isLandscape: isLandscape.value
    }))

    const apply = (next: MobileAdaptiveSnapshot) => {
        windowWidth.value = next.windowWidth
        windowHeight.value = next.windowHeight
        pixelRatio.value = next.pixelRatio
        safeAreaInsets.value = next.safeAreaInsets
    }
    const refresh = () => {
        const next = getWindowSnapshot(windowWidth.value, windowHeight.value)
        apply(next)
        return snapshot.value
    }
    const onResize = (event: any) => {
        const size = event?.size || event || {}
        windowWidth.value = Number(size.windowWidth) || windowWidth.value
        windowHeight.value = Number(size.windowHeight) || windowHeight.value
        // 折叠、旋转和分屏后安全区也可能变化，重新读取完整窗口信息。
        refresh()
    }
    const start = () => {
        refresh()
        if (listening || options.listen === false || typeof uni === 'undefined' || typeof uni.onWindowResize !== 'function') return
        uni.onWindowResize(onResize)
        listening = true
    }
    const stop = () => {
        if (!listening || typeof uni === 'undefined' || typeof uni.offWindowResize !== 'function') return
        uni.offWindowResize(onResize)
        listening = false
    }

    onMounted(start)
    onBeforeUnmount(stop)

    return {
        windowWidth,
        windowHeight,
        pixelRatio,
        safeAreaInsets,
        widthClass,
        heightClass,
        isCompact,
        isMedium,
        isExpanded,
        isWide,
        isLandscape,
        snapshot,
        refresh,
        start,
        stop
    }
}

export function provideAdaptiveLayout(layout: MobileAdaptiveLayoutState) {
    provide(adaptiveLayoutKey, layout)
    return layout
}

export function useAdaptiveContext(options: UseAdaptiveLayoutOptions = {}) {
    return inject(adaptiveLayoutKey, null) || useAdaptiveLayout(options)
}
