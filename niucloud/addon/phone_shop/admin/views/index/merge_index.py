# Read both files
with open('index.vue', 'r', encoding='utf-8') as f:
    current = f.read()

with open('index copy.vue', 'r', encoding='utf-8') as f:
    copy = f.read()

# Find the section to replace in current file (lines 122-141)
# Replace "商品上下架趋势" section with "订单趋势" section from copy

# Template replacement
old_template = '''        <!-- 商品上下架趋势 -->
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
        <!-- 商品上下架趋势 end -->'''

new_template = '''        <!-- 订单趋势 -->
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
        <!-- 订单趋势 end -->'''

# Replace template
result = current.replace(old_template, new_template)

# Now replace the script section
# Remove old chart refs and functions
old_refs = '''const router = useRouter()
const unshelfChartRef = ref<HTMLElement | null>(null)
const shelfChartRef = ref<HTMLElement | null>(null)'''

new_refs = '''const router = useRouter()
const visitStat = ref<HTMLElement | null>(null)
const hourStat = ref<HTMLElement | null>(null)'''

result = result.replace(old_refs, new_refs)

# Remove old interfaces and add statCount
old_interfaces = '''interface TrendSeries {
    name: string
    data: number[]
}
interface TrendPayload {
    legend: string[]
    categories: string[]
    series: TrendSeries[]
}'''

new_interfaces = '''interface StatCountType {
    order_num: number[]
    time: string[]
    sale_money: number[]
}'''

result = result.replace(old_interfaces, new_interfaces)

# Remove categoryTrend and add statCount
old_stat = '''const statTotal = ref<StatTotalType>({ ...defaultTotal })
const statToday = ref<StatDayType>({ ...defaultDay })
const statYesterday = ref<StatDayType>({ ...defaultDay })
const statGoods = ref<StatGoodsType>({ ...defaultGoods })
const categoryTrend = ref<{ shelf: TrendPayload; unshelf: TrendPayload }>({
    shelf: { legend: [], categories: [], series: [] },
    unshelf: { legend: [], categories: [], series: [] }
})'''

new_stat = '''const statTotal = ref<StatTotalType>({ ...defaultTotal })
const statToday = ref<StatDayType>({ ...defaultDay })
const statYesterday = ref<StatDayType>({ ...defaultDay })
const statGoods = ref<StatGoodsType>({ ...defaultGoods })
const statCount = ref<StatCountType>({ order_num: [], time: [], sale_money: [] })'''

result = result.replace(old_stat, new_stat)

# Remove old chart rendering functions and add new ones
# Find and remove renderTrendChart and refreshTrendCharts functions
import re

# Remove renderTrendChart function
pattern1 = r'const renderTrendChart = \(el: HTMLElement \| null, trend: TrendPayload, emptyText: string\) => \{[\s\S]*?\n\}\n\n'
result = re.sub(pattern1, '', result)

# Remove refreshTrendCharts function  
pattern2 = r'const refreshTrendCharts = \(\) => \{[\s\S]*?\n\}\n\n'
result = re.sub(pattern2, '', result)

# Add new chart functions before getStatInfoFn
new_chart_functions = '''const drawChart = () => {
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

'''

# Insert before getStatInfoFn
result = result.replace('const getStatInfoFn = async () => {', new_chart_functions + 'const getStatInfoFn = async () => {')

# Update getStatInfoFn to use new data structure
old_getstat = '''const getStatInfoFn = async () => {
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
}'''

new_getstat = '''const getStatInfoFn = async () => {
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
}'''

result = result.replace(old_getstat, new_getstat)

# Update onBeforeUnmount
old_unmount = '''onBeforeUnmount(() => {
    if (unshelfChartRef.value) {
        const chart = echarts.getInstanceByDom(unshelfChartRef.value)
        chart?.dispose()
    }
    if (shelfChartRef.value) {
        const chart = echarts.getInstanceByDom(shelfChartRef.value)
        chart?.dispose()
    }
})'''

new_unmount = '''onBeforeUnmount(() => {
    if (visitStat.value) {
        const chart = echarts.getInstanceByDom(visitStat.value)
        chart?.dispose()
    }
    if (hourStat.value) {
        const chart = echarts.getInstanceByDom(hourStat.value)
        chart?.dispose()
    }
})'''

result = result.replace(old_unmount, new_unmount)

# Write result
with open('index.vue', 'w', encoding='utf-8') as f:
    f.write(result)

print("Merge completed successfully!")
