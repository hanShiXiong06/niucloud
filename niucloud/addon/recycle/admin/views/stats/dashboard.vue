<template>
  <div
    class="min-h-screen bg-gradient-to-br from-blue-50 via-white to-purple-50"
  >
    <!-- 页面标题和快速筛选 -->
    <div class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-10">
      <div class="mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div
          class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4"
        >
          <!-- 页面标题 -->
          <div class="flex items-center space-x-3">
            <div
              class="w-8 h-8 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg flex items-center justify-center"
            >
              <el-icon :size="18" color="white">
                <DataAnalysis />
              </el-icon>
            </div>
            <div>
              <h1 class="text-xl font-semibold text-gray-900">数据概览</h1>
              <p class="text-sm text-gray-500">
                {{ userRole === "admin" ? "管理员控制台" : "工作台数据" }}
              </p>
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
                    : 'text-gray-600 hover:text-blue-600 hover:bg-white/50',
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
                <el-icon :size="14" color="white" class="mr-1">
                  <Search />
                </el-icon>
                查询
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 主要内容区域 -->
    <div class="mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- 普通用户视图 -->
      <div
        v-if="
          userRole === 'user' || userRole === 'checker' || userRole === 'pricer'
        "
        class="space-y-6"
      >
        <!-- 用户工作概览 -->
        <SectionHeader
          :title="currentUserName"
          :subtitle="`${getTimePeriodText()} 工作概览`"
          :icon="User"
        >
          <template #right>
            <div class="text-right">
              <div class="text-white/80 text-xs">总计设备</div>
              <div class="text-white text-xl font-bold">
                {{ getTotalDevices() }}
              </div>
            </div>
          </template>
        </SectionHeader>

        <!-- 统计卡片网格 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-2">
          <!-- 签收统计 -->
          <StatCard
            title="签收订单"
            :mainValue="userWorkStats.signed_order_count || 0"
            :subValue="`${userWorkStats.signed_device_count || 0} 台`"
            color="blue"
            :icon="Box"
            :showIndicator="true"
          />

          <!-- 质检统计 -->
          <StatCard
            v-if="
              userWorkStats.check_count > 0 ||
              ['checker', 'admin'].includes(userRole)
            "
            title="质检设备"
            :mainValue="userWorkStats.check_count || 0"
            subValue="已质检"
            color="green"
            :icon="Search"
            :showIndicator="true"
          />

          <!-- 定价统计 -->
          <StatCard
            v-if="
              userWorkStats.price_count > 0 ||
              ['pricer', 'admin'].includes(userRole)
            "
            title="定价设备"
            :mainValue="userWorkStats.price_count || 0"
            :subValue="`¥${userWorkStats.total_amount || 0}`"
            color="orange"
            :icon="PriceTag"
            :showIndicator="true"
          />

          <!-- 打款统计 -->
          <StatCard
            v-if="
              userWorkStats.payment_count > 0 ||
              ['pricer', 'admin'].includes(userRole)
            "
            title="打款设备"
            :mainValue="userWorkStats.payment_count || 0"
            subValue="已打款"
            color="red"
            :icon="Money"
            :showIndicator="true"
          />
        </div>

        <!-- 设备分类图表 -->
        <ChartCard title="设备分类分布" :icon="PieChart">
          <div ref="userCategoryChart" class="w-full h-80"></div>
        </ChartCard>
      </div>

      <!-- 管理员视图 -->
      <div v-else-if="userRole === 'admin'" class="space-y-6">
        <!-- 运营概览区块 -->
        <el-row gutter="16">
          <el-col :span="12">
            <div class="space-y-4">
              <SectionHeader
                title="运营概览"
                :subtitle="`${getTimePeriodText()} 业务数据`"
                :icon="DataBoard"
                bgClass="bg-gradient-to-r from-blue-400 to-blue-500"
              />

              <!-- 统计卡片 -->
              <el-row :gutter="16">
                <el-col :span="24">
                  <div
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4"
                  >
                    <StatCard
                      :title="getOrderLabel()"
                      :mainValue="overviewStats.today_order_count || 0"
                      :subValue="`昨日 ${
                        overviewStats.yesterday_order_count || 0
                      }`"
                      color="blue"
                      link="/recycle_order/list"
                      :icon="Document"
                    />

                    <StatCard
                      :title="getCheckLabel()"
                      :mainValue="overviewStats.today_check_count || 0"
                      color="green"
                      link="/recycle_order/list"
                      :icon="Search"
                      :extraInfo="(overviewStats.today_check_breakdown || []).slice(0, 2).map((item: any) => `${item.category_name} ${item.count}`)"
                    />

                    <StatCard
                      :title="getPaymentLabel()"
                      :mainValue="`¥${overviewStats.today_payment_amount || 0}`"
                      :subValue="`${overviewStats.today_payment_count || 0} 台`"
                      color="orange"
                      link="/recycle_order/list"
                      :icon="Money"
                    />

                    <StatCard
                      :title="getReturnLabel()"
                      :mainValue="overviewStats.today_return_count || 0"
                      subValue="设备数量"
                      link="/recycle_return_order/list"
                      color="red"
                      :icon="RefreshLeft"
                    />
                  </div>
                </el-col>
                <el-col :span="12">
                  <!-- 运营概览环形图 -->
                  <ChartCard
                    title="业务分布"
                    :icon="PieChart"
                    contentClass="py-2 "
                    class="mt-4"
                  >
                    <div ref="overviewRingChart" class="w-full h-80"></div>
                  </ChartCard>
                </el-col>
                <el-col :span="12"  class="mt-4">
                  <!-- 员工工作统计区块 - 优化布局 -->
                  <div class="h-full flex flex-col bg-white rounded-lg ">

                    <div
                      ref="adminUserChart"
                      class="flex-1 min-h-[380px]"
                    ></div>
                  </div>
                </el-col>
                <el-col :xs="24" :lg="24" class="mt-4">
                  <ChartCard title="员工工作统计" :icon="UserFilled">
                    <template #header-right v-if="userList.length > 0">
                      <div class="flex items-center gap-2">
                        <span class="text-xs text-gray-600 whitespace-nowrap"
                          >员工筛选：</span
                        >
                        <el-select
                          v-model="selectedUserId"
                          placeholder="全部员工"
                          clearable
                          @change="handleFetchUserDetailStats"
                        >
                          <el-option label="全部员工" value="" />
                          <el-option
                            v-for="user in userList"
                            :key="user.uid"
                            :label="user.real_name || user.username"
                            :value="String(user.uid)"
                          />
                        </el-select>
                      </div>
                    </template>

                    <!-- 采用左右布局：表格在左，图表在右 -->
                    <el-row :gutter="16">
                      <!-- 左侧：数据表格 -->
                      <el-col :xs="24" :lg="24">
                        <div class="w-full">
                          <el-table
                            :data="userDetailStats"
                            v-loading="userLoading"
                            size="small"
                            class="w-full min-h-[293px]"
                          >
                            <el-table-column
                              prop="user_name"
                              label="员工"
                              fixed
                            />
                            <el-table-column
                              prop="user_type_name"
                              label="角色"
                            />
                            <el-table-column
                              prop="signed_order_count"
                              label="签收单"
                            />
                            <el-table-column
                              prop="signed_device_count"
                              label="签收台"
                            />
                            <el-table-column prop="check_count" label="质检" />
                            <el-table-column prop="price_count" label="定价" />
                            <el-table-column
                              prop="payment_count"
                              label="打款"
                            />
                          </el-table>
                        </div>
                      </el-col>

                      <!-- 右侧：对比图表 -->
                    </el-row>
                  </ChartCard>
                 
                </el-col>
              </el-row>
            </div>
          </el-col>
          <el-col :span="12">
            <!-- 会员统计区块 -->
            <div class="space-y-4">
              <SectionHeader
                title="会员统计"
                :subtitle="`${getTimePeriodText()} 用户数据`"
                :icon="User"
                bgClass="bg-gradient-to-r from-purple-500 to-pink-500"
              />

              <!-- 会员统计概览卡片 -->
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <StatCard
                  title="总用户数"
                  :mainValue="memberStatsOverview.total_members || 0"
                  subValue="注册用户"
                  color="purple"
                  :icon="User"
                />

                <StatCard
                  title="新增用户"
                  :mainValue="memberStatsOverview.new_members || 0"
                  :subValue="`期间注册`"
                  color="blue"
                  :icon="UserFilled"
                />

                <StatCard
                  title="活跃用户"
                  :mainValue="memberStatsOverview.active_members || 0"
                  subValue="期间活跃"
                  color="green"
                  :icon="View"
                />

                <StatCard
                  title="拉新用户"
                  :mainValue="memberStatsOverview.invite_members || 0"
                  subValue="被推广注册"
                  color="orange"
                  :icon="Share"
                />
              </div>

              <!-- 会员统计图表区域 -->
              <el-row :gutter="16">
                <!-- 左侧：注册趋势 + 渠道分布 -->
                <el-col :xs="24" :lg="16">
                  <el-row :gutter="16">
                    <!-- 注册趋势图 -->
                    <el-col :span="24">
                      <ChartCard
                        title="注册趋势"
                        :icon="TrendCharts"
                        contentClass="py-2"
                      >
                        <div
                          ref="memberRegisterTrendChart"
                          class="w-full h-80"
                          v-loading="memberLoading"
                        ></div>
                      </ChartCard>
                    </el-col>

                    <!-- 活跃度统计图 -->
                    <el-col :span="24" class="mt-4">
                      <ChartCard
                        title="用户活跃度"
                        :icon="DataLine"
                        contentClass="py-2"
                      >
                        <div
                          ref="memberActivityChart"
                          class="w-full h-80"
                          v-loading="memberLoading"
                        ></div>
                      </ChartCard>
                    </el-col>
                  </el-row>
                </el-col>

                <!-- 右侧：注册渠道 + 拉新排行 -->
                <el-col :xs="24" :lg="8">
                  <el-row :gutter="16">
                    <!-- 注册渠道分布 -->
                    <el-col :span="24">
                      <ChartCard
                        title="注册渠道"
                        :icon="PieChart"
                        contentClass="py-2"
                      >
                        <div
                          ref="memberChannelChart"
                          class="w-full h-80"
                          v-loading="memberLoading"
                        ></div>
                      </ChartCard>
                    </el-col>

                    <!-- 拉新排行榜 -->
                    <el-col :span="24" class="mt-4">
                      <ChartCard
                        title="拉新排行榜 TOP10"
                        :icon="Trophy"
                        contentClass="py-2"
                      >
                        <div
                          class="w-full h-80 overflow-y-auto"
                          v-loading="memberLoading"
                        >
                          <div
                            v-if="memberInviteRank.length === 0"
                            class="flex items-center justify-center h-full text-gray-400"
                          >
                            暂无数据
                          </div>
                          <div v-else class="space-y-2 p-2">
                            <div
                              v-for="(item, index) in memberInviteRank"
                              :key="item.member_id"
                              class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                              <div class="flex items-center gap-3">
                                <!-- 排名 -->
                                <div
                                  :class="[
                                    'w-7 h-7 flex items-center justify-center rounded-full text-sm font-bold',
                                    index === 0
                                      ? 'bg-yellow-400 text-white'
                                      : index === 1
                                      ? 'bg-gray-300 text-white'
                                      : index === 2
                                      ? 'bg-orange-400 text-white'
                                      : 'bg-blue-100 text-blue-600',
                                  ]"
                                >
                                  {{ index + 1 }}
                                </div>
                                <!-- 用户信息 -->
                                <div>
                                  <div
                                    class="text-sm font-medium text-gray-900"
                                  >
                                    {{ item.nickname }}
                                  </div>
                                  <div class="text-xs text-gray-500">
                                    {{ item.mobile }}
                                  </div>
                                </div>
                              </div>
                              <!-- 拉新数量 -->
                              <div class="text-right">
                                <div class="text-lg font-bold text-blue-600">
                                  {{ item.invite_count }}
                                </div>
                                <div class="text-xs text-gray-500">人</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </ChartCard>
                    </el-col>
                  </el-row>
                </el-col>
              </el-row>
            </div>
          </el-col>
        </el-row>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted, nextTick, watch } from "vue";
import {
  DataAnalysis,
  Search,
  User,
  Box,
  PriceTag,
  Money,
  PieChart,
  DataBoard,
  Document,
  RefreshLeft,
  UserFilled,
  TrendCharts,
  DataLine,
  View,
  Share,
  Trophy,
} from "@element-plus/icons-vue";

// 导入组件
import StatCard from "./components/StatCard.vue";
import ChartCard from "./components/ChartCard.vue";
import SectionHeader from "./components/SectionHeader.vue";

// 导入 hooks
import { useStatsData } from "./hooks/useStatsData";
import { useCharts } from "./hooks/useCharts";
import { useDateFilter } from "./hooks/useDateFilter";

// 使用 hooks - 数据管理
const {
  userRole,
  currentUserName,
  queryParams,
  userWorkStats,
  overviewStats,
  userDetailStats,
  userList,
  userLoading,
  memberStatsOverview,
  memberRegisterTrend,
  memberChannelStats,
  memberInviteRank,
  memberActivityStats,
  memberLoading,
  fetchUserDetailStats,
  fetchData,
} = useStatsData();

// 使用 hooks - 图表管理
const {
  userCategoryChart,
  adminUserChart,
  overviewRingChart,
  memberRegisterTrendChart,
  memberChannelChart,
  memberActivityChart,
  initUserCategoryChart,
  updateUserCategoryChart,
  initAdminUserChart,
  updateAdminUserChart,
  initOverviewRingChart,
  updateOverviewRingChart,
  initMemberRegisterTrendChart,
  updateMemberRegisterTrendChart,
  initMemberChannelChart,
  updateMemberChannelChart,
  initMemberActivityChart,
  updateMemberActivityChart,
  handleResize,
  disposeCharts,
} = useCharts();

// 使用 hooks - 日期筛选
const {
  dateRange,
  activePeriod,
  quickPeriods,
  handleQuickPeriod: handleQuickPeriodBase,
  handleDateChange: handleDateChangeBase,
  getTimePeriodText: getTimePeriodTextBase,
  getPeriodLabel,
} = useDateFilter();

// 选中的用户ID
const selectedUserId = ref("");

// 包装 hooks 方法
const handleQuickPeriod = (period: string) => {
  handleQuickPeriodBase(period, (start, end) => {
    queryParams.value.start_time = start;
    queryParams.value.end_time = end;
    fetchData();
  });
};

const handleDateChange = (dates: string[]) => {
  handleDateChangeBase(dates, (start, end) => {
    queryParams.value.start_time = start;
    queryParams.value.end_time = end;
    fetchData();
  });
};

const getTimePeriodText = () => {
  return getTimePeriodTextBase(
    queryParams.value.start_time,
    queryParams.value.end_time
  );
};

const handleFetchUserDetailStats = () => {
  fetchUserDetailStats(selectedUserId.value);
};

const getTotalDevices = () => {
  return (
    (userWorkStats.value.signed_device_count || 0) +
    (userWorkStats.value.check_count || 0) +
    (userWorkStats.value.price_count || 0) +
    (userWorkStats.value.payment_count || 0)
  );
};

const getOrderLabel = () => getPeriodLabel("order");
const getCheckLabel = () => getPeriodLabel("check");
const getPaymentLabel = () => getPeriodLabel("payment");
const getReturnLabel = () => getPeriodLabel("return");

// 监听数据变化，更新图表
watch(
  () => userWorkStats.value.sign_category_breakdown,
  (newData) => {
    nextTick(() => {
      if (!userCategoryChart.value) return;
      initUserCategoryChart();
      updateUserCategoryChart(newData || []);
    });
  },
  { deep: true, immediate: true }
);

watch(
  () => userDetailStats.value,
  (newData) => {
    nextTick(() => {
      if (!adminUserChart.value) return;
      initAdminUserChart();
      updateAdminUserChart(newData || []);
    });
  },
  { deep: true, immediate: true }
);

// 监听运营概览数据变化，更新环形图
watch(
  () => overviewStats.value,
  (newData) => {
    nextTick(() => {
      if (!overviewRingChart.value) return;
      initOverviewRingChart();
      updateOverviewRingChart(newData || {});
    });
  },
  { deep: true, immediate: true }
);

// 监听会员注册趋势数据变化
watch(
  () => memberRegisterTrend.value,
  (newData) => {
    nextTick(() => {
      if (!memberRegisterTrendChart.value) return;
      initMemberRegisterTrendChart();
      updateMemberRegisterTrendChart(newData || []);
    });
  },
  { deep: true, immediate: true }
);

// 监听会员渠道数据变化
watch(
  () => memberChannelStats.value,
  (newData) => {
    nextTick(() => {
      if (!memberChannelChart.value) return;
      initMemberChannelChart();
      updateMemberChannelChart(newData || []);
    });
  },
  { deep: true, immediate: true }
);

// 监听会员活跃度数据变化
watch(
  () => memberActivityStats.value,
  (newData) => {
    nextTick(() => {
      if (!memberActivityChart.value) return;
      initMemberActivityChart();
      updateMemberActivityChart(newData || []);
    });
  },
  { deep: true, immediate: true }
);

// 初始化
onMounted(() => {
  fetchData();
  window.addEventListener("resize", handleResize);
});

// 清理
onUnmounted(() => {
  window.removeEventListener("resize", handleResize);
  disposeCharts();
});
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

/* 紧凑表格样式 */
.compact-table {
  :deep(.el-table) {
    @apply border-0 rounded-lg;

    .el-table__header {
      th {
        @apply bg-gray-50 text-gray-700 font-medium;
      }
    }

    .el-table__body {
      tr {
        @apply hover:bg-blue-50 transition-colors;

        td {
          @apply border-b border-gray-100;
        }
      }
    }
  }
}
:deep(.el-select) {
  width: 100px;
}

/* 响应式调整 */
@media (max-width: 1024px) {
  .custom-date-picker {
    width: 100%;
  }
}
</style>
