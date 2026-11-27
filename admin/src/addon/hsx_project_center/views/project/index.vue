<template>
    <div class="main-page">
        <el-card shadow="never" class="!border-none">
            <template #header>
                <div class="flex items-start justify-between gap-[20px]">
                    <div>
                        <div class="text-[18px] font-semibold text-[#1d2939]">项目配置</div>
                        <div class="mt-[6px] text-[13px] leading-[20px] text-[#667085]">统一配置项目微页面、资料表单和审核负责人，客户扫码后可在同一页了解项目并提交资料。</div>
                    </div>
                    <el-button type="primary" @click="openEditor()">新建项目</el-button>
                </div>
            </template>

            <div class="mb-[18px] flex flex-wrap items-center gap-[10px] rounded-[10px] bg-[#f8fafc] p-[14px]">
                <el-input v-model="query.keyword" clearable class="!w-[280px]" placeholder="项目名称或项目编号" @keyup.enter="loadPage(1)" />
                <el-select v-model="query.status" clearable class="!w-[150px]" placeholder="全部状态">
                    <el-option label="草稿" :value="0" /><el-option label="启用" :value="1" /><el-option label="停用" :value="2" />
                </el-select>
                <el-button type="primary" @click="loadPage(1)">查询</el-button><el-button @click="resetQuery">重置</el-button>
            </div>

            <el-table v-loading="loading" :data="rows" size="large">
                <el-table-column label="项目" min-width="250">
                    <template #default="{ row }">
                        <div class="font-medium text-[#1d2939]">{{ row.title }}</div>
                        <div class="mt-[5px] text-[12px] text-[#98a2b3]">{{ row.project_no }}<span v-if="row.subtitle"> · {{ row.subtitle }}</span></div>
                    </template>
                </el-table-column>
                <el-table-column label="业务入口" min-width="210">
                    <template #default="{ row }">
                        <div>资料表单 #{{ row.form_id || '-' }}</div>
                        <div class="mt-[4px] text-[12px] text-[#98a2b3]">审核员 {{ (row.reviewer_uids || []).length }} 人 · 工单 {{ row.application_count || 0 }} 笔</div>
                    </template>
                </el-table-column>
                <el-table-column label="项目能力" min-width="210">
                    <template #default="{ row }">
                        <el-tag effect="plain" :type="row.ai_enabled ? 'success' : 'info'">AI {{ row.ai_enabled ? '已开启' : '未开启' }}</el-tag>
                        <el-tooltip :disabled="!row.distribution_enabled" placement="top" :width="420" :content="row.distribution_summary?.rule_text || '项目已开启分销，请查看项目配置中的推广资格规则。'">
                            <el-tag class="ml-[6px] cursor-help" effect="plain" :type="row.distribution_enabled ? 'warning' : 'info'">分销 {{ row.distribution_enabled ? '已开启' : '未开启' }}</el-tag>
                        </el-tooltip>
                        <el-tooltip :disabled="!row.area_eligibility_summary?.enabled" placement="top" :content="row.area_eligibility_summary?.enabled ? `付款前必须查询地区，已配置 ${row.area_eligibility_summary.scope_count || 0} 个参与范围` : '未限制参与地区'">
                            <el-tag class="ml-[6px] cursor-help" effect="plain" :type="row.area_eligibility_summary?.enabled ? 'success' : 'info'">地区 {{ row.area_eligibility_summary?.enabled ? '已限制' : '不限' }}</el-tag>
                        </el-tooltip>
                    </template>
                </el-table-column>
                <el-table-column label="参考金额" width="130"><template #default="{ row }">{{ row.payment_amount > 0 ? `¥${row.payment_amount}` : '-' }}</template></el-table-column>
                <el-table-column label="状态" width="100"><template #default="{ row }"><el-tag :type="Number(row.status) === 1 ? 'success' : 'info'">{{ row.status_name }}</el-tag></template></el-table-column>
                <el-table-column label="操作" fixed="right" width="230">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="showEntry(row)">参与入口</el-button>
                        <el-button link type="primary" @click="openEditor(row.id)">编辑</el-button>
                        <el-button link type="danger" @click="remove(row)">删除</el-button>
                    </template>
                </el-table-column>
            </el-table>
            <div class="mt-[18px] flex justify-end"><el-pagination v-model:current-page="page.page" v-model:page-size="page.limit" layout="total, prev, pager, next" :total="page.total" @current-change="loadPage" /></div>
        </el-card>

        <el-drawer v-model="editor.visible" :title="editor.id ? '编辑项目' : '新建项目'" size="680px" destroy-on-close>
            <el-form ref="formRef" :model="form" :rules="rules" label-position="top" class="project-form">
                <section class="form-section">
                    <div class="section-title">基础信息</div>
                    <div class="grid grid-cols-2 gap-x-[16px]">
                        <el-form-item label="项目名称" prop="title"><el-input v-model="form.title" maxlength="120" show-word-limit placeholder="如：美团闪购入驻" /></el-form-item>
                        <el-form-item label="展示顺序"><el-input-number v-model="form.sort" :min="0" :max="9999" class="!w-full" /></el-form-item>
                    </div>
                    <el-form-item label="一句话说明"><el-input v-model="form.subtitle" maxlength="255" placeholder="让客户快速理解项目价值" /></el-form-item>
                    <el-form-item label="项目封面"><upload-image v-model="form.cover" :limit="1" width="150px" height="90px" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">资料与审核</div>
                    <el-alert class="mb-[16px]" type="info" :closable="false" title="若开启地区限制，客户必须先查询参与资格；符合后才展示付款码，再核对客户群编号并提交资料。" />
                    <el-form-item label="万能表单" prop="form_id">
                        <el-select v-model="form.form_id" filterable class="!w-full" placeholder="请选择已经配置好的资料表单">
                            <el-option v-for="item in metadata.forms" :key="item.form_id" :label="item.form_name" :value="item.form_id" />
                        </el-select>
                    </el-form-item>
                    <el-form-item label="资料审核员" prop="reviewer_uids">
                        <el-select v-model="form.reviewer_uids" multiple filterable collapse-tags class="!w-full" placeholder="审核工单将分配给这些员工">
                            <el-option v-for="item in metadata.reviewers" :key="item.uid" :label="item.name" :value="item.uid" />
                        </el-select>
                    </el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">参与地区资格</div>
                    <div class="setting-row"><div><div class="setting-name flex items-center">付款前地区查询<el-tooltip placement="top" :width="420" content="只配置允许参与的地区。省级规则覆盖全省，市级规则覆盖全市，区县规则只覆盖该区县；服务端会在工单提交时再次校验，前端无法绕过。"><el-icon class="ml-[6px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></div><div class="setting-desc">默认关闭；开启后，客户查询为“可以参加”才会看到付款码。</div></div><el-switch v-model="form.area_eligibility_enabled" /></div>
                    <ProjectAreaEligibilityConfig v-if="form.area_eligibility_enabled" v-model="form.area_eligibility" />
                </section>

                <section class="form-section">
                    <div class="section-title">陌生客户联系</div>
                    <el-alert class="mb-[16px]" type="info" :closable="false" show-icon title="推荐流程：查询资格 → 添加企微 → 群内付款 → 工作人员核对 → 填写资料。企微接口不可用时自动展示备用二维码。" />
                    <div class="setting-row"><div><div class="setting-name flex items-center">付款前先添加项目顾问<el-tooltip placement="top" :width="440" content="企业微信不能强制添加陌生客户。客户需主动扫码添加；按钮只记录客户声明，真实联系人和付款仍由工作人员核对。"><el-icon class="ml-[6px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></div><div class="setting-desc">开启后，客户未完成“加企微”步骤时不会展示付款码。</div></div><el-switch v-model="form.contact_guide_enabled" /></div>
                    <template v-if="form.contact_guide_enabled">
                        <el-form-item label="联系入口模式">
                            <el-radio-group v-model="form.contact_guide_mode"><el-radio-button label="wecom_first">企微优先</el-radio-button><el-radio-button label="qrcode">只用上传二维码</el-radio-button></el-radio-group>
                            <div class="form-tip">企微优先会调用 hsx_wecom 生成“联系我”二维码；授权、权限或接口异常时立即降级到备用二维码。</div>
                        </el-form-item>
                        <el-form-item v-if="form.contact_guide_mode === 'wecom_first'" label="项目顾问">
                            <el-select v-model="form.contact_guide_staff_uids" multiple filterable collapse-tags class="!w-full" placeholder="选择接待陌生客户的企微成员">
                                <el-option v-for="item in metadata.reviewers" :key="item.uid" :label="item.name" :value="item.uid" />
                            </el-select>
                            <div class="form-tip">所选员工还必须在企业微信插件内完成 UserID 绑定，并具有客户联系权限。</div>
                        </el-form-item>
                        <el-form-item label="备用企微二维码" required>
                            <upload-image v-model="form.contact_guide_fallback_qrcode" :limit="1" width="110px" height="110px" />
                            <div class="form-tip">必须上传。即使企微已打通，也用它兜底，防止授权到期或接口临时故障导致客户无法继续。</div>
                        </el-form-item>
                        <div class="grid grid-cols-2 gap-x-[16px]"><el-form-item label="步骤标题"><el-input v-model="form.contact_guide_title" maxlength="50" /></el-form-item><el-form-item label="继续按钮"><el-input v-model="form.contact_guide_button_text" maxlength="30" /></el-form-item></div>
                        <el-form-item label="添加说明"><el-input v-model="form.contact_guide_tips" type="textarea" :rows="2" maxlength="500" show-word-limit /></el-form-item>
                        <el-form-item label="客户添加后的首句话"><el-input v-model="form.contact_guide_greeting" maxlength="200" show-word-limit /><div class="form-tip">移动端会展示给客户，方便陌生客户添加后直接复制或照着发送。</div></el-form-item>
                    </template>
                </section>

                <section class="form-section">
                    <div class="section-title">客户付款引导</div>
                    <div class="grid grid-cols-2 gap-x-[16px]">
                        <el-form-item label="付款金额"><el-input-number v-model="form.payment_amount" :min="0" :precision="2" class="!w-full" /></el-form-item>
                        <el-form-item label="客户付款码" required><upload-image v-model="form.payment_qrcode" :limit="1" width="90px" height="90px" /><div class="mt-[7px] text-[12px] leading-[20px] text-[#98a2b3]">未限制地区时直接展示；开启地区限制时，查询为“可以参加”后才展示。</div></el-form-item>
                    </div>
                    <el-form-item label="付款提示"><el-input v-model="form.payment_tips" type="textarea" :rows="3" maxlength="1000" placeholder="示例：长按识别付款码，付款后截图并发送到客户群，再点击下一步填写资料。" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">项目介绍</div>
                    <el-form-item label="DIY 项目微页面">
                        <el-select v-model="form.intro_page_id" clearable filterable class="!w-full" placeholder="不选则使用下方的简易介绍">
                            <el-option v-for="item in metadata.diy_pages" :key="item.id" :label="item.page_name" :value="item.id" />
                        </el-select>
                        <div class="mt-[7px] text-[12px] leading-[20px] text-[#98a2b3]">选中后，客户端优先展示已装修的微页面；下方内容作为未选微页面或页面失效时的兜底。</div>
                    </el-form-item>
                    <el-form-item label="项目介绍正文"><el-input v-model="form.intro" type="textarea" :rows="5" placeholder="说明项目是什么、适合谁、投入和退出规则。" /></el-form-item>
                    <el-form-item label="办理步骤（每行一步）"><el-input v-model="form.steps_text" type="textarea" :rows="4" placeholder="了解项目\n项目页长按付款码完成付款\n将付款截图发送到客户群\n核对群号并提交资料\n等待审核" /></el-form-item>
                    <el-form-item label="常见问题（每行“问题|答案”）"><el-input v-model="form.faqs_text" type="textarea" :rows="5" placeholder="付款后多久上线？|20日前通常一周左右，具体以资料审核进度为准。" /></el-form-item>
                </section>

                <section class="form-section">
                    <div class="section-title">可选能力</div>
                    <div class="setting-row"><div><div class="setting-name">AI 项目顾问</div><div class="setting-desc">默认关闭；同时开启 hsx_ai 总开关和“项目中心”业务接入后，客户可在项目页弹窗咨询。</div></div><el-switch v-model="form.ai_enabled" /></div>
                    <template v-if="form.ai_enabled">
                        <el-alert class="my-[14px]" type="info" :closable="false" title="知识按当前站点和当前项目隔离；AI 无法确认时会引导客户返回群内联系工作人员。" />
                        <div class="grid grid-cols-2 gap-x-[16px]">
                            <el-form-item label="助手名称"><el-input v-model="form.ai_title" maxlength="30" show-word-limit placeholder="AI 项目顾问" /></el-form-item>
                            <el-form-item label="语音朗读"><div class="flex h-[32px] items-center gap-[16px]"><el-switch v-model="form.ai_voice_enabled" /><el-checkbox v-if="form.ai_voice_enabled" v-model="form.ai_auto_read">默认自动朗读</el-checkbox></div></el-form-item>
                        </div>
                        <el-form-item label="欢迎语"><el-input v-model="form.ai_welcome" type="textarea" :rows="2" maxlength="300" show-word-limit placeholder="告诉客户可以咨询哪些项目问题" /></el-form-item>
                        <el-form-item label="核心关注问题（每行一个）"><el-input v-model="form.ai_suggestions_text" type="textarea" :rows="4" placeholder="这个项目适合我吗？\n需要准备哪些资料？\n付款后多久可以上线？\n不做了如何退款？" /><div class="form-tip">弹窗首屏直接展示，建议配置 4～6 个最常问的问题。</div></el-form-item>
                        <el-form-item label="项目专属知识库"><el-input v-model="form.ai_knowledge" type="textarea" :rows="8" maxlength="30000" show-word-limit placeholder="补充项目费用、适用门店、资料要求、审核规则、上线周期、退出与退款规则等。项目介绍、办理步骤和常见问题会自动进入知识库，无需重复填写。" /><div class="form-tip">只填写允许客户公开了解的内容；到账状态、审核结果等实时事实仍由群内工作人员确认。</div></el-form-item>
                    </template>
                    <div class="setting-row"><div><div class="setting-name flex items-center">邀请分佣<el-tooltip placement="top" :width="450" content="项目可以允许所有正常会员直接推广，也可以接入会员等级权益控制资格和系数。客户资料审核通过并确认群内付款流水后生成佣金单，保护期结束才入账；历史工单不追溯。"><el-icon class="ml-[6px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></div><div class="setting-desc">默认关闭；开启后可以自行选择是否接入会员等级。</div></div><el-switch v-model="form.distribution_enabled" /></div>
                    <div v-if="form.distribution_enabled" class="distribution-config">
                        <el-form-item>
                            <template #label><span>推广资格</span><el-tooltip placement="top" :width="430" content="“所有会员”不读取会员等级，任何正常登录会员都能直接分享，一级和二级佣金系数固定为 100%；“按会员等级”则由会员等级权益决定能否推广及佣金系数。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template>
                            <el-radio-group v-model="form.eligibility_mode"><el-radio-button label="all_member">所有会员</el-radio-button><el-radio-button label="member_level">按会员等级</el-radio-button></el-radio-group>
                            <div class="form-tip">{{ form.eligibility_mode === 'all_member' ? '无需配置会员等级；会员登录后即可生成邀请海报，佣金按 100% 系数计算。' : '只有会员等级中开启“项目推广分佣”权益的会员，才可以分享和获得佣金。' }}</div>
                        </el-form-item>
                        <el-alert class="mb-[16px]" type="warning" :closable="false" show-icon>
                            <template #title>{{ form.eligibility_mode === 'all_member' ? '示例：一级基础佣金 300 元，所有正常会员均按 100% 系数计算，最终佣金为 300 元。' : '示例：一级基础佣金 300 元，推广人等级系数 120%，最终佣金为 360 元。' }} 客户退款会冻结或冲红，余额不足会记为待抵扣。</template>
                        </el-alert>
                        <div v-if="form.eligibility_mode === 'member_level'" class="mb-[15px] rounded-[10px] bg-[#f8fafc] px-[14px] py-[12px] text-[13px] text-[#475467]">
                            当前已开放等级：{{ enabledLevelText }}。
                            <el-link class="ml-[6px]" type="primary" href="/site/member/level">前往会员等级配置</el-link>
                        </div>
                        <el-form-item>
                            <template #label><span>基础佣金算法</span><el-tooltip placement="top" :width="390" content="固定金额：每笔有效工单按固定元数计算。付款比例：按项目付款金额乘百分比计算。例如付款 5999 元、一级比例 5%，一级基础佣金为 299.95 元。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template>
                            <el-radio-group v-model="form.commission_type"><el-radio-button label="fixed">固定金额</el-radio-button><el-radio-button label="ratio">付款比例</el-radio-button></el-radio-group>
                        </el-form-item>
                        <div class="grid grid-cols-2 gap-x-[16px]">
                            <el-form-item>
                                <template #label><span>一级基础佣金</span><el-tooltip placement="top" :width="390" :content="form.eligibility_mode === 'all_member' ? '客户的直接推荐人获得。所有会员模式下系数固定 100%，实际佣金就是此处基础佣金。' : '客户的直接推荐人获得。实际佣金 = 此处基础佣金 × 推荐人当前会员等级的一级系数。'"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template>
                                <el-input-number v-model="form.first_value" :min="0" :max="form.commission_type === 'ratio' ? 100 : 999999.99" :precision="2" class="!w-full" /><div class="form-tip">{{ form.commission_type === 'ratio' ? '填写百分比，最大 100%' : '单位：元/笔' }}</div>
                            </el-form-item>
                            <el-form-item>
                                <template #label><span>二级基础佣金</span><el-tooltip placement="top" :width="410" :content="form.eligibility_mode === 'all_member' ? '关系示例：A 邀请 B，B 邀请 C；C 审核通过时 B 获一级、A 获二级，二者均按 100% 系数。' : '关系示例：A 邀请 B，B 邀请 C；C 审核通过时 B 获一级、A 获二级；A 的会员等级还必须开启二级分佣。'"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template>
                                <el-input-number v-model="form.second_value" :min="0" :max="form.commission_type === 'ratio' ? 100 : 999999.99" :precision="2" class="!w-full" /><div class="form-tip">填 0 表示该项目不发二级佣金</div>
                            </el-form-item>
                        </div>
                        <el-form-item>
                            <template #label><span>结算保护期</span><el-tooltip placement="top" :width="380" content="审核通过后先生成待结算佣金，保护期内发生退款会直接取消或减少佣金。填 0 表示审核通过后立即结算；建议保留 7 天以覆盖客户退出和退款。"><el-icon class="ml-[5px] cursor-help text-[#98a2b3]"><QuestionFilled /></el-icon></el-tooltip></template>
                            <el-input-number v-model="form.settle_days" :min="0" :max="365" class="!w-[220px]" /><span class="ml-[8px] text-[13px] text-[#667085]">天</span>
                        </el-form-item>
                        <el-checkbox v-model="form.approval_requires_payment_check" disabled>审核通过前必须确认已核对群内付款流水</el-checkbox>
                    </div>
                    <div class="setting-row"><div><div class="setting-name">项目状态</div><div class="setting-desc">草稿可保存不完整配置；启用前必须配置付款码、万能表单和资料审核员。</div></div><el-select v-model="form.status" class="!w-[140px]"><el-option label="草稿" :value="0" /><el-option label="启用" :value="1" /><el-option label="停用" :value="2" /></el-select></div>
                </section>
            </el-form>
            <template #footer><div class="flex justify-end gap-[10px]"><el-button @click="editor.visible = false">取消</el-button><el-button type="primary" :loading="editor.saving" @click="save">保存项目</el-button></div></template>
        </el-drawer>
        <spread-popup ref="spreadPopupRef" />
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox, type FormInstance } from 'element-plus'
import { QuestionFilled } from '@element-plus/icons-vue'
import { addProjectCenterProject, deleteProjectCenterProject, editProjectCenterProject, getProjectCenterMetadata, getProjectCenterProject, getProjectCenterProjects } from '@/addon/hsx_project_center/api'
import spreadPopup from '@/components/spread-popup/index.vue'
import ProjectAreaEligibilityConfig from '@/addon/hsx_project_center/components/ProjectAreaEligibilityConfig.vue'

const loading = ref(false), rows = ref<any[]>([]), formRef = ref<FormInstance>()
const query = reactive<any>({ keyword: '', status: '' }), page = reactive({ page: 1, limit: 15, total: 0 })
const metadata = reactive<any>({ forms: [], reviewers: [], diy_pages: [], member_levels: [] })
const editor = reactive({ visible: false, id: 0, saving: false })
const spreadPopupRef = ref<any>()
const emptyAreaEligibility = () => ({ enabled: 0, allowed_area_ids: [] as number[], title: '先查询您的地区是否可参与', tips: '选择门店所在省、市、区，确认可以参与后再付款。', button_text: '查询是否可以参加', eligible_text: '当前地区可以参加，请继续完成付款。', ineligible_text: '当前地区暂不在可参与范围内，请联系工作人员确认。', enabled_at: 0 })
const emptyContactGuide = () => ({ enabled: 0, mode: 'wecom_first', staff_uids: [] as number[], fallback_qrcode: '', title: '先添加项目顾问', tips: '添加后请发送“我要参与项目”，工作人员会协助建群、付款与后续办理。', greeting: '你好，我想咨询并参与这个项目。', button_text: '我已添加，继续付款' })
const emptyForm = () => ({ title: '', subtitle: '', cover: '', status: 0, sort: 0, intro_page_id: 0, form_id: undefined as any, payment_qrcode: '', payment_amount: 5999, payment_tips: '请长按识别付款码完成付款，保存完整截图并发送到客户群，再点击下一步填写资料。', reviewer_uids: [] as number[], area_eligibility_enabled: false, area_eligibility: emptyAreaEligibility(), contact_guide_enabled: false, contact_guide_mode: 'wecom_first', contact_guide_staff_uids: [] as number[], contact_guide_fallback_qrcode: '', contact_guide_title: '先添加项目顾问', contact_guide_tips: '添加后请发送“我要参与项目”，工作人员会协助建群、付款与后续办理。', contact_guide_greeting: '你好，我想咨询并参与这个项目。', contact_guide_button_text: '我已添加，继续付款', ai_enabled: false, ai_scene: 'hsx_project_center.customer_assistant', ai_title: 'AI 项目顾问', ai_welcome: '你可以直接问项目费用、准备资料、办理流程、审核和退款规则。', ai_suggestions_text: '这个项目适合我吗？\n需要准备哪些资料？\n付款后多久可以上线？\n审核不通过怎么办？\n不做了如何处理退款？', ai_knowledge: '', ai_voice_enabled: true, ai_auto_read: false, distribution_enabled: false, eligibility_mode: 'member_level', commission_type: 'fixed', first_value: 0, second_value: 0, settle_days: 7, approval_requires_payment_check: true, intro: '', steps_text: '', faqs_text: '' })
const form = reactive<any>(emptyForm())
const rules = { title: [{ required: true, message: '请输入项目名称', trigger: 'blur' }] }
const enabledLevelText = computed(() => {
    const levels = (metadata.member_levels || []).filter((item:any) => item.distribution_enabled)
    return levels.length ? levels.map((item:any) => `${item.level_name}（一级 ${item.first_coefficient}%${item.second_enabled ? ` / 二级 ${item.second_coefficient}%` : ''}）`).join('、') : '暂无，请先配置会员等级权益'
})

function pageRows(data:any) { return data?.data || data?.list || [] }
async function loadPage(toPage?:number) { if (toPage) page.page = toPage; loading.value = true; try { const res:any = await getProjectCenterProjects({ ...query, page: page.page, limit: page.limit }); rows.value = pageRows(res.data); page.total = Number(res.data?.total || 0) } finally { loading.value = false } }
function resetQuery() { query.keyword = ''; query.status = ''; loadPage(1) }
function resetForm() { Object.assign(form, emptyForm()) }
function splitFaqs(text:string) { return text.split('\n').map(line => line.trim()).filter(Boolean).map(line => { const [question, ...answer] = line.split('|'); return { question: question.trim(), answer: answer.join('|').trim() } }).filter(item => item.question && item.answer) }
async function openEditor(id = 0) { resetForm(); editor.id = id; if (id) { const res:any = await getProjectCenterProject(id); const data = res.data || {}, config = data.config_json || {}, distribution = config.distribution || {}, areaEligibility = { ...emptyAreaEligibility(), ...(config.area_eligibility || {}) }, contactGuide = { ...emptyContactGuide(), ...(config.contact_guide || {}) }; Object.assign(form, data, { area_eligibility_enabled: !!areaEligibility.enabled, area_eligibility: areaEligibility, contact_guide_enabled: !!contactGuide.enabled, contact_guide_mode: contactGuide.mode, contact_guide_staff_uids: contactGuide.staff_uids || [], contact_guide_fallback_qrcode: contactGuide.fallback_qrcode || '', contact_guide_title: contactGuide.title, contact_guide_tips: contactGuide.tips, contact_guide_greeting: contactGuide.greeting, contact_guide_button_text: contactGuide.button_text, ai_enabled: !!data.ai_enabled, distribution_enabled: !!data.distribution_enabled, intro: config.intro || '', steps_text: (config.steps || []).join('\n'), faqs_text: (config.faqs || []).map((item:any) => `${item.question}|${item.answer}`).join('\n'), ai_title: config.ai_title || 'AI 项目顾问', ai_welcome: config.ai_welcome || '你可以直接问项目费用、准备资料、办理流程、审核和退款规则。', ai_suggestions_text: (config.ai_suggestions || []).join('\n') || emptyForm().ai_suggestions_text, ai_knowledge: config.ai_knowledge || '', ai_voice_enabled: config.ai_voice_enabled !== 0, ai_auto_read: !!config.ai_auto_read, eligibility_mode: distribution.eligibility_mode || 'member_level', commission_type: distribution.commission_type || 'fixed', first_value: Number(distribution.first_value || 0), second_value: Number(distribution.second_value || 0), settle_days: Number(distribution.settle_days ?? 7), approval_requires_payment_check: distribution.approval_requires_payment_check !== 0 }) } editor.visible = true }
async function save() { await formRef.value?.validate(); if (Number(form.status) === 1 && !String(form.payment_qrcode || '').trim()) return ElMessage.warning('启用项目前请先上传客户付款码'); if (Number(form.status) === 1 && !Number(form.form_id || 0)) return ElMessage.warning('启用项目前请先选择万能表单'); if (Number(form.status) === 1 && !(form.reviewer_uids || []).length) return ElMessage.warning('启用项目前请至少选择一名资料审核员'); if (form.area_eligibility_enabled && !(form.area_eligibility?.allowed_area_ids || []).length) return ElMessage.warning('开启地区参与限制后，请至少选择一个可参与地区'); if (form.contact_guide_enabled && !String(form.contact_guide_fallback_qrcode || '').trim()) return ElMessage.warning('开启陌生客户联系流程后，请上传备用企微二维码'); if (form.contact_guide_enabled && form.contact_guide_mode === 'wecom_first' && !(form.contact_guide_staff_uids || []).length) return ElMessage.warning('企微优先模式请至少选择一名项目顾问'); if (form.distribution_enabled && Number(form.first_value || 0) <= 0 && Number(form.second_value || 0) <= 0) return ElMessage.warning('开启分销后，一级和二级基础佣金不能同时为 0'); editor.saving = true; try { const payload = { ...form, ai_enabled: Number(form.ai_enabled), ai_scene: 'hsx_project_center.customer_assistant', distribution_enabled: Number(form.distribution_enabled), config_json: { intro: form.intro.trim(), steps: form.steps_text.split('\n').map((item:string) => item.trim()).filter(Boolean), faqs: splitFaqs(form.faqs_text), ai_title: form.ai_title.trim(), ai_welcome: form.ai_welcome.trim(), ai_suggestions: form.ai_suggestions_text.split('\n').map((item:string) => item.trim()).filter(Boolean).slice(0, 8), ai_knowledge: form.ai_knowledge.trim(), ai_voice_enabled: Number(form.ai_voice_enabled), ai_auto_read: Number(form.ai_auto_read), area_eligibility: { ...form.area_eligibility, enabled: Number(form.area_eligibility_enabled) }, contact_guide: { enabled: Number(form.contact_guide_enabled), mode: form.contact_guide_mode, staff_uids: form.contact_guide_staff_uids, fallback_qrcode: form.contact_guide_fallback_qrcode, title: form.contact_guide_title.trim(), tips: form.contact_guide_tips.trim(), greeting: form.contact_guide_greeting.trim(), button_text: form.contact_guide_button_text.trim() }, distribution: { eligibility_mode: form.eligibility_mode, commission_type: form.commission_type, first_value: Number(form.first_value || 0), second_value: Number(form.second_value || 0), settle_days: Number(form.settle_days || 0), approval_requires_payment_check: 1 } } }; editor.id ? await editProjectCenterProject(editor.id, payload) : await addProjectCenterProject(payload); ElMessage.success('项目已保存'); editor.visible = false; await loadPage() } finally { editor.saving = false } }
async function remove(row:any) { await ElMessageBox.confirm(`确认删除“${row.title}”吗？已有客户资料的项目将不允许删除。`, '删除项目', { type: 'warning' }); await deleteProjectCenterProject(row.id); ElMessage.success('已删除'); await loadPage(1) }
function showEntry(row:any) { spreadPopupRef.value?.show('/addon/hsx_project_center/pages/project/detail', [{ name: 'id', value: row.id }], `${row.title}参与入口`, 'hsx_project_center') }
onMounted(async () => { const res:any = await getProjectCenterMetadata(); Object.assign(metadata, res.data || {}); await loadPage() })
</script>

<style scoped>
.form-section { margin-bottom: 18px; padding: 18px; border: 1px solid #e7ecf3; border-radius: 12px; background: #fff; }
.section-title { margin-bottom: 16px; padding-left: 10px; border-left: 3px solid var(--el-color-primary); color: #1d2939; font-size: 16px; font-weight: 600; }
.setting-row { display: flex; min-height: 62px; align-items: center; justify-content: space-between; gap: 20px; border-bottom: 1px solid #f0f2f5; }
.setting-row:last-child { border-bottom: 0; }
.setting-name { color: #344054; font-weight: 500; }.setting-desc { margin-top: 4px; color: #98a2b3; font-size: 12px; }
.distribution-config { margin: 12px 0 18px; padding: 16px; border: 1px solid #f5d08a; border-radius: 12px; background: #fffaf0; }
.form-tip { margin-top: 6px; color: #98a2b3; font-size: 12px; line-height: 18px; }
</style>
