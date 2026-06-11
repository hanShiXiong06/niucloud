<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">仓库与库位</div>
                    <div class="mt-1 text-sm text-gray-500">正式入库必须落到具体仓库和库位，停用不会影响历史流水。</div>
                </div>
                <el-button type="primary" @click="openWarehouse()">新增仓库</el-button>
            </div>

            <el-table class="mt-5" :data="warehouses" v-loading="loading" row-key="id" size="large">
                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="px-12 py-3">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="font-medium">库位</span>
                                <el-button type="primary" link @click="openLocation(row)">新增库位</el-button>
                            </div>
                            <el-table :data="row.locations || []" border size="small" empty-text="暂无库位">
                                <el-table-column prop="location_name" label="库位名称" />
                                <el-table-column prop="location_code" label="库位编码" />
                                <el-table-column label="状态" width="100">
                                    <template #default="{ row: location }">
                                        <el-tag :type="location.status === 1 ? 'success' : 'info'">
                                            {{ location.status === 1 ? '启用' : '停用' }}
                                        </el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="操作" width="150" align="center">
                                    <template #default="{ row: location }">
                                        <el-button type="primary" link @click="openLocation(row, location)">编辑</el-button>
                                        <el-button type="danger" link @click="removeLocation(location)">删除</el-button>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="warehouse_name" label="仓库名称" min-width="180" />
                <el-table-column prop="warehouse_code" label="仓库编码" width="150" />
                <el-table-column label="默认入库仓" width="130">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default === 1" type="success">默认</el-tag>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="库位数" width="100">
                    <template #default="{ row }">{{ row.locations?.length || 0 }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
                <el-table-column label="操作" width="170" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openWarehouse(row)">编辑</el-button>
                        <el-button type="danger" link @click="removeWarehouse(row)">删除</el-button>
                    </template>
                </el-table-column>

                <template #empty>
                    <EmptyState
                        icon="box"
                        title="还没有仓库"
                        description="先建一个仓库和库位，设备确认入库时才能选择存放位置。"
                    >
                        <template #action>
                            <el-button type="primary" @click="openWarehouse()">新增仓库</el-button>
                        </template>
                    </EmptyState>
                </template>
            </el-table>
        </el-card>

        <el-dialog v-model="warehouseDialog.visible" :title="warehouseDialog.form.id ? '编辑仓库' : '新增仓库'" width="520px">
            <el-form label-width="100px">
                <el-form-item label="仓库名称" required><el-input v-model.trim="warehouseDialog.form.warehouse_name" /></el-form-item>
                <el-form-item label="仓库编码"><el-input v-model.trim="warehouseDialog.form.warehouse_code" /></el-form-item>
                <el-form-item label="状态"><el-switch v-model="warehouseDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="默认入库仓"><el-switch v-model="warehouseDialog.form.is_default" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="warehouseDialog.form.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="warehouseDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="warehouseDialog.loading" @click="submitWarehouse">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="locationDialog.visible" :title="locationDialog.form.id ? '编辑库位' : '新增库位'" width="520px">
            <el-form label-width="100px">
                <el-form-item label="所属仓库">{{ locationDialog.warehouseName }}</el-form-item>
                <el-form-item label="库位名称" required><el-input v-model.trim="locationDialog.form.location_name" /></el-form-item>
                <el-form-item label="库位编码"><el-input v-model.trim="locationDialog.form.location_code" /></el-form-item>
                <el-form-item label="状态"><el-switch v-model="locationDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="locationDialog.form.remark" type="textarea" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="locationDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="locationDialog.loading" @click="submitLocation">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
    deleteErpWarehouse,
    deleteErpWarehouseLocation,
    getErpWarehouseList,
    saveErpWarehouse,
    saveErpWarehouseLocation
} from '@/addon/hsx_erp/api/warehouse'
import EmptyState from '@/addon/hsx_erp/components/empty-state/index.vue'

const loading = ref(false)
const warehouses = ref<any[]>([])
const warehouseDialog = reactive<any>({
    visible: false, loading: false,
    form: { id: 0, warehouse_name: '', warehouse_code: '', status: 1, is_default: 0, remark: '' }
})
const locationDialog = reactive<any>({
    visible: false, loading: false, warehouseId: 0, warehouseName: '',
    form: { id: 0, location_name: '', location_code: '', status: 1, remark: '' }
})

const loadData = async () => {
    loading.value = true
    try {
        const res: any = await getErpWarehouseList()
        warehouses.value = res.data || []
    } finally {
        loading.value = false
    }
}

const openWarehouse = (row: any = {}) => {
    Object.assign(warehouseDialog.form, {
        id: Number(row.id || 0), warehouse_name: row.warehouse_name || '',
        warehouse_code: row.warehouse_code || '', status: row.status ?? 1,
        is_default: row.is_default ?? 0, remark: row.remark || ''
    })
    warehouseDialog.visible = true
}

const submitWarehouse = async () => {
    if (!warehouseDialog.form.warehouse_name) return ElMessage.warning('请填写仓库名称')
    warehouseDialog.loading = true
    try {
        await saveErpWarehouse(warehouseDialog.form.id, { ...warehouseDialog.form })
        ElMessage.success('仓库已保存')
        warehouseDialog.visible = false
        await loadData()
    } finally {
        warehouseDialog.loading = false
    }
}

const openLocation = (warehouse: any, row: any = {}) => {
    locationDialog.warehouseId = Number(warehouse.id)
    locationDialog.warehouseName = warehouse.warehouse_name
    Object.assign(locationDialog.form, {
        id: Number(row.id || 0), location_name: row.location_name || '',
        location_code: row.location_code || '', status: row.status ?? 1, remark: row.remark || ''
    })
    locationDialog.visible = true
}

const submitLocation = async () => {
    if (!locationDialog.form.location_name) return ElMessage.warning('请填写库位名称')
    locationDialog.loading = true
    try {
        await saveErpWarehouseLocation(locationDialog.warehouseId, locationDialog.form.id, { ...locationDialog.form })
        ElMessage.success('库位已保存')
        locationDialog.visible = false
        await loadData()
    } finally {
        locationDialog.loading = false
    }
}

const removeWarehouse = async (row: any) => {
    await ElMessageBox.confirm('确定删除该仓库吗？已有库存的仓库不能删除。', '删除仓库', { type: 'warning' })
    await deleteErpWarehouse(Number(row.id))
    ElMessage.success('仓库已删除')
    await loadData()
}

const removeLocation = async (row: any) => {
    await ElMessageBox.confirm('确定删除该库位吗？已有库存的库位不能删除。', '删除库位', { type: 'warning' })
    await deleteErpWarehouseLocation(Number(row.id))
    ElMessage.success('库位已删除')
    await loadData()
}

onMounted(loadData)
</script>
