import"./base-0e92f4db.js";/* empty css                   */import{E as X}from"./el-overlay-3eff2fc5.js";import{_ as Y}from"./index-48a4e5ef.js";/* empty css                    *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import{S as Z}from"./index-fac59425.js";/* empty css                  *//* empty css                        *//* empty css                    */import"./el-tooltip-4ed993c7.js";/* empty css                */import{a as ee,E as te}from"./el-form-item-c2dd2ffe.js";/* empty css                  */import{t as r}from"./index-2d1c7ba3.js";import{f as ae,l as le,m as oe,n as re}from"./diy-e72c5c6e.js";import{a as ie,u as se}from"./vue-router-8b032575.js";import{a3 as ne}from"./event-a537c4cb.js";import{a as $}from"./index-e9d9b1a1.js";import{E as pe}from"./index-8cefa3ab.js";import{E as me}from"./index-e09a20f5.js";import{E as de}from"./index-2668a8ea.js";import{a as ue,E as ce}from"./index-395859da.js";import{E as fe}from"./index-95382bd9.js";import{a as _e,E as ge}from"./index-8ef7dff7.js";import{v as he}from"./directive-c6f70b8e.js";import{d as ve,M as V,r as c,w as ye,c as be,b as x,e as we,q as o,p as i,f as _,x as n,u as t,v as g,L as Ve,m as C,C as z}from"./runtime-core.esm-bundler-67034826.js";import"./index-defed8ff.js";import"./index-72686045.js";import"./focus-trap-83769a43.js";import"./index-6cae7119.js";import"./index-d87ae4a2.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-17d8e4dc.js";import"./attachment-f90dcf10.js";/* empty css                 *//* empty css                 *//* empty css               *//* empty css                    *//* empty css                   */import"./index-8c8d61e8.js";import"./index-ef31373f.js";import"./common-46715e7e.js";import"./index-a31d0a55.js";import"./aria-adfa05c5.js";import"./validator-9409f909.js";import"./index-de22cd40.js";import"./index-434046cb.js";import"./index-d23c70b3.js";import"./index-2b1dc445.js";import"./index-c7745eb3.js";import"./debounce-f6ba9d12.js";import"./position-c2e84b2a.js";import"./index-0caa5b89.js";import"./index-fd563016.js";import"./isEqual-97c7f2d5.js";import"./index-757074f4.js";import"./index-66750d66.js";import"./strings-1130dd70.js";import"./index-c6aa1547.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-81f2aa1e.js";import"./el-main-7a89c415.js";import"./index-ebd2990f.js";import"./_isIterateeCall-7d0e706f.js";const xe={class:"main-container"},ke={class:"flex justify-between items-center"},Ce={class:"text-[20px]"},Ee={class:"mr-[10px]"},De={class:"mr-[10px]"},Se={class:"mt-[16px] flex justify-end"},Re={class:"dialog-footer"},Kt=ve({__name:"route",setup(Pe){const B=V({});ie();const I=se().meta.title;c(),c(!1);let s=V({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{title:""}});const k=c("");(async()=>{k.value=(await Z()).data.wap_url})();const h=(a=1)=>{s.loading=!0,s.page=a,le({page:s.page,limit:s.limit,...s.searchParam}).then(e=>{s.loading=!1;let d=Math.ceil(e.data.length/s.limit),f=JSON.parse(JSON.stringify(e.data)),u=[];for(var y=0;y<d;y++)u[y]=f.splice(0,s.limit);s.data=u[s.page-1],s.total=e.data.length}).catch(()=>{s.loading=!1})};h(),ae({}).then(a=>{for(let e in a.data)B[e]=a.data[e]});const E=c(),{copy:J,isSupported:L,copied:D}=ne(),S=a=>{L.value||$({message:r("notSupportCopy"),type:"warning"}),J(a)};ye(D,()=>{D.value&&$({message:r("copySuccess"),type:"success"})});const p=c("wechat"),R=c(""),P=c(0),v=V({title:"",name:"",page:"",is_share:0,sort:0}),m=V({wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}}),b=c(!1),O=be(()=>({})),U=c(),j=async a=>{let e=(await oe({name:a.name})).data;e.title&&(a.id=e.id,a.title=e.title,a.name=e.name,a.page=e.page,a.is_share=e.is_share,a.sort=e.sort,a.share=e.share),v.title=a.title,v.name=a.name,v.page=a.page,v.is_share=a.is_share,v.sort=a.sort,P.value=a.id,R.value=a.title;let d=a.share?JSON.parse(a.share):{wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}};d&&(m.wechat=d.wechat,m.weapp=d.weapp),b.value=!0},M=async a=>{a&&await a.validate(async e=>{e&&re({id:P.value,share:JSON.stringify(m),...v}).then(()=>{h(),b.value=!1}).catch(()=>{})})},q=a=>{a&&(a.resetFields(),h())};return(a,e)=>{const d=pe,f=ee,u=me,y=te,F=de,w=ue,A=ce,G=fe,T=_e,H=ge,K=Y,Q=X,W=he;return x(),we("div",xe,[o(F,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[_("div",ke,[_("span",Ce,n(t(I)),1)]),o(F,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:i(()=>[o(y,{inline:!0,model:t(s).searchParam,ref_key:"searchFormDiyRouteRef",ref:E},{default:i(()=>[o(f,{label:t(r)("title"),prop:"title"},{default:i(()=>[o(d,{modelValue:t(s).searchParam.title,"onUpdate:modelValue":e[0]||(e[0]=l=>t(s).searchParam.title=l),placeholder:t(r)("titlePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(f,null,{default:i(()=>[o(u,{type:"primary",onClick:e[1]||(e[1]=l=>h())},{default:i(()=>[g(n(t(r)("search")),1)]),_:1}),o(u,{onClick:e[2]||(e[2]=l=>q(E.value))},{default:i(()=>[g(n(t(r)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),Ve((x(),C(A,{data:t(s).data,size:"large"},{empty:i(()=>[_("span",null,n(t(s).loading?"":t(r)("emptyData")),1)]),default:i(()=>[o(w,{prop:"title",label:t(r)("title"),"min-width":"70"},null,8,["label"]),o(w,{prop:"addon_title",label:t(r)("forAddon"),"min-width":"70"},null,8,["label"]),o(w,{prop:"page",label:t(r)("wapUrl"),"min-width":"170"},{default:i(({row:l})=>[_("span",Ee,n(k.value+l.page),1),o(u,{type:"primary",link:"",onClick:N=>S(k.value+l.page)},{default:i(()=>[g(n(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),o(w,{prop:"page",label:t(r)("weappUrl"),"min-width":"120"},{default:i(({row:l})=>[_("span",De,n(l.page),1),o(u,{type:"primary",link:"",onClick:N=>S(l.page)},{default:i(()=>[g(n(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),o(w,{label:t(r)("share"),fixed:"right",align:"right","min-width":"80"},{default:i(({row:l})=>[l.is_share==1?(x(),C(u,{key:0,type:"primary",link:"",onClick:N=>j(l)},{default:i(()=>[g(n(t(r)("shareSet")),1)]),_:2},1032,["onClick"])):z("",!0)]),_:1},8,["label"])]),_:1},8,["data"])),[[W,t(s).loading]]),_("div",Se,[o(G,{"current-page":t(s).page,"onUpdate:currentPage":e[3]||(e[3]=l=>t(s).page=l),"page-size":t(s).limit,"onUpdate:pageSize":e[4]||(e[4]=l=>t(s).limit=l),layout:"total, sizes, prev, pager, next, jumper",total:t(s).total,onSizeChange:e[5]||(e[5]=l=>h()),onCurrentChange:h},null,8,["current-page","page-size","total"])])]),_:1}),o(Q,{modelValue:b.value,"onUpdate:modelValue":e[12]||(e[12]=l=>b.value=l),title:t(r)("shareSet"),width:"30%"},{footer:i(()=>[_("span",Re,[o(u,{onClick:e[10]||(e[10]=l=>b.value=!1)},{default:i(()=>[g(n(t(r)("cancel")),1)]),_:1}),o(u,{type:"primary",onClick:e[11]||(e[11]=l=>M(U.value))},{default:i(()=>[g(n(t(r)("confirm")),1)]),_:1})])]),default:i(()=>[o(H,{modelValue:p.value,"onUpdate:modelValue":e[6]||(e[6]=l=>p.value=l)},{default:i(()=>[o(T,{label:t(r)("wechat"),name:"wechat"},null,8,["label"]),o(T,{label:t(r)("weapp"),name:"weapp"},null,8,["label"])]),_:1},8,["modelValue"]),o(y,{model:m[p.value],"label-width":"90px",ref_key:"shareFormRef",ref:U,rules:t(O)},{default:i(()=>[o(f,{label:t(r)("sharePage")},{default:i(()=>[_("span",null,n(R.value),1)]),_:1},8,["label"]),o(f,{label:t(r)("shareTitle"),prop:"title"},{default:i(()=>[o(d,{modelValue:m[p.value].title,"onUpdate:modelValue":e[7]||(e[7]=l=>m[p.value].title=l),placeholder:t(r)("shareTitlePlaceholder"),clearable:"",maxlength:"30","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),p.value=="wechat"?(x(),C(f,{key:0,label:t(r)("shareDesc"),prop:"desc"},{default:i(()=>[o(d,{modelValue:m[p.value].desc,"onUpdate:modelValue":e[8]||(e[8]=l=>m[p.value].desc=l),placeholder:t(r)("shareDescPlaceholder"),type:"textarea",rows:"4",clearable:"",maxlength:"100","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])):z("",!0),o(f,{label:t(r)("shareImageUrl"),prop:"url"},{default:i(()=>[o(K,{modelValue:m[p.value].url,"onUpdate:modelValue":e[9]||(e[9]=l=>m[p.value].url=l),limit:1},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])]),_:1},8,["modelValue","title"])])}}});export{Kt as default};
