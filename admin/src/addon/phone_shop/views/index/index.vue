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

        <!-- 订单趋势 -->
        <el-row :gutter="15" class="mt-[15px]">
            <el-col :span="12">
                <el-card shadow="never" class="!border-none">
                    <template #header>
                        <span class="text-lg font-extrabold">订单量趋势</span>
                    </template>
                    <div ref="visitStat" :style="{ width: '100%', height: '300px' }"></div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card shadow="never" class="!border-none">
                    <template #header>
                        <span class="text-lg font-extrabold">销售额（元）</span>
                    </template>
                    <div ref="hourStat" :style="{ width: '100%', height: '300px' }"></div>
                </el-card>
            </el-col>
        </el-row>
        <!-- 订单趋势 end -->
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
const visitStat = ref<HTMLElement | null>(null)
const hourStat = ref<HTMLElement | null>(null)

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
interface StatCountType {
    order_num: number[]
    time: string[]
    sale_money: number[]
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
const statCount = ref<StatCountType>({ order_num: [], time: [], sale_money: [] })

const normalizeNumberMap = (payload: Record<string, any>) => {
    const result: Record<string, number> = {}
    Object.keys(payload || {}).forEach((key) => {
        const value = Number(payload[key] ?? 0)
        result[key] = Number.isNaN(value) ? 0 : value
    })
    return result
}

const drawChart = () => {
    const value = statCount.value.order_num
    if (!visitStat.value) return
    const visitStatChart = echarts.init(visitStat.value)
    const visitStatOption = {
        legend: {},
        xAxis: {
            data: statCount.value.time
        },
        yAxis: {},
        tooltip: {
            trigger: 'axis',
            formatter: (params: any[]) => {
                if (!params.length) return ''
                const date = params[0].axisValue
                const data = params[0].data
                return `${date}<br/>订单量: ${data} 单`
            }
        },
        series: [
            {
                type: 'line',
                data: value
            }
        ]
    }
    visitStatChart.setOption(visitStatOption)
}

const drawChartTo = () => {
    const valueTo = statCount.value.sale_money
    if (!hourStat.value) return
    const hourStatChart = echarts.init(hourStat.value)
    const hourStatOption = {
        legend: {},
        xAxis: {
            data: statCount.value.time
        },
        yAxis: {},
        tooltip: {
            trigger: 'axis',
            formatter: (params: any[]) => {
                if (!params.length) return ''
                const date = params[0].axisValue
                const data = params[0].data
                return `${date}<br/>销售额: ${data} 元`
            }
        },
        series: [
            {
                type: 'line',
                data: valueTo
            }
        ]
    }
    hourStatChart.setOption(hourStatOption)
}

const getStatInfoFn = async () => {
    const [totalRes, todayRes, yesterdayRes, goodsRes, statRes] = await Promise.all([
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
    statCount.value = statRes.data || { order_num: [], time: [], sale_money: [] }

    await nextTick()
    drawChart()
    drawChartTo()
}
getStatInfoFn()

onBeforeUnmount(() => {
    if (visitStat.value) {
        const chart = echarts.getInstanceByDom(visitStat.value)
        chart?.dispose()
    }
    if (hourStat.value) {
        const chart = echarts.getInstanceByDom(hourStat.value)
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
