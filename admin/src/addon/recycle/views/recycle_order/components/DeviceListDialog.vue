<template>
    <el-dialog v-model="dialogVisible" title="设备信息确认" width="800" center :destroy-on-close="true"
        @closed="handleClosed">
        <template #header>
            <div class="flex justify-between items-center">
                <span class="text-lg font-bold">设备信息确认 , 共 {{ devices.length }} 台 </span>
                <el-button type="primary" @click="addDevice">添加设备</el-button>
            </div>
        </template>

        <el-table :data="devices" border>
            <el-table-column label="IMEI">
                <template #default="{ row }">
                    <div v-if="row.editing">
                        <el-input v-model="row.imei" placeholder="请输入IMEI" />
                    </div>
                    <span v-else>{{ row.imei }}</span>
                </template>
            </el-table-column>
            <el-table-column label="型号">
                <template #default="{ row }">
                    <div v-if="row.editing">
                        <el-input v-model="row.model" placeholder="请输入型号" />
                    </div>
                    <span v-else>{{ row.model }}</span>
                </template>
            </el-table-column>
            <!-- <el-table-column label="预估价格">
                <template #default="{ row }">
                    <div v-if="row.editing">
                        <el-input-number v-model="row.initial_price" :min="0" :step="10" />
                    </div>
                    <span v-else>{{ row.initial_price }}</span>
                </template>
            </el-table-column> -->
            <el-table-column label="操作" width="120">
                <template #default="{ row }">
                    <el-button-group>
                        <el-button type="primary" size="small" :icon="Edit" @click="editDevice(row)">
                            编辑
                        </el-button>
                        <el-button type="danger" size="small" :icon="Delete" @click="removeDevice(row)">
                            删除
                        </el-button>
                    </el-button-group>
                </template>
            </el-table-column>
        </el-table>

        <template #footer>
            <div class="dialog-footer">
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="confirmDevices" :loading="loading">
                    确认并签收
                </el-button>
            </div>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { ref, defineProps, defineEmits, watch } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Edit, Delete } from '@element-plus/icons-vue'
import { updateDeviceStatus } from '@/addon/recycle/api/recycle_order'

// 定义设备接口
interface Device {
    id?: string | number;
    imei: string;
    model: string;
    initial_price: number | string;
    editing: boolean;
    [key: string]: any;
}

// API响应接口
interface ApiResponse {
    code: number;
    message?: string;
    data?: any;
}

const props = defineProps({
    visible: {
        type: Boolean,
        default: false
    },
    orderId: {
        type: [String, Number],
        default: ''
    },
    deviceList: {
        type: Array as () => Device[],
        default: () => []
    }
})

const emit = defineEmits(['update:visible', 'success', 'closed', 'delete'])

// 内部状态
const dialogVisible = ref(props.visible)
const loading = ref(false)
const devices = ref<Device[]>([])

// 监听visible属性变化
watch(() => props.visible, (newVal) => {
    dialogVisible.value = newVal
    if (newVal) {
        // 初始化设备列表（深拷贝避免直接修改props）
        devices.value = JSON.parse(JSON.stringify(props.deviceList))
    }
})

// 监听内部visible状态变化，同步到父组件
watch(dialogVisible, (newVal) => {
    emit('update:visible', newVal)
})

// 添加新设备
const addDevice = () => {
    devices.value.push({
        imei: '',
        model: '',
        initial_price: 0,
        editing: true
    })
}

// 编辑设备
const editDevice = (row: Device) => {
    row.editing = true
}

// 删除设备
const removeDevice = async (row: Device) => {
    try {
        // 如果不是新设备，需要弹出确认框
        if (!row.isNew && row.id) {
            await ElMessageBox.confirm('确认删除该设备?', '提示', {
                confirmButtonText: '确定',
                cancelButtonText: '取消',
                type: 'warning'
            })

            // 通知父组件处理删除
            emit('delete', row.id)
            // 从本地列表中移除 - 让父组件刷新后再重新加载，避免界面状态不一致
            // 不在这里直接修改列表，等API调用成功后父组件会刷新
        } else {
            // 如果是新设备，直接从本地列表中移除
            devices.value = devices.value.filter(item => item !== row)
            ElMessage.success('已移除')
        }
    } catch (error: any) {
        // 用户取消操作或删除失败
        if (error !== 'cancel') {
            console.error('删除设备失败：', error)
            ElMessage.error('删除失败')
        }
    }
}

// 确认设备信息并签收
const confirmDevices = async () => {
    // 验证必填项
    for (const device of devices.value) {
        if (!device.imei || !device.model) {
            ElMessage.warning('请完善设备信息')
            return
        }
    }

    try {
        loading.value = true

        // 准备要提交的数据
        const data = {
            order_id: props.orderId,
            devices: devices.value.map(device => ({
                id: device.id,
                imei: device.imei,
                model: device.model,
                initial_price: device.initial_price
            }))
        }

        // 调用API保存设备信息
        const result = await updateDeviceStatus(String(props.orderId), data) as unknown as ApiResponse

        if (result.code !== 1) {
            throw new Error(result.message || '操作失败')
        }

        ElMessage.success('订单签收成功')
        dialogVisible.value = false
        emit('success')
    } catch (error: any) {
        console.error('保存设备信息失败：', error)
        ElMessage.error(error.message || '保存失败')
    } finally {
        loading.value = false
    }
}

// 取消编辑
const handleClosed = () => {
    emit('closed')
}
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
</style>