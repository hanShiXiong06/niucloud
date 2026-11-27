import * as echarts from 'echarts'
import type { ECharts, EChartsOption, SetOptionOpts } from 'echarts'
import { nextTick, onBeforeUnmount, onMounted, shallowRef, unref, watch, type Ref } from 'vue'
import { withHsxChartTheme } from '../charts'

export interface UseChartOptions {
    renderer?: 'canvas' | 'svg'
    autoResize?: boolean
    observeTheme?: boolean
    setOption?: SetOptionOpts
    loading?: Ref<boolean>
    onReady?: (instance: ECharts) => void
}

/** 管理 ECharts 初始化、明暗主题、ResizeObserver、loading 与销毁。 */
export function useChart(
    container: Ref<HTMLElement | null>,
    option: Ref<EChartsOption>,
    options: UseChartOptions = {}
) {
    const instance = shallowRef<ECharts | null>(null)
    let resizeObserver: ResizeObserver | null = null
    let themeObserver: MutationObserver | null = null

    const render = (nextOption: EChartsOption = unref(option)) => {
        if (!instance.value || !nextOption) return
        instance.value.setOption(withHsxChartTheme(nextOption, container.value), options.setOption || { notMerge: false, lazyUpdate: true })
    }

    const resize = () => instance.value?.resize()

    const init = async () => {
        await nextTick()
        const element = container.value
        if (!element || instance.value) return instance.value
        instance.value = echarts.init(element, undefined, { renderer: options.renderer || 'canvas' })
        render()
        if (options.loading?.value) instance.value.showLoading('default', { text: '加载中…' })
        options.onReady?.(instance.value)

        if (options.autoResize !== false && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(resize)
            resizeObserver.observe(element)
        }
        if (options.observeTheme !== false && typeof MutationObserver !== 'undefined') {
            themeObserver = new MutationObserver(() => render())
            themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] })
        }
        return instance.value
    }

    const dispose = () => {
        resizeObserver?.disconnect()
        themeObserver?.disconnect()
        resizeObserver = null
        themeObserver = null
        instance.value?.dispose()
        instance.value = null
    }

    watch(option, (value) => render(value), { deep: true })
    if (options.loading) {
        watch(options.loading, (loading) => {
            if (!instance.value) return
            if (loading) instance.value.showLoading('default', { text: '加载中…' })
            else instance.value.hideLoading()
        })
    }
    onMounted(init)
    onBeforeUnmount(dispose)

    return { instance, init, render, resize, dispose }
}
