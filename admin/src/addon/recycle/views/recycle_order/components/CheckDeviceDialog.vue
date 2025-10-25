<template>
  <el-dialog 
    v-model="dialogVisible" 
    title="设备质检" 
    width="960px" 
    :destroy-on-close="true" 
    class="check-device-dialog"
    align-center
  >
    <!-- 设备信息条 - 紧凑型 -->
    <div class="device-info-bar">
      <div class="device-basic">
        <div class="device-icon">📱</div>
        <div class="device-details">
          <h3 class="device-model">{{ deviceData.model || "未知型号" }}</h3>
          <div class="device-meta">
            <span v-if="!showImeiEdit" class="imei-display">IMEI: {{ deviceForm.imei || '未录入' }}</span>
            <el-input 
            v-else
            v-model="deviceForm.imei" 
            placeholder="请输入15位IMEI或使用扫码枪"
            size="default"
            clearable
            maxlength="15"
            show-word-limit
            @input="handleImeiInput"
            @blur="showImeiEdit = false"
            ref="imeiInputRef"
          >
            <template #prefix>
              <el-icon><Postcard /></el-icon>
            </template>
            <template #append>
              <el-button @click="focusImeiInput">
                <el-icon><Aim /></el-icon>
                扫码
              </el-button>
            </template>
          </el-input>
          </div>
        </div>
      </div>
      <div class="quick-actions">
        <el-button size="small" type="primary" @click="toggleImeiEdit" v-if="!showImeiEdit">
          <el-icon><Edit /></el-icon>
          修改IMEI
        </el-button>
      </div>
    </div>



    <!-- 智能质检面板 -->
    <div class="smart-check-panel">
      <div class="panel-header">
        <span>🔍 智能质检</span>
        <div class="header-actions">
          <el-button 
          size="small" 
          text
          @click="fetchCoverage" 
          :loading="loadingCoverage"
          :disabled="!deviceForm.imei"
        >
          <el-icon v-if="!loadingCoverage"><Headset /></el-icon>
          {{ loadingCoverage ? '查询中...' : '查询保修' }}
        </el-button>
        
                 <el-button 
           text 
           size="small" 
           @click="fetchActivationlock" 
           :loading="loadingActivationLock"
           :disabled="!deviceForm.imei"
         >
           <el-icon v-if="!loadingActivationLock"><Lock /></el-icon>
           {{ loadingActivationLock ? '查询中...' : '查询激活锁' }}
         </el-button>
                 <el-button 
           text 
           size="small" 
           @click="fetchMdm" 
           :loading="loadingMdm"
           :disabled="!deviceForm.imei"
         >
           <el-icon v-if="!loadingMdm"><Monitor /></el-icon>
           {{ loadingMdm ? '查询中...' : '查询监管锁' }}
         </el-button>
        |
          <el-button size="small" text @click="clearAllSelections">清空</el-button>
          <el-button size="small" text @click="fillCommonResult">常用模板</el-button>
          
        </div>
      </div>
      
      
      <!-- 保修信息显示区域 -->
      <el-collapse-transition>
        <div v-if="warrantyInfo" class="warranty-display-panel">
          <div class="warranty-header">
            <span>📱 设备保修信息</span>
            <el-button size="small" text @click="clearWarrantyInfo">
              <el-icon><Close /></el-icon>
              清除
            </el-button>
          </div>

          <WarrantyInfoDisplay :warrantyData="warrantyInfo" />
        </div>
      </el-collapse-transition>
      
      <!-- 快速质检选项 - 卡片式布局 -->
      <div class="check-grid">
        <!-- 电池状态 -->
        <div class="check-card battery-card">
          <div class="card-header">
            <el-icon class="header-icon"><Lightning /></el-icon>
            <span>电池状态</span>
          </div>
          <div class="card-content">
            <div class="input-row">
              <span class="label">健康度</span>
              <el-input-number 
                v-model="templateSelections.battery" 
                :min="0" :max="100" :step="1"
                size="small" @change="updateCheckResult"
              />
              <span class="unit">%</span>
            </div>
            <div class="input-row">
              <span class="label">循环</span>
              <el-input
                type="number"
                v-model="templateSelections.battery_num" 
                :min="0" :max="9999" :step="50"
                size="small" @change="updateCheckResult"
              />
              <span class="unit">次</span>
            </div>
            <div class="input-row">
              <span class="label">激活锁</span>
              <el-switch 
                v-model="templateSelections.activationLock"
                style="--el-switch-on-color: #ff4949 ;--el-switch-off-color: #13ce66;"
                active-text="on"
                inactive-text="off"
                size="small"
                @change="updateCheckResult"
              />
            </div>
            <div class="input-row">
              <span class="label">监管锁</span>
              <el-switch 
                v-model="templateSelections.mdmLock"
                 style="--el-switch-on-color: #ff4949 ; --el-switch-off-color:  #13ce66;"
                active-text="on"
                inactive-text="off"
                size="small"
                @change="updateCheckResult"
              />
            </div>
          </div>
        </div>

        <!-- 屏幕状态 -->
        <div class="check-card screen-card">
          <div class="card-header">
            <el-icon class="header-icon"><Monitor /></el-icon>
            <span>屏幕状态</span>
          </div>
          <div class="card-content">
            <div class="tag-grid">
              <el-tag 
                v-for="option in screenOptions" 
                :key="option"
                :type="templateSelections.screen === option ? 'primary' : undefined"
                :effect="templateSelections.screen === option ? 'dark' : 'plain'"
                size="small"
                class="check-tag"
                @click="selectScreenOption(option)"
              >
                {{ option }}
              </el-tag>
            </div>
          </div>
        </div>

        <!-- 外观状态 -->
        <div class="check-card appearance-card">
          <div class="card-header">
            <el-icon class="header-icon"><Picture /></el-icon>
            <span>外观状态</span>
          </div>
          <div class="card-content">
            <div class="tag-grid">
              <el-tag 
                v-for="option in appearanceOptions" 
                :key="option"
                :type="templateSelections.appearance === option ? 'primary' : undefined"
                :effect="templateSelections.appearance === option ? 'dark' : 'plain'"
                size="small"
                class="check-tag"
                @click="selectAppearanceOption(option)"
              >
                {{ option }}
              </el-tag>
            </div>
          </div>
        </div>

        <!-- 功能测试 -->
        <div class="check-card function-card">
          <div class="card-header">
            <el-icon class="header-icon"><Setting /></el-icon>
            <span>功能异常</span>
          </div>
          <div class="card-content">
            <div class="tag-grid">
              <el-tag 
                v-for="option in functionOptions" 
                :key="option"
                :type="templateSelections.function.includes(option) ? 'danger' : undefined"
                :effect="templateSelections.function.includes(option) ? 'dark' : 'plain'"
                size="small"
                class="check-tag"
                @click="toggleFunctionOption(option)"
              >
                {{ option }}
              </el-tag>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- 核心信息表单 -->
    <el-form :model="deviceForm" :rules="rules" ref="formRef" label-position="top" class="core-form">
      <!-- 质检结果 -->
      <div class="row-result">
        
      <el-form-item class="flex-column" label="📋 质检结果" prop="check_result">
        <el-input 
          type="textarea" 
          v-model="deviceForm.check_result" 
          :rows="4" 
          placeholder="详细描述设备状态，或使用上方快速选择..."
          maxlength="100" 
          show-word-limit 
          class="result-textarea"
        />
      </el-form-item>
      <el-form-item class="flex-direction-column" label="📋 扣费说明" prop="remark">
            <el-input 
              type="textarea" 
              v-model="deviceForm.remark" 
              :rows="4" 
              placeholder="扣费说明、特殊情况备注等..." 
              maxlength="200"
              class="result-textarea"
              show-word-limit 
            />
          </el-form-item>
        <el-form-item class="flex-direction-column price-item" label="💰 最终价格" prop="final_price" >
          <el-input-number 
            v-model="deviceForm.final_price" 
            :step="10" 
            :precision="2" 
            :min="0"
            :max="99999"
            placeholder="定价"
            class="price-input"
          />
        </el-form-item>
</div>
      <!-- 关键信息行 -->
      <div class="key-info-row  align-center">
        <!-- 最终价格 -->
       

        <!-- 质检图片 -->
        <el-form-item label="📸 质检图片" class="upload-item">
          <div class="upload-wrapper">
            <upload-image v-model="deviceForm.check_images" :limit="6" />
            <div class="qr-quick-scan" v-if="code">

              <img :src="code" class="qr-mini" alt="扫码上传" />
               <!-- 通过qrcode 生产二维码 -->

              <span>手机扫码上传</span>
            </div>
          </div>
        </el-form-item>
      </div>

    
    </el-form>

    <!-- 底部操作栏 -->
    <template #footer>
      <div class="action-bar">
        <div class="action-info">
          <span class="check-count">已检测项目: {{ getCheckedCount() }}</span>
        </div>
        <div class="action-buttons">
          <el-button size="large" @click="handleCancel">
            取消
          </el-button>
          <el-button 
            type="primary" 
            size="large" 
            @click="handleConfirm" 
            :loading="submitting"
          >
            <el-icon v-if="!submitting"><Check /></el-icon>
            {{ submitting ? '提交中...' : '完成质检' }}
          </el-button>
        </div>
      </div>
    </template>
  </el-dialog>
</template>

<script setup lang="ts">
import {
  ref,
  defineProps,
  defineEmits,
  watch,
  reactive,
  computed
} from "vue";
import { getCoverage, getActivationlock, getMdm } from '@/addon/recycle/api/device_query_api'

import  QRCode  from "qrcode";

import { ElMessage, FormInstance, FormRules } from "element-plus";
import { 
  Edit, Postcard, Aim, Lightning, Monitor, Picture, Setting, 
  Check, InfoFilled, Headset, Close, Lock
} from '@element-plus/icons-vue';
import { useRoute, useRouter } from 'vue-router'
import WarrantyInfoDisplay from '@/addon/recycle/components/WarrantyInfoDisplay.vue'

const route = useRoute()
const router = useRouter()

// 定义设备信息接口
interface DeviceInfo {
  id?: string | number;
  model?: string;
  imei?: string;
  initial_price?: string | number;
  final_price?: string | number;
  check_result?: string;
  check_images?: string;
  check_status?: number;
  remark?: string;
  status?: number;
  [key: string]: any;
  info?: any;
}

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  device: {
    type: Object as () => DeviceInfo,
    default: () => ({}),
  },
});

function extractBrand(productName:string, brandList:string[]) {
  // 将品牌列表转换为正则表达式，不区分大小写
  const brandRegex = new RegExp(`^(${brandList.join('|')})`, 'i');
  const match = productName.match(brandRegex);
  return match ? match[0] : '';
}

// 品牌列表（注意顺序很重要，长的品牌名应该放在前面）
const brands = [
  '华为', '荣耀', '小米', 'OPPO', 'vivo', 
  '三星', 'realme', '努比亚', 'moto', '中兴','HUAWEI','Xiaomi','OPPO','vivo','Samsung','Realme','Nubia','Moto','ZTE','摩托'
];

// 保修查询
const fetchCoverage = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码');
    return;
  }
  
  

  loadingCoverage.value = true;
  try {

    const res = await getCoverage({imei:deviceForm.imei, brand: extractBrand(deviceData.value.model, brands)});

    if (res.data  && res.data.model) {
      // 直接返回保修数据对象的格式：{ sn: '...', model: '...', ... }
      warrantyInfo.value = res.data;
      deviceData.value.model = res.data.model +' '+ res.data.capacity +' '+ res.data.color;
      deviceForm.info = res.data;
      ElMessage.success('保修信息查询成功');
    } else if (res.data && Array.isArray(res.data) && res.data.length > 0) {
      // 处理数组格式的错误返回
      ElMessage.error('保修查询失败：' + (res.data[0] || '未知错误'));
    } else if (res.data && res.data.msg) {
      // 处理有错误消息的情况
      ElMessage.error('保修查询失败：' + res.data.msg);
    } else {

      ElMessage.warning('未查询到保修信息');
    }
  } catch (error) {
    console.error('保修查询失败:', error);
    ElMessage.error('保修查询失败，请稍后重试');
  } finally {
    loadingCoverage.value = false;
  }
};

// 清除保修信息
const clearWarrantyInfo = () => {
  warrantyInfo.value = null;
};

// 激活锁查询
const fetchActivationlock = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码');
    return;
  }
  
  loadingActivationLock.value = true;
  try {
    const res = await getActivationlock(deviceForm.imei);
    
    if (res.data && res.data.sn) {
      // 直接返回激活锁数据对象的格式
      activationLockInfo.value = res.data;
      
      // 更新激活锁状态到模板选择中
      templateSelections.activationLock = res.data.locked === true || res.data.fmi === 'On';
      
      // 更新质检结果
      updateCheckResult();
      
      ElMessage.success('激活锁信息查询成功');
    } else if (res.data && Array.isArray(res.data) && res.data.length > 0) {
      // 处理数组格式的错误返回
      ElMessage.error('激活锁查询失败：' + (res.data[0] || '未知错误'));
    } else if (res.data && res.data.msg) {
      // 处理有错误消息的情况
      ElMessage.error('激活锁查询失败：' + res.data.msg);
    } else {
      ElMessage.warning('未查询到激活锁信息');
    }
  } catch (error) {
    console.error('激活锁查询失败:', error);
    ElMessage.error('激活锁查询失败，请稍后重试');
  } finally {
    loadingActivationLock.value = false;
  }
};

// 清除激活锁信息
const clearActivationLockInfo = () => {
  activationLockInfo.value = null;
  templateSelections.activationLock = false;
  updateCheckResult();
};

// 监管锁查询
const fetchMdm = async () => {
  if (!deviceForm.imei) {
    ElMessage.warning('请先输入IMEI号码');
    return;
  }
  
  loadingMdm.value = true;
  try {
    const res = await getMdm(deviceForm.imei);
    
    if (res.data && res.data.sn) {
      // 直接返回监管锁数据对象的格式
      mdmInfo.value = res.data;
      
      // 更新监管锁状态到模板选择中
      // MDM锁通常通过特定字段判断，这里假设有locked或mdm字段
      templateSelections.mdmLock = res.data.locked === true || res.data.mdm === 'On' || res.data.mdm === true;
      
      // 更新质检结果
      updateCheckResult();
      
      ElMessage.success('监管锁信息查询成功');
    } else if (res.data && Array.isArray(res.data) && res.data.length > 0) {
      // 处理数组格式的错误返回
      ElMessage.error('监管锁查询失败：' + (res.data[0] || '未知错误'));
    } else if (res.data && res.data.msg) {
      // 处理有错误消息的情况
      ElMessage.error('监管锁查询失败：' + res.data.msg);
    } else {
      ElMessage.warning('未查询到监管锁信息');
    }
  } catch (error) {
    console.error('监管锁查询失败:', error);
    ElMessage.error('监管锁查询失败，请稍后重试');
  } finally {
    loadingMdm.value = false;
  }
};

// 清除监管锁信息
const clearMdmInfo = () => {
  mdmInfo.value = null;
  templateSelections.mdmLock = false;
  updateCheckResult();
};

const emit = defineEmits(["update:visible", "confirm", "cancel"]);

// 内部状态
const dialogVisible = ref(props.visible);
const deviceData = ref<DeviceInfo>({ ...props.device });
const submitting = ref(false);
const formRef = ref<FormInstance>();
const imeiInputRef = ref();
const showImeiEdit = ref(false);
const activeCollapse = ref([]);
const code = ref(''); // 二维码数据 当前域名+/site/diy/attachment
const loadingCoverage = ref(false); // 保修查询加载状态
const warrantyInfo = ref<any>(null); // 保修信息数据
const loadingActivationLock = ref(false); // 激活锁查询加载状态
const activationLockInfo = ref<any>(null); // 激活锁信息数据
const loadingMdm = ref(false); // 监管锁查询加载状态
const mdmInfo = ref<any>(null); // 监管锁信息数据

// 表单验证规则
const rules = reactive<FormRules>({
  check_result: [
    { required: true, message: "请输入质检结果", trigger: "blur" },
    { min: 5, message: "质检结果至少5个字符", trigger: "blur" },
  ],
});


// 生成二维码
const generateQrCode = async () => {
  
 code.value = await QRCode.toDataURL(window.location.origin + '/site/diy/attachment' ,  { errorCorrectionLevel: 'L', margin: 0, width: 100 });
};
generateQrCode()

// 表单数据
const deviceForm = reactive<{
  check_result: string;
  check_images: string;
  final_price: number | undefined;
  remark: string;
  imei: string;
  info?: any;
}>({
  check_result: props.device.check_result || "",
  check_images: props.device.check_images || "",
  final_price:
    typeof props.device.final_price === "number"
      ? props.device.final_price
      : typeof props.device.final_price === "string"
        ? parseFloat(props.device.final_price) || undefined
        : undefined,
  remark: props.device.remark || "",
  imei: props.device.imei || "",
});

// 质检模板选项 - 精简
const screenOptions = [ '无划痕','细微划痕','小划痕','明显划痕','硬划痕','外爆','内爆','未知部件','官方提示'];
const appearanceOptions = ['无磕碰','细微划痕','轻微氧化','中度磨损','重度磨损','严重损坏','组装壳','组装后玻璃'];
const functionOptions = ['通话','充电','指纹','面容','WiFi','蓝牙','指南针','NFC','振动','重力','wifi','距离感应','光线感应','闪光','触摸','主麦','前麦','后麦','扬声器','听筒','网络锁','按键','前摄','后摄'];

// 模板选择状态
const templateSelections = reactive({
  battery: undefined as number | undefined,
  battery_num: undefined as number | undefined,
  screen: '',
  appearance: '',
  function: [] as string[],
  activationLock: false, // 激活锁状态：false=关闭(绿色)，true=开启(红色)
  mdmLock: false, // 监管锁状态：false=关闭(绿色)，true=开启(红色)
});

// 计算已检测项目数量
const getCheckedCount = () => {
  let count = 0;
  if (templateSelections.battery) count++;
  if (templateSelections.battery_num) count++;
  if (templateSelections.screen) count++;
  if (templateSelections.appearance) count++;
  count += templateSelections.function.length;
  return count;
};

// 选择屏幕状态
const selectScreenOption = (option: string) => {
  templateSelections.screen = templateSelections.screen === option ? '' : option;
  updateCheckResult();
};

// 选择外观状态
const selectAppearanceOption = (option: string) => {
  templateSelections.appearance = templateSelections.appearance === option ? '' : option;
  updateCheckResult();
};

// 切换功能选项（可多选）
const toggleFunctionOption = (option: string) => {
  const index = templateSelections.function.indexOf(option);
  if (index > -1) {
    templateSelections.function.splice(index, 1);
  } else {
    templateSelections.function.push(option);
  }
  updateCheckResult();
};

// 更新质检结果
const updateCheckResult = () => {
  const results = [];
  
  // 电池
  if (templateSelections.battery) {
    results.push(`电池健康度${templateSelections.battery}%`);
  }
  
  if (templateSelections.battery_num) {
    results.push(`循环${templateSelections.battery_num}次`);
  }
  
  // 激活锁
  if (templateSelections.activationLock) {
    results.push('激活锁开启');
  }
  
  // 监管锁
  if (templateSelections.mdmLock) {
    results.push('监管锁开启');
  }

  // 屏幕
  if (templateSelections.screen) {
    results.push(`屏幕${templateSelections.screen}`);
  }
  
  // 外观
  if (templateSelections.appearance) {
    results.push(`外观${templateSelections.appearance}`);
  }
  
  // 功能异常
  if (templateSelections.function.length > 0) {
    results.push(`功能异常: ${templateSelections.function.join('、')}`);
  }
  
  deviceForm.check_result = results.join('; ');
};

// 清空所有选择
const clearAllSelections = () => {
  templateSelections.battery = undefined;
  templateSelections.battery_num = undefined;
  templateSelections.activationLock = false;
  templateSelections.mdmLock = false;
  templateSelections.screen = '';
  templateSelections.appearance = '';
  templateSelections.function = [];
  deviceForm.check_result = '';
};

// 填充常用结果
const fillCommonResult = () => {
  templateSelections.battery = 85;
  templateSelections.screen = '完好';
  templateSelections.appearance = '轻微磨损';
  updateCheckResult();
};

// 切换IMEI编辑
const toggleImeiEdit = () => {
  showImeiEdit.value = !showImeiEdit.value;
  if (showImeiEdit.value) {
    setTimeout(() => {
      imeiInputRef.value?.focus();
    }, 100);
  }
};

// 监听visible属性变化
watch(
  () => props.visible,
  (newVal) => {
    dialogVisible.value = newVal;
  }
);

// 监听device属性变化
watch(
  () => props.device,
  (newVal) => {
    deviceData.value = { ...newVal };
    deviceForm.check_result = newVal.check_result || "";
    deviceForm.check_images = newVal.check_images || "";
    deviceForm.final_price =
      typeof newVal.final_price === "number"
        ? newVal.final_price
        : typeof newVal.final_price === "string"
          ? parseFloat(newVal.final_price) || undefined
          : undefined;
    deviceForm.remark = newVal.remark || "";
    deviceForm.imei = newVal.imei || "";
  },
  { deep: true }
);

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
  emit("update:visible", newVal);
});

// 处理取消操作
const handleCancel = () => {
  dialogVisible.value = false;
  emit("cancel");
};

// 处理确认操作
const handleConfirm = async () => {
  if (!formRef.value) return;

  await formRef.value.validate(async (valid, fields) => {
    if (!valid) {
      return;
    }

    submitting.value = true;
    try {
      // 准备提交的数据
      const submitData = {
        id: deviceData.value.id,
        check_result: deviceForm.check_result,
        check_images: deviceForm.check_images,
        remark: deviceForm.remark,
        check_status: 1, // 已质检
        final_price: deviceForm.final_price,
        action: "check", // 标识这是质检操作
        imei: deviceForm.imei,
        model: deviceData.value.model,
        info: deviceForm.info,
      };
      emit("confirm", submitData);
      dialogVisible.value = false;
    } finally {
      submitting.value = false;
    }
  });
};

// IMEI输入相关方法
const handleImeiInput = (value: string) => {
  // 过滤非数字字符，IMEI通常只包含数字
  const filteredValue = value.replace(/[^0-9]/g, '');
  if (filteredValue !== value) {
    deviceForm.imei = filteredValue;
  }
};

const focusImeiInput = () => {
  if (imeiInputRef.value) {
    imeiInputRef.value.focus();
  }
};
</script>

<style lang="scss" scoped>
// 主对话框样式
.check-device-dialog {
  :deep(.el-dialog) {
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
  }

  :deep(.el-dialog__header) {
    padding: 20px 24px 12px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    
    .el-dialog__title {
      font-size: 18px;
      font-weight: 600;
      color: white;
    }
  }
  
  :deep(.el-dialog__body) {
    padding: 20px 24px;
    background-color: #fafbfc;
    max-height: 70vh;
    overflow-y: auto;
  }
  
  :deep(.el-dialog__footer) {
    padding: 16px 24px;
    background-color: white;
    border-top: 1px solid #e5e7eb;
  }
}

// 设备信息条
.device-info-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 16px;
  margin-bottom: 16px;
  
  .device-basic {
    display: flex;
    align-items: center;
    gap: 12px;
    
    .device-icon {
      font-size: 24px;
      width: 40px;
      height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f3f4f6;
      border-radius: 8px;
    }
    
    .device-details {
      .device-model {
        font-size: 16px;
        font-weight: 600;
        margin: 0 0 4px 0;
        color: #111827;
      }
      
      .device-meta {
        display: flex;
        align-items: center;
        gap: 16px;
        font-size: 13px;
        color: #6b7280;
        
        .price-display {
          color: #f59e0b;
          font-weight: 600;
        }
      }
    }
  }
}

// IMEI编辑面板
.imei-edit-panel {
  background: #f8fafc;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  margin-bottom: 16px;
  
  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #e2e8f0;
    font-weight: 500;
    color: #374151;
  }
  
  .imei-input-group {
    padding: 16px;
  }
}

// 智能质检面板
.smart-check-panel {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  margin-bottom: 16px;
  
  .panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px;
    border-bottom: 1px solid #e5e7eb;
    font-weight: 600;
    color: #111827;
    
    .header-actions {
      display: flex;
      gap: 8px;
    }
  }
  
  .panel-content {
    padding: 8px;
    display: flex;
    gap: 12px;
    
    .el-button {
      border-radius: 6px;
      transition: all 0.2s ease;
      
      &:hover {
        transform: translateY(-1px);
      }
    }
  }
  
  // 保修信息显示区域
  .warranty-display-panel {
    margin: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    overflow: hidden;
    background: #ffffff;
    
    .warranty-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 12px 16px;
      background: linear-gradient(90deg, #f8fafc 0%, #e2e8f0 100%);
      border-bottom: 1px solid #e5e7eb;
      font-weight: 600;
      color: #374151;
      
      span {
        font-size: 14px;
      }
    }
    
    // 重写保修信息组件在这里的样式
    :deep(.warranty-info-display) {
      padding: 16px;
      background: #ffffff;
      min-height: auto;
      
      .device-basic-card,
      .info-cards-grid,
      .additional-info-card,
      .brightstar-card {
        margin-bottom: 12px;
        
        &:last-child {
          margin-bottom: 0;
        }
      }
      
      .info-cards-grid {
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 12px;
      }
    }
  }
  
  .check-grid {
    display: grid;
    grid-template-columns:  1fr 1fr 1fr 2fr;
    gap: 8px;
    padding: 8px;
  }
  
  .check-card {
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    overflow: hidden;
    
    .card-header {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 12px;
      background: #f9fafb;
      border-bottom: 1px solid #e5e7eb;
      font-size: 14px;
      font-weight: 500;
      color: #374151;
      
      .header-icon {
        color: #6366f1;
      }
    }
    
    .card-content {
      padding: 12px;
      
      .input-row {
        display: flex;
        align-items: center;
        gap: 2px;
        margin-bottom: 8px;
        
        &:last-child {
          margin-bottom: 0;
        }
        
        .label {
          width: 60px;
          font-size: 12px;
          color: #6b7280;
        }
        
        .unit {
          font-size: 12px;
          color: #9ca3af;
        }
      }
      
      .tag-grid {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        
        .check-tag {
          cursor: pointer;
          font-size: 12px;
          transition: all 0.2s;
          
          &:hover {
            transform: translateY(-1px);
          }
        }
      }
    }
  }
}

// 核心表单
.core-form {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 8px;
  padding: 20px;
  
  .result-textarea {
    :deep(.el-textarea__inner) {
      border: 1px solid #d1d5db;
      border-radius: 6px;
      font-size: 14px;
    }
  }
  .row-result{
    display: grid;
    gap: 24px;
    grid-template-columns: 1fr 1fr 1fr;
  }
  .key-info-row {
    display: grid;
    // grid-template-columns: 1fr 2fr;
    gap: 24px;
    margin-top: 16px;
    align-items: center;
    
    .price-item {
      .price-input {
        width: 100%;
      }
    }
    
    .upload-item {
      .upload-wrapper {
        display: flex;
        align-items: flex-start;
        gap: 16px;
        
        .qr-quick-scan {
          display: flex;
          flex-direction: column;
          align-items: center;
          gap: 4px;
          
          .qr-mini {
            width: 50px;
            height: 50px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
          }
          
          span {
            font-size: 12px;
            color: #6b7280;
          }
        }
      }
    }
  }
  
  .remark-collapse {
    margin-top: 16px;
    border: 1px solid #e5e7eb;
    border-radius: 6px;
    
    :deep(.el-collapse-item__header) {
      background: #f9fafb;
      padding: 12px 16px;
      font-size: 14px;
      color: #374151;
    }
    
    :deep(.el-collapse-item__content) {
      padding: 16px;
    }
  }
}

// 底部操作栏
.action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  
  .action-info {
    .check-count {
      font-size: 13px;
      color: #6b7280;
      background: #f3f4f6;
      padding: 4px 8px;
      border-radius: 4px;
    }
  }
  
  .action-buttons {
    display: flex;
    gap: 12px;
  }
}

// 响应式设计
@media (max-width: 768px) {
  .check-device-dialog {
    :deep(.el-dialog) {
      width: 95vw !important;
      max-height: 90vh;
    }
  }
  
  .check-grid {
    grid-template-columns: 1fr  1fr !important;
  }
  
  .key-info-row {
    grid-template-columns: 1fr !important;
    gap: 16px !important;
  }
  
  .action-bar {
    flex-direction: column;
    gap: 12px;
    
    .action-buttons {
      width: 100%;
      
      .el-button {
        flex: 1;
      }
    }
  }
}
</style>