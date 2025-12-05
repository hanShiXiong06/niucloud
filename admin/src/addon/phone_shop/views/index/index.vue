<template>
    <div class="main-container">
        <!-- 实时概况 -->
        <el-card shadow="never" class="!border-none">
            <template #header>
                <span class="text-lg font-extrabold mr-[10px]">{{t('realtimeOverview')}}</span>
                <span class="text-sm text-[#a19f98]">{{t('updateTime')}}</span>
                <span class="text-sm text-[#a19f98]">{{ time }}</span>
            </template>
            <el-row :gutter="10">
                <el-col :span="8">
                    <div class="ml-[10px]">
                        <div class="text-sm text-[#a19f98] leading-8">
                            <el-statistic :value="statToday.listed_goods_num || 0">
                                <template #title>
                                    <div style="display: inline-flex; align-items: center">
                                        <span class="mr-[5px]">{{t('todayListedGoods')}}</span>
                                        <el-tooltip class="box-item" effect="light" :content="t('todayListedGoods')" placement="top">
                                            <el-icon>
                                                <QuestionFilled />
                                            </el-icon>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-statistic>
                        </div>
                        <div class="text-sm text-[#a19f98] leading-8">
                            <span>{{t('yesterday')}}</span>
                            <span>{{statYesterday.listed_goods_num || 0}}</span>
                        </div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="ml-[10px]">
                        <div class="text-sm text-[#a19f98] leading-8">
                            <el-statistic :value="statToday.delisted_goods_num || 0">
                                <template #title>
                                    <div style="display: inline-flex; align-items: center">
                                        <span class="mr-[5px]">{{t('todayDelistedGoods')}}</span>
                                        <el-tooltip class="box-item" effect="light" :content="t('todayDelistedGoods')" placement="top">
                                            <el-icon>
                                                <QuestionFilled />
                                            </el-icon>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-statistic>
                        </div>
                        <div class="text-sm text-[#a19f98] leading-8">
                            <span>{{t('yesterday')}}</span>
                            <span>{{statYesterday.delisted_goods_num || 0}}</span>
                        </div>
                    </div>
                </el-col>
                <el-col :span="8">
                    <div class="ml-[10px]">
                        <div class="text-sm text-[#a19f98] leading-8">
                            <el-statistic :value="statToday.access_sum || 0">
                                <template #title>
                                    <div style="display: inline-flex; align-items: center">
                                        <span class="mr-[5px]">{{t('todayBrowseCount')}}</span>
                                        <el-tooltip class="box-item" effect="light" :content="t('todayBrowseCount')" placement="top">
                                            <el-icon>
                                                <QuestionFilled />
                                            </el-icon>
                                        </el-tooltip>
                                    </div>
                                </template>
                            </el-statistic>
                        </div>
                        <div class="text-sm text-[#a19f98] leading-8">
                            <span>{{t('yesterday')}}</span>
                            <span>{{statYesterday.access_sum || 0}}</span>
                        </div>
                        <div class="text-sm text-[#a19f98] leading-8 mt-[15px]">
                            <el-statistic :title="t('browseTotal')" :value="statTotal.access_sum || 0" />
                        </div>
                    </div>
                </el-col>
            </el-row>
        </el-card>
        <!-- 实时概况 end -->

        <!-- 代办事项 -->
        <el-card shadow="never" class="mt-[15px] !border-none">
            <template #header>
                <span class="text-lg font-extrabold">{{t('agentMatters')}}</span>
            </template>
            <el-row :gutter="10">
                <el-col :span="6" class="cursor-pointer" @click="router.push({ path: '/phone_shop/goods/list', query: { status: 1 }})">
                    <el-statistic :value="statGoods.sale_goods_num">
                        <template #title>
                            <div style="display: inline-flex; align-items: center">{{t('saleGoodsNum')}}</div>
                        </template>
                    </el-statistic>
                </el-col>
                <el-col :span="6" class="cursor-pointer" @click="router.push({ path: '/phone_shop/goods/list', query: { status: 0 }})">
                    <el-statistic :value="statGoods.warehouse_goods_num">
                        <template #title>
                            <div style="display: inline-flex; align-items: center">{{t('warehouseGoodsNum')}}</div>
                        </template>
                    </el-statistic>
                </el-col>
                <el-col :span="6" class="cursor-pointer" @click="router.push({ path: '/phone_shop/goods/list', query: { status: 1 }})">
                    <el-statistic :value="statGoods.sale_goods_over_30_unsold_num">
                        <template #title>
                            <div style="display: inline-flex; align-items: center">{{t('listedOver30')}}</div>
                        </template>
                    </el-statistic>
                </el-col>
                <el-col :span="6" class="cursor-pointer" @click="router.push({ path: '/phone_shop/goods/list', query: { status: 1 }})">
                    <el-statistic :value="statGoods.sale_goods_over_90_unsold_num">
                        <template #title>
                            <div style="display: inline-flex; align-items: center">{{t('listedOver90')}}</div>
                        </template>
                    </el-statistic>
                </el-col>
            </el-row>
        </el-card>
        <!-- 代办事项 end -->

        <!-- 商品上下架趋势 -->
        <el-row :gutter="15" class="mt-[15px]">
            <el-col :span="12">
                <el-card shadow="never" class="!border-none">
                    <template #header>
                        <span class="text-lg font-extrabold">{{t('categoryUnshelfComparison')}}</span>
                    </template>
                    <div ref="unshelfChartRef" :style="{ width: '100%', height: '300px' }"></div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="!border-none">
                    <template #header>
                        <span class="text-lg font-extrabold">{{t('categoryShelfComparison')}}</span>
                    </template>
                    <div ref="shelfChartRef" :style="{ width: '100%', height: '300px' }"></div>
                </el-card>
            </el-col>
        </el-row>
        <!-- 商品上下架趋势 end -->
    </div>
</template>

<script lang="ts" setup>
import { ref, onBeforeUnmount, nextTick } from 'vue'
import { t } from '@/lang'
import {
    getShopCountList,
    getShopTodayCountList,
    getShopYesterdayCountList,
    getShopStat,
    getShopGoodsStat
} from '@/addon/phone_shop/api/shop'
import * as echarts from 'echarts'
import type { EChartsOption } from 'echarts'
import { useRouter } from 'vue-router'
import { QuestionFilled } from '@element-plus/icons-vue'

const router = useRouter()
const unshelfChartRef = ref<HTMLElement | null>(null)
const shelfChartRef = ref<HTMLElement | null>(null)

interface StatTotalType {
    order_num: number
    sale_money: number
    refund_money: number
    access_sum: number
}
interface StatDayType {
    order_num: number
    sale_money: number
    refund_money: number
    access_sum: number
    listed_goods_num: number
    delisted_goods_num: number
}
interface StatGoodsType {
    sale_goods_num: number
    warehouse_goods_num: number
    sale_goods_over_30_unsold_num: number
    sale_goods_over_90_unsold_num: number
}
interface TrendSeries {
    name: string
    data: number[]
}
interface TrendPayload {
    legend: string[]
    categories: string[]
    series: TrendSeries[]
}

const defaultTotal: StatTotalType = { order_num: 0, sale_money: 0, refund_money: 0, access_sum: 0 }
const defaultDay: StatDayType = { order_num: 0, sale_money: 0, refund_money: 0, access_sum: 0, listed_goods_num: 0, delisted_goods_num: 0 }
const defaultGoods: StatGoodsType = {
    sale_goods_num: 0,
    warehouse_goods_num: 0,
    sale_goods_over_30_unsold_num: 0,
    sale_goods_over_90_unsold_num: 0
}

const statTotal = ref<StatTotalType>({ ...defaultTotal })
const statToday = ref<StatDayType>({ ...defaultDay })
const statYesterday = ref<StatDayType>({ ...defaultDay })
const statGoods = ref<StatGoodsType>({ ...defaultGoods })
const categoryTrend = ref<{ shelf: TrendPayload; unshelf: TrendPayload }>({
    shelf: { legend: [], categories: [], series: [] },
    unshelf: { legend: [], categories: [], series: [] }
})

const normalizeNumberMap = (payload: Record<string, any>) => {
    const result: Record<string, number> = {}
    Object.keys(payload || {}).forEach((key) => {
        const value = Number(payload[key] ?? 0)
        result[key] = Number.isNaN(value) ? 0 : value
    })
    return result
}

const renderTrendChart = (el: HTMLElement | null, trend: TrendPayload, emptyText: string) => {
    if (!el) return
    let chart = echarts.getInstanceByDom(el)
    if (!chart) chart = echarts.init(el)

    // 过滤掉所有值为0的分类
    const validIndices: number[] = []
    const categoryCounts = new Array(trend.categories.length).fill(0)
    
    // 计算每个分类在所有系列中的总和
    trend.series.forEach((series) => {
        series.data.forEach((value, index) => {
            if (value > 0) {
                categoryCounts[index] += value
            }
        })
    })
    
    // 找出有数据的分类索引
    categoryCounts.forEach((count, index) => {
        if (count > 0) {
            validIndices.push(index)
        }
    })

    // 如果没有有效数据，显示空状态
    if (validIndices.length === 0) {
        const option: EChartsOption = {
            legend: { data: [] },
            xAxis: { type: 'category', data: [] },
            yAxis: { type: 'value' },
            series: [],
            graphic: [
                {
                    type: 'text',
                    left: 'center',
                    top: 'middle',
                    style: {
                        text: emptyText,
                        fill: '#909399',
                        fontSize: 16
                    }
                }
            ]
        }
        chart.setOption(option, true)
        return
    }

    // 过滤分类和系列数据
    const filteredCategories = validIndices.map((idx) => trend.categories[idx])
    const filteredSeries = trend.series.map((series) => ({
        ...series,
        type: 'bar',
        barMaxWidth: 28,
        data: validIndices.map((idx) => series.data[idx] || 0)
    }))

    const option: EChartsOption = {
        tooltip: { 
            trigger: 'axis',
            formatter: (params: any) => {
                if (!params || params.length === 0) return ''
                let result = params[0].axisValue + '<br/>'
                params.forEach((item: any) => {
                    if (item.value > 0) {
                        result += `${item.seriesName}: ${item.value}<br/>`
                    }
                })
                return result
            }
        },
        legend: { data: trend.legend },
        xAxis: {
            type: 'category',
            data: filteredCategories,
            axisLabel: { 
                interval: 0,
                rotate: filteredCategories.length > 5 ? 45 : 0
            }
        },
        yAxis: { type: 'value' },
        series: filteredSeries
    }

    chart.setOption(option, true)
}

const refreshTrendCharts = () => {
    renderTrendChart(unshelfChartRef.value, categoryTrend.value.unshelf, '暂无数据')
    renderTrendChart(shelfChartRef.value, categoryTrend.value.shelf, '暂无数据')
}

const getStatInfoFn = async () => {
    const [totalRes, todayRes, yesterdayRes, goodsRes, trendRes] = await Promise.all([
        getShopCountList(),
        getShopTodayCountList(),
        getShopYesterdayCountList(),
        getShopGoodsStat(),
        getShopStat()
    ])

    statTotal.value = { ...defaultTotal, ...normalizeNumberMap(totalRes.data || {}) }
    statToday.value = { ...defaultDay, ...normalizeNumberMap(todayRes.data || {}) }
    statYesterday.value = { ...defaultDay, ...normalizeNumberMap(yesterdayRes.data || {}) }
    statGoods.value = { ...defaultGoods, ...normalizeNumberMap(goodsRes.data || {}) }

    const trendData = trendRes.data || {}
    const defaultTrend: TrendPayload = { legend: [], categories: [], series: [] }
    categoryTrend.value = {
        shelf: trendData.shelf || { ...defaultTrend },
        unshelf: trendData.unshelf || { ...defaultTrend }
    }

    await nextTick()
    refreshTrendCharts()
}
getStatInfoFn()

onBeforeUnmount(() => {
    if (unshelfChartRef.value) {
        const chart = echarts.getInstanceByDom(unshelfChartRef.value)
        chart?.dispose()
    }
    if (shelfChartRef.value) {
        const chart = echarts.getInstanceByDom(shelfChartRef.value)
        chart?.dispose()
    }
})

const time = ref('')
const nowTime = () => {
    const date = new Date()
    const year = date.getFullYear()
    const month = date.getMonth() + 1
    const day = date.getDate()
    const hh = checkTime(date.getHours())
    const mm = checkTime(date.getMinutes())
    const ss = checkTime(date.getSeconds())
    function checkTime (i:any) {
        if (i < 10) {
            return '0' + i
        }
        return i
    }
    time.value = year + '-' + month + '-' + day + ' ' + hh + ':' + mm + ':' + ss
}
nowTime()
</script>

<style lang="scss" scoped></style>
