<template>
  <div class="order-search-panel">
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
        <div class="search-head">
          <el-segmented v-model="filterMode" :options="filterModeOptions" />
          <div class="search-head__hint">
            {{ filterMode === 'basic' ? '常用检索：适合快速定位订单' : '高级检索：展开全部筛选条件' }}
          </div>
        </div>

        <el-form
          :inline="!props.isMobile"
          :model="props.advancedSearchForm"
          :class="['search-form', filterMode === 'advanced' ? 'search-form--advanced' : 'search-form--basic']"
        >
          <el-form-item v-if="filterMode === 'advanced'" label="订单编号" class="search-item search-item--code">
            <el-input
              v-model="props.advancedSearchForm.order_no"
              placeholder="输入精确订单号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="快递单号" class="search-item search-item--code">
            <el-input
              v-model="props.advancedSearchForm.express_no"
              placeholder="输入快递单号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="订单状态" class="search-item search-item--select">
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

          <el-form-item label="用户搜索" class="search-item search-item--member">
            <member-select
              v-model="props.advancedSearchForm.member_id"
              placeholder="输入用户昵称、手机号或用户编号"
              @change="handleMemberChange"
              class="w-full"
            />
          </el-form-item>

          <el-form-item label="用户手机号" class="search-item search-item--mobile">
            <el-input
              v-model="props.advancedSearchForm.user_mobile"
              placeholder="输入用户手机号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="配送方式" class="search-item search-item--select">
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

          <el-form-item label="设备IMEI" class="search-item search-item--imei">
            <el-input
              v-model="props.advancedSearchForm.device_imei"
              placeholder="输入设备IMEI号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="设备型号" class="search-item search-item--model">
            <el-input
              v-model="props.advancedSearchForm.device_model"
              placeholder="输入设备型号"
              clearable
              class="w-full"
            />
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="提交数量" class="search-item search-item--range">
            <div class="range-inline">
              <el-input-number
                v-model="props.advancedSearchForm.device_count_min"
                :min="0"
                :controls="false"
                placeholder="最少"
                class="range-input"
              />
              <span>至</span>
              <el-input-number
                v-model="props.advancedSearchForm.device_count_max"
                :min="0"
                :controls="false"
                placeholder="最多"
                class="range-input"
              />
            </div>
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="订单金额" class="search-item search-item--range">
            <div class="range-inline">
              <el-input-number
                v-model="props.advancedSearchForm.amount_min"
                :min="0"
                :precision="2"
                :controls="false"
                placeholder="最低"
                class="range-input"
              />
              <span>至</span>
              <el-input-number
                v-model="props.advancedSearchForm.amount_max"
                :min="0"
                :precision="2"
                :controls="false"
                placeholder="最高"
                class="range-input"
              />
            </div>
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="创建时间" class="search-item search-item--date">
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

          <el-form-item v-if="filterMode === 'advanced'" label="更新时间" class="search-item search-item--date">
            <el-date-picker
              v-model="props.advancedSearchForm.update_time_range"
              type="daterange"
              range-separator="至"
              start-placeholder="开始日期"
              end-placeholder="结束日期"
              format="YYYY-MM-DD"
              value-format="YYYY-MM-DD"
              class="w-full"
            />
          </el-form-item>

          <el-form-item v-if="filterMode === 'advanced'" label="签收时间" class="search-item search-item--date">
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

          <el-form-item v-if="filterMode === 'advanced'" label="质检时间" class="search-item search-item--date">
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

          <el-form-item v-if="filterMode === 'advanced'" label="打款时间" class="search-item search-item--date">
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

          <div class="search-actions">
            <div :class="props.isMobile ? 'grid grid-cols-1 gap-2' : 'flex gap-2'">
              <el-button
                type="primary"
                :icon="Search"
                @click="emit('advanced-search')"
                :class="props.isMobile ? 'w-full' : ''"
              >
                {{ filterMode === 'basic' ? '搜索' : '执行高级搜索' }}
              </el-button>
              <el-button
                :icon="Refresh"
                @click="emit('reset-search')"
                :class="props.isMobile ? 'w-full' : ''"
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
import { ref, watch } from "vue";
import { Search, Refresh } from "@element-plus/icons-vue";
import MemberSelect from "@/addon/recycle/components/member-select/index.vue";

interface Props {
  isMobile: boolean;
  mobileSearchVisible: boolean;
  advancedSearchForm: Record<string, any>;
  orderStatusMap: Record<string, any>;
}

const props = defineProps<Props>();
const filterMode = ref<"basic" | "advanced">("basic");
const filterModeOptions = [
  { label: "基础筛选", value: "basic" },
  { label: "高级筛选", value: "advanced" },
];

const clearAdvancedOnlyFields = () => {
  props.advancedSearchForm.order_no = "";
  props.advancedSearchForm.status = [];
  props.advancedSearchForm.delivery_type = [];
  props.advancedSearchForm.device_model = "";
  props.advancedSearchForm.device_count_min = null;
  props.advancedSearchForm.device_count_max = null;
  props.advancedSearchForm.amount_min = null;
  props.advancedSearchForm.amount_max = null;
  props.advancedSearchForm.create_time_range = [];
  props.advancedSearchForm.update_time_range = [];
  props.advancedSearchForm.sign_at = [];
  props.advancedSearchForm.complete_at = [];
  props.advancedSearchForm.pay_time = [];
};

watch(filterMode, (mode) => {
  if (mode === "basic") {
    clearAdvancedOnlyFields();
  }
});

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
.order-search-panel {
  margin-bottom: 10px;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  background: #fff;
}

.search-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.search-head__hint {
  color: #6b7280;
  font-size: 12px;
}

.search-form {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-end;
  column-gap: 8px;
  row-gap: 8px;
}

.search-form .search-item {
  flex: 0 1 230px;
  width: auto;
  min-width: 210px;
  margin-right: 0;
  margin-bottom: 0;
}

.search-form .search-item--code {
  flex-basis: 235px;
}

.search-form .search-item--mobile {
  flex-basis: 220px;
}

.search-form .search-item--imei {
  flex-basis: 240px;
}

.search-form .search-item--model {
  flex-basis: 230px;
}

.search-form .search-item--select {
  flex-basis: 220px;
}

.search-form .search-item--member {
  flex: 1 1 250px;
  max-width: 360px;
}

.search-form .search-item--range {
  flex-basis: 250px;
}

.search-form .search-item--date {
  flex-basis: 300px;
}

.search-form--advanced {
  column-gap: 12px;
  row-gap: 10px;
}

.search-form--advanced .search-item {
  flex-basis: 280px;
  min-width: 250px;
}

.search-form--advanced .search-item--code {
  flex-basis: 280px;
}

.search-form--advanced .search-item--mobile {
  flex-basis: 250px;
}

.search-form--advanced .search-item--imei {
  flex-basis: 285px;
}

.search-form--advanced .search-item--model {
  flex-basis: 280px;
}

.search-form--advanced .search-item--select {
  flex-basis: 260px;
}

.search-form--advanced .search-item--member {
  flex: 1 1 320px;
  max-width: 460px;
}

.search-form--advanced .search-item--range {
  flex-basis: 310px;
}

.search-form--advanced .search-item--date {
  flex-basis: 360px;
}

.search-form .search-item :deep(.el-form-item__content) {
  width: 100%;
}

.search-form .search-item :deep(.el-date-editor) {
  width: 100% !important;
}

.range-inline {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
}

.range-inline span {
  color: #9ca3af;
  font-size: 12px;
}

.range-input {
  flex: 1;
  min-width: 0;
}

.range-input :deep(.el-input__inner) {
  text-align: left;
}

.search-actions {
  flex: 0 0 auto;
  padding-bottom: 0;
  margin-bottom: 0;
}

.search-actions .el-button {
  margin-left: 0;
}

/* 移动端：一列 */
@media (max-width: 768px) {
  .search-head {
    align-items: flex-start;
    flex-direction: column;
  }

  .search-head__hint {
    display: none;
  }

  .search-form .search-item {
    flex-basis: auto;
    width: 100%;
    min-width: unset;
    margin-right: 0;
  }

  .search-actions {
    width: 100%;
    padding-top: 12px;
    border-top: 1px solid #eef2f7;
  }
}
</style>
