<template>
    <el-drawer v-model="visible" title="库位责任分配" size="620px" @open="loadAll">
        <div v-loading="loading" class="laa">
            <el-alert
                v-if="loaded && !erpConnected"
                type="warning"
                :closable="false"
                show-icon
                title="未检测到 ERP 仓库"
                description="库位来自 ERP，未接入或暂无仓库时无法分配。请先在 ERP 中创建仓库与库位。"
                class="mb-4"
            />

            <template v-else>
                <div class="laa-tip">
                    把库位分配给员工后：员工在移动端只看自己负责库位里的设备，管理员看全部。
                    一个库位可分给多人，一个人也可负责多个库位。
                </div>

                <el-select
                    v-model="currentWarehouseId"
                    placeholder="选择仓库"
                    class="laa-wh-select"
                    @change="onWarehouseChange"
                >
                    <el-option
                        v-for="w in warehouses"
                        :key="w.id"
                        :label="warehouseLabel(w)"
                        :value="w.id"
                    />
                </el-select>

                <el-table v-if="currentLocations.length" :data="currentLocations" border class="laa-table">
                    <el-table-column prop="location_name" label="库位" width="170" />
                    <el-table-column label="负责员工">
                        <template #default="{ row }">
                            <el-select
                                v-model="assignMap[row.id]"
                                multiple
                                collapse-tags
                                collapse-tags-tooltip
                                filterable
                                placeholder="选择负责员工（可多选）"
                                class="laa-staff-select"
                                :loading="savingId === row.id"
                                @change="(val) => saveLocation(row, val)"
                            >
                                <el-option v-for="s in staff" :key="s.uid" :label="s.label" :value="s.uid" />
                            </el-select>
                        </template>
                    </el-table-column>
                </el-table>

                <el-empty v-else-if="currentWarehouseId" description="该仓库暂无库位" />
                <el-empty v-else description="请选择仓库后分配库位负责人" />
            </template>
        </div>
    </el-drawer>
</template>

<script lang="ts" setup>
import { computed, reactive, ref } from 'vue'
import { ElMessage } from 'element-plus'
import {
    getAssignWarehouseTree,
    getAssignStaffOptions,
    getAssignList,
    setLocationStaff
} from '@/addon/hsx_device_asset/api/device_asset'

interface LocationItem { id: number; location_name: string }
interface WarehouseItem { id: number; warehouse_name: string; business_type?: string; is_default?: number; locations?: LocationItem[] }
interface StaffItem { uid: number; label: string }

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits(['update:modelValue'])

const visible = computed({
    get: () => props.modelValue,
    set: (v: boolean) => emit('update:modelValue', v)
})

const loading = ref(false)
const loaded = ref(false)
const erpConnected = ref(false)
const warehouses = ref<WarehouseItem[]>([])
const staff = ref<StaffItem[]>([])
const currentWarehouseId = ref<number | ''>('')
const savingId = ref<number | null>(null)
// location_id -> uid[]
const assignMap = reactive<Record<number, number[]>>({})

const WH_TYPE_LABEL: Record<string, string> = { mall: '商城', peer: '同行', scrap: '报废', hold: '暂存' }
const warehouseLabel = (w: WarehouseItem) =>
    `${ w.warehouse_name }${ w.business_type ? ' · ' + (WH_TYPE_LABEL[w.business_type] || w.business_type) : '' }`

const currentLocations = computed<LocationItem[]>(() => {
    const w = warehouses.value.find((item) => item.id === currentWarehouseId.value)
    return w?.locations || []
})

const loadAll = async () => {
    loading.value = true
    try {
        const [treeRes, staffRes, assignRes]: any = await Promise.all([
            getAssignWarehouseTree(),
            getAssignStaffOptions(),
            getAssignList()
        ])
        warehouses.value = treeRes.data?.warehouses || []
        erpConnected.value = !!treeRes.data?.erp_connected
        staff.value = staffRes.data || []

        // 预填每个库位的负责员工
        Object.keys(assignMap).forEach((k) => delete assignMap[Number(k)])
        for (const a of (assignRes.data || [])) {
            const lid = Number(a.location_id)
            if (!assignMap[lid]) assignMap[lid] = []
            assignMap[lid].push(Number(a.uid))
        }

        // 默认选中默认仓 / 首个仓
        if (!currentWarehouseId.value && warehouses.value.length) {
            const def = warehouses.value.find((w) => Number(w.is_default) === 1) || warehouses.value[0]
            currentWarehouseId.value = def.id
        }
    } catch (e) {
        // 请求层已提示
    } finally {
        loaded.value = true
        loading.value = false
    }
}

const onWarehouseChange = () => { /* 切换仓库仅切换展示，数据已全量加载 */ }

const saveLocation = async (row: LocationItem, uids: number[]) => {
    savingId.value = row.id
    try {
        await setLocationStaff({
            warehouse_id: currentWarehouseId.value || 0,
            location_id: row.id,
            uids
        })
        ElMessage.success(`「${ row.location_name }」负责人已更新`)
    } catch (e) {
        // 失败回滚为重新加载
        await loadAll()
    } finally {
        savingId.value = null
    }
}
</script>

<style lang="scss" scoped>
.laa-tip {
    margin-bottom: 16px;
    padding: 10px 12px;
    background: var(--el-fill-color-lighter);
    border-radius: 8px;
    font-size: 13px;
    line-height: 1.6;
    color: var(--el-text-color-secondary);
}

.laa-wh-select {
    width: 100%;
    margin-bottom: 16px;
}

.laa-staff-select {
    width: 100%;
}

.laa-table {
    width: 100%;
}
</style>
