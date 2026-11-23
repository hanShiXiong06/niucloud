import request from '@/utils/request'

export type Row = Record<string, any>
export interface Field { name: string; label: string; type?: string; required?: boolean; help?: string; placeholder?: string; default?: any; options?: { label: string; value: any }[] }
export interface Adapter { code: string; name: string; version: string; supported_business_types: string[]; manual_business_types?: string[]; manual_target?:{mode:string;account_code:string}; manual_setup_hint?:string; manual_scope_options?:{value:string;label:string}[]; fields: Field[]; mapping_requirements: Record<string, boolean> }
export interface Page { list: Row[]; count: number }
const base = 'integration/'
const quiet = { showErrorMessage: false }
const get = async <T>(path: string, params?: Row): Promise<T> => (await request.get(base + path, { ...quiet, params })).data
const post = async <T>(path: string, data: Row = {}): Promise<T> => (await request.post(base + path, data, quiet)).data
const del = async <T>(path: string, data?: Row): Promise<T> => (await request.delete(base + path, { ...quiet, data })).data
export const api = {
    adapters: (forForm = false) => get<{list: Adapter[]}>(forForm ? 'connection/adapters' : 'adapter/list'),
    connections: (params: Row) => get<Page>('connection/lists', params),
    connection: (id: number) => get<Row>('connection/' + id),
    saveConnection: (id: number, form: Row) => post<Row>('connection/save/' + id, form),
    test: (id: number) => post('connection/test/' + id),
    status: (id: number, status: string) => post('connection/status/' + id, { status }),
    deleteConnection: (id: number) => del('connection/' + id),
    options: () => get<{list: Row[]}>('mapping/connections'),
    mappings: (params: Row) => get<Page>('mapping/lists', params),
    resources: (type: string, keyword: string) => get<{list: Row[]}>('mapping/resources', { resource_type: type, keyword }),
    saveMapping: (id: number, data: Row) => post('mapping/save/' + id, data),
    deleteMapping: (id: number) => del('mapping/' + id),
    tasks: (params: Row) => get<Page>('task/lists', params),
    task: (id: number) => get<Row>('task/' + id),
    statistics: () => get<{today: Row}>('task/statistics'),
    retry: (id: number) => post<Row>('task/retry/' + id),
    prepareOriginal: (id: number) => post<Row>('task/prepare/' + id),
    capturePurchaseSource: (id: number) => post<Row>('task/prepare/' + id,{intent:'capture_purchase_source'}),
    capturePaymentSource: (id:number,purchase:number)=> post<Row>('task/prepare/'+id,{intent:'capture_payment_source',purchase_id:purchase}),
    reviewOriginal: (id: number) => post<Row>('task/review/' + id),
    failures: (params: Row) => get<Page>('failure/lists', params),
    failure: (id: number) => get<Row>('failure/' + id),
    resolve: (id: number, note: string) => post('failure/resolve/' + id, { note }),
    deleteResolved: (ids: number[]) => del<{count: number}>('failure/batch_delete', { ids })
}
