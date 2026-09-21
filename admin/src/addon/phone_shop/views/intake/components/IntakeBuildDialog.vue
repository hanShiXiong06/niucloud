<template>
    <HsxDialog v-model="visible" :title="materialMode ? '完善商城资料' : '完善并上架'"
        :subtitle="materialMode ? '沿用商城属性配置，补齐资料并完成运营核对' : '核对交接资料，确认后发布到客户商城'"
        size="lg" top="4vh" body-max-height="calc(92vh - 164px)" :draggable="false"
        :confirm-loading="saving" :close-on-press-escape="!previewing" :before-close="beforeClose" @close="invalidateRequests">
        <div v-if="loading" class="intake-build__loading" role="status" aria-live="polite">
            <el-skeleton :rows="7" animated />
            <p>正在读取设备图片、分类与交接资料…</p>
        </div>
        <el-result v-else-if="loadError" icon="warning" title="交接资料未加载完整" :sub-title="loadError">
            <template #extra><el-button type="primary" @click="load">重新加载</el-button></template>
        </el-result>
        <div v-else-if="ready" class="intake-build">
            <aside class="intake-build__preview">
                <div class="intake-build__eyebrow"><span>ERP 交接设备</span><el-tag size="small" effect="plain">{{ materialMode ? materialInfo?.listing_state_name : '待上架' }}</el-tag></div>
                <div class="intake-build__cover">
                    <el-image v-if="images.length" :src="images[activeImage]" fit="contain" :preview-src-list="images"
                        :initial-index="activeImage" preview-teleported @show="previewing = true" @close="previewing = false">
                        <template #error><span class="intake-build__image-error">图片加载失败，可点击其他图片核对</span></template>
                    </el-image>
                    <span v-else class="intake-build__image-error">暂无设备图片</span>
                    <span v-if="images.length" class="intake-build__image-count">{{ activeImage + 1 }} / {{ images.length }} · 点击放大</span>
                </div>
                <div v-if="images.length > 1" class="intake-build__thumbs" aria-label="选择预览图片">
                    <button v-for="(url, index) in images" :key="`${index}-${url}`" type="button" :aria-label="`查看第 ${index + 1} 张图片`"
                        :aria-pressed="activeImage === index" :class="{ selected: activeImage === index }" @click="activeImage = index">
                        <el-image :src="url" fit="cover" />
                    </button>
                </div>
                <h3>{{ source.model_name || '待核对设备' }}</h3>
                <p class="intake-build__device-spec">{{ [source.memory, source.color, source.condition_grade].filter(Boolean).join(' / ') || '配置以交接资料为准' }}</p>
                <div class="intake-build__imei"><span>IMEI</span><strong>{{ source.imei || '未记录' }}</strong></div>
                <el-tag v-if="materialMode" size="small" :type="materialInfo?.material_task?.status === 'completed' ? 'success' : 'warning'">{{ materialInfo?.material_task?.status_name }}</el-tag>
                <p class="intake-build__muted">图片沿用 ERP 交接；如需更换，请回 ERP 修改并重新交接。</p>
            </aside>

            <section class="intake-build__editor">
                <div class="intake-build__notice">
                    <el-icon><InfoFilled /></el-icon>
                    <span>{{ materialMode ? '仅补展示资料，不修改价格、库存或财务；已售、已下架的商品不会重新上架。' : basicFirst ? '确认分类即可上架购买；细节由运营在资料待办中继续完善。' : '图片和质检已带入，核对分类与销售资料后即可上架。' }}</span>
                </div>
                <el-form :model="form" label-position="top" :disabled="saving" @submit.prevent>
                    <el-tabs v-model="activeTab" class="intake-build__tabs">
                        <el-tab-pane label="基本信息" name="basic">
                            <el-form-item v-if="materialMode" label="商品分类"><span>{{ materialCategoryNames || '暂未关联有效分类，请在商品管理中核对' }}</span></el-form-item>
                            <el-form-item v-else label="商品分类" required :error="fieldError('category')">
                                <el-cascader ref="categoryRef" v-model="form.goods_category" :options="categoryOptions" :props="categoryProps"
                                    clearable filterable placeholder="搜索或选择分类，便于客户找到这台设备" @change="loadSpecOptions" />
                                <p class="intake-build__help">沿用商城现有分类，不新建分类。</p>
                            </el-form-item>
                            <el-form-item label="商品标题" required :error="fieldError('title')">
                                <el-input ref="titleRef" v-model="form.goods_name" :maxlength="materialMode ? 255 : 60" show-word-limit placeholder="建议：型号 + 容量 + 颜色" />
                            </el-form-item>
                            <div v-if="!materialMode" class="intake-build__grid">
                                <el-form-item label="品牌">
                                    <el-select v-model="form.brand_id" placeholder="选择品牌（选填）" clearable filterable>
                                        <el-option v-for="item in brandOptions" :key="item.brand_id" :label="item.brand_name" :value="item.brand_id" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item v-if="!basicFirst" :label="specLabel">
                                    <el-select v-model="form.memory" :loading="specLoading" filterable clearable placeholder="选择商城已有规格">
                                        <el-option v-if="form.memory && !specItems.includes(form.memory)" :value="form.memory" :label="`${form.memory}（原值，待对应）`" disabled />
                                        <el-option v-for="value in specItems" :key="value" :label="value" :value="value" />
                                    </el-select>
                                </el-form-item>
                                <el-form-item v-if="!basicFirst" label="成色">
                                    <el-select v-model="form.condition_grade" filterable clearable placeholder="选择商城已有成色">
                                        <el-option v-if="form.condition_grade && !gradeOptions.some(item => item.grade_name === form.condition_grade)" :value="form.condition_grade" :label="`${form.condition_grade}（原值，待对应）`" disabled />
                                        <el-option v-for="item in gradeOptions" :key="item.grade_id" :label="item.grade_name" :value="item.grade_name" />
                                    </el-select>
                                </el-form-item>
                            </div>
                            <p v-if="specError" class="intake-build__muted" role="status">{{ specError }} <el-button link type="primary" @click="loadSpecOptions(form.goods_category)">重试</el-button></p>
                            <el-collapse class="intake-build__optional">
                                <el-collapse-item title="更多展示信息（选填）" name="display">
                                    <el-form-item label="展示摘要"><el-input v-model="form.sub_title" :maxlength="materialMode ? 255 : 100" show-word-limit placeholder="填写已确认的卖点或售后说明" /></el-form-item>
                                    <template v-if="!basicFirst && !materialMode">
                                        <el-form-item label="服务保障">
                                            <el-select v-model="form.service_ids" multiple clearable filterable collapse-tags collapse-tags-tooltip placeholder="选择实际提供的服务">
                                                <el-option v-for="item in serviceOptions" :key="item.service_id" :label="item.service_name" :value="item.service_id" />
                                            </el-select>
                                        </el-form-item>
                                        <el-form-item label="商品标签">
                                            <el-select v-model="form.label_ids" multiple clearable filterable collapse-tags collapse-tags-tooltip placeholder="选择标签">
                                                <el-option v-for="item in labelOptions" :key="item.label_id" :label="item.label_name" :value="item.label_id" />
                                            </el-select>
                                        </el-form-item>
                                    </template>
                                </el-collapse-item>
                            </el-collapse>
                        </el-tab-pane>
                        <el-tab-pane v-if="materialMode" label="商城属性" name="attributes">
                            <div class="intake-build__section-heading"><strong>选择商城已有属性</strong><el-button link type="primary" :loading="refreshingCatalog" :disabled="saving" @click="refreshCatalog">刷新选项</el-button></div>
                            <IntakeMaterialFields v-model="materialForm" :catalog="materialInfo?.catalog || {}" />
                        </el-tab-pane>
                        <el-tab-pane v-else label="价格与配送" name="sale">
                            <div class="intake-build__section-heading"><strong>销售定价</strong><span>{{ basicFirst ? '由 ERP 定价岗位维护' : '沿用站点会员价格规则' }}</span></div>
                            <div v-if="basicFirst" class="intake-build__readonly-prices">
                                <div><span>销售价</span><strong>¥{{ money(form.price) }}</strong></div>
                                <div><span>同行 / 基准价</span><strong>¥{{ money(form.peer_price) }}</strong></div>
                                <p>此处仅核对，不改价。如需调价，请回 ERP 修改并重新交接。</p>
                            </div>
                            <el-form-item v-else label="销售价格（元）" required :error="fieldError('price')">
                                <TierPriceInput :key="pricingKey" v-model="form.price" v-model:base-price="form.pricing_base_price" :disabled="saving" @policy="onPricingPolicy" />
                                <div v-if="!pricingReady" class="intake-build__help">价格规则尚未就绪，暂不能上架。<el-button link type="primary" @click="pricingKey++">重新加载规则</el-button></div>
                            </el-form-item>
                            <el-collapse class="intake-build__optional intake-build__costs">
                                <el-collapse-item title="划线价与成本参考" name="cost">
                                    <div class="intake-build__grid">
                                        <el-form-item label="划线价（元）"><el-input-number v-model="form.market_price" :min="0" :precision="2" :controls="false" :disabled="basicFirst" /></el-form-item>
                                        <el-form-item label="成本价（元）"><el-input-number v-model="form.cost_price" :min="0" :precision="2" :controls="false" :disabled="basicFirst" /></el-form-item>
                                    </div>
                                    <p v-if="!basicFirst && !autoTierPricing" class="intake-build__muted">同行价 ¥{{ money(form.peer_price) }} · 沿用 ERP 交接价格</p>
                                    <p class="intake-build__muted">成本仅供内部核对，不向客户展示。</p>
                                </el-collapse-item>
                            </el-collapse>
                            <el-form-item label="发货方式" required :error="fieldError('delivery')" class="intake-build__delivery">
                                <el-checkbox-group v-model="form.delivery_type">
                                    <el-checkbox v-for="item in deliveryOptions" :key="item.value" :label="item.value" border>{{ item.label }}</el-checkbox>
                                </el-checkbox-group>
                                <p class="intake-build__help">至少选择一种，只勾选门店实际支持的方式。</p>
                            </el-form-item>
                        </el-tab-pane>
                        <el-tab-pane :label="materialMode ? '质检与记录' : '质检与详情'" name="quality">
                            <div class="intake-build__section-heading"><strong>设备质检</strong><span>ERP 带入 · 只读</span></div>
                            <CheckResultPanel v-if="check.result_items?.length || check.summary_fields?.length"
                                :summary-fields="check.summary_fields" :severity-summary="check.severity_summary"
                                :abnormal-items="check.abnormal_items" :items="check.result_items" />
                            <el-empty v-else description="尚无结构化质检报告，不代表检测正常" :image-size="58" />
                            <el-form-item v-if="!basicFirst && !materialMode" label="商品详情文案（选填）" class="intake-build__description">
                                <el-input v-model="form.goods_desc" type="textarea" :rows="4" placeholder="填写卖点、售后或购买说明；无需重复粘贴质检报告" />
                            </el-form-item>
                            <el-collapse v-if="materialMode && materialInfo?.material_task?.history?.length" class="intake-build__optional">
                                <el-collapse-item title="处理记录" name="history">
                                    <div v-for="(item, index) in [...materialInfo.material_task.history].reverse()" :key="index" class="intake-build__history">
                                        <span>{{ item.operator_name || '商城运营' }} · {{ item.action === 'complete' ? '核对完成' : '保存进度' }}</span>
                                        <span>{{ formatTime(item.at) }}</span>
                                    </div>
                                </el-collapse-item>
                            </el-collapse>
                        </el-tab-pane>
                    </el-tabs>
                </el-form>
            </section>
        </div>
        <template #footer>
            <div class="intake-build__footer">
                <div class="intake-build__footer-copy" aria-live="polite">
                    <span v-if="submitError" class="intake-build__error" role="alert">{{ submitError }}</span>
                    <template v-else-if="materialMode && ready && !loading && !loadError">
                        <strong>保存进度可稍后继续</strong><span>核对完成仅结束资料待办，不改变交易状态。</span>
                    </template>
                    <template v-else-if="ready && !loading && !loadError">
                        <button v-if="missing.length" type="button" class="intake-build__missing" @click="locate(missing[0])">待完善：{{ missing.map(item => item.label).join('、') }} →</button>
                        <strong v-else>资料已就绪</strong>
                        <span>确认上架后，客户可在商城查看并购买。</span>
                    </template>
                    <span v-else>{{ loading ? '资料读取中，请稍候' : '请先完整加载交接资料' }}</span>
                </div>
                <div class="intake-build__footer-actions">
                    <el-button :disabled="saving" @click="beforeClose(() => { visible = false })">取消</el-button>
                    <template v-if="materialMode">
                        <el-button :loading="saving" :disabled="!ready || loading || refreshingCatalog || !!loadError" @click="submitMaterial('save')">保存进度</el-button>
                        <el-button type="primary" :loading="saving" :disabled="!ready || loading || refreshingCatalog || !!loadError" @click="submitMaterial('complete')">核对完成</el-button>
                    </template>
                    <el-button v-else type="primary" :loading="saving" :disabled="!ready || loading || !!loadError" @click="submit">确认上架</el-button>
                </div>
            </div>
        </template>
    </HsxDialog>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, reactive, ref } from 'vue'
import { InfoFilled } from '@element-plus/icons-vue'
import { HsxDialog, useFeedback } from '@/addon/hsx_components/core'
import { img } from '@/utils/common'
import { buildDeviceIntake, previewDeviceIntake, getDeviceIntakeMaterial, saveDeviceIntakeMaterial } from '@/addon/phone_shop/api/device_intake'
import { getBrandList, getCategoryTree, getLabelList } from '@/addon/phone_shop/api/goods'
import { getGrades, getSpecOptionsByCategory } from '@/addon/phone_shop/api/spec'
import CheckResultPanel from '@/addon/phone_shop/components/CheckResultPanel.vue'
import TierPriceInput from '@/addon/phone_shop/views/goods/components/TierPriceInput.vue'
import IntakeMaterialFields from './IntakeMaterialFields.vue'
import { batteryValue, emptyMaterialForm, materialReview, mergeParameters, parameterFields } from './material-options'

type TabName = 'basic' | 'sale' | 'quality' | 'attributes'
type CheckKey = 'images' | 'category' | 'title' | 'price' | 'delivery'
type ReadinessItem = { key: CheckKey; label: string; ok: boolean; tab: TabName; message: string }
const emit = defineEmits<{ (event: 'published'): void; (event: 'saved'): void }>()
const feedback = useFeedback()
const visible = ref(false), loading = ref(false), saving = ref(false), ready = ref(false)
const loadError = ref(''), submitError = ref(''), attempted = ref(false)
const source = ref<Record<string, any>>({}), basicFirst = ref(false), activeTab = ref<TabName>('basic')
const materialMode = ref(false), materialInfo = ref<any>(null), materialForm = ref(emptyMaterialForm()), initialMaterial = ref(''), refreshingCatalog = ref(false)
const materialCategoryNames = computed(() => array(materialInfo.value?.catalog?.categories).map(row => row.category_name).join(' / '))
const imageUrls = ref<string[]>([]), activeImage = ref(0), previewing = ref(false), check = ref<Record<string, any>>({})
const images = computed(() => imageUrls.value.map(url => img(url)))
const brandOptions = ref<any[]>([]), categoryOptions = ref<any[]>([]), labelOptions = ref<any[]>([]), serviceOptions = ref<any[]>([])
const categoryProps = { value: 'category_id', label: 'category_name', children: 'child_list', checkStrictly: true, emitPath: true }
const specGroups = ref<any[]>([]), gradeOptions = ref<any[]>([]), defaultGrades = ref<any[]>([]), specLoading = ref(false), specError = ref('')
const specLabel = computed(() => specGroups.value[0]?.label || '内存 / 规格')
const specItems = computed(() => (specGroups.value[0]?.items || []).map((item: any) => item.item_value))
const pricingReady = ref(false), autoTierPricing = ref(false), pricingKey = ref(0)
const categoryRef = ref<any>(), titleRef = ref<any>()
const deliveryOptions = [{ value: 'express', label: '快递发货' }, { value: 'local_delivery', label: '同城配送' }, { value: 'store', label: '到店自提' }]
const emptyForm = () => ({ intake_id: 0, goods_name: '', sub_title: '', brand_id: '' as number | string,
    goods_category: [] as number[], label_ids: [] as number[], service_ids: [] as number[], memory: '', condition_grade: '',
    delivery_type: ['express'], price: 0, market_price: 0, cost_price: 0, peer_price: 0, pricing_base_price: 0, goods_desc: '' })
const form = reactive(emptyForm())
const initialForm = ref<ReturnType<typeof emptyForm> | null>(null)
let requestSequence = 0, specSequence = 0
const positive = (value: unknown) => Number.isFinite(Number(value)) && Number(value) > 0
const money = (value: unknown) => Number.isFinite(Number(value)) ? Number(value).toFixed(2) : '—'
const array = (value: any): any[] => {
    if (Array.isArray(value)) return value
    if (typeof value === 'string') { try { const parsed = JSON.parse(value); return Array.isArray(parsed) ? parsed : [] } catch { return [] } }
    return []
}
const errorText = (error: any, fallback: string) => (typeof error === 'string' ? error : error?.msg || error?.message) || fallback
const checks = computed<ReadinessItem[]>(() => [
    { key: 'images', label: '设备图片', ok: images.value.length > 0, tab: 'basic', message: '请回 ERP 上传商品图片，并重新交接后再上架。' },
    { key: 'category', label: '商品分类', ok: form.goods_category.length > 0, tab: 'basic', message: '请选择商品分类' },
    { key: 'title', label: '商品标题', ok: !!form.goods_name.trim(), tab: 'basic', message: '请填写商品标题' },
    { key: 'price', label: '销售定价', ok: pricingReady.value && positive(form.price) && (!autoTierPricing.value || positive(form.pricing_base_price)), tab: 'sale', message: pricingReady.value ? '销售价格必须大于 0' : '价格规则尚未就绪，请重新加载规则后再上架' },
    { key: 'delivery', label: '发货方式', ok: form.delivery_type.length > 0, tab: 'sale', message: '请至少选择一种发货方式' }
])
const missing = computed(() => checks.value.filter(item => !item.ok))
const fieldError = (key: CheckKey) => attempted.value ? missing.value.find(item => item.key === key)?.message || '' : ''
// 自动加价产生的零售价变化不是人工编辑；关闭提醒只比较用户可维护的输入。
const signature = (value: ReturnType<typeof emptyForm>) => JSON.stringify({ ...value, price: autoTierPricing.value ? undefined : value.price })
const dirty = computed(() => initialForm.value && (signature(form) !== signature(initialForm.value) || (materialMode.value && initialMaterial.value !== JSON.stringify(materialForm.value))))

function invalidateRequests() { ++requestSequence; ++specSequence }
onBeforeUnmount(invalidateRequests)

async function loadSpecOptions(path: number[] | null) {
    const sequence = ++specSequence
    const ids = array(path).map(Number).filter(id => id > 0)
    specGroups.value = []; specError.value = ''; gradeOptions.value = defaultGrades.value; specLoading.value = false
    if (!ids.length || basicFirst.value) return
    specLoading.value = true
    try {
        const res = await getSpecOptionsByCategory({ category_id: ids[ids.length - 1], 'category_path[]': ids })
        if (sequence !== specSequence || !visible.value) return
        specGroups.value = array(res.data?.spec_groups)
        gradeOptions.value = array(res.data?.grades).length ? res.data.grades : defaultGrades.value
    } catch { if (sequence === specSequence) specError.value = '分类规格加载失败，请重试；已填资料会保留。' }
    finally { if (sequence === specSequence) specLoading.value = false }
}

async function load() {
    if (saving.value) return
    const sequence = ++requestSequence
    ++specSequence
    loading.value = true; ready.value = false; loadError.value = ''; submitError.value = ''
    try {
        if (materialMode.value) {
            const res = await getDeviceIntakeMaterial(source.value.intake_id)
            if (sequence === requestSequence && visible.value) populateMaterial(res.data)
            return
        }
        const [preview, categories, brands, labels, grades] = await Promise.all([
            previewDeviceIntake({ intake_id: source.value.intake_id }), getCategoryTree(), getBrandList({}), getLabelList({}), getGrades()
        ])
        if (sequence !== requestSequence || !visible.value) return
        const data = preview.data
        if (!data || typeof data !== 'object' || Array.isArray(data)) throw new Error('没有返回交接资料，请刷新列表后重试')
        categoryOptions.value = array(categories.data); brandOptions.value = array(brands.data); labelOptions.value = array(labels.data)
        defaultGrades.value = array(grades.data); gradeOptions.value = defaultGrades.value
        serviceOptions.value = array(data.service_options); check.value = data.check || {}
        if (data.material_task?.status) basicFirst.value = data.material_task.status !== 'none'
        imageUrls.value = array(data.images ?? source.value.images).filter(url => typeof url === 'string' && !!url.trim())
        activeImage.value = 0
        const row = source.value
        Object.assign(form, emptyForm(), {
            intake_id: row.intake_id, goods_name: data.goods_name || [row.model_name, row.memory, row.condition_grade].filter(Boolean).join(' '),
            sub_title: data.sub_title || '', goods_category: array(data.goods_category).map(Number).filter(id => id > 0),
            memory: data.memory_group || row.memory || '', condition_grade: data.condition_grade || row.condition_grade || '',
            service_ids: array(data.service_ids), label_ids: array(data.label_ids),
            delivery_type: array(data.delivery_type).length ? data.delivery_type : ['express'],
            price: Number(data.price ?? row.sale_price) || 0, peer_price: Number(data.peer_price ?? row.peer_price) || 0,
            pricing_base_price: Number(data.peer_price ?? row.peer_price) || 0,
            cost_price: Number(data.cost_price ?? row.cost_price) || 0,
            market_price: basicFirst.value ? Number(data.sale_price ?? row.sale_price) || 0 : 0
        })
        // 分岗模式展示交接原价，不让自动试算顺带改价，保持后端价格归属校验。
        if (basicFirst.value) form.price = Number(data.sale_price ?? row.sale_price ?? data.price) || 0
        pricingReady.value = basicFirst.value; autoTierPricing.value = false; pricingKey.value++
        initialForm.value = JSON.parse(JSON.stringify(form)); ready.value = true
        void loadSpecOptions(form.goods_category)
    } catch (error) { if (sequence === requestSequence) loadError.value = errorText(error, '请检查网络后重试，尚未执行上架。') }
    finally { if (sequence === requestSequence) loading.value = false }
}

async function open(row: Record<string, any>, mode: 'build' | 'material' = 'build') {
    if (saving.value) return
    materialMode.value = mode === 'material'; materialInfo.value = null; materialForm.value = emptyMaterialForm(); initialMaterial.value = ''; refreshingCatalog.value = false
    source.value = { ...row }; basicFirst.value = !!row.material_task && row.material_task.status !== 'none'
    initialForm.value = null; attempted.value = false; activeTab.value = materialMode.value ? 'attributes' : 'basic'; specGroups.value = []; specError.value = ''
    previewing.value = false; visible.value = true
    await load()
}

function onPricingPolicy(policy: { enabled?: number }) {
    autoTierPricing.value = Number(policy.enabled) === 1
    pricingReady.value = true
}

async function locate(item: ReadinessItem) {
    activeTab.value = item.tab
    if (!item.ok) attempted.value = true
    await nextTick()
    if (item.key === 'category') categoryRef.value?.$el?.querySelector('input')?.focus()
    if (item.key === 'title') titleRef.value?.focus?.()
    if (item.key === 'images' && !item.ok) feedback.warning(item.message)
}

async function beforeClose(done: () => void) {
    if (saving.value || previewing.value) return
    if (dirty.value && !await feedback.confirm({ title: materialMode.value ? '资料尚未保存' : '资料尚未上架', message: '关闭会放弃本次修改，ERP 原始交接资料不受影响。', confirmText: '放弃修改', cancelText: '继续编辑' })) return
    invalidateRequests(); done()
}

async function submit() {
    if (materialMode.value || saving.value || loading.value || !ready.value || loadError.value || !visible.value) return
    attempted.value = true; submitError.value = ''
    if (missing.value.length) { await locate(missing.value[0]); return }
    saving.value = true
    try {
        await buildDeviceIntake({ ...form, goods_name: form.goods_name.trim(), goods_category: [...form.goods_category],
            label_ids: [...form.label_ids], service_ids: [...form.service_ids], delivery_type: [...form.delivery_type] })
        initialForm.value = null; visible.value = false; emit('published')
    } catch (error) { submitError.value = errorText(error, '上架请求未完成，请核对网络及商品状态后重试；本次填写仍保留。') }
    finally { saving.value = false }
}

const formatTime = (value: number) => value ? new Date(value * 1000).toLocaleString('zh-CN', { hour12: false }) : ''
const formatDate = (value: number) => {
    if (!value) return ''
    const date = new Date(value * 1000)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}
function populateMaterial(data: any) {
    if (!data?.catalog || !Array.isArray(data.catalog.templates)) throw new Error('商城属性选项未返回，请同步更新 phone_shop 配套后端后重试')
    materialInfo.value = data
    const values = data.form || {}
    source.value = { ...source.value, model_name: data.model_name, imei: data.imei, memory: values.memory_group, color: values.device_color, condition_grade: values.condition_grade }
    imageUrls.value = array(data.images).filter(url => typeof url === 'string' && !!url.trim()); activeImage.value = 0; check.value = data.check || {}
    Object.assign(form, emptyForm(), { intake_id: data.intake_id, goods_name: values.goods_name || '', sub_title: values.sub_title || '' })
    materialForm.value = { memory_group: values.memory_group || '', device_color: values.device_color || '', condition_grade: values.condition_grade || '',
        battery_health: batteryValue(values.battery_health), warranty_date: formatDate(Number(values.warranty_expire_time || 0)),
        attr_ids: array(values.attr_ids).map(Number), attr_format: array(values.attr_format) }
    materialForm.value.attr_format = mergeParameters(parameterFields(data.catalog, materialForm.value.attr_ids), materialForm.value.attr_ids, materialForm.value.attr_format)
    autoTierPricing.value = false
    initialForm.value = JSON.parse(JSON.stringify(form)); initialMaterial.value = JSON.stringify(materialForm.value); ready.value = true
}
async function refreshCatalog() {
    if (!materialMode.value || !ready.value || loading.value || saving.value || refreshingCatalog.value) return
    const sequence = ++requestSequence
    refreshingCatalog.value = true
    try {
        const res = await getDeviceIntakeMaterial(source.value.intake_id)
        if (sequence !== requestSequence || !visible.value) return
        if (!res.data?.catalog) throw new Error('商城选项未返回，请更新配套后端')
        // 只刷新选项，不覆盖正在编辑的数据或旧 revision；他人已保存时由后端阻止覆盖。
        materialInfo.value.catalog = res.data.catalog
        materialForm.value.attr_format = mergeParameters(parameterFields(res.data.catalog, materialForm.value.attr_ids), materialForm.value.attr_ids, materialForm.value.attr_format)
        feedback.success('商城选项已刷新，当前填写内容已保留')
    } catch (error) { if (sequence === requestSequence) feedback.error(errorText(error, '选项刷新失败，当前填写内容已保留')) }
    finally { if (sequence === requestSequence) refreshingCatalog.value = false }
}
async function submitMaterial(action: 'save' | 'complete') {
    if (!materialMode.value || !materialInfo.value || saving.value || loading.value || refreshingCatalog.value || !ready.value || loadError.value || !visible.value) return
    submitError.value = ''
    if (!form.goods_name.trim()) { activeTab.value = 'basic'; submitError.value = '请填写商品标题'; return }
    const review = materialReview(materialInfo.value.catalog, materialForm.value)
    if (review.invalidParameters) { activeTab.value = 'attributes'; submitError.value = '部分参数选项已移除，请重新选择或清空后保存'; return }
    saving.value = true
    try {
        if (action === 'complete' && !await feedback.confirm({ title: '核对完成', message: `${review.notes}确认已完成本次核对？仅结束资料待办，不改变上架和交易状态。`, confirmText: '确认完成', cancelText: '继续完善', type: review.notes ? 'warning' : 'info' })) return
        const values = materialForm.value
        const res = await saveDeviceIntakeMaterial(source.value.intake_id, {
            goods_name: form.goods_name.trim(), sub_title: form.sub_title, memory_group: values.memory_group, device_color: values.device_color,
            condition_grade: values.condition_grade, battery_health: values.battery_health ?? -1, warranty_expire_time: values.warranty_date || 0,
            attr_ids: [...values.attr_ids], attr_format: JSON.parse(JSON.stringify(values.attr_format)), action, revision: materialInfo.value.material_task.revision
        })
        populateMaterial(res.data); emit('saved')
        if (res.data?.warning) feedback.warning(res.data.warning)
        else feedback.success(action === 'complete' ? '资料已核对完成，商品交易状态未改变' : '进度已保存，可稍后继续完善')
        if (action === 'complete') visible.value = false
    } catch (error) { submitError.value = errorText(error, '保存未完成，本次填写仍保留，请核对后重试。') }
    finally { saving.value = false }
}

defineExpose({ open })
</script>

<style scoped lang="scss">
.intake-build { display: grid; grid-template-columns: 244px minmax(0, 1fr); gap: 26px; min-width: 0; color: var(--el-text-color-primary); }
.intake-build__loading { padding: 24px; min-height: 380px; p { margin-top: 20px; color: var(--el-text-color-secondary); text-align: center; } }
.intake-build__preview { align-self: start; padding: 16px; background: var(--el-fill-color-light); border: 1px solid var(--el-border-color-lighter); border-radius: 12px; min-width: 0;
    h3 { font-size: 15px; line-height: 22px; margin: 14px 0 4px; overflow-wrap: anywhere; }
}
.intake-build__eyebrow { display: flex; align-items: center; justify-content: space-between; font-size: 12px; color: var(--el-text-color-secondary); margin-bottom: 12px; }
.intake-build__cover { position: relative; height: 188px; display: flex; align-items: center; justify-content: center; background: var(--el-bg-color); border-radius: 8px; overflow: hidden;
    .el-image { width: 100%; height: 100%; }
}
.intake-build__image-count { position: absolute; bottom: 8px; left: 8px; padding: 3px 8px; border-radius: 4px; color: #fff; background: rgba(15, 23, 42, .65); font-size: 11px; pointer-events: none; }
.intake-build__image-error { display: block; padding: 18px; font-size: 12px; line-height: 20px; color: var(--el-text-color-secondary); }
.intake-build__thumbs { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 8px;
    button { padding: 2px; width: 46px; height: 46px; border: 1px solid var(--el-border-color); background: var(--el-bg-color); border-radius: 6px; cursor: pointer; overflow: hidden; }
    .selected { border-color: var(--el-color-primary); box-shadow: 0 0 0 1px var(--el-color-primary); }
    .el-image { width: 100%; height: 100%; border-radius: 3px; }
}
.intake-build__device-spec, .intake-build__muted { font-size: 12px; line-height: 19px; color: var(--el-text-color-secondary); margin: 6px 0 0; overflow-wrap: anywhere; }
.intake-build__imei { display: flex; flex-wrap: wrap; gap: 6px 10px; margin: 12px 0; font-size: 12px; span { color: var(--el-text-color-secondary); } strong { font-weight: 500; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; } }
.intake-build__editor { min-width: 0; }
.intake-build__notice { display: flex; align-items: flex-start; gap: 8px; padding: 10px 12px; border-radius: 8px; background: var(--el-color-primary-light-9); color: var(--el-color-primary); font-size: 12px; line-height: 20px; margin-bottom: 12px; .el-icon { flex: none; margin-top: 3px; } }
.intake-build__tabs { :deep(.el-tabs__header) { margin-bottom: 18px; } :deep(.el-tabs__item) { font-size: 14px; } }
.intake-build__editor { :deep(.el-form-item) { margin-bottom: 20px; } :deep(.el-form-item__label) { margin-bottom: 6px; line-height: 20px; font-size: 13px; } :deep(.el-select), :deep(.el-cascader), :deep(.el-input-number) { width: 100%; } :deep(.el-input-number .el-input__inner) { text-align: left; } }
.intake-build__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; }
.intake-build__help { flex-basis: 100%; font-size: 12px; line-height: 19px; color: var(--el-text-color-secondary); margin: 6px 0 0; }
.intake-build__optional { border-top: 1px solid var(--el-border-color-lighter); :deep(.el-collapse-item__header) { font-weight: 500; font-size: 13px; } :deep(.el-collapse-item__content) { padding: 4px 0 12px; } }
.intake-build__section-heading { display: flex; align-items: baseline; flex-wrap: wrap; gap: 8px; margin: 0 0 12px; font-size: 14px; span { color: var(--el-text-color-secondary); font-size: 12px; } }
.intake-build__readonly-prices { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; border: 1px solid var(--el-border-color-lighter); background: var(--el-fill-color-light); border-radius: 8px; padding: 16px; margin-bottom: 18px;
    div { display: flex; flex-direction: column; gap: 6px; min-width: 0; } span { font-size: 12px; color: var(--el-text-color-secondary); }
    strong { font-size: 22px; font-variant-numeric: tabular-nums; overflow-wrap: anywhere; } p { grid-column: 1 / -1; font-size: 12px; line-height: 19px; color: var(--el-text-color-secondary); margin: 0; }
}
.intake-build__costs { margin-bottom: 20px; }
.intake-build__delivery { .el-checkbox-group { display: flex; flex-wrap: wrap; gap: 8px; } :deep(.el-checkbox.is-bordered) { margin: 0; padding: 0 12px; } }
.intake-build__description { margin-top: 20px; }
.intake-build__footer { width: 100%; display: flex; align-items: center; justify-content: space-between; gap: 16px; text-align: left; }
.intake-build__footer-copy { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; font-size: 12px; line-height: 18px; color: var(--el-text-color-secondary); strong { color: var(--el-text-color-primary); font-weight: 500; } }
.intake-build__footer-actions { flex: none; display: flex; gap: 8px; white-space: nowrap; }
.intake-build__footer-actions .el-button { margin-left: 0; }
.intake-build__history { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; font-size: 12px; color: var(--el-text-color-secondary); }
.intake-build__missing { align-self: flex-start; padding: 0; border: 0; background: none; color: var(--el-color-warning-dark-2); font: inherit; text-align: left; cursor: pointer; }
.intake-build__error { color: var(--el-color-danger); overflow-wrap: anywhere; max-height: 54px; overflow: auto; }
@media (max-width: 1100px) { .intake-build { grid-template-columns: 208px minmax(0, 1fr); gap: 18px; } .intake-build__preview { padding: 12px; } .intake-build__cover { height: 154px; } }
@media (max-width: 760px) {
    .intake-build { grid-template-columns: 1fr; gap: 18px; }
    .intake-build__preview { display: grid; grid-template-columns: 104px minmax(0, 1fr); gap: 0 14px; }
    .intake-build__eyebrow { grid-column: 1 / -1; }
    .intake-build__cover { grid-column: 1; grid-row: 2 / 6; height: 104px; }
    .intake-build__preview h3, .intake-build__device-spec, .intake-build__imei { grid-column: 2; margin: 0 0 5px; }
    .intake-build__thumbs { grid-column: 1 / -1; grid-row: 6; }
    .intake-build__preview > .intake-build__muted { grid-column: 1 / -1; }
    .intake-build__footer { align-items: flex-start; gap: 10px; }
}
@media (max-width: 480px) { .intake-build__grid { grid-template-columns: 1fr; } .intake-build__footer { flex-direction: column; } .intake-build__footer-actions { align-self: flex-end; } }
</style>
