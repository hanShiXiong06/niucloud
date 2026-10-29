export type ErpListingAction = 'one_stop' | 'photo' | 'price' | 'media_price' | 'material'

export function erpListingFormDefinition(contract: any, action: ErpListingAction) {
    const fallbackFields: Record<ErpListingAction, string[]> = {
        one_stop: ['catalog_product_id', 'spec', 'image_urls', 'video_url', 'retail_price', 'quality_remark', 'remark_public', 'remark_internal'],
        photo: ['image_urls', 'video_url', 'quality_remark'],
        price: ['retail_price'],
        media_price: ['image_urls', 'video_url', 'quality_remark', 'retail_price'],
        material: ['catalog_product_id', 'spec', 'remark_public'],
    }
    const fallbackRequired: Record<ErpListingAction, string[]> = {
        one_stop: ['catalog_product_id', 'spec', 'image_urls', 'retail_price'],
        photo: ['image_urls'],
        price: ['retail_price'],
        media_price: ['image_urls', 'retail_price'],
        material: ['catalog_product_id', 'spec'],
    }
    return contract?.forms?.[action] || {
        action,
        title: '完善商品资料',
        description: '',
        submit_label: '保存',
        visible_fields: fallbackFields[action],
        editable_fields: fallbackFields[action],
        required_fields: fallbackRequired[action],
    }
}

export function erpListingFieldVisible(contract: any, action: ErpListingAction, field: string) {
    return erpListingFormDefinition(contract, action).visible_fields?.includes(field) === true
}

export function erpListingFormPayload(form: Record<string, any>, contract: any, action: ErpListingAction) {
    const definition = erpListingFormDefinition(contract, action)
    return Object.fromEntries([
        ...definition.editable_fields.filter((field: string) => field !== 'mall_model').map((field: string) => [field, form[field]]),
        ['workflow_action', action],
        ...(Number(definition.publish_basic) === 1 ? [['publish_basic', 1]] : []),
        ...(contract?.mall && !Number(contract.mall.connected) && ['media_price', 'one_stop'].includes(action) ? [['defer_publish', 1]] : []),
    ])
}

export function validateErpListingForm(form: Record<string, any>, contract: any, action: ErpListingAction): string {
    const definition = erpListingFormDefinition(contract, action)
    const labels: Record<string, string> = {
        mall_category_id: '商城分类',
        mall_memory: '商城内存',
        mall_color: '商城颜色',
        mall_grade_id: '成色等级',
        mall_warranty_date: '保修到期日',
        mall_battery_health: '电池健康度',
        catalog_product_id: '商品目录型号',
        spec: '设备规格',
        image_urls: '商品图片',
        video_url: '展示视频',
        retail_price: '销售价格',
        quality_remark: '质检备注',
        remark_public: '对外说明',
        remark_internal: '对内备注',
    }
    const missing = (definition.required_fields || []).filter((field: string) => {
        if (['catalog_product_id', 'mall_category_id', 'retail_price'].includes(field)) return !Number.isFinite(Number(form[field])) || Number(form[field] || 0) <= 0
        if (field === 'image_urls') {
            if (Array.isArray(form[field])) return form[field].filter(Boolean).length === 0
            return String(form[field] || '').split(',').map((item) => item.trim()).filter(Boolean).length === 0
        }
        return !String(form[field] || '').trim()
    })
    if (missing.length) return `请先完善${missing.map((field: string) => labels[field] || field).join('、')}`
    const battery = form.mall_battery_health
    if (Number(definition.publish_basic) === 1 && battery !== '' && battery !== null && battery !== undefined) {
        if (!['string', 'number'].includes(typeof battery) || !/^[0-9]+$/.test(String(battery)) || !Number.isInteger(Number(battery)) || Number(battery) < 0 || Number(battery) > 100) return '电池健康度请填写 0–100 之间的整数，未知请留空'
    }
    if (Number(definition.publish_basic) === 1 && (Number(form.mall_grade_unmatched || 0) === 1 || (Number(form.mall_grade_id || 0) === 0 && String(form.mall_grade_name || '').trim()))) return '原成色等级已失效或尚未匹配商城字典，请重新选择或清空等级'
    if (Number(definition.publish_basic) === 1 && form.mall_image_mode !== 'custom') {
        if (!String(form.mall_model || '').trim()) return '设备型号缺失，无法匹配标准图；请先完善设备资料或使用自定义实拍'
        if (!String(form.mall_memory || '').trim() || !String(form.mall_color || '').trim()) return '使用标准图时，请先选择商城内存和颜色'
    }
    return ''
}
