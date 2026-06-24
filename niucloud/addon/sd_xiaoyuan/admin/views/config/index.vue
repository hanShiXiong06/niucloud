<template>
    <div class="config-container">
        <el-tabs v-model="activeTab">
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
                                <el-checkbox v-model="featureConfig.enable_parttime" :true-label="1" :false-label="0">兼职招聘</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_companion" :true-label="1" :false-label="0">约伴组局</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_queue" :true-label="1" :false-label="0">代排队</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_class" :true-label="1" :false-label="0">代上课</el-checkbox>
                                <el-checkbox v-model="featureConfig.enable_seat" :true-label="1" :false-label="0">代占座</el-checkbox>
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
                        
                        <el-form-item label="审核设置">
                            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                <el-checkbox v-model="featureConfig.campus_auth_auto_approve" :true-label="1" :false-label="0">校园认证自动审核通过</el-checkbox>
                                <el-checkbox v-model="featureConfig.community_auto_approve" :true-label="1" :false-label="0">树洞自动审核通过</el-checkbox>
                                <el-checkbox v-model="featureConfig.confession_auto_approve" :true-label="1" :false-label="0">表白墙自动审核通过</el-checkbox>
                                <el-checkbox v-model="featureConfig.house_auto_approve" :true-label="1" :false-label="0">房屋租赁自动审核通过</el-checkbox>
                                <el-checkbox v-model="featureConfig.secondhand_auto_approve" :true-label="1" :false-label="0">闲置市场自动审核通过</el-checkbox>
                                <el-checkbox v-model="featureConfig.lost_found_auto_approve" :true-label="1" :false-label="0">失物招领自动审核通过</el-checkbox>
                            </div>
                            <div class="text-gray-400 text-xs mt-1">开启后，用户发布对应内容或提交校园认证时自动审核通过，无需后台人工审核</div>
                        </el-form-item>
                        
                        <el-form-item label="界面设置">
                            <div style="display: flex; flex-wrap: wrap; gap: 20px;">
                                <el-checkbox v-model="featureConfig.enable_custom_tabbar" :true-label="1" :false-label="0">显示自定义底部导航</el-checkbox>
                            </div>
                            <div class="text-gray-400 text-xs mt-1">关闭后，将使用框架默认的底部导航（需在后台装修-底部导航中配置）</div>
                        </el-form-item>
                        
                        <el-form-item label="关闭提示文字">
                            <el-input v-model="featureConfig.close_text" placeholder="请输入功能关闭时的提示文字" style="width: 400px;" />
                            <div class="text-gray-400 text-xs mt-1">当功能关闭时，页面显示的提示文字</div>
                        </el-form-item>
                        
                        <el-form-item label="前端名称设置">
                            <div style="display: flex; flex-wrap: wrap; gap: 16px;">
                                <el-input v-model="featureConfig.secondhand_name" placeholder="闲置市场名称" style="width: 260px;" />
                                <el-input v-model="featureConfig.community_name" placeholder="树洞名称" style="width: 260px;" />
                            </div>
                            <div class="text-gray-400 text-xs mt-1">可自定义前端显示名称，留空将分别默认显示“闲置市场”和“校园树洞”</div>
                        </el-form-item>
                        
                        <el-form-item>
                            <el-button type="primary" @click="saveFeatureConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>
                </el-card>
            </el-tab-pane>

            <!-- 基础配置 -->
            <el-tab-pane label="基础配置" name="base">
                <el-card>
                    <el-form :model="baseConfig" label-width="150px">
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
                        <el-form-item label="接单完成奖励积分">
                            <el-input-number v-model="baseConfig.runner_complete_point" :min="0" :max="100000" />
                            <span style="margin-left: 10px;">分（0 表示不发放）</span>
                        </el-form-item>
                        <el-form-item label="自动确认收货天数">
                            <el-input-number v-model="baseConfig.auto_confirm_days" :min="0" :max="365" />
                            <span style="margin-left: 10px;">天（0 表示不自动确认）</span>
                        </el-form-item>
                        <el-form-item label="未认证显示任务大厅">
                            <el-switch v-model="baseConfig.unauth_show_order_hall" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="单学校模式">
                            <el-switch v-model="baseConfig.single_school" :active-value="1" :inactive-value="0" />
                            <span style="margin-left: 10px; color: #999;">开启后全站固定使用下方默认学校，各模块按该校筛选</span>
                        </el-form-item>
                        <el-form-item label="默认学校" v-if="baseConfig.single_school == 1">
                            <el-select v-model="baseConfig.default_school_id" placeholder="请选择默认学校" filterable clearable style="width: 320px;">
                                <el-option v-for="item in schoolOptions" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
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
                        <el-form-item label="帮我送默认跑腿费">
                            <el-input-number v-model="feeConfig.base_fee" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元</span>
                            <div class="text-gray-400 text-xs mt-1">帮我送、扔垃圾、帮搬运、代清洁等下单页默认跑腿费</div>
                        </el-form-item>
                        <el-form-item label="帮我买默认跑腿费">
                            <el-input-number v-model="feeConfig.buy_fee" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元</span>
                            <div class="text-gray-400 text-xs mt-1">帮我买下单页默认跑腿费（此前前端写死2元，现由此配置）</div>
                        </el-form-item>
                        <!-- 以下四项：后台 calculateFee 当前写死阈值与单价，未读配置，先隐藏避免误以为生效 -->
                        <!--
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
                        -->
                        <el-form-item label="加急费用">
                            <el-input-number v-model="feeConfig.urgent_fee" :min="0" :precision="2" />
                            <span style="margin-left: 10px;">元</span>
                            <div class="text-gray-400 text-xs mt-1">帮我送等开启加急时的附加费用</div>
                        </el-form-item>
                        <el-alert type="info" :closable="false" style="margin-bottom: 16px;">
                            接单员分成、按任务类型的接单员比例请在「接单员管理 → 等级配置」中设置（表内为接单员所得%，平台抽成=100%-该比例）。此处不再维护「默认平台抽成」「各类型抽成」，避免与等级配置重复；历史站点已保存的旧值仍保留在数据库，仅隐藏表单项。
                        </el-alert>
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

            <!-- 公众号通知 -->
            <el-tab-pane label="公众号通知" name="notice">
                <el-card>
                    <el-form :model="noticeConfig" label-width="180px">
                        <el-form-item label="开启公众号推送">
                            <el-switch v-model="noticeConfig.enable_wechat_notice" :active-value="1" :inactive-value="0" />
                            <div class="text-gray-400 text-xs mt-1">关闭后不会发送任何公众号模板消息</div>
                        </el-form-item>
                        <el-form-item v-if="!noticeConfig.enable_wechat_notice">
                            <el-button type="primary" @click="saveNoticeConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>

                  
                    <el-form v-if="noticeConfig.enable_wechat_notice" :model="noticeConfig" label-width="180px" style="margin-top: 20px;">
                        <el-form-item label="新订单通知接单员">
                            <el-input v-model="noticeConfig.wechat_tpl_order_pay_runner" placeholder="粘贴私有模板ID" style="width: 480px;" clearable />
                            <div class="text-gray-400 text-xs mt-1">支付成功通知同校接单员，需4个字段见上表</div>
                        </el-form-item>
                        <el-form-item label="骑手入驻通知管理员">
                            <el-input v-model="noticeConfig.wechat_tpl_runner_apply" placeholder="粘贴私有模板ID" style="width: 480px;" clearable />
                            <div class="text-gray-400 text-xs mt-1">通知站点管理员，需3个字段见上表</div>
                        </el-form-item>
                        <el-form-item label="审核管理员会员ID">
                            <el-input v-model="noticeConfig.wechat_runner_apply_admin_ids" placeholder="多个用英文逗号分隔，如：12,45" style="width: 480px;" clearable />
                            <div class="text-gray-400 text-xs mt-1">填会员ID，须微信H5授权登录过；有人申请入驻时推送给这些人审核</div>
                        </el-form-item>
                        <el-form-item label="跑手已接单提醒">
                            <el-input v-model="noticeConfig.wechat_tpl_order_accept_user" placeholder="粘贴私有模板ID" style="width: 480px;" clearable />
                            <div class="text-gray-400 text-xs mt-1">通知下单用户，需4个字段见上表</div>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="saveNoticeConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>


                    <el-alert
                        v-if="noticeConfig.enable_wechat_notice"
                        title="在微信公众平台手动添加类目模板，字段名对应[渠道管理-微信公众号-模板消息]参数即可。"
                        type="info"
                        :closable="false"
                        style="margin: 16px 0;"
                    />

                    <div v-if="noticeConfig.enable_wechat_notice && noticeTplGuide.length" class="notice-guide-box">
                        <div class="notice-guide-title">微信公众平台添加模板时，请包含以下字段（名称尽量一致）：</div>
                        <div v-for="item in noticeTplGuide" :key="item.notice_key" class="notice-guide-item">
                            <div class="notice-guide-head">
                                <span class="notice-guide-name">{{ item.name }}</span>
                                <span class="notice-guide-tag">参考编号 {{ item.temp_key }}</span>
                                <span class="notice-guide-tag">通知{{ item.receiver }}</span>
                            </div>
                            <el-table :data="item.fields" border size="small" style="margin-top: 8px;">
                                <el-table-column prop="name" label="模板字段名" min-width="140" />
                                <el-table-column prop="wx_type" label="微信字段类型" min-width="120" />
                            </el-table>
                            <div class="text-gray-400 text-xs mt-1">{{ item.tips }}</div>
                        </div>
                    </div>


                    <el-divider content-position="left">小程序订阅消息</el-divider>
                    <el-form :model="noticeConfig" label-width="180px">
                        <el-form-item label="开启小程序订阅推送">
                            <el-switch v-model="noticeConfig.enable_weapp_notice" :active-value="1" :inactive-value="0" />
                            <div class="text-gray-400 text-xs mt-1">接单员/用户需在前端授权订阅，每次授权可收一条</div>
                        </el-form-item>
                        <template v-if="noticeConfig.enable_weapp_notice">
                            <el-form-item label="新订单通知接单员">
                                <el-input v-model="noticeConfig.weapp_tpl_order_pay_runner" placeholder="粘贴小程序订阅模板ID" style="width: 480px;" clearable />
                            </el-form-item>
                            <el-form-item label="跑手已接单提醒">
                                <el-input v-model="noticeConfig.weapp_tpl_order_accept_user" placeholder="粘贴小程序订阅模板ID" style="width: 480px;" clearable />
                            </el-form-item>
                            <el-form-item label="订单取消通知">
                                <el-input v-model="noticeConfig.weapp_tpl_order_cancel_user" placeholder="粘贴小程序订阅模板ID" style="width: 480px;" clearable />
                            </el-form-item>
                            <el-form-item>
                                <el-button type="primary" @click="saveNoticeConfig">保存配置</el-button>
                            </el-form-item>
                        </template>
                        <el-form-item v-else>
                            <el-button type="primary" @click="saveNoticeConfig">保存配置</el-button>
                        </el-form-item>
                    </el-form>

                    <el-alert
                        v-if="noticeConfig.enable_weapp_notice"
                        title="在微信小程序后台-订阅消息添加模板，复制模板ID填入上方。"
                        type="info"
                        :closable="false"
                        style="margin: 16px 0;"
                    />

                    <div v-if="noticeConfig.enable_weapp_notice && weappTplGuide.length" class="notice-guide-box">
                        <div class="notice-guide-title">小程序订阅消息模板字段参考：</div>
                        <div v-for="item in weappTplGuide" :key="item.notice_key" class="notice-guide-item">
                            <div class="notice-guide-head">
                                <span class="notice-guide-name">{{ item.name }}</span>
                                <span class="notice-guide-tag">参考编号 {{ item.temp_key }}</span>
                                <span class="notice-guide-tag">通知{{ item.receiver }}</span>
                            </div>
                            <el-table :data="item.fields" border size="small" style="margin-top: 8px;">
                                <el-table-column prop="name" label="模板字段名" min-width="140" />
                                <el-table-column prop="wx_type" label="微信字段类型" min-width="120" />
                            </el-table>
                            <div class="text-gray-400 text-xs mt-1">{{ item.tips }}</div>
                        </div>
                    </div>

                    
                </el-card>
            </el-tab-pane>


        </el-tabs>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { ElMessage } from 'element-plus'
import { getConfig, setConfig, getAllSchools } from '@/addon/sd_xiaoyuan/api/admin'

const activeTab = ref('features')

const baseConfig = ref({
    service_phone: '',
    service_start_time: '',
    service_end_time: '',
    order_timeout: 30,
    accept_timeout: 30,
    runner_school_limit: 0,
    runner_complete_point: 0,
    auto_confirm_days: 0,
    unauth_show_order_hall: 0,
    single_school: 0,
    default_school_id: 0
})

const schoolOptions = ref<any[]>([])

const createFeeConfig = () => ({
    base_fee: 3,
    buy_fee: 2,
    urgent_fee: 5
})

const feeConfig = ref(createFeeConfig())

const expressConfig = ref({
    express_type: 1,
    express_app_key: '',
    express_customer: '',
    aliyun_express_appcode: ''
})

const noticeConfig = ref({
    enable_wechat_notice: 0,
    wechat_tpl_order_pay_runner: '',
    wechat_tpl_runner_apply: '',
    wechat_tpl_order_accept_user: '',
    wechat_runner_apply_admin_ids: '',
    enable_weapp_notice: 0,
    weapp_tpl_order_pay_runner: '',
    weapp_tpl_order_accept_user: '',
    weapp_tpl_order_cancel_user: ''
})

const noticeTplGuide = ref<any[]>([])
const weappTplGuide = ref<any[]>([])

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
    enable_parttime: 1,
    enable_companion: 1,
    enable_queue: 1,
    enable_class: 1,
    enable_seat: 1,
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
    secondhand_name: '闲置市场',
    community_name: '校园树洞',
    require_auth_publish: 1,
    enable_custom_tabbar: 1,
    community_auto_approve: 0,
    confession_auto_approve: 0,
    house_auto_approve: 0,
    secondhand_auto_approve: 0,
    lost_found_auto_approve: 0,
    campus_auth_auto_approve: 0
})

onMounted(() => {
    loadSchoolOptions()
    loadConfig()
})

const loadSchoolOptions = async () => {
    const res: any = await getAllSchools()
    if (res.code === 1) {
        schoolOptions.value = res.data || []
    }
}

const loadConfig = async () => {
    try {
        const res: any = await getConfig()
        if (res.code === 1 && res.data) {
            if (res.data.base) Object.assign(baseConfig.value, res.data.base)
            if (res.data.fee) {
                feeConfig.value = { ...createFeeConfig(), ...res.data.fee }
            } else {
                feeConfig.value = createFeeConfig()
            }
            if (res.data.express) Object.assign(expressConfig.value, res.data.express)
            if (res.data.features) Object.assign(featureConfig.value, res.data.features)
            if (res.data.notice) {
                Object.assign(noticeConfig.value, res.data.notice)
                noticeTplGuide.value = res.data.notice.tpl_guide || []
                weappTplGuide.value = res.data.notice.weapp_tpl_guide || []
            }
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
        // 只提交基础费、加急费，不覆盖数据库里的 commission_*（改在接单员等级配置）
        const payload = {
            base_fee: feeConfig.value.base_fee,
            buy_fee: feeConfig.value.buy_fee,
            urgent_fee: feeConfig.value.urgent_fee
        }
        const res: any = await setConfig({ type: 'fee', config: payload })
        if (res.code === 1) {
            ElMessage.success('保存成功')
            loadConfig()
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

const saveNoticeConfig = async () => {
    try {
        const payload = {
            enable_wechat_notice: noticeConfig.value.enable_wechat_notice,
            wechat_tpl_order_pay_runner: noticeConfig.value.wechat_tpl_order_pay_runner,
            wechat_tpl_runner_apply: noticeConfig.value.wechat_tpl_runner_apply,
            wechat_tpl_order_accept_user: noticeConfig.value.wechat_tpl_order_accept_user,
            wechat_runner_apply_admin_ids: noticeConfig.value.wechat_runner_apply_admin_ids,
            enable_weapp_notice: noticeConfig.value.enable_weapp_notice,
            weapp_tpl_order_pay_runner: noticeConfig.value.weapp_tpl_order_pay_runner,
            weapp_tpl_order_accept_user: noticeConfig.value.weapp_tpl_order_accept_user,
            weapp_tpl_order_cancel_user: noticeConfig.value.weapp_tpl_order_cancel_user
        }
        const res: any = await setConfig({ type: 'notice', config: payload })
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

.notice-guide-box {
    padding: 12px 16px;
    background: #fafafa;
    border-radius: 4px;
}

.notice-guide-title {
    font-size: 13px;
    color: #606266;
    margin-bottom: 12px;
}

.notice-guide-item {
    margin-bottom: 16px;
}

.notice-guide-head {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.notice-guide-name {
    font-weight: 600;
    font-size: 14px;
}

.notice-guide-tag {
    font-size: 12px;
    color: #909399;
    background: #f0f0f0;
    padding: 2px 8px;
    border-radius: 3px;
}
</style>
