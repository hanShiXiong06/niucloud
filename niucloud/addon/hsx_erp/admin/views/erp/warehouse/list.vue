<template>
    <div class="main-container">
        <el-card class="!border-none" shadow="never">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-page-title">仓库库位</div>
                    <div class="mt-1 text-sm text-gray-500">采购入库必须落到仓库和库位，后续销售、盘点、调拨都以这里为准。</div>
                </div>
                <el-button type="primary" :icon="Plus" @click="openWarehouse()">新增仓库</el-button>
            </div>

            <el-table class="mt-5" :data="warehouses" v-loading="loading" row-key="id" size="large">
                <el-table-column type="expand">
                    <template #default="{ row }">
                        <div class="px-12 py-3">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="font-medium">库位</span>
                                <el-button type="primary" link :icon="Plus" @click="openLocation(row)">新增库位</el-button>
                            </div>
                            <el-table :data="row.locations || []" border size="small" empty-text="暂无库位">
                                <el-table-column prop="location_name" label="库位名称" min-width="160" />
                                <el-table-column prop="location_code" label="库位编码" min-width="140" />
                                <el-table-column label="负责人" min-width="150">
                                    <template #default="{ row: location }">
                                        <span v-if="location.manager_effective_name">{{ location.manager_effective_name }}</span>
                                        <span v-else class="text-orange-500">未设置</span>
                                        <el-tag v-if="location.manager_source === 'warehouse'" class="ml-2" size="small" effect="plain">继承仓库</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column label="状态" width="100">
                                    <template #default="{ row: location }">
                                        <el-tag :type="location.status === 1 ? 'success' : 'info'">{{ location.status === 1 ? '启用' : '停用' }}</el-tag>
                                    </template>
                                </el-table-column>
                                <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
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
                <el-table-column label="负责人" min-width="130">
                    <template #default="{ row }">
                        <span v-if="row.manager_name">{{ row.manager_name }}</span>
                        <span v-else class="text-orange-500">未设置</span>
                    </template>
                </el-table-column>
                <el-table-column label="业务属性" min-width="230">
                    <template #default="{ row }">
                        <div class="flex flex-wrap gap-1">
                            <el-tag :type="warehouseTypeMeta(row.warehouse_type).type">{{ warehouseTypeMeta(row.warehouse_type).label }}</el-tag>
                            <el-tag v-if="row.need_photo === 1" effect="plain" type="warning">拍照</el-tag>
                            <el-tag v-if="row.need_pricing === 1" effect="plain" type="warning">定价</el-tag>
                            <el-tag v-if="row.allow_direct_sale === 1" effect="plain" type="success">可直售</el-tag>
                            <el-tag v-if="row.allow_transfer === 0" effect="plain" type="danger">禁调拨</el-tag>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="默认入库仓" width="120">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_default === 1" type="success">默认</el-tag>
                        <span v-else class="text-gray-400">-</span>
                    </template>
                </el-table-column>
                <el-table-column label="库位数" width="90">
                    <template #default="{ row }">{{ row.locations?.length || 0 }}</template>
                </el-table-column>
                <el-table-column label="状态" width="100">
                    <template #default="{ row }"><el-tag :type="row.status === 1 ? 'success' : 'info'">{{ row.status === 1 ? '启用' : '停用' }}</el-tag></template>
                </el-table-column>
                <el-table-column prop="remark" label="备注" min-width="180" show-overflow-tooltip />
                <el-table-column label="操作" width="160" align="center">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="openWarehouse(row)">编辑</el-button>
                        <el-button type="danger" link @click="removeWarehouse(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-dialog v-model="warehouseDialog.visible" :title="warehouseDialog.form.id ? '编辑仓库' : '新增仓库'" width="520px">
            <el-form label-width="96px">
                <el-form-item label="仓库名称" required><el-input v-model.trim="warehouseDialog.form.warehouse_name" /></el-form-item>
                <el-form-item label="仓库编码"><el-input v-model.trim="warehouseDialog.form.warehouse_code" /></el-form-item>
                <el-form-item label="负责人" required>
                    <el-select v-model="warehouseDialog.form.manager_uid" class="w-full" filterable placeholder="选择本站管理员">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                    </el-select>
                    <div class="mt-1 text-xs text-gray-400">负责仓库整体库存；未单独指定负责人的库位将继承此人。</div>
                </el-form-item>
                <el-form-item label="业务类型" required>
                    <el-select v-model="warehouseDialog.form.warehouse_type" class="w-full" @change="onWarehouseTypeChange">
                        <el-option label="二手机仓" value="owned" />
                        <el-option label="同行仓" value="peer" />
                        <el-option label="代卖仓" value="consignment" />
                        <el-option label="异常仓" value="exception" />
                    </el-select>
                </el-form-item>
                <div class="mb-4 rounded border border-gray-100 bg-gray-50 px-4 py-3 text-sm text-gray-500">
                    {{ warehouseTypeMeta(warehouseDialog.form.warehouse_type).desc }}
                </div>
                <div class="grid grid-cols-1 gap-x-4 md:grid-cols-2">
                    <el-form-item label="需要拍照"><el-switch v-model="warehouseDialog.form.need_photo" :active-value="1" :inactive-value="0" /></el-form-item>
                    <el-form-item label="需要定价"><el-switch v-model="warehouseDialog.form.need_pricing" :active-value="1" :inactive-value="0" /></el-form-item>
                    <el-form-item label="允许直售"><el-switch v-model="warehouseDialog.form.allow_direct_sale" :active-value="1" :inactive-value="0" /></el-form-item>
                    <el-form-item label="允许调拨"><el-switch v-model="warehouseDialog.form.allow_transfer" :active-value="1" :inactive-value="0" :disabled="warehouseDialog.form.warehouse_type === 'consignment'" /></el-form-item>
                </div>
                <el-form-item label="默认去向">
                    <el-select v-model="warehouseDialog.form.default_sale_target" class="w-full">
                        <el-option label="未定" value="unset" />
                        <el-option label="卖同行" value="peer" />
                        <el-option label="上商城" value="mall" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态"><el-switch v-model="warehouseDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="默认入库仓"><el-switch v-model="warehouseDialog.form.is_default" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="warehouseDialog.form.sort" :min="0" :controls="false" class="!w-[160px]" /></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="warehouseDialog.form.remark" type="textarea" :rows="2" /></el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="warehouseDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="warehouseDialog.loading" @click="submitWarehouse">保存</el-button>
            </template>
        </el-dialog>

        <el-dialog v-model="locationDialog.visible" :title="locationDialog.form.id ? '编辑库位' : '新增库位'" width="520px">
            <el-form label-width="96px">
                <el-form-item label="所属仓库">{{ locationDialog.warehouseName }}</el-form-item>
                <el-form-item label="库位名称" required><el-input v-model.trim="locationDialog.form.location_name" /></el-form-item>
                <el-form-item label="库位编码"><el-input v-model.trim="locationDialog.form.location_code" /></el-form-item>
                <el-form-item label="负责人">
                    <el-select v-model="locationDialog.form.manager_uid" class="w-full" clearable filterable placeholder="不选择则继承仓库负责人">
                        <el-option v-for="item in staffOptions" :key="item.uid" :label="staffName(item)" :value="item.uid" />
                    </el-select>
                    <div class="mt-1 text-xs text-gray-400">可指定具体库管；留空时自动由所属仓库负责人承担。</div>
                </el-form-item>
                <el-form-item label="状态"><el-switch v-model="locationDialog.form.status" :active-value="1" :inactive-value="0" /></el-form-item>
                <el-form-item label="排序"><el-input-number v-model="locationDialog.form.sort" :min="0" :controls="false" class="!w-[160px]" /></el-form-item>
                <el-form-item label="备注"><el-input v-model.trim="locationDialog.form.remark" type="textarea" :rows="2" /></el-form-item>
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
import { Plus } from '@element-plus/icons-vue'
import { getErpStaffOptions } from '@/addon/hsx_erp/api/erp'
import { deleteErpWarehouse, deleteErpWarehouseLocation, getErpWarehouseList, saveErpWarehouse, saveErpWarehouseLocation } from '@/addon/hsx_erp/api/warehouse'

const loading = ref(false)
const warehouses = ref<any[]>([])
const staffOptions = ref<any[]>([])
const warehouseDialog = reactive<any>({
    visible: false,
    loading: false,
    form: { id: 0, warehouse_name: '', warehouse_code: '', manager_uid: null, warehouse_type: 'owned', need_photo: 0, need_pricing: 0, allow_direct_sale: 1, allow_transfer: 1, default_sale_target: 'unset', status: 1, is_default: 0, sort: 0, remark: '' }
})
const locationDialog = reactive<any>({
    visible: false,
    loading: false,
    warehouseId: 0,
    warehouseName: '',
    form: { id: 0, location_name: '', location_code: '', manager_uid: null, status: 1, sort: 0, remark: '' }
})

async function loadData() {
    loading.value = true
    try {
        const [warehouseRes, staffRes]: any[] = await Promise.all([getErpWarehouseList(), getErpStaffOptions()])
        warehouses.value = Array.isArray(warehouseRes?.data) ? warehouseRes.data : []
        staffOptions.value = Array.isArray(staffRes?.data?.users) ? staffRes.data.users : []
    } finally {
        loading.value = false
    }
}

function openWarehouse(row: any = {}) {
    Object.assign(warehouseDialog.form, {
        id: Number(row.id || 0),
        warehouse_name: row.warehouse_name || '',
        warehouse_code: row.warehouse_code || '',
        manager_uid: Number(row.manager_uid || 0) || null,
        warehouse_type: row.warehouse_type || 'owned',
        need_photo: row.need_photo ?? 0,
        need_pricing: row.need_pricing ?? 0,
        allow_direct_sale: row.allow_direct_sale ?? 1,
        allow_transfer: row.allow_transfer ?? 1,
        default_sale_target: row.default_sale_target || 'unset',
        status: row.status ?? 1,
        is_default: row.is_default ?? 0,
        sort: row.sort ?? 0,
        remark: row.remark || ''
    })
    warehouseDialog.visible = true
    onWarehouseTypeChange(warehouseDialog.form.warehouse_type, false)
}

async function submitWarehouse() {
    if (!warehouseDialog.form.warehouse_name) return ElMessage.warning('请填写仓库名称')
    if (!warehouseDialog.form.manager_uid) return ElMessage.warning('请选择仓库负责人')
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

function openLocation(warehouse: any, row: any = {}) {
    locationDialog.warehouseId = Number(warehouse.id)
    locationDialog.warehouseName = warehouse.warehouse_name
    Object.assign(locationDialog.form, {
        id: Number(row.id || 0),
        location_name: row.location_name || '',
        location_code: row.location_code || '',
        manager_uid: Number(row.manager_uid || 0) || null,
        status: row.status ?? 1,
        sort: row.sort ?? 0,
        remark: row.remark || ''
    })
    locationDialog.visible = true
}

async function submitLocation() {
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

async function removeWarehouse(row: any) {
    await ElMessageBox.confirm('确定删除该仓库吗？已有库存或库位时不能删除，可改为停用。', '删除仓库', { type: 'warning' })
    await deleteErpWarehouse(Number(row.id))
    ElMessage.success('仓库已删除')
    await loadData()
}

async function removeLocation(row: any) {
    await ElMessageBox.confirm('确定删除该库位吗？已有库存时不能删除，可改为停用。', '删除库位', { type: 'warning' })
    await deleteErpWarehouseLocation(Number(row.id))
    ElMessage.success('库位已删除')
    await loadData()
}

function onWarehouseTypeChange(value: string, applyPreset = true) {
    if (value === 'owned' && applyPreset) {
        Object.assign(warehouseDialog.form, { need_photo: 1, need_pricing: 1, allow_direct_sale: 1, allow_transfer: 1, default_sale_target: 'mall' })
    }
    if (value === 'peer' && applyPreset) {
        Object.assign(warehouseDialog.form, { need_photo: 0, need_pricing: 0, allow_direct_sale: 1, allow_transfer: 1, default_sale_target: 'peer' })
    }
    if (value === 'consignment') {
        Object.assign(warehouseDialog.form, { allow_transfer: 0 })
        if (applyPreset) Object.assign(warehouseDialog.form, { need_photo: 1, need_pricing: 1, allow_direct_sale: 1, default_sale_target: 'mall' })
    }
    if (value === 'exception' && applyPreset) {
        Object.assign(warehouseDialog.form, { need_photo: 0, need_pricing: 0, allow_direct_sale: 0, allow_transfer: 0, default_sale_target: 'unset' })
    }
}

function warehouseTypeMeta(type: string) {
    const map: any = {
        owned: { label: '二手机仓', type: 'primary', desc: '自有库存仓，入库即生成采购应付，通常需要拍照定价后上商城，也可卖同行。' },
        peer: { label: '同行仓', type: 'success', desc: '自有库存仓，通常无需拍照定价，可直接同行出库，成交后按实际售价确认利润。' },
        consignment: { label: '代卖仓', type: 'warning', desc: '寄售库存，不属于自有资产，入库不应生成普通采购应付，不能直接调拨为自有库存。' },
        exception: { label: '异常仓', type: 'danger', desc: '退回、复检、争议或待处理设备，默认不允许直接销售。' }
    }
    return map[type || 'owned'] || map.owned
}

function staffName(item: any) {
    return item?.name || item?.real_name || item?.username || `员工#${item?.uid || ''}`
}

onMounted(loadData)
</script>
