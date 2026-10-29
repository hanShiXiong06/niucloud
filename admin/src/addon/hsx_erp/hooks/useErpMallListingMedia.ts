import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { getErpListingCatalog } from '@/addon/hsx_erp/api/erp'

export function erpListingImagePaths(value: any): string[] {
    if (Array.isArray(value)) return value.map(item => String(item || '').trim()).filter(Boolean)
    const text = String(value || '').trim()
    if (!text) return []
    if (text.startsWith('[')) {
        try { const parsed = JSON.parse(text); if (Array.isArray(parsed)) return erpListingImagePaths(parsed) } catch { /* 普通路径继续处理 */ }
    }
    return text.split(',').map(item => item.trim()).filter(Boolean)
}

export function erpMallListingInitialFields(row: Record<string, any>) {
    return {
        mall_asset_id: Number(row.id || 0),
        mall_model: String(row.model || '').trim(),
        mall_brand_id: Number(row.mall_brand_id || 0),
        mall_brand_name: String(row.mall_brand_name || '').trim(),
        mall_memory: String(row.mall_memory || '').trim(),
        mall_color: String(row.mall_color || '').trim(),
        mall_grade_id: Number(row.mall_grade_id || 0),
        mall_grade_name: String(row.mall_grade_name || '').trim(),
        mall_grade_unmatched: 0,
        // 日期由后端按本站业务时区反显；空串表示明确未填，不从旧时间戳猜回日期。
        mall_warranty_date: typeof row.mall_warranty_date === 'string' ? row.mall_warranty_date : '',
        // 显式 0% 与未知不同；不从旧 asset.battery=0 猜测电池健康度。
        mall_battery_health: row.mall_battery_health ?? '',
        mall_label_ids: Array.isArray(row.mall_label_ids) ? [...row.mall_label_ids] : [],
        // 未知来源的已有图片视为本机实拍，不能自动登记到标准图库。
        mall_image_mode: ['standard', 'custom'].includes(row.mall_image_mode)
            ? row.mall_image_mode : (erpListingImagePaths(row.image_urls).length ? 'custom' : 'standard'),
        mall_standard_register: 0,
    }
}

export function erpMallImageIdentity(form: Record<string, any>): string {
    return JSON.stringify([
        Number(form.mall_category_id || 0), String(form.mall_model || '').trim(),
        String(form.mall_memory || '').trim(), String(form.mall_color || '').trim(),
    ])
}

const emptyStandard = () => ({ eligible: false, matched: false, key: '', images: [] as string[], missing: [] as string[], update_at: 0, message: '' })
const optionText = (value: any): string => typeof value === 'string' || typeof value === 'number'
    ? String(value).trim() : String(value?.item_value ?? value?.value ?? value?.label ?? value?.name ?? '').trim()
const optionList = (value: any): string[] => Array.isArray(value) ? [...new Set(value.map(optionText).filter(Boolean))] : []
const normalizeChoice = (value: string, memory = false): string => {
    const normalized = value.trim().replace(/\s+/g, '').toLowerCase()
    return memory ? normalized.replace(/gb/g, 'g').replace(/tb/g, 't') : normalized
}

/** PC 与移动端共用的标准图选择状态；只管理当前弹窗，不写图库。 */
export function useErpMallListingMedia(
    getForm: () => Record<string, any>, enabled: () => boolean,
    update: (patch: Record<string, any>) => void,
) {
    const loading = ref(false)
    const attributesReady = ref(false)
    const labelsReady = ref(false)
    const error = ref('')
    const memoryOptions = ref<string[]>([])
    const colorOptions = ref<string[]>([])
    const attributeNotice = ref('')
    const grades = ref<{ grade_id: number; grade_name: string }[]>([])
    const mallLabels = ref<{ label_id: number; label_name: string; group_id?: number; group_name?: string }[]>([])
    const mallBrands = ref<{ brand_id: number; brand_name: string }[]>([])
    const categorySuggestionNotice = ref('')
    const standard = ref(emptyStandard())
    const mode = computed(() => getForm().mall_image_mode === 'custom' ? 'custom' : 'standard')
    const gradeNeedsSelection = computed(() => Number(getForm().mall_grade_unmatched || 0) === 1
        || (Number(getForm().mall_grade_id || 0) === 0 && !!String(getForm().mall_grade_name || '').trim()))
    const imageContextKey = computed(() => `${mode.value}:${erpMallImageIdentity(getForm())}`)
    const standardDrafts = new Map<string, { image_urls: string; register: number }>()
    let customDraft = ''
    let initialized = false
    let requestSeq = 0
    let timer: ReturnType<typeof setTimeout> | undefined
    let disposed = false
    let loadedIdentity = ''
    let gradeSelectionTouched = false
    let pendingCategory: number | null = null
    let brandSelectionTouched = false
    const requestIdentity = () => JSON.stringify([Number(getForm().mall_asset_id || 0), erpMallImageIdentity(getForm())])

    function rememberDraft() {
        const form = getForm()
        const imageUrls = erpListingImagePaths(form.image_urls).join(',')
        if (mode.value === 'custom') customDraft = imageUrls
        else standardDrafts.set(erpMallImageIdentity(form), { image_urls: imageUrls, register: Number(form.mall_standard_register || 0) })
    }

    function selectMode(value: 'standard' | 'custom') {
        if (value === mode.value) return
        rememberDraft()
        const key = erpMallImageIdentity(getForm())
        const draft = standardDrafts.get(key)
        const matched = loadedIdentity === key && standard.value.matched
        update({
            mall_image_mode: value,
            image_urls: value === 'custom' ? customDraft : (matched ? standard.value.images.join(',') : draft?.image_urls || ''),
            mall_standard_register: value === 'standard' && !matched ? Number(draft?.register || 0) : 0,
        })
    }

    function changeIdentity(patch: Record<string, any>) {
        rememberDraft()
        const key = erpMallImageIdentity({ ...getForm(), ...patch })
        const draft = standardDrafts.get(key)
        standard.value = emptyStandard()
        loadedIdentity = ''
        if (Object.prototype.hasOwnProperty.call(patch, 'mall_category_id')) {
            attributesReady.value = false
            memoryOptions.value = []; colorOptions.value = []; grades.value = []
        }
        update({ ...patch, ...(mode.value === 'standard' ? { image_urls: draft?.image_urls || '', mall_standard_register: Number(draft?.register || 0) } : {}) })
    }

    function selectCategory(value: number, label = '') {
        if (Number(value || 0) === Number(getForm().mall_category_id || 0)) return
        pendingCategory = Number(value || 0)
        attributeNotice.value = ''
        categorySuggestionNotice.value = ''
        // 先带着设备已知规格查询目标分类，再按目标字典精确核对；不做跨分类模糊匹配。
        changeIdentity({ mall_category_id: Number(value || 0), mall_category_name: label })
    }

    function selectGrade(id: number, name = '') {
        gradeSelectionTouched = true
        const selected = Number(getForm().mall_grade_id || 0) === id ? 0 : id
        update({ mall_grade_id: selected, mall_grade_name: selected > 0 ? name : '', mall_grade_unmatched: 0 })
    }
    function selectBrand(id: number, name = '') {
        brandSelectionTouched = true
        const brand = mallBrands.value.find(item => item.brand_id === Number(id))
        if (Number(id) > 0 && !brand) return
        update({ mall_brand_id: brand?.brand_id || 0, mall_brand_name: brand?.brand_name || '' })
    }

    function changeImages(value: any) {
        const imageUrls = erpListingImagePaths(value).join(',')
        if (mode.value === 'custom') {
            customDraft = imageUrls
            update({ image_urls: imageUrls, mall_standard_register: 0 })
        } else if (canUploadStandard.value) {
            standardDrafts.set(erpMallImageIdentity(getForm()), { image_urls: imageUrls, register: imageUrls ? 1 : 0 })
            update({ image_urls: imageUrls, mall_standard_register: imageUrls ? 1 : 0 })
        }
    }

    async function load(token: number, identity: string) {
        const form = getForm()
        try {
            const res: any = await getErpListingCatalog({
                asset_id: Number(form.mall_asset_id || 0),
                category_id: Number(form.mall_category_id || 0), model: String(form.mall_model || '').trim(),
                memory: String(form.mall_memory || '').trim(), color: String(form.mall_color || '').trim(),
            })
            if (disposed || token !== requestSeq || identity !== requestIdentity() || !enabled()) return
            const data = res?.data || {}
            if (!Array.isArray(data.brands)) throw new Error('商城品牌返回不完整，请更新配套后端后重试；原选择尚未修改')
            if (!Array.isArray(data.labels)) throw new Error('商城标签返回不完整，请更新配套后端后重试；原选择尚未修改')
            if (Array.isArray(data.brands)) mallBrands.value = data.brands.filter((item: any) => Number.isSafeInteger(Number(item?.brand_id)) && Number(item.brand_id) > 0 && String(item.brand_name || '').trim())
                .map((item: any) => ({ brand_id: Number(item.brand_id), brand_name: String(item.brand_name).trim() }))
            // 品牌、标签属于本站全局字典，不能依赖是否已选择商城分类。
            mallLabels.value = data.labels.filter((item: any) => Number.isSafeInteger(Number(item?.label_id)) && Number(item.label_id) > 0 && String(item.label_name || '').trim())
                .map((item: any) => ({ ...item, label_id: Number(item.label_id), label_name: String(item.label_name).trim() }))
            labelsReady.value = true
            const suggestion = data.category_suggestion
            const suggestedBrand = !brandSelectionTouched && !Number(getForm().mall_brand_id || 0)
                ? mallBrands.value.find(item => item.brand_id === Number(suggestion?.brand_id)) : undefined
            if (!Number(form.mall_category_id || 0)) {
                if (suggestion?.status === 'ready' && Number(suggestion.category_id) > 0) {
                    categorySuggestionNotice.value = String(suggestion.message || '已按相同回收型号带入商城分类，请核对。')
                    update({ mall_category_id: Number(suggestion.category_id), mall_category_name: String(suggestion.label || ''),
                        ...(suggestedBrand ? { mall_brand_id: suggestedBrand.brand_id, mall_brand_name: suggestedBrand.brand_name } : {}) })
                } else {
                    categorySuggestionNotice.value = String(suggestion?.message || '')
                    if (suggestedBrand) update({ mall_brand_id: suggestedBrand.brand_id, mall_brand_name: suggestedBrand.brand_name })
                }
                return
            }
            if (!Array.isArray(data.grades) || !Array.isArray(data.attributes?.memory_group) || !Array.isArray(data.attributes?.device_color)) {
                throw new Error('商城规格与等级返回不完整，请重新加载；原选择尚未修改')
            }
            memoryOptions.value = optionList(data.attributes?.memory_group)
            colorOptions.value = optionList(data.attributes?.device_color)
            attributesReady.value = true
            grades.value = (Array.isArray(data.grades) ? data.grades : []).map((item: any) => ({
                grade_id: Number(item.grade_id || 0), grade_name: String(item.grade_name || '').trim(),
            })).filter((item: any) => item.grade_id > 0 && item.grade_name)
            const patch: Record<string, any> = suggestedBrand ? { mall_brand_id: suggestedBrand.brand_id, mall_brand_name: suggestedBrand.brand_name } : {}
            const current = getForm()
            const selectedGrade = grades.value.find(item => item.grade_id === Number(current.mall_grade_id || 0))
            if (selectedGrade) { patch.mall_grade_name = selectedGrade.grade_name; patch.mall_grade_unmatched = 0 }
            else if (Number(current.mall_grade_id || 0) > 0) {
                // 已失效的 ID 不能借同名换绑到另一个等级；保留原名称，要求明确重选或清空。
                gradeSelectionTouched = true
                patch.mall_grade_id = 0
                patch.mall_grade_unmatched = 1
            }
            else if (!gradeSelectionTouched && Number(current.mall_grade_id || 0) === 0 && String(current.mall_grade_name || '').trim()) {
                const matches = grades.value.filter(item => item.grade_name === String(current.mall_grade_name).trim())
                if (matches.length === 1) { patch.mall_grade_id = matches[0].grade_id; patch.mall_grade_name = matches[0].grade_name }
            }
            const verifyCategory = pendingCategory === Number(current.mall_category_id || 0)
            if (verifyCategory) {
                pendingCategory = null
            }
            const rejected: string[] = []
            let canonicalized = false
            for (const [field, options, label] of [
                ['mall_memory', memoryOptions.value, '内存'], ['mall_color', colorOptions.value, '颜色'],
            ] as [string, string[], string][]) {
                const saved = String(current[field] || '').trim()
                if (!saved || !options.length || options.includes(saved)) continue
                const matches = options.filter(value => normalizeChoice(value, field === 'mall_memory') === normalizeChoice(saved, field === 'mall_memory'))
                if (matches.length === 1) { patch[field] = matches[0]; canonicalized = true }
                else if (verifyCategory) { patch[field] = ''; rejected.push(label) }
            }
            if (rejected.length || canonicalized) {
                if (rejected.length) attributeNotice.value = `当前分类没有匹配的${rejected.join('、')}，已清空，请重新选择。`
                changeIdentity(patch)
                return
            }
            const result = data.standard_images || {}
            const images = erpListingImagePaths(result.images)
            standard.value = { eligible: Number(result.eligible) === 1, matched: Number(result.matched) === 1 && images.length > 0, key: String(result.key || ''), images, missing: Array.isArray(result.missing) ? result.missing : [], update_at: Number(result.update_at || 0), message: String(result.message || '').trim() }
            const imageIdentity = erpMallImageIdentity(getForm())
            loadedIdentity = imageIdentity
            if (standard.value.matched) standardDrafts.set(imageIdentity, { image_urls: images.join(','), register: 0 })
            if (mode.value === 'standard') {
                const draft = standardDrafts.get(imageIdentity)
                patch.image_urls = standard.value.matched ? images.join(',') : draft?.image_urls || ''
                patch.mall_standard_register = standard.value.matched ? 0 : Number(draft?.register || 0)
            }
            if (Object.keys(patch).length) update(patch)
        } catch (e: any) {
            if (disposed || token !== requestSeq || identity !== requestIdentity()) return
            standard.value = emptyStandard()
            error.value = e?.message || e?.msg || '商城规格和标准图加载失败，请重试'
        } finally {
            if (!disposed && token === requestSeq) loading.value = false
        }
    }

    function refresh() {
        if (timer) clearTimeout(timer)
        const token = ++requestSeq
        labelsReady.value = false
        if (!enabled()) {
            attributesReady.value = false
            loading.value = false; error.value = ''; standard.value = emptyStandard(); return
        }
        loading.value = true; error.value = ''
        const identity = requestIdentity()
        timer = setTimeout(() => { void load(token, identity) }, 180)
    }

    // 监听稳定的身份字符串；不能返回新数组，否则图片回填也会触发重复请求。
    watch(() => enabled() ? requestIdentity() : '', identity => {
        if (identity && !initialized) {
            initialized = true
            if (mode.value === 'custom') customDraft = erpListingImagePaths(getForm().image_urls).join(',')
            else if (Number(getForm().mall_standard_register || 0) === 1) rememberDraft()
        }
        refresh()
    }, { immediate: true })
    onBeforeUnmount(() => { disposed = true; requestSeq++; if (timer) clearTimeout(timer) })

    const canUploadStandard = computed(() => !loading.value && !error.value && !standard.value.message && standard.value.eligible && !standard.value.matched)
    const imageHint = computed(() => {
        if (mode.value === 'custom') return '仅用于这台设备，不保存为标准图。请拍摄真实外观及瑕疵，第一张作为封面。'
        if (!Number(getForm().mall_category_id || 0)) return '请先选择商城分类，再选择内存和颜色。'
        if (loading.value) return '正在匹配本型号、内存和颜色的标准图…'
        if (error.value) return error.value
        if (standard.value.message) return standard.value.message
        if (!standard.value.eligible) return standard.value.message || '请选择完整的内存和颜色后匹配标准图；设备型号缺失时请先完善设备资料。'
        if (standard.value.matched) return '已自动使用本型号、内存、颜色的标准图；如需展示本机真实外观，请切换“自定义实拍”。'
        return '尚无该型号、内存和颜色的标准图。首次上传并保存后，后续同规格同颜色可自动复用；不要上传本机专属瑕疵图。'
    })

    return { loading, attributesReady, labelsReady, error, memoryOptions, colorOptions, attributeNotice, grades, mallLabels, mallBrands, categorySuggestionNotice, gradeNeedsSelection, standard, mode, imageContextKey, canUploadStandard, imageHint, selectMode, selectCategory, selectGrade, selectBrand, changeIdentity, changeImages, refresh }
}
