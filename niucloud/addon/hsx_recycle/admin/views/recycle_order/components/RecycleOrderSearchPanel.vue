<template>
  <HsxSearchPanel v-model="collapsed" title="筛选条件" collapsible :summary="activeCount ? '已设置 ' + activeCount + ' 项条件，折叠后仍生效' : '按订单、客户或设备查找'">
      <template #extra>
          <el-button type="primary" :icon="Search" @click="emit('advanced-search')">查询</el-button>
          <el-button :icon="Refresh" @click="emit('reset-search')">重置</el-button>
      </template>
      <el-form :inline="true" :model="props.advancedSearchForm" class="search-form" @submit.prevent>
          <el-form-item label="快递单号" class="search-item search-item--code">
              <el-input
                  v-model="props.advancedSearchForm.express_no"
                  placeholder="输入快递单号"
                  clearable
                  class="w-full"
              />
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
          <el-form-item label="设备IMEI" class="search-item search-item--imei">
              <el-input
                  v-model="props.advancedSearchForm.device_imei"
                  placeholder="输入设备IMEI号"
                  clearable
                  class="w-full"
              />
          </el-form-item>
          <HsxFold class="w-full" title="更多筛选" :summary="advancedActiveCount ? '已设置 ' + advancedActiveCount + ' 项条件' : '订单状态、时间、数量与金额'">
              <div class="search-advanced-grid">
                  <el-form-item  label="订单编号" class="search-item search-item--code">
                      <el-input
                          v-model="props.advancedSearchForm.order_no"
                          placeholder="输入精确订单号"
                          clearable
                          class="w-full"
                      />
                  </el-form-item>
                  <el-form-item  label="订单状态" class="search-item search-item--select">
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
                  <el-form-item  label="配送方式" class="search-item search-item--select">
                      <el-select
                          v-model="props.advancedSearchForm.delivery_type"
                          placeholder="选择配送方式"
                          clearable
                          multiple
                          class="w-full"
                      >
                          <el-option label="快递配送" value="1" />
                          <el-option label="自送到店" value="2" />
                          <el-option label="物流车配送" value="3" />
                      </el-select>
                  </el-form-item>
                  <el-form-item  label="物流车牌" class="search-item search-item--code">
                      <el-input v-model="props.advancedSearchForm.logistics_vehicle_no" placeholder="输入车牌号" clearable class="w-full" />
                  </el-form-item>
                  <el-form-item  label="下单来源" class="search-item search-item--select">
                      <el-select
                          v-model="props.advancedSearchForm.order_source"
                          placeholder="选择来源"
                          clearable
                          class="w-full"
                      >
                          <el-option label="客户下单" value="customer" />
                          <el-option label="代下单" value="agent" />
                      </el-select>
                  </el-form-item>
                  <el-form-item  label="设备型号" class="search-item search-item--model">
                      <el-input
                          v-model="props.advancedSearchForm.device_model"
                          placeholder="输入设备型号"
                          clearable
                          class="w-full"
                      />
                  </el-form-item>
                  <el-form-item  label="提交数量" class="search-item search-item--range">
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
                  <el-form-item  label="订单金额" class="search-item search-item--range">
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
                  <el-form-item  label="创建时间" class="search-item search-item--date">
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
                  <el-form-item  label="更新时间" class="search-item search-item--date">
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
                  <el-form-item  label="签收时间" class="search-item search-item--date">
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
                  <el-form-item  label="质检时间" class="search-item search-item--date">
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
                  <el-form-item  label="打款时间" class="search-item search-item--date">
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
              </div>
          </HsxFold>
      </el-form>
  </HsxSearchPanel>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { HsxSearchPanel, HsxFold } from '@/addon/hsx_components/core'
import { Search, Refresh } from '@element-plus/icons-vue'
import MemberSelect from '@/addon/hsx_recycle/components/member-select/index.vue'
interface Props { isMobile: boolean; mobileSearchVisible: boolean; advancedSearchForm: Record<string, any>; orderStatusMap: Record<string, any> }
const props = defineProps<Props>()
const emit = defineEmits(['toggle-mobile-search', 'advanced-search', 'reset-search', 'member-change'])
const panelCollapsed = ref(false)
const collapsed = computed({
  get: () => props.isMobile ? !props.mobileSearchVisible : panelCollapsed.value,
  set: value => {
    if (props.isMobile) { if (value !== !props.mobileSearchVisible) emit('toggle-mobile-search') }
    else panelCollapsed.value = value
  }
})
const basicKeys = new Set(['express_no', 'member_id', 'user_mobile', 'device_imei'])
const activeKeys = computed(() => Object.keys(props.advancedSearchForm).filter(key => {
  const value = props.advancedSearchForm[key]
  return Array.isArray(value) ? value.length > 0 : value !== '' && value !== null && value !== undefined
}))
const activeCount = computed(() => activeKeys.value.length)
const advancedActiveCount = computed(() => activeKeys.value.filter(key => !basicKeys.has(key)).length)
const handleMemberChange = (...args: any[]) => emit('member-change', ...args)
</script>

<style scoped>
.search-form, .search-advanced-grid { display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px; }
.search-form .search-item { flex: 1 1 220px; min-width: 0; margin: 0; }
.search-advanced-grid :deep(.el-form-item) { display: flex; flex-direction: column; align-items: stretch; }
.search-advanced-grid :deep(.el-form-item__label) { width: auto !important; justify-content: flex-start; padding-bottom: 4px; height: auto; line-height: 20px; font-size: 12px; color: var(--hsx-text-secondary); }
.range-inline { display: flex; align-items: center; gap: 8px; width: 100%; }
.range-input { min-width: 0; flex: 1; }
</style>
