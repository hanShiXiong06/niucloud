import{g as W,a4 as V,r as u,w as X,j as Y,m as w,n as Z,F as l,E as o,q as _,L as i,u as t,K as f,a1 as ee,D,T as $}from"./base-45eb5090.js";/* empty css                   */import{E as ae}from"./el-overlay-616d6124.js";import{_ as te}from"./index-a7c34c2b.js";/* empty css                    *//* empty css                        *//* empty css                    *//* empty css               */import"./el-tooltip-58212670.js";import{a0 as le}from"./index-ad71a852.js";/* empty css                *//* empty css                     *//* empty css                  *//* empty css                 */import{t as r}from"./index-3694a2b2.js";import{f as oe,m as re,n as se,o as ie}from"./diy-5f64d5fc.js";import{a as ne,u as pe}from"./vue-router-fcbaaf02.js";import{J as me}from"./event-4977bef7.js";import{a as B}from"./index-aef37b98.js";import{E as ue}from"./index-4ce9333e.js";import{a as de,E as ce}from"./index-2bfbe5a7.js";import{E as _e}from"./index-25c37860.js";import{E as fe}from"./index-fc3020f4.js";import{a as he,E as ve}from"./index-cbbcd330.js";import{a as ge,E as ye}from"./index-87ef16fc.js";import{v as be}from"./directive-9f485fe5.js";import"./index-cd1661d3.js";import"./focus-trap-318ae2e0.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-0436c5a5.js";import"./attachment-95048130.js";/* empty css                  *//* empty css                  *//* empty css                      *//* empty css                 *//* empty css                    */import"./index-47488199.js";import"./index-9670e877.js";import"./storage-4159d1ed.js";import"./index-0d830c44.js";import"./index-3be486d3.js";import"./index-a096e75b.js";import"./index-a2f15582.js";import"./index-719dad93.js";import"./index-c0090d79.js";import"./isEqual-f877b6c1.js";import"./_Uint8Array-e584e472.js";import"./flatten-0fc8b7eb.js";import"./index-e841b684.js";import"./index-cc9a73f0.js";import"./index-201145a4.js";import"./strings-2444fdb3.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./el-radio-2719e44c.js";import"./el-avatar-bc58ad46.js";import"./common-af78c857.js";import"./common-2cf17469.js";import"./_initCloneObject-983ff8c8.js";import"./_isIterateeCall-104f1f93.js";const Ve={class:"main-container"},we={class:"flex justify-between items-center"},ke={class:"text-[20px]"},De={class:"mr-[10px]"},Ee={class:"mr-[10px]"},xe={class:"dialog-footer"},$a=W({__name:"route",setup(Ce){const I=V({});ne();const L=pe().meta.title;u(),u(!1);let n=V({loading:!0,data:[],searchParam:{title:"",type:""}});const k=u("");(async()=>{k.value=(await le()).data.wap_url})();const y=()=>{n.loading=!0,re({...n.searchParam}).then(a=>{n.loading=!1,n.data=a.data}).catch(()=>{n.loading=!1})};y(),oe({}).then(a=>{for(let e in a.data)I[e]=a.data[e]});const E=u(),{copy:J,isSupported:j,copied:x}=me(),C=a=>{j.value||B({message:r("notSupportCopy"),type:"warning"}),J(a)};X(x,()=>{x.value&&B({message:r("copySuccess"),type:"success"})});const p=u("wechat"),R=u(""),F=u(0),h=V({title:"",name:"",page:"",is_share:0,sort:0}),m=V({wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}}),g=u(!1),O=Y(()=>({})),T=u(),q=async a=>{let e=(await se({name:a.name})).data;e.title&&(a.id=e.id,a.title=e.title,a.name=e.name,a.page=e.page,a.is_share=e.is_share,a.sort=e.sort,a.share=e.share),h.title=a.title,h.name=a.name,h.page=a.page,h.is_share=a.is_share,h.sort=a.sort,F.value=a.id,R.value=a.title;let d=a.share?JSON.parse(a.share):{wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}};d&&(m.wechat=d.wechat,m.weapp=d.weapp),g.value=!0},z=async a=>{a&&await a.validate(async e=>{e&&ie({id:F.value,share:JSON.stringify(m),...h}).then(()=>{y(),g.value=!1}).catch(()=>{})})},K=a=>{a&&(a.resetFields(),y())};return(a,e)=>{const d=ue,v=de,c=_e,P=ce,S=fe,b=he,M=ve,U=ge,A=ye,G=te,H=ae,Q=be;return w(),Z("div",Ve,[l(S,{class:"box-card !border-none",shadow:"never"},{default:o(()=>[_("div",we,[_("span",ke,i(t(L)),1)]),l(S,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:o(()=>[l(P,{inline:!0,model:t(n).searchParam,ref_key:"searchFormDiyRouteRef",ref:E},{default:o(()=>[l(v,{label:t(r)("title"),prop:"title"},{default:o(()=>[l(d,{modelValue:t(n).searchParam.title,"onUpdate:modelValue":e[0]||(e[0]=s=>t(n).searchParam.title=s),placeholder:t(r)("titlePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(v,null,{default:o(()=>[l(c,{type:"primary",onClick:e[1]||(e[1]=s=>y())},{default:o(()=>[f(i(t(r)("search")),1)]),_:1}),l(c,{onClick:e[2]||(e[2]=s=>K(E.value))},{default:o(()=>[f(i(t(r)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),ee((w(),D(M,{data:t(n).data,size:"large"},{empty:o(()=>[_("span",null,i(t(n).loading?"":t(r)("emptyData")),1)]),default:o(()=>[l(b,{prop:"title",label:t(r)("title"),"min-width":"70"},null,8,["label"]),l(b,{prop:"page",label:t(r)("wapUrl"),"min-width":"170"},{default:o(({row:s})=>[_("span",De,i(k.value+s.page),1),l(c,{type:"primary",link:"",onClick:N=>C(k.value+s.page)},{default:o(()=>[f(i(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),l(b,{prop:"page",label:t(r)("weappUrl"),"min-width":"120"},{default:o(({row:s})=>[_("span",Ee,i(s.page),1),l(c,{type:"primary",link:"",onClick:N=>C(s.page)},{default:o(()=>[f(i(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),l(b,{label:t(r)("share"),fixed:"right","min-width":"80"},{default:o(({row:s})=>[s.is_share==1?(w(),D(c,{key:0,type:"primary",link:"",onClick:N=>q(s)},{default:o(()=>[f(i(t(r)("shareSet")),1)]),_:2},1032,["onClick"])):$("",!0)]),_:1},8,["label"])]),_:1},8,["data"])),[[Q,t(n).loading]])]),_:1}),l(H,{modelValue:g.value,"onUpdate:modelValue":e[9]||(e[9]=s=>g.value=s),title:t(r)("shareSet"),width:"30%"},{footer:o(()=>[_("span",xe,[l(c,{onClick:e[7]||(e[7]=s=>g.value=!1)},{default:o(()=>[f(i(t(r)("cancel")),1)]),_:1}),l(c,{type:"primary",onClick:e[8]||(e[8]=s=>z(T.value))},{default:o(()=>[f(i(t(r)("confirm")),1)]),_:1})])]),default:o(()=>[l(A,{modelValue:p.value,"onUpdate:modelValue":e[3]||(e[3]=s=>p.value=s)},{default:o(()=>[l(U,{label:t(r)("wechat"),name:"wechat"},null,8,["label"]),l(U,{label:t(r)("weapp"),name:"weapp"},null,8,["label"])]),_:1},8,["modelValue"]),l(P,{model:m[p.value],"label-width":"90px",ref_key:"shareFormRef",ref:T,rules:t(O)},{default:o(()=>[l(v,{label:t(r)("sharePage")},{default:o(()=>[_("span",null,i(R.value),1)]),_:1},8,["label"]),l(v,{label:t(r)("shareTitle"),prop:"title"},{default:o(()=>[l(d,{modelValue:m[p.value].title,"onUpdate:modelValue":e[4]||(e[4]=s=>m[p.value].title=s),placeholder:t(r)("shareTitlePlaceholder"),clearable:"",maxlength:"30","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),p.value=="wechat"?(w(),D(v,{key:0,label:t(r)("shareDesc"),prop:"desc"},{default:o(()=>[l(d,{modelValue:m[p.value].desc,"onUpdate:modelValue":e[5]||(e[5]=s=>m[p.value].desc=s),placeholder:t(r)("shareDescPlaceholder"),type:"textarea",rows:"4",clearable:"",maxlength:"100","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])):$("",!0),l(v,{label:t(r)("shareImageUrl"),prop:"url"},{default:o(()=>[l(G,{modelValue:m[p.value].url,"onUpdate:modelValue":e[6]||(e[6]=s=>m[p.value].url=s),limit:1},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])]),_:1},8,["modelValue","title"])])}}});export{$a as default};