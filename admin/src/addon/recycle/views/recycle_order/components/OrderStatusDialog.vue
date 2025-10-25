<template>
    <el-dialog v-model="dialogVisible" title="修改订单状态" width="400px" :destroy-on-close="true" @closed="handleClosed">
        <el-form :model="form" label-width="100px" v-loading="loading">
            <el-form-item label="当前状态">
                <el-tag :type="getStatusTagType(form.currentStatus)">
                    {{ getOrderStatusText(form.currentStatus) }}
                </el-tag>
            </el-form-item>

            <el-form-item label="目标状态">
                <el-select v-model="form.status" placeholder="请选择状态">
                    <el-option v-for="status in availableNextStatus" :key="status" :label="getOrderStatusText(status)"
                        :value="status" />
                </el-select>
            </el-form-item>

            <el-form-item label="备注" v-if="form.status">
                <el-input v-model="form.remark" type="textarea" :rows="3" placeholder="请输入状态变更备注（可选）" />
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="submitForm" :disabled="!form.status" :loading="submitLoading">
                    确定
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, defineProps, defineEmits, watch, computed } from 'vue';
import { ElMessage } from 'element-plus';
import { updateRecycleOrder } from '@/addon/recycle/api/recycle_order';

// 订单状态定义
const ORDER_STATUS = {
    PENDING_CONFIRM: 1, // 待确认
    CHECKING: 2,        // 检测中
    CHECKED: 3,         // 已检测
    PAYING: 4,          // 待付款
    PAID: 5,            // 已付款
    COMPLETED: 6,       // 已完成
    CANCELLED: 7,       // 已取消
    RETURNING: 8,       // 退货中
    RETURNED: 9         // 已退货
};

// 定义订单类型
interface Order {
    id: number;
    status: number;
    status_name?: string;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    orderData: {
        type: Object as () => Order,
        default: () => ({})
    }
});

const emit = defineEmits(['update:visible', 'success', 'closed']);

// 内部状态
const dialogVisible = ref(props.visible);
const loading = ref(false);
const submitLoading = ref(false);

// 表单数据
const form = reactive({
    id: 0,
    currentStatus: 0,
    status: 0,
    remark: ''
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
    if (props.orderData) {
        form.id = props.orderData.id || 0;
        form.currentStatus = props.orderData.status || 0;
        form.status = 0; // 重置状态选择
        form.remark = '';
    }
};

// 可用的下一状态列表
const availableNextStatus = computed(() => {
    return getAvailableNextStatus(form.currentStatus);
});

// 获取可用的下一个状态
const getAvailableNextStatus = (currentStatus: number): number[] => {
    switch (currentStatus) {
        case ORDER_STATUS.PENDING_CONFIRM:
            return [ORDER_STATUS.CHECKING, ORDER_STATUS.CANCELLED];
        case ORDER_STATUS.CHECKING:
            return [ORDER_STATUS.CHECKED, ORDER_STATUS.CANCELLED];
        case ORDER_STATUS.CHECKED:
            return [ORDER_STATUS.PAYING, ORDER_STATUS.CANCELLED];
        case ORDER_STATUS.PAYING:
            return [ORDER_STATUS.PAID, ORDER_STATUS.CANCELLED, ORDER_STATUS.RETURNING];
        case ORDER_STATUS.PAID:
            return [ORDER_STATUS.COMPLETED, ORDER_STATUS.CANCELLED];
        case ORDER_STATUS.COMPLETED:
            return []; // 已完成状态没有下一步
        case ORDER_STATUS.CANCELLED:
            return []; // 已取消状态没有下一步
        case ORDER_STATUS.RETURNING:
            return [ORDER_STATUS.RETURNED];
        case ORDER_STATUS.RETURNED:
            return []; // 已退货状态没有下一步
        default:
            return [];
    }
};

// 获取订单状态对应的标签类型
const getStatusTagType = (status: number): string => {
    const typeMap: Record<number, string> = {
        [ORDER_STATUS.PENDING_CONFIRM]: 'warning',
        [ORDER_STATUS.CHECKING]: 'primary',
        [ORDER_STATUS.CHECKED]: 'success',
        [ORDER_STATUS.PAYING]: 'warning',
        [ORDER_STATUS.PAID]: 'warning',
        [ORDER_STATUS.COMPLETED]: 'success',
        [ORDER_STATUS.CANCELLED]: 'info',
        [ORDER_STATUS.RETURNING]: 'warning',
        [ORDER_STATUS.RETURNED]: 'info'
    };
    return typeMap[status] || 'info';
};

// 获取订单状态文本
const getOrderStatusText = (status: number): string => {
    const statusMap: Record<number, string> = {
        [ORDER_STATUS.PENDING_CONFIRM]: '待确认',
        [ORDER_STATUS.CHECKING]: '检测中',
        [ORDER_STATUS.CHECKED]: '已检测',
        [ORDER_STATUS.PAYING]: '待付款',
        [ORDER_STATUS.PAID]: '已付款',
        [ORDER_STATUS.COMPLETED]: '已完成',
        [ORDER_STATUS.CANCELLED]: '已取消',
        [ORDER_STATUS.RETURNING]: '退货中',
        [ORDER_STATUS.RETURNED]: '已退货'
    };
    return statusMap[status] || '未知状态';
};

// 提交表单
const submitForm = async () => {
    if (!form.status) {
        ElMessage.warning('请选择目标状态');
        return;
    }

    // 验证状态变更合法性
    const availableStatus = getAvailableNextStatus(form.currentStatus);
    if (!availableStatus.includes(form.status)) {
        ElMessage.error('不允许的状态变更');
        return;
    }

    try {
        submitLoading.value = true;

        const response = await updateRecycleOrder(String(form.id), {
            status: form.status,
            old_status: form.currentStatus,
            remark: form.remark
        });

        if (response.code === 1) {
            ElMessage.success('状态更新成功');
            dialogVisible.value = false;
            emit('success');
        } else {
            ElMessage.error(response.message || '状态更新失败');
        }
    } catch (error: any) {
        console.error('更新状态失败:', error);
        ElMessage.error(error.message || '状态更新失败');
    } finally {
        submitLoading.value = false;
    }
};

// 对话框关闭处理
const handleClosed = () => {
    // 重置表单
    form.status = 0;
    form.remark = '';
    emit('closed');
};

// 初始加载
if (props.visible) {
    initFormData();
}
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>