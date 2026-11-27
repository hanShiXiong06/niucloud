import request from '@/utils/request'

// Preparing a link and registering an existing receipt are deliberately different actions.
export const prepareOriginalReceipt = (sid:number,sale:number) => request.post('integration/task/prepare/'+sid,{intent:'capture_receipt_source',sale_id:sale})
export const readOriginalReceipt = (id:number) => request.get('boyuan/receipt-tasks/'+id)
export const submitOriginalReceipt = (id:number,data:Record<string,unknown>) => request.post('boyuan/receipt-tasks/'+id+'/submit',data)
