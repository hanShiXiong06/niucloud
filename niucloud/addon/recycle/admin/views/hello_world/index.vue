<template>
  <div class="p-[15px]">
      <el-card header="质检员工作量统计" class="mb-[15px]">
          <div>
              <el-table :data="staffCountData" v-loading="staffLoading">
                  <el-table-column prop="user_name" label="质检员名称" />
                  <el-table-column prop="total_processed_devices" label="已质检设备数" />
                  <el-table-column prop="successfully_recycled_devices" label="成功回收设备数" />
              </el-table>
          </div>
      </el-card>

      <el-card header="估价员工作量统计">
          <div>
              <el-table :data="priceConfirmerData" v-loading="priceConfirmerLoading">
                  <el-table-column prop="user_name" label="估价员名称" />
                  <el-table-column prop="total_processed_devices" label="已处理设备数" />
                  <el-table-column prop="successfully_recycled_devices" label="成功回收设备数" />
              </el-table>
          </div>
      </el-card>
  </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { getStaffCount, getPriceConfirmerPerformance } from '@/addon/recycle/api/hello_world' // 路径确认

const staffCountData = ref<any[]>([])
const staffLoading = ref(false)
const priceConfirmerData = ref<any[]>([])
const priceConfirmerLoading = ref(false)

const fetchStaffCount = async () => {
  staffLoading.value = true
  try {
      const res = await getStaffCount()
      staffCountData.value = res.data && Array.isArray(res.data.user_stats) ? res.data.user_stats : []
  } catch (e) {
      console.error("获取质检员统计失败", e)
      staffCountData.value = [] // 出错时清空或给默认值
  } finally {
      staffLoading.value = false
  }
}

const fetchPriceConfirmerPerformance = async () => {
  priceConfirmerLoading.value = true
  try {
      const res = await getPriceConfirmerPerformance()
      priceConfirmerData.value = res.data && Array.isArray(res.data.user_stats) ? res.data.user_stats : []
  } catch (e) {
      console.error("获取估价员统计失败", e)
      priceConfirmerData.value = [] // 出错时清空或给默认值
  } finally {
      priceConfirmerLoading.value = false
  }
}

onMounted(() => {
  fetchStaffCount()
  fetchPriceConfirmerPerformance()
})
</script>

<style lang="scss" scoped>
/* 如果需要，可以在这里添加一些简单的样式 */
</style> 