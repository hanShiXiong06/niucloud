import type { EChartsOption } from 'echarts'

export type HsxChartTone = 'primary' | 'success' | 'warning' | 'danger' | 'info'
export type HsxSimpleChartType = 'line' | 'bar'

export interface HsxChartSeries {
    name: string
    data: number[]
    color?: string
}

export interface HsxCartesianChartOptions {
    labels: Array<string | number>
    series: HsxChartSeries[]
    smooth?: boolean
    area?: boolean
    horizontal?: boolean
    showLegend?: boolean
    unit?: string
    boundaryGap?: boolean
    stack?: string
}

export interface HsxDonutChartOptions {
    data: Array<{ name: string, value: number, color?: string }>
    centerText?: string
    centerSubtext?: string
    showLegend?: boolean
    radius?: [string, string]
}

export interface HsxSparklineOptions {
    data: number[]
    type?: HsxSimpleChartType
    color?: string
    area?: boolean
    smooth?: boolean
}

export interface HsxChartThemeTokens {
    primary: string
    text: string
    secondaryText: string
    border: string
    surface: string
    palette: string[]
}

const fallbackPalette = ['#2563eb', '#22c55e', '#06b6d4', '#f59e0b', '#8b5cf6', '#ef4444']

function cssVariable(root: HTMLElement | null | undefined, name: string, fallback: string) {
    if (!root || typeof window === 'undefined') return fallback
    return window.getComputedStyle(root).getPropertyValue(name).trim() || fallback
}

export function getHsxChartTheme(root?: HTMLElement | null): HsxChartThemeTokens {
    return {
        primary: cssVariable(root, '--hsx-color-primary', '#2563eb'),
        text: cssVariable(root, '--hsx-text-primary', '#172033'),
        secondaryText: cssVariable(root, '--hsx-text-secondary', '#7b8799'),
        border: cssVariable(root, '--hsx-border-color', '#e2e8f0'),
        surface: cssVariable(root, '--hsx-bg-surface', '#ffffff'),
        palette: fallbackPalette
    }
}

function themedAxis(axis: any, tokens: HsxChartThemeTokens) {
    if (!axis) return axis
    const apply = (item: any) => ({
        ...item,
        axisLine: { lineStyle: { color: tokens.border }, ...(item?.axisLine || {}) },
        axisTick: { lineStyle: { color: tokens.border }, ...(item?.axisTick || {}) },
        axisLabel: { color: tokens.secondaryText, ...(item?.axisLabel || {}) },
        splitLine: { lineStyle: { color: tokens.border, type: 'dashed' }, ...(item?.splitLine || {}) },
        nameTextStyle: { color: tokens.secondaryText, ...(item?.nameTextStyle || {}) }
    })
    return Array.isArray(axis) ? axis.map(apply) : apply(axis)
}

/** 为任意 ECharts option 注入 HSX 明暗主题语义，不修改业务传入对象。 */
export function withHsxChartTheme(option: EChartsOption, root?: HTMLElement | null): EChartsOption {
    const tokens = getHsxChartTheme(root)
    const legend = option.legend as any
    const tooltip = option.tooltip as any
    return {
        ...option,
        color: option.color || tokens.palette,
        backgroundColor: 'transparent',
        textStyle: { color: tokens.text, ...((option.textStyle as any) || {}) },
        legend: legend === undefined ? legend : {
            textStyle: { color: tokens.secondaryText, ...(legend?.textStyle || {}) },
            ...legend
        },
        tooltip: tooltip === undefined ? tooltip : {
            backgroundColor: tokens.surface,
            borderColor: tokens.border,
            textStyle: { color: tokens.text },
            ...tooltip
        },
        xAxis: themedAxis(option.xAxis, tokens),
        yAxis: themedAxis(option.yAxis, tokens)
    }
}

function tooltipValue(unit = '') {
    return (params: any) => {
        const rows = Array.isArray(params) ? params : [params]
        const title = rows[0]?.axisValueLabel || rows[0]?.name || ''
        return [title, ...rows.map((item) => `${item.marker || ''}${item.seriesName || ''} <b>${item.value}${unit}</b>`)].join('<br/>')
    }
}

export function createLineChartOption(options: HsxCartesianChartOptions): EChartsOption {
    return {
        tooltip: { trigger: 'axis', formatter: tooltipValue(options.unit) },
        legend: options.showLegend === false ? undefined : { top: 0, right: 0 },
        grid: { top: options.showLegend === false ? 18 : 42, right: 18, bottom: 12, left: 8, containLabel: true },
        xAxis: { type: 'category', data: options.labels, boundaryGap: options.boundaryGap ?? false },
        yAxis: { type: 'value' },
        series: options.series.map((item, index) => ({
            name: item.name,
            type: 'line',
            data: item.data,
            smooth: options.smooth ?? true,
            symbol: 'circle',
            symbolSize: 6,
            showSymbol: false,
            lineStyle: { width: 3, color: item.color },
            itemStyle: { color: item.color },
            areaStyle: options.area ? { opacity: index === 0 ? 0.16 : 0.08, color: item.color } : undefined,
            emphasis: { focus: 'series' }
        }))
    }
}

export function createBarChartOption(options: HsxCartesianChartOptions): EChartsOption {
    const categoryAxis: any = { type: 'category', data: options.labels, axisTick: { show: false } }
    const valueAxis: any = { type: 'value' }
    return {
        tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, formatter: tooltipValue(options.unit) },
        legend: options.showLegend === false ? undefined : { top: 0, right: 0 },
        grid: { top: options.showLegend === false ? 18 : 42, right: 18, bottom: 12, left: 8, containLabel: true },
        xAxis: options.horizontal ? valueAxis : categoryAxis,
        yAxis: options.horizontal ? categoryAxis : valueAxis,
        series: options.series.map((item) => ({
            name: item.name,
            type: 'bar',
            data: item.data,
            stack: options.stack,
            barMaxWidth: 34,
            itemStyle: { color: item.color, borderRadius: options.horizontal ? [0, 6, 6, 0] : [6, 6, 0, 0] },
            emphasis: { focus: 'series' }
        }))
    }
}

export function createDonutChartOption(options: HsxDonutChartOptions): EChartsOption {
    const total = options.data.reduce((sum, item) => sum + Number(item.value || 0), 0)
    return {
        tooltip: { trigger: 'item', formatter: '{b}<br/>{c} · {d}%' },
        legend: options.showLegend === false ? undefined : { bottom: 0, left: 'center' },
        title: options.centerText || options.centerSubtext ? {
            text: options.centerText || String(total),
            subtext: options.centerSubtext,
            left: 'center',
            top: '42%',
            textAlign: 'center',
            textStyle: { fontSize: 20, fontWeight: 700 },
            subtextStyle: { fontSize: 12 }
        } : undefined,
        series: [{
            type: 'pie',
            radius: options.radius || ['58%', '78%'],
            center: ['50%', options.showLegend === false ? '50%' : '44%'],
            avoidLabelOverlap: true,
            itemStyle: { borderRadius: 6 },
            label: { show: false },
            emphasis: { label: { show: true, fontSize: 13, fontWeight: 600 } },
            data: options.data.map((item) => ({ ...item, itemStyle: item.color ? { color: item.color } : undefined }))
        }]
    }
}

export function createSparklineOption(options: HsxSparklineOptions): EChartsOption {
    const color = options.color || '#2563eb'
    const isBar = options.type === 'bar'
    return {
        animationDuration: 500,
        grid: { top: 4, right: 2, bottom: 2, left: 2 },
        xAxis: { type: 'category', show: false, data: options.data.map((_, index) => index + 1) },
        yAxis: { type: 'value', show: false, scale: true },
        series: [{
            type: isBar ? 'bar' : 'line',
            data: options.data,
            smooth: options.smooth ?? true,
            symbol: 'none',
            barMaxWidth: 9,
            lineStyle: { width: 2.5, color },
            itemStyle: isBar ? { color, borderRadius: [4, 4, 0, 0] } : { color },
            areaStyle: !isBar && options.area !== false ? { color, opacity: 0.12 } : undefined,
            silent: true
        } as any]
    }
}
