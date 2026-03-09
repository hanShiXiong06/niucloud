<template>
    <div class="sign-manage">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.today_count || 0 }}</div>
                    <div class="stat-label">今日签到</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.yesterday_count || 0 }}</div>
                    <div class="stat-label">昨日签到</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.week_count || 0 }}</div>
                    <div class="stat-label">本周签到</div>
                </el-card>
            </el-col>
            <el-col :span="6">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.total_count || 0 }}</div>
                    <div class="stat-label">累计签到</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 签到配置 -->
        <el-card class="config-card">
            <template #header>
                <span>签到奖励配置</span>
            </template>
            <el-table :data="signConfig">
                <el-table-column prop="day" label="天数" width="100">
                    <template #default="{ row }">第{{ row.day }}天</template>
                </el-table-column>
                <el-table-column label="积分奖励">
                    <template #default="{ row }">
                        <el-input-number v-model="row.points" :min="0" size="small" />
                    </template>
                </el-table-column>
                <!-- 优惠券奖励功能暂不使用
                <el-table-column label="优惠券奖励">
                    <template #default="{ row }">
                        <el-select v-model="row.coupon_id" placeholder="选择优惠券" clearable size="small">
                            <el-option label="无" :value="0" />
                        </el-select>
                    </template>
                </el-table-column>
                -->
            </el-table>
            <div class="config-actions">
                <el-button type="primary" @click="saveConfig">保存配置</el-button>
            </div>
        </el-card>

        <!-- 签到记录 -->
        <el-card class="table-card">
            <template #header>
                <span>签到记录</span>
            </template>
            <el-form :inline="true" :model="searchForm">
                <el-form-item label="日期">
                    <el-date-picker v-model="searchForm.sign_date" type="date" value-format="YYYY-MM-DD" placeholder="选择日期" />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadRecords">搜索</el-button>
                </el-form-item>
            </el-form>
            <el-table :data="recordList" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="member_nickname" label="用户昵称" width="150" />
                <el-table-column prop="sign_date" label="签到日期" width="120" />
                <el-table-column prop="continuous_days" label="连续天数" width="100" />
                <el-table-column prop="reward_points" label="获得积分" width="100" />
            </el-table>
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadRecords"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getSignStat, getSignList, getSignConfig, saveSignConfig } from '../../api/admin'

const stat = ref<any>({})
const signConfig = ref([
    { day: 1, points: 10, coupon_id: 0 },
    { day: 2, points: 20, coupon_id: 0 },
    { day: 3, points: 30, coupon_id: 0 },
    { day: 4, points: 40, coupon_id: 0 },
    { day: 5, points: 50, coupon_id: 0 },
    { day: 6, points: 60, coupon_id: 0 },
    { day: 7, points: 100, coupon_id: 0 }
])
const searchForm = ref({ sign_date: '' })
const recordList = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

onMounted(() => {
    loadStat()
    loadConfig()
    loadRecords()
})

const loadStat = async () => {
    const res: any = await getSignStat()
    if (res.code === 1) stat.value = res.data
}

const loadConfig = async () => {
    const res: any = await getSignConfig()
    if (res.code === 1 && res.data) signConfig.value = res.data
}

const loadRecords = async () => {
    loading.value = true
    try {
        const res: any = await getSignList({ ...searchForm.value, page: page.value, limit: limit.value })
        if (res.code === 1) {
            recordList.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const saveConfig = async () => {
    const res: any = await saveSignConfig({ config: signConfig.value })
    if (res.code === 1) ElMessage.success('保存成功')
}
</script>

<style scoped>
.stat-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 32px; font-weight: bold; color: #1a83ff; }
.stat-label { font-size: 14px; color: #999; margin-top: 8px; }
.config-card { margin-bottom: 16px; }
.config-actions { margin-top: 16px; text-align: right; }
</style>
