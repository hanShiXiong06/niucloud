<template>
    <div class="group-order-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="拼单类型">
                    <el-select v-model="searchForm.group_type" placeholder="全部类型" clearable>
                        <el-option v-for="(name, key) in typeList" :key="key" :label="name" :value="key" />
                    </el-select>
                </el-form-item>
                <el-form-item label="状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option v-for="(name, key) in statusList" :key="key" :label="name" :value="Number(key)" />
                    </el-select>
                </el-form-item>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable>
                        <el-option v-for="school in schoolList" :key="school.id" :label="school.name" :value="school.id" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="loadList">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表 -->
        <el-card>
            <el-table :data="list" v-loading="loading" stripe>
                <el-table-column prop="group_no" label="拼单编号" width="180" />
                <el-table-column prop="group_type" label="类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ typeList[row.group_type] || row.group_type }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="title" label="标题" min-width="200" show-overflow-tooltip />
                <el-table-column prop="current_members" label="人数" width="120">
                    <template #default="{ row }">
                        {{ row.current_members }}/{{ row.max_members }}
                    </template>
                </el-table-column>
                <el-table-column prop="per_price" label="人均价格" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.per_price }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ statusList[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="deadline" label="截止时间" width="180">
                    <template #default="{ row }">
                        {{ row.deadline || '-' }}
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="发起时间" width="180">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="100" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadList"
                @current-change="loadList"
            />
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="detailVisible" title="拼单详情" width="800px">
            <el-descriptions :column="2" border v-if="currentGroup">
                <el-descriptions-item label="拼单编号">{{ currentGroup.group_no }}</el-descriptions-item>
                <el-descriptions-item label="拼单类型">{{ typeList[currentGroup.group_type] }}</el-descriptions-item>
                <el-descriptions-item label="标题" :span="2">{{ currentGroup.title }}</el-descriptions-item>
                <el-descriptions-item label="商家名称">{{ currentGroup.shop_name || '-' }}</el-descriptions-item>
                <el-descriptions-item label="配送地址">{{ currentGroup.delivery_address || '-' }}</el-descriptions-item>
                <el-descriptions-item label="当前人数">{{ currentGroup.current_members }}/{{ currentGroup.max_members }}</el-descriptions-item>
                <el-descriptions-item label="最少人数">{{ currentGroup.min_members }}</el-descriptions-item>
                <el-descriptions-item label="人均价格">¥{{ currentGroup.per_price }}</el-descriptions-item>
                <el-descriptions-item label="总价格">¥{{ currentGroup.total_price }}</el-descriptions-item>
                <el-descriptions-item label="状态">
                    <el-tag :type="getStatusType(currentGroup.status)">{{ statusList[currentGroup.status] }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="截止时间">{{ currentGroup.deadline || '-' }}</el-descriptions-item>
                <el-descriptions-item label="描述" :span="2">{{ currentGroup.content || '-' }}</el-descriptions-item>
            </el-descriptions>
            
            <div class="mt-4" v-if="currentGroup?.members?.length">
                <h4>参与成员</h4>
                <el-table :data="currentGroup.members" stripe size="small">
                    <el-table-column prop="member_id" label="用户ID" width="100" />
                    <el-table-column prop="is_leader" label="角色" width="80">
                        <template #default="{ row }">
                            <el-tag :type="row.is_leader ? 'warning' : 'info'" size="small">
                                {{ row.is_leader ? '团长' : '成员' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                    <el-table-column prop="order_content" label="点单内容" min-width="150" />
                    <el-table-column prop="amount" label="金额" width="100">
                        <template #default="{ row }">
                            ¥{{ row.amount }}
                        </template>
                    </el-table-column>
                    <el-table-column prop="pay_status" label="支付状态" width="100">
                        <template #default="{ row }">
                            <el-tag :type="row.pay_status ? 'success' : 'warning'" size="small">
                                {{ row.pay_status ? '已支付' : '未支付' }}
                            </el-tag>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { getGroupOrderList, getGroupOrderInfo, getGroupOrderTypeList, getGroupOrderStatusList } from '@/addon/sd_xiaoyuan/api/groupOrder'
import { getAllSchools } from '@/addon/sd_xiaoyuan/api/school'

const loading = ref(false)
const list = ref<any[]>([])
const typeList = ref<Record<string, string>>({})
const statusList = ref<Record<number, string>>({})
const schoolList = ref<any[]>([])
const pagination = reactive({
    page: 1,
    limit: 10,
    total: 0
})

const searchForm = reactive({
    group_type: '',
    status: '',
    school_id: ''
})

const detailVisible = ref(false)
const currentGroup = ref<any>(null)

onMounted(() => {
    loadTypeList()
    loadStatusList()
    loadSchoolList()
    loadList()
})

const loadTypeList = async () => {
    try {
        const res: any = await getGroupOrderTypeList()
        typeList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadStatusList = async () => {
    try {
        const res: any = await getGroupOrderStatusList()
        statusList.value = res.data || {}
    } catch (e) {
        console.error(e)
    }
}

const loadSchoolList = async () => {
    try {
        const res: any = await getAllSchools()
        schoolList.value = res.data || []
    } catch (e) {
        console.error(e)
    }
}

const loadList = async () => {
    loading.value = true
    try {
        const res: any = await getGroupOrderList({
            ...searchForm,
            page: pagination.page,
            limit: pagination.limit
        })
        list.value = res.data?.data || []
        pagination.total = res.data?.total || 0
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const handleReset = () => {
    searchForm.group_type = ''
    searchForm.status = ''
    searchForm.school_id = ''
    pagination.page = 1
    loadList()
}

const getStatusType = (status: number) => {
    if (status === 30) return 'success'
    if (status >= 90) return 'info'
    if (status >= 10) return 'warning'
    return 'primary'
}


const viewDetail = async (row: any) => {
    try {
        const res: any = await getGroupOrderInfo(row.id)
        currentGroup.value = res.data
        detailVisible.value = true
    } catch (e) {
        console.error(e)
    }
}
</script>

<style lang="scss" scoped>
.group-order-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.text-price {
    color: #f56c6c;
    font-weight: bold;
}

.mt-4 {
    margin-top: 20px;
}
</style>
