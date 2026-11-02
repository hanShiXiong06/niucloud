<template>
    <div class="lead-card merchant-portal-entry">
        <div class="portal-heading"><div><h3>把工单入口交给商户</h3><p class="lead-muted">商户使用本站会员身份，不需要另开后台账号，也不会接触 ERP 密码。</p></div><el-button @click="noticeVisible = true">通知渠道配置</el-button></div>
        <ol class="portal-steps"><li><strong>先绑定会员</strong><p>在“合作商户”中绑定这家门店实际使用的本站会员，核对会员手机号。</p><el-button link type="primary" @click="$emit('manage-merchants')">去合作商户绑定</el-button></li><li><strong>让商户本人登录</strong><p>在装修首页或会员中心添加链接，选择“商户客资协作 → 商户工作台（我的工单）”，保存并发布会员端。</p></li><li><strong>接单后，反馈成单或未成单</strong><p>客户直接向门店付款；分享收益可稍后填写。没有收到通知，也可主动打开工作台查工单。</p></li></ol>
        <div class="portal-entry-block"><div class="portal-entry-title"><strong>小程序入口</strong><el-button type="primary" plain @click="copyText(miniPath)">复制小程序路径</el-button></div><el-input :model-value="miniPath" readonly aria-label="商户工作台小程序路径" /><p class="lead-muted">请将本插件会员端加入小程序并重新发布。消息点击可定位到对应工单，登录与工单归属仍由接口校验。</p></div>
        <div class="portal-entry-block"><div class="portal-entry-title"><strong>H5 入口</strong><el-button :loading="loadingUrl" @click="loadUrl">刷新本站地址</el-button></div><template v-if="h5Url"><el-input :model-value="h5Url" readonly aria-label="商户工作台 H5 地址" /><div class="lead-actions portal-link-actions"><el-button @click="copyText(h5Url)">复制 H5 链接</el-button><a :href="h5Url" target="_blank" rel="noopener noreferrer">在新窗口打开</a></div><p class="lead-muted">地址来自框架的本站 H5 配置；显示地址不代表已验证部署或完成商户登录。分享前请用真实会员验收。</p></template><el-alert v-else :title="urlMessage || '尚无可对外分享的 HTTPS H5 入口。请先配置并部署本站 H5；本地地址不会作为客户链接提供。'" type="info" :closable="false" show-icon /></div>
        <el-collapse><el-collapse-item title="商户看不到工单时，按这个顺序核对"><ul class="portal-checks"><li>会员是否绑定到正确且启用的合作商户。</li><li>商户是否登录了同一站点、同一会员，而不是管理员账号。</li><li>工单是否已实际派给这家门店；被转派的原商户无权继续查看新的客户资料。</li><li>小程序 / H5 是否发布了本插件页面；通知入口不能代替页面部署。</li></ul></el-collapse-item></el-collapse>
        <el-alert title="这里不提供管理员代登录或切换查看其他商户。二维码、链接和工单编号都不是访问授权，后台仍按当前会员校验门店归属。" type="info" :closable="false" show-icon class="portal-boundary" />
        <NoticeSettings v-model="noticeVisible" />
    </div>
</template>
<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { useClipboard } from '@vueuse/core'
import { ElMessage } from 'element-plus'
import { getNoticeConfig } from '../../api'
import NoticeSettings from './NoticeSettings.vue'
defineEmits<{ (event: 'manage-merchants'): void }>()
const miniPath = 'addon/hsx_lead/pages/workbench/index'
const noticeVisible = ref(false), loadingUrl = ref(false), h5Url = ref(''), urlMessage = ref('')
const { copy, isSupported } = useClipboard()
let sequence = 0
function publicEntry(value: unknown): string {
    if (typeof value !== 'string' || !value.trim()) return ''
    try {
        const url = new URL(value)
        const host = url.hostname.toLowerCase(), pieces = host.split('.').map(Number)
        const privateIpv4 = pieces.length === 4 && pieces.every(Number.isInteger) && (pieces[0] === 10 || pieces[0] === 127 || pieces[0] === 0 || pieces[0] === 192 && pieces[1] === 168 || pieces[0] === 172 && pieces[1] >= 16 && pieces[1] <= 31 || pieces[0] === 169 && pieces[1] === 254)
        if (url.protocol !== 'https:' || url.username || url.password || url.search || url.hash || host === 'localhost' || host.endsWith('.localhost') || host.endsWith('.local') || host.includes(':') || privateIpv4 || !url.pathname.endsWith('/' + miniPath)) return ''
        return url.href
    } catch { return '' }
}
async function loadUrl() {
    const token = ++sequence; loadingUrl.value = true; urlMessage.value = ''
    try { const result = await getNoticeConfig(); if (token !== sequence) return; const entry = result.data?.portal_entry || {}; h5Url.value = entry.h5_ready === true ? publicEntry(entry.h5_url) : ''; if (!h5Url.value) urlMessage.value = entry.message || '尚未返回本站可公开分享的 HTTPS H5 地址；请先完成 H5 域名与页面部署。本地地址不会被发给客户。' }
    catch { if (token === sequence) { h5Url.value = ''; urlMessage.value = '暂未读到本站 H5 地址，请核对站点配置与当前岗位权限。仍可复制小程序路径。' } }
    finally { if (token === sequence) loadingUrl.value = false }
}
async function copyText(value: string) {
    if (!value) return
    if (!isSupported.value) { ElMessage.warning('当前浏览器不支持复制，请在上方文本框选中后手动复制'); return }
    try { await copy(value); ElMessage.success('已复制入口；商户仍需使用绑定会员登录') }
    catch { ElMessage.warning('复制未完成，请手动选择文本复制') }
}
onMounted(loadUrl)
onBeforeUnmount(() => { sequence++ })
</script>
<style scoped>
.portal-heading, .portal-entry-title { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; }
.portal-heading h3 { margin: 0; }.portal-heading p { margin: 8px 0 0; line-height: 1.7; }
.portal-steps { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; padding: 20px 0 22px 20px; margin: 20px 0; border-bottom: 1px solid var(--el-border-color-lighter); }
.portal-steps li { padding-right: 10px; }.portal-steps p { margin: 8px 0; color: var(--el-text-color-regular); line-height: 1.8; }
.portal-entry-block { padding: 0 0 20px; margin-bottom: 20px; border-bottom: 1px solid var(--el-border-color-lighter); }.portal-entry-title { align-items: center; margin-bottom: 12px; }.portal-entry-block p { margin-bottom: 0; line-height: 1.8; }
.portal-link-actions { margin-top: 12px; }.portal-link-actions a { color: var(--el-color-primary); text-decoration: none; }.portal-checks { padding-left: 20px; line-height: 2; color: var(--el-text-color-regular); }.portal-boundary { margin-top: 20px; }
@media (max-width: 800px) { .portal-steps { grid-template-columns: 1fr; gap: 18px; }.portal-heading { flex-direction: column; gap: 12px; } }
</style>
