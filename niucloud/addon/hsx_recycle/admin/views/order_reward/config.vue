<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-6">
                <div>
                    <span class="text-page-title">订单完成奖励配置</span>
                    <p class="text-sm text-gray-500 mt-1">配置用户完成回收订单后的积分奖励</p>
                </div>
            </div>

            <div v-loading="loading" class="config-container">
                <el-card class="config-card" shadow="hover">
                    <template #header>
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <div class="config-icon bg-green-500">
                                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-lg font-semibold">积分奖励设置</h3>
                                    <p class="text-xs text-gray-500">新用户完成订单后自动发放积分</p>
                                </div>
                            </div>
                            <el-switch
                                v-model="formData.is_enable"
                                :active-value="1"
                                :inactive-value="0"
                            />
                        </div>
                    </template>

                    <el-form :model="formData" label-width="120px" label-position="left">
                        <el-form-item label="每单奖励">
                            <el-input-number
                                v-model="formData.reward_point"
                                :min="0"
                                :max="10000"
                                :step="1"
                                :disabled="formData.is_enable === 0"
                            />
                            <span class="ml-2 text-sm text-gray-500">积分</span>
                        </el-form-item>

                        <el-form-item label="奖励次数">
                            <el-input-number
                                v-model="formData.reward_times"
                                :min="1"
                                :max="100"
                                :step="1"
                                :disabled="formData.is_enable === 0"
                            />
                            <span class="ml-2 text-sm text-gray-500">次（用户前N单可获得奖励）</span>
                        </el-form-item>

                        <el-form-item label="">
                            <div class="reward-preview" v-if="formData.is_enable === 1">
                                <el-alert
                                    type="success"
                                    :closable="false"
                                    show-icon
                                >
                                    <template #title>
                                        <span class="text-sm">
                                            新用户前 <strong>{{ formData.reward_times }}</strong> 单，每单奖励 <strong>{{ formData.reward_point }}</strong> 积分，
                                            共可获得 <strong class="text-green-600">{{ formData.reward_times * formData.reward_point }}</strong> 积分
                                        </span>
                                    </template>
                                </el-alert>
                            </div>
                        </el-form-item>

                        <el-form-item label="">
                            <div class="text-sm text-gray-500">
                                <p>• 用户完成回收订单后，系统将自动发放设置的积分数量</p>
                                <p>• 每个用户最多可获得设置的奖励次数，超过后将不再发放</p>
                                <p>• 积分将记录在会员账户中，可用于兑换或抵扣</p>
                                <p>• 建议设置合理的积分数量和次数，以提高用户复购率</p>
                            </div>
                        </el-form-item>
                    </el-form>

                    <div class="flex justify-end gap-2 mt-4">
                        <el-button @click="loadConfig">重置</el-button>
                        <el-button type="primary" :loading="saving" @click="saveConfig">保存配置</el-button>
                    </div>
                </el-card>
            </div>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getOrderRewardConfig, setOrderRewardConfig } from '@/addon/hsx_recycle/api/order_reward'

const loading = ref(false)
const saving = ref(false)

const formData = reactive({
    is_enable: 0,
    reward_point: 0,
    reward_times: 1
})

const toInteger = (value: unknown, fallback: number, min: number, max: number) => {
    const number = Number(value)
    if (!Number.isFinite(number)) return fallback
    return Math.min(max, Math.max(min, Math.trunc(number)))
}

const applyConfig = (value: unknown) => {
    const config = value && typeof value === 'object' && !Array.isArray(value)
        ? value as Record<string, unknown>
        : {}

    formData.is_enable = Number(config.is_enable) === 1 ? 1 : 0
    formData.reward_point = toInteger(config.reward_point, 0, 0, 10000)
    formData.reward_times = toInteger(config.reward_times, 1, 1, 100)
}

// 加载配置
const loadConfig = async () => {
    loading.value = true
    try {
        const response = await getOrderRewardConfig()
        applyConfig(response?.data)
    } catch (error) {
        console.error('加载配置失败:', error)
        ElMessage.error('加载配置失败')
    } finally {
        loading.value = false
    }
}

// 保存配置
const saveConfig = async () => {
    // 验证
    if (formData.is_enable === 1) {
        if (formData.reward_point <= 0) {
            ElMessage.warning('请设置奖励积分数量')
            return
        }
        if (formData.reward_times <= 0) {
            ElMessage.warning('请设置奖励次数')
            return
        }
    }

    try {
        saving.value = true
        await setOrderRewardConfig({
            is_enable: formData.is_enable,
            reward_point: formData.reward_point,
            reward_times: formData.reward_times
        })
        ElMessage.success('保存成功')
        await loadConfig()
    } catch (error) {
        console.error('保存配置失败:', error)
        ElMessage.error('保存配置失败')
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadConfig()
})
</script>

<script lang="ts">
export default {
    name: 'OrderRewardConfig',
    // 牛云页签缓存读取组件的 __name，显式声明 name 后 Vue 3.2 不会再自动生成该字段。
    __name: 'OrderRewardConfig'
}
</script>

<style lang="scss" scoped>
.config-container {
    max-width: 800px;
}

.config-card {
    transition: all 0.3s ease;

    &:hover {
        transform: translateY(-2px);
    }

    :deep(.el-card__header) {
        padding: 20px;
        background: linear-gradient(135deg, #f5f7fa 0%, #f9fafb 100%);
        border-bottom: 1px solid #e5e7eb;
    }

    :deep(.el-card__body) {
        padding: 24px;
    }
}

.config-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.bg-green-500 {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.reward-preview {
    width: 100%;
    margin-top: 8px;
}
</style>
