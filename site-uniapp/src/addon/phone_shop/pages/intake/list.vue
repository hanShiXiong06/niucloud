<template>
    <view class="intake-page" :style="themeColor()">
        <view class="intake-head">
            <view class="intake-title-row">
                <view><text class="intake-title">商城资料运营</text><text class="intake-subtitle">核对分类、规格与标签后发布商城</text></view>
                <view class="role-badge"><u-icon name="account" color="var(--primary-color)" size="15" /><text>运营专员</text></view>
            </view>
            <view class="search-box">
                <u-icon name="search" color="#94a3b8" size="18" />
                <input v-model="keyword" class="search-input" placeholder="搜索型号或资产 ID" confirm-type="search" @confirm="reload" />
                <u-icon v-if="keyword" name="close-circle-fill" color="#cbd5e1" size="17" @click="keyword='';reload()" />
            </view>
            <view class="status-tabs">
                <view v-for="item in statusTabs" :key="item.value" class="status-tab" :class="{ active: status === item.value }" @click="status=item.value;reload()">{{ item.label }}</view>
            </view>
        </view>

        <z-paging ref="pagingRef" v-model="list" :fixed="true" :default-page-size="15" :paging-style="pagingStyle" @query="queryList">
            <template #empty><u-empty mode="list" text="暂无待整理商品" /></template>
            <view v-if="policy.can_phone_shop_operate !== 1" class="policy-tip">
                <u-icon name="info-circle" color="#64748b" size="17" />
                <text>当前配置由 ERP 库存人员一次完成资料并直接上架，本页仅查看历史交接。</text>
            </view>
            <view class="intake-list">
                <view v-for="row in list" :key="row.intake_id" class="intake-card">
                    <view class="intake-card__main">
                        <image v-if="firstImage(row)" class="device-cover" :src="imageUrl(firstImage(row))" mode="aspectFill" />
                        <view v-else class="device-cover empty"><u-icon name="photo" color="#cbd5e1" size="28" /></view>
                        <view class="device-content">
                            <view class="device-title-row"><text class="device-title">{{ row.model_name || '未命名设备' }}</text><u-tag :text="row.status_name || statusText(row.status)" :type="row.status === 1 ? 'success' : row.status === 2 ? 'info' : 'warning'" plain plainFill size="mini" /></view>
                            <text class="device-spec">{{ [row.memory,row.color,row.condition_grade].filter(Boolean).join(' · ') || '待核对规格' }}</text>
                            <text class="device-id">IMEI {{ row.imei || '-' }} · ERP #{{ row.erp_asset_id }}</text>
                            <view class="device-price"><text>销售价</text><text class="price">¥{{ money(row.sale_price) }}</text><text>成本 ¥{{ money(row.cost_price) }}</text></view>
                        </view>
                    </view>
                    <view class="card-actions">
                        <text class="handoff-tip">图片、价格和质检报告已由 ERP 交接</text>
                        <view v-if="row.status === 0 && policy.can_phone_shop_operate === 1" class="operate-btn" @click="openEditor(row)">整理资料并上架</view>
                        <text v-else class="finished-text">{{ row.status === 1 ? '已完成发布' : '无需处理' }}</text>
                    </view>
                </view>
            </view>
        </z-paging>

        <u-popup :show="editorVisible" mode="bottom" round="20" :closeOnClickOverlay="!saving" @close="closeEditor">
            <view class="editor-popup">
                <view class="editor-head"><view><text class="editor-title">整理商城资料</text><text class="editor-subtitle">核对信息，发布后记录渠道映射</text></view><u-icon name="close" color="#94a3b8" size="20" @click="closeEditor" /></view>
                <scroll-view scroll-y class="editor-body">
                    <view class="editor-device">
                        <image v-if="firstImage(editingRow)" class="editor-cover" :src="imageUrl(firstImage(editingRow))" mode="aspectFill" />
                        <view><text class="editor-model">{{ editingRow?.model_name || '-' }}</text><text class="editor-meta">{{ editingRow?.imei || '-' }} · 销售价 ¥{{ money(form.price) }}</text></view>
                    </view>
                    <view class="form-card">
                        <view class="form-row"><text class="form-label required">商品标题</text><input v-model="form.goods_name" class="form-input" placeholder="请输入对外商品标题" /></view>
                        <view class="form-row"><text class="form-label">副标题</text><input v-model="form.sub_title" class="form-input" placeholder="选填，一句话卖点" /></view>
                        <category-popup v-model="form.category_id" label="商城分类" :createable="false" @change="onCategoryChange" />
                        <view class="form-row"><text class="form-label">规格</text><input v-model="form.memory" class="form-input" placeholder="如 256G" /></view>
                        <view class="form-row"><text class="form-label">成色</text><input v-model="form.condition_grade" class="form-input" placeholder="如 99新" /></view>
                    </view>
                    <view class="form-card">
                        <view class="section-title">质检与展示说明</view>
                        <textarea v-model="form.goods_desc" class="desc-input" placeholder="质检报告已自动带入，可补充对外展示说明" />
                    </view>
                    <view class="editor-safe-space" />
                </scroll-view>
                <view class="editor-foot"><view class="publish-btn"><u-button type="primary" shape="circle" :loading="saving" text="确认发布商城" @click="submit" /></view></view>
            </view>
        </u-popup>
    </view>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { img } from '@/utils/common'
import { buildMobileDeviceIntake, getMobileDeviceIntakeList, getMobileDeviceIntakePolicy, previewMobileDeviceIntake } from '@/addon/phone_shop/api/device_intake'
import CategoryPopup from '@/addon/phone_shop/components/category-popup.vue'

const pagingRef = ref<any>(null)
const list = ref<any[]>([])
const keyword = ref('')
const status = ref<any>(0)
const policy = ref<any>({ can_phone_shop_operate: 1 })
const editorVisible = ref(false)
const saving = ref(false)
const editingRow = ref<any>(null)
const pagingStyle = { top: '232rpx', background: '#f6f7f9' }
const statusTabs = [{ label:'待整理', value:0 }, { label:'已上架', value:1 }, { label:'全部', value:'' }]
const defaultForm = () => ({ intake_id:0, goods_name:'', sub_title:'', category_id:'', goods_category:[] as number[], brand_id:0, label_ids:[] as number[], service_ids:[] as number[], memory:'', condition_grade:'', delivery_type:['express'], price:0, market_price:0, cost_price:0, goods_desc:'', status:1 })
const form = ref<any>(defaultForm())

function reload() { pagingRef.value?.reload() }
async function queryList(pageNo:number,pageSize:number) {
    try {
        if (pageNo === 1) {
            const p:any = await getMobileDeviceIntakePolicy()
            policy.value = p?.data || { can_phone_shop_operate:1 }
        }
        const res:any = await getMobileDeviceIntakeList({ model_name:keyword.value, status:status.value, page:pageNo, limit:pageSize })
        pagingRef.value?.complete(res?.data?.data || [])
    } catch { pagingRef.value?.complete(false) }
}
function imageList(row:any):string[] { const raw=row?.images; if(Array.isArray(raw)) return raw.filter(Boolean); try { const d=JSON.parse(raw||'[]'); if(Array.isArray(d)) return d.filter(Boolean) } catch {} return String(raw||'').split(',').map(v=>v.trim()).filter(Boolean) }
function firstImage(row:any) { return imageList(row)[0] || '' }
function imageUrl(url:string) { return img(url) }
function money(v:any) { return Number(v||0).toFixed(2) }
function statusText(v:any) { return Number(v)===1?'已上架':Number(v)===2?'已忽略':'待整理' }
async function openEditor(row:any) {
    editingRow.value=row; form.value={ ...defaultForm(), intake_id:Number(row.intake_id), goods_name:row.model_name||'', memory:row.memory||'', condition_grade:row.condition_grade||'', price:Number(row.sale_price||0), cost_price:Number(row.cost_price||0) }; editorVisible.value=true
    try { const res:any=await previewMobileDeviceIntake(Number(row.intake_id)); const m=res?.data||{}; const category=(m.goods_category||[]).map(Number).filter(Boolean); form.value={ ...form.value, category_id:category.length?category[category.length-1]:form.value.category_id, goods_category:category.length?category:form.value.goods_category, goods_name:m.goods_name||form.value.goods_name, sub_title:m.sub_title||'', memory:m.memory_group||form.value.memory, condition_grade:m.condition_grade||form.value.condition_grade, label_ids:m.label_ids||[], service_ids:m.service_ids||[], delivery_type:(m.delivery_type||[]).length?m.delivery_type:['express'], price:Number(m.price||form.value.price), goods_desc:m.qc_report?.text||form.value.goods_name } } catch {}
}
function onCategoryChange(payload:any) { form.value.category_id=Number(payload?.category_id||0); form.value.goods_category=(payload?.category_path||[]).map(Number).filter(Boolean) }
function closeEditor() { if(saving.value)return; editorVisible.value=false; editingRow.value=null }
async function submit() {
    if(!form.value.goods_name.trim()) return uni.showToast({ title:'请填写商品标题', icon:'none' })
    if(!form.value.goods_category.length) return uni.showToast({ title:'请选择商城分类', icon:'none' })
    saving.value=true
    try { await buildMobileDeviceIntake({ ...form.value }); uni.showToast({ title:'已发布商城', icon:'success' }); editorVisible.value=false; reload() } finally { saving.value=false }
}
</script>

<style scoped lang="scss">
.intake-page { min-height:100vh; background:#f6f7f9; color:#0f172a; }
.intake-head { position:fixed; z-index:20; top:0; left:0; right:0; padding:22rpx 24rpx 14rpx; background:#f6f7f9; box-sizing:border-box; }
.intake-title-row { display:flex; align-items:center; justify-content:space-between; gap:20rpx; }
.intake-title,.intake-subtitle { display:block; }.intake-title { font-size:32rpx; font-weight:750; }.intake-subtitle { margin-top:4rpx; color:#94a3b8; font-size:20rpx; }
.role-badge { display:flex; align-items:center; gap:6rpx; padding:10rpx 14rpx; border-radius:20rpx; background:#eef6ff; color:var(--primary-color); font-size:21rpx; }
.search-box { height:70rpx; margin-top:16rpx; padding:0 22rpx; border-radius:35rpx; background:#fff; display:flex; align-items:center; gap:12rpx; }.search-input { flex:1; min-width:0; font-size:25rpx; }
.status-tabs { display:flex; gap:12rpx; margin-top:14rpx; }.status-tab { padding:8rpx 24rpx; border-radius:24rpx; background:#fff; color:#64748b; font-size:23rpx; }.status-tab.active { background:var(--primary-color); color:#fff; font-weight:650; }
.policy-tip { display:flex; gap:10rpx; margin:16rpx 24rpx 0; padding:16rpx 18rpx; border-radius:12rpx; background:#f1f5f9; color:#64748b; font-size:21rpx; line-height:1.5; }
.intake-list { padding:18rpx 24rpx 40rpx; }.intake-card { margin-bottom:16rpx; padding:22rpx; border-radius:18rpx; background:#fff; box-shadow:0 5rpx 18rpx rgba(15,23,42,.035); }
.intake-card__main { display:flex; gap:18rpx; }.device-cover { width:136rpx; height:136rpx; flex-shrink:0; border-radius:14rpx; background:#f1f5f9; }.device-cover.empty { display:flex; align-items:center; justify-content:center; }
.device-content { flex:1; min-width:0; }.device-title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:10rpx; }.device-title { flex:1; min-width:0; font-size:27rpx; font-weight:700; line-height:1.4; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.device-spec,.device-id { display:block; margin-top:7rpx; color:#64748b; font-size:21rpx; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }.device-id { color:#94a3b8; }.device-price { display:flex; align-items:baseline; gap:9rpx; margin-top:10rpx; color:#94a3b8; font-size:20rpx; }.device-price .price { color:#f97316; font-size:27rpx; font-weight:750; }
.card-actions { display:flex; align-items:center; justify-content:space-between; gap:12rpx; margin-top:18rpx; padding-top:16rpx; border-top:1rpx solid #eef2f7; }.handoff-tip { flex:1; min-width:0; color:#94a3b8; font-size:19rpx; }.operate-btn { flex-shrink:0; padding:12rpx 18rpx; border-radius:10rpx; background:#eff6ff; color:#2563eb; font-size:22rpx; font-weight:650; }.finished-text { color:#16a34a; font-size:21rpx; }
.editor-popup { height:88vh; background:#f6f7f9; display:flex; flex-direction:column; }.editor-head { padding:26rpx 30rpx 20rpx; background:#fff; display:flex; align-items:flex-start; justify-content:space-between; }.editor-title,.editor-subtitle { display:block; }.editor-title { font-size:31rpx; font-weight:750; }.editor-subtitle { margin-top:5rpx; color:#94a3b8; font-size:20rpx; }.editor-body { flex:1; min-height:0; padding:18rpx 24rpx; box-sizing:border-box; }.editor-device { display:flex; align-items:center; gap:16rpx; padding:18rpx; border-radius:16rpx; background:#eff6ff; }.editor-cover { width:88rpx; height:88rpx; border-radius:12rpx; }.editor-model,.editor-meta { display:block; }.editor-model { font-size:25rpx; font-weight:700; }.editor-meta { margin-top:7rpx; color:#64748b; font-size:20rpx; }
.form-card { margin-top:16rpx; padding:0 22rpx; border-radius:16rpx; background:#fff; }.form-row { min-height:92rpx; display:flex; align-items:center; gap:18rpx; border-bottom:1rpx solid #eef2f7; }.form-row:last-child { border-bottom:0; }.form-label { width:150rpx; color:#334155; font-size:25rpx; }.form-label.required::before { content:'*'; margin-right:5rpx; color:#ef4444; }.form-input { flex:1; min-width:0; text-align:right; font-size:25rpx; }.section-title { padding-top:22rpx; font-size:25rpx; font-weight:700; }.desc-input { width:100%; min-height:180rpx; padding:18rpx 0 24rpx; color:#475569; font-size:23rpx; line-height:1.6; box-sizing:border-box; }.editor-safe-space { height:30rpx; }.editor-foot { padding:18rpx 26rpx calc(18rpx + env(safe-area-inset-bottom)); background:#fff; }.publish-btn { width:100%; }
</style>
