import"./base-962c0c23.js";/* empty css                   *//* empty css                  *//* empty css                     *//* empty css                */import{_ as P}from"./index-f4731ae1.js";/* empty css                 */import{t as l}from"./index-105fbad0.js";import{P as g,v as I,Q as B}from"./sys-4abedf95.js";import{c as D,a as F}from"./storage-abe718b1.js";import{u as R}from"./vue-router-79053937.js";import{E as S}from"./index-93f2c618.js";import{a as T,E as q}from"./index-61c777fa.js";import{E as L}from"./index-69523418.js";import{E as j}from"./index-bba9e58c.js";import{v as W}from"./directive-c0c3e9a3.js";import{d as A,r as f,M as v,b,e as M,f as m,x as c,u as r,L as O,m as V,p as s,q as o,C as Q,v as $}from"./runtime-core.esm-bundler-dc7a07d7.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-d0d31f1b.js";import"./el-overlay-60700377.js";import"./event-ff03ec12.js";import"./index-5d86eb33.js";import"./focus-trap-b8b5a003.js";import"./attachment-56217309.js";/* empty css               *//* empty css                  *//* empty css                  */import"./index-7525671c.js";import"./el-radio-bfd4b1ad.js";import"./index-8bcaafa6.js";import"./index-7a123a20.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import"./el-avatar-3bb47ce2.js";import"./index-d57cc47d.js";/* empty css                      *//* empty css                    *//* empty css                 *//* empty css                    *//* empty css                   */import"./index-cc03da8f.js";import"./index-50a00d09.js";import"./index-a9dd5cf5.js";import"./index-190f0dba.js";import"./index-9f244af6.js";import"./index-df51d91a.js";import"./isEqual-c7d5e6d9.js";import"./_Uint8Array-6ff3cafa.js";import"./flatten-d5d1dc97.js";import"./index-100b1469.js";import"./index-b933df38.js";import"./index-4f5c40a5.js";import"./strings-4868a118.js";import"./common-080b9b9f.js";import"./common-2cf17469.js";import"./_initCloneObject-28e6bdaa.js";const z={class:"main-container"},G={class:"flex ml-[18px] justify-between items-center mt-[20px] mb-[5px]"},H={class:"text-[24px]"},J={class:"panel-title !text-sm"},K={class:"panel-title !text-sm"},X={class:"panel-title !text-sm"},Y={class:"fixed-footer-wrap"},Z={class:"fixed-footer"},rt=A({__name:"system",setup(ee){const x=R().meta.title,i=f(!0),h=f(),e=v({site_name:"",logo:"",desc:"",latitude:"",keywords:"",longitude:"",province_id:"",city_id:"",district_id:"",address:"",full_address:"",business_hours:"",phone:"",front_end_name:"",front_end_logo:"",icon:"",wechat_code:"",enterprise_wechat:"",tel:""});(async(p=0)=>{const t=await(await g()).data;Object.keys(e).forEach(n=>{t[n]!=null&&(e[n]=t[n])});const d=await(await I()).data;e.wechat_code=d.wechat_code,e.enterprise_wechat=d.enterprise_wechat,e.tel=d.tel,h.value=D(),i.value=!1})();const w=f(),y=v({site_name:[{required:!0,message:l("siteNamePlaceholder"),trigger:"blur"}],front_end_name:[{required:!0,message:l("frontEndNamePlaceholder"),trigger:"blur"}]}),E=async p=>{i.value||!p||await p.validate(async t=>{t&&(i.value=!0,B(e).then(()=>{i.value=!1,k()}).catch(()=>{i.value=!1}))})},k=async()=>{const p=await(await g()).data;F.set({key:"siteInfo",data:p})};return(p,t)=>{const d=S,n=T,u=P,_=L,N=q,U=j,C=W;return b(),M("div",z,[m("div",G,[m("span",H,c(r(x)),1)]),O((b(),V(N,{model:e,"label-width":"150px",ref_key:"formRef",ref:w,rules:y,class:"page-form"},{default:s(()=>[o(_,{class:"box-card !border-none",shadow:"never"},{default:s(()=>[m("h3",J,c(r(l)("websiteInfo")),1),o(n,{label:r(l)("siteName"),prop:"site_name"},{default:s(()=>[o(d,{modelValue:e.site_name,"onUpdate:modelValue":t[0]||(t[0]=a=>e.site_name=a),placeholder:r(l)("siteNamePlaceholder"),class:"input-width",clearable:"",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(n,{label:r(l)("logo")},{default:s(()=>[o(u,{modelValue:e.logo,"onUpdate:modelValue":t[1]||(t[1]=a=>e.logo=a)},null,8,["modelValue"])]),_:1},8,["label"]),o(n,{label:r(l)("icon")},{default:s(()=>[o(u,{modelValue:e.icon,"onUpdate:modelValue":t[2]||(t[2]=a=>e.icon=a)},null,8,["modelValue"])]),_:1},8,["label"]),o(n,{label:r(l)("keywords")},{default:s(()=>[o(d,{modelValue:e.keywords,"onUpdate:modelValue":t[3]||(t[3]=a=>e.keywords=a),placeholder:r(l)("keywordsPlaceholder"),class:"input-width",clearable:"",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(n,{label:r(l)("desc")},{default:s(()=>[o(d,{modelValue:e.desc,"onUpdate:modelValue":t[4]||(t[4]=a=>e.desc=a),type:"textarea",rows:"4",clearable:"",placeholder:r(l)("descPlaceholder"),class:"input-width",maxlength:"100"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1}),o(_,{class:"box-card !border-none",shadow:"never"},{default:s(()=>[m("h3",K,c(r(l)("frontEndInfo")),1),o(n,{label:r(l)("frontEndName")},{default:s(()=>[o(d,{modelValue:e.front_end_name,"onUpdate:modelValue":t[5]||(t[5]=a=>e.front_end_name=a),placeholder:r(l)("frontEndNamePlaceholder"),class:"input-width",clearable:"",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(n,{label:r(l)("frontEndLogo")},{default:s(()=>[o(u,{modelValue:e.front_end_logo,"onUpdate:modelValue":t[6]||(t[6]=a=>e.front_end_logo=a)},null,8,["modelValue"])]),_:1},8,["label"])]),_:1}),h.value=="admin"?(b(),V(_,{key:0,class:"box-card !border-none",shadow:"never"},{default:s(()=>[m("h3",X,c(r(l)("serviceInformation")),1),o(n,{label:r(l)("contactsTel")},{default:s(()=>[o(d,{modelValue:e.tel,"onUpdate:modelValue":t[7]||(t[7]=a=>e.tel=a),placeholder:r(l)("contactsTelPlaceholder"),class:"input-width",clearable:"",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(n,{label:r(l)("wechatCode")},{default:s(()=>[o(u,{modelValue:e.wechat_code,"onUpdate:modelValue":t[8]||(t[8]=a=>e.wechat_code=a)},null,8,["modelValue"])]),_:1},8,["label"]),o(n,{label:r(l)("customerServiceCode")},{default:s(()=>[o(u,{modelValue:e.enterprise_wechat,"onUpdate:modelValue":t[9]||(t[9]=a=>e.enterprise_wechat=a)},null,8,["modelValue"])]),_:1},8,["label"])]),_:1})):Q("",!0)]),_:1},8,["model","rules"])),[[C,i.value]]),m("div",Y,[m("div",Z,[o(U,{type:"primary",loading:i.value,onClick:t[10]||(t[10]=a=>E(w.value))},{default:s(()=>[$(c(r(l)("save")),1)]),_:1},8,["loading"])])])])}}});export{rt as default};