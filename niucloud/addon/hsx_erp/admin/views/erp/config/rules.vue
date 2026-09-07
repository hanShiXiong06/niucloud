<template>
    <HsxPage padding="none" class="main-container">
        <el-card class="!border-none" shadow="never">
            <HsxTitle size="page" collapsible-subtitle class="mb-4">
                <template #default>业务规则</template>
                <template #subtitle>按业务场景管理 ERP 运行方式。未开启的能力不会影响日常开单与库存操作。</template>
                <template #extra><div class="flex gap-2 flex-wrap">
                        <el-button :loading="loading" @click="loadConfig">刷新</el-button>
                        <el-button type="primary" :loading="saving" @click="submit">保存规则</el-button>
                    </div></template>
            </HsxTitle>

            <div class="config-workspace">
                <nav class="config-nav" aria-label="业务规则分类">
                    <button
                        v-for="item in navItems"
                        :key="item.key"
                        type="button"
                        class="config-nav-item"
                        :class="{ 'is-active': activeNav === item.key }"
                        @click="activeNav = item.key"
                    >
                        <span class="config-nav-icon">{{ item.index }}</span>
                        <span class="config-nav-copy">
                            <strong>{{ item.title }}</strong>
                            <small>{{ item.description }}</small>
                        </span>
                        <span class="config-nav-arrow">›</span>
                    </button>
                </nav>

                <div class="config-panel" v-loading="loading">
                    <div class="config-panel-header">
                        <div>
                            <div class="config-panel-title">{{ activeNavItem.title }}</div>
                            <div class="config-panel-description">{{ activeNavItem.detail }}</div>
                        </div>
                        <el-tag effect="plain" round>{{ activeNavItem.tag }}</el-tag>
                    </div>

                    <div class="rule-grid">
                <section v-show="activeNav === 'trade'" class="rule-section">
                    <div class="section-title">财务规则</div>
                    <div class="section-description">控制收付款形成事实后的锁定方式和账户要求。</div>
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="启用折账">
                            <el-switch v-model="form.finance.enable_offset" :active-value="1" :inactive-value="0" />
                            <span class="ml-3 text-sm text-gray-500">开启后仅财务可发起，系统不自动折账。</span>
                        </el-form-item>
                        <el-form-item label="财务确认后锁定">
                            <el-switch v-model="form.finance.finance_fact_lock" :active-value="1" :inactive-value="0" disabled />
                            <span class="ml-3 text-sm text-gray-500">收款、付款、折账后不能直接取消，只能退货或冲正。</span>
                        </el-form-item>
                        <el-form-item label="收付款必须选账户">
                            <el-switch v-model="form.finance.settlement_requires_account" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'workspace'" class="rule-section section-wide">
                    <div class="section-title">运营模式与销售资料</div>
                    <div class="section-description">统一控制一人完成或多人分工时的操作步骤、字段展示与资料交付方式。</div>
                    <div class="workspace-setting">
                        <div class="workspace-setting__head">
                            <div>
                                <div class="workspace-setting__title">销售资料工作模式</div>
                                <div class="workspace-setting__desc">所有人仍从 ERP 库存中心进入；系统只改变任务如何分配，不增加新的操作入口。</div>
                            </div>
                            <el-tag type="success" effect="plain">推荐先选一站式</el-tag>
                        </div>
                        <div class="workspace-mode-grid">
                            <button
                                v-for="item in workspaceModes"
                                :key="item.value"
                                type="button"
                                class="workspace-mode-card"
                                :class="{ 'is-active': form.listing_workspace.mode === item.value }"
                                @click="form.listing_workspace.mode = item.value"
                            >
                                <span class="workspace-mode-card__mark">{{ item.index }}</span>
                                <span class="workspace-mode-card__body">
                                    <strong>{{ item.title }}</strong>
                                    <small>{{ item.description }}</small>
                                    <em>{{ item.flow }}</em>
                                </span>
                                <span class="workspace-mode-card__check">✓</span>
                            </button>
                        </div>
                        <div class="workspace-provider-row">
                            <div>
                                <div class="workspace-provider-row__label">图片与视频处理</div>
                                <div class="workspace-provider-row__tip">选择“自动判断”时，中台安装且可用就调用中台；不可用会无感降级为 ERP 普通上传。</div>
                            </div>
                            <el-radio-group v-model="form.listing_workspace.media_provider">
                                <el-radio-button label="auto">自动判断</el-radio-button>
                                <el-radio-button label="erp">ERP 普通上传</el-radio-button>
                                <el-radio-button label="device_asset">标准化拍照中台</el-radio-button>
                            </el-radio-group>
                        </div>
                        <div class="workspace-provider-row">
                            <div>
                                <div class="workspace-provider-row__label">资料完成后</div>
                                <div class="workspace-provider-row__tip">默认保留一次人工确认；开启自动发布后，仍会受仓库规则和商城渠道配置约束。</div>
                            </div>
                            <el-switch v-model="form.listing_workspace.auto_publish" :active-value="1" :inactive-value="0" active-text="自动发布" inactive-text="人工确认" />
                        </div>
                        <div class="workspace-fields">
                            <div class="workspace-setting__head">
                                <div>
                                    <div class="workspace-setting__title">一站式表单字段</div>
                                    <div class="workspace-setting__desc">控制一人模式当次展示和校验的销售资料。分岗模式的核心交付物由岗位步骤强制保留。</div>
                                </div>
                                <el-tag effect="plain">必填项必须先显示</el-tag>
                            </div>
                            <div class="workspace-field-list">
                                <div v-for="item in listingFieldOptions" :key="item.key" class="workspace-field-row">
                                    <div class="workspace-field-row__copy">
                                        <strong>{{ item.label }}</strong>
                                        <small>{{ item.description }}</small>
                                    </div>
                                    <div class="workspace-field-row__controls">
                                        <span>显示</span>
                                        <el-switch
                                            v-model="form.listing_workspace.field_rules[item.key].enabled"
                                            :active-value="1"
                                            :inactive-value="0"
                                            @change="onListingFieldEnabledChange(item.key)"
                                        />
                                        <span>必填</span>
                                        <el-switch
                                            v-model="form.listing_workspace.field_rules[item.key].required"
                                            :active-value="1"
                                            :inactive-value="0"
                                            :disabled="form.listing_workspace.field_rules[item.key].enabled !== 1"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-show="activeNav === 'channel'" class="rule-section section-wide">
                    <div class="section-title">自有商城渠道</div>
                    <HsxNotice default-expanded class="mb-4" type="info" :closable="false" show-icon title="ERP 始终是主数据；商城只能消费 ERP 数据或维护自己的数据映射，不能反向修改 ERP 分类和规格。" />
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="启用商城联动">
                            <el-switch v-model="form.marketplace.channels.phone_shop.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="商城分类来源">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.category_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="erp">消费 ERP 目录</el-radio-button>
                                    <el-radio-button label="independent">商城独立分类</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">独立分类不会被 ERP 覆盖，由运营首次对应后保存映射，同类设备可自动复用。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="商城规格来源">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.spec_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="erp">消费 ERP 规格</el-radio-button>
                                    <el-radio-button label="independent">商城独立规格</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">独立规格由运营对应；映射只做翻译，不会改写 ERP 规格。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="渠道发布方式">
                            <div>
                                <el-radio-group v-model="form.marketplace.channels.phone_shop.publish_mode" :disabled="form.marketplace.channels.phone_shop.enabled !== 1">
                                    <el-radio-button label="direct">资料齐全直接发布</el-radio-button>
                                    <el-radio-button label="manual">商城运营逐台确认</el-radio-button>
                                </el-radio-group>
                                <div class="mt-2 text-xs text-gray-400">
                                    直接发布：ERP 资料齐全且已有必要映射时一键上架，缺少映射会自动转商城待办；逐台确认：所有 ERP 设备均由商城运营核对后发布。
                                </div>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'team'" class="rule-section section-wide">
                    <div class="section-title">自动任务默认负责人</div>
                    <HsxNotice default-expanded class="mb-4" type="info" :closable="false" show-icon title="只需设置一次。应收应付或设备进入拍照、商城定价、资料上架环节时，系统自动写入责任人并通知本人。" />
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item v-for="stage in taskStages" :key="stage.stage_key" :label="stage.name">
                            <div class="flex items-center gap-3">
                                <el-select v-model="stage.default_uid" class="!w-[240px]" clearable placeholder="自动选择首位岗位员工">
                                    <el-option v-for="user in stage.users" :key="user.uid" :label="user.name" :value="user.uid">
                                        <span>{{ user.name }}</span><span class="float-right text-xs text-gray-400">{{ user.username }}</span>
                                    </el-option>
                                </el-select>
                                <span v-if="stage.users?.length" class="text-xs text-gray-400">候选人来自角色动作权限</span>
                                <el-tag v-else type="danger" effect="plain">未配置岗位权限</el-tag>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'trade'" class="rule-section section-wide">
                    <div class="section-title">采购规则</div>
                    <div class="section-description">采购页面自动跟随销售资料工作模式，不再维护第二套容易冲突的录入开关。</div>
                    <div class="purchase-entry-setting purchase-entry-setting--derived">
                        <div class="workspace-setting__head">
                            <div>
                                <div class="workspace-setting__title">{{ purchaseModeDerived.title }}</div>
                                <div class="workspace-setting__desc">{{ purchaseModeDerived.description }}</div>
                            </div>
                            <el-tag type="success" effect="plain">由工作模式自动确定</el-tag>
                        </div>
                    </div>
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="入库立即生成应付">
                            <el-switch v-model="form.purchase.create_payable_on_inbound" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                        <el-form-item label="未付款允许业务取消">
                            <el-switch v-model="form.purchase.allow_cancel_before_finance_fact" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'goods'" class="rule-section">
                    <div class="section-title">设备命名规则</div>
                    <HsxNotice default-expanded
                        class="mb-4"
                        type="info"
                        :closable="false"
                        show-icon
                        title="采购开单选择分类和规格时，会按这里的规则自动生成设备名称。"
                    />
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="分类写入名称">
                            <el-radio-group v-model="form.product_title.category_mode">
                                <el-radio-button label="auto">自动</el-radio-button>
                                <el-radio-button label="level_1_2">一级+二级</el-radio-button>
                                <el-radio-button label="level_2_3">二级+三级</el-radio-button>
                                <el-radio-button label="level_3">仅末级</el-radio-button>
                                <el-radio-button label="full">完整路径</el-radio-button>
                            </el-radio-group>
                            <div class="mt-2 text-xs text-gray-500">自动：三级分类默认取二级+三级，二级分类取一级+二级，避免出现“手机 苹果 iPhone”这类冗余名称。</div>
                        </el-form-item>
                        <el-form-item label="规格写入名称">
                            <el-switch v-model="form.product_title.spec_in_title" :active-value="1" :inactive-value="0" />
                            <span class="ml-3 text-sm text-gray-500">如内存、容量、颜色等被标记为标题字段的规格。</span>
                        </el-form-item>
                        <el-form-item label="成色写入名称">
                            <el-switch v-model="form.product_title.grade_in_title" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="名称分隔符">
                            <el-input v-model="form.product_title.separator" class="!w-[160px]" placeholder="默认空格" />
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'trade'" class="rule-section section-wide">
                    <div class="section-title">销售规则</div>
                    <div class="section-description">统一销售出库、应收确认、取消回库和客户信用控制。</div>
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="出库立即生成应收">
                            <el-switch v-model="form.sale.create_receivable_on_outbound" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                        <el-form-item label="未收款允许业务取消">
                            <el-switch v-model="form.sale.allow_cancel_before_finance_fact" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="取消回原仓库">
                            <el-switch v-model="form.sale.return_to_original_location_on_cancel" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="允许同行未结算">
                            <el-switch v-model="form.sale.enable_peer_pending" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="允许客户试卖">
                            <el-switch v-model="form.sale.enable_trial_sale" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="利润确认口径">
                            <el-radio-group v-model="form.sale.profit_confirm_mode">
                                <el-radio-button label="settlement">结算确认</el-radio-button>
                                <el-radio-button label="outbound">出库预估</el-radio-button>
                            </el-radio-group>
                        </el-form-item>
                        <el-divider content-position="left">客户信用控制</el-divider>
                        <el-form-item label="启用开单信用检查">
                            <el-switch v-model="form.sale.credit_control.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="默认处理方式">
                            <el-select v-model="form.sale.credit_control.default_policy" class="!w-[220px]" :disabled="form.sale.credit_control.enabled !== 1">
                                <el-option label="正常交易，不提醒" value="normal" />
                                <el-option label="有欠款时提醒" value="remind" />
                                <el-option label="有欠款时仅现结" value="cash_only" />
                                <el-option label="有欠款时暂停交易" value="blocked" />
                            </el-select>
                        </el-form-item>
                        <el-form-item label="最低欠款金额">
                            <el-input-number v-model="form.sale.credit_control.min_outstanding_amount" :min="0" :precision="2" :controls="false" :disabled="form.sale.credit_control.enabled !== 1" />
                            <span class="ml-3 text-sm text-gray-500">达到该金额才触发，0 表示任意欠款。</span>
                        </el-form-item>
                        <el-form-item label="最低欠款账龄">
                            <el-input-number v-model="form.sale.credit_control.min_outstanding_days" :min="0" :max="3650" :precision="0" :disabled="form.sale.credit_control.enabled !== 1" />
                            <span class="ml-3 text-sm text-gray-500">最早未结应收达到该天数才触发，0 表示立即。</span>
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'special'" class="rule-section section-wide">
                    <div class="section-title">整备与代卖</div>
                    <div class="section-description">只有门店实际开展整备或寄售业务时才需要开启。</div>
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="启用整备流程">
                            <el-switch v-model="form.refurbish.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="入库默认整备">
                            <el-switch v-model="form.refurbish.default_required" :active-value="1" :inactive-value="0" :disabled="form.refurbish.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="整备跟踪方式">
                            <div>
                                <el-radio-group v-model="form.refurbish.tracking_mode" :disabled="form.refurbish.enabled !== 1">
                                    <el-radio-button label="simple">简易登记</el-radio-button>
                                    <el-radio-button label="external">外送追踪</el-radio-button>
                                </el-radio-group>
                                <div class="mt-1 text-xs text-gray-400">简易登记只在完工时记录服务商和费用；外送追踪会记录设备当前交给了哪家整备商。</div>
                            </div>
                        </el-form-item>
                        <el-form-item label="首页积压提醒">
                            <el-switch v-model="form.refurbish.daily_reminder_enabled" :active-value="1" :inactive-value="0" :disabled="form.refurbish.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="当日待整备阈值">
                            <div class="flex items-center gap-2">
                                <el-input-number v-model="form.refurbish.daily_reminder_threshold" :min="1" :max="999" :precision="0" :disabled="form.refurbish.daily_reminder_enabled !== 1" />
                                <span class="text-sm text-gray-500">台；达到后提醒老板及时分配处理</span>
                            </div>
                        </el-form-item>
                        <el-form-item label="启用代卖">
                            <el-switch v-model="form.consignment.enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="收款后生成寄售应付">
                            <el-switch v-model="form.consignment.settle_payable_after_receipt" :active-value="1" :inactive-value="0" :disabled="form.consignment.enabled !== 1" />
                        </el-form-item>
                        <el-form-item label="转自有必须重采">
                            <el-switch v-model="form.consignment.transfer_to_owned_requires_repurchase" :active-value="1" :inactive-value="0" disabled />
                        </el-form-item>
                    </el-form>
                </section>

                <section v-show="activeNav === 'goods'" class="rule-section">
                    <div class="section-title">库存周转预警</div>
                    <HsxNotice default-expanded class="mb-4" type="info" :closable="false" show-icon title="库龄按设备实际入库时间计算；阈值供库存中心、移动端和经营工作台统一使用。" />
                    <el-form class="rule-form" label-width="160px">
                        <el-form-item label="关注起始天数">
                            <el-input-number v-model="form.turnover.attention_days" :min="1" :max="365" :precision="0" />
                        </el-form-item>
                        <el-form-item label="预警起始天数">
                            <el-input-number v-model="form.turnover.warning_days" :min="form.turnover.attention_days + 1" :max="730" :precision="0" />
                        </el-form-item>
                        <el-form-item label="严重滞销天数">
                            <el-input-number v-model="form.turnover.critical_days" :min="form.turnover.warning_days + 1" :max="1095" :precision="0" />
                        </el-form-item>
                        <el-form-item label="首页周转提醒">
                            <el-switch v-model="form.turnover.reminder_enabled" :active-value="1" :inactive-value="0" />
                        </el-form-item>
                        <el-form-item label="提醒设备数量">
                            <div class="flex items-center gap-2">
                                <el-input-number v-model="form.turnover.reminder_count_threshold" :min="1" :max="9999" :precision="0" :disabled="form.turnover.reminder_enabled !== 1" />
                                <span class="text-sm text-gray-500">台达到预警或严重滞销后提醒</span>
                            </div>
                        </el-form-item>
                    </el-form>
                </section>
                    </div>
                </div>
            </div>
        </el-card>
    </HsxPage>
</template>

<script setup lang="ts">
import { HsxTitle, HsxPage, HsxNotice, useFeedback } from '@/addon/hsx_components/core'
import { computed, onMounted, reactive, ref } from 'vue'

import { getErpConfig, saveErpConfig, getErpTaskAssignmentSettings, saveErpTaskAssignmentSettings } from '@/addon/hsx_erp/api/config'
const hsxFeedback = useFeedback()


const loading = ref(false)
const saving = ref(false)
const form = reactive(defaultRules())
const taskStages = ref<any[]>([])
const activeNav = ref('workspace')
const navItems = [
    { key: 'workspace', index: '01', title: '运营模式', description: '一人完成、多人分工', detail: '决定销售资料由一人一次完成，还是拆分给拍摄、销售定价与渠道运营岗位处理。', tag: '使用入口' },
    { key: 'trade', index: '02', title: '交易与财务', description: '采购、销售、收付款', detail: '管理从采购入库到销售结算的核心规则，建议只在业务口径发生变化时调整。', tag: '核心流程' },
    { key: 'goods', index: '03', title: '商品与库存', description: '名称规则、周转预警', detail: '统一商品展示名称与库存周转阈值，后续配件数量库存也在此处开启和管理。', tag: '库存管理' },
    { key: 'channel', index: '04', title: '商城渠道', description: '分类、规格、发布方式', detail: '决定商城消费 ERP 数据还是独立维护映射，并控制资料齐全后的发布方式。', tag: '渠道协同' },
    { key: 'team', index: '05', title: '任务分工', description: '自动负责人、岗位承接', detail: '为财务、拍摄、销售定价和商城运营设置默认负责人，减少人工派单。', tag: '团队协作' },
    { key: 'special', index: '06', title: '整备与代卖', description: '按需开启的扩展流程', detail: '整备、外送追踪和寄售业务均为可选能力，未开展时无需配置。', tag: '扩展业务' }
]
const workspaceModes = [
    { value: 'one_stop', index: '01', title: '一站式录入', description: '适合 1～5 人团队，一人在一个表单全部完成', flow: '资料 · 图片 · 销售价 · 发布' },
    { value: 'split', index: '02', title: '专业分工', description: '拍摄、销售定价、渠道运营分别承接待办', flow: '拍摄 → 销售定价 → 渠道运营' },
    { value: 'photo_price', index: '03', title: '拍摄定价合并', description: '同一人连续拍图和定价，运营只处理渠道资料', flow: '拍摄与定价 → 渠道运营' }
]
const listingFieldOptions = [
    { key: 'catalog_product_id', label: '商品目录型号', description: '品类、品牌、系列与标准型号' },
    { key: 'spec', label: '设备规格', description: '容量、颜色、成色、电池等本机信息' },
    { key: 'image_urls', label: '商品图片', description: '商城展示所需的正反面、边框和瑕疵图' },
    { key: 'video_url', label: '展示视频', description: '可选的商品展示视频' },
    { key: 'retail_price', label: '销售价格', description: '对外销售价格，不影响采购成本' },
    { key: 'quality_remark', label: '质检备注', description: '拍摄或资料整理人员补充的质检说明' },
    { key: 'remark_public', label: '对外说明', description: '商城客户可见的商品描述' },
    { key: 'remark_internal', label: '对内备注', description: '仅员工可见的协作信息' },
]
const activeNavItem = computed(() => navItems.find((item) => item.key === activeNav.value) || navItems[0])
const purchaseModeDerived = computed(() => form.listing_workspace.mode === 'one_stop'
    ? { title: '采购时一次完善', description: '采购人员在同一页面完成采购事实与已启用的销售资料；必填项未完成不能提交。' }
    : { title: '采购事实先入库', description: '采购人员只负责供应商、设备、成本和入库位置；保存后系统按工作模式自动生成拍摄、定价和资料任务。' })

function defaultListingFieldRules() {
    return {
        catalog_product_id: { enabled: 1, required: 1 },
        spec: { enabled: 1, required: 1 },
        image_urls: { enabled: 1, required: 1 },
        video_url: { enabled: 1, required: 0 },
        retail_price: { enabled: 1, required: 1 },
        quality_remark: { enabled: 1, required: 0 },
        remark_public: { enabled: 1, required: 0 },
        remark_internal: { enabled: 1, required: 0 },
    }
}

function defaultRules() {
    return {
        finance: { enable_offset: 1, finance_fact_lock: 1, settlement_requires_account: 1 },
        purchase: { create_payable_on_inbound: 1, allow_cancel_before_finance_fact: 1, mobile_entry_mode: 'quick' },
        product_title: { category_mode: 'auto', spec_in_title: 1, grade_in_title: 0, separator: ' ' },
        sale: { create_receivable_on_outbound: 1, allow_cancel_before_finance_fact: 1, return_to_original_location_on_cancel: 1, enable_peer_pending: 1, enable_trial_sale: 0, profit_confirm_mode: 'settlement', credit_control: { enabled: 1, default_policy: 'remind', min_outstanding_amount: 0, min_outstanding_days: 0 } },
        refurbish: { enabled: 1, default_required: 0, tracking_mode: 'simple', daily_reminder_enabled: 1, daily_reminder_threshold: 25, reminder_dismiss_date: '' },
        listing_workspace: { mode: 'one_stop', media_provider: 'auto', auto_publish: 0, fallback_to_erp: 1, field_rules: defaultListingFieldRules() },
        marketplace: {
            recycle_material_owner: 'erp',
            channels: { phone_shop: { enabled: 1, category_mode: 'erp', spec_mode: 'erp', publish_mode: 'direct' } }
        },
        turnover: { attention_days: 7, warning_days: 15, critical_days: 30, reminder_enabled: 1, reminder_count_threshold: 1, reminder_dismiss_date: '' },
        consignment: { enabled: 0, settle_payable_after_receipt: 1, transfer_to_owned_requires_repurchase: 1 }
    }
}

function onListingFieldEnabledChange(key: string) {
    const rule = form.listing_workspace.field_rules[key]
    if (rule && rule.enabled !== 1) rule.required = 0
}

function applyListingWorkspace(payload: any = {}) {
    const incomingRules = payload?.field_rules && typeof payload.field_rules === 'object'
        ? payload.field_rules
        : {}
    const fieldRules = defaultListingFieldRules()
    listingFieldOptions.forEach((item) => {
        const incoming = incomingRules[item.key]
        if (incoming && typeof incoming === 'object') {
            fieldRules[item.key] = {
                enabled: Number(incoming.enabled ?? fieldRules[item.key].enabled) === 1 ? 1 : 0,
                required: Number(incoming.required ?? fieldRules[item.key].required) === 1 ? 1 : 0,
            }
        }
        if (fieldRules[item.key].enabled !== 1) fieldRules[item.key].required = 0
    })
    Object.assign(form.listing_workspace, payload || {}, { field_rules: fieldRules })
}

async function loadConfig() {
    loading.value = true
    try {
        const [res, taskRes]: any[] = await Promise.all([getErpConfig(), getErpTaskAssignmentSettings()])
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
        applyListingWorkspace(res?.data?.listing_workspace)
        Object.assign(form.marketplace, res?.data?.marketplace || {})
        Object.assign(form.turnover, res?.data?.turnover || {})
        Object.assign(form.consignment, res?.data?.consignment || {})
        taskStages.value = (taskRes?.data || []).map((item: any) => ({ ...item, default_uid: Number(item.default_uid || 0) || undefined }))
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    try {
        const defaults = Object.fromEntries(taskStages.value.map((item: any) => [item.stage_key, Number(item.default_uid || 0)]))
        const [res]: any[] = await Promise.all([
            saveErpConfig(JSON.parse(JSON.stringify(form))),
            saveErpTaskAssignmentSettings(defaults)
        ])
        Object.assign(form.finance, res?.data?.finance || {})
        Object.assign(form.purchase, res?.data?.purchase || {})
        Object.assign(form.product_title, res?.data?.product_title || {})
        Object.assign(form.sale, res?.data?.sale || {})
        Object.assign(form.refurbish, res?.data?.refurbish || {})
        applyListingWorkspace(res?.data?.listing_workspace)
        Object.assign(form.marketplace, res?.data?.marketplace || {})
        Object.assign(form.turnover, res?.data?.turnover || {})
        Object.assign(form.consignment, res?.data?.consignment || {})
        hsxFeedback.success('业务规则已保存')
    } finally {
        saving.value = false
    }
}

onMounted(loadConfig)
</script>

<style scoped>
.config-page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    padding-bottom: 18px;
    border-bottom: 1px solid #edf1f7;
}
.config-workspace {
    display: grid;
    grid-template-columns: 230px minmax(0, 1fr);
    gap: 20px;
    margin-top: 20px;
    align-items: start;
}
.config-nav {
    position: sticky;
    top: 16px;
    padding: 8px;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: #f8fafc;
}
.config-nav-item {
    display: flex;
    align-items: center;
    width: 100%;
    min-height: 66px;
    padding: 11px 10px;
    border: 0;
    border-radius: 9px;
    background: transparent;
    color: #64748b;
    text-align: left;
    cursor: pointer;
    transition: background-color .18s ease, color .18s ease, box-shadow .18s ease;
}
.config-nav-item + .config-nav-item {
    margin-top: 4px;
}
.config-nav-item:hover {
    color: #334155;
    background: #fff;
}
.config-nav-item.is-active {
    color: var(--el-color-primary);
    background: #fff;
    box-shadow: 0 4px 14px rgba(15, 23, 42, .07);
}
.workspace-setting {
    margin-bottom: 22px;
    padding: 20px;
    border: 1px solid #dfe7f3;
    border-radius: 14px;
    background: linear-gradient(145deg, #f8fbff 0%, #fff 55%);
}
.purchase-entry-setting {
    margin: 14px 0 22px;
    padding: 18px;
    border: 1px solid #dfe7f3;
    border-radius: 14px;
    background: linear-gradient(145deg, #f8fbff 0%, #fff 62%);
}
.workspace-setting__head,
.workspace-provider-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}
.workspace-setting__title,
.workspace-provider-row__label {
    color: #172033;
    font-size: 15px;
    font-weight: 650;
}
.workspace-setting__desc,
.workspace-provider-row__tip {
    margin-top: 5px;
    color: #8491a7;
    font-size: 12px;
    line-height: 1.65;
}
.workspace-mode-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-top: 18px;
}
.workspace-mode-card {
    position: relative;
    display: flex;
    min-height: 116px;
    gap: 12px;
    padding: 16px;
    overflow: hidden;
    border: 1px solid #e3e9f2;
    border-radius: 12px;
    background: #fff;
    text-align: left;
    cursor: pointer;
    transition: border-color .18s ease, box-shadow .18s ease, transform .18s ease;
}
.workspace-mode-card:hover {
    border-color: #a8c7ff;
    transform: translateY(-1px);
}
.workspace-mode-card.is-active {
    border-color: var(--el-color-primary);
    box-shadow: 0 8px 22px rgba(37, 99, 235, .12);
}
.workspace-mode-card__mark {
    flex: 0 0 auto;
    color: #a2aec1;
    font-size: 12px;
    font-weight: 700;
}
.workspace-mode-card.is-active .workspace-mode-card__mark {
    color: var(--el-color-primary);
}
.workspace-mode-card__body {
    display: flex;
    min-width: 0;
    flex: 1;
    flex-direction: column;
}
.workspace-mode-card__body strong {
    color: #172033;
    font-size: 15px;
}
.workspace-mode-card__body small {
    margin-top: 7px;
    color: #7d899c;
    line-height: 1.55;
}
.workspace-mode-card__body em {
    margin-top: auto;
    padding-top: 10px;
    color: #4b68a0;
    font-size: 12px;
    font-style: normal;
}
.workspace-mode-card__check {
    position: absolute;
    right: 12px;
    top: 10px;
    color: var(--el-color-primary);
    font-size: 16px;
    opacity: 0;
}
.workspace-mode-card.is-active .workspace-mode-card__check {
    opacity: 1;
}
.workspace-provider-row {
    margin-top: 14px;
    padding-top: 15px;
    border-top: 1px solid #e9eef6;
}
.workspace-fields {
    margin-top: 18px;
    padding-top: 18px;
    border-top: 1px solid #e9eef6;
}
.workspace-field-list {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
    margin-top: 14px;
}
.workspace-field-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    min-width: 0;
    gap: 16px;
    padding: 12px 14px;
    border: 1px solid #e8edf5;
    border-radius: 10px;
    background: #fff;
}
.workspace-field-row__copy {
    min-width: 0;
}
.workspace-field-row__copy strong,
.workspace-field-row__copy small {
    display: block;
}
.workspace-field-row__copy strong {
    color: #334155;
    font-size: 14px;
}
.workspace-field-row__copy small {
    overflow: hidden;
    margin-top: 4px;
    color: #94a3b8;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.workspace-field-row__controls {
    display: flex;
    align-items: center;
    flex: 0 0 auto;
    gap: 8px;
    color: #7c8799;
    font-size: 12px;
}
.purchase-entry-setting--derived {
    margin-bottom: 12px;
}
@media (max-width: 1100px) {
    .workspace-mode-grid {
        grid-template-columns: 1fr;
    }
    .workspace-provider-row {
        align-items: flex-start;
        flex-direction: column;
    }
    .workspace-field-list {
        grid-template-columns: 1fr;
    }
}
.config-nav-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 34px;
    height: 34px;
    margin-right: 10px;
    border-radius: 9px;
    background: #eef2f7;
    color: #94a3b8;
    font-size: 12px;
    font-weight: 700;
}
.config-nav-item.is-active .config-nav-icon {
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
}
.config-nav-copy {
    min-width: 0;
    flex: 1;
}
.config-nav-copy strong,
.config-nav-copy small {
    display: block;
}
.config-nav-copy strong {
    color: inherit;
    font-size: 14px;
    font-weight: 650;
}
.config-nav-copy small {
    overflow: hidden;
    margin-top: 3px;
    color: #94a3b8;
    font-size: 12px;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.config-nav-arrow {
    margin-left: 6px;
    color: #cbd5e1;
    font-size: 20px;
}
.config-nav-item.is-active .config-nav-arrow {
    color: var(--el-color-primary);
}
.config-panel {
    min-width: 0;
}
.config-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    min-height: 76px;
    margin-bottom: 14px;
    padding: 15px 18px;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: linear-gradient(135deg, #f8fbff 0%, #fff 68%);
}
.config-panel-title {
    color: #1e293b;
    font-size: 18px;
    font-weight: 700;
}
.config-panel-description {
    margin-top: 5px;
    color: #8492a6;
    font-size: 13px;
    line-height: 1.6;
}
.rule-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
}
.rule-section {
    min-width: 0;
    padding: 20px 20px 8px;
    border: 1px solid #e8edf5;
    border-radius: 12px;
    background: #fff;
}
.section-wide {
    grid-column: 1 / -1;
}
.section-title {
    position: relative;
    margin-bottom: 4px;
    padding-left: 12px;
    color: #1e293b;
    font-size: 16px;
    font-weight: 700;
}
.section-title::before {
    position: absolute;
    top: 3px;
    bottom: 3px;
    left: 0;
    width: 3px;
    border-radius: 2px;
    background: var(--el-color-primary);
    content: '';
}
.section-description {
    margin: 0 0 16px 12px;
    color: #94a3b8;
    font-size: 13px;
}
.rule-form {
    margin-top: 16px;
}
.rule-form :deep(.el-form-item) {
    margin-bottom: 20px;
}
.rule-form :deep(.el-form-item__label) {
    color: #475569;
    font-weight: 500;
}
.rule-form :deep(.el-form-item__content) {
    min-width: 0;
}
.rule-form :deep(.el-radio-group) {
    max-width: 100%;
    flex-wrap: wrap;
}
.rule-form :deep(.el-alert) {
    line-height: 1.6;
}

@media (max-width: 1280px) {
    .config-workspace {
        grid-template-columns: 1fr;
    }
    .config-nav {
        position: static;
        display: flex;
        overflow-x: auto;
        gap: 6px;
    }
    .config-nav-item {
        flex: 0 0 190px;
        margin-top: 0 !important;
    }
    .config-nav-arrow {
        display: none;
    }
}

@media (max-width: 900px) {
    .config-page-header,
    .config-panel-header {
        align-items: stretch;
        flex-direction: column;
    }
    .rule-grid {
        grid-template-columns: 1fr;
    }
    .section-wide {
        grid-column: auto;
    }
    .rule-section {
        padding-right: 14px;
        padding-left: 14px;
    }
    .rule-form :deep(.el-form-item) {
        display: block;
    }
    .rule-form :deep(.el-form-item__label) {
        display: block;
        width: auto !important;
        margin-bottom: 8px;
        text-align: left;
    }
    .rule-form :deep(.el-form-item__content) {
        margin-left: 0 !important;
    }
}
</style>
