<template>
  <div class="printer-add-wrap">
    <el-card class="box-card">
      <template #header>
        <div class="card-header">
          <span>绑定打印机</span>
          <el-button text @click="goBack">返回列表</el-button>
        </div>
      </template>
      
      <el-form
        ref="formRef"
        :model="form"
        :rules="rules"
        label-width="120px"
        label-position="right"
        class="printer-form"
      >



        <el-form-item label="打印机品牌" prop="brand">
          <el-select v-model="form.brand" placeholder="请选择打印机品牌" style="width: 100%">
            <el-option 
              v-for="item in brandList"
              :key="item.brand"
              :label="item.name"
              :value="item.brand"
            >
              <div class="brand-option">
                <img :src="item.logo" alt="" class="brand-logo" v-if="item.logo">
                <span>{{ item.name }}</span>
              </div>
            </el-option>
          </el-select>
        </el-form-item>
        
        <el-form-item label="打印机名称" prop="printer_name">
          <el-input v-model="form.printer_name" placeholder="为打印机取个名字，方便识别"></el-input>
        </el-form-item>
        
        <el-form-item label="打印机序列号" prop="sn">
          <el-input v-model="form.sn" placeholder="打印机SN码"></el-input>
        </el-form-item>
        
        <el-form-item label="用户KEY" prop="user_key">
          <el-input v-model="form.user_key" placeholder="云打印服务用户KEY"></el-input>
        </el-form-item>
        
        <el-form-item label="用户名" prop="user_name">
          <el-input v-model="form.user_name" placeholder="云打印服务用户名"></el-input>
        </el-form-item>
        
        <el-form-item label="打印机类型" prop="type">
          <el-radio-group v-model="form.type">
            <el-radio label="label">标签打印机</el-radio>
            <el-radio label="ticket">小票打印机</el-radio>
          </el-radio-group>
        </el-form-item>
    
        <el-form-item>
          <el-button type="primary" @click="submitForm" :loading="loading">保存</el-button>
          <el-button @click="testPrinterHandler" :disabled="!canTest" :loading="testing">测试打印</el-button>
          <el-button @click="goBack">取消</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';
import { getPrinterBrands, bindPrinter, testPrinter } from '@/addon/recycle/api/printer';
import { getUserList } from '@/app/api/site';

const router = useRouter();
const formRef = ref(null);
const loading = ref(false);
const testing = ref(false);
const brandList = ref([]);
const userSearchKey = ref('');
const userSearchResult = ref([]);

// 表单数据
const form = reactive({
  brand: 'xpyun',
  printer_name: '',
  sn: '',
  user_key: '',
  user_name: '',
  type: 'label',
  selectedUsers: []
});

// 表单验证规则
const rules = {
  brand: [{ required: true, message: '请选择打印机品牌', trigger: 'change' }],
  printer_name: [{ required: true, message: '请输入打印机名称', trigger: 'blur' }],
  sn: [{ required: true, message: '请输入打印机序列号', trigger: 'blur' }],
  user_key: [{ required: true, message: '请输入用户KEY', trigger: 'blur' }],
  user_name: [{ required: true, message: '请输入用户名', trigger: 'blur' }]
};

// 是否可以测试打印
const canTest = computed(() => {
  return form.sn && form.user_key && form.user_name;
});

// 获取打印机品牌列表
const fetchBrandList = async () => {
  try {
    const res = await getPrinterBrands();
    
    if (res.code === 1) {
      brandList.value = res.data;
    }
  } catch (error) {
    console.error('获取打印机品牌列表失败', error);
  }
};

// 提交表单
const submitForm = async () => {
  if (!formRef.value) return;
  
  await formRef.value.validate(async (valid) => {
    if (!valid) return;
    
    try {
      loading.value = true;
      
      // 准备提交的数据
      const submitData = {
        brand: form.brand,
        printer_name: form.printer_name,
        sn: form.sn,
        user_key: form.user_key,
        user_name: form.user_name,
        type: form.type
      };
      
      // 如果有选择用户，则提交用户ID
      if (form.selectedUsers.length > 0) {
        submitData.user_ids = form.selectedUsers.map(user => user.uid);
      }
      
      const res = await bindPrinter(submitData);
      
      if (res.code === 1) {
        router.push('/recycle/printer/list');
      } 
    } catch (error) {
      console.error('绑定打印机失败', error);
    } finally {
      loading.value = false;
    }
  });
};

// 测试打印机
const testPrinterHandler = async () => {
  if (!canTest.value) {
    ElMessage.warning('请先填写打印机序列号、用户名和用户KEY');
    return;
  }
  
  try {
    testing.value = true;
    const res = await testPrinter({
      sn: form.sn,
      user_name: form.user_name,
      user_key: form.user_key
    });
    
    if (res.code === 1) {
      ElMessage.success('测试打印成功');
    }
  } catch (error) {
    console.error('测试打印失败', error);
  } finally {
    testing.value = false;
  }
};





// 返回列表页
const goBack = () => {
  router.push('/recycle/printer/list');
};

onMounted(() => {
  fetchBrandList();
});
</script>

<style lang="scss" scoped>
.printer-add-wrap {
  padding: 16px;
  
  .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .printer-form {
    max-width: 800px;
    margin: 0 auto;
  }
  
  .brand-option {
    display: flex;
    align-items: center;
    
    .brand-logo {
      width: 24px;
      height: 24px;
      margin-right: 8px;
    }
  }
  
  .user-search-box {
    margin-bottom: 10px;
    
    .user-search-input {
      width: 100%;
    }
  }
  
  .user-search-result {
    margin-bottom: 10px;
    border: 1px solid #ebeef5;
    border-radius: 4px;
  }
  
  .selected-users {
    margin-top: 10px;
    
    .selected-users-title {
      margin-bottom: 8px;
      font-size: 14px;
      color: #606266;
    }
    
    .user-tag {
      margin-right: 8px;
      margin-bottom: 8px;
    }
  }
  
  .tip-text {
    margin-top: 10px;
    font-size: 12px;
    color: #909399;
  }
}
</style> 