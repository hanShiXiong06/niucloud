/* empty css             *//* empty css                   *//* empty css                  */import"./el-overlay-f7f710bd.js";/* empty css                  *//* empty css                     *//* empty css                 */import{E as q,b as K}from"./el-radio-c9a1047c.js";import{t as l}from"./index-5f4ce139.js";import{A as R,B as F}from"./sys-aa893c6b.js";import{a as A,E as C}from"./index-624573cc.js";import{E as I}from"./index-95693143.js";import{E as N}from"./index-4862d1b3.js";import{E as j}from"./index-4683bff4.js";import{v as L}from"./directive-a07a10ed.js";import{d as O,r as g,M as T,c as G,b as y,m as k,p as s,f as h,q as r,i as w,v as f,x as p,u as a,L as M}from"./runtime-core.esm-bundler-7c3fd514.js";const $={class:"form-tip"},z={class:"dialog-footer"},ce=O({__name:"storage-tencent",emits:["complete"],setup(H,{expose:E,emit:P}){let n=g(!1);const d=g(!0),b={storage_type:"",bucket:"",access_key:"",secret_key:"",domain:"",is_use:"",region:""},t=T({...b}),v=g(),B=G(()=>({bucket:[{required:!0,message:l("tencentBucketPlaceholder"),trigger:"blur"}],access_key:[{required:!0,message:l("tencentAccessKeyPlaceholder"),trigger:"blur"}],secret_key:[{required:!0,message:l("tencentSecretKeyPlaceholder"),trigger:"blur"}],region:[{required:!0,message:l("regionPlaceholder"),trigger:"blur"}],domain:[{required:!0,message:l("domainPlaceholder"),trigger:"blur"}]})),D=async u=>{d.value||!u||await u.validate(async e=>{e&&(d.value=!0,R(t).then(_=>{d.value=!1,n.value=!1,P("complete")}).catch(_=>{d.value=!1,n.value=!1}))})};return E({showDialog:n,setFormData:async(u=null)=>{if(d.value=!0,Object.assign(t,b),u){const e=await(await F(u.storage_type)).data;Object.keys(t).forEach(i=>{e[i]!=null&&(t[i]=e[i]),e.params[i]!=null&&(t[i]=e.params[i].value)})}d.value=!1}}),(u,e)=>{const i=q,_=K,c=A,m=I,U=C,V=N,x=j,S=L;return y(),k(x,{modelValue:a(n),"onUpdate:modelValue":e[8]||(e[8]=o=>w(n)?n.value=o:n=o),title:a(l)("tencentStorage"),width:"580px","destroy-on-close":!0},{footer:s(()=>[h("span",z,[r(V,{onClick:e[6]||(e[6]=o=>w(n)?n.value=!1:n=!1)},{default:s(()=>[f(p(a(l)("cancel")),1)]),_:1}),r(V,{type:"primary",loading:d.value,onClick:e[7]||(e[7]=o=>D(v.value))},{default:s(()=>[f(p(a(l)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[M((y(),k(U,{model:t,"label-width":"140px",ref_key:"formRef",ref:v,rules:a(B),class:"page-form"},{default:s(()=>[r(c,{label:a(l)("isUse")},{default:s(()=>[r(_,{modelValue:t.is_use,"onUpdate:modelValue":e[0]||(e[0]=o=>t.is_use=o)},{default:s(()=>[r(i,{label:"1"},{default:s(()=>[f(p(a(l)("startUsing")),1)]),_:1}),r(i,{label:"0"},{default:s(()=>[f(p(a(l)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(c,{label:a(l)("tencentBucket"),prop:"bucket"},{default:s(()=>[r(m,{modelValue:t.bucket,"onUpdate:modelValue":e[1]||(e[1]=o=>t.bucket=o),placeholder:a(l)("tencentBucketPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),h("div",$,p(a(l)("tencentBucketTips")),1)]),_:1},8,["label"]),r(c,{label:a(l)("tencentAccessKey"),prop:"access_key"},{default:s(()=>[r(m,{modelValue:t.access_key,"onUpdate:modelValue":e[2]||(e[2]=o=>t.access_key=o),placeholder:a(l)("tencentAccessKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(c,{label:a(l)("tencentSecretKey"),prop:"secret_key"},{default:s(()=>[r(m,{modelValue:t.secret_key,"onUpdate:modelValue":e[3]||(e[3]=o=>t.secret_key=o),placeholder:a(l)("tencentSecretKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(c,{label:a(l)("region"),prop:"region"},{default:s(()=>[r(m,{modelValue:t.region,"onUpdate:modelValue":e[4]||(e[4]=o=>t.region=o),placeholder:a(l)("regionPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(c,{label:a(l)("domain"),prop:"domain"},{default:s(()=>[r(m,{modelValue:t.domain,"onUpdate:modelValue":e[5]||(e[5]=o=>t.domain=o),placeholder:a(l)("domainPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[S,d.value]])]),_:1},8,["modelValue","title"])}}});export{ce as _};
