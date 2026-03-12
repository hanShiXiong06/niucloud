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

            <!-- 功能开关 -->
            <el-tab-pane label="功能开关" name="features">
                <el-card>
                    <el-alert
                        title="功能开关配置"
                        description="关闭后，前端将不显示对应功能的入口和菜单"
                        type="info"
                        :closable="false"
                        style="margin-bottom: 20px;"
                    />
                    <el-form :model="featureConfig" label-width="150px">
                        <el-form-item label="任务类型功能">
                            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                <el-checkbox v-model="featureConfig.enable_buy" :true-label="1" :false-label="0">帮我买</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_send" :true-label="1" :false-label="0">帮我送</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_express" :true-label="1" :false-label="0">代取快递</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_print" :true-label="1" :false-label="0">帮打印</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_trash" :true-label="1" :false-label="0">扔垃圾</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_carry" :true-label="1" :false-label="0">帮搬运</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_clean" :true-label="1" :false-label="0">代清洁</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_help" :true-label="1" :false-label="0">帮帮忙</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_game" :true-label="1" :false-label="0">游戏陪练</el-checkbox>
                            </div>
                        </el-form-item>
                        
                        <el-form-item label="其他功能">
                            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                <el-checkbox v-model="featureConfig.enable_house" :true-label="1" :false-label="0">房屋租赁</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_schedule" :true-label="1" :false-label="0">课程表</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_group" :true-label="1" :false-label="0">拼单好饭</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_secondhand" :true-label="1" :false-label="0">闲置市场</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_lost_found" :true-label="1" :false-label="0">失物招领</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_community" :true-label="1" :false-label="0">校园树洞</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_confession" :true-label="1" :false-label="0">表白墙</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_sign" :true-label="1" :false-label="0">每日签到</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_points_mall" :true-label="1" :false-label="0">积分商城</el-checkbox>
                            </div>
                        </el-form-item>
                        
                        <el-form-item label="认证设置">
                            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                <el-checkbox v-model="featureConfig.require_auth_publish" :true-label="1" :false-label="0">发布需要实名认证</el-checkbox>
                            </div>
                            <div class="text-gray-400 text-xs mt-1">关闭后，用户发布内容时不需要进行实名认证，个人中心的实名认证入口也会隐藏</div>
                        </el-form-item>
                        
                        <el-form-item label="关闭提示文字">
                            <el-input v-model="featureConfig.close_text" placeholder="请输入功能关闭时的提示文字" style="width: 400px;" />
                            <div class="text-gray-400 text-xs mt-1">当功能关闭时，页面显示的提示文字</div>
                        </el-form-item>
                        
                        <el-form-item>
                            <el-button type="primary" @click="saveFeatureConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>
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

const featureConfig = ref({
    enable_buy: 1,
    enable_send: 1,
    enable_express: 1,
    enable_print: 1,
    enable_trash: 1,
    enable_carry: 1,
    enable_clean: 1,
    enable_help: 1,
    enable_game: 1,
    enable_house: 1,
    enable_schedule: 1,
    enable_group: 1,
    enable_secondhand: 1,
    enable_lost_found: 1,
    enable_community: 1,
    enable_confession: 1,
    enable_sign: 1,
    enable_points_mall: 1,
    close_text: '功能已下架',
    require_auth_publish: 1
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
            if (res.data.features) Object.assign(featureConfig.value, res.data.features)
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

const saveFeatureConfig = async () => {
    try {
        const res: any = await setConfig({ type: 'features', config: featureConfig.value })
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
