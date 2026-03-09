<template>
    <div class="stat-page">
        <el-row :gutter="16" class="stat-cards">
            <el-col :span="6">
                <el-card class="stat-card community">
                    <div class="stat-icon"><i class="el-icon-chat-dot-round"></i></div>
                    <div class="stat-info">
                        <div class="stat-value">{{ overview.community?.total || 0 }}</div>
                        <div class="stat-label">社区帖子</div>
                        <div class="stat-today">今日 +{{ overview.community?.today || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card confession">
                    <div class="stat-icon"><i class="el-icon-star-on"></i></div>
                    <div class="stat-info">
                        <div class="stat-value">{{ overview.confession?.total || 0 }}</div>
                        <div class="stat-label">表白墙</div>
                        <div class="stat-today">今日 +{{ overview.confession?.today || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card sign">
                    <div class="stat-icon"><i class="el-icon-check"></i></div>
                    <div class="stat-info">
                        <div class="stat-value">{{ overview.sign?.total || 0 }}</div>
                        <div class="stat-label">累计签到</div>
                        <div class="stat-today">今日 {{ overview.sign?.today || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card house">
                    <div class="stat-icon"><i class="el-icon-house"></i></div>
                    <div class="stat-info">
                        <div class="stat-value">{{ overview.house?.total || 0 }}</div>
                        <div class="stat-label">房源总数</div>
                        <div class="stat-today">在线 {{ overview.house?.published || 0 }}</div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-card class="trend-card">
            <template #header>
                <div class="card-header">
                    <span>数据趋势</span>
                    <el-radio-group v-model="trendDays" size="small" @change="loadTrend">
                        <el-radio-button :label="7">近7天</el-radio-button>
                        <el-radio-button :label="15">近15天</el-radio-button>
                        <el-radio-button :label="30">近30天</el-radio-button>
                    </el-radio-group>
                </div>
            </template>
            <div class="trend-chart" ref="chartRef"></div>
        </el-card>

        <el-row :gutter="16">
            <el-col :span="12">
                <el-card>
                    <template #header>邀请统计</template>
                    <div class="invite-stat">
                        <div class="invite-item">
                            <div class="value">{{ overview.invite?.total || 0 }}</div>
                            <div class="label">总邀请人数</div>
                        </div>
                    </div>
                </el-card>
            </el-col>
            <el-col :span="12">
                <el-card>
                    <template #header>快捷操作</template>
                    <div class="quick-actions">
                        <el-button type="primary" @click="goTo('community')">社区管理</el-button>
                        <el-button type="success" @click="goTo('confession')">表白墙管理</el-button>
                        <el-button type="warning" @click="goTo('sign')">签到管理</el-button>
                        <el-button type="info" @click="goTo('house')">房源管理</el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { getStatOverview, getStatTrend } from '../../api/admin'

const router = useRouter()
const overview = ref<any>({})
const trendData = ref<any[]>([])
const trendDays = ref(7)
const chartRef = ref<HTMLElement | null>(null)

onMounted(() => {
    loadOverview()
    loadTrend()
})

const loadOverview = async () => {
    const res: any = await getStatOverview()
    if (res.code === 1) {
        overview.value = res.data
    }
}

const loadTrend = async () => {
    const res: any = await getStatTrend({ days: trendDays.value })
    if (res.code === 1) {
        trendData.value = res.data
    }
}

const goTo = (path: string) => {
    router.push(`/sd_xiaoyuan/${path}`)
}
</script>

<style scoped>
.stat-cards { margin-bottom: 16px; }

.stat-card {
    display: flex;
    align-items: center;
    padding: 20px;
}

.stat-card .stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
    margin-right: 16px;
}

.stat-card.community .stat-icon { background: linear-gradient(135deg, #1a83ff, #0066cc); }
.stat-card.confession .stat-icon { background: linear-gradient(135deg, #ff6b6b, #ff8e8e); }
.stat-card.sign .stat-icon { background: linear-gradient(135deg, #ff9500, #ff6b00); }
.stat-card.house .stat-icon { background: linear-gradient(135deg, #52c41a, #389e0d); }

.stat-info .stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #333;
}

.stat-info .stat-label {
    font-size: 14px;
    color: #999;
}

.stat-info .stat-today {
    font-size: 12px;
    color: #52c41a;
    margin-top: 4px;
}

.trend-card { margin-bottom: 16px; }
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.trend-chart {
    height: 300px;
}

.invite-stat {
    display: flex;
    justify-content: center;
    padding: 20px;
}

.invite-item {
    text-align: center;
}

.invite-item .value {
    font-size: 48px;
    font-weight: bold;
    color: #1a83ff;
}

.invite-item .label {
    font-size: 14px;
    color: #999;
}

.quick-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    padding: 20px;
}
</style>
