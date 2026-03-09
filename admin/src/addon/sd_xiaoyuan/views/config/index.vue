<template>
    <div class="config-container">
        <el-tabs v-model="activeTab">
            <!-- 基础配置 -->
            <el-tab-pane label="基础配置" name="base">
                <el-card>
                    <el-form :model="baseConfig" label-width="150px">
                        <el-form-item label="平台名称">
                            <el-input v-model="baseConfig.platform_name" placeholder="请输入平台名称" />
                        </el-form-item>
                        <el-form-item label="订单超时时间">
                            <el-input-number v-model="baseConfig.order_timeout" :min="5" :max="60" />
                            <span style="margin-left: 10px;">分钟</span>
                        </el-form-item>
                        <el-form-item label="接单超时时间">
                            <el-input-number v-model="baseConfig.accept_timeout" :min="5" :max="120" />
                            <span style="margin-left: 10px;">分钟</span>
                        </el-form-item>
                        <el-form-item label="接单员学校限制">
                            <el-switch v-model="baseConfig.runner_school_limit" :active-value="1" :inactive-value="0" />
                            <span style="margin-left: 10px; color: #999;">开启后，接单员只能接自己学校的订单</span>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveBaseConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 费用配置 -->
            <el-tab-pane label="费用配置" name="fee">
                <el-card>
                    <el-form :model="feeConfig" label-width="150px">
                        <el-form-item label="基础费用">
                            <el-input-number v-model="feeConfig.base_fee" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元</span>
                        </el-form-item>
                        <el-form-item label="距离单价">
                            <el-input-number v-model="feeConfig.distance_price" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元/公里</span>
                        </el-form-item>
                        <el-form-item label="免费距离">
                            <el-input-number v-model="feeConfig.free_distance" :min="0" :precision="1" />
                            <span style="margin-left: 10px;">公里</span>
                        </el-form-item>
                        <el-form-item label="重量单价">
                            <el-input-number v-model="feeConfig.weight_price" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元/公斤</span>
                        </el-form-item>
                        <el-form-item label="免费重量">
                            <el-input-number v-model="feeConfig.free_weight" :min="0" :precision="1" />
                            <span style="margin-left: 10px;">公斤</span>
                        </el-form-item>
                        <el-form-item label="加急费用">
                            <el-input-number v-model="feeConfig.urgent_fee" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元</span>
                        </el-form-item>
                        <el-form-item label="默认平台抽成比例">
                            <el-input-number v-model="feeConfig.commission_rate" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%（未单独设置类型时使用此比例）</span>
                        </el-form-item>
                        <el-divider content-position="left">各类型抽成比例（留空则使用默认比例）</el-divider>
                        <el-form-item label="代取快递(EXPRESS)">
                            <el-input-number v-model="feeConfig.commission_rate_express" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="代买服务(BUY)">
                            <el-input-number v-model="feeConfig.commission_rate_buy" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="跑腿服务(ERRAND)">
                            <el-input-number v-model="feeConfig.commission_rate_errand" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="代排队(QUEUE)">
                            <el-input-number v-model="feeConfig.commission_rate_queue" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="代打印(PRINT)">
                            <el-input-number v-model="feeConfig.commission_rate_print" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="代占座(SEAT)">
                            <el-input-number v-model="feeConfig.commission_rate_seat" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="帮搬运(CARRY)">
                            <el-input-number v-model="feeConfig.commission_rate_carry" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="扔垃圾(TRASH)">
                            <el-input-number v-model="feeConfig.commission_rate_trash" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="代清洁(CLEAN)">
                            <el-input-number v-model="feeConfig.commission_rate_clean" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item label="帮帮忙(HELP)">
                            <el-input-number v-model="feeConfig.commission_rate_help" :min="0" :max="100" />
                            <span style="margin-left: 10px;">%</span>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveFeeConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 物流配置 -->
            <el-tab-pane label="物流配置" name="express">
                <el-card>
                    <el-form :model="expressConfig" label-width="150px">
                        <el-form-item label="物流接口">
                            <el-radio-group v-model="expressConfig.express_type">
                                <el-radio :label="1">快递100</el-radio>
                                <el-radio :label="2">阿里云物流查询</el-radio>
                            </el-radio-group>
                            <div class="text-gray-400 text-xs mt-1">选择物流查询接口</div>
                        </el-form-item>

                        <!-- 快递100配置 -->
                        <template v-if="expressConfig.express_type === 1">
                            <el-form-item label="AppKey">
                                <el-input v-model="expressConfig.express_app_key" placeholder="请输入快递100的AppKey" />
                                <div class="text-gray-400 text-xs mt-1">
                                    <a href="https://www.kuaidi100.com/openapi/" target="_blank" style="color: #1890ff;">获取快递100 API Key</a>
                                </div>
                            </el-form-item>
                            <el-form-item label="Customer">
                                <el-input v-model="expressConfig.express_customer" placeholder="请输入快递100的Customer" type="password" show-password />
                                <div class="text-gray-400 text-xs mt-1">快递100企业版订单号或授权编码</div>
                            </el-form-item>
                        </template>

                        <!-- 阿里云物流查询配置 -->
                        <template v-if="expressConfig.express_type === 2">
                            <el-form-item label="AppCode">
                                <el-input v-model="expressConfig.aliyun_express_appcode" placeholder="请输入阿里云AppCode" type="password" show-password />
                                <div class="text-gray-400 text-xs mt-1">
                                    <a href="https://market.aliyun.com/detail/cmapi00054243" target="_blank" style="color: #1890ff;">开通阿里云物流查询服务</a>
                                </div>
                            </el-form-item>
                        </template>

                        <el-form-item>
                            <el-button type="primary" @click="saveExpressConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 接单范围已移除，接单员只能看到自己学校的订单 -->
            <!-- 提现配置已移除，使用框架自带提现功能 -->
        </el-tabs>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getConfig, setConfig, getFeeConfig, setFeeConfig } from '@/addon/sd_xiaoyuan/api/admin'

const activeTab = ref('base')

const baseConfig = ref({
    platform_name: '校园帮',
    service_phone: '',
    service_start_time: '',
    service_end_time: '',
    order_timeout: 30,
    accept_timeout: 30,
    runner_school_limit: 0
})

const feeConfig = ref({
    base_fee: 3,
    distance_price: 1,
    free_distance: 1,
    weight_price: 2,
    free_weight: 2,
    urgent_fee: 5,
    commission_rate: 20,
    commission_rate_express: undefined as number | undefined,
    commission_rate_buy: undefined as number | undefined,
    commission_rate_errand: undefined as number | undefined,
    commission_rate_queue: undefined as number | undefined,
    commission_rate_print: undefined as number | undefined,
    commission_rate_seat: undefined as number | undefined,
    commission_rate_carry: undefined as number | undefined,
    commission_rate_trash: undefined as number | undefined,
    commission_rate_clean: undefined as number | undefined,
    commission_rate_help: undefined as number | undefined
})

const expressConfig = ref({
    express_type: 1,
    express_app_key: '',
    express_customer: '',
    aliyun_express_appcode: ''
})


onMounted(() => {
    loadConfig()
})

const loadConfig = async () => {
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            if (res.data.base) Object.assign(baseConfig.value, res.data.base)
            if (res.data.fee) Object.assign(feeConfig.value, res.data.fee)
            if (res.data.express) Object.assign(expressConfig.value, res.data.express)
        }
    } catch (e) {
        console.error(e)
    }
}

const saveBaseConfig = async () => {
    try {
        const res: any = await setConfig({ type: 'base', config: baseConfig.value })
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (e) {
        ElMessage.error('保存失败')
    }
}

const saveFeeConfig = async () => {
    try {
        const res: any = await setFeeConfig(feeConfig.value)
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (e) {
        ElMessage.error('保存失败')
    }
}

const saveExpressConfig = async () => {
    try {
        const res: any = await setConfig({ type: 'express', config: expressConfig.value })
        if (res.code === 1) {
            ElMessage.success('保存成功')
        } else {
            ElMessage.error(res.msg || '保存失败')
        }
    } catch (e) {
        ElMessage.error('保存失败')
    }
}

</script>

<style scoped lang="scss">
.config-container {
    padding: 20px;
}

.el-card {
    margin-bottom: 20px;
}
</style>
