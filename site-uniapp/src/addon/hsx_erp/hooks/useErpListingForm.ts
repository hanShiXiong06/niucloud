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
        ...definition.editable_fields.map((field: string) => [field, form[field]]),
        ['workflow_action', action],
    ])
}

export function validateErpListingForm(form: Record<string, any>, contract: any, action: ErpListingAction): string {
    const definition = erpListingFormDefinition(contract, action)
    const labels: Record<string, string> = {
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
        if (field === 'catalog_product_id') return Number(form[field] || 0) <= 0
        if (field === 'retail_price') return Number(form[field] || 0) <= 0
        if (field === 'image_urls') {
            if (Array.isArray(form[field])) return form[field].filter(Boolean).length === 0
            return String(form[field] || '').split(',').map((item) => item.trim()).filter(Boolean).length === 0
        }
        return !String(form[field] || '').trim()
    })
    return missing.length ? `请先完善${Array.from(new Set(missing.map((field: string) => labels[field] || '必填资料'))).join('、')}` : ''
}
