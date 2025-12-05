<template>
  <div class="ai-design-container">
    <div class="header">
      <h2>AI设计管理</h2>
      <el-button type="primary" @click="handleCreate">创建新设计</el-button>
    </div>
    
    <div class="content">
      <el-table :data="designList" style="width: 100%">
        <el-table-column prop="name" label="设计名称" />
        <el-table-column prop="type" label="设计类型" />
        <el-table-column prop="status" label="状态" />
        <el-table-column prop="create_time" label="创建时间" />
        <el-table-column label="操作">
          <template #default>
            <el-button type="text">查看</el-button>
            <el-button type="text">编辑</el-button>
            <el-button type="text" style="color: red">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getDesignList } from '@/addon/ai_design/admin/api/design'

const designList = ref([])

const loadData = async () => {
  try {
    const res = await getDesignList({})
    designList.value = res.data.list || []
  } catch (error) {
    console.error('加载数据失败:', error)
  }
}

const handleCreate = () => {
  // TODO: 打开创建设计对话框
  console.log('创建新设计')
}

onMounted(() => {
  loadData()
})
</script>

<style scoped>
.ai-design-container {
  padding: 20px;
}

.header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.content {
  background: #fff;
  padding: 20px;
  border-radius: 4px;
}
</style>

