/* empty css             *//* empty css                   *//* empty css                  *//* empty css                *//* empty css                     *//* empty css                        *//* empty css                 */import{E as T,b as $}from"./el-radio-c9a1047c.js";import{_ as j}from"./index.vue_vue_type_script_setup_true_lang-0079e90b.js";import{_ as X}from"./index-82132406.js";/* empty css               *//* empty css                  *//* empty css                  */import{x as G}from"./index-aae906bf.js";/* empty css                   */import{u as M,t as o}from"./index-5f4ce139.js";import{c as z,e as H,f as J,h as K}from"./article-a302dda6.js";import{u as Q,a as W}from"./vue-router-b5675730.js";import{E as Y}from"./index-95693143.js";import{a as Z,E as ee}from"./index-624573cc.js";import{a as te,E as oe}from"./index-9fbce820.js";import{E as le}from"./index-24c7fcee.js";import{E as ae}from"./index-acd12562.js";import{E as re}from"./index-4862d1b3.js";import{v as ie}from"./directive-a07a10ed.js";import{d as me,r as b,w as se,M as ne,c as pe,b as c,e as P,q as l,p as i,L as de,m as U,u as r,F as ue,t as ce,v as f,x as _,f as R}from"./runtime-core.esm-bundler-7c3fd514.js";import"./error-492b6a5b.js";import"./plugin-vue_export-helper-edbdb6f8.js";import"./index-f02197a7.js";import"./event-9519ab40.js";import"./index-868cd458.js";import"./index-a3cf5375.js";import"./index.vue_vue_type_style_index_0_lang-a42d8a18.js";/* empty css                  */import"./el-overlay-f7f710bd.js";import"./focus-trap-bb1e8c7a.js";import"./index-7b0897f9.js";import"./attachment-51c3470b.js";/* empty css                 *//* empty css                      *//* empty css                    *//* empty css                    *//* empty css                   */import"./index-be5dc120.js";import"./index-2083be2e.js";import"./index-be5868d6.js";import"./index-cf47f151.js";import"./sys-aa893c6b.js";import"./common-465e36b3.js";import"./index-548a7823.js";import"./validator-62f68fe3.js";import"./index-c656f08b.js";import"./index-2f0b1bf3.js";import"./index-9bac81c5.js";import"./index-f97852b4.js";import"./el-switch-3d36d31d.js";import"./index-47617222.js";import"./index-381e0c1f.js";import"./index-470ade69.js";import"./isEqual-f40f939e.js";import"./_Uint8Array-de4f83bb.js";import"./flatten-b3585bb8.js";import"./index-800b62de.js";import"./index-4683bff4.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-avatar-7d17482e.js";import"./common-cc37bda4.js";import"./common-2cf17469.js";import"./_baseClone-cf40e5b2.js";import"./_initCloneObject-bc5ed9bb.js";import"./index-2804b007.js";import"./index-6ed8f3b9.js";const fe={class:"main-container"},_e={class:"fixed-footer-wrap"},ge={class:"fixed-footer"},Nt=me({__name:"edit",setup(be){const g=Q(),S=W(),d=parseInt(g.query.id||0),n=b(!1),u=b([]),C=G(),v=M();v.pageReturn=!0,se(g,(p,e)=>{v.pageReturn=!1});const h={id:"",category_id:"",title:"",intro:"",summary:"",image:"",author:"",content:"",visit:"",visit_virtual:"",is_show:1,sort:0},t=ne({...h});d&&(async(p=0)=>{if(n.value=!0,Object.assign(t,h),p){const e=await(await z(p)).data;Object.keys(t).forEach(m=>{e[m]!=null&&(t[m]=e[m])}),n.value=!1}else n.value=!1})(d),(async()=>{u.value=await(await H({})).data,!d&&u.value.length>0&&(t.category_id=u.value[0].category_id)})();const V=b(),F=pe(()=>({title:[{required:!0,message:o("titlePlaceholder"),trigger:"blur"}],category_id:[{required:!0,message:o("categoryIdPlaceholder"),trigger:"blur"}],content:[{required:!0,message:o("contentPlaceholder"),trigger:"blur"},{validator:(p,e,m)=>{!e.replace(/<[^<>]+>/g,"").replace(/&nbsp;/gi,"")&&e.indexOf("img")===-1?m(new Error(o("contentPlaceholder"))):m()},trigger:["blur","change"]}]})),I=async p=>{n.value||!p||await p.validate(async e=>{e&&(n.value=!0,(d?J:K)(t).then(w=>{n.value=!1,y()}).catch(()=>{n.value=!1}))})},y=()=>{C.removeTab(g.path),S.push({path:"/article/list"})};return(p,e)=>{const m=Y,s=Z,w=te,A=oe,D=X,L=j,E=T,k=$,q=le,B=ee,N=ae,x=re,O=ie;return c(),P("div",fe,[l(N,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[de((c(),U(B,{model:t,"label-width":"90px",ref_key:"formRef",ref:V,rules:r(F),class:"page-form"},{default:i(()=>[l(s,{label:r(o)("title"),prop:"title"},{default:i(()=>[l(m,{modelValue:t.title,"onUpdate:modelValue":e[0]||(e[0]=a=>t.title=a),clearable:"",placeholder:r(o)("titlePlaceholder"),class:"input-width",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("categoryName"),prop:"category_id"},{default:i(()=>[l(A,{modelValue:t.category_id,"onUpdate:modelValue":e[1]||(e[1]=a=>t.category_id=a),clearable:"",placeholder:r(o)("categoryIdPlaceholder"),class:"input-width"},{default:i(()=>[(c(!0),P(ue,null,ce(u.value,a=>(c(),U(w,{label:a.name,value:a.category_id},null,8,["label","value"]))),256))]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("intro"),prop:"intro"},{default:i(()=>[l(m,{modelValue:t.intro,"onUpdate:modelValue":e[2]||(e[2]=a=>t.intro=a),type:"textarea",rows:"4",clearable:"",placeholder:r(o)("introPlaceholder"),class:"input-width",maxlength:"50"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("summary"),prop:"summary"},{default:i(()=>[l(m,{modelValue:t.summary,"onUpdate:modelValue":e[3]||(e[3]=a=>t.summary=a),type:"textarea",rows:"4",clearable:"",placeholder:r(o)("summaryPlaceholder"),class:"input-width",maxlength:"50"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("image")},{default:i(()=>[l(D,{modelValue:t.image,"onUpdate:modelValue":e[4]||(e[4]=a=>t.image=a)},null,8,["modelValue"])]),_:1},8,["label"]),l(s,{label:r(o)("author"),prop:"author"},{default:i(()=>[l(m,{modelValue:t.author,"onUpdate:modelValue":e[5]||(e[5]=a=>t.author=a),clearable:"",placeholder:r(o)("authorPlaceholder"),class:"input-width",maxlength:"20"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("content"),prop:"content"},{default:i(()=>[l(L,{modelValue:t.content,"onUpdate:modelValue":e[6]||(e[6]=a=>t.content=a)},null,8,["modelValue"])]),_:1},8,["label"]),l(s,{label:r(o)("visitVirtual")},{default:i(()=>[l(m,{modelValue:t.visit_virtual,"onUpdate:modelValue":e[7]||(e[7]=a=>t.visit_virtual=a),clearable:"",placeholder:r(o)("visitVirtualPlaceholder"),class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("isShow")},{default:i(()=>[l(k,{modelValue:t.is_show,"onUpdate:modelValue":e[8]||(e[8]=a=>t.is_show=a),placeholder:r(o)("isShowPlaceholder")},{default:i(()=>[l(E,{label:1},{default:i(()=>[f(_(r(o)("show")),1)]),_:1}),l(E,{label:0},{default:i(()=>[f(_(r(o)("hidden")),1)]),_:1})]),_:1},8,["modelValue","placeholder"])]),_:1},8,["label"]),l(s,{label:r(o)("sort"),prop:"sort"},{default:i(()=>[l(q,{modelValue:t.sort,"onUpdate:modelValue":e[9]||(e[9]=a=>t.sort=a),min:0},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])),[[O,n.value]])]),_:1}),R("div",_e,[R("div",ge,[l(x,{type:"primary",onClick:e[10]||(e[10]=a=>I(V.value))},{default:i(()=>[f(_(r(o)("save")),1)]),_:1}),l(x,{onClick:e[11]||(e[11]=a=>y())},{default:i(()=>[f(_(r(o)("cancel")),1)]),_:1})])])])}}});export{Nt as default};
