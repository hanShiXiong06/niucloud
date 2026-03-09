<template>
    <div class="coupon-list-container">
        <!-- 操作按钮 -->
        <el-card class="action-card">
            <el-button type="primary" @click="showAddDialog">新增优惠券</el-button>
        </el-card>

        <!-- 优惠券列表 -->
        <el-card>
            <el-table :data="couponList" v-loading="loading" stripe>
                <el-table-column prop="id" label="ID" width="80" />
                <el-table-column prop="name" label="优惠券名称" min-width="150" />
                <el-table-column prop="type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ getTypeName(row.type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="discount_value" label="优惠金额/折扣" width="120">
                    <template #default="{ row }">
                        {{ row.type === 'DISCOUNT' ? row.discount_value + '折' : '¥' + row.discount_value }}
                    </template>
                </el-table-column>
                <el-table-column prop="min_amount" label="使用门槛" width="100">
                    <template #default="{ row }">
                        {{ row.min_amount > 0 ? '满' + row.min_amount + '元' : '无门槛' }}
                    </template>
                </el-table-column>
                <el-table-column prop="total_count" label="发放/领取" width="100">
                    <template #default="{ row }">
                        {{ row.receive_count }}/{{ row.total_count }}
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'" size="small">
                            {{ row.status === 1 ? '启用' : '禁用' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="end_time" label="有效期" width="180">
                    <template #default="{ row }">
                        {{ row.start_time || '-' }} ~ {{ row.end_time || '-' }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="editCoupon(row)">编辑</el-button>
                        <el-button :type="row.status === 1 ? 'warning' : 'success'" link size="small" @click="toggleStatus(row)">
                            {{ row.status === 1 ? '禁用' : '启用' }}
                        </el-button>
                        <el-button type="danger" link size="small" @click="deleteCoupon(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadCoupons"
                @current-change="loadCoupons"
            />
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑优惠券' : '新增优惠券'" width="600px">
            <el-form :model="formData" label-width="120px">
                <el-form-item label="优惠券名称" required>
                    <el-input v-model="formData.name" placeholder="请输入优惠券名称" />
                </el-form-item>
                <el-form-item label="优惠类型" required>
                    <el-radio-group v-model="formData.type">
                        <el-radio label="AMOUNT">满减券</el-radio>
                        <el-radio label="DISCOUNT">折扣券</el-radio>
                    </el-radio-group>
                </el-form-item>
                <el-form-item :label="formData.type === 'DISCOUNT' ? '折扣' : '优惠金额'" required>
                    <el-input-number v-model="formData.discount_value" :min="0" :precision="formData.type === 'DISCOUNT' ? 1 : 2" />
                    <span style="margin-left: 10px;">{{ formData.type === 'DISCOUNT' ? '折' : '元' }}</span>
                </el-form-item>
                <el-form-item label="使用门槛">
                    <el-input-number v-model="formData.min_amount" :min="0" :precision="2" />
                    <span style="margin-left: 10px;">元（0表示无门槛）</span>
                </el-form-item>
                <el-form-item label="发放数量" required>
                    <el-input-number v-model="formData.total_count" :min="1" />
                </el-form-item>
                <el-form-item label="每人限领">
                    <el-input-number v-model="formData.limit_per_user" :min="1" />
                    <span style="margin-left: 10px;">张</span>
                </el-form-item>
                <el-form-item label="有效期" required>
                    <el-date-picker
                        v-model="formData.date_range"
                        type="datetimerange"
                        range-separator="至"
                        start-placeholder="开始时间"
                        end-placeholder="结束时间"
                        value-format="X"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" @click="saveCoupon">保存</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getCouponList, addCoupon, editCoupon as editCouponApi, deleteCoupon as deleteCouponApi, setCouponStatus } from '@/addon/sd_xiaoyuan/api/admin'

const loading = ref(false)
const couponList = ref<any[]>([])
const dialogVisible = ref(false)
const isEdit = ref(false)

const formData = ref({
    id: 0,
    name: '',
    type: 'AMOUNT',
    discount_value: 0,
    min_amount: 0,
    total_count: 100,
    limit_per_user: 1,
    date_range: []
})

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

const typeMap: Record<string, string> = {
    'AMOUNT': '满减券',
    'DISCOUNT': '折扣券'
}

onMounted(() => {
    loadCoupons()
})

const loadCoupons = async () => {
    loading.value = true
    try {
        const res = await getCouponList({
            page: pagination.value.page,
            limit: pagination.value.limit
        })
        if (res.code === 1) {
            couponList.value = res.data.list
            pagination.value.total = res.data.count
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const getTypeName = (type: string) => typeMap[type] || type


const showAddDialog = () => {
    isEdit.value = false
    formData.value = {
        id: 0,
        name: '',
        type: 'AMOUNT',
        discount_value: 0,
        min_amount: 0,
        total_count: 100,
        limit_per_user: 1,
        date_range: []
    }
    dialogVisible.value = true
}

const editCoupon = (row: any) => {
    isEdit.value = true
    formData.value = {
        id: row.id,
        name: row.name,
        type: row.type,
        discount_value: row.discount_value,
        min_amount: row.min_amount,
        total_count: row.total_count,
        limit_per_user: row.limit_per_user,
        date_range: [row.start_time, row.end_time]
    }
    dialogVisible.value = true
}

const saveCoupon = async () => {
    if (!formData.value.name) {
        ElMessage.warning('请输入优惠券名称')
        return
    }
    
    const data: any = {
        ...formData.value,
        start_time: formData.value.date_range?.[0] || 0,
        end_time: formData.value.date_range?.[1] || 0
    }
    delete data.date_range
    
    try {
        const res = isEdit.value ? await editCouponApi(data) : await addCoupon(data)
        if (res.code === 1) {
            ElMessage.success('保存成功')
            dialogVisible.value = false
            loadCoupons()
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const toggleStatus = async (row: any) => {
    try {
        const res = await setCouponStatus({ id: row.id, status: row.status === 1 ? 0 : 1 })
        if (res.code === 1) {
            ElMessage.success('操作成功')
            loadCoupons()
        } else {
            ElMessage.error(res.msg || '操作失败')
        }
    } catch (e) {
        ElMessage.error('操作失败')
    }
}

const deleteCoupon = (row: any) => {
    ElMessageBox.confirm('确定要删除该优惠券吗？', '提示', { type: 'warning' }).then(async () => {
        try {
            const res = await deleteCouponApi({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('删除成功')
                loadCoupons()
            } else {
                ElMessage.error(res.msg || '删除失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}
</script>

<style scoped lang="scss">
.coupon-list-container {
    padding: 20px;
}

.action-card {
    margin-bottom: 20px;
}

.el-pagination {
    margin-top: 20px;
    justify-content: flex-end;
}
</style>
