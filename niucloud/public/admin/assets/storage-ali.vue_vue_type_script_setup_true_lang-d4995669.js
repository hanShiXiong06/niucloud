import{d as S,r as g,R as K,c as q,e as h,v as k,x as s,g as b,y as r,i as E,A as f,B as c,u as a,Q as F}from"./base-06478700.js";/* empty css                   */import{E as A}from"./el-overlay-42a687c6.js";/* empty css                  */import{a as C,E as I}from"./el-form-item-314d006d.js";/* empty css                 *//* empty css                 */import{t as l}from"./index-81ed253c.js";import{aa as N,ab as T}from"./index-80fd3793.js";import{E as j,b as O}from"./index-6290cf08.js";import{E as G}from"./index-b68e8463.js";import{E as L}from"./index-c2f001d3.js";import{v as Q}from"./directive-cb2d3366.js";const $={class:"form-tip"},z={class:"form-tip"},H={class:"dialog-footer"},ne=S({__name:"storage-ali",emits:["complete"],setup(J,{expose:w,emit:P}){let d=g(!1);const n=g(!0),v={storage_type:"",bucket:"",access_key:"",secret_key:"",endpoint:"",domain:"",is_use:""},o=K({...v}),V=g(),B=q(()=>({bucket:[{required:!0,message:l("aliBucketPlaceholder"),trigger:"blur"}],access_key:[{required:!0,message:l("aliAccessKeyPlaceholder"),trigger:"blur"}],secret_key:[{required:!0,message:l("aliSecretKeyPlaceholder"),trigger:"blur"}],endpoint:[{required:!0,message:l("aliEndpointPlaceholder"),trigger:"blur"}],domain:[{required:!0,message:l("domainPlaceholder"),trigger:"blur"}]})),D=async u=>{n.value||!u||await u.validate(async e=>{e&&(n.value=!0,N(o).then(_=>{n.value=!1,d.value=!1,P("complete")}).catch(_=>{n.value=!1}))})};return w({showDialog:d,setFormData:async(u=null)=>{if(n.value=!0,Object.assign(o,v),u){const e=await(await T(u.storage_type)).data;Object.keys(o).forEach(i=>{e[i]!=null&&(o[i]=e[i]),e.params[i]!=null&&(o[i]=e.params[i].value)})}n.value=!1}}),(u,e)=>{const i=j,_=O,m=C,p=G,U=I,y=L,x=A,R=Q;return h(),k(x,{modelValue:a(d),"onUpdate:modelValue":e[8]||(e[8]=t=>E(d)?d.value=t:d=t),title:a(l)("aliStorage"),width:"580px","destroy-on-close":!0},{footer:s(()=>[b("span",H,[r(y,{onClick:e[6]||(e[6]=t=>E(d)?d.value=!1:d=!1)},{default:s(()=>[f(c(a(l)("cancel")),1)]),_:1}),r(y,{type:"primary",loading:n.value,onClick:e[7]||(e[7]=t=>D(V.value))},{default:s(()=>[f(c(a(l)("confirm")),1)]),_:1},8,["loading"])])]),default:s(()=>[F((h(),k(U,{model:o,"label-width":"140px",ref_key:"formRef",ref:V,rules:a(B),class:"page-form"},{default:s(()=>[r(m,{label:a(l)("isUse")},{default:s(()=>[r(_,{modelValue:o.is_use,"onUpdate:modelValue":e[0]||(e[0]=t=>o.is_use=t)},{default:s(()=>[r(i,{label:"1"},{default:s(()=>[f(c(a(l)("startUsing")),1)]),_:1}),r(i,{label:"0"},{default:s(()=>[f(c(a(l)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"]),r(m,{label:a(l)("aliBucket"),prop:"bucket"},{default:s(()=>[r(p,{modelValue:o.bucket,"onUpdate:modelValue":e[1]||(e[1]=t=>o.bucket=t),placeholder:a(l)("aliBucketPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),b("div",$,c(a(l)("aliBucketTips")),1)]),_:1},8,["label"]),r(m,{label:a(l)("aliAccessKey"),prop:"access_key"},{default:s(()=>[r(p,{modelValue:o.access_key,"onUpdate:modelValue":e[2]||(e[2]=t=>o.access_key=t),placeholder:a(l)("aliAccessKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(l)("aliSecretKey"),prop:"secret_key"},{default:s(()=>[r(p,{modelValue:o.secret_key,"onUpdate:modelValue":e[3]||(e[3]=t=>o.secret_key=t),placeholder:a(l)("aliSecretKeyPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),r(m,{label:a(l)("aliEndpoint"),prop:"endpoint"},{default:s(()=>[r(p,{modelValue:o.endpoint,"onUpdate:modelValue":e[4]||(e[4]=t=>o.endpoint=t),placeholder:a(l)("aliEndpointPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),b("div",z,c(a(l)("aliEndpointTips")),1)]),_:1},8,["label"]),r(m,{label:a(l)("domain"),prop:"domain"},{default:s(()=>[r(p,{modelValue:o.domain,"onUpdate:modelValue":e[5]||(e[5]=t=>o.domain=t),placeholder:a(l)("domainPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[R,n.value]])]),_:1},8,["modelValue","title"])}}});export{ne as _};
