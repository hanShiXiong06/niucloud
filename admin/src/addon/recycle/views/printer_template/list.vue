<template>
  <div class="template-list-wrap">
    <el-card class="box-card" v-loading="loading">
      <template #header>
        <div class="card-header">
          <span>打印模板管理</span>
          <el-button type="primary" @click="handleAdd">添加模板</el-button>
        </div>
      </template>

      <!-- 搜索区域 -->
      <div class="search-area">
        <el-form :model="searchForm" inline>
          <el-form-item label="模板名称">
            <el-input 
              v-model="searchForm.template_name" 
              placeholder="请输入模板名称" 
              clearable
              style="width: 200px"
            />
          </el-form-item>
          <el-form-item label="模板类型">
            <el-select 
              v-model="searchForm.template_type" 
              placeholder="请选择模板类型" 
              clearable
              style="width: 150px"
            >
              <el-option 
                v-for="(name, key) in typeList"
                :key="key"
                :label="name"
                :value="key"
              />
            </el-select>
          </el-form-item>
          <el-form-item label="状态">
            <el-select 
              v-model="searchForm.status" 
              placeholder="请选择状态" 
              clearable
              style="width: 120px"
            >
              <el-option label="启用" :value="1" />
              <el-option label="禁用" :value="0" />
            </el-select>
          </el-form-item>
          <el-form-item>
            <el-button type="primary" @click="fetchTemplateList">搜索</el-button>
            <el-button @click="resetSearch">重置</el-button>
          </el-form-item>
        </el-form>
      </div>

      <!-- 表格区域 -->
      <el-table :data="templateList" style="width: 100%" border>
        <el-table-column prop="template_name" label="模板名称" min-width="150">
          <template #default="{ row }">
            <div class="template-name">
              <span>{{ row.template_name }}</span>
              <el-tag v-if="row.is_default" type="success" size="small" style="margin-left: 8px;">默认</el-tag>
            </div>
          </template>
        </el-table-column>
        
        <el-table-column prop="type_name" label="模板类型" width="120" />
        
        <el-table-column prop="width" label="宽度(mm)" width="80" />
        <el-table-column prop="height" label="高度(mm)" width="80" />
        
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
        
        <el-table-column prop="create_time" label="创建时间" width="160" />
        
        <el-table-column label="操作" width="280" fixed="right">
          <template #default="{ row }">

            <el-button type="success" size="small" @click="handleTest(row)">测试</el-button>
            <el-button type="warning" size="small" @click="handleEdit(row)">编辑</el-button>
            <el-button 
              v-if="!row.is_default" 
              type="info" 
              size="small" 
              @click="handleSetDefault(row)"
            >
              设为默认
            </el-button>
            <el-button type="danger" size="small" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <!-- 分页 -->
      <div class="pagination-wrap">
        <el-pagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.limit"
          :page-sizes="[10, 20, 50, 100]"
          :total="pagination.total"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="fetchTemplateList"
          @current-change="fetchTemplateList"
        />
      </div>
    </el-card>
    
    <!-- 预览对话框 -->
    <el-dialog v-model="previewVisible" title="模板预览" width="800px">
      <div class="preview-content" v-html="previewHtml"></div>
      <template #footer>
        <el-button @click="previewVisible = false">关闭</el-button>
      </template>
    </el-dialog>

    <!-- 测试打印对话框 -->
    <el-dialog v-model="testVisible" title="测试打印" width="600px">
      <el-form :model="testForm" label-width="100px">
        <el-form-item label="测试数据">
          <el-input 
            v-model="testForm.testData" 
            type="textarea" 
            :rows="8" 
            placeholder="请输入JSON格式的测试数据，如：{&quot;order_id&quot;: &quot;123456&quot;, &quot;imei&quot;: &quot;123456789012345&quot;}"
          />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="testVisible = false">取 消</el-button>
        <el-button type="primary" @click="submitTest" :loading="testLoading">测试打印</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';
import { 
  getTemplateList, 
  deleteTemplate, 
  modifyTemplateStatus, 
  setDefaultTemplate,
  previewTemplate,
  testPrintTemplate,
  getTemplateTypeList
} from '@/addon/recycle/api/printer_template';

const router = useRouter();
const loading = ref(false);
const testLoading = ref(false);
const previewVisible = ref(false);
const testVisible = ref(false);
const previewHtml = ref('');
const currentTestTemplate = ref(null);

const templateList = ref([]);
const typeList = ref({});

const searchForm = reactive({
  template_name: '',
  template_type: '',
  status: ''
});

const pagination = reactive({
  page: 1,
  limit: 20,
  total: 0
});

const testForm = reactive({
  testData: '{"order_id": "123456", "imei": "123456789012345", "brand": "iPhone", "model": "13 Pro", "color": "深空灰", "memory": "128GB"}'
});

// 获取模板列表
const fetchTemplateList = async () => {
  try {
    loading.value = true;
    const params = {
      ...searchForm,
      page: pagination.page,
      limit: pagination.limit
    };
    
    const res = await getTemplateList(params);
    
    if (res.code === 1 && res.data) {
      templateList.value = res.data.data.map(item => ({
        ...item,
        statusLoading: false
      }));
      pagination.total = res.data.total;
    }
  } catch (error) {
    console.error('获取模板列表失败', error);
  } finally {
    loading.value = false;
  }
};

// 获取模板类型列表
const fetchTypeList = async () => {
  try {
    const res = await getTemplateTypeList();
    if (res.code === 1) {
      typeList.value = res.data;
    }
  } catch (error) {
    console.error('获取模板类型失败', error);
  }
};

// 重置搜索
const resetSearch = () => {
  searchForm.template_name = '';
  searchForm.template_type = '';
  searchForm.status = '';
  pagination.page = 1;
  fetchTemplateList();
};

// 添加模板
const handleAdd = () => {
  router.push('/recycle/printer_template/add');
};

// 编辑模板
const handleEdit = (row) => {
  router.push(`/recycle/printer_template/edit/${row.template_id}`);
};

// 删除模板
const handleDelete = (row) => {
  ElMessageBox.confirm(
    `确定要删除模板"${row.template_name}"吗？删除后无法恢复。`, 
    '删除确认', 
    {
      confirmButtonText: '确定删除',
      cancelButtonText: '取消',
      type: 'warning'
    }
  ).then(async () => {
    try {
      await deleteTemplate(row.template_id);
      await fetchTemplateList();
    } catch (error) {
      console.error('删除模板失败', error);
    }
  }).catch(() => {});
};

// 状态切换
const handleStatusChange = async (row) => {
  try {
    row.statusLoading = true;
    await modifyTemplateStatus(row.template_id, row.status);
  } catch (error) {
    console.error('切换模板状态失败', error);
    row.status = row.status ? 0 : 1;
  } finally {
    row.statusLoading = false;
  }
};

// 设置默认模板
const handleSetDefault = async (row) => {
  try {
    await setDefaultTemplate(row.template_id);
    await fetchTemplateList();
  } catch (error) {
    console.error('设置默认模板失败', error);
  }
};



// 测试打印
const handleTest = (row) => {
  currentTestTemplate.value = row;
  testVisible.value = true;
};

// 提交测试打印
const submitTest = async () => {
  if (!currentTestTemplate.value) return;
  
  try {
    testLoading.value = true;
    
    let testData = {};
    try {
      testData = JSON.parse(testForm.testData);
    } catch (e) {
      ElMessage.error('测试数据格式错误，请输入有效的JSON格式');
      return;
    }
    
    const res = await testPrintTemplate(currentTestTemplate.value.template_id, {
      test_data: testData
    });
    
    if (res.code === 1) {
      testVisible.value = false;
    }
  } catch (error) {
    console.error('测试打印失败', error);
  } finally {
    testLoading.value = false;
  }
};

onMounted(() => {
  fetchTypeList();
  fetchTemplateList();
});
</script>

<style lang="scss" scoped>
.template-list-wrap {
  padding: 16px;
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .search-area {
    margin-bottom: 20px;
    padding: 20px;
    background: #f5f7fa;
    border-radius: 4px;
  }
  
  .template-name {
    display: flex;
    align-items: center;
  }
  
  .pagination-wrap {
    margin-top: 20px;
    text-align: right;
  }
  
  .preview-content {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #dcdfe6;
    padding: 10px;
    background: #f9f9f9;
  }
}
</style> 