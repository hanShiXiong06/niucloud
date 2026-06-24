<template>
    <div class="invite-manage">
        <!-- 统计卡片 -->
        <el-row :gutter="16" class="stat-row">
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.total_invite || 0 }}</div>
                    <div class="stat-label">总邀请人数</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value">¥{{ stat.total_commission || '0.00' }}</div>
                    <div class="stat-label">累计佣金</div>
                </el-card>
            </el-col>
            <el-col :span="8">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.today_invite || 0 }}</div>
                    <div class="stat-label">今日新增</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 分销配置 -->
        <el-card class="config-card">
            <template #header>
                <span>分销配置</span>
            </template>
            <el-form :model="configForm" label-width="120px">
                <el-form-item label="开启分销">
                    <el-switch v-model="configForm.open_fenxiao" :active-value="1" :inactive-value="0" />
                </el-form-item>
                <el-form-item label="一级佣金比例">
                    <el-input-number v-model="configForm.fenxiao_rate1" :min="0" :max="100" />
                    <span style="margin-left: 8px;">%</span>
                </el-form-item>
                <el-form-item label="二级佣金比例">
                    <el-input-number v-model="configForm.fenxiao_rate2" :min="0" :max="100" />
                    <span style="margin-left: 8px;">%</span>
                </el-form-item>
                <el-form-item label="邀请海报背景图">
                    <upload-image v-model="configForm.invite_poster_bg" />
                    <div style="color: #999; font-size: 12px; margin-top: 4px;">推广海报生成时使用的背景图片，建议尺寸：750x1334</div>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="saveConfig">保存配置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 分销关系列表 -->
        <el-card class="table-card">
            <template #header>
                <span>分销关系</span>
            </template>
            <el-table :data="relationList" v-loading="loading">
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="member_id" label="用户ID" width="100" />
                <el-table-column prop="pid" label="一级推荐人" width="120" />
                <el-table-column prop="pid2" label="二级推荐人" width="120" />
                <el-table-column prop="total_commission" label="累计佣金" width="120">
                    <template #default="{ row }">¥{{ row.total_commission || '0.00' }}</template>
                </el-table-column>
                <el-table-column prop="total_invite" label="邀请人数" width="100" />
                <el-table-column prop="create_time" label="绑定时间" width="160">
                    <template #default="{ row }">{{ (row.create_time) }}</template>
                </el-table-column>
            </el-table>
            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                @current-change="loadRelations"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getInviteStat, getInviteList, getInviteConfig, saveInviteConfig } from '../../api/admin'

const stat = ref<any>({})
const configForm = ref({ open_fenxiao: 0, fenxiao_rate1: 10, fenxiao_rate2: 5, invite_poster_bg: '' })
const relationList = ref([])
const loading = ref(false)
const page = ref(1)
const limit = ref(10)
const total = ref(0)

onMounted(() => {
    loadStat()
    loadConfig()
    loadRelations()
})

const loadStat = async () => {
    const res: any = await getInviteStat()
    if (res.code === 1) stat.value = res.data
}

const loadConfig = async () => {
    const res: any = await getInviteConfig()
    if (res.code === 1 && res.data) configForm.value = res.data
}

const loadRelations = async () => {
    loading.value = true
    try {
        const res: any = await getInviteList({ page: page.value, limit: limit.value })
        if (res.code === 1) {
            relationList.value = res.data.list
            total.value = res.data.count
        }
    } finally {
        loading.value = false
    }
}

const saveConfig = async () => {
    try {
        const res: any = await saveInviteConfig(configForm.value)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (e: any) {
        console.error('保存分销配置失败:', e)
        ElMessage.error(e.message || '保存失败')
    }
}

</script>

<style scoped>
.stat-row { margin-bottom: 16px; }
.stat-card { text-align: center; }
.stat-value { font-size: 32px; font-weight: bold; color: #1a83ff; }
.stat-label { font-size: 14px; color: #999; margin-top: 8px; }
.config-card { margin-bottom: 16px; }
</style>
