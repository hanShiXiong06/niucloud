
import request from '@/utils/request'

/***************************************************** hello world ****************************************************/
export function getHelloWorld() {
    return request.get(`recycle/hello_world`)
}


export function getDict() {
    return request.get(`recycle/dict/29`)
}