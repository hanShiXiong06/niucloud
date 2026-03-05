<template>
  <div
    :class="[
      'mb-3 bg-white/95 backdrop-blur-sm shadow-lg rounded-lg p-2 -mx-2',
      props.isMobile ? '' : 'sticky top-30 z-50',
    ]"
  >
    <div v-if="props.isMobile" class="flex items-center justify-between gap-2 px-2 pt-1">
      <el-button type="primary" plain :icon="Search" @click="emit('toggle-mobile-search')">
        {{ props.mobileSearchVisible ? "收起筛选" : "展开筛选" }}
      </el-button>
      <el-button text type="primary" :icon="Refresh" @click="emit('reset-search')">
        快速重置
      </el-button>
    </div>

    <el-collapse-transition>
      <div v-show="!props.isMobile || props.mobileSearchVisible" class="p-2">
        <el-form :inline="!props.isMobile" :model="props.advancedSearchForm" class="search-form">
          <el-form-item label="订单编号" class="search-item">
            <el-input
              v-model="props.advancedSearchForm.order_no"
              placeholder="输入精确订单号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="快递单号" class="search-item">
            <el-input
              v-model="props.advancedSearchForm.express_no"
              placeholder="输入快递单号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="订单状态" class="search-item">
            <el-select
              v-model="props.advancedSearchForm.status"
              placeholder="选择状态"
              clearable
              multiple
              collapse-tags
              class="w-full"
            >
              <el-option
                v-for="(status, key) in props.orderStatusMap"
                :key="key"
                :label="status.name"
                :value="status.status"
              />
            </el-select>
          </el-form-item>

          <el-form-item label="用户搜索" class="search-item">
            <member-select
              v-model="props.advancedSearchForm.member_id"
              placeholder="输入用户昵称、手机号或用户编号"
              @change="handleMemberChange"
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="用户手机号" class="search-item">
            <el-input
              v-model="props.advancedSearchForm.user_mobile"
              placeholder="输入用户手机号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="配送方式" class="search-item">
            <el-select
              v-model="props.advancedSearchForm.delivery_type"
              placeholder="选择配送方式"
              clearable
              multiple
              class="w-full"
            >
              <el-option label="快递配送" value="1" />
              <el-option label="自送到店" value="2" />
            </el-select>
          </el-form-item>

          <el-form-item label="设备IMEI" class="search-item">
            <el-input
              v-model="props.advancedSearchForm.device_imei"
              placeholder="输入设备IMEI号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="设备型号" class="search-item">
            <el-input
              v-model="props.advancedSearchForm.device_model"
              placeholder="输入设备型号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="创建时间" class="search-item">
            <el-date-picker
              v-model="props.advancedSearchForm.create_time_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="签收时间" class="search-item">
            <el-date-picker
              v-model="props.advancedSearchForm.sign_at"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="质检时间" class="search-item">
            <el-date-picker
              v-model="props.advancedSearchForm.complete_at"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="打款时间" class="search-item">
            <el-date-picker
              v-model="props.advancedSearchForm.pay_time"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              class="w-full"
            />
          </el-form-item>

          <div class="pt-4 border-t border-orange-200 w-full">
            <div :class="props.isMobile ? 'grid grid-cols-1 gap-2' : 'flex justify-center gap-3'">
              <el-button
                type="primary"
                :icon="Search"
                @click="emit('advanced-search')"
                :class="
                  props.isMobile
                    ? 'w-full bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 border-0 shadow-sm'
                    : 'bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 border-0 shadow-sm px-6'
                "
              >
                执行高级搜索
              </el-button>
              <el-button
                :icon="Refresh"
                @click="emit('reset-search')"
                :class="
                  props.isMobile
                    ? 'w-full border-gray-300 text-gray-600 hover:border-gray-400'
                    : 'border-gray-300 text-gray-600 hover:border-gray-400 px-6'
                "
              >
                重置所有条件
              </el-button>
            </div>
          </div>
        </el-form>
      </div>
    </el-collapse-transition>
  </div>
</template>

<script setup lang="ts">
import { Search, Refresh } from "@element-plus/icons-vue";
import MemberSelect from "@/addon/recycle/components/member-select/index.vue";

interface Props {
  isMobile: boolean;
  mobileSearchVisible: boolean;
  advancedSearchForm: Record<string, any>;
  orderStatusMap: Record<string, any>;
}

const props = defineProps<Props>();

const emit = defineEmits([
  "toggle-mobile-search",
  "advanced-search",
  "reset-search",
  "member-change",
]);

const handleMemberChange = (...args: any[]) => {
  emit("member-change", ...args);
};
</script>

<style scoped>
/* 桌面端：搜索表单使用 grid 布局，一行 3 个 */
.search-form {
  display: flex;
  flex-wrap: wrap;
  gap: 0;
}

.search-form .search-item {
  width: calc(33.333% - 10px);
  min-width: 240px;
  margin-right: 10px;
  margin-bottom: 12px;
}

.search-form .search-item :deep(.el-form-item__content) {
  width: 100%;
}

.search-form .search-item :deep(.el-date-editor) {
  width: 100% !important;
}

/* 移动端：一列 */
@media (max-width: 768px) {
  .search-form .search-item {
    width: 100%;
    min-width: unset;
    margin-right: 0;
  }
}
</style>
