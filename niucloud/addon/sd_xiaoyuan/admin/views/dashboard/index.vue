<template>
    <div class="dashboard-container">
        <!-- 数据概览卡片 -->
        <el-row :gutter="20" class="stat-cards">
            <el-col :span="6">
                <el-card class="stat-card order" :body-style="{ padding: '20px' }">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <el-icon><Tickets /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">{{ overview.order?.total || 0 }}</div>
                            <div class="stat-label">总订单数</div>
                        </div>
                    </div>
                    <div class="stat-extra">今日新增 <span class="highlight">{{ overview.order?.today || 0 }}</span></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card runner" :body-style="{ padding: '20px' }">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <el-icon><User /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">{{ overview.runner?.total || 0 }}</div>
                            <div class="stat-label">接单员总数</div>
                        </div>
                    </div>
                    <div class="stat-extra">当前在线 <span class="highlight">{{ overview.runner?.online || 0 }}</span></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card income" :body-style="{ padding: '20px' }">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <el-icon><Money /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">¥{{ overview.income?.total || 0 }}</div>
                            <div class="stat-label">平台收益</div>
                        </div>
                    </div>
                    <div class="stat-extra">今日收益 <span class="highlight">¥{{ overview.income?.today || 0 }}</span></div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card pending" :body-style="{ padding: '20px' }">
                    <div class="stat-content">
                        <div class="stat-icon">
                            <el-icon><Bell /></el-icon>
                        </div>
                        <div class="stat-info">
                            <div class="stat-value">{{ pendingCount }}</div>
                            <div class="stat-label">待处理</div>
                        </div>
                    </div>
                    <div class="stat-extra">审核 <span class="highlight">{{ overview.runner?.pending_audit || 0 }}</span> | 提现 <span class="highlight">{{ overview.withdraw?.pending || 0 }}</span></div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 快捷入口 -->
        <el-card class="quick-entry-card">
            <template #header>
                <span class="text-lg font-bold">快捷入口</span>
            </template>
            <div class="quick-entry-grid">
                <div class="entry-item" @click="goTo('/order/list')">
                    <div class="entry-icon order"><el-icon><Tickets /></el-icon></div>
                    <span>订单管理</span>
                </div>
                <div class="entry-item" @click="goTo('/task/list')">
                    <div class="entry-icon task"><el-icon><List /></el-icon></div>
                    <span>任务悬赏</span>
                </div>
                <div class="entry-item" @click="goTo('/group_order/list')">
                    <div class="entry-icon group"><el-icon><Connection /></el-icon></div>
                    <span>拼单好饭</span>
                </div>
                <div class="entry-item" @click="goTo('/secondhand/list')">
                    <div class="entry-icon secondhand"><el-icon><ShoppingCart /></el-icon></div>
                    <span>二手交易</span>
                </div>
                <div class="entry-item" @click="goTo('/lost_found/list')">
                    <div class="entry-icon lost"><el-icon><Search /></el-icon></div>
                    <span>失物招领</span>
                </div>
                <div class="entry-item" @click="goTo('/runner/list')">
                    <div class="entry-icon runner"><el-icon><UserFilled /></el-icon></div>
                    <span>接单员管理</span>
                </div>
                <div class="entry-item" @click="goTo('/community/list')">
                    <div class="entry-icon community"><el-icon><ChatDotRound /></el-icon></div>
                    <span>社区管理</span>
                </div>
                <div class="entry-item" @click="goTo('/confession/list')">
                    <div class="entry-icon confession"><el-icon><Star /></el-icon></div>
                    <span>表白墙</span>
                </div>
                <div class="entry-item" @click="goTo('/house/list')">
                    <div class="entry-icon house"><el-icon><House /></el-icon></div>
                    <span>房屋租赁</span>
                </div>
                <div class="entry-item" @click="goTo('/house/order')">
                    <div class="entry-icon house"><el-icon><Tickets /></el-icon></div>
                    <span>房源订单</span>
                </div>
                <div class="entry-item" @click="goTo('/campus_auth/list')">
                    <div class="entry-icon auth"><el-icon><Checked /></el-icon></div>
                    <span>校园认证</span>
                    <el-badge :value="overview.campus_auth?.pending" v-if="overview.campus_auth?.pending" class="entry-badge" />
                </div>
                <div class="entry-item" @click="goTo('/config/index')">
                    <div class="entry-icon config"><el-icon><Setting /></el-icon></div>
                    <span>系统配置</span>
                </div>
            </div>
        </el-card>

        <!-- 图表区域 -->
        <el-row :gutter="20" class="chart-section">
            <el-col :span="24">
                <el-card>
                    <template #header>
                        <div class="card-header">
                            <span>订单趋势</span>
                            <el-radio-group v-model="chartType" size="small" @change="loadChartData">
                                <el-radio-button label="week">近7天</el-radio-button>
                                <el-radio-button label="month">近30天</el-radio-button>
                            </el-radio-group>
                        </div>
                    </template>
                    <div ref="chartRef" style="height: 300px;"></div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Tickets, User, Money, Bell, UserFilled, Wallet, Setting, List, Connection, ShoppingCart, Search, ChatDotRound, Star, House, Checked } from '@element-plus/icons-vue'
import { useRouter } from 'vue-router'
import * as echarts from 'echarts'
import { getDashboardOverview, getDashboardStat } from '@/addon/sd_xiaoyuan/api/admin'

const router = useRouter()
const chartRef = ref()
const chartType = ref('week')
const overview = ref<any>({})
const chartData = ref<any[]>([])

const pendingCount = computed(() => {
    return (overview.value.runner?.pending_audit || 0) + 
           (overview.value.withdraw?.pending || 0) + 
           (overview.value.campus_auth?.pending || 0)
})

onMounted(() => {
    loadOverview()
    loadChartData()
})

const loadOverview = async () => {
    try {
        const res = await getDashboardOverview()
        if (res.code === 1) {
            overview.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const loadChartData = async () => {
    try {
        const res = await getDashboardStat({ type: chartType.value })
        if (res.code === 1) {
            chartData.value = res.data
            renderChart()
        }
    } catch (e) {
        console.error(e)
    }
}

const renderChart = () => {
    const chart = echarts.init(chartRef.value)
    
    const option = {
        tooltip: {
            trigger: 'axis'
        },
        legend: {
            data: ['订单数', '完成数', '收益']
        },
        xAxis: {
            type: 'category',
            data: chartData.value.map(item => item.date)
        },
        yAxis: [
            {
                type: 'value',
                name: '订单数'
            },
            {
                type: 'value',
                name: '收益(元)'
            }
        ],
        series: [
            {
                name: '订单数',
                type: 'bar',
                data: chartData.value.map(item => item.order_count)
            },
            {
                name: '完成数',
                type: 'bar',
                data: chartData.value.map(item => item.complete_count)
            },
            {
                name: '收益',
                type: 'line',
                yAxisIndex: 1,
                data: chartData.value.map(item => item.income)
            }
        ]
    }
    
    chart.setOption(option)
}

const goTo = (path: string) => {
    router.push(`/sd_xiaoyuan${path}`)
}
</script>

<style scoped lang="scss">
.dashboard-container {
    padding: 20px;
}

.stat-cards {
    margin-bottom: 20px;
}

.stat-card {
    border-radius: 12px;
    border: none;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    overflow: hidden;
    
    &:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
    }
    
    .stat-content {
        display: flex;
        align-items: center;
        margin-bottom: 12px;
    }
    
    .stat-icon {
        width: 56px;
        height: 56px;
        border-radius: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 16px;
        flex-shrink: 0;
        
        .el-icon {
            font-size: 26px;
            color: #fff;
        }
    }
    
    &.order .stat-icon { background: linear-gradient(135deg, #1a83ff, #0066cc); }
    &.runner .stat-icon { background: linear-gradient(135deg, #52c41a, #389e0d); }
    &.income .stat-icon { background: linear-gradient(135deg, #ff9500, #ff6b00); }
    &.pending .stat-icon { background: linear-gradient(135deg, #722ed1, #531dab); }
    
    .stat-info {
        flex: 1;
        min-width: 0;
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a1a1a;
            line-height: 1.2;
        }
        
        .stat-label {
            font-size: 14px;
            color: #8c8c8c;
            margin-top: 4px;
        }
    }
    
    .stat-extra {
        font-size: 13px;
        color: #8c8c8c;
        padding-top: 12px;
        border-top: 1px solid #f0f0f0;
        
        .highlight {
            color: #1a83ff;
            font-weight: 600;
        }
    }
    
    &.order .stat-extra .highlight { color: #1a83ff; }
    &.runner .stat-extra .highlight { color: #52c41a; }
    &.income .stat-extra .highlight { color: #ff9500; }
    &.pending .stat-extra .highlight { color: #722ed1; }
}

.quick-entry-card {
    margin-bottom: 20px;
}

.quick-entry-grid {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 16px;
    
    .entry-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 16px 8px;
        background: #f8f9fa;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
        
        &:hover {
            background: #e6f4ff;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .entry-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            
            .el-icon {
                font-size: 24px;
                color: #fff;
            }
            
            &.order { background: linear-gradient(135deg, #1a83ff, #0066cc); }
            &.task { background: linear-gradient(135deg, #52c41a, #389e0d); }
            &.group { background: linear-gradient(135deg, #ff9500, #ff6b00); }
            &.secondhand { background: linear-gradient(135deg, #722ed1, #531dab); }
            &.lost { background: linear-gradient(135deg, #eb2f96, #c41d7f); }
            &.runner { background: linear-gradient(135deg, #13c2c2, #08979c); }
            &.community { background: linear-gradient(135deg, #fa8c16, #d46b08); }
            &.confession { background: linear-gradient(135deg, #f5222d, #cf1322); }
            &.house { background: linear-gradient(135deg, #2f54eb, #1d39c4); }
            &.withdraw { background: linear-gradient(135deg, #faad14, #d48806); }
            &.auth { background: linear-gradient(135deg, #a0d911, #7cb305); }
            &.config { background: linear-gradient(135deg, #8c8c8c, #595959); }
        }
        
        span {
            font-size: 13px;
            color: #333;
            text-align: center;
        }
        
        .entry-badge {
            position: absolute;
            top: 8px;
            right: 8px;
        }
    }
}

.chart-section {
    margin-bottom: 20px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

</style>
