<template>
    <div class="main-container">
        <el-card class="box-card !border-none" shadow="never">
            <div class="flex justify-between items-center mb-[16px]">
                <div>
                    <span class="text-page-title">库位责任</span>
                    <div class="text-xs text-gray-400 mt-1">把库位/分类指派给员工。员工只看见、只能操作自己负责库位里的设备；管理员看全部。</div>
                </div>
                <el-button :loading="loading" @click="loadAll">刷新</el-button>
            </div>

            <el-empty v-if="!loading && !warehouses.length" description="暂无仓库，请先在「仓库与库位」创建仓库与库位" />

            <div v-for="w in warehouses" :key="w.id" class="wh-block">
                <div class="wh-head">
                    <div>
                        <span class="wh-name">{{ w.warehouse_name }}</span>
                        <el-tag size="small" effect="plain" class="ml-[8px]">{{ businessTypeLabel(w.business_type) }}</el-tag>
                    </div>
                    <el-button size="small" type="primary" link :disabled="!(w.locations || []).length" @click="openWarehouseAssign(w)">整仓指派</el-button>
                </div>
                <el-table :data="w.locations || []" size="small" border>
                    <el-table-column prop="location_name" label="库位/分类" min-width="160" />
                    <el-table-column label="负责人" min-width="280">
                        <template #default="{ row }">
                            <template v-if="(staffOf(row.id) || []).length">
                                <el-tag v-for="uid in staffOf(row.id)" :key="uid" size="small" class="mr-[6px] mb-[4px]">{{ staffName(uid) }}</el-tag>
                            </template>
                            <span v-else class="text-gray-400 text-xs">未指派（仅管理员可见）</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="操作" width="120" align="center">
                        <template #default="{ row }">
                            <el-button size="small" type="primary" link @click="openLocationAssign(w, row)">设置负责人</el-button>
                        </template>
                    </el-table-column>
                    <template #empty>
                        <span class="text-gray-400 text-xs">该仓暂无库位，请先在「仓库与库位」添加库位</span>
                    </template>
                </el-table>
            </div>
        </el-card>

        <!-- 单库位指派 -->
        <el-dialog v-model="locDialog.visible" title="设置库位负责人" width="460px">
            <div class="mb-[10px] text-sm text-gray-500">{{ locDialog.warehouseName }} / {{ locDialog.locationName }}</div>
            <el-select v-model="locDialog.uids" multiple filterable class="w-full" placeholder="选择负责人（可多选）">
                <el-option v-for="s in staffOptions" :key="s.uid" :label="s.label" :value="s.uid" />
            </el-select>
            <template #footer>
                <el-button @click="locDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveLocation">保存</el-button>
            </template>
        </el-dialog>

        <!-- 整仓指派：把某员工加到本仓全部库位（合并，不影响他人） -->
        <el-dialog v-model="whDialog.visible" title="整仓指派" width="460px">
            <div class="mb-[10px] text-sm text-gray-500">{{ whDialog.warehouseName }} · 共 {{ whDialog.locationIds.length }} 个库位</div>
            <el-select v-model="whDialog.uids" multiple filterable class="w-full" placeholder="选择负责人，将加入该仓全部库位">
                <el-option v-for="s in staffOptions" :key="s.uid" :label="s.label" :value="s.uid" />
            </el-select>
            <template #footer>
                <el-button @click="whDialog.visible = false">取消</el-button>
                <el-button type="primary" :loading="saving" @click="saveWarehouse">应用到全仓</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import { getAssignTree, getAssignStaffOptions, getAssignList, setLocationStaff } from '@/addon/hsx_erp/api/location_assign'

const loading = ref(false)
const saving = ref(false)
const warehouses = ref<any[]>([])
const staffOptions = ref<any[]>([])
const assignMap = reactive<Record<number, number[]>>({})

const businessTypeMap: Record<string, string> = {
    mall: '二手机仓(商城)', peer: '同行仓', consignment: '代卖仓', hold: '暂存仓', scrap: '报废仓'
}
const businessTypeLabel = (v: string) => businessTypeMap[v] || '二手机仓(商城)'
const staffName = (uid: number) => staffOptions.value.find(s => s.uid === uid)?.label || ('员工#' + uid)
const staffOf = (locationId: number) => assignMap[locationId] || []

const loadAll = async () => {
    loading.value = true
    try {
        const [treeRes, staffRes, listRes]: any = await Promise.all([
            getAssignTree(), getAssignStaffOptions(), getAssignList()
        ])
        warehouses.value = treeRes.data?.warehouses || []
        staffOptions.value = staffRes.data || []
        Object.keys(assignMap).forEach(k => delete assignMap[Number(k)])
        for (const row of (listRes.data || [])) {
            const lid = Number(row.location_id)
            if (!assignMap[lid]) assignMap[lid] = []
            if (!assignMap[lid].includes(Number(row.uid))) assignMap[lid].push(Number(row.uid))
        }
    } finally {
        loading.value = false
    }
}

const locDialog = reactive({ visible: false, warehouseId: 0, locationId: 0, warehouseName: '', locationName: '', uids: [] as number[] })
const openLocationAssign = (w: any, loc: any) => {
    locDialog.warehouseId = Number(w.id)
    locDialog.locationId = Number(loc.id)
    locDialog.warehouseName = w.warehouse_name
    locDialog.locationName = loc.location_name
    locDialog.uids = [...(assignMap[Number(loc.id)] || [])]
    locDialog.visible = true
}
const saveLocation = async () => {
    saving.value = true
    try {
        await setLocationStaff(locDialog.locationId, { warehouse_id: locDialog.warehouseId, uids: locDialog.uids })
        ElMessage.success('已保存')
        locDialog.visible = false
        await loadAll()
    } finally {
        saving.value = false
    }
}

const whDialog = reactive({ visible: false, warehouseId: 0, warehouseName: '', locationIds: [] as number[], uids: [] as number[] })
const openWarehouseAssign = (w: any) => {
    whDialog.warehouseId = Number(w.id)
    whDialog.warehouseName = w.warehouse_name
    whDialog.locationIds = (w.locations || []).map((l: any) => Number(l.id))
    whDialog.uids = []
    whDialog.visible = true
}
// 把选中的员工合并进本仓每个库位（保留各库位已有负责人）
const saveWarehouse = async () => {
    if (!whDialog.uids.length) { ElMessage.warning('请选择负责人'); return }
    saving.value = true
    try {
        for (const lid of whDialog.locationIds) {
            const merged = Array.from(new Set([...(assignMap[lid] || []), ...whDialog.uids]))
            await setLocationStaff(lid, { warehouse_id: whDialog.warehouseId, uids: merged })
        }
        ElMessage.success('已应用到全仓')
        whDialog.visible = false
        await loadAll()
    } finally {
        saving.value = false
    }
}

onMounted(loadAll)
</script>

<style lang="scss" scoped>
.wh-block { margin-bottom: 22px; }
.wh-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.wh-name { font-size: 15px; font-weight: 600; color: #1f2937; }
</style>
