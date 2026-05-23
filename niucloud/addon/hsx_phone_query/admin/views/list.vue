<template>
    <div class="list-page">
        <!-- 搜索区域 -->
        <el-card class="filter-card">
            <el-form :inline="true" :model="searchData" class="demo-form-inline">
                <el-form-item label="序列号">
                    <el-input v-model="searchData.keyword" placeholder="请输入序列号" clearable></el-input>
                </el-form-item>
                <el-form-item label="查询时间">
                    <el-date-picker v-model="searchData.dateRange" type="daterange" range-separator="至"
                        start-placeholder="开始日期" end-placeholder="结束日期" value-format="YYYY-MM-DD"
                        :shortcuts="dateShortcuts"></el-date-picker>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">查询</el-button>
                    <el-button @click="resetSearch">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表区域 -->
        <el-card class="list-card">
            <el-table v-loading="loading" :data="tableData" style="width: 100%" border>
                <el-table-column type="index" label="序号" width="60"></el-table-column>
                <el-table-column prop="sn" label="序列号" min-width="180"></el-table-column>
                <el-table-column prop="type_name" label="查询类型" width="120"></el-table-column>
                <el-table-column label="查询状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="row.is_look ? 'success' : 'warning'">
                            {{ row.is_look ? '已读' : '未读' }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="设备信息" min-width="200">
                    <template #default="{ row }">
                        <div v-if="row.info">
                            <div>{{ row.info.机型 || '-' }}</div>
                            <div v-if="row.info.容量 || row.info.颜色">
                                {{ row.info.容量 }} {{ row.info.颜色 ? `· ${row.info.颜色}` : '' }}
                            </div>
                        </div>
                    </template>
                </el-table-column>
                <el-table-column prop="pay_type" label="支付方式" width="120" />

                <el-table-column prop="money" label="支付金额" width="120" />

                <el-table-column prop="cost_money" label="三方成本" width="120" />

                <el-table-column prop="profit_money" label="利润" width="120" />

                <el-table-column label="渠道" width="140">
                    <template #default="{ row }">
                        {{ row.channel_name || row.provider_name || '-' }}
                    </template>
                </el-table-column>

                <el-table-column label="缓存/通知" width="140">
                    <template #default="{ row }">
                        <el-tag size="small" :type="row.from_cache_count > 0 ? 'success' : 'info'">
                            {{ row.from_cache_count > 0 ? '缓存' : '实时' }}
                        </el-tag>
                        <el-tag size="small" class="ml-2" :type="row.notice_status == 1 ? 'success' : row.notice_status == 2 ? 'danger' : 'info'">
                            {{ row.notice_status == 1 ? '已通知' : row.notice_status == 2 ? '通知失败' : '未通知' }}
                        </el-tag>
                    </template>
                </el-table-column>


                <el-table-column prop="create_time" label="查询时间" width="180"></el-table-column>
                <el-table-column label="查询人" width="180">
                    <template #default="{ row }">
                        <div>
                            <div>{{ row.member_info.nickname || row.member_info.username }}</div>

                        </div>
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="120" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link @click="handleView(row)">查看</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
            <div class="pagination-container">
                <el-pagination v-model:current-page="page" v-model:page-size="limit" :page-sizes="[10, 20, 50, 100]"
                    :total="total" layout="total, sizes, prev, pager, next, jumper" @size-change="handleSizeChange"
                    @current-change="handleCurrentChange"></el-pagination>
            </div>
        </el-card>

        <!-- 详情弹窗 -->
        <el-dialog v-model="dialogVisible" title="查询详情" width="600px" destroy-on-close>
            <div v-if="detailData" class="detail-content">
                <div class="detail-item">
                    <span class="label">序列号：</span>
                    <span class="value">{{ detailData.sn }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">查询类型：</span>
                    <span class="value">{{ detailData.type_name }}</span>
                </div>
                <div class="detail-item">
                    <span class="label">查询时间：</span>
                    <span class="value">{{ detailData.create_time }}</span>
                </div>
                <div class="detail-info" v-if="detailData.info">
                    <div class="info-title">设备信息</div>
                    <div v-for="(value, key) in detailData.info" :key="key" class="info-item">
                        <span class="label">{{ key }}：</span>
                        <span class="value">{{ value }}</span>
                    </div>
                </div>
            </div>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { getHsxPhoneQueryList, getHsxPhoneQueryInfo } from '@/addon/hsx_phone_query/api/hsx_phone_query'
import { ElMessage } from 'element-plus'

// 搜索相关
const searchData = ref({
    keyword: '',
    dateRange: []
})

// 日期快捷选项
const dateShortcuts = [
    {
        text: '最近一周',
        value: () => {
            const end = new Date()
            const start = new Date()
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 7)
            return [start, end]
        }
    },
    {
        text: '最近一个月',
        value: () => {
            const end = new Date()
            const start = new Date()
            start.setTime(start.getTime() - 3600 * 1000 * 24 * 30)
            return [start, end]
        }
    }
]

// 表格数据
const loading = ref(false)
const tableData = ref([])
const page = ref(1)
const limit = ref(10)
const total = ref(0)

// 获取列表数据
const getList = async () => {
    loading.value = true
    try {
        const params = {
            page: page.value,
            limit: limit.value,
            keyword: searchData.value.keyword,
            start_time: searchData.value.dateRange?.[0] || '',
            end_time: searchData.value.dateRange?.[1] || ''
        }
        const res = await getHsxPhoneQueryList(params)
        if (res.code === 1) {
            tableData.value = res.data.list
            total.value = res.data.count
        }
    } catch (error) {
        console.error('获取列表失败:', error)
        ElMessage.error('获取列表失败')
    } finally {
        loading.value = false
    }
}

// 搜索
const handleSearch = () => {
    page.value = 1
    getList()
}

// 重置搜索
const resetSearch = () => {
    searchData.value = {
        keyword: '',
        dateRange: []
    }
    handleSearch()
}

// 分页
const handleSizeChange = (val: number) => {
    limit.value = val
    getList()
}

const handleCurrentChange = (val: number) => {
    page.value = val
    getList()
}

// 详情相关
const dialogVisible = ref(false)
const detailData = ref(null)

const handleView = async (row: any) => {
    try {
        const res = await getHsxPhoneQueryInfo(row.id)
        if (res.code === 1) {
            detailData.value = res.data
            dialogVisible.value = true
        }
    } catch (error) {
        console.error('获取详情失败:', error)
        ElMessage.error('获取详情失败')
    }
}

onMounted(() => {
    getList()
})
</script>

<style lang="scss" scoped>
.list-page {
    padding: 20px;

    .filter-card {
        margin-bottom: 20px;
    }

    .list-card {
        .pagination-container {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
        }
    }
}

.detail-content {
    .detail-item {
        margin-bottom: 16px;

        .label {
            color: #666;
            margin-right: 8px;
        }

        .value {
            color: #333;
        }
    }

    .detail-info {
        border-top: 1px solid #eee;
        padding-top: 16px;
        margin-top: 16px;

        .info-title {
            font-weight: bold;
            margin-bottom: 16px;
        }

        .info-item {
            margin-bottom: 12px;

            .label {
                color: #666;
                margin-right: 8px;
            }

            .value {
                color: #333;
            }
        }
    }
}
</style>
