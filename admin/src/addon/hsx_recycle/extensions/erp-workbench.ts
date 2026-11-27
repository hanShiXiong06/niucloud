// Optional desktop workbench entry, consumed only if this site grants the recycle audit menu.
export default {desk:{name:'recycle_business_operation_logs',label:'ERP 操作记录',
    panel:{load:()=>import('./business-operation-list.vue'),props:{source:'erp'}}}}
