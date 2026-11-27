<template>
    <el-drawer :model-value="modelValue" title="上门取件 · 开通与使用指引" size="min(760px, 100vw)" @update:model-value="$emit('update:modelValue', $event)">
        <div class="pickup-guide">
            <el-alert title="这是上门取件，不是电子面单。无需购买打印机；终端客户不选择服务商或快递公司。" type="info" :closable="false" show-icon />
            <h3>先明确：谁负责哪一步</h3>
            <el-table :data="roles" border size="small">
                <el-table-column prop="role" label="角色" width="105" />
                <el-table-column prop="task" label="要做的事" min-width="180" />
                <el-table-column prop="result" label="完成后得到什么" min-width="160" />
            </el-table>
            <el-collapse v-model="opened" class="guide-sections">
                <el-collapse-item title="1. 开通账号：我到底要向快递100要什么？" name="account">
                    <p>由本站管理员准备本站的企业账号，不共用其他站点账号。告诉客户经理：需要「上门取件 / 寄件接口」，不是只开通查物流，也不需要电子面单。</p>
                    <div class="sample"><strong>可以直接发送给客户经理</strong><p>我们做手机回收上门取件，每个站点独立结算。请确认本账号开通的线上 / 线下寄件模式、承运商和具体产品、寄付 / 到付、计费标准与保价 / 禁限寄要求，并提供寄件 Key、Secret 的获取入口。有多个合作渠道时，请提供 channelSw；我们还需要沙箱账号和测试回调指引。</p></div>
                    <ul><li>Key：企业管理后台 → 我的信息 → 企业信息。</li><li>Secret：对应寄件服务的企业管理后台获取，找不到时联系客户经理；不要拿查询接口参数代替。</li><li>线上模式：与快递100结算，需按合作规则准备余额；线下模式：向快递员支付。本站不代充值。</li><li>正式凭证和沙箱凭证分开使用；页面显示 ****** 表示已保存，原样保留不会改密钥。</li></ul>
                    <div class="guide-links"><a :href="links.credentials" target="_blank" rel="noopener noreferrer">凭证获取图文</a><a :href="links.opening" target="_blank" rel="noopener noreferrer">寄件开通 / 调试说明</a><a :href="links.sandbox" target="_blank" rel="noopener noreferrer">申请及使用沙箱</a></div>
                </el-collapse-item>
                <el-collapse-item title="2. 选择公司和产品：名称从哪里来？" name="product">
                    <p>本页下拉来自插件维护的快递100官方参考目录，核对日期 {{ catalog.verified_at || '2026-09-29' }}。例如线上顺丰可选「顺丰标快 / 顺丰特快」，京东可选「特惠送」。公司编码自动带入，不需要手写。</p>
                    <el-alert title="官方目录支持 ≠ 本站账号已开通 ≠ 当前地址可上门。产品权限仍需客户经理确认，实际预约以服务商受理和回调为准。" type="warning" :closable="false" />
                    <p>线下文档未提供逐公司的特约产品表，当前只提供文档规定的默认「标准快递」，不能把线上清单直接当成线下已开通清单。需要其他特约产品时，请提交官方产品参数，由部署人员更新插件目录。</p>
                    <p>切换模式或快递公司后要重新选产品；系统不会悄悄改成另一家公司、另一产品或付款方。线上只能寄付；线下圆通、中通也不支持到付。</p>
                    <p>「重新读取目录」读取本站插件字典，不是联网同步账号授权。公开寄件文档中未找到账号已开通产品清单接口，不能把报价接口当作授权查询。</p>
                    <div class="guide-links"><a :href="links.online" target="_blank" rel="noopener noreferrer">线上官方目录 / 参数</a><a :href="links.offline" target="_blank" rel="noopener noreferrer">线下官方公司 / 参数</a></div>
                    <div v-for="mode in catalog.modes || []" :key="mode.value" class="unsupported">
                        <p v-for="carrier in mode.carriers.filter((item: any) => item.disabled)" :key="carrier.code">{{ carrier.name }}：{{ carrier.reason }}</p>
                    </div>
                </el-collapse-item>
                <el-collapse-item title="3. 部署人员：公开回调、通知模板怎么配？" name="deploy">
                    <p>回调地址：<code>https://本站域名/api/recycle/express/kuaidi100_push</code>。填写本站正式 HTTPS 地址，不能用 localhost / 内网地址；不要手工追加 record_id。</p>
                    <p>系统下单时自动补充本单标识并独立验签。公开接口必须可从外网访问，不得被登录页、验证码、代理重定向拦截。保存配置只检查格式，不会证明回调已经可达。</p>
                    <p><strong>通知使用牛云原生 notice：</strong>小程序模板与字段由插件固定提供。管理员到「渠道 → 微信小程序 → 消息模板」找到「回收预约取件状态通知」，获取模板后去「设置 → 消息管理」开启小程序通知。不需要填写模板 ID、字段映射，也没有第二套保存入口。</p>
                    <el-alert title="小程序已提供固定模板定义，复用回收插件已有公共编号 30171；本站类目及模板权限是否满足，以微信实际获取结果为准。公众号模板仍待匹配，保持关闭即可，不影响已就绪的小程序渠道。" type="info" :closable="false" show-icon />
                    <p>模板显示在列表中不代表获取成功。获取失败时查看框架返回的原因，由部署人员核对账号类目和权限，不要求管理员猜编号。客户还须主动订阅；只有真实客户手机收到并打开本人订单，才算完成通知验收。</p>
                    <p>业务只调用框架 NoticeService，由 NoticeData 钩子提供订单变量和收件人，执行记录进入系统通知日志。已有日志或「已交给通知框架」不代表微信受理、送达或已读。通知失败不回滚回收单，客户仍可进入订单查看取件安排。</p>
                </el-collapse-item>
                <el-collapse-item title="4. 管理员：按什么顺序验收，什么时候启用？" name="acceptance">
                    <ol><li>先停用新预约，准备本站账号、模式、产品、付款方和回调。</li><li>保存 →「检查已保存配置」只检验格式，不创建订单、不扣运费。</li><li>部署人员使用专属沙箱验证预约、失败、回调、取消与通知数据。沙箱成功不等于正式账号有权限。</li><li>管理员在框架获取小程序取件模板、开启消息，再由客户主动授权并实机验收。公众号尚未开放，不需开启；获取失败时通过订单页和人工联系兜底。</li><li>确认正式账号权限、结算和实际地址后，由管理员授权一笔实单：核对公司 / 产品 / 付款方及费用，真实预约可能产生费用。</li><li>客户手机核对预约结果；检查快递员和时间回调、通知实际接收、取消及费用结果。未通过的项目必须明确交接，不宣称全链路完成。</li></ol>
                    <p>换服务商只影响新预约。旧单继续用原渠道、原环境和原账号处理；不要删掉旧账号凭证。快递鸟、快递公司直连尚未完成适配验收，不会显示成可用渠道。</p>
                </el-collapse-item>
                <el-collapse-item title="5. 客户提交后，成功或失败分别怎么办？" name="operation">
                    <el-table :data="outcomes" border size="small"><el-table-column prop="state" label="当前结果" width="112" /><el-table-column prop="action" label="客户 / 管理员下一步" /></el-table>
                    <p>客户订单页展示实际承运公司、预约时段及快递员姓名 / 电话；服务商未返回的显示「待安排」，不编造。快递公司的短信不能替代本站消息，本站消息未送达时以订单页和工作人员核实为兜底。</p>
                </el-collapse-item>
            </el-collapse>
        </div>
    </el-drawer>
</template>
<script setup lang="ts">
import { ref } from 'vue'
defineProps<{ modelValue: boolean; catalog: any }>()
defineEmits<{ (event: 'update:modelValue', value: boolean): void }>()
const opened = ref(['account'])
const links = {
    credentials: 'https://api.kuaidi100.com/document/chakankey',
    opening: 'https://api.kuaidi100.com/document/shang-jia-ji-jian-ce-shi',
    sandbox: 'https://api.kuaidi100.com/document/jijianceshipingtai',
    online: 'https://api.kuaidi100.com/document/603cb649a62a19500e19866b',
    offline: 'https://api.kuaidi100.com/document/cduan-ji-jian-jie-kou-wen-dang'
}
const roles = [
    { role: '本站管理员', task: '准备账号、确认产品和费用，设置固定公司与付款方', result: '明确本站实际可用服务' },
    { role: '开发 / 部署', task: '核实固定通知模板、配置公开回调，验证框架链路和真实回调', result: '未开通项明确，不让管理员手填映射' },
    { role: '客户', task: '提交回收单、确认寄件地址与预约时间，同意订阅（可选）', result: '在订单中看安排，不用选择渠道' },
    { role: '业务 / 客服', task: '处理异常、核实未知预约结果，必要时协助自行寄件', result: '客户有下一步，不重复叫件' }
]
const outcomes = [
    { state: '预约已受理', action: '展示返回安排；等待实际揽收。受理不代表快递员已经取件。' },
    { state: '明确失败', action: '显示服务商原因；客户可自行寄件并登记物流，或联系工作人员处理。先确认没有有效预约。' },
    { state: '结果待核实', action: '超时可能已下单。管理员先到预约记录和服务商后台核查，不可立即重试或自行重复叫件。' },
    { state: '取消处理中', action: '等待服务商取消结果；请求发出不等于已经取消，也不等于已经退款。' },
    { state: '通知未就绪', action: '到小程序消息模板页获取取件模板，再开启系统消息；获取失败按微信返回原因核对账号权限。公众号保持关闭，使用订单页和人工联系兜底。' },
    { state: '送达未确认', action: '查看系统通知执行记录及客户授权；框架执行不代表客户收到，不为补消息重新创建取件单。' }
]
</script>
<style scoped lang="scss">
.pickup-guide{color:#334155;font-size:13px;line-height:1.85}.pickup-guide h3{font-size:15px;margin:24px 0 12px}.pickup-guide p{margin:10px 0}.pickup-guide ul,.pickup-guide ol{padding-left:22px}.pickup-guide li{margin:8px 0}.pickup-guide code{word-break:break-all;color:#334155;background:#f1f5f9;padding:2px 5px;border-radius:4px}.guide-sections{margin-top:22px}.guide-sections :deep(.el-collapse-item__header){font-weight:600;height:auto;min-height:52px;line-height:1.6;padding:10px 0}.sample{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:12px 16px}.sample p{margin-bottom:0}.guide-links{display:flex;flex-wrap:wrap;gap:16px;margin:14px 0}.guide-links a{color:var(--el-color-primary);text-decoration:none}.unsupported{color:#64748b;font-size:12px}
</style>
