import{d as T,r as y,R as B,w as K,e as h,f as N,g as i,u as e,B as r,Q as R,v as D,x as p,y as a,A as b}from"./base-06478700.js";/* empty css                   *//* empty css                  */import{a as $,E as I}from"./el-form-item-314d006d.js";import{_ as O}from"./index.vue_vue_type_style_index_0_lang-b8460f40.js";/* empty css                */import{_ as Q}from"./index-7eaa3a3d.js";/* empty css                 */import{t}from"./index-81ed253c.js";import{g as W,a as j,s as F}from"./aliapp-f34d1eab.js";import{a3 as L}from"./event-10eba222.js";import{u as z,a as M}from"./vue-router-d09a2c28.js";import{a as g}from"./index-b52d0f2a.js";import{E as G}from"./index-b68e8463.js";import{E as H}from"./index-e10fccde.js";import{E as J}from"./index-c2f001d3.js";import{v as X}from"./directive-cb2d3366.js";import"./index-2fcd1254.js";/* empty css                    */import"./common-92a35870.js";import"./index-41a974fa.js";import"./index-f27d6ce0.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-e59c442c.js";import"./el-overlay-42a687c6.js";import"./index-9fe5de95.js";import"./focus-trap-3e826cdc.js";import"./index-818c0ce2.js";import"./attachment-21666979.js";/* empty css               *//* empty css                  *//* empty css                  */import"./index-981b0207.js";import"./index-adb89d14.js";import"./el-main-9a0960e7.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-6b67c4ac.js";import"./el-tooltip-58212670.js";import"./index-2a269c7c.js";import"./index-e4abfaa5.js";import"./index-9ee9102c.js";/* empty css                      *//* empty css                    *//* empty css                 *//* empty css                 *//* empty css               *//* empty css                   */import"./index-4a01421d.js";import"./index-01f6e375.js";import"./validator-6e9db238.js";import"./index-c17093ae.js";import"./index-543fb162.js";import"./index-b6a184ba.js";import"./debounce-1db848fd.js";import"./position-c3bcd0be.js";import"./index-b56195b5.js";import"./index-40e21e72.js";import"./isEqual-42d4b10f.js";import"./index-137757c0.js";import"./index-35e821cc.js";import"./index-34d55b7e.js";import"./strings-fe930bc4.js";import"./index-5a0d60aa.js";const Y={class:"main-container"},Z={class:"detail-head"},ee=i("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1),te={class:"ml-[1px]"},oe=i("span",{class:"adorn"},"|",-1),ae={class:"right"},le={class:"panel-title !text-sm"},ie={class:"form-tip"},se={class:"panel-title !text-sm"},pe={class:"input-width"},re={class:"form-tip"},ne={class:"input-width"},de={class:"form-tip"},me={class:"input-width"},ue={class:"form-tip"},ce={class:"panel-title !text-sm"},_e={class:"flex"},fe={class:"panel-title !text-sm"},ve={class:"fixed-footer-wrap"},ye={class:"fixed-footer"},Pt=T({__name:"config",setup(he){const V=z(),w=M(),x=V.meta.title,m=y(!0),l=B({name:"",qrcode:"",private_key:"",app_id:"",aes_key:"",public_key_crt:"",alipay_public_key_crt:"",alipay_with_crt:"",request_url:""}),f=y();W().then(d=>{Object.assign(l,d.data),m.value=!1}),j().then(d=>{l.request_url=d.data.domain});const{copy:k,isSupported:E,copied:v}=L(),C=d=>{if(!E.value){g({message:t("notSupportCopy"),type:"warning"});return}k(d)};K(v,()=>{v.value&&g({message:t("copySuccess"),type:"success"})});const S=async d=>{m.value||!d||await d.validate(async o=>{o&&(m.value=!0,F(l).then(()=>{m.value=!1}).catch(()=>{m.value=!1}))})};return(d,o)=>{const u=G,n=$,P=Q,c=H,_=O,U=I,q=J,A=X;return h(),N("div",Y,[i("div",Z,[i("div",{class:"left",onClick:o[0]||(o[0]=s=>e(w).push({path:"/website/channel/aliapp"}))},[ee,i("span",te,r(e(t)("returnToPreviousPage")),1)]),oe,i("span",ae,r(e(x)),1)]),R((h(),D(U,{model:l,"label-width":"150px",ref_key:"formRef",ref:f,class:"page-form"},{default:p(()=>[a(c,{class:"box-card !border-none",shadow:"never"},{default:p(()=>[i("h3",le,r(e(t)("aliappSet")),1),a(n,{label:e(t)("aliappName")},{default:p(()=>[a(u,{modelValue:l.name,"onUpdate:modelValue":o[1]||(o[1]=s=>l.name=s),placeholder:e(t)("aliappNamePlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(n,{label:e(t)("aliappQrcode")},{default:p(()=>[a(P,{modelValue:l.qrcode,"onUpdate:modelValue":o[2]||(o[2]=s=>l.qrcode=s)},null,8,["modelValue"]),i("div",ie,r(e(t)("aliappQrcodeTips")),1)]),_:1},8,["label"])]),_:1}),a(c,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:p(()=>[i("h3",se,r(e(t)("aliappDevelopInfo")),1),a(n,{label:e(t)("aliappOriginal")},{default:p(()=>[a(u,{modelValue:l.private_key,"onUpdate:modelValue":o[3]||(o[3]=s=>l.private_key=s),placeholder:e(t)("aliappOriginalPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(n,{label:e(t)("aliappAppid")},{default:p(()=>[a(u,{modelValue:l.app_id,"onUpdate:modelValue":o[4]||(o[4]=s=>l.app_id=s),placeholder:e(t)("appidPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),a(n,{label:e(t)("countersignType")},{default:p(()=>[b(r(e(t)("certificate")),1)]),_:1},8,["label"]),a(n,{label:e(t)("publicKey")},{default:p(()=>[i("div",pe,[a(_,{modelValue:l.public_key_crt,"onUpdate:modelValue":o[5]||(o[5]=s=>l.public_key_crt=s),api:"sys/document/aliyun"},null,8,["modelValue"])]),i("div",re,r(e(t)("publicKeyTips")),1)]),_:1},8,["label"]),a(n,{label:e(t)("alipayPublicKey")},{default:p(()=>[i("div",ne,[a(_,{modelValue:l.alipay_public_key_crt,"onUpdate:modelValue":o[6]||(o[6]=s=>l.alipay_public_key_crt=s),api:"sys/document/aliyun"},null,8,["modelValue"])]),i("div",de,r(e(t)("alipayPublicKeyTips")),1)]),_:1},8,["label"]),a(n,{label:e(t)("alipayWithCrt")},{default:p(()=>[i("div",me,[a(_,{modelValue:l.alipay_with_crt,"onUpdate:modelValue":o[7]||(o[7]=s=>l.alipay_with_crt=s),api:"sys/document/aliyun"},null,8,["modelValue"])]),i("div",ue,r(e(t)("alipayWithCrtTips")),1)]),_:1},8,["label"])]),_:1}),a(c,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:p(()=>[i("h3",ce,r(e(t)("theServerSetting")),1),a(n,{label:"AESKey"},{default:p(()=>[a(u,{modelValue:l.aes_key,"onUpdate:modelValue":o[8]||(o[8]=s=>l.aes_key=s),placeholder:e(t)("AESKeyPlaceholder"),class:"input-width","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"])]),_:1})]),_:1}),a(c,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:p(()=>[i("div",_e,[i("h3",fe,r(e(t)("functionSetting")),1)]),a(n,{label:e(t)("serveWhiteList")},{default:p(()=>[a(u,{"model-value":l.request_url,class:"input-width",readonly:!0},{append:p(()=>[i("div",{class:"cursor-pointer",onClick:o[9]||(o[9]=s=>C(l.request_url))},r(e(t)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model"])),[[A,m.value]]),i("div",ve,[i("div",ye,[a(q,{type:"primary",loading:m.value,onClick:o[10]||(o[10]=s=>S(f.value))},{default:p(()=>[b(r(e(t)("save")),1)]),_:1},8,["loading"])])])])}}});export{Pt as default};