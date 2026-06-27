<template>
  <div class="cashier">
    <!-- 顶部:富检索(关键字 / IMEI / 内存 / 成色,联动) -->
    <div class="cashier-topbar">
      <el-input v-model.trim="filter.keyword" clearable placeholder="型号/标题/副标题" class="!w-[200px]" @keyup.enter="reloadGoods" @clear="reloadGoods" />
      <el-input v-model.trim="filter.imei" clearable placeholder="IMEI" class="!w-[150px]" @keyup.enter="reloadGoods" @clear="reloadGoods" />
      <el-select v-model="filter.memory_group" multiple collapse-tags collapse-tags-tooltip clearable placeholder="内存" class="!w-[180px]" @change="onFilterChange">
        <el-option v-for="m in memOptions" :key="m" :label="m" :value="m" />
      </el-select>
      <el-select v-model="filter.condition_grade" multiple collapse-tags collapse-tags-tooltip clearable placeholder="成色等级" class="!w-[180px]" @change="onFilterChange">
        <el-option v-for="g in gradeOptions" :key="g" :label="g" :value="g" />
      </el-select>
      <el-button type="primary" :icon="Search" @click="reloadGoods">查询</el-button>
    </div>

    <div class="cashier-body">
      <aside class="cashier-cats">
        <!-- 三级分类:逐级下钻 + 返回。点父级=按其下全部商品筛选并进入子级 -->
        <div v-if="catStack.length" class="cat-back" @click="catBack">
          <span class="cat-back__arrow">‹</span>{{ catStackTop.category_name }}
        </div>
        <div v-if="!catStack.length" :class="['cat-item', categoryId === 0 && 'is-active']" @click="selectAllCats">全部</div>
        <div v-for="c in currentCats" :key="c.category_id" :class="['cat-item', categoryId === c.category_id && 'is-active']" @click="onCatClick(c)">
          <span class="cat-item__name">{{ c.category_name }}</span>
          <span v-if="c.children && c.children.length" class="cat-item__more">›</span>
        </div>
        <div v-if="!currentCats.length" class="cat-empty">无子分类</div>
      </aside>

      <main class="cashier-goods" v-loading="goodsLoading">
        <div class="goods-grid">
          <div v-for="g in goods" :key="g.sku_id" :class="['goods-card', inCart(g.sku_id) && 'is-selected']" @click="addToCart(g)">
            <div class="goods-card__img">
              <el-image :src="img(g.goods_cover)" fit="cover" lazy><template #error><div class="goods-card__noimg">🖼</div></template></el-image>
              <span v-if="inCart(g.sku_id)" class="goods-card__check">✓</span>
              <button class="goods-card__eye" title="查看详情" @click.stop="openDetail(g)"><el-icon><View /></el-icon></button>
            </div>
            <div class="goods-card__name" :title="g.goods_name">{{ g.goods_name }}</div>
            <div v-if="g.sub_title" class="goods-card__sub" :title="g.sub_title">{{ g.sub_title }}</div>
            <div v-if="g.imei" class="goods-card__imei" :title="g.imei">IMEI: {{ g.imei }}</div>
            <div class="goods-card__tags">
              <span v-if="g.memory_group">{{ g.memory_group }}</span>
              <span v-if="g.condition_grade">{{ g.condition_grade }}</span>
            </div>
            <div class="goods-card__meta">
              <div class="goods-card__prices">
                <span class="goods-card__price">¥{{ priceOf(g) }}</span>
                <span v-if="g.has_member_price" class="goods-card__origin">¥{{ g.price.toFixed(2) }}</span>
              </div>
              <span class="goods-card__stock">库存{{ g.stock }}</span>
            </div>
          </div>
          <el-empty v-if="!goodsLoading && !goods.length" description="暂无商品" :image-size="80" />
        </div>
        <div v-if="goods.length < goodsTotal" class="goods-more"><el-button :loading="goodsLoading" @click="loadMore">加载更多（{{ goods.length }}/{{ goodsTotal }}）</el-button></div>
      </main>

      <aside class="cashier-cart">
        <!-- 头部:买家 + 结算方式 + 收款户头 -->
        <div class="cart-top">
          <div class="cart-top__row">
            <span class="cart-top__lbl">买家</span>
            <el-select v-model="memberId" filterable remote clearable :remote-method="searchMember" :loading="memberLoading"
              placeholder="搜索会员(昵称/手机)" class="flex-1" @change="onMemberChange">
              <el-option v-for="m in memberOptions" :key="m.member_id" :label="memberLabel(m)" :value="m.member_id" />
            </el-select>
          </div>
          <div v-if="memberId" class="cart-top__row">
            <span class="cart-top__lbl">身份</span>
            <el-tag :type="buyerIsMember ? 'success' : 'info'" size="small" effect="light" round>{{ buyerLevelName }}</el-tag>
            <span class="cart-top__hint">{{ buyerIsMember ? '按会员价计算' : '按零售价计算' }}</span>
          </div>
          <div class="cart-top__row">
            <span class="cart-top__lbl">结算</span>
            <el-radio-group v-model="paymentMode" size="small">
              <el-radio-button v-if="allowCash" label="offline_cash">现结</el-radio-button>
              <el-radio-button label="offline_credit">挂账</el-radio-button>
            </el-radio-group>
            <el-radio-group v-if="paymentMode === 'offline_cash'" v-model="splitPay" size="small" @change="onSplitChange">
              <el-radio-button :label="false">单笔</el-radio-button>
              <el-radio-button :label="true">分笔</el-radio-button>
            </el-radio-group>
          </div>
          <!-- 单笔:单个收款户头 -->
          <div v-if="paymentMode === 'offline_cash' && !splitPay" class="cart-top__row">
            <span class="cart-top__lbl">收款</span>
            <el-select v-model="capitalAccountId" placeholder="收款户头" size="small" class="flex-1">
              <el-option v-for="a in accountOptions" :key="a.id" :label="a.account_name || a.name" :value="a.id" />
            </el-select>
          </div>
          <!-- 分笔:多账户(微信一笔/支付宝一笔…),合计须=订单合计 -->
          <div v-if="paymentMode === 'offline_cash' && splitPay" class="cart-pay">
            <div v-for="(p, i) in payments" :key="i" class="pay-row">
              <el-select v-model="p.account_id" placeholder="收款户头(微信/支付宝…)" size="small" class="pay-acc">
                <el-option v-for="a in accountOptions" :key="a.id" :label="a.account_name || a.name" :value="a.id" />
              </el-select>
              <el-input-number v-model="p.amount" :min="0" :precision="2" :controls="false" size="small" class="pay-amt" />
              <el-button :icon="Delete" link type="danger" class="pay-del" @click="payments.splice(i, 1)" />
            </div>
            <div class="pay-ops">
              <el-button link type="primary" size="small" @click="addPayment">+ 加一笔</el-button>
              <span class="pay-sum" :class="{ bad: !paySumOk }">已分 ¥{{ paidSum.toFixed(2) }} / ¥{{ total }}<template v-if="!paySumOk">（差 ¥{{ payRemain.toFixed(2) }}）</template></span>
            </div>
          </div>
        </div>
        <div class="cart-head">已选 {{ cart.length }} 台<span v-if="memberId" class="cart-head__mp">· {{ buyerIsMember ? buyerLevelName + '价' : '零售价' }}</span></div>
        <div class="cart-list">
          <div v-for="(it, i) in cart" :key="it.sku_id" class="cart-row">
            <el-image class="cart-row__img" :src="img(it.cover)" fit="cover" />
            <div class="cart-row__body">
              <div class="cart-row__name" :title="it.goods_name">{{ it.goods_name }}</div>
              <div v-if="it.sub_title" class="cart-row__sub" :title="it.sub_title">{{ it.sub_title }}</div>
              <div class="cart-row__imei">
                <span v-if="it.imei" :title="it.imei">IMEI: {{ it.imei }}</span>
                <span v-if="it.memory_group" class="cart-row__chip">{{ it.memory_group }}</span>
                <span v-if="it.condition_grade" class="cart-row__chip">{{ it.condition_grade }}</span>
              </div>
              <el-input-number v-model="it.sale_price" :min="0" :precision="2" :controls="false" size="small" class="cart-row__price-input" />
            </div>
            <el-button :icon="Delete" link type="danger" @click="removeFromCart(i)" />
          </div>
          <div v-if="!cart.length" class="cart-empty">点左侧商品加入</div>
        </div>
        <div class="cart-foot">
          <div class="cart-total"><span>合计</span><b>¥{{ total }}</b></div>
          <el-button type="primary" size="large" class="!w-full" :loading="submitting" :disabled="!cart.length || !memberId || (paymentMode === 'offline_cash' && splitPay && !paySumOk)" @click="checkout">开单</el-button>
        </div>
      </aside>
    </div>

    <!-- 详情(类移动端商品详情:图片 + 质检报告) -->
    <el-dialog v-model="detail.visible" :title="detail.row?.goods_name || '商品详情'" width="640px" top="5vh">
      <div v-if="detail.row" class="cdetail">
        <el-image v-if="detail.row.goods_cover" :src="img(detail.row.goods_cover)" fit="cover" class="cdetail__cover" />
        <div v-if="detail.row.sub_title" class="cdetail__sub">{{ detail.row.sub_title }}</div>
        <div class="cdetail__base">
          <span v-if="detail.row.memory_group">内存 {{ detail.row.memory_group }}</span>
          <span v-if="detail.row.condition_grade">成色 {{ detail.row.condition_grade }}</span>
          <span v-if="detail.row.imei">IMEI {{ detail.row.imei }}</span>
          <span class="cdetail__price">¥{{ priceOf(detail.row) }}</span>
        </div>
        <CheckResultPanel
          v-if="detail.row.check_meta?.result_items?.length || detail.row.check_meta?.summary_fields?.length"
          :summary-fields="detail.row.check_meta.summary_fields"
          :severity-summary="detail.row.check_meta.severity_summary"
          :abnormal-items="detail.row.check_meta.abnormal_items"
          :items="detail.row.check_meta.result_items"
        />
        <el-empty v-else description="暂无质检报告" :image-size="60" />
      </div>
      <template #footer>
        <el-button @click="detail.visible = false">关闭</el-button>
        <el-button type="primary" @click="addToCart(detail.row); detail.visible = false">加入</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Search, Delete, View } from '@element-plus/icons-vue'
import { img } from '@/utils/common'
import { getMemberList } from '@/app/api/member'
import { cashierGoods, cashierCheckout, cashierCategoryTree } from '@/addon/phone_shop/api/cashier'
import { getSpecGroups, getGrades } from '@/addon/phone_shop/api/spec'
import { getCapitalAccounts } from '@/addon/hsx_erp/api/capital_account'
import { getErpConfig } from '@/addon/hsx_erp/api/config'
import CheckResultPanel from '@/addon/hsx_recycle/views/recycle_order/components/CheckResultPanel.vue'

const categories = ref<any[]>([])
const categoryId = ref(0)
// 三级分类下钻:catStack=已进入的父级路径;currentCats=当前层;categoryIds=选中节点+全部子级id
const catStack = ref<any[]>([])
const categoryIds = ref<number[]>([])
const currentCats = computed<any[]>(() => catStack.value.length ? (catStack.value[catStack.value.length - 1].children || []) : categories.value)
const catStackTop = computed<any>(() => catStack.value[catStack.value.length - 1] || {})
const descendantIds = (cat: any): number[] => {
  const ids: number[] = [Number(cat.category_id)]
  const walk = (c: any) => (c.children || []).forEach((ch: any) => { ids.push(Number(ch.category_id)); walk(ch) })
  walk(cat)
  return ids
}
const selectAllCats = () => { categoryId.value = 0; categoryIds.value = []; loadFilters(); reloadGoods() }
const onCatClick = (cat: any) => {
  categoryId.value = Number(cat.category_id)
  categoryIds.value = descendantIds(cat)
  loadFilters(); reloadGoods()
  if (cat.children && cat.children.length) catStack.value.push(cat) // 有子级则下钻
}
const catBack = () => {
  const popped = catStack.value.pop()
  // 返回上一层:按上一层父级筛选(回到顶层则全部)
  const parent = catStack.value[catStack.value.length - 1]
  if (parent) { categoryId.value = Number(parent.category_id); categoryIds.value = descendantIds(parent) }
  else { categoryId.value = 0; categoryIds.value = [] }
  loadFilters(); reloadGoods()
}
const filter = reactive<{ keyword: string; imei: string; memory_group: string[]; condition_grade: string[] }>({ keyword: '', imei: '', memory_group: [], condition_grade: [] })
const memOptions = ref<string[]>([])
const gradeOptions = ref<string[]>([])

const goods = ref<any[]>([])
const goodsTotal = ref(0)
const goodsPage = ref(1)
const goodsLoading = ref(false)

const memberId = ref<number | undefined>(undefined)
const memberOptions = ref<any[]>([])
const memberLoading = ref(false)
const selectedMember = ref<any>(null)
const paymentMode = ref('offline_cash')
const allowCash = ref(true)   // 现结开关:后台「收款设置」关闭后,收银台只能挂账
const loadSettleConfig = async () => {
  try {
    const res: any = await getErpConfig()
    allowCash.value = Number(res.data?.allow_instant_settle ?? 1) === 1
    if (!allowCash.value) paymentMode.value = 'offline_credit'
  } catch (e) { /* ERP 未启用时默认允许现结 */ }
}
const capitalAccountId = ref<number | undefined>(undefined)
const accountOptions = ref<any[]>([])
// 分笔现结:多账户收款(账户本身已区分微信/支付宝),合计须=订单合计
const splitPay = ref(false)
const payments = ref<Array<{ account_id: number | undefined; amount: number }>>([])
const paidSum = computed(() => payments.value.reduce((s, p) => s + Number(p.amount || 0), 0))

const cart = ref<any[]>([])
const submitting = ref(false)
const detail = reactive<{ visible: boolean; row: any }>({ visible: false, row: null })

const priceOf = (g: any) => Number(g.has_member_price ? g.member_price : g.price).toFixed(2)
const levelNameOf = (m: any) => { const n = m && m.member_level_name; return (n && String(n).trim()) ? String(n).trim() : '' }
const memberLabel = (m: any) => `${m.nickname || m.username || '会员'}${m.mobile ? ' · ' + m.mobile : ''}${levelNameOf(m) ? ' · ' + levelNameOf(m) : ' · 普通用户'}`
const buyerIsMember = computed(() => !!levelNameOf(selectedMember.value))
const buyerLevelName = computed(() => levelNameOf(selectedMember.value) || '普通用户')
const onMemberChange = () => {
  selectedMember.value = memberOptions.value.find((m: any) => m.member_id === memberId.value) || null
  reloadGoods()
}
const total = computed(() => cart.value.reduce((s, it) => s + Number(it.sale_price || 0), 0).toFixed(2))
const inCart = (skuId: number) => cart.value.some((it) => it.sku_id === skuId)
// 分笔:差额 / 合计是否对齐(2位精度)
const payRemain = computed(() => Number(total.value) - paidSum.value)
const paySumOk = computed(() => Math.abs(payRemain.value) < 0.01)
const addPayment = () => {
  const remain = Math.max(0, Number(payRemain.value.toFixed(2)))
  payments.value.push({ account_id: undefined, amount: remain })
}
const onSplitChange = (v: any) => {
  // 切到分笔且为空时,默认放一笔=订单合计
  if (v && !payments.value.length) payments.value = [{ account_id: capitalAccountId.value, amount: Number(total.value) }]
}

const loadCategories = async () => {
  try { const res: any = await cashierCategoryTree(); categories.value = res.data || [] } catch (e) { /* */ }
}
const loadGoods = async (reset = false) => {
  goodsLoading.value = true
  try {
    if (reset) { goodsPage.value = 1; goods.value = [] }
    const res: any = await cashierGoods({
      page: goodsPage.value, limit: 18, member_id: memberId.value || 0, category_id: categoryId.value || 0, category_ids: categoryIds.value, ...filter,
    })
    const d = res.data || {}
    goods.value = goodsPage.value === 1 ? (d.data || []) : goods.value.concat(d.data || [])
    goodsTotal.value = Number(d.total || 0)
    // 后端联动选项为空时,用已加载商品兜底填充,确保下拉始终有值
    if (!memOptions.value.length) memOptions.value = deriveOptions('memory_group')
    if (!gradeOptions.value.length) gradeOptions.value = deriveOptions('condition_grade')
  } finally { goodsLoading.value = false }
}
const reloadGoods = () => loadGoods(true)
const loadMore = () => { goodsPage.value++; loadGoods(false) }

// 内存选项 = 规格组(spec/group,按分类联动)的子项 item_value;成色选项 = goods/grade 字典(全局)
const loadFilters = async () => {
  // 内存:随分类联动
  try {
    const res: any = await getSpecGroups({ category_id: categoryId.value || 0 })
    const groups = res.data || []
    // 优先取 label 含"内存"的规格组,没有则用全部规格组的子项
    const memGroups = groups.filter((g: any) => String(g.label || '').includes('内存'))
    const useGroups = memGroups.length ? memGroups : groups
    const set = new Set<string>()
    useGroups.forEach((g: any) => (g.items || []).forEach((it: any) => { const v = String(it.item_value ?? '').trim(); if (v) set.add(v) }))
    memOptions.value = set.size ? Array.from(set) : deriveOptions('memory_group')
  } catch (e) { memOptions.value = deriveOptions('memory_group') }
  // 成色:全局字典,加载一次即可
  if (!gradeOptions.value.length) {
    try {
      const res: any = await getGrades()
      const list = (res.data || []).map((x: any) => String(x.grade_name ?? '').trim()).filter(Boolean)
      gradeOptions.value = list.length ? list : deriveOptions('condition_grade')
    } catch (e) { gradeOptions.value = deriveOptions('condition_grade') }
  }
}
// 从已加载商品里去重派生候选项(字典接口无返回时的兜底)
const deriveOptions = (key: string): string[] => {
  const set = new Set<string>()
  goods.value.forEach((g: any) => { const v = String(g[key] ?? '').trim(); if (v) set.add(v) })
  return Array.from(set).sort()
}
const onFilterChange = () => { reloadGoods() }

const searchMember = async (kw: string) => {
  memberLoading.value = true
  try { const res: any = await getMemberList({ keyword: kw, page: 1, limit: 20 }); memberOptions.value = res.data?.data || res.data?.list || [] }
  finally { memberLoading.value = false }
}

const addToCart = (g: any) => {
  if (!g || inCart(g.sku_id)) return
  if (Number(g.stock) <= 0) { ElMessage.warning('该商品无库存'); return }
  cart.value.push({ sku_id: g.sku_id, goods_id: g.goods_id, erp_asset_id: g.erp_asset_id, goods_name: g.goods_name, sub_title: g.sub_title, imei: g.imei, memory_group: g.memory_group, condition_grade: g.condition_grade, cover: g.goods_cover, sale_price: Number(priceOf(g)) })
}
const removeFromCart = (i: number) => cart.value.splice(i, 1)
const openDetail = (g: any) => { detail.row = g; detail.visible = true }

const loadAccounts = async () => {
  try { const res: any = await getCapitalAccounts(); accountOptions.value = res.data?.data || res.data?.list || res.data || [] } catch (e) { /* ERP 未启用时忽略 */ }
}

const accNameOf = (id: any) => { const a = accountOptions.value.find((x: any) => x.id === id); return a ? (a.account_name || a.name) : '' }

const checkout = async () => {
  if (!memberId.value) { ElMessage.warning('请选择买家会员'); return }
  if (!cart.value.length) return
  const isCash = paymentMode.value === 'offline_cash'
  const isSplit = isCash && splitPay.value
  // 校验收款方式
  if (isCash && !isSplit && !capitalAccountId.value) { ElMessage.warning('现结请选择收款户头'); return }
  if (isSplit) {
    if (!payments.value.length) { ElMessage.warning('请至少添加一笔收款'); return }
    if (payments.value.some((p) => !p.account_id || Number(p.amount) <= 0)) { ElMessage.warning('每笔收款需选择户头并填写金额'); return }
    if (!paySumOk.value) { ElMessage.warning(`分笔合计 ¥${paidSum.value.toFixed(2)} 与订单合计 ¥${total.value} 不一致`); return }
  }

  // 二次确认:把买家 / 结算方式 / 收款 / 台数 / 金额列清楚再开
  const isCredit = paymentMode.value === 'offline_credit'
  const buyer = memberOptions.value.find((m: any) => m.member_id === memberId.value)
  const buyerName = buyer ? memberLabel(buyer) : ('会员#' + memberId.value)
  let settleLine = ''
  if (isCredit) {
    settleLine = '<span style="color:#e6a23c;font-weight:600">挂账</span>(生成 ERP 应收,事后结算)'
  } else if (isSplit) {
    const rows = payments.value.map((p) => `<div style="padding-left:12px">· ${accNameOf(p.account_id)}：<b>¥${Number(p.amount).toFixed(2)}</b></div>`).join('')
    settleLine = `<span style="color:#67c23a;font-weight:600">现结·分笔</span>${rows}`
  } else {
    settleLine = `<span style="color:#67c23a;font-weight:600">现结</span> · 收款户头:<b>${accNameOf(capitalAccountId.value)}</b>`
  }
  const html = `
    <div style="line-height:2;font-size:13px">
      <div>买家:<b>${buyerName}</b></div>
      <div>结算方式:${settleLine}</div>
      <div>台数:<b>${cart.value.length}</b> 台 · 合计:<b style="color:#f56c6c">¥${total.value}</b></div>
      <div style="color:#909399;margin-top:6px">确认后将直接生成 ERP 出货单${isCredit ? ',并挂应收' : '并即时收款入账'},此操作不可一键撤销(需到 ERP 退回)。</div>
    </div>`
  try {
    await ElMessageBox.confirm(html, '确认开单', {
      confirmButtonText: isCredit ? '确认挂账开单' : '确认现结开单',
      cancelButtonText: '再看看',
      type: 'warning',
      dangerouslyUseHTMLString: true,
    })
  } catch (e) { return } // 取消

  submitting.value = true
  try {
    await cashierCheckout({
      member_id: memberId.value, payment_mode: paymentMode.value,
      capital_account_id: (isCash && !isSplit) ? capitalAccountId.value : 0,
      payments: isSplit ? payments.value.map((p) => ({ account_id: p.account_id, amount: Number(p.amount) })) : [],
      items: cart.value.map((it) => ({ sku_id: it.sku_id, goods_id: it.goods_id, asset_id: it.erp_asset_id, sale_price: it.sale_price })),
    })
    cart.value = []; payments.value = []; splitPay.value = false; reloadGoods()
  } finally { submitting.value = false }
}

onMounted(() => { loadCategories(); loadFilters(); loadGoods(true); loadAccounts(); loadSettleConfig() })
</script>

<style lang="scss" scoped>
.cashier { display: flex; flex-direction: column; height: calc(100vh - 100px); background: #fff; border-radius: 10px; overflow: hidden; }
.cashier-topbar { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border-bottom: 1px solid var(--el-border-color-lighter); flex-wrap: wrap; }
.cashier-body { flex: 1; display: flex; min-height: 0; }

.cashier-cats { width: 140px; flex-shrink: 0; border-right: 1px solid var(--el-border-color-lighter); overflow-y: auto; padding: 8px 0; }
.cat-item { display: flex; align-items: center; justify-content: space-between; gap: 4px; padding: 10px 12px 10px 16px; font-size: 13px; cursor: pointer; color: var(--el-text-color-regular); border-left: 3px solid transparent; }
.cat-item:hover { background: var(--el-fill-color-light); }
.cat-item.is-active { background: var(--el-color-primary-light-9); border-left-color: var(--el-color-primary); color: var(--el-color-primary); font-weight: 600; }
.cat-item__name { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cat-item__more { color: var(--el-text-color-placeholder); font-size: 14px; flex: none; }
.cat-back { display: flex; align-items: center; gap: 4px; padding: 8px 12px; font-size: 12px; color: var(--el-color-primary); cursor: pointer; border-bottom: 1px solid var(--el-border-color-lighter); margin-bottom: 4px; font-weight: 600; }
.cat-back__arrow { font-size: 16px; line-height: 1; }
.cat-empty { padding: 16px; font-size: 12px; color: var(--el-text-color-placeholder); text-align: center; }

.cashier-goods { flex: 1; min-width: 0; overflow-y: auto; padding: 14px; }
.goods-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(155px, 1fr)); gap: 12px; }
.goods-card { border: 1px solid var(--el-border-color-lighter); border-radius: 10px; overflow: hidden; cursor: pointer; transition: all .15s; background: #fff; }
.goods-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); transform: translateY(-2px); }
.goods-card.is-selected { border-color: var(--el-color-primary); box-shadow: 0 0 0 2px var(--el-color-primary-light-7); }
.goods-card__img { position: relative; aspect-ratio: 1; background: var(--el-fill-color-light); :deep(.el-image) { width: 100%; height: 100%; } }
.goods-card__noimg { display: flex; align-items: center; justify-content: center; height: 100%; font-size: 28px; color: #cbd5e1; }
.goods-card__check { position: absolute; top: 6px; right: 6px; width: 22px; height: 22px; border-radius: 50%; background: var(--el-color-primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 13px; }
.goods-card__eye { position: absolute; bottom: 6px; right: 6px; width: 26px; height: 26px; border-radius: 6px; border: none; background: rgba(0,0,0,.45); color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; }
.goods-card__eye:hover { background: rgba(0,0,0,.7); }
.goods-card__name { padding: 8px 8px 2px; font-size: 12px; line-height: 1.4; height: 38px; overflow: hidden; }
.goods-card__sub { padding: 0 8px; font-size: 11px; color: var(--el-text-color-secondary); line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.goods-card__imei { padding: 1px 8px 0; font-size: 10px; color: var(--el-text-color-placeholder); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.goods-card__tags { display: flex; gap: 4px; padding: 2px 8px 0; span { font-size: 10px; color: var(--el-text-color-secondary); background: var(--el-fill-color-light); border-radius: 4px; padding: 1px 6px; } }
.goods-card__meta { display: flex; align-items: flex-end; justify-content: space-between; padding: 4px 8px 8px; }
.goods-card__prices { display: flex; align-items: baseline; gap: 5px; }
.goods-card__price { color: var(--el-color-danger); font-weight: 700; font-size: 14px; }
.goods-card__origin { color: var(--el-text-color-placeholder); font-size: 11px; text-decoration: line-through; }
.goods-card__stock { font-size: 11px; color: var(--el-text-color-secondary); }
.goods-more { text-align: center; margin-top: 14px; }

.cashier-cart { width: 340px; flex-shrink: 0; border-left: 1px solid var(--el-border-color-lighter); display: flex; flex-direction: column; }
.cart-top { padding: 12px 14px 6px; border-bottom: 1px solid var(--el-border-color-lighter); display: flex; flex-direction: column; gap: 8px; }
.cart-top__row { display: flex; align-items: center; gap: 8px; }
.cart-top__lbl { font-size: 12px; color: var(--el-text-color-secondary); width: 28px; flex-shrink: 0; }
.cart-top__hint { font-size: 11px; color: var(--el-text-color-placeholder); }
.cart-pay { display: flex; flex-direction: column; gap: 6px; padding: 2px 0 2px 36px; }
.pay-row { display: flex; align-items: center; gap: 6px; }
.pay-acc { flex: 1; min-width: 0; }
.pay-amt { width: 100px; flex: none; }
.pay-del { flex: none; padding: 0; }
.pay-ops { display: flex; align-items: center; justify-content: space-between; }
.pay-sum { font-size: 12px; color: var(--el-text-color-secondary); }
.pay-sum.bad { color: var(--el-color-danger); }
.cart-head { padding: 10px 14px; font-weight: 600; font-size: 13px; border-bottom: 1px solid var(--el-border-color-lighter); .cart-head__mp { font-size: 12px; color: var(--el-color-primary); font-weight: 400; } }
.cart-list { flex: 1; overflow-y: auto; padding: 8px 10px; }
.cart-row { display: flex; align-items: center; gap: 8px; padding: 6px 0; border-bottom: 1px dashed var(--el-border-color-lighter); }
.cart-row__img { width: 42px; height: 42px; border-radius: 6px; flex-shrink: 0; }
.cart-row__body { flex: 1; min-width: 0; }
.cart-row__name { font-size: 12px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cart-row__sub { font-size: 11px; color: var(--el-text-color-secondary); line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cart-row__imei { display: flex; align-items: center; gap: 4px; margin-top: 1px; font-size: 10px; color: var(--el-text-color-placeholder); overflow: hidden; white-space: nowrap; }
.cart-row__chip { color: var(--el-text-color-secondary); background: var(--el-fill-color-light); border-radius: 4px; padding: 0 5px; }
.cdetail__sub { margin-top: 8px; font-size: 13px; color: var(--el-text-color-secondary); line-height: 1.4; }
.cart-row__price-input { width: 110px; margin-top: 3px; :deep(.el-input__inner) { color: var(--el-color-danger); font-weight: 600; text-align: left; } }
.cart-empty { color: var(--el-text-color-placeholder); text-align: center; padding: 30px 0; font-size: 13px; }
.cart-foot { padding: 12px 14px; border-top: 1px solid var(--el-border-color-lighter); }
.cart-total { display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; b { font-size: 20px; color: var(--el-color-danger); } }

.cdetail { display: flex; flex-direction: column; gap: 12px; }
.cdetail__cover { width: 100%; max-height: 240px; border-radius: 8px; }
.cdetail__base { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 12px; color: var(--el-text-color-secondary); }
.cdetail__price { color: var(--el-color-danger); font-weight: 700; font-size: 16px; margin-left: auto; }
</style>
