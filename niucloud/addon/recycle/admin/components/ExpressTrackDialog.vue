<template>
    <el-dialog
        v-model="visibleProxy"
        :title="title"
        width="600px"
        :close-on-click-modal="false"
        destroy-on-close
    >
        <div v-loading="loading" class="express-track-dialog">
            <div v-if="expressInfo" class="express-info-container">
                <div class="express-header">
                    <div class="express-header-main">
                        <div>
                            <h3>{{ currentCompanyName }}</h3>
                            <p>运单号：{{ currentMailNo }}</p>
                        </div>
                        <el-tag :type="getExpressStatusType(expressInfo.logisticsStatus)" size="large">
                            {{ expressInfo.logisticsStatusDesc || '未知状态' }}
                        </el-tag>
                    </div>
                    <div class="express-latest">
                        <p>最新状态：{{ expressInfo.theLastMessage || '-' }}</p>
                        <p>更新时间：{{ expressInfo.theLastTime || '-' }}</p>
                    </div>
                </div>

                <div class="express-trace">
                    <h4>物流轨迹</h4>
                    <el-timeline>
                        <el-timeline-item
                            v-for="(item, index) in expressInfo.logisticsTraceDetailList"
                            :key="index"
                            :timestamp="item.timeDesc"
                            :type="index === 0 ? 'primary' : 'info'"
                            :size="index === 0 ? 'large' : 'normal'"
                        >
                            <div class="trace-item">
                                <div class="trace-location">{{ item.areaName }}</div>
                                <div class="trace-desc">{{ item.desc }}</div>
                            </div>
                        </el-timeline-item>
                    </el-timeline>
                </div>
            </div>
            <el-empty v-else :description="emptyDescription" :image-size="90" />
        </div>

        <template #footer>
            <el-button @click="visibleProxy = false">关闭</el-button>
            <el-button type="primary" :loading="loading" @click="reloadTrack">刷新状态</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { ElMessage } from 'element-plus'
import { getExpress } from '@/addon/recycle/api/device_query_api'

interface ExpressTraceItem {
    timeDesc?: string
    areaName?: string
    desc?: string
}

interface ExpressTrackInfo {
    logisticsCompanyName?: string
    mailNo?: string
    logisticsStatus?: string
    logisticsStatusDesc?: string
    theLastMessage?: string
    theLastTime?: string
    logisticsTraceDetailList?: ExpressTraceItem[]
    [key: string]: any
}

const props = withDefaults(defineProps<{
    visible: boolean
    expressNo: string
    mobile?: string
    companyName?: string
    title?: string
    emptyDescription?: string
}>(), {
    mobile: '',
    companyName: '快递公司',
    title: '快递物流信息',
    emptyDescription: '暂无快递信息'
})

const emit = defineEmits<{
    'update:visible': [value: boolean]
}>()

const loading = ref(false)
const expressInfo = ref<ExpressTrackInfo | null>(null)

const visibleProxy = computed({
    get: () => props.visible,
    set: (value: boolean) => emit('update:visible', value)
})

const currentCompanyName = computed(() => expressInfo.value?.logisticsCompanyName || props.companyName || '快递公司')
const currentMailNo = computed(() => expressInfo.value?.mailNo || props.expressNo || '-')

const getExpressStatusType = (status?: string) => {
    const statusMap: Record<string, string> = {
        ACCEPT: 'info',
        TRANSPORT: 'warning',
        DELIVER: 'primary',
        SIGN: 'success',
        REJECT: 'danger',
        EXCEPTION: 'danger'
    }
    return statusMap[status || ''] || 'info'
}

const reloadTrack = async () => {
    if (!props.expressNo) {
        expressInfo.value = null
        return
    }

    const mobileLast4 = String(props.mobile || '').slice(-4)
    if (!mobileLast4) {
        expressInfo.value = null
        ElMessage.warning('无法获取手机号后四位，无法查询快递信息')
        return
    }

    loading.value = true
    try {
        const res = await getExpress(props.expressNo, mobileLast4)
        const data = res.data?.data || res.data || null
        if (!data?.logisticsTraceDetailList?.length) {
            expressInfo.value = null
            return
        }
        expressInfo.value = data
    } catch (error: any) {
        expressInfo.value = null
        ElMessage.error(error.message || '查询运单状态失败')
    } finally {
        loading.value = false
    }
}

watch(
    () => [props.visible, props.expressNo, props.mobile],
    ([visible]) => {
        if (visible) {
            void reloadTrack()
        }
    },
    { immediate: true }
)
</script>

<style scoped lang="scss">
.express-track-dialog {
    min-height: 180px;
}

.express-info-container {
    max-height: min(68vh, 620px);
    overflow-y: auto;
    padding-right: 6px;
}

.express-header {
    border-bottom: 1px solid #ebeef5;
    padding-bottom: 16px;
}

.express-header-main {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
}

.express-header-main h3 {
    margin: 0;
    color: #303133;
    font-size: 18px;
    font-weight: 600;
    line-height: 1.4;
}

.express-header-main p,
.express-latest p {
    margin: 4px 0 0;
    color: #606266;
    font-size: 13px;
    line-height: 1.5;
}

.express-latest {
    margin-top: 12px;
}

.express-trace {
    margin-top: 16px;
}

.express-trace h4 {
    margin: 0 0 12px;
    color: #303133;
    font-size: 15px;
    font-weight: 600;
}

.trace-item .trace-location {
    margin-bottom: 4px;
    color: #303133;
    font-weight: 500;
}

.trace-item .trace-desc {
    color: #606266;
    font-size: 14px;
    line-height: 1.5;
}
</style>
