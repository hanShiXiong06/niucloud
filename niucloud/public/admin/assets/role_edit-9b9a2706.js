/* empty css             *//* empty css                   *//* empty css                  *//* empty css                    *//* empty css                *//* empty css                    */import{a as Y,E as Z}from"./el-form-item-144bc604.js";/* empty css               *//* empty css                       *//* empty css                 *//* empty css                 */import{t as r}from"./index-6b4cbbe4.js";import{L as ee,M as ae,J as te,H as oe,I as le}from"./index-596de8a9.js";import{e as se}from"./common-a45d855f.js";import{u as re,a as ne}from"./vue-router-9f815af7.js";import{_ as de}from"./addon-role.vue_vue_type_script_setup_true_lang-008a8b23.js";import{a as U}from"./index-a6ffcea0.js";import{E as ie}from"./index-5f2625ed.js";import{E as me,a as ue}from"./index-d6b4c236.js";import{E as pe,b as ce}from"./index-6ff31475.js";import{E as _e}from"./index-784d7581.js";import{E as fe}from"./index-0ee88e31.js";import{a as he,E as ve}from"./index-6982a78b.js";import{E as ge}from"./index-6f670765.js";import{v as be}from"./directive-d226d53f.js";import{d as ye,M as ke,r as R,U as xe,o as Ee,A as Ve,ac as j,w as we,L as Ce,u as e,b as c,e as b,f as u,x as f,m as P,p as n,q as s,v as x,C as E,i as S,F as Re,t as Ne,at as De,au as Ie}from"./runtime-core.esm-bundler-c17ab5bc.js";import{_ as Le}from"./_plugin-vue_export-helper-c27b6911.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./event-3e082a4a.js";import"./_baseClone-37ba2e68.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./el-overlay-380df695.js";import"./index-93efb1b5.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                  */import"./index-138bfa06.js";import"./index-72bf6cb5.js";import"./isEqual-ca98cf0c.js";import"./index-0b4c4f48.js";import"./strings-e72e60f7.js";const T=y=>(De("data-v-1de626e0"),y=y(),Ie(),y),Ue={class:"main-container"},je={class:"detail-head"},Pe=T(()=>u("span",{class:"iconfont iconxiangzuojiantou !text-xs"},null,-1)),Se={class:"ml-[1px]"},Te=T(()=>u("span",{class:"adorn"},"|",-1)),Ae={class:"right"},Be={key:1,class:"px-[15px]"},Fe={class:"flex items-center justify-between"},Oe={key:2,class:"fixed-footer-wrap"},qe={class:"fixed-footer"},Me=ye({__name:"role_edit",setup(y){const V=re(),w=ne(),A=V.meta.title,h=ke({loading:!1,activeName:"system",checkAll:!1,formData:{role_name:"",status:1,addon:{},system:[]},menusData:[],addonList:[]});let N=R(null),B=R({role_name:[{required:!0,message:r("roleNamePlaceholder"),trigger:"blur"}],rules:[{validator:(d,a,o)=>{a.length?o():o(new Error(r("rulesPlaceholder")))},trigger:"blur"}]}),{loading:m,formData:t,activeName:k,checkAll:_,menusData:C,addonList:F}=xe(h);Ee(async()=>{m.value=!0;let d=await ee();d.data&&(h.menusData=d.data);let a=await ae();a.data&&(h.addonList=a.data.map(o=>(o.menu_name=o.title,o.menu_key=o.key,o))),V.query.role_id?O(V.query.role_id):m.value=!1});const O=d=>{te(d).then(a=>{h.formData=Object.assign(t.value,a.data),t.value.addon=Object.assign(t.value.addon,t.value.rules.addon),t.value.system=Object.assign(t.value.system,t.value.rules.system),m.value=!1,Ve(()=>{var o=[];t.value.system.forEach(i=>D(i,j(C.value),o)),v.value.setCheckedKeys(o)})}).catch(()=>{m.value=!1})},D=(d,a,o)=>{a.forEach(i=>{d==i.menu_key?(!i.children||i.children.length==0)&&o.push(d):i.children&&i.children.length>0&&D(d,i.children,o)})};let v=R(null);we(_,()=>{_.value?v.value.setCheckedNodes(j(C.value)):v.value.setCheckedNodes([])});const q=se(d=>{h.formData.system=v.value.getCheckedNodes(!1,!0).map(a=>a.menu_key)}),M=async d=>{const a=t.value.role_id?oe:le;await d.validate(async o=>{if(o){if(!t.value.system.length)return U({message:`${r("systemErr")}`}),!1;var i=Object.values(t.value.addon).filter(g=>{if(g.length)return!0});if(!i.length)return U({message:`${r("applicationErr")}`}),!1;const p=Object.assign({},t.value);p.rules={system:p.system,addon:p.addon},a(p).then(g=>{w.push({path:"/setting/auth/role"})}).catch(()=>{m.value=!1})}})};return(d,a)=>{const o=ie,i=Y,p=me,g=pe,$=ce,z=ue,G=Z,H=_e,J=fe,I=he,K=ve,L=ge,Q=be;return Ce((c(),b("div",Ue,[u("div",je,[u("div",{class:"left",onClick:a[0]||(a[0]=l=>e(w).push({path:"/setting/auth/role"}))},[Pe,u("span",Se,f(e(r)("returnToPreviousPage")),1)]),Te,u("span",Ae,f(e(A)),1)]),e(m)?E("",!0):(c(),P(G,{key:0,model:e(t),"label-width":"90px",ref_key:"formRef",ref:N,rules:e(B),class:"page-form mt-[30px]"},{default:n(()=>[s(z,null,{default:n(()=>[s(p,{span:24},{default:n(()=>[s(i,{label:e(r)("roleName"),prop:"role_name"},{default:n(()=>[s(o,{modelValue:e(t).role_name,"onUpdate:modelValue":a[1]||(a[1]=l=>e(t).role_name=l),placeholder:e(r)("roleNamePlaceholder"),clearable:"",disabled:e(t).uid,class:"input-width",maxlength:"10","show-word-limit":!0},null,8,["modelValue","placeholder","disabled"])]),_:1},8,["label"])]),_:1}),s(p,{span:24},{default:n(()=>[s(i,{label:e(r)("status")},{default:n(()=>[s($,{modelValue:e(t).status,"onUpdate:modelValue":a[2]||(a[2]=l=>e(t).status=l)},{default:n(()=>[s(g,{label:1},{default:n(()=>[x(f(e(r)("startUsing")),1)]),_:1}),s(g,{label:0},{default:n(()=>[x(f(e(r)("statusDeactivate")),1)]),_:1})]),_:1},8,["modelValue"])]),_:1},8,["label"])]),_:1})]),_:1})]),_:1},8,["model","rules"])),e(m)?E("",!0):(c(),b("div",Be,[s(K,{modelValue:e(k),"onUpdate:modelValue":a[4]||(a[4]=l=>S(k)?k.value=l:k=l),class:"demo-tabs"},{default:n(()=>[s(I,{label:e(r)("system"),name:"system"},{default:n(()=>[u("div",Fe,[u("div",null,[s(H,{modelValue:e(_),"onUpdate:modelValue":a[3]||(a[3]=l=>S(_)?_.value=l:_=l),label:e(r)("selectAll")},null,8,["modelValue","label"])])]),s(J,{data:e(C),props:{label:"menu_name"},"show-checkbox":"",onCheckChange:e(q),"expand-on-click-node":!1,"node-key":"menu_key",ref_key:"treeRef",ref:v},null,8,["data","onCheckChange"])]),_:1},8,["label"]),s(I,{label:e(r)("application"),name:"application"},{default:n(()=>[(c(!0),b(Re,null,Ne(e(F),(l,W)=>(c(),b("div",{key:W,class:"p-[15px] border-[1px] border-solid border-[#e4e7ed] mt-[15px]"},[l.children?(c(),P(de,{key:0,modelValue:e(t).addon[l.key],"onUpdate:modelValue":X=>e(t).addon[l.key]=X,data:l},null,8,["modelValue","onUpdate:modelValue","data"])):E("",!0)]))),128))]),_:1},8,["label"])]),_:1},8,["modelValue"])])),e(m)?E("",!0):(c(),b("div",Oe,[u("div",qe,[s(L,{type:"primary",onClick:a[5]||(a[5]=l=>M(e(N)))},{default:n(()=>[x(f(e(r)("save")),1)]),_:1}),s(L,{onClick:a[6]||(a[6]=l=>e(w).push({path:"/setting/auth/role"}))},{default:n(()=>[x(f(e(r)("cancel")),1)]),_:1})])]))])),[[Q,e(m)]])}}});const Pa=Le(Me,[["__scopeId","data-v-1de626e0"]]);export{Pa as default};
