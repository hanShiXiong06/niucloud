import"./base-0e92f4db.js";/* empty css                   */import{E as S}from"./el-overlay-3eff2fc5.js";/* empty css                  */import{a as K,E as R}from"./el-form-item-c2dd2ffe.js";/* empty css                 *//* empty css                 */import{t as a}from"./index-2d1c7ba3.js";import{a7 as F,a8 as C}from"./index-fac59425.js";import{E as A,b as I}from"./index-9aa10ae4.js";import{E as N}from"./index-8cefa3ab.js";import{E as j}from"./index-e09a20f5.js";import{v as L}from"./directive-c6f70b8e.js";import{d as O,r as g,M as T,c as G,b as V,m as k,p as s,f as q,q as r,i as h,v as f,x as c,u as l,L as M}from"./runtime-core.esm-bundler-67034826.js";const $={class:"form-tip"},z={class:"dialog-footer"},ne=O({__name:"storage-qiniu",emits:["complete"],setup(H,{expose:w,emit:E}){let u=g(!1);const n=g(!0),b={storage_type:"",bucket:"",access_key:"",secret_key:"",domain:"",is_use:""},t=T({...b}),v=g(),D=G(()=>({bucket:[{required:!0,message:a("qiniuBucketPlaceholder"),trigger:"blur"}],access_key:[{required:!0,message:a("qiniuAccessKeyPlaceholder"),trigger:"blur"}],secret_key:[{required:!0,message:a("qiniuSecretKeyPlaceholder"),trigger:"blur"}],endpoint:[{required:!0,message:a("aliEndpointPlaceholder"),trigger:"blur"}],domain:[{required:!0,message:a("domainPlaceholder"),trigger:"blur"}]})),P=async d=>{n.value||!d||await d.validate(async e=>{e&&(n.value=!0,F(t).then(_=>{n.value=!1,u.value=!1,E("complete")}).catch(_=>{n.value=!1}))})};return w({showDialog:u,setFormData:async(d=null)=>{if(n.value=!0,Object.assign(t,b),d){const e=await(await C(d.storage_type)).data;Object.keys(t).forEach(i=>{e[i]!=null&&(t[i]=e[i]),e.params[i]!=null&&(t[i]=e.params[i].value)})}n.value=!1}}),(d,e)=>{const i=A,_=I,m=K,p=N,B=R,y=j,U=S,x=L;return V(),k(U,{modelValue:l(u),"onUpdate:modelValue":e[7]||(e[7]=o=>h(u)?u.value=o:u=o),title:l(a)("qiniuStorage"),width:"580px","destroy-on-close":!0},{footer:s(()=>[q("span",z,[r(y,{onClick:e[5]||(e[5]=o=>h(u)?u.value=!1:u=!1)},{default:s(()=>[f(c(l(a)("cancel")),1)]),_:1}),r(y,{type:"primary",loading:n.value,onClick:e[6]||(e[6]=o=>P(v.value))},{default:s(()=>[f(c(l(a)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[M((V(),k(B,{model:t,"label-width":"140px",ref_key:"formRef",ref:v,rules:l(D),class:"page-form"},{default:s(()=>[r(m,{label:l(a)("isUse")},{default:s(()=>[r(_,{modelValue:t.is_use,"onUpdate:modelValue":e[0]||(e[0]=o=>t.is_use=o)},{default:s(()=>[r(i,{label:"1"},{default:s(()=>[f(c(l(a)("startUsing")),1)]),_:1}),r(i,{label:"0"},{default:s(()=>[f(c(l(a)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(m,{label:l(a)("qiniuBucket"),prop:"bucket"},{default:s(()=>[r(p,{modelValue:t.bucket,"onUpdate:modelValue":e[1]||(e[1]=o=>t.bucket=o),placeholder:l(a)("qiniuBucketPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),q("div",$,c(l(a)("qiniuBucketTips")),1)]),_:1},8,["label"]),r(m,{label:l(a)("qiniuAccessKey"),prop:"access_key"},{default:s(()=>[r(p,{modelValue:t.access_key,"onUpdate:modelValue":e[2]||(e[2]=o=>t.access_key=o),placeholder:l(a)("qiniuAccessKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:l(a)("qiniuSecretKey"),prop:"secret_key"},{default:s(()=>[r(p,{modelValue:t.secret_key,"onUpdate:modelValue":e[3]||(e[3]=o=>t.secret_key=o),placeholder:l(a)("qiniuSecretKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:l(a)("domain"),prop:"domain"},{default:s(()=>[r(p,{modelValue:t.domain,"onUpdate:modelValue":e[4]||(e[4]=o=>t.domain=o),placeholder:l(a)("domainPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[x,n.value]])]),_:1},8,["modelValue","title"])}}});export{ne as _};
