import{g as S,r as g,a4 as x,j as F,m as V,D as k,E as s,q,F as r,c as h,K as f,L as c,u as l,a1 as R}from"./base-d2ce4248.js";/* empty css                   */import{E as C}from"./el-overlay-7451f13b.js";/* empty css                  *//* empty css                     *//* empty css                 */import{E as j,b as A}from"./el-radio-b620ac73.js";import{t as a}from"./index-578c83eb.js";import{ab as I,ac as N}from"./index-057b5f2c.js";import{a as L,E as O}from"./index-f579a83b.js";import{E as T}from"./index-9997ff5d.js";import{E as G}from"./index-953c712f.js";import{v as $}from"./directive-3f066692.js";const z={class:"form-tip"},H={class:"dialog-footer"},ue=S({__name:"storage-qiniu",emits:["complete"],setup(J,{expose:E,emit:w}){let u=g(!1);const n=g(!0),b={storage_type:"",bucket:"",access_key:"",secret_key:"",domain:"",is_use:""},t=x({...b}),v=g(),D=F(()=>({bucket:[{required:!0,message:a("qiniuBucketPlaceholder"),trigger:"blur"}],access_key:[{required:!0,message:a("qiniuAccessKeyPlaceholder"),trigger:"blur"}],secret_key:[{required:!0,message:a("qiniuSecretKeyPlaceholder"),trigger:"blur"}],endpoint:[{required:!0,message:a("aliEndpointPlaceholder"),trigger:"blur"}],domain:[{required:!0,message:a("domainPlaceholder"),trigger:"blur"}]})),P=async d=>{n.value||!d||await d.validate(async e=>{e&&(n.value=!0,I(t).then(_=>{n.value=!1,u.value=!1,w("complete")}).catch(_=>{n.value=!1}))})};return E({showDialog:u,setFormData:async(d=null)=>{if(n.value=!0,Object.assign(t,b),d){const e=await(await N(d.storage_type)).data;Object.keys(t).forEach(i=>{e[i]!=null&&(t[i]=e[i]),e.params[i]!=null&&(t[i]=e.params[i].value)})}n.value=!1}}),(d,e)=>{const i=j,_=A,m=L,p=T,B=O,y=G,U=C,K=$;return V(),k(U,{modelValue:l(u),"onUpdate:modelValue":e[7]||(e[7]=o=>h(u)?u.value=o:u=o),title:l(a)("qiniuStorage"),width:"580px","destroy-on-close":!0},{footer:s(()=>[q("span",H,[r(y,{onClick:e[5]||(e[5]=o=>h(u)?u.value=!1:u=!1)},{default:s(()=>[f(c(l(a)("cancel")),1)]),_:1}),r(y,{type:"primary",loading:n.value,onClick:e[6]||(e[6]=o=>P(v.value))},{default:s(()=>[f(c(l(a)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[R((V(),k(B,{model:t,"label-width":"140px",ref_key:"formRef",ref:v,rules:l(D),class:"page-form"},{default:s(()=>[r(m,{label:l(a)("isUse")},{default:s(()=>[r(_,{modelValue:t.is_use,"onUpdate:modelValue":e[0]||(e[0]=o=>t.is_use=o)},{default:s(()=>[r(i,{label:"1"},{default:s(()=>[f(c(l(a)("startUsing")),1)]),_:1}),r(i,{label:"0"},{default:s(()=>[f(c(l(a)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(m,{label:l(a)("qiniuBucket"),prop:"bucket"},{default:s(()=>[r(p,{modelValue:t.bucket,"onUpdate:modelValue":e[1]||(e[1]=o=>t.bucket=o),placeholder:l(a)("qiniuBucketPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),q("div",z,c(l(a)("qiniuBucketTips")),1)]),_:1},8,["label"]),r(m,{label:l(a)("qiniuAccessKey"),prop:"access_key"},{default:s(()=>[r(p,{modelValue:t.access_key,"onUpdate:modelValue":e[2]||(e[2]=o=>t.access_key=o),placeholder:l(a)("qiniuAccessKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:l(a)("qiniuSecretKey"),prop:"secret_key"},{default:s(()=>[r(p,{modelValue:t.secret_key,"onUpdate:modelValue":e[3]||(e[3]=o=>t.secret_key=o),placeholder:l(a)("qiniuSecretKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:l(a)("domain"),prop:"domain"},{default:s(()=>[r(p,{modelValue:t.domain,"onUpdate:modelValue":e[4]||(e[4]=o=>t.domain=o),placeholder:l(a)("domainPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[K,n.value]])]),_:1},8,["modelValue","title"])}}});export{ue as _};
