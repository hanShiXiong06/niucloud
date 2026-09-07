<template>
  <PremiumTheme class="min-h-screen bg-gray-50">
    <!-- 页面标题和快速筛选 -->
    <div class="bg-white shadow-sm border-b border-gray-100 sticky top-0 z-10">
      <div class="mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <HsxTitle size="page" collapsible-subtitle>
            <template #default>数据概览</template>
            <template #subtitle>
                {{ userRole === "admin" ? "管理员控制台" : "工作台数据" }}
            </template>
            <template #extra>
                <el-dropdown
                    v-if="canShowWidget('quick_express_ship') || canShowWidget('quick_express_track')"
                    trigger="click"
                    @command="handleExpressQuickCommand"
                >
                    <el-button type="primary">
                快递
                        <el-icon class="el-icon--right">
                            <ArrowDown />
                        </el-icon>
                    </el-button>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item v-if="canShowWidget('quick_express_ship')" command="ship">
                                <el-icon><Box /></el-icon>
                    快速寄件
                            </el-dropdown-item>
                            <el-dropdown-item v-if="canShowWidget('quick_express_track')" command="track">
                                <el-icon><Search /></el-icon>
                    快速查件
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>

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
                        @click="handleRefresh"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200 shadow-sm"
                    >
                        <el-icon :size="14" color="white" class="mr-1">
                            <Search />
                        </el-icon>
                查询
                    </button>
                </div>
            </template>
        </HsxTitle>
      </div>
    </div>

    <!-- 主要内容区域 -->
    <div class="mx-auto">
      <section class="mb-6 rounded-lg border border-gray-200 bg-white shadow-sm">

        <div class="p-5">
          <BoardTabs
            v-if="dashboardTabs.length"
            v-model="activeDashboard"
            :tabs="dashboardTabs"
            class="mb-5"
          />

          <DashboardEmptyState
            v-if="!dashboardTabs.length"
            title="当前账号暂无可见看板"
            description="可到首页配置中开启业务、财务或用户看板，并按角色或员工分配可见权限。"
          />
          <el-skeleton v-else-if="businessLoading && !businessDashboard.cards.length" :rows="4" animated />
          <div
            v-else-if="activeDashboard === 'business'"
            class="space-y-4"
          >
            <RecycleOverviewBoard
              :dashboard="businessDashboard"
              :trend="businessTrend"
              :loading="businessLoading || businessTrendLoading"
              :date-label="dashboardDateLabel"
              @refresh="handleRefresh"
              @drilldown-card="handleMetricDrilldown"
              @drilldown-ledger="handleLedgerDrilldown"
              @drilldown-task="handleTaskDrilldown"
              @consignment="goConsignmentStatus"
            />
          </div>

          <div v-else-if="activeDashboard === 'finance'" class="space-y-4">
            <DashboardSummary
              board="finance"
              :cards="businessDashboard.cards"
              :todo="businessDashboard.todo"
              :date-label="dashboardDateLabel"
            />

            <MetricGrid
              v-if="canShowWidget('finance_dashboard_metrics')"
              :cards="financeMetricCards"
              tone="finance"
              columns="finance"
              @drilldown="handleMetricDrilldown"
            />

            <ChartCard title="打款台账" :icon="Money" contentClass="p-0">
              <div class="grid grid-cols-2 gap-3 p-4 lg:grid-cols-4">
                <div
                  v-for="item in financeLedgerStats"
                  :key="item.label"
                  class="rounded-lg border border-gray-100 bg-gray-50 p-3"
                >
                  <div class="text-xs text-gray-500">{{ item.label }}</div>
                  <div class="mt-1 text-xl font-semibold text-gray-900">
                    ¥{{ item.value }}
                  </div>
                </div>
              </div>
              <div class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">
                {{ businessDashboard.finance_summary?.caliber || "按实际打款时间统计。" }}
              </div>
            </ChartCard>

            <ChartCard
              v-if="canShowWidget('finance_dashboard_trend')"
              title="资金趋势"
              :icon="TrendCharts"
              contentClass="py-2"
            >
              <template #header-right>
                <span class="text-xs text-gray-500">按所选时间展示打款金额变化</span>
              </template>
              <div
                ref="financeTrendChart"
                class="w-full h-[320px]"
                v-loading="businessTrendLoading"
              ></div>
            </ChartCard>
          </div>

          <div v-else class="space-y-4">
            <DashboardSummary
              board="user"
              :cards="businessDashboard.cards"
              :todo="businessDashboard.todo"
              :date-label="dashboardDateLabel"
            />
          </div>
        </div>
      </section>

      <!-- 普通用户视图 -->
      <div
        v-if="
          activeDashboard === 'user' &&
          (userRole === 'user' || userRole === 'checker' || userRole === 'pricer')
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
            v-if="canShowWidget('my_signed_devices')"
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
              canShowWidget('my_check_count') &&
              (userWorkStats.check_count > 0 ||
                ['checker', 'admin'].includes(userRole))
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
              canShowWidget('my_price_count') &&
              (userWorkStats.price_count > 0 ||
                ['pricer', 'admin'].includes(userRole))
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
              canShowWidget('my_payment_count') &&
              (userWorkStats.payment_count > 0 ||
                ['pricer', 'admin'].includes(userRole))
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
        <ChartCard v-if="canShowWidget('user_category_chart')" title="设备分类分布" :icon="PieChart">
          <div ref="userCategoryChart" class="w-full h-80"></div>
        </ChartCard>
      </div>

      <!-- 管理员视图 -->
      <div v-if="activeDashboard === 'user' && userRole === 'admin'" class="space-y-6">
        <section class="space-y-4">
          <SectionHeader
            title="团队工作统计"
            :subtitle="`${getTimePeriodText()} 员工处理数据`"
            :icon="DataBoard"
            bgClass="bg-blue-600"
          />

          <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">
            <ChartCard
              v-if="canShowWidget('staff_work_chart')"
              title="员工工作量对比"
              :icon="DataBoard"
              contentClass="py-2"
            >
              <div ref="adminUserChart" class="w-full min-h-[360px]"></div>
            </ChartCard>

            <ChartCard v-if="canShowWidget('staff_work_table')" title="员工工作明细" :icon="UserFilled">
              <template #header-right v-if="userList.length > 0">
                <div class="flex items-center gap-2">
                  <span class="text-xs text-gray-600 whitespace-nowrap">员工筛选：</span>
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

              <el-table
                :data="userDetailStats"
                v-loading="userLoading"
                size="small"
                class="w-full min-h-[360px]"
              >
                <el-table-column prop="user_name" label="员工" min-width="120" fixed />
                <el-table-column prop="user_type_name" label="角色" min-width="100" />
                <el-table-column prop="signed_order_count" label="签收单" min-width="90" />
                <el-table-column prop="signed_device_count" label="签收台" min-width="90" />
                <el-table-column prop="check_count" label="质检" min-width="80" />
                <el-table-column prop="price_count" label="定价" min-width="80" />
                <el-table-column prop="payment_count" label="打款" min-width="80" />
              </el-table>
            </ChartCard>
          </div>
        </section>

        <section v-if="canShowWidget('member_stats_overview')" class="space-y-4">
          <SectionHeader
            title="会员统计"
            :subtitle="`${getTimePeriodText()} 用户数据`"
            :icon="User"
            bgClass="bg-indigo-600"
          />

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

          <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <div class="space-y-4 xl:col-span-2">
              <ChartCard title="注册趋势" :icon="TrendCharts" contentClass="py-2">
                <div
                  ref="memberRegisterTrendChart"
                  class="w-full h-80"
                  v-loading="memberLoading"
                ></div>
              </ChartCard>

              <ChartCard title="用户活跃度" :icon="DataLine" contentClass="py-2">
                <div
                  ref="memberActivityChart"
                  class="w-full h-80"
                  v-loading="memberLoading"
                ></div>
              </ChartCard>
            </div>

            <div class="space-y-4">
              <ChartCard title="注册渠道" :icon="PieChart" contentClass="py-2">
                <div
                  ref="memberChannelChart"
                  class="w-full h-80"
                  v-loading="memberLoading"
                ></div>
              </ChartCard>

              <ChartCard title="拉新排行榜 TOP10" :icon="Trophy" contentClass="py-2">
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
                      class="flex items-center justify-between rounded-lg bg-gray-50 p-3 transition-colors hover:bg-gray-100"
                    >
                      <div class="flex min-w-0 items-center gap-3">
                        <div
                          :class="[
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-sm font-bold',
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
                        <div class="min-w-0">
                          <div class="truncate text-sm font-medium text-gray-900">
                            {{ item.nickname }}
                          </div>
                          <div class="truncate text-xs text-gray-500">
                            {{ item.mobile }}
                          </div>
                        </div>
                      </div>
                      <div class="ml-3 shrink-0 text-right">
                        <div class="text-lg font-bold text-blue-600">
                          {{ item.invite_count }}
                        </div>
                        <div class="text-xs text-gray-500">人</div>
                      </div>
                    </div>
                  </div>
                </div>
              </ChartCard>
            </div>
          </div>
        </section>
      </div>
    </div>
  </PremiumTheme>
</template>

<script setup lang="ts">
import { HsxTitle } from '@/addon/hsx_components/core'
import PremiumTheme from '@/addon/hsx_recycle/components/PremiumTheme.vue'
import { computed, ref, onMounted, onUnmounted, nextTick, watch } from "vue";
import { useRouter } from "vue-router";
import {
  ArrowDown,
  DataAnalysis,
  Search,
  User,
  Box,
  PriceTag,
  Money,
  PieChart,
  DataBoard,
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
import BoardTabs from "./components/dashboard/BoardTabs.vue";
import DashboardEmptyState from "./components/dashboard/DashboardEmptyState.vue";
import DashboardSummary from "./components/dashboard/DashboardSummary.vue";
import MetricGrid from "./components/dashboard/MetricGrid.vue";
import RecycleOverviewBoard from "./components/dashboard/RecycleOverviewBoard.vue";

// 导入 hooks
import { useStatsData } from "./hooks/useStatsData";
import { useCharts } from "./hooks/useCharts";
import { useDateFilter } from "./hooks/useDateFilter";
import {
  getRecycleDashboardOverview,
  getRecycleDashboardTrend,
} from "@/addon/hsx_recycle/api/stats";
import type {
  BusinessDashboard,
  BusinessTrend,
  DashboardMetricCard,
  DashboardTabItem,
} from "./types/dashboard";

// 使用 hooks - 数据管理
const {
  userRole,
  currentUserName,
  queryParams,
  userWorkStats,
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
  canShowWidget,
} = useStatsData();

// 使用 hooks - 图表管理
const {
  userCategoryChart,
  adminUserChart,
  financeTrendChart,
  memberRegisterTrendChart,
  memberChannelChart,
  memberActivityChart,
  initUserCategoryChart,
  updateUserCategoryChart,
  initAdminUserChart,
  updateAdminUserChart,
  initFinanceTrendChart,
  updateFinanceTrendChart,
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
} = useDateFilter();

const router = useRouter();
const activeDashboard = ref("business");
const businessLoading = ref(false);
const businessTrendLoading = ref(false);
const businessDashboard = ref<BusinessDashboard>({
  date_range: {},
  thresholds: {},
  cards: [],
  todo: [],
  ledger: {
    order_count: 0,
    device_count: 0,
    signed_order_count: 0,
    signed_device_count: 0,
    pending_sign_order_count: 0,
    pending_check_device_count: 0,
    checking_device_count: 0,
    pending_quote_device_count: 0,
    pending_confirm_count: 0,
    pending_pay_order_count: 0,
    pending_pay_device_count: 0,
    completed_order_count: 0,
    completed_device_count: 0,
    return_device_count: 0,
    pending_return_count: 0,
  },
  finance_summary: {
    selected_paid_amount: "0.00",
    today_paid_amount: "0.00",
    week_paid_amount: "0.00",
    month_paid_amount: "0.00",
    pending_pay_amount: "0.00",
    caliber: "",
  },
  today_business: {
    order_count: 0,
    device_count: 0,
    recycled_device_count: 0,
    sold_device_count: 0,
    category_breakdown: [],
    source_breakdown: [],
    delivery_breakdown: [],
    order_status_breakdown: [],
    device_status_breakdown: [],
    price_summary: {
      count: 0,
      min: "0.00",
      max: "0.00",
      avg: "0.00",
      label: "0.00 - 0.00",
    },
    price_ranges: [],
    consignment: {
      today_count: 0,
      pending_count: 0,
      selling_count: 0,
      pending_settlement_count: 0,
      sold_today_count: 0,
      sold_today_amount: "0.00",
    },
  },
  responsibility: {
    total_pending_devices: 0,
    explain: "",
    tasks: [],
  },
  explain: "",
});
const businessTrend = ref<BusinessTrend>({
  date_range: {},
  x_axis: [],
  series: [],
  explain: "",
});

// 选中的用户ID
const selectedUserId = ref("");

const dashboardTabs = computed<DashboardTabItem[]>(() => {
  const tabs = [
    {
      key: "business",
      label: "业务看板",
      visible: canShowWidget("business_dashboard_metrics") || canShowWidget("business_dashboard_trend"),
    },
    {
      key: "finance",
      label: "财务看板",
      visible: canShowWidget("finance_dashboard_metrics") || canShowWidget("finance_dashboard_trend"),
    },
    {
      key: "user",
      label: userRole.value === "admin" ? "用户看板" : "我的看板",
      visible:
        canShowWidget("user_dashboard_work") ||
        canShowWidget("my_signed_devices") ||
        canShowWidget("my_check_count") ||
        canShowWidget("my_price_count") ||
        canShowWidget("my_payment_count") ||
        canShowWidget("staff_work_chart") ||
        canShowWidget("staff_work_table") ||
        canShowWidget("member_stats_overview"),
    },
  ];

  return tabs.filter((tab) => tab.visible);
});

const financeMetricCards = computed(() => {
  return businessDashboard.value.cards.filter((card) => ["资金", "库存"].includes(card.category));
});

const financeLedgerStats = computed(() => {
  const finance = businessDashboard.value.finance_summary || {
    selected_paid_amount: "0.00",
    today_paid_amount: "0.00",
    week_paid_amount: "0.00",
    month_paid_amount: "0.00",
    last_7_days_paid_amount: "0.00",
    last_30_days_paid_amount: "0.00",
  };

  return [
    { label: "所选时间打款", value: finance.selected_paid_amount || "0.00" },
    { label: "今日打款", value: finance.today_paid_amount || "0.00" },
    { label: "近7天打款", value: finance.last_7_days_paid_amount || finance.week_paid_amount || "0.00" },
    { label: "近30天打款", value: finance.last_30_days_paid_amount || finance.month_paid_amount || "0.00" },
  ];
});

const dashboardDateLabel = computed(() => {
  const startTime = businessDashboard.value.date_range?.start_time || queryParams.value.start_time || "今日";
  const endTime = businessDashboard.value.date_range?.end_time || queryParams.value.end_time || "";

  if (startTime && endTime && startTime !== endTime) {
    return `${startTime} 至 ${endTime}`;
  }

  return startTime || "今日";
});

const fetchBusinessDashboard = async () => {
  businessLoading.value = true;
  try {
    const res = await getRecycleDashboardOverview(queryParams.value);
    businessDashboard.value = {
      date_range: res.data?.date_range || {},
      thresholds: res.data?.thresholds || {},
      cards: Array.isArray(res.data?.cards) ? res.data.cards : [],
      todo: Array.isArray(res.data?.todo) ? res.data.todo : [],
      ledger: res.data?.ledger || undefined,
      finance_summary: res.data?.finance_summary || undefined,
      today_business: res.data?.today_business || undefined,
      responsibility: res.data?.responsibility || undefined,
      explain: res.data?.explain || "",
    };
  } catch (error) {
    console.error("获取经营看板失败", error);
    businessDashboard.value = {
      date_range: {},
      thresholds: {},
      cards: [],
      todo: [],
      ledger: undefined,
      finance_summary: undefined,
      today_business: undefined,
      responsibility: undefined,
      explain: "经营看板暂时无法加载，请稍后重试。",
    };
  } finally {
    businessLoading.value = false;
  }
};

const fetchBusinessTrend = async () => {
  businessTrendLoading.value = true;
  try {
    const res = await getRecycleDashboardTrend(queryParams.value);
    businessTrend.value = {
      date_range: res.data?.date_range || {},
      x_axis: Array.isArray(res.data?.x_axis) ? res.data.x_axis : [],
      series: Array.isArray(res.data?.series) ? res.data.series : [],
      explain: res.data?.explain || "",
    };
  } catch (error) {
    console.error("获取经营趋势失败", error);
    businessTrend.value = {
      date_range: {},
      x_axis: [],
      series: [],
      explain: "经营趋势暂时无法加载，请稍后重试。",
    };
  } finally {
    businessTrendLoading.value = false;
  }
};

const handleRefresh = async () => {
  await Promise.all([fetchData(activeDashboard.value as "business" | "finance" | "user"), fetchBusinessDashboard(), fetchBusinessTrend()]);
};

const handleMetricDrilldown = (card: DashboardMetricCard) => {
  const drilldown = card.drilldown;
  if (!drilldown?.filter_key) return;

  router.push({
    path: "/recycle_order/list",
    query: {
      filter_key: drilldown.filter_key,
      view_mode: drilldown.view_mode || "",
      start_time: queryParams.value.start_time || "",
      end_time: queryParams.value.end_time || "",
      dashboard_title: card.title,
      t: String(Date.now()),
    },
  });
};

const handleLedgerDrilldown = (item: {
  label: string;
  filterKey?: string;
  viewMode?: string;
}) => {
  if (!item.filterKey) return;

  router.push({
    path: "/recycle_order/list",
    query: {
      filter_key: item.filterKey,
      view_mode: item.viewMode || "",
      start_time: queryParams.value.start_time || "",
      end_time: queryParams.value.end_time || "",
      dashboard_title: item.label,
      t: String(Date.now()),
    },
  });
};

const handleTaskDrilldown = (task: NonNullable<BusinessDashboard["responsibility"]>["tasks"][number]) => {
  if (task.route_path) {
    router.push({
      path: task.route_path,
      query: {
        ...(task.route_query || {}),
        start_time: queryParams.value.start_time || "",
        end_time: queryParams.value.end_time || "",
        dashboard_title: task.title,
        t: String(Date.now()),
      },
    });
    return;
  }

  const drilldown = task.drilldown;
  if (!drilldown?.filter_key) return;

  router.push({
    path: "/recycle_order/list",
    query: {
      filter_key: drilldown.filter_key,
      view_mode: drilldown.view_mode || "",
      start_time: queryParams.value.start_time || "",
      end_time: queryParams.value.end_time || "",
      dashboard_title: task.title,
      t: String(Date.now()),
    },
  });
};

const goConsignmentStatus = (status: string | number) => {
  router.push({
    path: "/site/consignment_order/list",
    query: {
      status,
      start_time: queryParams.value.start_time || "",
      end_time: queryParams.value.end_time || "",
      t: String(Date.now()),
    },
  });
};

const handleExpressQuickCommand = (command: "ship" | "track") => {
  if (command === "ship") {
    router.push({
      path: "/express/order_record",
      query: {
        quick_action: "create",
        t: String(Date.now()),
      },
    });
    return;
  }

  router.push({
    path: "/express/order_record",
    query: {
      quick_action: "search",
      t: String(Date.now()),
    },
  });
};

// 包装 hooks 方法
const handleQuickPeriod = (period: string) => {
  handleQuickPeriodBase(period, (start, end) => {
    queryParams.value.start_time = start;
    queryParams.value.end_time = end;
    handleRefresh();
  });
};

const handleDateChange = (dates: string[]) => {
  handleDateChangeBase(dates, (start, end) => {
    queryParams.value.start_time = start;
    queryParams.value.end_time = end;
    handleRefresh();
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

watch(
  () => businessTrend.value,
  (newData) => {
    nextTick(() => {
      if (!financeTrendChart.value) return;
      initFinanceTrendChart();
      updateFinanceTrendChart(newData || {});
    });
  },
  { deep: true, immediate: true }
);

watch(
  dashboardTabs,
  (tabs) => {
    if (!tabs.length) return;
    if (!tabs.some((tab) => tab.key === activeDashboard.value)) {
      activeDashboard.value = tabs[0].key;
    }
  },
  { immediate: true }
);

watch(
  activeDashboard,
  () => {
    fetchData(activeDashboard.value as "business" | "finance" | "user");
    nextTick(() => {
      if (financeTrendChart.value) {
        initFinanceTrendChart();
        updateFinanceTrendChart(businessTrend.value || {});
      }
    });
  }
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
  handleRefresh();
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
