<template>
    <div class="runner-level-page">
        <el-tabs v-model="activeTab">
            <el-tab-pane label="等级配置" name="level">
                <el-card>
                    <template #header>
                        <div class="card-header">
                            <span>接单员等级配置</span>
                            <el-button type="primary" size="small" @click="addLevel">添加等级</el-button>
                        </div>
                    </template>
                    <div class="level-tip">
                        <el-alert type="info" :closable="false">
                            <p>佣金比例说明：接单员获得的比例，平台抽成 = 100% - 佣金比例</p>
                            <p>各类型单独设置：留空则使用默认佣金比例</p>
                        </el-alert>
                    </div>
                    <el-table :data="levelList" border>
                        <el-table-column prop="level" label="等级" width="60" fixed />
                        <el-table-column label="等级名称" width="120" fixed>
                            <template #default="{ row }">
                                <el-input v-model="row.name" size="small" />
                            </template>
                        </el-table-column>
                        <el-table-column label="最低订单数" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.min_orders" :min="0" size="small" :controls="false" style="width:80px" />
                            </template>
                        </el-table-column>
                        <el-table-column label="默认佣金(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.commission_rate" :min="0" :max="100" size="small" :controls="false" style="width:70px" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代取快递(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_express" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代买服务(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_buy" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="帮我送(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_send" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代打印(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_print" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代排队(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_queue" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代占座(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_seat" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="帮搬运(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_carry" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="扔垃圾(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_trash" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="代清洁(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_clean" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="帮帮忙(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_help" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="游戏陪练(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_game" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="拼单(%)" width="100">
                            <template #default="{ row }">
                                <el-input-number v-model="row.rate_group" :min="0" :max="100" size="small" :controls="false" style="width:70px" placeholder="默认" />
                            </template>
                        </el-table-column>
                        <el-table-column label="状态" width="80" fixed="right">
                            <template #default="{ row }">
                                <el-switch v-model="row.status" :active-value="1" :inactive-value="0" />
                            </template>
                        </el-table-column>
                        <el-table-column label="操作" width="80" fixed="right">
                            <template #default="{ $index }">
                                <el-button type="danger" size="small" @click="removeLevel($index)" v-if="levelList.length > 1">删除</el-button>
                            </template>
                        </el-table-column>
                    </el-table>
                    <div class="save-btn">
                        <el-button type="primary" @click="saveLevels">保存配置</el-button>
                    </div>
                </el-card>
            </el-tab-pane>

            <!-- 邀请奖励配置和邀请奖励记录功能已移至邀请分销页面，此处注释掉 -->
            <!-- <el-tab-pane label="邀请奖励配置" name="invite">
                <el-card>
                    <el-form :model="inviteConfig" label-width="150px">
                        <el-form-item label="启用邀请奖励">
                            <el-switch v-model="inviteConfig.is_enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="奖励金额(元)">
                            <el-input-number v-model="inviteConfig.reward_amount" :min="0" :precision="2" />
                        </el-form-item>
                        <el-form-item label="奖励类型">
                            <el-select v-model="inviteConfig.reward_type">
                                <el-option label="余额" value="balance" />
                                <el-option label="积分" value="point" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="需完成订单数">
                            <el-input-number v-model="inviteConfig.require_orders" :min="0" />
                            <span class="tip">被邀请人需要完成多少单后才发放奖励，0表示审核通过即发放</span>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveInviteConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <el-tab-pane label="邀请奖励记录" name="rewards">
                <el-card>
                    <el-table :data="rewardList" v-loading="loading">
                        <el-table-column prop="id" label="ID" width="80" />
                        <el-table-column prop="inviter_id" label="邀请人ID" width="100" />
                        <el-table-column prop="invitee_id" label="被邀请人ID" width="100" />
                        <el-table-column prop="reward_amount" label="奖励金额" width="100" />
                        <el-table-column label="状态" width="100">
                            <template #default="{ row }">
                                <el-tag v-if="row.status === 0" type="warning">待发放</el-tag>
                                <el-tag v-else type="success">已发放</el-tag>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-pagination
                        v-model:current-page="rewardPage"
                        :total="rewardTotal"
                        layout="total, prev, pager, next"
                        @current-change="loadRewards"
                    />
                </el-card>
            </el-tab-pane> -->
        </el-tabs>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getRunnerLevelList, saveRunnerLevels, getRunnerInviteConfig, saveRunnerInviteConfig, getRunnerInviteRewardList } from '../../api/admin'

const activeTab = ref('level')
const levelList = ref<any[]>([])
const inviteConfig = ref({
    is_enabled: 1,
    reward_amount: 10,
    reward_type: 'balance',
    require_orders: 1
})
const rewardList = ref<any[]>([])
const rewardPage = ref(1)
const rewardTotal = ref(0)
const loading = ref(false)

onMounted(() => {
    loadLevels()
    // loadInviteConfig()
    // loadRewards()
})

const loadLevels = async () => {
    const res: any = await getRunnerLevelList()
    if (res.code === 1) {
        levelList.value = res.data
    }
}

const addLevel = () => {
    const maxLevel = levelList.value.length > 0 ? Math.max(...levelList.value.map(l => l.level)) : 0
    levelList.value.push({
        level: maxLevel + 1,
        name: '',
        min_orders: 0,
        commission_rate: 70,
        rate_express: null,
        rate_buy: null,
        rate_send: null,
        rate_print: null,
        rate_queue: null,
        rate_seat: null,
        rate_carry: null,
        rate_trash: null,
        rate_clean: null,
        rate_help: null,
        rate_game: null,
        rate_group: null,
        status: 1
    })
}

const removeLevel = (index: number) => {
    levelList.value.splice(index, 1)
}

const saveLevels = async () => {
    const res: any = await saveRunnerLevels({ levels: levelList.value })
    if (res.code === 1) {
        ElMessage.success('保存成功')
    }
}

const loadInviteConfig = async () => {
    const res: any = await getRunnerInviteConfig()
    if (res.code === 1) {
        inviteConfig.value = res.data
    }
}

const saveInviteConfig = async () => {
    const res: any = await saveRunnerInviteConfig(inviteConfig.value)
    if (res.code === 1) {
        ElMessage.success('保存成功')
    }
}

const loadRewards = async () => {
    loading.value = true
    const res: any = await getRunnerInviteRewardList({ page: rewardPage.value, limit: 10 })
    loading.value = false
    if (res.code === 1) {
        rewardList.value = res.data.list
        rewardTotal.value = res.data.count
    }
}
</script>

<style scoped>
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.save-btn {
    margin-top: 20px;
    text-align: center;
}
.tip {
    margin-left: 10px;
    color: #999;
    font-size: 12px;
}
.level-tip {
    margin-bottom: 15px;
}
.level-tip p {
    margin: 5px 0;
}
</style>
