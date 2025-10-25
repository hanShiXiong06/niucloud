<template>
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50">
        <!-- 页面标题和快速筛选 -->
        <div class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-10">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <!-- 页面标题 -->
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center">
                            <el-icon :size="18" color="white"><DataAnalysis /></el-icon>
                        </div>
                        <div>
                            <h1 class="text-xl font-semibold text-gray-900">数据概览</h1>
                            <p class="text-sm text-gray-500">{{ userRole === 'admin' ? '管理员控制台' : '工作台数据' }}</p>
                        </div>
                    </div>
                    
                    <!-- 快速筛选按钮组 -->
                    <div class="flex flex-wrap items-center gap-3">
                        <!-- 快速时间筛选 -->
                        <div class="flex bg-gray-100 rounded-lg p-1">
                            <button
                                v-for="period in quickPeriods"
                                :key="period.key"
                                @click="handleQuickPeriod(period.key)"
                                :class="[
                                    'px-3 py-1.5 text-sm font-medium rounded-md transition-all duration-200',
                                    activePeriod === period.key
                                        ? 'bg-white text-blue-600 shadow-sm'
                                        : 'text-gray-600 hover:text-blue-600 hover:bg-white/50'
                                ]"
                            >
                                {{ period.label }}
                            </button>
                        </div>
                        
                        <!-- 自定义日期范围 -->
                        <div class="flex items-center gap-2">
                            <el-date-picker
                                v-model="dateRange"
                                type="daterange"
                                range-separator="至"
                                start-placeholder="开始日期"
                                end-placeholder="结束日期"
                                format="YYYY-MM-DD"
                                value-format="YYYY-MM-DD"
                                @change="handleDateChange"
                                size="default"
                                class="custom-date-picker"
                            />
                            <button
                                @click="fetchData"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-sm"
                            >
                                <el-icon :size="14" color="white" class="mr-1"><Search /></el-icon>
                                查询
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 主要内容区域 -->
        <div class=" mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- 普通用户视图 -->
            <div v-if="userRole === 'user' || userRole === 'checker' || userRole === 'pricer'" class="space-y-6">
                <!-- 工作概览卡片 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <!-- 卡片头部 -->
                    <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-3">
                                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                    <el-icon :size="20" color="white"><User /></el-icon>
                                </div>
                                <div>
                                    <h2 class="text-lg font-semibold text-white">{{ currentUserName }}</h2>
                                    <p class="text-blue-100 text-sm">{{ getTimePeriodText() }} 工作概览</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-white/80 text-xs">总计设备</div>
                                <div class="text-white text-2xl font-bold">{{ getTotalDevices() }}</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 快速统计卡片 -->
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                            <!-- 签收统计 -->
                            <div class="relative bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-blue-600 text-sm font-medium">签收订单</p>
                                        <p class="text-2xl font-bold text-blue-700 mt-1">{{ userWorkStats.signed_order_count || 0 }}</p>
                                        <p class="text-blue-500 text-xs mt-1">{{ userWorkStats.signed_device_count || 0 }} 台设备</p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Box /></el-icon>
                                    </div>
                                </div>
                                <div class="absolute top-2 right-2 w-2 h-2 bg-green-400 rounded-full" v-if="userWorkStats.signed_order_count > 0"></div>
                            </div>
                            
                            <!-- 质检统计 -->
                            <div class="relative bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200" 
                                 v-if="userWorkStats.check_count > 0 || ['checker', 'admin'].includes(userRole)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-green-600 text-sm font-medium">质检设备</p>
                                        <p class="text-2xl font-bold text-green-700 mt-1">{{ userWorkStats.check_count || 0 }}</p>
                                        <p class="text-green-500 text-xs mt-1">已质检数量</p>
                                    </div>
                                    <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Search /></el-icon>
                                    </div>
                                </div>
                                <div class="absolute top-2 right-2 w-2 h-2 bg-green-400 rounded-full" v-if="userWorkStats.check_count > 0"></div>
                            </div>
                            
                            <!-- 定价统计 -->
                            <div class="relative bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 border border-orange-200" 
                                 v-if="userWorkStats.price_count > 0 || ['pricer', 'admin'].includes(userRole)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-orange-600 text-sm font-medium">定价设备</p>
                                        <p class="text-2xl font-bold text-orange-700 mt-1">{{ userWorkStats.price_count || 0 }}</p>
                                        <p class="text-orange-500 text-xs mt-1">¥{{ userWorkStats.total_amount || 0 }}</p>
                                    </div>
                                    <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><PriceTag /></el-icon>
                                    </div>
                                </div>
                                <div class="absolute top-2 right-2 w-2 h-2 bg-green-400 rounded-full" v-if="userWorkStats.price_count > 0"></div>
                            </div>
                            
                            <!-- 打款统计 -->
                            <div class="relative bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4 border border-red-200" 
                                 v-if="userWorkStats.payment_count > 0 || ['pricer', 'admin'].includes(userRole)">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-red-600 text-sm font-medium">打款设备</p>
                                        <p class="text-2xl font-bold text-red-700 mt-1">{{ userWorkStats.payment_count || 0 }}</p>
                                        <p class="text-red-500 text-xs mt-1">已打款数量</p>
                                    </div>
                                    <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Money /></el-icon>
                                    </div>
                                </div>
                                <div class="absolute top-2 right-2 w-2 h-2 bg-green-400 rounded-full" v-if="userWorkStats.payment_count > 0"></div>
                            </div>
                        </div>
                        
                        <!-- 详细工作信息 -->
                        <div class="space-y-4">
                            <!-- 签收业务详情 -->
                            <div v-if="userWorkStats.signed_device_count > 0" class="bg-blue-50 rounded-lg p-4 border-l-4 border-blue-500">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-blue-500 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <el-icon :size="14" color="white"><Box /></el-icon>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-blue-900 mb-2">签收业务详情</h3>
                                        <p class="text-blue-700 text-sm">
                                            签收了 <span class="font-semibold">{{ userWorkStats.signed_order_count || 0 }}</span> 个订单，
                                            共 <span class="font-semibold">{{ userWorkStats.signed_device_count || 0 }}</span> 台设备
                                        </p>
                                        <div v-if="userWorkStats.sign_category_breakdown && userWorkStats.sign_category_breakdown.length > 0" 
                                             class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="item in userWorkStats.sign_category_breakdown" :key="item.category_name"
                                                  class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-700 text-xs rounded-full">
                                                {{ item.category_name }} {{ item.count }}台
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 质检业务详情 -->
                            <div v-if="userWorkStats.check_count > 0" class="bg-green-50 rounded-lg p-4 border-l-4 border-green-500">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-green-500 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <el-icon :size="14" color="white"><Search /></el-icon>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-green-900 mb-2">质检业务详情</h3>
                                        <p class="text-green-700 text-sm">
                                            质检了 <span class="font-semibold">{{ userWorkStats.check_count || 0 }}</span> 台设备
                                        </p>
                                        <div v-if="userWorkStats.check_category_breakdown && userWorkStats.check_category_breakdown.length > 0" 
                                             class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="item in userWorkStats.check_category_breakdown" :key="item.category_name"
                                                  class="inline-flex items-center px-2 py-1 bg-green-100 text-green-700 text-xs rounded-full">
                                                {{ item.category_name }} {{ item.count }}台
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 定价业务详情 -->
                            <div v-if="userWorkStats.price_count > 0" class="bg-orange-50 rounded-lg p-4 border-l-4 border-orange-500">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <el-icon :size="14" color="white"><PriceTag /></el-icon>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-orange-900 mb-2">定价业务详情</h3>
                                        <p class="text-orange-700 text-sm">
                                            定价了 <span class="font-semibold">{{ userWorkStats.price_count || 0 }}</span> 台设备
                                            <span v-if="userWorkStats.total_amount > 0" class="ml-2">
                                                （总价值 <span class="font-semibold">¥{{ userWorkStats.total_amount }}</span>）
                                            </span>
                                        </p>
                                        <div v-if="userWorkStats.price_category_breakdown && userWorkStats.price_category_breakdown.length > 0" 
                                             class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="item in userWorkStats.price_category_breakdown" :key="item.category_name"
                                                  class="inline-flex items-center px-2 py-1 bg-orange-100 text-orange-700 text-xs rounded-full">
                                                {{ item.category_name }} {{ item.count }}台
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 打款业务详情 -->
                            <div v-if="userWorkStats.payment_count > 0" class="bg-red-50 rounded-lg p-4 border-l-4 border-red-500">
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 bg-red-500 rounded-lg flex items-center justify-center flex-shrink-0 mt-0.5">
                                        <el-icon :size="14" color="white"><Money /></el-icon>
                                    </div>
                                    <div class="flex-1">
                                        <h3 class="font-medium text-red-900 mb-2">打款业务详情</h3>
                                        <p class="text-red-700 text-sm">
                                            打款了 <span class="font-semibold">{{ userWorkStats.payment_count || 0 }}</span> 台设备
                                        </p>
                                        <div v-if="userWorkStats.payment_category_breakdown && userWorkStats.payment_category_breakdown.length > 0" 
                                             class="mt-2 flex flex-wrap gap-2">
                                            <span v-for="item in userWorkStats.payment_category_breakdown" :key="item.category_name"
                                                  class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 text-xs rounded-full">
                                                {{ item.category_name }} {{ item.count }}台
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 设备分类图表 -->
                <div v-if="userWorkStats.sign_category_breakdown && userWorkStats.sign_category_breakdown.length > 0" 
                     class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                            <el-icon :size="18" color="#3B82F6" class="mr-2"><PieChart /></el-icon>
                            设备分类分布
                        </h3>
                    </div>
                    <div class="p-6">
                        <div ref="userCategoryChart" class="w-full h-80"></div>
                    </div>
                </div>
            </div>

            <!-- 管理员视图 -->
            <div v-else-if="userRole === 'admin'" class="space-y-6">
                <!-- 今日概况 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-purple-600 to-blue-600 px-6 py-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                                <el-icon :size="20" color="white"><DataBoard /></el-icon>
                            </div>
                            <div>
                                <h2 class="text-lg font-semibold text-white">运营概览</h2>
                                <p class="text-purple-100 text-sm">{{ getTimePeriodText() }} 业务数据</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <!-- 订单统计 -->
                            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-4 border border-blue-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-blue-600 text-sm font-medium">{{ getOrderLabel() }}</p>
                                        <p class="text-2xl font-bold text-blue-700 mt-1">{{ overviewStats.today_order_count || 0 }}</p>
                                        <p class="text-blue-500 text-xs mt-1">昨日 {{ overviewStats.yesterday_order_count || 0 }} 单</p>
                                    </div>
                                    <div class="w-12 h-12 bg-blue-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Document /></el-icon>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 质检统计 -->
                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-4 border border-green-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-green-600 text-sm font-medium">{{ getCheckLabel() }}</p>
                                        <p class="text-2xl font-bold text-green-700 mt-1">{{ overviewStats.today_check_count || 0 }}</p>
                                        <div v-if="overviewStats.today_check_breakdown && overviewStats.today_check_breakdown.length > 0" class="mt-1 flex flex-wrap gap-1">
                                            <span v-for="item in overviewStats.today_check_breakdown.slice(0, 2)" :key="item.category_name"
                                                  class="text-xs text-green-600 bg-green-200 px-1 rounded">
                                                {{ item.category_name }} {{ item.count }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="w-12 h-12 bg-green-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Search /></el-icon>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 打款统计 -->
                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-4 border border-orange-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-orange-600 text-sm font-medium">{{ getPaymentLabel() }}</p>
                                        <p class="text-2xl font-bold text-orange-700 mt-1">¥{{ overviewStats.today_payment_amount || 0 }}</p>
                                        <p class="text-orange-500 text-xs mt-1">{{ overviewStats.today_payment_count || 0 }} 台设备</p>
                                    </div>
                                    <div class="w-12 h-12 bg-orange-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><Money /></el-icon>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- 退货统计 -->
                            <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-xl p-4 border border-red-200">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-red-600 text-sm font-medium">{{ getReturnLabel() }}</p>
                                        <p class="text-2xl font-bold text-red-700 mt-1">{{ overviewStats.today_return_count || 0 }}</p>
                                        <p class="text-red-500 text-xs mt-1">设备数量</p>
                                    </div>
                                    <div class="w-12 h-12 bg-red-500 rounded-lg flex items-center justify-center">
                                        <el-icon :size="20" color="white"><RefreshLeft /></el-icon>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 员工工作统计 -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                                <el-icon :size="18" color="#8B5CF6" class="mr-2"><UserFilled /></el-icon>
                                员工工作统计
                            </h3>
                            <div class=" w-[240px] flex items-center gap-3">
                                <span class="w-[120px] text-sm text-gray-600">员工筛选：</span>
                                <el-select v-model="selectedUserId" placeholder="全部员工" clearable @change="fetchUserDetailStats" size="default" class="w-40">
                                    <el-option label="全部员工" value="" />
                                    <el-option 
                                        v-for="user in userList" 
                                        :key="user.uid" 
                                        :label="user.real_name || user.username" 
                                        :value="user.uid" 
                                    />
                                </el-select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-6">
                        <!-- 用户工作统计表格 -->
                        <div class="overflow-x-auto">
                            <el-table :data="userDetailStats" v-loading="userLoading" class="w-full">
                                <el-table-column prop="user_name" label="员工姓名" min-width="100" />
                                <el-table-column prop="user_type_name" label="角色" min-width="80" />
                                <el-table-column prop="period_text" label="时间段" min-width="120" />
                                <el-table-column prop="signed_order_count" label="签收订单数" min-width="100" />
                                <el-table-column prop="signed_device_count" label="签收设备数" min-width="100" />
                                <el-table-column prop="category_breakdown_text" label="设备分布" min-width="150" />
                                <el-table-column prop="check_count" label="质检数" min-width="80" />
                                <el-table-column prop="check_category_text" label="质检分类" min-width="120" />
                                <el-table-column prop="price_count" label="定价数" min-width="80" />
                                <el-table-column prop="payment_count" label="打款数" min-width="80" />
                            </el-table>
                        </div>
                        
                        <!-- 员工工作量对比图表 -->
                        <div class="mt-6" v-if="userDetailStats.length > 0">
                            <h4 class="text-md font-medium text-gray-900 mb-4 flex items-center">
                                <el-icon :size="16" color="#3B82F6" class="mr-2"><DataLine /></el-icon>
                                员工工作量对比
                            </h4>
                            <div ref="adminUserChart" class="w-full h-96"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script lang="ts" setup>
import { ref, onMounted, onUnmounted, computed, nextTick, watch } from 'vue'
import { ElMessage } from 'element-plus'
import * as echarts from 'echarts'
import { 
    DataAnalysis, Search, User, Box, PriceTag, Money, PieChart, DataBoard, 
    Document, RefreshLeft, UserFilled, DataLine
} from '@element-plus/icons-vue'
import { 
    getTodayStats, 
    getUserStats, 
    getCategoryStats, 
    getRankingStats,
    getSignStats,
    getSignCategoryStats,
    getOverviewStats,
    getUserDetailStats,
    getUserList
} from '@/addon/recycle/api/stats'

// 响应式数据
const userRole = ref('user') // 用户角色
const currentUserName = ref('') // 当前用户名称
const currentUserId = ref(0) // 新增：当前用户ID
const dateRange = ref<string[]>([])
const selectedUserId = ref('')
const activePeriod = ref('today') // 当前激活的时间段

// 快速时间筛选选项
const quickPeriods = ref([
    { key: 'today', label: '今日' },
    { key: 'week', label: '本周' },
    { key: 'month', label: '本月' },
    { key: 'custom', label: '自定义' }
])

// 普通用户数据
const userSignStats = ref<any>({
    signed_order_count: 0,
    signed_device_count: 0,
    category_breakdown: []
})

// 新增：普通用户工作统计数据（合并后的完整数据）
const userWorkStats = ref<any>({
    signed_order_count: 0,
    signed_device_count: 0,
    sign_category_breakdown: [],
    check_count: 0,
    check_category_breakdown: [],
    price_count: 0,
    price_category_breakdown: [],
    payment_count: 0,
    payment_category_breakdown: [],
    recycle_count: 0,
    return_count: 0,
    total_amount: 0
})

// 管理员数据
const overviewStats = ref<any>({
    today_order_count: 0,
    yesterday_order_count: 0,
    today_check_count: 0,
    today_check_breakdown: [],
    today_payment_amount: 0,
    today_payment_count: 0,
    today_return_count: 0
})

const userDetailStats = ref<any[]>([])
const userList = ref<any[]>([])
const userLoading = ref(false)

// 查询参数
const queryParams = ref({
    start_time: '',
    end_time: '',
})

// 图表实例
const userCategoryChart = ref<HTMLElement>()
const adminUserChart = ref<HTMLElement>()

let userCategoryChartInstance: echarts.ECharts | null = null
let adminUserChartInstance: echarts.ECharts | null = null

// 计算属性
const getTimePeriodText = () => {
    if (queryParams.value.start_time && queryParams.value.end_time) {
        if (queryParams.value.start_time === queryParams.value.end_time) {
            return queryParams.value.start_time
        }
        return `${queryParams.value.start_time} 至 ${queryParams.value.end_time}`
    }
    return '今日'
}

// 获取总设备数
const getTotalDevices = () => {
    return (userWorkStats.value.signed_device_count || 0) + 
           (userWorkStats.value.check_count || 0) + 
           (userWorkStats.value.price_count || 0) + 
           (userWorkStats.value.payment_count || 0)
}

// 获取标签文字（根据时间段动态变化）
const getOrderLabel = () => {
    switch (activePeriod.value) {
        case 'week': return '本周订单'
        case 'month': return '本月订单'
        default: return '今日订单'
    }
}

const getCheckLabel = () => {
    switch (activePeriod.value) {
        case 'week': return '本周质检'
        case 'month': return '本月质检'
        default: return '今日质检'
    }
}

const getPaymentLabel = () => {
    switch (activePeriod.value) {
        case 'week': return '本周打款'
        case 'month': return '本月打款'
        default: return '今日打款'
    }
}

const getReturnLabel = () => {
    switch (activePeriod.value) {
        case 'week': return '本周退货'
        case 'month': return '本月退货'
        default: return '今日退货'
    }
}

// 处理快速时间筛选
const handleQuickPeriod = (period: string) => {
    activePeriod.value = period
    const today = new Date()
    let startDate: Date
    let endDate: Date = today
    
    switch (period) {
        case 'today':
            startDate = today
            endDate = today
            break
        case 'week':
            startDate = new Date(today)
            startDate.setDate(today.getDate() - today.getDay()) // 本周开始
            break
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1) // 本月开始
            break
        case 'custom':
            // 自定义模式不设置日期，让用户手动选择
            return
        default:
            startDate = today
    }
    
    const formatDate = (date: Date) => {
        return date.toISOString().split('T')[0]
    }
    
    if (period !== 'custom') {
        queryParams.value.start_time = formatDate(startDate)
        queryParams.value.end_time = formatDate(endDate)
        dateRange.value = [queryParams.value.start_time, queryParams.value.end_time]
        fetchData()
    }
}

// 方法
const handleDateChange = (dates: string[]) => {
    if (dates && dates.length === 2) {
        queryParams.value.start_time = dates[0]
        queryParams.value.end_time = dates[1]
        activePeriod.value = 'custom' // 设置为自定义模式
    } else {
        queryParams.value.start_time = ''
        queryParams.value.end_time = ''
    }
    fetchData()
}

// 初始化普通用户分类饼图
const initUserCategoryChart = () => {
    if (!userCategoryChart.value) return
    
    userCategoryChartInstance = echarts.init(userCategoryChart.value)
    updateUserCategoryChart()
}

// 更新普通用户分类饼图
const updateUserCategoryChart = () => {
    if (!userCategoryChartInstance || !userWorkStats.value.sign_category_breakdown?.length) return
    
    const data = userWorkStats.value.sign_category_breakdown.map((item: any, index: number) => ({
        value: item.count,
        name: item.category_name,
        itemStyle: { 
            color: ['#409EFF', '#67C23A', '#E6A23C', '#F56C6C', '#909399', '#13C2C2', '#722ED1'][index % 7]
        }
    }))
    
    const option = {
        title: {
            text: '设备分类分布',
            left: 'center'
        },
        tooltip: {
            trigger: 'item',
            formatter: '{a} <br/>{b}: {c}台 ({d}%)'
        },
        legend: {
            orient: 'horizontal',
            bottom: '0'
        },
        series: [
            {
                name: '设备类型',
                type: 'pie',
                radius: ['30%', '70%'],
                center: ['50%', '45%'],
                avoidLabelOverlap: false,
                    label: {
                        show: true,
                    formatter: '{b}: {c}台'
                },
                labelLine: {
                    show: true
                },
                data
            }
        ]
    }
    
    userCategoryChartInstance.setOption(option)
}

// 初始化管理员用户对比图表
const initAdminUserChart = () => {
    if (!adminUserChart.value) return
    
    adminUserChartInstance = echarts.init(adminUserChart.value)
    updateAdminUserChart()
}

// 更新管理员用户对比图表
const updateAdminUserChart = () => {
    if (!adminUserChartInstance || !userDetailStats.value.length) return
    
    const users = userDetailStats.value.map(item => item.user_name)
    const signedData = userDetailStats.value.map(item => item.signed_device_count)
    const checkData = userDetailStats.value.map(item => item.check_count)
    const priceData = userDetailStats.value.map(item => item.price_count)
    const paymentData = userDetailStats.value.map(item => item.payment_count)
    
    const option = {
        title: {
            text: '员工工作量对比',
            left: 'center'
        },
        tooltip: {
            trigger: 'axis',
            axisPointer: {
                type: 'shadow'
            }
        },
        legend: {
            top: '30'
        },
        grid: {
            left: '3%',
            right: '4%',
            bottom: '3%',
            containLabel: true
        },
        xAxis: {
            type: 'category',
            data: users,
            axisLabel: {
                rotate: 45
            }
        },
        yAxis: {
            type: 'value'
        },
        series: [
            {
                name: '签收数量',
                type: 'bar',
                data: signedData,
                itemStyle: { color: '#13C2C2' }
            },
            {
                name: '质检数量',
                type: 'bar',
                data: checkData,
                itemStyle: { color: '#409EFF' }
            },
            {
                name: '定价数量',
                type: 'bar',
                data: priceData,
                itemStyle: { color: '#67C23A' }
            },
            {
                name: '打款数量',
                type: 'bar',
                data: paymentData,
                itemStyle: { color: '#E6A23C' }
            }
        ]
    }
    
    adminUserChartInstance.setOption(option)
}

// 获取用户角色和基本信息
const fetchUserRole = async () => {
    try {
        const res = await getTodayStats({})
        if (res.data) {
            userRole.value = res.data.user_role || 'user'
            currentUserName.value = res.data.user_name || '用户'
            currentUserId.value = res.data.user_id || 0 // 保存用户ID
            console.log('用户角色:', userRole.value, '用户ID:', currentUserId.value)
        }
    } catch (e) {
        console.error("获取用户角色失败", e)
    }
}

// 新增：获取普通用户工作统计（包含签收、质检、定价、打款的完整统计）
const fetchUserWorkStats = async () => {
    try {
        const params = {
            ...queryParams.value,
            user_id: currentUserId.value
        }
        const res = await getUserStats(params)
        
        // 从返回的数组中找到当前用户的数据
        if (res.data && res.data.length > 0) {
            const userData = res.data[0]
            
            // 更新完整的工作统计数据
            userWorkStats.value = {
                // 签收数据
                signed_order_count: userData.signed_order_count || 0,
                signed_device_count: userData.signed_device_count || 0,
                sign_category_breakdown: userData.sign_category_breakdown || [],
                // 质检数据
                check_count: userData.check_count || 0,
                check_category_breakdown: userData.check_category_breakdown || [],
                // 定价数据
                price_count: userData.price_count || 0,
                price_category_breakdown: userData.price_category_breakdown || [],
                // 打款数据
                payment_count: userData.payment_count || 0,
                payment_category_breakdown: userData.payment_category_breakdown || [],
                // 其他数据
                recycle_count: userData.recycle_count || 0,
                return_count: userData.return_count || 0,
                total_amount: userData.total_amount || 0
            }
            
            // 同时更新旧的userSignStats数据结构（为了兼容现有模板）
            userSignStats.value = {
                signed_order_count: userData.signed_order_count || 0,
                signed_device_count: userData.signed_device_count || 0,
                category_breakdown: userData.sign_category_breakdown || []
            }
        } else {
            // 重置数据
            userWorkStats.value = {
                signed_order_count: 0,
                signed_device_count: 0,
                sign_category_breakdown: [],
                check_count: 0,
                check_category_breakdown: [],
                price_count: 0,
                price_category_breakdown: [],
                payment_count: 0,
                payment_category_breakdown: [],
                recycle_count: 0,
                return_count: 0,
                total_amount: 0
            }
            
            userSignStats.value = {
                signed_order_count: 0,
                signed_device_count: 0,
                category_breakdown: []
            }
        }
        
        // 更新图表
        nextTick(() => {
            if (!userCategoryChartInstance) {
                initUserCategoryChart()
            } else {
                updateUserCategoryChart()
            }
        })
    } catch (e) {
        console.error("获取用户工作统计失败", e)
        ElMessage.error('获取工作统计失败')
    }
}

// 获取管理员概况统计
const fetchOverviewStats = async () => {
    try {
        const res = await getOverviewStats(queryParams.value)
        overviewStats.value = res.data || {}
    } catch (e) {
        console.error("获取概况统计失败", e)
        ElMessage.error('获取概况统计失败')
    }
}

// 获取用户列表
const fetchUserList = async () => {
    try {
        const res = await getUserList()
        userList.value = res.data || []
    } catch (e) {
        console.error("获取用户列表失败", e)
    }
}

// 获取用户详细统计
const fetchUserDetailStats = async () => {
    userLoading.value = true
    try {
        const params = {
            ...queryParams.value,
            user_id: selectedUserId.value
        }
        const res = await getUserDetailStats(params)
        userDetailStats.value = res.data || []
        
        // 更新图表
        nextTick(() => {
            if (!adminUserChartInstance) {
                initAdminUserChart()
                } else {
                updateAdminUserChart()
            }
        })
    } catch (e) {
        console.error("获取用户详细统计失败", e)
        ElMessage.error('获取用户统计失败')
    } finally {
        userLoading.value = false
    }
}

// 窗口大小变化时重新调整图表
const handleResize = () => {
    userCategoryChartInstance?.resize()
    adminUserChartInstance?.resize()
}

// 获取所有数据
const fetchData = async () => {
    // 首先获取用户角色
    await fetchUserRole()
    
    // 根据角色获取不同的数据
    if (userRole.value === 'admin') {
        await fetchOverviewStats()
        await fetchUserList()
        await fetchUserDetailStats()
    } else {
        await fetchUserWorkStats()
    }
}

// 初始化
onMounted(() => {
    fetchData()
    
    // 监听窗口大小变化
    window.addEventListener('resize', handleResize)
})

// 组件卸载时清理
onUnmounted(() => {
    window.removeEventListener('resize', handleResize)
    userCategoryChartInstance?.dispose()
    adminUserChartInstance?.dispose()
})
</script>

<style lang="scss" scoped>
/* 自定义日期选择器样式 */
.custom-date-picker {
    :deep(.el-input__wrapper) {
        @apply border-gray-300 rounded-lg;
    }
    
    :deep(.el-input__wrapper:hover) {
        @apply border-blue-400;
    }
    
    :deep(.el-input__wrapper.is-focus) {
        @apply border-blue-500 shadow-sm;
    }
}

/* Element Plus 表格优化 */
:deep(.el-table) {
    @apply border-0 rounded-lg overflow-hidden;
    
    .el-table__header {
        @apply bg-gray-50;
        
        th {
            @apply bg-gray-50 border-b border-gray-200 text-gray-700 font-medium;
        }
    }
    
    .el-table__body {
        tr {
            @apply hover:bg-blue-50 transition-colors duration-200;
            
            td {
                @apply border-b border-gray-100;
            }
        }
    }
}

/* Element Plus 选择器优化 */
:deep(.el-select) {
    .el-input__wrapper {
        @apply border-gray-300 rounded-lg;
    }
    
    .el-input__wrapper:hover {
        @apply border-blue-400;
    }
    
    .el-input__wrapper.is-focus {
        @apply border-blue-500 shadow-sm;
    }
}

/* 加载动画优化 */
:deep(.el-loading-mask) {
    background-color: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(4px);
}

/* 响应式调整 */
@media (max-width: 640px) {
    .custom-date-picker {
        width: 100%;
    }
}
</style> 