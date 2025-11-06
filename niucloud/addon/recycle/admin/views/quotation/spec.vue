<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-4">
                <span class="text-lg">规格同步管理</span>
                <el-button type="primary" @click="loadStats">刷新统计</el-button>
            </div>

            <!-- 统计卡片 -->
            <div class="grid grid-cols-3 gap-4 mb-4">
                <el-card shadow="hover">
                    <div class="text-center">
                        <div class="text-gray-500 text-sm mb-2">型号统计</div>
                        <div class="text-2xl font-bold mb-1">{{ stats.models?.total || 0 }}</div>
                        <div class="text-sm">
                            <span class="text-green-600">同步: {{ stats.models?.sync || 0 }}</span>
                            <span class="text-gray-400 ml-2">不同步: {{ stats.models?.not_sync || 0 }}</span>
                        </div>
                    </div>
                </el-card>
                <el-card shadow="hover">
                    <div class="text-center">
                        <div class="text-gray-500 text-sm mb-2">内存统计</div>
                        <div class="text-2xl font-bold mb-1">{{ stats.capacities?.total || 0 }}</div>
                        <div class="text-sm">
                            <span class="text-green-600">同步: {{ stats.capacities?.sync || 0 }}</span>
                            <span class="text-gray-400 ml-2">不同步: {{ stats.capacities?.not_sync || 0 }}</span>
                        </div>
                    </div>
                </el-card>
                <el-card shadow="hover">
                    <div class="text-center">
                        <div class="text-gray-500 text-sm mb-2">等级规格统计</div>
                        <div class="text-2xl font-bold mb-1">{{ stats.grade_specs?.total || 0 }}</div>
                        <div class="text-sm">
                            <span class="text-green-600">同步: {{ stats.grade_specs?.sync || 0 }}</span>
                            <span class="text-gray-400 ml-2">不同步: {{ stats.grade_specs?.not_sync || 0 }}</span>
                        </div>
                    </div>
                </el-card>
            </div>

            <!-- 标签页 -->
            <el-tabs v-model="activeTab" @tab-change="handleTabChange">
                <!-- 型号管理 -->
                <el-tab-pane label="型号管理" name="model">
                    <ModelManage ref="modelManageRef" @refresh-stats="loadStats" />
                </el-tab-pane>

                <!-- 内存管理 -->
                <el-tab-pane label="内存管理" name="capacity">
                    <CapacityManage ref="capacityManageRef" @refresh-stats="loadStats" />
                </el-tab-pane>

                <!-- 等级规格管理 -->
                <el-tab-pane label="等级规格管理" name="gradeSpec">
                    <GradeSpecManage ref="gradeSpecManageRef" @refresh-stats="loadStats" />
                </el-tab-pane>

                <!-- 报价单配置管理 -->
                <el-tab-pane label="报价单配置" name="config">
                    <ConfigManage ref="configManageRef" />
                </el-tab-pane>

                <!-- 价格配置管理 -->
                <el-tab-pane label="价格配置" name="priceConfig">
                    <PriceConfigManage ref="priceConfigManageRef" />
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<script lang="ts" setup>
import { ref, reactive, onMounted } from 'vue'
import { getQuotationSpecSyncStats } from '@/addon/recycle/api/quotation'
import ModelManage from './components/ModelManage.vue'
import CapacityManage from './components/CapacityManage.vue'
import GradeSpecManage from './components/GradeSpecManage.vue'
import ConfigManage from './components/ConfigManage.vue'
import PriceConfigManage from './components/PriceConfigManage.vue'

const activeTab = ref('model')

// 统计信息
const stats = reactive({
    models: { total: 0, sync: 0, not_sync: 0 },
    capacities: { total: 0, sync: 0, not_sync: 0 },
    grade_specs: { total: 0, sync: 0, not_sync: 0 },
})

// 组件引用
const modelManageRef = ref<InstanceType<typeof ModelManage>>()
const capacityManageRef = ref<InstanceType<typeof CapacityManage>>()
const gradeSpecManageRef = ref<InstanceType<typeof GradeSpecManage>>()
const configManageRef = ref<InstanceType<typeof ConfigManage>>()
const priceConfigManageRef = ref<InstanceType<typeof PriceConfigManage>>()

// 加载统计信息
const loadStats = async () => {
    try {
        const res = await getQuotationSpecSyncStats()
        Object.assign(stats, res.data)
    } catch (error) {
        console.error('加载统计信息失败:', error)
    }
}

// 标签页切换
const handleTabChange = (tabName: string) => {
    // 切换到某个标签页时，可以触发对应组件的刷新
    if (tabName === 'model' && modelManageRef.value) {
        modelManageRef.value.loadList()
    } else if (tabName === 'capacity' && capacityManageRef.value) {
        capacityManageRef.value.loadList()
    } else if (tabName === 'gradeSpec' && gradeSpecManageRef.value) {
        gradeSpecManageRef.value.loadList()
    } else if (tabName === 'config' && configManageRef.value) {
        configManageRef.value.loadList()
    } else if (tabName === 'priceConfig' && priceConfigManageRef.value) {
        priceConfigManageRef.value.loadList()
    }
}

// 初始化
onMounted(() => {
    loadStats()
})
</script>
<style scoped>
.main-container {
    padding: 20px;
}
</style>

