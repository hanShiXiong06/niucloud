import type {
    MobileChartData,
    MobileChartMode,
    MobileChartPreset,
    MobileChartSeries,
    MobileChartType
} from '../types'

export const hsxMobileChartPalette = Object.freeze({
    light: ['#3c9cff', '#10b981', '#f97316', '#7c3aed', '#dc2626', '#0d9488'],
    dark: ['#60a5fa', '#34d399', '#fb923c', '#a78bfa', '#f87171', '#2dd4bf']
})

const isPlainObject = (value: unknown): value is Record<string, any> => {
    return Boolean(value) && Object.prototype.toString.call(value) === '[object Object]'
}

/**
 * uCharts 配置合并器。数组直接替换，函数与格式化器保持原引用。
 */
export function mergeMobileChartOptions<T extends Record<string, any>>(
    base: T,
    ...sources: Array<Record<string, any> | undefined>
): T {
    const target: Record<string, any> = { ...base }
    sources.forEach((source) => {
        if (!source) return
        Object.keys(source).forEach((key) => {
            const next = source[key]
            const current = target[key]
            if (isPlainObject(current) && isPlainObject(next)) {
                target[key] = mergeMobileChartOptions(current, next)
            } else {
                target[key] = Array.isArray(next) ? [...next] : next
            }
        })
    })
    return target as T
}

export function createMobileChartOptions(
    type: MobileChartType,
    overrides: Record<string, any> = {},
    mode: MobileChartMode = 'light'
) {
    const dark = mode === 'dark'
    const axisColor = dark ? '#94a3b8' : '#64748b'
    const gridColor = dark ? '#334155' : '#e6ebf2'
    const common = {
        color: [...hsxMobileChartPalette[mode]],
        padding: [12, 10, 8, 10],
        dataLabel: false,
        fontColor: axisColor,
        legend: { show: true, position: 'bottom', fontColor: axisColor },
        xAxis: { disableGrid: true, fontColor: axisColor, axisLineColor: gridColor },
        yAxis: {
            gridType: 'dash',
            dashLength: 4,
            gridColor,
            data: [{ min: 0, fontColor: axisColor, axisLineColor: gridColor }]
        }
    }
    const byType: Record<string, Record<string, any>> = {
        column: {
            legend: { show: false },
            extra: { column: { type: 'group', width: 14, activeBgColor: dark ? '#1e293b' : '#eef2ff' } }
        },
        bar: {
            legend: { show: false },
            xAxis: { disabled: true },
            yAxis: { disabled: false, fontColor: axisColor },
            extra: { bar: { type: 'group', width: 14, categoryGap: 8 } }
        },
        line: {
            legend: { show: false },
            extra: { line: { type: 'curve', width: 2, activeType: 'hollow' } }
        },
        area: {
            legend: { show: false },
            extra: { area: { type: 'curve', opacity: 0.18, addLine: true, width: 2 } }
        },
        pie: {
            padding: [8, 8, 8, 8],
            dataLabel: true,
            extra: { pie: { activeOpacity: 0.65, activeRadius: 8, labelWidth: 12 } }
        },
        ring: {
            padding: [8, 8, 8, 8],
            dataLabel: true,
            extra: { ring: { ringWidth: 24, activeOpacity: 0.65, activeRadius: 8, labelWidth: 12 } }
        }
    }
    return mergeMobileChartOptions(common, byType[type], overrides)
}

const normalizeSeries = (series: MobileChartSeries[] | number[], fallbackName: string): MobileChartSeries[] => {
    if (!series.length) return []
    if (typeof series[0] === 'number') {
        return [{ name: fallbackName, data: series as number[] }]
    }
    return series as MobileChartSeries[]
}

export function createMobileCartesianChart(
    type: Extract<MobileChartType, 'column' | 'bar' | 'line' | 'area'>,
    categories: Array<string | number>,
    series: MobileChartSeries[] | number[],
    options: Record<string, any> = {},
    mode: MobileChartMode = 'light'
): MobileChartPreset {
    return {
        type,
        chartData: { categories, series: normalizeSeries(series, '数据') },
        opts: createMobileChartOptions(type, options, mode)
    }
}

export const createMobileColumnChart = (
    categories: Array<string | number>,
    series: MobileChartSeries[] | number[],
    options: Record<string, any> = {},
    mode: MobileChartMode = 'light'
) => createMobileCartesianChart('column', categories, series, options, mode)

export const createMobileLineChart = (
    categories: Array<string | number>,
    series: MobileChartSeries[] | number[],
    options: Record<string, any> = {},
    mode: MobileChartMode = 'light'
) => createMobileCartesianChart('line', categories, series, options, mode)

export function createMobileRingChart(
    rows: Array<{ name: string, value: number }>,
    options: Record<string, any> = {},
    mode: MobileChartMode = 'light'
): MobileChartPreset {
    return {
        type: 'ring',
        chartData: { series: [{ name: '占比', data: rows }] },
        opts: createMobileChartOptions('ring', options, mode)
    }
}

export function hasMobileChartData(chartData?: MobileChartData | null) {
    if (!chartData || !Array.isArray(chartData.series)) return false
    return chartData.series.some((series) => Array.isArray(series?.data) && series.data.length > 0)
}
