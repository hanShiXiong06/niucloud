<template>
    <div class="order-list-container">
        <!-- 搜索区域 -->
        <el-card class="search-card">
            <el-form :model="searchForm" inline>
                <el-form-item label="学校">
                    <el-select v-model="searchForm.school_id" placeholder="全部学校" clearable filterable>
                        <el-option v-for="s in schoolList" :key="s.id" :label="s.name" :value="s.id" />
                    </el-select>
                </el-form-item>
                <el-form-item label="订单号">
                    <el-input v-model="searchForm.order_no" placeholder="请输入订单号" clearable />
                </el-form-item>
                <el-form-item label="订单状态">
                    <el-select v-model="searchForm.status" placeholder="全部状态" clearable>
                        <el-option label="待支付" :value="0" />
                        <el-option label="待接单" :value="10" />
                        <el-option label="已接单" :value="20" />
                        <el-option label="取货中" :value="30" />
                        <el-option label="配送中" :value="40" />
                        <el-option label="用户确认" :value="45" />
                        <el-option label="已完成" :value="50" />
                        <el-option label="已取消" :value="90" />
                        <el-option label="已退款" :value="91" />
                    </el-select>
                </el-form-item>
                <el-form-item label="服务类型">
                    <el-select v-model="searchForm.task_type" placeholder="全部类型" clearable>
                        <el-option label="代取快递" value="EXPRESS" />
                        <el-option label="代买服务" value="BUY" />
                        <el-option label="跑腿服务" value="ERRAND" />
                        <el-option label="代排队" value="QUEUE" />
                        <el-option label="代上课" value="CLASS" />
                        <el-option label="代打印" value="PRINT" />
                        <el-option label="代占座" value="SEAT" />
                        <el-option label="扔垃圾" value="TRASH" />
                        <el-option label="帮搬运" value="CARRY" />
                        <el-option label="代清洁" value="CLEAN" />
                        <el-option label="帮帮忙" value="HELP" />
                        <el-option label="拼单好饭" value="GROUP" />
                        <el-option label="帮我送" value="SEND" />
                        <el-option label="游戏陪玩" value="GAME" />
                        <el-option label="兼职招聘" value="PARTTIME" />
                        <el-option label="约伴组局" value="COMPANION" />
                    </el-select>
                </el-form-item>
                <el-form-item label="下单时间">
                    <el-date-picker
                        v-model="searchForm.date_range"
                        type="daterange"
                        range-separator="至"
                        start-placeholder="开始日期"
                        end-placeholder="结束日期"
                        value-format="YYYY-MM-DD"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">搜索</el-button>
                    <el-button @click="handleReset">重置</el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 统计卡片 -->
        <el-row :gutter="20" class="stat-row">
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value">{{ stat.total || 0 }}</div>
                    <div class="stat-label">总订单</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value pending">{{ stat.pending || 0 }}</div>
                    <div class="stat-label">待接单</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value processing">{{ stat.processing || 0 }}</div>
                    <div class="stat-label">进行中</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value completed">{{ stat.completed || 0 }}</div>
                    <div class="stat-label">已完成</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value">¥{{ stat.total_amount || 0 }}</div>
                    <div class="stat-label">总金额</div>
                </el-card>
            </el-col>
            <el-col :span="4">
                <el-card class="stat-card">
                    <div class="stat-value income">¥{{ stat.platform_income || 0 }}</div>
                    <div class="stat-label">平台收益</div>
                </el-card>
            </el-col>
        </el-row>

        <!-- 订单列表 -->
        <el-card>
            <el-table :data="orderList" v-loading="loading" stripe>
                <el-table-column prop="order_no" label="订单号" width="180" />
                <el-table-column prop="school_name" label="学校" width="120" />
                <el-table-column label="用户信息" width="130">
                    <template #default="{ row }">
                        <div>{{ row.member_nickname || '—' }}</div>
                        <div class="text-gray">编号：{{ row.member_no || row.member_id || '—' }}</div>
                    </template>
                </el-table-column>
                <el-table-column prop="task_type" label="服务类型" width="100">
                    <template #default="{ row }">
                        <el-tag size="small">{{ getTaskTypeName(row.task_type) }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="服务信息" min-width="250">
                    <template #default="{ row }">
                        <div v-if="row.receive_address" class="text-gray">送达：{{ row.receive_address }}</div>
                        <div v-if="row.goods_name" class="text-gray">商品：{{ row.goods_name }}</div>
                        <div v-if="row.ext_data && Object.keys(row.ext_data).length">
                            <template v-for="(val, key) in row.ext_data" :key="key">
                                <div v-if="key !== 'images' && key !== 'files' && key !== 'gender_limit' && key !== 'delivery_images' && key !== 'proof_images' && key !== 'goods_name' && val" class="text-gray" style="margin-top:2px;">
                                    <span style="font-weight:500;">{{ extLabelMap[key] || key }}：</span>{{ formatExtValue(key, val) }}
                                </div>
                            </template>
                        </div>
                        <div v-if="row.task_desc" class="text-gray">{{ row.task_desc }}</div>
                    </template>
                </el-table-column>
                <el-table-column label="取件信息" min-width="200">
                    <template #default="{ row }">
                        <template v-if="row.task_type !== 'QUEUE' && row.task_type !== 'SEAT' && row.task_type !== 'CLASS'">
                            <div v-if="row.pickup_name || row.pickup_mobile">{{ row.pickup_name }} {{ row.pickup_mobile }}</div>
                            <div class="text-gray">{{ row.pickup_address }}</div>
                        </template>
                        <template v-else-if="row.task_type === 'QUEUE'">
                            <div v-if="row.ext_data?.queue_location">排队地点：{{ row.ext_data.queue_location }}</div>
                            <div v-if="row.ext_data?.queue_time" class="text-gray">排队时间：{{ row.ext_data.queue_time }}</div>
                        </template>
                        <template v-else-if="row.task_type === 'CLASS'">
                            <div v-if="row.ext_data?.class_subject">课程：{{ row.ext_data.class_subject }}</div>
                            <div v-if="row.ext_data?.class_location" class="text-gray">地点：{{ row.ext_data.class_location }}</div>
                        </template>
                        <template v-else-if="row.task_type === 'SEAT'">
                            <div v-if="row.ext_data?.location_detail">位置：{{ row.ext_data.location_detail }}</div>
                            <div v-if="row.ext_data?.seat_count" class="text-gray">座位数：{{ row.ext_data.seat_count }}个</div>
                        </template>
                    </template>
                </el-table-column>
                <el-table-column label="图片" width="100">
                    <template #default="{ row }">
                        <el-image v-if="row.goods_image" :src="img(row.goods_image)" style="width:50px;height:50px;" fit="cover" :preview-src-list="[img(row.goods_image)]" />
                        <template v-else-if="row.ext_data?.images && Array.isArray(row.ext_data.images) && row.ext_data.images.length">
                            <el-image :src="img(row.ext_data.images[0])" style="width:50px;height:50px;" fit="cover" :preview-src-list="row.ext_data.images.map((i: string) => img(i))" />
                        </template>
                        <span v-else class="text-gray">-</span>
                    </template>
                </el-table-column>
                <el-table-column prop="actual_fee" label="实付金额" width="100">
                    <template #default="{ row }">
                        <span class="text-price">¥{{ row.actual_fee }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="status" label="状态" width="100">
                    <template #default="{ row }">
                        <el-tag :type="getStatusType(row.status)" size="small">
                            {{ getStatusName(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="接单员" width="120">
                    <template #default="{ row }">
                        <span v-if="row.runner_name">{{ row.runner_name }}</span>
                        <span v-else class="text-gray">暂无</span>
                    </template>
                </el-table-column>
                <el-table-column prop="create_time" label="下单时间" width="160">
                    <template #default="{ row }">
                        {{ (row.create_time) }}
                    </template>
                </el-table-column>
                <el-table-column label="操作" width="150" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" link size="small" @click="viewDetail(row)">详情</el-button>
                        <el-button type="warning" link size="small" v-if="row.status === 10" @click="openAssignDialog(row)">指派</el-button>
                        <el-button type="success" link size="small" v-if="row.status === 45" @click="confirmComplete(row)">确认完成</el-button>
                        <el-button type="danger" link size="small" v-if="row.status < 50" @click="cancelOrder(row)">取消</el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                @size-change="loadOrders"
                @current-change="loadOrders"
            />
        </el-card>

        <!-- 订单详情弹窗 -->
        <el-dialog v-model="detailVisible" title="订单详情" width="800px">
            <el-descriptions :column="2" border v-if="currentOrder">
                <el-descriptions-item label="订单号">{{ currentOrder.order_no }}</el-descriptions-item>
                <el-descriptions-item label="服务类型">{{ getTaskTypeName(currentOrder.task_type) }}</el-descriptions-item>
                <el-descriptions-item label="用户昵称">{{ currentOrder.member_nickname || '—' }}</el-descriptions-item>
                <el-descriptions-item label="会员编号">{{ currentOrder.member_no || currentOrder.member_id || '—' }}</el-descriptions-item>
                <el-descriptions-item label="送达地址" v-if="currentOrder.receive_address" :span="2">{{ currentOrder.receive_address }}</el-descriptions-item>
                <el-descriptions-item label="服务信息" :span="2">
                    <div v-if="currentOrder.goods_name">商品：{{ currentOrder.goods_name }}</div>
                    <div v-if="currentOrder.ext_data && Object.keys(currentOrder.ext_data).length" style="margin-top:4px;">
                        <template v-for="(val, key) in currentOrder.ext_data" :key="key">
                            <div v-if="key !== 'images' && key !== 'files' && key !== 'gender_limit' && key !== 'delivery_images' && key !== 'proof_images' && key !== 'goods_name' && val" style="margin-bottom:2px;">
                                <span style="font-weight:500;">{{ extLabelMap[key] || key }}：</span>{{ formatExtValue(key, val) }}
                            </div>
                        </template>
                    </div>
                </el-descriptions-item>
                <el-descriptions-item label="取件人" v-if="currentOrder.task_type !== 'QUEUE' && currentOrder.task_type !== 'SEAT' && currentOrder.task_type !== 'CLASS'">{{ currentOrder.pickup_name }} {{ currentOrder.pickup_mobile }}</el-descriptions-item>
                <el-descriptions-item label="取件地址" v-if="currentOrder.task_type !== 'QUEUE' && currentOrder.task_type !== 'SEAT' && currentOrder.task_type !== 'CLASS'">{{ currentOrder.pickup_address }}</el-descriptions-item>
                <el-descriptions-item label="排队地点" v-if="currentOrder.task_type === 'QUEUE' && currentOrder.ext_data?.queue_location">{{ currentOrder.ext_data.queue_location }}</el-descriptions-item>
                <el-descriptions-item label="排队时间" v-if="currentOrder.task_type === 'QUEUE' && currentOrder.ext_data?.queue_time">{{ currentOrder.ext_data.queue_time }}</el-descriptions-item>
                <el-descriptions-item label="课程名称" v-if="currentOrder.task_type === 'CLASS' && currentOrder.ext_data?.class_subject">{{ currentOrder.ext_data.class_subject }}</el-descriptions-item>
                <el-descriptions-item label="上课地点" v-if="currentOrder.task_type === 'CLASS' && currentOrder.ext_data?.class_location">{{ currentOrder.ext_data.class_location }}</el-descriptions-item>
                <el-descriptions-item label="上课时间" v-if="currentOrder.task_type === 'CLASS' && currentOrder.ext_data?.class_time">{{ currentOrder.ext_data.class_time }}</el-descriptions-item>
                <el-descriptions-item label="占座位置" v-if="currentOrder.task_type === 'SEAT' && currentOrder.ext_data?.location_detail">{{ currentOrder.ext_data.location_detail }}</el-descriptions-item>
                <el-descriptions-item label="座位数量" v-if="currentOrder.task_type === 'SEAT' && currentOrder.ext_data?.seat_count">{{ currentOrder.ext_data.seat_count }}个</el-descriptions-item>
                <el-descriptions-item label="订单金额">¥{{ currentOrder.total_fee }}</el-descriptions-item>
                <el-descriptions-item label="实付金额">¥{{ currentOrder.actual_fee }}</el-descriptions-item>
                <el-descriptions-item label="接单员收益">¥{{ currentOrder.runner_income || 0 }}</el-descriptions-item>
                <el-descriptions-item label="平台收益">¥{{ currentOrder.platform_fee || 0 }}</el-descriptions-item>
                <el-descriptions-item label="订单状态">
                    <el-tag :type="getStatusType(currentOrder.status)">{{ getStatusName(currentOrder.status) }}</el-tag>
                </el-descriptions-item>
                <el-descriptions-item label="接单员">{{ currentOrder.runner_name || '暂无' }}</el-descriptions-item>
                <el-descriptions-item label="下单时间">{{ currentOrder.create_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="完成时间">{{ currentOrder.complete_time || '-' }}</el-descriptions-item>
                <el-descriptions-item label="任务描述" :span="2">{{ currentOrder.task_desc || '-' }}</el-descriptions-item>
                <el-descriptions-item label="备注" :span="2">{{ currentOrder.remark || '-' }}</el-descriptions-item>
                <el-descriptions-item label="隐私信息" :span="2" v-if="currentOrder.yinsi_text">{{ currentOrder.yinsi_text }}</el-descriptions-item>
                <el-descriptions-item label="图片" :span="2" v-if="currentOrder.goods_image || (currentOrder.ext_data?.images && Array.isArray(currentOrder.ext_data.images) && currentOrder.ext_data.images.length)">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <el-image v-if="currentOrder.goods_image" :src="img(currentOrder.goods_image)" style="width:80px;height:80px;" fit="cover" :preview-src-list="[img(currentOrder.goods_image)]" />
                        <template v-if="currentOrder.ext_data?.images && Array.isArray(currentOrder.ext_data.images) && currentOrder.ext_data.images.length">
                            <el-image v-for="(imgUrl, i) in currentOrder.ext_data.images" :key="i" :src="img(imgUrl)" style="width:80px;height:80px;" fit="cover" :preview-src-list="currentOrder.ext_data.images.map((url: string) => img(url))" />
                        </template>
                    </div>
                </el-descriptions-item>
                <el-descriptions-item label="任务凭证" :span="2" v-if="currentOrder.status >= 40 && currentOrder.ext_data?.delivery_images && Array.isArray(currentOrder.ext_data.delivery_images) && currentOrder.ext_data.delivery_images.length">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <el-image v-for="(imgUrl, i) in currentOrder.ext_data.delivery_images" :key="i" :src="img(imgUrl)" style="width:80px;height:80px;" fit="cover" :preview-src-list="currentOrder.ext_data.delivery_images.map((url: string) => img(url))" />
                    </div>
                </el-descriptions-item>
                <el-descriptions-item label="完成凭证" :span="2" v-if="currentOrder.status === 50 && currentOrder.ext_data?.proof_images && Array.isArray(currentOrder.ext_data.proof_images) && currentOrder.ext_data.proof_images.length">
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <el-image v-for="(imgUrl, i) in currentOrder.ext_data.proof_images" :key="i" :src="img(imgUrl)" style="width:80px;height:80px;" fit="cover" :preview-src-list="currentOrder.ext_data.proof_images.map((url: string) => img(url))" />
                    </div>
                </el-descriptions-item>
            </el-descriptions>
        </el-dialog>

        <!-- 指派接单员弹窗 -->
        <el-dialog v-model="assignVisible" title="指派接单员" width="600px">
            <el-input
                v-model="runnerKeyword"
                placeholder="搜索接单员姓名或手机号"
                clearable
                @input="searchRunners"
                style="margin-bottom: 16px;"
            >
                <template #prefix>
                    <el-icon><Search /></el-icon>
                </template>
            </el-input>
            <div v-if="onlineRunners.length === 0 && !runnerLoading" style="text-align:center;padding:40px 0;color:#999;">
                暂无在线接单员
            </div>
            <el-table :data="onlineRunners" v-loading="runnerLoading" max-height="400">
                <el-table-column prop="real_name" label="姓名" width="100" />
                <el-table-column prop="mobile" label="手机号" width="140" />
                <el-table-column prop="score" label="评分" width="80">
                    <template #default="{ row }">
                        <span style="color:#ff9500;">{{ row.score }}</span>
                    </template>
                </el-table-column>
                <el-table-column prop="today_orders" label="今日单量" width="90" />
                <el-table-column prop="complete_orders" label="累计完成" width="90" />
                <el-table-column label="操作" width="100">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" @click="confirmAssign(row)">指派</el-button>
                    </template>
                </el-table-column>
            </el-table>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { getOrderList, getOrderStat, cancelOrder as cancelOrderApi, confirmCompleteOrder, assignRunner as assignRunnerApi, getOnlineRunners, getAllSchools } from '@/addon/sd_xiaoyuan/api/admin'
import { Search } from '@element-plus/icons-vue'
import { img } from '@/utils/common'

const loading = ref(false)
const orderList = ref<any[]>([])
const stat = ref<any>({})
const detailVisible = ref(false)
const currentOrder = ref<any>(null)

const searchForm = ref({
    school_id: '',
    order_no: '',
    status: '',
    task_type: '',
    date_range: []
})

const schoolList = ref<any[]>([])

const loadSchools = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolList.value = res.data || []
    }
}

const pagination = ref({
    page: 1,
    limit: 10,
    total: 0
})

const taskTypeMap: Record<string, string> = {
    'EXPRESS': '代取快递',
    'BUY': '代买服务',
    'ERRAND': '跑腿服务',
    'QUEUE': '代排队',
    'CLASS': '代上课',
    'PRINT': '代打印',
    'SEAT': '代占座',
    'TRASH': '扔垃圾',
    'CARRY': '帮搬运',
    'CLEAN': '代清洁',
    'HELP': '帮帮忙',
    'GROUP': '拼单',
    'SEND': '帮送服务',
    'GAME': '游戏陪玩',
    'PARTTIME': '兼职招聘',
    'COMPANION': '约伴组局'
}

const extLabelMap: Record<string, string> = {
    'goods_name': '商品名称',
    'goods_desc': '商品描述',
    'help_type': '帮忙类型',
    'gender_limit': '性别限制',
    'address_id': '地址ID',
    'group_type': '拼单类型',
    'title': '拼单标题',
    'shop_name': '店铺名称',
    'min_members': '最少人数',
    'max_members': '最多人数',
    'current_members': '当前人数',
    'per_price': '人均价格',
    'delivery_fee': '配送费',
    'delivery_address': '配送地址',
    'deadline': '截止时间',
    'order_content': '点单内容',
    'content': '详细说明',
    'expect_time': '期望时间',
    'quick_tags': '快捷标签',
    'station_id': '驿站ID',
    'station_name': '驿站名称',
    'packages': '包裹数',
    'pickup_code': '取件码',
    'trash_desc': '垃圾描述',
    'files': '文件',
    'page_count': '页数',
    'print_side': '单双面',
    'print_color': '彩色',
    'paper_size': '纸张大小',
    'clean_type': '清洁类型',
    'area': '面积',
    'appointment_time': '预约时间',
    'remark': '备注',
    'images': '图片',
    'game_type': '游戏类型',
    'game_name': '游戏名称',
    'rank_level': '段位等级',
    'service_type': '服务类型',
    'voice_chat': '语音聊天',
    'online_time': '在线时间',
    'unit': '单位',
    'item_type': '物品类型',
    'task_desc': '任务描述',
    'location_type': '位置类型',
    'queue_location': '排队地点',
    'queue_purpose': '排队目的',
    'estimated_duration': '预计时长',
    'queue_time': '排队时间',
    'class_subject': '课程名称',
    'class_location': '上课地点',
    'building_type': '教学楼类型',
    'class_duty_text': '服务内容',
    'class_duration': '课程时长',
    'class_time': '上课时间',
    'location_detail': '位置详情',
    'seat_count': '座位数量',
    'start_time': '开始时间',
    'duration': '时长',
    'job_type': '岗位类型',
    'salary': '薪资说明',
    'work_time': '工作时间',
    'location': '地点',
    'contact': '联系方式',
    'recruit_count': '招聘人数',
    'activity_type': '活动类型',
    'activity_time': '活动时间',
    'people_count': '人数需求',
    'budget': '费用说明'
}

const gameTypeMap: Record<string, string> = {
    'WZRY': '王者荣耀',
    'LOL': '英雄联盟',
    'YS': '原神',
    'PUBG': '和平精英',
    'OTHER': '其他'
}

const serviceTypeMap: Record<string, string> = {
    'PLAY_WITH': '陪玩',
    'CARRY': '代练',
    'TEACH': '教学'
}

const genderMap: Record<string, string> = {
    'male': '仅限男性',
    'female': '仅限女性',
    'MALE': '仅限男性',
    'FEMALE': '仅限女性',
    'any': '不限',
    'ANY': '不限'
}

const helpTypeMap: Record<string, string> = {
    'ONLINE': '线上帮忙',
    'OFFLINE': '线下帮忙'
}

const cleanTypeMap: Record<string, string> = {
    'DORM': '宿舍清洁',
    'OFFICE': '办公室清洁',
    'OTHER': '其他'
}

const groupTypeMap: Record<string, string> = {
    'FRUIT': '水果拼单',
    'TEA': '奶茶拼单',
    'FOOD': '外卖拼单',
    'OTHER': '其他拼单'
}

const locationTypeMap: Record<string, string> = {
    'SERVICE': '服务窗口',
    'CANTEEN': '食堂',
    'LIBRARY': '图书馆',
    'STUDY_ROOM': '自习室',
    'CLASSROOM': '教室',
    'OTHER': '其他'
}

const packageSizeMap: Record<string, string> = {
    'small': '小件',
    'medium': '中件',
    'large': '大件',
    'extra_large': '特大件'
}

const formatExtValue = (key: string, val: any) => {
    if (key === 'game_type') return gameTypeMap[val] || val
    if (key === 'service_type') return serviceTypeMap[val] || val
    if (key === 'gender_limit') return genderMap[val] || val
    if (key === 'help_type') return helpTypeMap[val] || val
    if (key === 'clean_type') return cleanTypeMap[val] || val
    if (key === 'group_type') return groupTypeMap[val] || val
    if (key === 'location_type') return locationTypeMap[val] || val
    if (key === 'voice_chat') return val == 1 || val === '1' ? '是' : '否'
    if (key === 'deadline' && typeof val === 'number') {
        return new Date(val * 1000).toLocaleString('zh-CN')
    }
    if (key === 'duration' && typeof val === 'number') {
        return `${val}分钟`
    }
    if (key === 'estimated_duration' && typeof val === 'number') {
        return `${val}分钟`
    }
    if (key === 'packages' && Array.isArray(val)) {
        return val.map((p: any) => `${packageSizeMap[p.size] || p.size || ''}(${p.quantity || 1}件)`).join('、')
    }
    if (Array.isArray(val)) return val.join('、')
    return val
}

const parseExt = (ext: string) => {
    try {
        return typeof ext === 'string' ? JSON.parse(ext) : ext
    } catch (e) {
        return {}
    }
}

const statusMap: Record<number, string> = {
    0: '待支付',
    10: '待接单',
    20: '已接单',
    30: '取货中',
    40: '配送中',
    45: '用户确认',
    50: '已完成',
    90: '已取消',
    91: '已退款'
}

onMounted(() => {
    loadSchools()
    loadOrders()
    loadStat()
})

const loadOrders = async () => {
    loading.value = true
    try {
        const params: any = {
            page: pagination.value.page,
            limit: pagination.value.limit,
            ...searchForm.value
        }
        
        if (searchForm.value.date_range?.length === 2) {
            params.start_date = searchForm.value.date_range[0]
            params.end_date = searchForm.value.date_range[1]
        }
        
        const res = await getOrderList(params)
        if (res.code === 1) {
            orderList.value = (res.data.list || []).map((item: any) => {
                if (item.ext && !item.ext_data) {
                    item.ext_data = parseExt(item.ext)
                }
                return item
            })
            pagination.value.total = res.data.count
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

const loadStat = async () => {
    try {
        const res = await getOrderStat()
        if (res.code === 1) {
            stat.value = res.data
        }
    } catch (e) {
        console.error(e)
    }
}

const handleSearch = () => {
    pagination.value.page = 1
    loadOrders()
}

const handleReset = () => {
    searchForm.value = {
        school_id: '',
        order_no: '',
        status: '',
        task_type: '',
        date_range: []
    }
    handleSearch()
}

const getTaskTypeName = (type: string) => taskTypeMap[type] || type
const getStatusName = (status: number) => statusMap[status] || '未知'

const getStatusType = (status: number) => {
    if (status === 0) return 'warning'
    if (status === 10) return 'info'
    if (status >= 20 && status < 50) return 'primary'
    if (status === 50) return 'success'
    return 'danger'
}


const viewDetail = (row: any) => {
    // Parse ext_data if not already parsed
    if (row.ext && !row.ext_data) {
        try {
            row.ext_data = typeof row.ext === 'string' ? JSON.parse(row.ext) : row.ext
        } catch (e) {
            row.ext_data = {}
        }
    }
    currentOrder.value = row
    detailVisible.value = true
}

// 指派接单员
const assignVisible = ref(false)
const assignOrderId = ref(0)
const assignSchoolId = ref(0)
const runnerKeyword = ref('')
const onlineRunners = ref<any[]>([])
const runnerLoading = ref(false)
let searchTimer: any = null

const openAssignDialog = (row: any) => {
    assignOrderId.value = row.id
    assignSchoolId.value = row.school_id || 0
    runnerKeyword.value = ''
    assignVisible.value = true
    searchRunners()
}

const searchRunners = (val?: string) => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(async () => {
        runnerLoading.value = true
        try {
            const res = await getOnlineRunners({ keyword: runnerKeyword.value, school_id: assignSchoolId.value })
            if (res.code === 1) {
                onlineRunners.value = res.data.list || []
            }
        } catch (e) {
            console.error(e)
        } finally {
            runnerLoading.value = false
        }
    }, 300)
}

const confirmAssign = (runner: any) => {
    ElMessageBox.confirm(
        `确定将订单指派给 ${runner.real_name}（${runner.mobile}）吗？`,
        '确认指派',
        { confirmButtonText: '确定', cancelButtonText: '取消', type: 'warning' }
    ).then(async () => {
        try {
            const res = await assignRunnerApi({ order_id: assignOrderId.value, runner_id: runner.id })
            if (res.code === 1) {
                ElMessage.success('指派成功')
                assignVisible.value = false
                loadOrders()
                loadStat()
            } else {
                ElMessage.error(res.msg || '指派失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}

const cancelOrder = (row: any) => {
    ElMessageBox.confirm('确定要取消该订单吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            const res = await cancelOrderApi({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('取消成功')
                loadOrders()
                loadStat()
            } else {
                ElMessage.error(res.msg || '取消失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}

const confirmComplete = (row: any) => {
    ElMessageBox.confirm('确认将该订单直接完成吗？', '提示', {
        confirmButtonText: '确定',
        cancelButtonText: '取消',
        type: 'warning'
    }).then(async () => {
        try {
            const res = await confirmCompleteOrder({ id: row.id })
            if (res.code === 1) {
                ElMessage.success('确认完成成功')
                loadOrders()
                loadStat()
            } else {
                ElMessage.error(res.msg || '操作失败')
            }
        } catch (e) {
            ElMessage.error('操作失败')
        }
    }).catch(() => {})
}
</script>

<style scoped lang="scss">
.order-list-container {
    padding: 20px;
}

.search-card {
    margin-bottom: 20px;
}

.stat-row {
    margin-bottom: 20px;
}

.stat-card {
    text-align: center;
    
    .stat-value {
        font-size: 28px;
        font-weight: bold;
        color: #333;
        
        &.pending { color: #e6a23c; }
        &.processing { color: #409eff; }
        &.completed { color: #67c23a; }
        &.income { color: #ff6b00; }
    }
    
    .stat-label {
        font-size: 14px;
        color: #999;
        margin-top: 8px;
    }
}

.text-gray {
    color: #999;
    font-size: 12px;
}

.text-price {
    color: #ff6b00;
    font-weight: bold;
}

.el-pagination {
    margin-top: 20px;
    justify-content: flex-end;
}
</style>
