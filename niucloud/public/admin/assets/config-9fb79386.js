/* empty css             *//* empty css                   *//* empty css                  */import{a as N,E as j}from"./el-form-item-144bc604.js";/* empty css                       *//* empty css                 *//* empty css                */import{_ as $}from"./index-76a877c2.js";/* empty css                 */import{t as e}from"./index-ebefdd26.js";import{g as B,a as O,e as I}from"./wechat-9225af04.js";import{a3 as K}from"./event-3e082a4a.js";import{u as L,a as W}from"./vue-router-9f815af7.js";import{a as y}from"./index-a6ffcea0.js";import{E as F}from"./index-5f2625ed.js";import{E as Q}from"./index-c5af06c2.js";import{E as z,b as G}from"./index-6ff31475.js";import{E as H}from"./index-6f670765.js";import{v as J}from"./directive-d226d53f.js";import{d as X,r as x,M as b,w as Y,b as V,e as Z,f as r,u as o,x as s,L as ee,m as oe,p as l,q as t,v as f}from"./runtime-core.esm-bundler-c17ab5bc.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./_baseClone-37ba2e68.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-b9e244b2.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";import"./attachment-2dcaf405.js";/* empty css               *//* empty css                  *//* empty css                  */import"./index-d5447f06.js";import"./common-a45d855f.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-e42600b8.js";/* empty css                  */import"./index-138bfa06.js";import"./index-72bf6cb5.js";/* empty css                      *//* empty css                    *//* empty css                 */import"./el-tooltip-4ed993c7.js";/* empty css                 *//* empty css               *//* empty css                    *//* empty css                   */import"./index-0ee6390f.js";import"./index-b74135ff.js";import"./aria-adfa05c5.js";import"./validator-f5e079db.js";import"./index-4ea26b0e.js";import"./index-d6b4c236.js";import"./index-6701860e.js";import"./index-f6eed9e8.js";import"./debounce-196ce6a6.js";import"./position-0d02b322.js";import"./index-d64b2f0e.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./index-cefc0b67.js";import"./index-b7b91fcb.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./index-bfecf17f.js";const te={class:"main-container"},ae={class:"detail-head"},le=r("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1),re={class:"ml-[1px]"},se=r("span",{class:"adorn"},"|",-1),ie={class:"right"},ne={class:"panel-title !text-sm"},pe={class:"form-tip"},de={class:"panel-title !text-sm"},ce={class:"form-tip"},me={class:"form-tip"},ue={class:"panel-title !text-sm"},_e={class:"form-tip"},he={class:"form-tip"},fe={class:"form-tip"},ge={class:"form-tip"},be={class:"form-tip"},ve={class:"flex"},we={class:"panel-title !text-sm"},ye={class:"form-tip"},xe={class:"fixed-footer-wrap"},Ve={class:"fixed-footer"},Io=X({__name:"config",setup(ke){const k=L(),P=W(),E=k.meta.title,u=x(!0),n=b({wechat_name:"",wechat_original:"",app_id:"",app_secret:"",qr_code:"",token:"",encoding_aes_key:"",encryption_type:"not_encrypt"}),v=x(),S=b({wechat_name:[{required:!0,message:e("wechatNamePlaceholder"),trigger:"blur"}],wechat_original:[{required:!0,message:e("wechatOriginalPlaceholder"),trigger:"blur"}],app_id:[{required:!0,message:e("appidPlaceholder"),trigger:"blur"}],app_secret:[{required:!0,message:e("appSecretPlaceholder"),trigger:"blur"}],token:[{required:!0,message:e("tokenPlaceholder"),trigger:"blur"}],encoding_aes_key:[{required:!0,message:e("encodingAesKeyPlaceholder"),trigger:"blur"}]});B().then(m=>{Object.assign(n,m.data),u.value=!1});const c=b({business_domain:"",js_secure_domain:"",serve_url:"",web_auth_domain:""});O().then(m=>{Object.assign(c,m.data),u.value=!1});const{copy:C,isSupported:T,copied:w}=K(),_=m=>{if(!T.value){y({message:e("notSupportCopy"),type:"warning"});return}C(m)};Y(w,()=>{w.value&&y({message:e("copySuccess"),type:"success"})});const q=async m=>{u.value||!m||await m.validate(async a=>{a&&(u.value=!0,I(n).then(()=>{u.value=!1}).catch(()=>{u.value=!1}))})};return(m,a)=>{const d=F,p=N,A=$,h=Q,g=z,U=G,M=j,R=H,D=J;return V(),Z("div",te,[r("div",ae,[r("div",{class:"left",onClick:a[0]||(a[0]=i=>o(P).push({path:"/website/channel/wechat"}))},[le,r("span",re,s(o(e)("returnToPreviousPage")),1)]),se,r("span",ie,s(o(E)),1)]),ee((V(),oe(M,{model:n,"label-width":"150px",ref_key:"formRef",ref:v,rules:S,class:"page-form"},{default:l(()=>[t(h,{class:"box-card !border-none",shadow:"never"},{default:l(()=>[r("h3",ne,s(o(e)("wechatInfo")),1),t(p,{label:o(e)("wechatName"),prop:"wechat_name"},{default:l(()=>[t(d,{modelValue:n.wechat_name,"onUpdate:modelValue":a[1]||(a[1]=i=>n.wechat_name=i),placeholder:o(e)("wechatNamePlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:o(e)("wechatOriginal"),prop:"wechat_original"},{default:l(()=>[t(d,{modelValue:n.wechat_original,"onUpdate:modelValue":a[2]||(a[2]=i=>n.wechat_original=i),placeholder:o(e)("wechatOriginalPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),t(p,{label:o(e)("wechatQrcode"),prop:"qr_code"},{default:l(()=>[t(A,{modelValue:n.qr_code,"onUpdate:modelValue":a[3]||(a[3]=i=>n.qr_code=i)},null,8,["modelValue"]),r("div",pe,s(o(e)("wechatQrcodeTips")),1)]),_:1},8,["label"])]),_:1}),t(h,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:l(()=>[r("h3",de,s(o(e)("wechatDevelopInfo")),1),t(p,{label:o(e)("wechatAppid"),prop:"app_id"},{default:l(()=>[t(d,{modelValue:n.app_id,"onUpdate:modelValue":a[4]||(a[4]=i=>n.app_id=i),placeholder:o(e)("appidPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),r("div",ce,s(o(e)("wechatAppidTips")),1)]),_:1},8,["label"]),t(p,{label:o(e)("wechatAppsecret"),prop:"app_secret"},{default:l(()=>[t(d,{modelValue:n.app_secret,"onUpdate:modelValue":a[5]||(a[5]=i=>n.app_secret=i),placeholder:o(e)("appSecretPlaceholder"),class:"input-width",clearable:""},null,8,["modelValue","placeholder"]),r("div",me,s(o(e)("wechatAppsecretTips")),1)]),_:1},8,["label"])]),_:1}),t(h,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:l(()=>[r("h3",ue,s(o(e)("theServerSetting")),1),t(p,{label:"URL"},{default:l(()=>[t(d,{"model-value":c.serve_url,placeholder:"Please input",class:"input-width",readonly:!0},{append:l(()=>[r("div",{class:"cursor-pointer",onClick:a[6]||(a[6]=i=>_(c.serve_url))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1}),t(p,{label:"Token",prop:"token"},{default:l(()=>[t(d,{modelValue:n.token,"onUpdate:modelValue":a[7]||(a[7]=i=>n.token=i),placeholder:o(e)("tokenPlaceholder"),class:"input-width",maxlength:"32","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),r("div",_e,s(o(e)("tokenTips")),1)]),_:1}),t(p,{label:"EncodingAESKey",prop:"encoding_aes_key"},{default:l(()=>[t(d,{modelValue:n.encoding_aes_key,"onUpdate:modelValue":a[8]||(a[8]=i=>n.encoding_aes_key=i),placeholder:o(e)("encodingAesKeyPlaceholder"),class:"input-width",maxlength:"43","show-word-limit":"",clearable:""},null,8,["modelValue","placeholder"]),r("div",he,s(o(e)("encodingAESKeyTips")),1)]),_:1}),t(p,{label:o(e)("encryptionType"),prop:"encryption_type"},{default:l(()=>[t(U,{modelValue:n.encryption_type,"onUpdate:modelValue":a[9]||(a[9]=i=>n.encryption_type=i)},{default:l(()=>[t(g,{label:"not_encrypt"},{default:l(()=>[f(s(o(e)("cleartextMode")),1)]),_:1}),t(g,{label:"compatible"},{default:l(()=>[f(s(o(e)("compatibleMode")),1)]),_:1}),t(g,{label:"safe"},{default:l(()=>[f(s(o(e)("safeMode")),1)]),_:1})]),_:1},8,["modelValue"]),r("div",fe,s(o(e)("cleartextModeTips")),1),r("div",ge,s(o(e)("compatibleModeTips")),1),r("div",be,s(o(e)("safeModeTips")),1)]),_:1},8,["label"])]),_:1}),t(h,{class:"box-card !border-none mt-[16px]",shadow:"never"},{default:l(()=>[r("div",ve,[r("h3",we,s(o(e)("functionSetting")),1)]),t(p,{label:""},{default:l(()=>[r("div",ye,s(o(e)("functionSettingTips")),1)]),_:1}),t(p,{label:o(e)("businessDomain")},{default:l(()=>[t(d,{"model-value":c.business_domain,placeholder:"Please input",class:"input-width",readonly:!0},{append:l(()=>[r("div",{class:"cursor-pointer",onClick:a[10]||(a[10]=i=>_(c.business_domain))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"]),t(p,{label:o(e)("jsSecureDomain")},{default:l(()=>[t(d,{"model-value":c.js_secure_domain,placeholder:"Please input",class:"input-width",readonly:!0},{append:l(()=>[r("div",{class:"cursor-pointer",onClick:a[11]||(a[11]=i=>_(c.business_domain))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"]),t(p,{label:o(e)("webAuthDomain")},{default:l(()=>[t(d,{"model-value":c.web_auth_domain,placeholder:"Please input",class:"input-width",readonly:!0},{append:l(()=>[r("div",{class:"cursor-pointer",onClick:a[12]||(a[12]=i=>_(c.business_domain))},s(o(e)("copy")),1)]),_:1},8,["model-value"])]),_:1},8,["label"])]),_:1})]),_:1},8,["model","rules"])),[[D,u.value]]),r("div",xe,[r("div",Ve,[t(R,{type:"primary",loading:u.value,onClick:a[13]||(a[13]=i=>q(v.value))},{default:l(()=>[f(s(o(e)("save")),1)]),_:1},8,["loading"])])])])}}});export{Io as default};
