import{g as N,r as w,a4 as h,w as R,m as y,n as $,q as p,L as s,u as o,a1 as B,D,E as r,F as l,K as f}from"./base-d2ce4248.js";/* empty css                   *//* empty css                  *//* empty css                     */import{E as K,b as O}from"./el-radio-b620ac73.js";/* empty css                */import{_ as I}from"./index-4b7c0a63.js";/* empty css                 */import{t as e}from"./index-578c83eb.js";import{g as k,s as j}from"./weapp-50d6994b.js";import{J as F}from"./event-f83e96f5.js";import{u as L}from"./vue-router-d3dc2686.js";import{a as x}from"./index-3118dac6.js";import{E as Q}from"./index-9997ff5d.js";import{a as W,E as G}from"./index-f579a83b.js";import{E as J}from"./index-32160c2f.js";import{E as z}from"./index-953c712f.js";import{v as H}from"./directive-3f066692.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-f7de127f.js";import"./el-overlay-7451f13b.js";import"./index-28969730.js";import"./focus-trap-b41dd321.js";import"./attachment-84ee7a05.js";/* empty css               *//* empty css                  *//* empty css                  */import"./index-057b5f2c.js";import"./storage-e62e496d.js";import"./index-758a5fe7.js";import"./index-92c8bc76.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import"./el-avatar-4397f45a.js";/* empty css                      *//* empty css                    *//* empty css                 *//* empty css                    *//* empty css                   */import"./index-3ff0840c.js";import"./index-faa3f8c5.js";import"./index-5e746953.js";import"./index-3ae544fb.js";import"./index-e41f0205.js";import"./index-13c7facf.js";import"./isEqual-51ec1a47.js";import"./_Uint8Array-6ca580e8.js";import"./flatten-2fc24abf.js";import"./index-aaab07eb.js";import"./index-83fe4dc1.js";import"./index-0ba64799.js";import"./strings-986fee93.js";import"./common-dd6d00bb.js";import"./common-2cf17469.js";import"./_initCloneObject-5fe9c070.js";const X={class:"main-container"},Y={class:"flex ml-[18px] justify-between items-center mt-[20px]"},Z={class:"text-[24px]"},ee={class:"panel-title !text-sm"},oe={class:"form-tip"},le={class:"panel-title !text-sm"},te={class:"form-tip"},ae={class:"form-tip"},re={class:"panel-title !text-sm"},pe={class:"form-tip"},se={class:"form-tip"},ie={class:"form-tip"},de={class:"form-tip"},ne={class:"form-tip"},ue={class:"flex"},me={class:"panel-title !text-sm"},ce={class:"fixed-footer-wrap"},_e={class:"fixed-footer"},wo=N({__name:"config",setup(fe){const V=L().meta.title,m=w(!0),a=h({weapp_name:"",weapp_original:"",app_id:"",app_secret:"",qr_code:"",token:"",encoding_aes_key:"",encryption_type:"not_encrypt",serve_url:"",request_url:"",socket_url:"",upload_url:"",download_url:""}),v=w(),E=h({weapp_name:[{required:!0,message:e("weappNamePlaceholder"),trigger:"blur"}],weapp_original:[{required:!0,message:e("weappOriginalPlaceholder"),trigger:"blur"}],app_id:[{required:!0,message:e("appidPlaceholder"),trigger:"blur"}],app_secret:[{required:!0,message:e("appSecretPlaceholder"),trigger:"blur"}],token:[{required:!0,message:e("tokenPlaceholder"),trigger:"blur"}],encoding_aes_key:[{required:!0,message:e("encodingAesKeyPlaceholder"),trigger:"blur"}]});k().then(u=>{Object.assign(a,u.data),m.value=!1}),k().then(u=>{Object.assign(u.data)});const{copy:P,isSupported:q,copied:b}=F(),c=u=>{if(!q.value){x({message:e("notSupportCopy"),type:"warning"});return}P(u)};R(b,()=>{b.value&&x({message:e("copySuccess"),type:"success"})});const C=async u=>{m.value||!u||await u.validate(async t=>{t&&(m.value=!0,j(a).then(()=>{m.value=!1}).catch(()=>{m.value=!1}))})};return(u,t)=>{const n=Q,d=W,U=I,_=J,g=K,S=O,T=G,A=z,M=H;return y(),$("div",X,[p("div",Y,[p("span",Z,s(o(V)),1)]),B((y(),D(T,{model:a,"label-width":"170px",ref_key:"formRef",ref:v,rules:E,class:"page-form"},{default:r(()=>[l(_,{class:"box-card !border-none",shadow:"never"},{default:r(()=>[p("h3",ee,s(o(e)("weappInfo")),1),l(d,{label:o(e)("weappName"),prop:"weapp_name"},{default:r(()=>[l(n,{modelValue:a.weapp_name,"onUpdate:modelValue":t[0]||(t[0]=i=>a.weapp_name=i),placeholder:o(e)("weappNamePlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(d,{label:o(e)("weappOriginal"),prop:"weapp_original"},{default:r(()=>[l(n,{modelValue:a.weapp_original,"onUpdate:modelValue":t[1]||(t[1]=i=>a.weapp_original=i),placeholder:o(e)("weappOriginalPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(d,{label:o(e)("weappQrcode"),prop:"qr_code"},{default:r(()=>[l(U,{modelValue:a.qr_code,"onUpdate:modelValue":t[2]||(t[2]=i=>a.qr_code=i)},null,8,["modelValue"]),p("div",oe,s(o(e)("weappQrcodeTips")),1)]),_:1},8,["label"])]),_:1}),l(_,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:r(()=>[p("h3",le,s(o(e)("weappDevelopInfo")),1),l(d,{label:o(e)("weappAppid"),prop:"app_id"},{default:r(()=>[l(n,{modelValue:a.app_id,"onUpdate:modelValue":t[3]||(t[3]=i=>a.app_id=i),placeholder:o(e)("appidPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),p("div",te,s(o(e)("weappAppidTips")),1)]),_:1},8,["label"]),l(d,{label:o(e)("weappAppsecret"),prop:"app_secret"},{default:r(()=>[l(n,{modelValue:a.app_secret,"onUpdate:modelValue":t[4]||(t[4]=i=>a.app_secret=i),placeholder:o(e)("appSecretPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),p("div",ae,s(o(e)("weappAppsecretTips")),1)]),_:1},8,["label"])]),_:1}),l(_,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:r(()=>[p("h3",re,s(o(e)("theServerSetting")),1),l(d,{label:"URL"},{default:r(()=>[l(n,{"model-value":a.serve_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:r(()=>[p("div",{class:"cursor-pointer",onClick:t[5]||(t[5]=i=>c(a.serve_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1}),l(d,{label:"Token",prop:"token"},{default:r(()=>[l(n,{modelValue:a.token,"onUpdate:modelValue":t[6]||(t[6]=i=>a.token=i),placeholder:o(e)("tokenPlaceholder"),class:"input-width",maxlength:"32","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),p("div",pe,s(o(e)("tokenTips")),1)]),_:1}),l(d,{label:"EncodingAESKey",prop:"encoding_aes_key"},{default:r(()=>[l(n,{modelValue:a.encoding_aes_key,"onUpdate:modelValue":t[7]||(t[7]=i=>a.encoding_aes_key=i),placeholder:o(e)("encodingAesKeyPlaceholder"),class:"input-width",maxlength:"43","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),p("div",se,s(o(e)("encodingAESKeyTips")),1)]),_:1}),l(d,{label:o(e)("encryptionType"),prop:"encryption_type"},{default:r(()=>[l(S,{modelValue:a.encryption_type,"onUpdate:modelValue":t[8]||(t[8]=i=>a.encryption_type=i)},{default:r(()=>[l(g,{label:"not_encrypt"},{default:r(()=>[f(s(o(e)("cleartextMode")),1)]),_:1}),l(g,{label:"compatible"},{default:r(()=>[f(s(o(e)("compatibleMode")),1)]),_:1}),l(g,{label:"safe"},{default:r(()=>[f(s(o(e)("safeMode")),1)]),_:1})]),_:1},8,["modelValue"]),p("div",ie,s(o(e)("cleartextModeTips")),1),p("div",de,s(o(e)("compatibleModeTips")),1),p("div",ne,s(o(e)("safeModeTips")),1)]),_:1},8,["label"])]),_:1}),l(_,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:r(()=>[p("div",ue,[p("h3",me,s(o(e)("functionSetting")),1)]),l(d,{label:o(e)("requestUrl")},{default:r(()=>[l(n,{"model-value":a.request_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:r(()=>[p("div",{class:"cursor-pointer",onClick:t[9]||(t[9]=i=>c(a.request_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"]),l(d,{label:o(e)("socketUrl")},{default:r(()=>[l(n,{"model-value":a.socket_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:r(()=>[p("div",{class:"cursor-pointer",onClick:t[10]||(t[10]=i=>c(a.socket_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"]),l(d,{label:o(e)("uploadUrl")},{default:r(()=>[l(n,{"model-value":a.upload_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:r(()=>[p("div",{class:"cursor-pointer",onClick:t[11]||(t[11]=i=>c(a.upload_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"]),l(d,{label:o(e)("downloadUrl")},{default:r(()=>[l(n,{"model-value":a.download_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:r(()=>[p("div",{class:"cursor-pointer",onClick:t[12]||(t[12]=i=>c(a.download_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model","rules"])),[[M,m.value]]),p("div",ce,[p("div",_e,[l(A,{type:"primary",loading:m.value,onClick:t[13]||(t[13]=i=>C(v.value))},{default:r(()=>[f(s(o(e)("save")),1)]),_:1},8,["loading"])])])])}}});export{wo as default};
