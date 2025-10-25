<template>
    <el-dialog v-model="dialogVisible" title="收款信息" width="500px" :destroy-on-close="true" @closed="handleClosed">
        <el-form :model="form" label-width="100px">
            <el-form-item label="收款账号">
                <el-input v-model="form.account" disabled />
            </el-form-item>

            <el-form-item label="收款方式">
                <el-input v-model="form.pay_type" disabled />
            </el-form-item>

            <el-form-item label="收款金额">
                <el-input v-model="form.amount" disabled>
                    <template #append>元</template>
                </el-input>
            </el-form-item>

            <el-form-item label="收款码" v-if="form.qrcode">
                <div class="qrcode-container">
                    <img :src="form.qrcode" alt="收款码" class="payment-qrcode" />
                </div>
            </el-form-item>

            <el-form-item label="收款说明" v-if="form.payment_desc">
                <div class="payment-desc">
                    {{ form.payment_desc }}
                </div>
            </el-form-item>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="dialogVisible = false">关闭</el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, reactive, defineProps, defineEmits, watch } from 'vue';

// 定义支付信息类型
interface PaymentInfo {
    id?: number;
    account?: string;
    pay_type?: string;
    amount?: number | string;
    qrcode?: string;
    payment_desc?: string;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    paymentData: {
        type: Object as () => PaymentInfo,
        default: () => ({})
    }
});

const emit = defineEmits(['update:visible', 'closed']);

// 内部状态
const dialogVisible = ref(props.visible);

// 表单数据
const form = reactive({
    account: '',
    pay_type: '',
    amount: 0,
    qrcode: '',
    payment_desc: ''
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
    if (props.paymentData) {
        form.account = props.paymentData.account || '';
        form.pay_type = props.paymentData.pay_type || '';
        form.amount = props.paymentData.amount || 0;
        form.qrcode = props.paymentData.qrcode || '';
        form.payment_desc = props.paymentData.payment_desc || '';
    }
};

// 对话框关闭处理
const handleClosed = () => {
    emit('closed');
};

// 初始加载
if (props.visible) {
    initFormData();
}
</script>

<style lang="scss" scoped>
.qrcode-container {
    display: flex;
    justify-content: center;
    margin: 10px 0;

    .payment-qrcode {
        max-width: 200px;
        max-height: 200px;
    }
}

.payment-desc {
    background-color: #f8f8f8;
    padding: 10px;
    border-radius: 4px;
    line-height: 1.5;
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
}
</style>