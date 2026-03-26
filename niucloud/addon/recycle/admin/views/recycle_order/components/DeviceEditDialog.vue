<template>
    <el-dialog
        v-model="dialogVisible"
        title="设备验机结果和定价"
        width="640px"
        :destroy-on-close="true"
        @closed="handleClosed"
        class="device-edit-dialog"
    >
        <!-- 设备信息只读卡片 -->
        <DeviceInfoCard
            v-if="props.deviceData"
            :device="props.deviceData"
            mode="full"
            class="mb-4"
        />

        <el-form
            :model="form"
            label-width="100px"
            :rules="rules"
            ref="formRef"
            v-loading="loading"
        >
            <el-form-item label="IMEI">
                <el-input v-model="form.imei" disabled />
            </el-form-item>

            <el-form-item label="型号">
                <el-input v-model="form.model" />
            </el-form-item>
            
            <el-form-item label="当前状态">
                <el-tag :type="getStatusTagType(form.currentStatus)">
                    {{ getDeviceStatusText(form.currentStatus) }}
                </el-tag>
            </el-form-item>

            <el-form-item label="卖家质检结果" prop="check_result_seller">
                <el-input
                    v-model="form.check_result_seller"
                    type="textarea"
                    :rows="3"
                    placeholder="请输入卖家质检结果"
                />
            </el-form-item>

            <el-form-item label="买家质检结果">
                <el-input
                    v-model="form.check_result_buyer"
                    type="textarea"
                    :rows="3"
                    placeholder="请输入买家质检结果"
                />
            </el-form-item>

            <el-form-item label="卖家照片">
                <upload-image v-model="form.check_images_seller" :limit="10" />
            </el-form-item>

            <el-form-item label="买家照片">
                <upload-image v-model="form.check_images_buyer" :limit="10" />
            </el-form-item>
            
            <!-- <el-form-item label="预估价格">
                <el-input v-model="form.initial_price" type="number" disabled>
                    <template #append>元</template>
                </el-input>
            </el-form-item> -->
            
            <el-form-item label="最终报价" prop="final_price">
                <el-input v-model="form.final_price" type="number">
                    <template #append>元</template>
                </el-input>
            </el-form-item>

            <el-form-item label="卖货价格">
                <el-input v-model="form.sell_price" type="number">
                    <template #append>元</template>
                </el-input>
            </el-form-item>
            
            <el-form-item label="价格备注" prop="price_remark">
                <el-input 
                    v-model="form.price_remark" 
                    type="textarea" 
                    :rows="2" 
                    placeholder="请输入价格备注" 
                />
            </el-form-item>
        </el-form>
        
        <template #footer>
            <span class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button 
                    type="success" 
                    @click="submitForm('CHECK_PRICE')" 
                    :loading="submitLoading"
                >
                    确认质检并定价
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, defineProps, defineEmits, watch, computed } from 'vue';
import DeviceInfoCard from './DeviceInfoCard.vue';
import type { FormInstance, FormRules } from 'element-plus';
import { ElMessage } from 'element-plus';
import { updateDeviceStatus } from '@/addon/recycle/api/recycle_order';

// 设备状态定义
const DEVICE_STATUS = {
    PENDING_CHECK: 1,   // 待验机
    CHECKING: 2,        // 验机中
    CHECKED: 3,         // 已验机
    CONFIRMED: 4,       // 已确认价格
    RECYCLED: 5,        // 已回收
    RETURNED: 6,        // 已退回
    CANCELLED: 7        // 已取消
}

// 定义设备类型
interface Device {
    id: number;
    order_id: number;
    imei?: string;
    model?: string;
    initial_price?: string | number;
    final_price?: string | number;
    sell_price?: string | number;
    status: number;
    status_name?: string;
    check_result?: string;
    check_result_seller?: string;
    check_result_buyer?: string;
    check_images?: string | any[];
    check_images_seller?: string | any[];
    check_images_buyer?: string | any[];
    price_remark?: string;
}

// 定义提交参数类型
interface DeviceStatusUpdateParams {
    order_id: number;
    status: number;
    check_result?: string;
    check_result_seller?: string;
    check_result_buyer?: string;
    check_images?: string | any[];
    check_images_seller?: string | any[];
    check_images_buyer?: string | any[];
    final_price?: string | number;
    sell_price?: string | number;
    price_remark?: string;
    check_status?: number;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    deviceData: {
        type: Object as () => Device,
        default: () => ({})
    }
});

const emit = defineEmits(['update:visible', 'success', 'closed']);

// 内部状态
const dialogVisible = ref(props.visible);
const loading = ref(false);
const submitLoading = ref(false);
const formRef = ref<FormInstance>();

// 表单数据
const form = reactive({
    id: 0,
    order_id: 0,
    imei: '',
    model: '',
    currentStatus: DEVICE_STATUS.PENDING_CHECK,
    check_result: '',
    check_result_seller: '',
    check_result_buyer: '',
    check_images: '',
    check_images_seller: '',
    check_images_buyer: '',
    initial_price: 0,
    final_price: 0,
    sell_price: 0,
    price_remark: ''
});

// 表单验证规则
const rules = reactive<FormRules>({
    check_result_seller: [
        { required: true, message: '请输入卖家质检结果', trigger: 'blur' }
    ],
    final_price: [
        { required: true, message: '请输入最终价格', trigger: 'blur' },
        { type: 'number', min: 0, message: '价格必须大于等于0', trigger: 'blur', transform: (val) => Number(val) }
    ],
    price_remark: [
        { required: true, message: '请输入价格备注', trigger: 'blur' }
    ]
});

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal;
    if (newVal) {
        initFormData();
    }
});

// 监听dialogVisible变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal);
});

// 初始化表单数据
const initFormData = () => {
    if (props.deviceData) {
        form.id = props.deviceData.id || 0;
        form.order_id = props.deviceData.order_id || 0;
        form.imei = props.deviceData.imei || '';
        form.model = props.deviceData.model || '';
        form.currentStatus = props.deviceData.status || DEVICE_STATUS.PENDING_CHECK;
        form.check_result = props.deviceData.check_result || '';
        form.check_result_seller = props.deviceData.check_result_seller || props.deviceData.check_result || '';
        form.check_result_buyer = props.deviceData.check_result_buyer || '';
        form.check_images = props.deviceData.check_images || '';
        form.check_images_seller = props.deviceData.check_images_seller || props.deviceData.check_images || '';
        form.check_images_buyer = props.deviceData.check_images_buyer || '';
        form.initial_price = props.deviceData.initial_price || 0;
        form.final_price = props.deviceData.final_price || 0;
        form.sell_price = props.deviceData.sell_price || 0;
        form.price_remark = props.deviceData.price_remark || '';
    }
};

// 获取设备状态对应的标签类型
const getStatusTagType = (status: number): string => {
    const typeMap: Record<number, string> = {
        [DEVICE_STATUS.PENDING_CHECK]: 'warning',  // 待验机
        [DEVICE_STATUS.CHECKING]: 'primary',       // 验机中
        [DEVICE_STATUS.CHECKED]: 'success',        // 已验机
        [DEVICE_STATUS.CONFIRMED]: 'warning',      // 已确认价格
        [DEVICE_STATUS.RECYCLED]: 'success',       // 已回收
        [DEVICE_STATUS.RETURNED]: 'danger',        // 已退回
        [DEVICE_STATUS.CANCELLED]: 'info'          // 已取消
    };
    return typeMap[status] || 'info';
};

// 获取设备状态文本
const getDeviceStatusText = (status: number): string => {
    const statusMap: Record<number, string> = {
        [DEVICE_STATUS.PENDING_CHECK]: '待验机',
        [DEVICE_STATUS.CHECKING]: '验机中',
        [DEVICE_STATUS.CHECKED]: '已验机',
        [DEVICE_STATUS.CONFIRMED]: '已确认价格',
        [DEVICE_STATUS.RECYCLED]: '已回收',
        [DEVICE_STATUS.RETURNED]: '已退回',
        [DEVICE_STATUS.CANCELLED]: '已取消'
    };
    return statusMap[status] || '未知状态';
};

// 提交表单
const submitForm = async (action: 'CHECK' | 'CHECK_PRICE') => {
    if (!formRef.value) return;
    
    try {
        submitLoading.value = true;
        
        // 处理待质检状态
        if (form.currentStatus === DEVICE_STATUS.PENDING_CHECK) {
            if (action === 'CHECK_PRICE') {
                // 确认质检并定价，直接进入已确认状态
                await formRef.value.validate();
            }
        }
        // 处理质检中状态
        else if (form.currentStatus === DEVICE_STATUS.CHECKING) {
            // 完成质检，进入已确认状态
            await formRef.value.validate();
        }
        
        // 准备提交参数
        let params: DeviceStatusUpdateParams = {
            order_id: form.order_id,
            status: DEVICE_STATUS.CHECKED, // 默认更新到已验机状态
            check_result: form.check_result,
            check_result_seller: form.check_result_seller,
            check_result_buyer: form.check_result_buyer,
            check_images: form.check_images_seller || form.check_images,
            check_images_seller: form.check_images_seller || form.check_images,
            check_images_buyer: form.check_images_buyer,
            final_price: form.final_price,
            sell_price: form.sell_price,
            price_remark: form.price_remark,
            check_status: 1
        };
        
        // 提交请求
        const response = await updateDeviceStatus(String(form.id), params);
        if (response.code === 1) {
            ElMessage.success('更新设备信息成功');
            dialogVisible.value = false;
            emit('success');
        } else {
            ElMessage.error(response.message || '更新设备信息失败');
        }
    } catch (error: any) {
        console.error('更新设备信息失败:', error);
        ElMessage.error(error.message || '更新设备信息失败');
    } finally {
        submitLoading.value = false;
    }
};

// 对话框关闭处理
const handleClosed = () => {
    // 重置表单
    if (formRef.value) {
        formRef.value.resetFields();
    }
    emit('closed');
};

// 初始加载
if (props.visible) {
    initFormData();
}
</script>

<style lang="scss" scoped>
.device-edit-dialog {
  :deep(.el-dialog__body) {
    padding: 16px 20px 0;
  }
}
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style> 