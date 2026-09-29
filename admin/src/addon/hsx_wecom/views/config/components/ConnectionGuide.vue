<template>
    <section class="connection-guide" aria-label="企业微信接入操作引导">
        <div class="guide-heading"><div><strong>{{ platform ? '平台配置一次，本站客户分别授权' : '谁来操作 · 下一步做什么' }}</strong><p>{{ platform ? '同一套 SaaS 下的客户共用域名与服务商通道；不为每个客户另建通道或收集应用 Secret。' : provider ? '客户留在自己的企业微信。授权、员工绑定和管理端登录是三个独立步骤。' : '自建应用由甲方企业管理员提供参数，可自行填写，也可委托平台人员协助。' }}</p></div></div>
        <div class="guide-steps" :class="{ 'four-steps': !platform && provider }">
            <article v-for="(step, index) in steps" :key="step.title"><span>{{ index + 1 }}</span><div><strong>{{ step.title }}</strong><small>{{ step.owner }}</small><p>{{ step.action }}</p><el-button v-if="step.tab" type="primary" link @click="$emit('navigate', step.tab)">{{ step.button }}</el-button></div></article>
        </div>
        <details class="guide-details">
            <summary>查看准备清单、准确入口和排查方法</summary>
            <template v-if="platform">
                <dl>
                    <dt>① 你：准备资格与应用</dt><dd>登录 <a href="https://open.work.weixin.qq.com/" target="_blank" rel="noopener noreferrer">企业微信服务商后台</a> → 提供 SaaS 应用 → 创建应用。当前接入是标准第三方应用，不是“代开发模板”；可测试、审核与上线资格，以该后台提示为准。</dd>
                    <dt>② 你：提供平台凭据</dt><dd>从同一个应用获取 SuiteID / SuiteSecret，并配置回调 Token / EncodingAESKey。服务商企业 ID 属于你，不是每个客户的企业 ID。不要把密钥放到群聊或操作截图。</dd>
                    <dt>③ 技术：配置本页</dt><dd>平台 → 系统设置 → 企业微信服务商。填写本套 SaaS 的 HTTPS 根地址、后台管理小程序 AppID，然后先保存。客户销售小程序 AppID 不能代替管理小程序。</dd>
                    <dt>④ 技术：配置回调</dt><dd>把本页生成的“事件回调 URL”用于接收 SuiteTicket / 授权变更的指令回调；“授权回调 URL”用于安装授权返回。按官方页面完成回调、授权域名与小程序关联配置，不能仅填一个 AppID 就算完成。</dd>
                    <dt>⑤ 技术：验证运行</dt><dd>等待收到 SuiteTicket，再点“验证服务商通道”。这只验证服务商凭据；还需检查消息队列 / 补偿任务运行，再由一个客户授权、绑定员工、收通知并打开任务。</dd>
                </dl>
                <p class="guide-warning">“配置已保存”不等于接通。另一套独立服务器 / 域名应独立核对通道与回调，不能直接覆盖当前已工作的 Suite 配置。</p>
            </template>
            <template v-else-if="provider">
                <dl>
                    <dt>你需要准备</dt><dd>甲方企业微信管理员、本站管理员账号、需要接收通知的本站员工账号。员工需已加入甲方企业，并在应用可见范围内；不需要提交甲方 Secret。</dd>
                    <dt>甲方管理员</dt><dd>本站 → 企业微信 → 协同配置 → 接入与通知，点“一键授权企业微信”。在官方页面核对是自己的企业并授权应用。返回后检查“授权企业”和“已授权”，不要选到服务商自己的企业。</dd>
                    <dt>本站管理员</dt><dd>缺少员工时，先到 <router-link to="/site/auth/user">权限管理 → 管理员</router-link> 创建账号并配置所需业务权限。再到本页“接收员工”点“扫码绑定”，只把专属码或链接交给本人。</dd>
                    <dt>员工本人 + 管理员</dt><dd>员工在手机企业微信切换到甲方企业后扫码；管理员在弹窗核对企业与成员标识，再勾选并确认。扫码不是自动授权后台权限，也不替代管理小程序登录。</dd>
                    <dt>共同验收</dt><dd>打开员工的“通知”开关并保存，选择一人发测试通知。请本人确认收到卡片、点开后台工作台、能够登录；最后再用一笔受控业务待办验证正确任务与操作权限。</dd>
                </dl>
                <p class="guide-warning">同域名可以服务多个客户，但企业与员工按站点隔离。当前同一服务商应用下，一家企业只能关联一个站点；不要让不同客户绑定到服务商的企业。</p>
            </template>
            <template v-else>
                <dl>
                    <dt>甲方企业管理员准备</dt><dd>登录 <a href="https://work.weixin.qq.com/" target="_blank" rel="noopener noreferrer">企业微信管理后台</a>，从“我的企业”获取企业 ID；从“应用管理 → 自建应用”获取对应 AgentId 和 Secret，配置接收员工的应用可见范围。</dd>
                    <dt>平台人员协助配置</dt><dd>在下方填写甲方自己的应用参数，管理小程序 AppID 使用本套 SaaS 的管理端 AppID。请在企业微信后台确认小程序关联、可信域名、服务器可信 IP 等要求；主体 / 域名校验不满足时联系平台处理，不要绕过。</dd>
                    <dt>绑定与测试</dt><dd>保存配置后测试连接，再到“接收员工”填写甲方企业通讯录内对应员工的 UserID，开启通知并保存。自建模式不使用服务商扫码绑定。</dd>
                </dl>
                <p class="guide-warning">Secret 仅在可信后台页面提交。自建模式与服务商授权不能混填；切换模式后要重新核对接收员工和测试结果。</p>
            </template>
            <div class="help-grid"><div><strong>授权卡住 / 企业不对</strong><p>由甲方管理员核对企业和应用权限；平台处理服务商、回调与域名配置。</p></div><div><strong>已绑定但收不到</strong><p>核对通知开关、应用可见范围和业务责任人；到“消息日志”查看失败原因。</p></div><div><strong>收到但打不开 / 无权限</strong><p>先看管理小程序是否关联和发布，再核对本站账号、站点与业务权限。由平台与本站管理员分别处理。</p></div></div>
        </details>
    </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ platform: boolean; provider: boolean }>()
defineEmits<{ (event: 'navigate', tab: string): void }>()
const steps = computed(() => props.platform ? [
    { title: '准备服务商应用', owner: '平台负责人', action: '核对应用资格、凭据与管理小程序' },
    { title: '保存并配置回调', owner: '平台技术人员', action: '配置本套 SaaS；同域客户无需重复填写' },
    { title: '验证并试运行', owner: '平台 + 一位客户', action: '凭据验证后，再验收员工真实收信与跳转' }
] : props.provider ? [
    { title: '授权客户企业', owner: '甲方企业管理员', action: '核对企业身份并安装授权', tab: 'config', button: '接入与通知' },
    { title: '扫码绑定员工', owner: '本站管理员 + 员工', action: '本人扫码、管理员核对确认', tab: 'staff', button: '接收员工' },
    { title: '真实通知测试', owner: '本站管理员 + 员工', action: '收卡片，点开管理端工作台', tab: 'staff', button: '测试通知' },
    { title: '验收业务待办', owner: '业务负责人', action: '分配测试任务，核对详情与操作权限', tab: 'messages', button: '消息日志' }
] : [
    { title: '提供企业应用参数', owner: '甲方企业管理员', action: '企业 ID、AgentId、Secret 与可见范围' },
    { title: '保存并测试连接', owner: '甲方或平台协助', action: '核对域名、IP、小程序关联' },
    { title: '绑定与真实测试', owner: '本站管理员 + 员工', action: '填写对应 UserID，收信并打开任务', tab: 'staff', button: '接收员工' }
])
</script>

<style scoped lang="scss">
.connection-guide { margin: 16px 0; padding: 16px; border: 1px solid var(--el-border-color-lighter); border-radius: 10px; background: var(--el-fill-color-extra-light); }.guide-heading strong { font-size: 14px; }.guide-heading p { color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; margin-top: 5px; }.guide-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 14px; }.guide-steps.four-steps { grid-template-columns: repeat(4, minmax(0, 1fr)); }.guide-steps article { display: flex; gap: 8px; align-items: flex-start; padding: 12px; border-radius: 8px; background: #fff; }.guide-steps article > span { display: grid; place-items: center; width: 22px; height: 22px; flex: 0 0 22px; border-radius: 50%; color: var(--el-color-primary); background: var(--el-color-primary-light-9); font-size: 12px; }.guide-steps strong, .guide-steps small { display: block; }.guide-steps strong { font-size: 13px; }.guide-steps small, .guide-steps p { color: var(--el-text-color-secondary); font-size: 12px; line-height: 1.6; margin-top: 4px; }.guide-details { margin-top: 12px; }.guide-details summary { cursor: pointer; font-size: 13px; color: var(--el-color-primary); padding: 6px 0; }.guide-details dl { display: grid; grid-template-columns: 180px minmax(0, 1fr); gap: 10px 12px; font-size: 13px; line-height: 1.7; margin-top: 12px; }.guide-details dt { font-weight: 500; }.guide-details dd { margin: 0; }.guide-details a { color: var(--el-color-primary); text-decoration: underline; }.guide-warning { margin: 14px 0; padding: 10px 12px; background: var(--el-color-warning-light-9); color: var(--el-text-color-regular); border-radius: 6px; font-size: 12px; line-height: 1.7; }.help-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; border-top: 1px solid var(--el-border-color-lighter); padding-top: 12px; font-size: 12px; }.help-grid p { margin-top: 5px; color: var(--el-text-color-secondary); line-height: 1.6; }
@media (max-width: 1100px) { .guide-steps.four-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 700px) { .guide-steps, .guide-steps.four-steps, .help-grid { grid-template-columns: 1fr; }.guide-details dl { grid-template-columns: 1fr; gap: 5px; }.guide-details dd { margin-bottom: 8px; } }
</style>
