<template>
  <div class="printer-list-wrap">
    <el-card class="box-card" v-loading="loading">
      <template #header>
        <div class="card-header">
          <span>打印机管理</span>
          <el-button type="primary" @click="handleAdd">添加打印机</el-button>
        </div>
      </template>

      <el-empty v-if="printerList.length === 0 && !loading" description="暂无打印机">
        <el-button type="primary" @click="handleAdd">添加打印机</el-button>
      </el-empty>
    
      <el-table v-else :data="printerList" style="width: 100%" border>
        <el-table-column prop="printer_name" label="打印机名称" min-width="120">
          <template #default="{ row }">
            <div class="printer-name">
              <span>{{ row.printer_name }}</span>
              <el-tag v-if="row.is_default" type="success" size="small" style="margin-left: 8px;">当前使用</el-tag>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="brand_name" label="品牌" width="100" />
        
        <el-table-column prop="type_name" label="类型" width="100">
          <template #default="{ row }">
            {{ row.type_name || '标签打印机' }}
          </template>
        </el-table-column>
        
        <el-table-column prop="sn" label="序列号" min-width="150" />
        
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="handleStatusChange(row)"
              :loading="row.statusLoading"
            />
          </template>
        </el-table-column>
        
        <el-table-column prop="create_time" label="添加时间" width="160" />
        
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button type="primary" size="small" @click="handleTest(row)">测试</el-button>
            <el-button type="warning" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>
    
    <!-- 测试打印对话框 -->
    <el-dialog v-model="testDialogVisible" title="测试打印" width="500px">
      <el-form :model="testForm" label-width="100px">
        <el-form-item label="打印内容">
          <el-input v-model="testForm.content" type="textarea" :rows="3" placeholder="请输入测试打印内容"></el-input>
        </el-form-item>
      </el-form>
      <template #footer>
        <span class="dialog-footer">
          <el-button @click="testDialogVisible = false">取 消</el-button>
          <el-button type="primary" @click="submitTest" :loading="testLoading">测试打印</el-button>
        </span>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';
import { 
  getUserPrinterList, 
  deleteUserPrinter, 
  togglePrinterStatus, 
  testPrinter 
} from '@/addon/recycle/api/printer';

const router = useRouter();
const printerList = ref([]);
const loading = ref(false);
const testLoading = ref(false);
const testDialogVisible = ref(false);
const currentTestPrinter = ref(null);
const testForm = ref({
  content: `<PAGE>
                    <SIZE>40,30</SIZE>
                    <TEXT x="8" y="8" w="1" h="1" r="0">
                    这是由 芯烨云 打印的标签 后台默认（测试标签），
                    </TEXT>
                    </PAGE`
});

// 获取用户的所有打印机列表
const fetchPrinterList = async () => {
  try {
    loading.value = true;
    const res = await getUserPrinterList();
  
    if (res.code === 1 && res.data && res.data.data) {
      printerList.value = res.data.data.map(item => ({
        ...item,
        statusLoading: false
      }));
    }
  } catch (error) {
    console.error('获取打印机列表失败', error);
  } finally {
    loading.value = false;
  }
};

// 添加打印机
const handleAdd = () => {
  router.push('/recycle/printer/add');
};

// 编辑打印机
const handleEdit = (row) => {
  router.push(`/recycle/printer/edit/${row.printer_id}`);
};

// 删除打印机
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除打印机"${row.printer_name}"吗？删除后无法恢复。`, 
    '删除确认', 
    {
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      loading.value = true;
      const res = await deleteUserPrinter(row.printer_id);
      
      if (res.code === 1) {
        await fetchPrinterList();
      }
    } catch (error) {
      console.error('删除打印机失败', error);
    } finally {
      loading.value = false;
    }
  }).catch(() => {});
};

// 状态切换
const handleStatusChange = async (row) => {
  try {
    row.statusLoading = true;
    const res = await togglePrinterStatus(row.printer_id, row.status);
    
    if (res.code === 1) {

      // 如果激活了这个打印机，需要刷新列表以更新is_default状态
      if (row.status) {
        await fetchPrinterList();
      }
    } else {
      // 如果失败，恢复原状态
      row.status = row.status ? 0 : 1;
    }
  } catch (error) {
    console.error('切换打印机状态失败', error);
    // 恢复原状态
    row.status = row.status ? 0 : 1;
  } finally {
    row.statusLoading = false;
  }
};

// 测试打印
const handleTest = (row) => {
  if (!row.status) {
    ElMessage.warning('请先激活打印机');
    return;
  }
  
  currentTestPrinter.value = row;
  testDialogVisible.value = true;
};

// 提交测试打印
const submitTest = async () => {
  if (!currentTestPrinter.value) {
    ElMessage.warning('请选择要测试的打印机');
    return;
  }
  
  try {
    testLoading.value = true;
    const res = await testPrinter({
      sn: currentTestPrinter.value.sn,
      user_name: currentTestPrinter.value.user_name,
      user_key: currentTestPrinter.value.user_key,
      content: testForm.value.content
    });
    
    if (res.code === 1) {
      testDialogVisible.value = false;
    }
  } catch (error) {
    console.error('测试打印失败', error);
  } finally {
    testLoading.value = false;
  }
};

onMounted(() => {
  fetchPrinterList();
});
</script>

<style lang="scss" scoped>
.printer-list-wrap {
  padding: 16px;
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .printer-name {
    display: flex;
    align-items: center;
  }
}
</style> 