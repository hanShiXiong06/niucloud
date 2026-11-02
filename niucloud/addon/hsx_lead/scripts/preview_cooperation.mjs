import http from 'node:http'
import { readFile, stat } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

// Local development preview only. Does not publish, modify Host, or proxy production.
const root=path.resolve(path.dirname(fileURLToPath(import.meta.url)),'../../../..')
const admin=path.join(root,'output/lead-cooperation-admin-build')
const mobile=process.env.HSX_LEAD_MOBILE_BUILD||path.join(root,'output/lead-cooperation-mobile-h5-build')
const port=Number(process.env.HSX_LEAD_PREVIEW_PORT||18116)
const backend=new URL(process.env.HSX_LEAD_LOCAL_API||'http://127.0.0.1:18114')
if(!['127.0.0.1','localhost'].includes(backend.hostname)||backend.protocol!=='http:'||!Number.isInteger(port)||port<1024||port>65535)throw new Error('This preview only accepts a local HTTP API and a non-privileged port')
await stat(path.join(admin,'index.html'));await stat(path.join(mobile,'index.html'))
const mime={'.html':'text/html; charset=utf-8','.js':'text/javascript; charset=utf-8','.css':'text/css; charset=utf-8','.json':'application/json','.svg':'image/svg+xml','.png':'image/png','.jpg':'image/jpeg','.jpeg':'image/jpeg','.webp':'image/webp','.ico':'image/x-icon','.woff':'font/woff','.woff2':'font/woff2','.ttf':'font/ttf'}
const server=http.createServer(async(req,res)=>{
  try{
    const url=new URL(req.url,'http://127.0.0.1:'+port), pathname=decodeURIComponent(url.pathname)
    if(['/api/','/adminapi/','/upload/','/uploads/','/attachment/'].some(prefix=>pathname.startsWith(prefix))){
      const upstream=http.request(new URL(req.url,backend),{method:req.method,headers:{...req.headers,host:backend.host}},answer=>{res.writeHead(answer.statusCode||502,answer.headers);answer.pipe(res)})
      upstream.on('error',()=>{if(!res.headersSent)res.writeHead(502,{'content-type':'text/plain; charset=utf-8'});res.end('本地PHP服务未启动，请先按文档启动127.0.0.1:18114')});req.pipe(upstream);return
    }
    const isMobile=pathname==='/wap'||pathname.startsWith('/wap/'), folder=isMobile?mobile:admin
    let relative=pathname.replace(/^\/wap\/?/,'').replace(/^\//,'')
    const assetIndex=pathname.indexOf('/assets/');if(assetIndex>=0)relative=pathname.slice(assetIndex+1)
    let file=path.resolve(folder,relative||'index.html')
    if(file!==folder&&!file.startsWith(folder+path.sep)){res.writeHead(403);res.end();return}
    let buffer
    try{if(!(await stat(file)).isFile())throw new Error('directory');buffer=await readFile(file)}catch{
      if(path.extname(relative)&&!relative.endsWith('.html')){res.writeHead(404);res.end('Not found');return}
      file=path.join(folder,'index.html');buffer=await readFile(file)
    }
    if(!isMobile&&file.endsWith('index.html'))buffer=Buffer.from(buffer.toString().replace('<head>','<head><base href="/">'))
    res.writeHead(200,{'content-type':mime[path.extname(file)]||'application/octet-stream','cache-control':'no-store'});res.end(buffer)
  }catch{res.writeHead(400,{'content-type':'text/plain'});res.end('Invalid local preview request')}
})
server.listen(port,'127.0.0.1',()=>console.log(`Platform: http://127.0.0.1:${port}/site/hsx_lead/workbench\nMerchant: http://127.0.0.1:${port}/wap/100005/addon/hsx_lead/pages/workbench/index\nLocal API: ${backend.origin} (no production proxy)`))
