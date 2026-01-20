<template>
    <div class="main-container">
        <!-- 页面标题 -->
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">订单数据统计</h1>
            <p class="text-gray-600">实时掌握订单数据，优化业务决策</p>
        </div>

        <!-- 核心指标卡片 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">今日订单</p>
                        <p class="text-2xl font-bold text-blue-600">{{ todayStats.total_orders || 0 }}</p>
                        <p class="text-xs text-gray-500">¥{{ todayStats.actual_amount?.toFixed(2) || '0.00' }}</p>
                    </div>
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">支付成功率</p>
                        <p class="text-2xl font-bold text-green-600">{{ todayStats.pay_rate || 0 }}%</p>
                        <p class="text-xs text-gray-500">已支付 {{ todayStats.paid_count || 0 }} 单</p>
                    </div>
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">订单完成率</p>
                        <p class="text-2xl font-bold text-purple-600">{{ todayStats.success_rate || 0 }}%</p>
                        <p class="text-xs text-gray-500">已完成 {{ todayStats.finished_count || 0 }} 单</p>
                    </div>
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm text-gray-600 mb-1">退款订单</p>
                        <p class="text-2xl font-bold text-red-600">{{ todayStats.total_refund_count || 0 }}</p>
                        <p class="text-xs text-gray-500">退款中 {{ todayStats.refunding_count || 0 }} 单</p>
                    </div>
                    <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- 时间段对比表格 -->
        <div class="bg-white rounded-lg shadow-sm border mb-6">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">时间段数据对比</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">时间段</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">订单总数</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">订单金额</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">实际金额</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已支付</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">已完成</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">支付率</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">完成率</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr v-for="(period, key) in periodStats" :key="key" class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">{{ getPeriodName(period.period_name) }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900 font-semibold">{{ period.total_orders || 0 }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-gray-900">¥{{ period.total_amount?.toFixed(2) || '0.00' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-green-600 font-semibold">¥{{ period.actual_amount?.toFixed(2) || '0.00' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-blue-600 font-semibold">{{ period.paid_count || 0 }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div class="text-sm text-purple-600 font-semibold">{{ period.finished_count || 0 }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      :class="getPayRateColor(period.pay_rate || 0)">
                                    {{ period.pay_rate || 0 }}%
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                      :class="getSuccessRateColor(period.success_rate || 0)">
                                    {{ period.success_rate || 0 }}%
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 图表和状态分布 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <!-- 订单状态分布 -->
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">订单状态分布</h3>
                <div class="h-64">
                    <v-chart :option="pieChartOption" class="w-full h-full" />
                </div>
            </div>

            <!-- 趋势图表 -->
            <div class="bg-white rounded-lg shadow-sm border p-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">时间段对比</h3>
                <div class="h-64">
                    <v-chart :option="barChartOption" class="w-full h-full" />
                </div>
            </div>
        </div>

        <!-- 详细状态列表 -->
        <div class="bg-white rounded-lg shadow-sm border">
            <div class="p-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900">订单状态详情</h3>
            </div>
            <div class="p-4">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="status in statusDistribution" :key="status.status" 
                         class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-3 h-3 rounded-full mr-3" :class="getStatusColor(status.status)"></div>
                            <span class="text-sm font-medium text-gray-700">{{ status.name }}</span>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-bold text-gray-900">{{ status.count }}</div>
                            <div class="text-xs text-gray-500">{{ getStatusPercentage(status.count) }}%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, computed, onMounted } from 'vue'
import { t } from '@/lang'
import { getStat } from '@/addon/tk_jhkd/api/order'
import { use } from 'echarts/core'
import { CanvasRenderer } from 'echarts/renderers'
import { PieChart, BarChart } from 'echarts/charts'
import { TitleComponent, TooltipComponent, LegendComponent, GridComponent } from 'echarts/components'
import VChart from 'vue-echarts'

use([
    CanvasRenderer,
    PieChart,
    BarChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
    GridComponent
])

// 响应式数据
const statData = ref<any>({})
const loading = ref(false)

// 计算属性
const periodStats = computed(() => {
    if (!statData.value) return {}
    const { today, yesterday, this_week, this_month, this_quarter, this_year } = statData.value
    return {
        today: today || {},
        yesterday: yesterday || {},
        this_week: this_week || {},
        this_month: this_month || {},
        this_quarter: this_quarter || {},
        this_year: this_year || {}
    }
})

const todayStats = computed(() => {
    return periodStats.value.today || {}
})

const statusDistribution = computed(() => {
    return statData.value?.status_distribution || []
})

// 饼图配置
const pieChartOption = computed(() => {
    const data = statusDistribution.value.map(item => ({
        name: item.name,
        value: item.count
    }))
    
    return {
        tooltip: {
            trigger: 'item',
            formatter: '{b}: {c} ({d}%)'
        },
        legend: {
            orient: 'vertical',
            left: 'left',
            textStyle: {
                fontSize: 12
            }
        },
        series: [
            {
                name: '订单状态',
                type: 'pie',
                radius: ['30%', '60%'],
                center: ['60%', '50%'],
                data: data,
                emphasis: {
                    itemStyle: {
                        shadowBlur: 10,
                        shadowOffsetX: 0,
                        shadowColor: 'rgba(0, 0, 0, 0.5)'
                    }
                },
                color: ['#3B82F6', '#10B981', '#F59E0B', '#8B5CF6', '#6B7280']
            }
        ]
    }
})

// 柱状图配置
const barChartOption = computed(() => {
    const periods = Object.keys(periodStats.value)
    const orderData = periods.map(key => periodStats.value[key]?.total_orders || 0)
    const labels = periods.map(key => getPeriodName(periodStats.value[key]?.period_name || key))
    
    return {
        tooltip: {
            trigger: 'axis'
        },
        xAxis: {
            type: 'category',
            data: labels,
            axisLabel: {
                fontSize: 11,
                rotate: 0
            }
        },
        yAxis: {
            type: 'value',
            name: '订单数量'
        },
        series: [
            {
                name: '订单数量',
                type: 'bar',
                data: orderData,
                itemStyle: {
                    color: '#3B82F6',
                    borderRadius: [4, 4, 0, 0]
                },
                barWidth: '60%'
            }
        ]
    }
})

// 方法
const getPeriodName = (periodName: string) => {
    const nameMap: Record<string, string> = {
        'Today': '今日',
        'Yesterday': '昨日', 
        'This Week': '本周',
        'This Month': '本月',
        'This Quarter': '本季度',
        'This Year': '本年'
    }
    return nameMap[periodName] || periodName
}

const getStatusColor = (status: number) => {
    const colorMap: Record<number, string> = {
        0: 'bg-yellow-500',   // 待支付
        1: 'bg-blue-500',     // 已支付
        2: 'bg-purple-500',   // 在途中
        10: 'bg-green-500',   // 已完成
        '-1': 'bg-gray-500'   // 已关闭
    }
    return colorMap[status] || 'bg-gray-400'
}

const getPayRateColor = (rate: number) => {
    if (rate >= 80) return 'bg-green-100 text-green-800'
    if (rate >= 60) return 'bg-yellow-100 text-yellow-800'
    return 'bg-red-100 text-red-800'
}

const getSuccessRateColor = (rate: number) => {
    if (rate >= 90) return 'bg-green-100 text-green-800'
    if (rate >= 70) return 'bg-yellow-100 text-yellow-800'
    return 'bg-red-100 text-red-800'
}

const getStatusPercentage = (count: number) => {
    const total = statusDistribution.value.reduce((sum: number, item: any) => sum + item.count, 0)
    return total > 0 ? ((count / total) * 100).toFixed(1) : '0.0'
}

const loadStatData = async () => {
    try {
        loading.value = true
        const res = await getStat()
        statData.value = res.data
    } catch (error) {
        console.error('获取统计数据失败:', error)
    } finally {
        loading.value = false
    }
}

// 生命周期
onMounted(() => {
    loadStatData()
})
</script>

<style lang="scss" scoped>
.main-container {
    @apply p-6 bg-gray-50 min-h-screen;
}

// 表格样式优化
table {
    th {
        @apply bg-gray-50 font-medium;
    }
    
    td {
        @apply border-t border-gray-200;
    }
    
    tr:hover {
        @apply bg-gray-50;
    }
}
</style>