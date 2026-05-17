
import request from '@/utils/request'

/***************************************************** hello world ****************************************************/
export function getHelloWorld() {
    return request.get(`wj_books/hello_world`)
}

