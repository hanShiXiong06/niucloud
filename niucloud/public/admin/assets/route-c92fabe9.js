/* empty css             *//* empty css                   */import{E as X}from"./el-overlay-380df695.js";import{_ as Y}from"./index-2de2d5e5.js";/* empty css                    *//* empty css                      *//* empty css                 *//* empty css               *//* empty css                  *//* empty css                  */import{S as Z}from"./index-1bbcede8.js";/* empty css                  *//* empty css                        *//* empty css                    */import"./el-tooltip-4ed993c7.js";/* empty css                */import{a as ee,E as te}from"./el-form-item-144bc604.js";/* empty css                  */import{t as r}from"./index-3862e13d.js";import{f as ae,l as le,m as oe,n as re}from"./diy-76da287d.js";import{a as ie,u as se}from"./vue-router-9f815af7.js";import{a3 as ne}from"./event-3e082a4a.js";import{a as $}from"./index-a6ffcea0.js";import{E as pe}from"./index-5f2625ed.js";import{E as me}from"./index-6f670765.js";import{E as de}from"./index-c5af06c2.js";import{a as ue,E as ce}from"./index-6191b0b4.js";import{E as fe}from"./index-cefc0b67.js";import{a as _e,E as ge}from"./index-6982a78b.js";import{v as he}from"./directive-d226d53f.js";import{d as ve,M as V,r as c,w as ye,c as be,b as x,e as we,q as o,p as i,f as _,x as n,u as t,v as g,L as Ve,m as C,C as z}from"./runtime-core.esm-bundler-c17ab5bc.js";import"./index-93efb1b5.js";import"./index-9ef6974e.js";import"./plugin-vue_export-helper-c018272e.js";import"./focus-trap-c0148071.js";import"./index-4b62c73a.js";import"./index-332680c2.js";/* empty css                        */import"./index.vue_vue_type_style_index_0_lang-027a5d0f.js";import"./attachment-92033b47.js";/* empty css                 *//* empty css                 *//* empty css               *//* empty css                    *//* empty css                   */import"./index-a62384b2.js";import"./index-138bfa06.js";import"./common-a45d855f.js";import"./index-b74135ff.js";import"./aria-adfa05c5.js";import"./validator-f5e079db.js";import"./index-72bf6cb5.js";import"./index-4ea26b0e.js";import"./index-d6b4c236.js";import"./index-6701860e.js";import"./index-f6eed9e8.js";import"./debounce-196ce6a6.js";import"./position-0d02b322.js";import"./index-d64b2f0e.js";import"./index-784d7581.js";import"./isEqual-ca98cf0c.js";import"./index-b7b91fcb.js";import"./index-5c20ff8f.js";import"./strings-e72e60f7.js";import"./index-bfecf17f.js";import"./_plugin-vue_export-helper-c27b6911.js";import"./index-0d36ccbf.js";import"./el-main-03d12282.js";import"./index-e42600b8.js";import"./_baseClone-37ba2e68.js";import"./_isIterateeCall-797defa1.js";const xe={class:"main-container"},ke={class:"flex justify-between items-center"},Ce={class:"text-[20px]"},Ee={class:"mr-[10px]"},De={class:"mr-[10px]"},Se={class:"mt-[16px] flex justify-end"},Re={class:"dialog-footer"},Wt=ve({__name:"route",setup(Pe){const B=V({});ie();const I=se().meta.title;c(),c(!1);let s=V({page:1,limit:10,total:0,loading:!0,data:[],searchParam:{title:""}});const k=c("");(async()=>{k.value=(await Z()).data.wap_url})();const h=(a=1)=>{s.loading=!0,s.page=a,le({page:s.page,limit:s.limit,...s.searchParam}).then(e=>{s.loading=!1;let d=Math.ceil(e.data.length/s.limit),f=JSON.parse(JSON.stringify(e.data)),u=[];for(var y=0;y<d;y++)u[y]=f.splice(0,s.limit);s.data=u[s.page-1],s.total=e.data.length}).catch(()=>{s.loading=!1})};h(),ae({}).then(a=>{for(let e in a.data)B[e]=a.data[e]});const E=c(),{copy:J,isSupported:L,copied:D}=ne(),S=a=>{L.value||$({message:r("notSupportCopy"),type:"warning"}),J(a)};ye(D,()=>{D.value&&$({message:r("copySuccess"),type:"success"})});const p=c("wechat"),R=c(""),P=c(0),v=V({title:"",name:"",page:"",is_share:0,sort:0}),m=V({wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}}),b=c(!1),O=be(()=>({})),U=c(),j=async a=>{let e=(await oe({name:a.name})).data;e.title&&(a.id=e.id,a.title=e.title,a.name=e.name,a.page=e.page,a.is_share=e.is_share,a.sort=e.sort,a.share=e.share),v.title=a.title,v.name=a.name,v.page=a.page,v.is_share=a.is_share,v.sort=a.sort,P.value=a.id,R.value=a.title;let d=a.share?JSON.parse(a.share):{wechat:{title:"",desc:"",url:""},weapp:{title:"",url:""}};d&&(m.wechat=d.wechat,m.weapp=d.weapp),b.value=!0},M=async a=>{a&&await a.validate(async e=>{e&&re({id:P.value,share:JSON.stringify(m),...v}).then(()=>{h(),b.value=!1}).catch(()=>{})})},q=a=>{a&&(a.resetFields(),h())};return(a,e)=>{const d=pe,f=ee,u=me,y=te,F=de,w=ue,A=ce,G=fe,T=_e,H=ge,K=Y,Q=X,W=he;return x(),we("div",xe,[o(F,{class:"box-card !border-none",shadow:"never"},{default:i(()=>[_("div",ke,[_("span",Ce,n(t(I)),1)]),o(F,{class:"box-card !border-none my-[10px] table-search-wrap",shadow:"never"},{default:i(()=>[o(y,{inline:!0,model:t(s).searchParam,ref_key:"searchFormDiyRouteRef",ref:E},{default:i(()=>[o(f,{label:t(r)("title"),prop:"title"},{default:i(()=>[o(d,{modelValue:t(s).searchParam.title,"onUpdate:modelValue":e[0]||(e[0]=l=>t(s).searchParam.title=l),placeholder:t(r)("titlePlaceholder")},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),o(f,null,{default:i(()=>[o(u,{type:"primary",onClick:e[1]||(e[1]=l=>h())},{default:i(()=>[g(n(t(r)("search")),1)]),_:1}),o(u,{onClick:e[2]||(e[2]=l=>q(E.value))},{default:i(()=>[g(n(t(r)("reset")),1)]),_:1})]),_:1})]),_:1},8,["model"])]),_:1}),Ve((x(),C(A,{data:t(s).data,size:"large"},{empty:i(()=>[_("span",null,n(t(s).loading?"":t(r)("emptyData")),1)]),default:i(()=>[o(w,{prop:"title",label:t(r)("title"),"min-width":"70"},null,8,["label"]),o(w,{prop:"addon_title",label:t(r)("forAddon"),"min-width":"70"},null,8,["label"]),o(w,{prop:"page",label:t(r)("wapUrl"),"min-width":"170"},{default:i(({row:l})=>[_("span",Ee,n(k.value+l.page),1),o(u,{type:"primary",link:"",onClick:N=>S(k.value+l.page)},{default:i(()=>[g(n(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),o(w,{prop:"page",label:t(r)("weappUrl"),"min-width":"120"},{default:i(({row:l})=>[_("span",De,n(l.page),1),o(u,{type:"primary",link:"",onClick:N=>S(l.page)},{default:i(()=>[g(n(t(r)("copy")),1)]),_:2},1032,["onClick"])]),_:1},8,["label"]),o(w,{label:t(r)("share"),fixed:"right",align:"right","min-width":"80"},{default:i(({row:l})=>[l.is_share==1?(x(),C(u,{key:0,type:"primary",link:"",onClick:N=>j(l)},{default:i(()=>[g(n(t(r)("shareSet")),1)]),_:2},1032,["onClick"])):z("",!0)]),_:1},8,["label"])]),_:1},8,["data"])),[[W,t(s).loading]]),_("div",Se,[o(G,{"current-page":t(s).page,"onUpdate:currentPage":e[3]||(e[3]=l=>t(s).page=l),"page-size":t(s).limit,"onUpdate:pageSize":e[4]||(e[4]=l=>t(s).limit=l),layout:"total, sizes, prev, pager, next, jumper",total:t(s).total,onSizeChange:e[5]||(e[5]=l=>h()),onCurrentChange:h},null,8,["current-page","page-size","total"])])]),_:1}),o(Q,{modelValue:b.value,"onUpdate:modelValue":e[12]||(e[12]=l=>b.value=l),title:t(r)("shareSet"),width:"30%"},{footer:i(()=>[_("span",Re,[o(u,{onClick:e[10]||(e[10]=l=>b.value=!1)},{default:i(()=>[g(n(t(r)("cancel")),1)]),_:1}),o(u,{type:"primary",onClick:e[11]||(e[11]=l=>M(U.value))},{default:i(()=>[g(n(t(r)("confirm")),1)]),_:1})])]),default:i(()=>[o(H,{modelValue:p.value,"onUpdate:modelValue":e[6]||(e[6]=l=>p.value=l)},{default:i(()=>[o(T,{label:t(r)("wechat"),name:"wechat"},null,8,["label"]),o(T,{label:t(r)("weapp"),name:"weapp"},null,8,["label"])]),_:1},8,["modelValue"]),o(y,{model:m[p.value],"label-width":"90px",ref_key:"shareFormRef",ref:U,rules:t(O)},{default:i(()=>[o(f,{label:t(r)("sharePage")},{default:i(()=>[_("span",null,n(R.value),1)]),_:1},8,["label"]),o(f,{label:t(r)("shareTitle"),prop:"title"},{default:i(()=>[o(d,{modelValue:m[p.value].title,"onUpdate:modelValue":e[7]||(e[7]=l=>m[p.value].title=l),placeholder:t(r)("shareTitlePlaceholder"),clearable:"",maxlength:"30","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),p.value=="wechat"?(x(),C(f,{key:0,label:t(r)("shareDesc"),prop:"desc"},{default:i(()=>[o(d,{modelValue:m[p.value].desc,"onUpdate:modelValue":e[8]||(e[8]=l=>m[p.value].desc=l),placeholder:t(r)("shareDescPlaceholder"),type:"textarea",rows:"4",clearable:"",maxlength:"100","show-word-limit":""},null,8,["modelValue","placeholder"])]),_:1},8,["label"])):z("",!0),o(f,{label:t(r)("shareImageUrl"),prop:"url"},{default:i(()=>[o(K,{modelValue:m[p.value].url,"onUpdate:modelValue":e[9]||(e[9]=l=>m[p.value].url=l),limit:1},null,8,["modelValue"])]),_:1},8,["label"])]),_:1},8,["model","rules"])]),_:1},8,["modelValue","title"])])}}});export{Wt as default};
