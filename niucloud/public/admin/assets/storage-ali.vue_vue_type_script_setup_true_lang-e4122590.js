import"./base-0e92f4db.js";/* empty css                   */import{E as q}from"./el-overlay-3eff2fc5.js";/* empty css                  */import{a as K,E as R}from"./el-form-item-c2dd2ffe.js";/* empty css                 *//* empty css                 */import{t as l}from"./index-bf9b1162.js";import{a7 as F,a8 as C}from"./index-95d7b9b8.js";import{E as A,b as I}from"./index-9aa10ae4.js";import{E as N}from"./index-8cefa3ab.js";import{E as T}from"./index-e09a20f5.js";import{v as j}from"./directive-c6f70b8e.js";import{d as L,r as g,M as O,c as G,b as h,m as k,p as s,f as b,q as r,i as E,v as f,x as c,u as a,L as M}from"./runtime-core.esm-bundler-67034826.js";const $={class:"form-tip"},z={class:"form-tip"},H={class:"dialog-footer"},ue=L({__name:"storage-ali",emits:["complete"],setup(J,{expose:w,emit:P}){let d=g(!1);const n=g(!0),v={storage_type:"",bucket:"",access_key:"",secret_key:"",endpoint:"",domain:"",is_use:""},o=O({...v}),V=g(),D=G(()=>({bucket:[{required:!0,message:l("aliBucketPlaceholder"),trigger:"blur"}],access_key:[{required:!0,message:l("aliAccessKeyPlaceholder"),trigger:"blur"}],secret_key:[{required:!0,message:l("aliSecretKeyPlaceholder"),trigger:"blur"}],endpoint:[{required:!0,message:l("aliEndpointPlaceholder"),trigger:"blur"}],domain:[{required:!0,message:l("domainPlaceholder"),trigger:"blur"}]})),U=async u=>{n.value||!u||await u.validate(async e=>{e&&(n.value=!0,F(o).then(_=>{n.value=!1,d.value=!1,P("complete")}).catch(_=>{n.value=!1}))})};return w({showDialog:d,setFormData:async(u=null)=>{if(n.value=!0,Object.assign(o,v),u){const e=await(await C(u.storage_type)).data;Object.keys(o).forEach(i=>{e[i]!=null&&(o[i]=e[i]),e.params[i]!=null&&(o[i]=e.params[i].value)})}n.value=!1}}),(u,e)=>{const i=A,_=I,m=K,p=N,B=R,y=T,x=q,S=j;return h(),k(x,{modelValue:a(d),"onUpdate:modelValue":e[8]||(e[8]=t=>E(d)?d.value=t:d=t),title:a(l)("aliStorage"),width:"580px","destroy-on-close":!0},{footer:s(()=>[b("span",H,[r(y,{onClick:e[6]||(e[6]=t=>E(d)?d.value=!1:d=!1)},{default:s(()=>[f(c(a(l)("cancel")),1)]),_:1}),r(y,{type:"primary",loading:n.value,onClick:e[7]||(e[7]=t=>U(V.value))},{default:s(()=>[f(c(a(l)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[M((h(),k(B,{model:o,"label-width":"140px",ref_key:"formRef",ref:V,rules:a(D),class:"page-form"},{default:s(()=>[r(m,{label:a(l)("isUse")},{default:s(()=>[r(_,{modelValue:o.is_use,"onUpdate:modelValue":e[0]||(e[0]=t=>o.is_use=t)},{default:s(()=>[r(i,{label:"1"},{default:s(()=>[f(c(a(l)("startUsing")),1)]),_:1}),r(i,{label:"0"},{default:s(()=>[f(c(a(l)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(m,{label:a(l)("aliBucket"),prop:"bucket"},{default:s(()=>[r(p,{modelValue:o.bucket,"onUpdate:modelValue":e[1]||(e[1]=t=>o.bucket=t),placeholder:a(l)("aliBucketPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),b("div",$,c(a(l)("aliBucketTips")),1)]),_:1},8,["label"]),r(m,{label:a(l)("aliAccessKey"),prop:"access_key"},{default:s(()=>[r(p,{modelValue:o.access_key,"onUpdate:modelValue":e[2]||(e[2]=t=>o.access_key=t),placeholder:a(l)("aliAccessKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(l)("aliSecretKey"),prop:"secret_key"},{default:s(()=>[r(p,{modelValue:o.secret_key,"onUpdate:modelValue":e[3]||(e[3]=t=>o.secret_key=t),placeholder:a(l)("aliSecretKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(l)("aliEndpoint"),prop:"endpoint"},{default:s(()=>[r(p,{modelValue:o.endpoint,"onUpdate:modelValue":e[4]||(e[4]=t=>o.endpoint=t),placeholder:a(l)("aliEndpointPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),b("div",z,c(a(l)("aliEndpointTips")),1)]),_:1},8,["label"]),r(m,{label:a(l)("domain"),prop:"domain"},{default:s(()=>[r(p,{modelValue:o.domain,"onUpdate:modelValue":e[5]||(e[5]=t=>o.domain=t),placeholder:a(l)("domainPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[S,n.value]])]),_:1},8,["modelValue","title"])}}});export{ue as _};
