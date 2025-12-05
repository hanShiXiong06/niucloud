<template>
  <div class="site-agent-config">
    <!-- 添加代理表单 -->
    <el-card class="box-card ">
      <template #header>
        <div class="card-header">
          <span>添加代理关系</span>
        </div>
      </template>
      
      <el-form :model="form" label-width="120px">
        <el-form-item label="选择代理站点">
          <el-select v-model="form.agent_site_id" placeholder="请选择要代理的站点">
            <el-option
              v-for="item in availableSites"
              :key="item.site_id"
              :label="item.site_name"
              :value="item.site_id"
            />
          </el-select>
        </el-form-item>
        <el-form-item>
          <el-button type="primary" @click="handleAdd">添加代理</el-button>
        </el-form-item>
      </el-form>
    </el-card>

    <!-- 代理列表 -->
    <el-card class="box-card">
      <template #header>
        <div class="card-header">
          <span>代理关系列表</span>
        </div>
      </template>

      <el-table :data="tableData" style="width: 100%">
        <el-table-column prop="id" label="ID" width="80" />
        <el-table-column prop="site_name" label="站点名称"/>
        <el-table-column prop="status" label="状态" width="120">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="create_time" label="创建时间" width="180" />
        <el-table-column label="操作" width="120">
          <template #default="{ row }">
            <el-button
              type="danger"
              link
              @click="handleDelete(row.id)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="mt-4 text-right">
        <el-pagination
          v-model:currentPage="page"
          v-model:pageSize="limit"
          :total="total"
          :page-sizes="[10, 20, 30, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </el-card>
  </div>
</template>

<script lang="ts" setup>
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  getSiteAgentList,
  getAvailableSites,
  addSiteAgent,
  modifySiteAgentStatus,
  deleteSiteAgent,
  type ISiteAgent,
  type ISite
} from '../../api/site'

// 表单数据
const form = ref({
  agent_site_id: undefined as number | undefined
})

// 可选站点列表
const availableSites = ref<ISite[]>([])

// 表格数据
const tableData = ref<ISiteAgent[]>([])
const page = ref(1)
const limit = ref(20)
const total = ref(0)

// 获取可代理站点列表
const loadAvailableSites = async () => {
  try {
    const res = await getAvailableSites()
    if (res.code === 1) {
      availableSites.value = res.data || []
    }
  } catch (error) {
    console.error('获取可代理站点失败:', error)
  }
}

// 获取代理列表
const loadTableData = async () => {
  try {
    const res = await getSiteAgentList({
      page: page.value,
      limit: limit.value
    })
    if (res.code === 1) {
      tableData.value = res.data.data || []
      total.value = res.data.total || 0
    }
  } catch (error) {
    console.error('获取代理列表失败:', error)
  }
}

// 添加代理
const handleAdd = async () => {
  if (!form.value.agent_site_id) {
    ElMessage.warning('请选择要代理的站点')
    return
  }

  try {
    const res = await addSiteAgent({
      agent_site_id: form.value.agent_site_id
    })
    if (res.code === 1) {
    
      form.value.agent_site_id = undefined
      loadTableData()
      loadAvailableSites()
    }
  } catch (error) {
    console.error('添加代理失败:', error)
  }
}

// 修改状态
const handleStatusChange = async (row: ISiteAgent) => {
  try {
    const res = await modifySiteAgentStatus({
      id: row.id,
      status: row.status
    })
   
  } catch (error) {
    console.error('修改状态失败:', error)
    // 恢复状态
    row.status = row.status === 1 ? 0 : 1
  }
}

// 删除代理
const handleDelete = async (id: number) => {
  try {
    await ElMessageBox.confirm('确定要删除该代理关系吗?', '提示', {
      type: 'warning'
    })
    
    const res = await deleteSiteAgent(id)
    if (res.code === 1) {
     
      loadTableData()
      loadAvailableSites()
    }
  } catch (error) {
    if (error !== 'cancel') {
      console.error('删除失败:', error)
    }
  }
}

// 分页大小改变
const handleSizeChange = (val: number) => {
  limit.value = val
  loadTableData()
}

// 页码改变
const handlePageChange = (val: number) => {
  page.value = val
  loadTableData()
}

// 初始化
onMounted(() => {
  loadAvailableSites()
  loadTableData()
})
</script>

<style scoped>
.site-agent-config {
 margin-bottom: 20px;
}
.mb-4 {
  margin-bottom: 1rem;
}
.mt-4 {
  margin-top: 1rem;
}
.text-right {
  text-align: right;
}
</style>
