import{d as X,r as R,c as W,e as t,f as i,g as o,y as l,x as e,B as _,u as a,i as J,aP as $e,aQ as Ee,v as w,A as q,R as de,V as me,o as Se,w as ne,z as F,F as g,Q as Ve,n as O,H as V,Z as Te}from"./base-06478700.js";import{E as _e,a as Le,c as Re,d as Ae}from"./el-main-9a0960e7.js";import{u as ee,_ as te,b as fe,c as xe,a as he,k as ve,E as De,h as Ie,s as Pe,aw as ze,e as Ue,g as Fe,d as je,B as Be,C as ue}from"./index-0d58768e.js";import{E as ye}from"./el-overlay-42a687c6.js";/* empty css                  *//* empty css               */import{_ as He,a as qe,b as We}from"./el-sub-menu-981613bb.js";import{i as Ke,a5 as Ne,h as Oe}from"./event-10eba222.js";/* empty css                        *//* empty css                 *//* empty css                  *//* empty css                 */import{s as se,a as N,d as Qe}from"./common-92a35870.js";import{t as y,a as Ge,u as ge}from"./index-e5b4f072.js";import{E as Ze,b as Je}from"./index-6290cf08.js";import{E as Xe}from"./index-f84999b2.js";import{E as Ye}from"./index-b12abbd4.js";import{b as Me,a as et,E as tt}from"./index-d50445bb.js";import{E as re}from"./index-e4abfaa5.js";import{R as ot,aB as lt}from"./index-2fcd1254.js";import{_ as ke}from"./_plugin-vue_export-helper-c27b6911.js";import"./el-tooltip-58212670.js";import{u as ie,a as we}from"./vue-router-d09a2c28.js";import{a as at,E as st}from"./el-form-item-314d006d.js";import{E as nt}from"./index-b68e8463.js";import{E as be}from"./index-c2f001d3.js";import{E as rt,a as it}from"./index-c17093ae.js";import"./index-adb89d14.js";import"./index-6b67c4ac.js";import"./index-b52d0f2a.js";import"./index-f27d6ce0.js";import"./focus-trap-3e826cdc.js";import"./index-2a269c7c.js";import"./index-9ee9102c.js";import"./index-9fe5de95.js";import"./index-818c0ce2.js";import"./validator-6e9db238.js";import"./position-c3bcd0be.js";import"./index-5a0d60aa.js";import"./debounce-1db848fd.js";const ce=K=>($e("data-v-a34ca4d9"),K=K(),Ee(),K),ct={class:"h-[100%] w-[100%] flex items-center justify-center px-[8px]"},pt={class:"setting-item flex items-baseline justify-between mb-[10px]"},dt={class:"title text-base text-tx-secondary whitespace-nowrap"},ut={class:""},mt=ce(()=>o("img",{class:"w-[35px] h-[35px]",src:He,alt:""},null,-1)),_t=ce(()=>o("img",{class:"w-[35px] h-[35px]",src:qe,alt:""},null,-1)),ft=ce(()=>o("img",{class:"w-[35px] h-[35px]",src:We,alt:""},null,-1)),xt={class:"setting-item flex items-center justify-between mb-[10px]"},ht={class:"title text-base text-tx-secondary"},vt={class:""},yt={class:"setting-item flex items-center justify-between mb-[10px]"},gt={class:"title text-base text-tx-secondary"},kt={class:""},wt=X({__name:"layout-setting",setup(K){const C=R(!1),u=ee(),m=Ke(),T=ot(m),s=W({get(){return u.dark},set(d){u.setTheme("dark",d),T(d),se(u.theme,u.dark?"dark":"light")}});W({get(){return u.sidebar},set(d){u.setTheme("sidebar",d),se(u.theme,u.dark?"dark":"light")}});const x=W({get(){return u.sidebarStyle},set(d){u.setTheme("sidebarStyle",d)}}),L=W({get(){return u.theme},set(d){u.setTheme("theme",d),se(u.theme,u.dark?"dark":"light")}});return(d,c)=>{const b=te,U=Ze,B=Je,I=Xe,P=Ye,H=re,$=Me;return t(),i("div",{class:"flex w-[100%] h-[100%]",onClick:c[4]||(c[4]=v=>C.value=!0)},[o("div",ct,[l(b,{name:"element-Setting"})]),l($,{modelValue:C.value,"onUpdate:modelValue":c[3]||(c[3]=v=>C.value=v),title:a(y)("layout.layoutSetting"),size:"300px"},{default:e(()=>[l(H,null,{default:e(()=>[o("div",pt,[o("div",dt,_(a(y)("layout.sidebarStyle")),1),o("div",ut,[l(B,{modelValue:a(x),"onUpdate:modelValue":c[0]||(c[0]=v=>J(x)?x.value=v:null),class:"ml-4"},{default:e(()=>[l(U,{label:"oneType",size:"large"},{default:e(()=>[mt]),_:1}),l(U,{label:"twoType",size:"large"},{default:e(()=>[_t]),_:1}),l(U,{label:"threeType",size:"large"},{default:e(()=>[ft]),_:1})]),_:1},8,["modelValue"])])]),o("div",xt,[o("div",ht,_(a(y)("layout.darkMode")),1),o("div",vt,[l(I,{modelValue:a(s),"onUpdate:modelValue":c[1]||(c[1]=v=>J(s)?s.value=v:null),"active-value":!0,"inactive-value":!1},null,8,["modelValue"])])]),o("div",yt,[o("div",gt,_(a(y)("layout.themeColor")),1),o("div",kt,[l(P,{modelValue:a(L),"onUpdate:modelValue":c[2]||(c[2]=v=>J(L)?L.value=v:null)},null,8,["modelValue"])])])]),_:1})]),_:1},8,["modelValue","title"])])}}});const bt=ke(wt,[["__scopeId","data-v-a34ca4d9"]]),Ct={class:"h-[100%] w-[100%] flex items-center justify-center px-[8px]"},$t=X({__name:"switch-lang",setup(K){const C=ie(),u=ee(),m=T=>{u.$patch(s=>{s.lang=T,N.set({key:"lang",data:T})}),Ge.loadLocaleMessages(C.meta.app||"",C.path,u.lang),location.reload()};return(T,s)=>{const x=te,L=fe,d=xe,c=he;return t(),w(c,{onCommand:m,tabindex:1,class:"h-[100%] w-[100%]"},{dropdown:e(()=>[l(d,null,{default:e(()=>[l(L,{command:"zh-cn",disabled:a(u).lang=="zh-cn"},{default:e(()=>[q("简体中文")]),_:1},8,["disabled"]),l(L,{command:"en",disabled:a(u).lang=="en"},{default:e(()=>[q("English")]),_:1},8,["disabled"])]),_:1})]),default:e(()=>[o("div",Ct,[l(x,{name:"iconfont-iconfanyi"})])]),_:1})}}}),Et={class:"userinfo flex h-full items-center"},St={class:"user-name pl-[8px]"},Vt={class:"form-tip"},Tt={class:"dialog-footer"},Lt=X({__name:"user-info",setup(K){const C=ve(),u=d=>{switch(d){case"logout":C.logout();break}};let m=R(!1);const T=R();let s=de({original_password:"",password:"",password_copy:""});const x=de({original_password:[{required:!0,message:y("originalPasswordPlaceholder"),trigger:"blur"}],password:[{required:!0,message:y("passwordPlaceholder"),trigger:"blur"}],password_copy:[{required:!0,message:y("passwordPlaceholder"),trigger:"blur"}]}),L=d=>{d&&d.validate(c=>{if(c){let b="";if(s.password&&!s.original_password&&(b=y("originalPasswordHint")),s.password&&s.original_password&&!s.password_copy&&(b=y("newPasswordHint")),s.password&&s.original_password&&s.password_copy&&s.password!=s.password_copy&&(b=y("doubleCipherHint")),b){Ie({type:"error",message:b});return}Pe(s).then(U=>{m.value=!1}).catch(U=>{m.value=!1})}else return!1})};return(d,c)=>{const b=De,U=te,B=me("router-link"),I=fe,P=xe,H=he,$=nt,v=at,E=st,Q=be,G=ye;return t(),i("div",null,[l(H,{onCommand:u,tabindex:1},{dropdown:e(()=>[l(P,null,{default:e(()=>[l(I,{command:"usercenter"},{default:e(()=>[l(B,{to:"/user/center"},{default:e(()=>[q("账号设置")]),_:1})]),_:1}),l(I,{command:"usercenter",onClick:c[0]||(c[0]=A=>J(m)?m.value=!0:m=!0)},{default:e(()=>[q("修改密码")]),_:1}),l(I,{command:"logout"},{default:e(()=>[q("退出登录")]),_:1})]),_:1})]),default:e(()=>[o("div",Et,[l(b,{size:25,icon:a(lt)},null,8,["icon"]),o("div",St,_(a(C).userInfo.username),1),l(U,{name:"element-ArrowDown",class:"ml-[5px]"})])]),_:1}),l(G,{modelValue:a(m),"onUpdate:modelValue":c[6]||(c[6]=A=>J(m)?m.value=A:m=A),title:"修改密码",width:"450px","before-close":d.handleClose},{footer:e(()=>[o("span",Tt,[l(Q,{onClick:c[4]||(c[4]=A=>J(m)?m.value=!1:m=!1)},{default:e(()=>[q(_(a(y)("cancel")),1)]),_:1}),l(Q,{type:"primary",onClick:c[5]||(c[5]=A=>L(T.value))},{default:e(()=>[q(_(a(y)("save")),1)]),_:1})])]),default:e(()=>[o("div",null,[l(E,{model:a(s),"label-width":"90px",ref_key:"formRef",ref:T,rules:x,class:"page-form"},{default:e(()=>[l(v,{label:a(y)("originalPassword"),prop:"original_password"},{default:e(()=>[l($,{modelValue:a(s).original_password,"onUpdate:modelValue":c[1]||(c[1]=A=>a(s).original_password=A),type:"password",placeholder:a(y)("originalPasswordPlaceholder"),clearable:"",class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"]),l(v,{label:a(y)("newPassword"),prop:"password"},{default:e(()=>[l($,{modelValue:a(s).password,"onUpdate:modelValue":c[2]||(c[2]=A=>a(s).password=A),type:"password",placeholder:a(y)("passwordPlaceholder"),clearable:"",class:"input-width"},null,8,["modelValue","placeholder"]),o("div",Vt,_(a(y)("passwordTip")),1)]),_:1},8,["label"]),l(v,{label:a(y)("passwordCopy"),prop:"password_copy"},{default:e(()=>[l($,{modelValue:a(s).password_copy,"onUpdate:modelValue":c[3]||(c[3]=A=>a(s).password_copy=A),type:"password",placeholder:a(y)("passwordPlaceholder"),clearable:"",class:"input-width"},null,8,["modelValue","placeholder"])]),_:1},8,["label"])]),_:1},8,["model","rules"])])]),_:1},8,["modelValue","before-close"])])}}}),Rt={class:"left-panel h-full flex items-center"},At={class:"flex items-center h-full pl-[10px] hidden-xs-only"},Dt={class:"right-panel h-full flex items-center justify-end"},It=["title"],Pt={class:"navbar-item !px-[0] flex items-center h-full cursor-pointer"},zt={class:"navbar-item !px-[0] flex items-center h-full cursor-pointer"},Ut={class:"navbar-item flex items-center h-full cursor-pointer"},Ft={class:"dialog-footer"},jt={class:"flex flex-wrap"},Bt=["onClick"],Ht=["onClick"],qt={class:"dialog-footer"},Wt=X({__name:"index",setup(K){const C=we();N.get("app_type");const{toggle:u,isFullscreen:m}=Ne(),T=ee(),s=ge(),x=ie(),L=R(window.innerWidth||document.documentElement.clientWidth||document.body.clientWidth),d=W(()=>T.dark);Se(()=>{window.onresize=()=>(()=>{L.value=window.innerWidth||document.documentElement.clientWidth||document.body.clientWidth})()}),ne(L,()=>{L.value<992?T.menuIsCollapse||T.toggleMenuCollapse(!0):T.menuIsCollapse&&T.toggleMenuCollapse(!1)});const c=()=>{C.push({path:"/app_manage"})},b=()=>{s.routeRefreshTag&&s.refreshRouterView()},U=W(()=>{const $=x.matched.filter(v=>v.meta.title);return $[0]&&$[0].path=="/"&&$.splice(0,1),$}),B=R(),I=R(!1),P=R(""),H=()=>{ze({view_path:P.value}).then(()=>{I.value=!1,C.go(0)})};return($,v)=>{const E=te,Q=et,G=tt,A=rt,n=it,r=be,z=ye,S=_e;return t(),w(S,{class:O(["h-full px-[10px]",{"layout-header border-b border-color":!a(d)}])},{default:e(()=>[l(n,{class:"w-100 h-full w-full"},{default:e(()=>[l(A,{span:12},{default:e(()=>[o("div",Rt,[o("div",{class:"navbar-item flex items-center h-full cursor-pointer",onClick:b},[l(E,{name:"element-Refresh"})]),o("div",At,[l(G,{separator:"/"},{default:e(()=>[(t(!0),i(g,null,F(a(U),(f,j)=>(t(),w(Q,{key:j},{default:e(()=>[q(_(f.meta.title),1)]),_:2},1024))),128))]),_:1})])])]),_:1}),l(A,{span:12},{default:e(()=>[o("div",Dt,[o("i",{class:"iconfont iconlingdang-xianxing cursor-pointer px-[8px]",title:a(y)("newInfo")},null,8,It),o("div",{class:"navbar-item flex items-center h-full cursor-pointer",onClick:c},[l(E,{name:"iconfont-iconqiehuan",title:a(y)("changeApp"),class:"!text-xs"},null,8,["title"])]),o("div",Pt,[l($t)]),o("div",{class:"navbar-item flex items-center h-full cursor-pointer",onClick:v[0]||(v[0]=(...f)=>a(u)&&a(u)(...f))},[a(m)?(t(),w(E,{key:0,name:"iconfont-icontuichuquanping"})):(t(),w(E,{key:1,name:"iconfont-iconquanping"}))]),o("div",zt,[l(bt)]),o("div",Ut,[l(Lt)])])]),_:1})]),_:1}),Ve(o("input",{type:"hidden","onUpdate:modelValue":v[1]||(v[1]=f=>$.comparisonToken=f)},null,512),[[Oe,$.comparisonToken]]),l(z,{modelValue:$.detectionLoginDialog,"onUpdate:modelValue":v[2]||(v[2]=f=>$.detectionLoginDialog=f),title:a(y)("layout.detectionLoginTip"),width:"30%","close-on-click-modal":!1,"close-on-press-escape":!1,"show-close":!1},{footer:e(()=>[o("span",Ft,[l(r,{onClick:$.detectionLoginFn},{default:e(()=>[q(_(a(y)("layout.detectionLoginOperation")),1)]),_:1},8,["onClick"])])]),default:e(()=>[o("span",null,_(a(y)("layout.detectionLoginContent")),1)]),_:1},8,["modelValue","title"]),l(z,{modelValue:I.value,"onUpdate:modelValue":v[3]||(v[3]=f=>I.value=f),title:a(y)("indexTemplate"),width:"550px","destroy-on-close":!0},{footer:e(()=>[o("span",qt,[l(r,{type:"primary",onClick:H},{default:e(()=>[q(_(a(y)("confirm")),1)]),_:1})])]),default:e(()=>[o("div",jt,[P.value==""?(t(!0),i(g,{key:0},F(B.value,(f,j)=>(t(),i("div",{key:j},[o("div",{onClick:Y=>P.value=f.view_path,class:O(["index-item py-[5px] px-[10px] mr-[10px] rounded-[3px] cursor-pointer",f.is_use==1?"bg-primary text-[#fff]":""])},[o("span",null,_(f.name),1)],10,Bt)]))),128)):(t(!0),i(g,{key:1},F(B.value,(f,j)=>(t(),i("div",{key:j},[o("div",{onClick:Y=>P.value=f.view_path,class:O(["index-item py-[5px] px-[10px] mr-[10px] rounded-[3px] cursor-pointer",P.value==f.view_path?"bg-primary text-[#fff]":""])},[o("span",null,_(f.name),1)],10,Ht)]))),128))])]),_:1},8,["modelValue","title"])]),_:1},8,["class"])}}});const Kt=ke(Wt,[["__scopeId","data-v-9e49551b"]]),Nt={class:"w-[124px] overflow-hidden"},Ot={class:"h-full flex flex-col relative"},Qt=o("span",{class:"iconfont iconyun1 !text-[32px] !w-auto text-[#fff]"},null,-1),Gt=[Qt],Zt=["src"],Jt={key:1,class:"flex items-center justify-center w-[30px] h-[30px]"},Xt=["onClick"],Yt={class:"text-[14px] ml-[8px]"},Mt=["onClick"],eo={class:"text-[14px] ml-[8px]"},to={key:0,class:"w-[155px] box-border border-r-[1px] border-solid second-menu"},oo={class:"text-[14px]"},lo={class:"text-[14px]"},ao={class:"text-[14px]"},so={class:"text-[14px]"},no={class:"text-[14px]"},ro={class:"text-[14px]"},io={class:"text-[14px]"},co=["onClick"],po={class:"text-[14px]"},uo={class:"text-[14px]"},mo={class:"text-[14px]"},_o={class:"text-[14px] !pl-[10px]"},fo={class:"text-[14px]"},xo={class:"text-[14px]"},ho={class:"text-[14px]"},vo=X({__name:"index",setup(K){const C=ve(),u=ee(),m=ie(),T=we(),s=R(""),x=R("");s.value=N.get("menuAppStorage"),x.value=N.get("menuAppStorage");const L=R(!1),d=R([]),c=R([]),b=R([]),U=async()=>{const n=await Be();d.value=d.value.concat(n.data),d.value.forEach((r,z)=>{r.type=="app"&&c.value.push(r.key),r.type=="addon"&&b.value.push(r.key)}),L.value=!0};U();const B=R({}),I=W(()=>{const n=[];return C.routers.forEach((r,z)=>{r.children&&r.children.length?(r.name=ue(r.children),B.value[r.meta.app]=ue(r.children),n.push(r)):(B.value[r.meta.app]=r.name,n.push(r))}),d.value&&d.value.length&&d.value.forEach((r,z)=>{n.forEach((S,f)=>{r.key==S.meta.key&&(S.meta.parentTitle=r.title,S.meta.parentIcon=r.icon)})}),!d.value.length&&!s.value&&(N.set({key:"menuAppStorage",data:""}),s.value=""),d.value.length&&!s.value&&(N.set({key:"menuAppStorage",data:c.value[0]}),s.value=c.value[0]),n.forEach((r,z)=>{s.value&&r.meta.app==s.value&&r.children.forEach((S,f)=>{if(n.push(S),S.children){let j=S.meta.key;S.children.forEach((Y,pe)=>{Y.parentKey=j})}})}),n});W(()=>u.dark),ne(()=>C.globalAppKey,(n,r)=>{U()},{deep:!0});const P=R(""),H=R("");ne(m,()=>{P.value=N.get("plugMenuTypeStorage");const n=m.matched[1];H.value=m.matched[1],m.meta.app&&m.meta.app==s.value?I.value.forEach((r,z)=>{r.children&&r.name!=m.name?r.children.forEach((S,f)=>{S.name==m.name&&(x.value=S.parentKey)}):r.name==m.name&&(x.value=r.name)}):x.value=n.meta.key,b.value.includes(x.value)&&P.value&&(x.value="app_center"),u.$patch(r=>{r.menuDrawer=!1})},{immediate:!0});let $=R(!0);const v=()=>{$.value=!0},E=(n,r)=>{if(r=="threefloatMenu"&&($.value=!1),!n.meta&&n.type=="app"||n.meta.key!="official_market"){let z=n.name;if(n.type=="app"){s.value=n.key,x.value=n.key,N.set({key:"menuAppStorage",data:n.key}),N.set({key:"plugMenuTypeStorage",data:""});const S=C.appMenuList;S.push(n.key),C.setAppMenuList(S),z=B.value[n.key]}else n.meta.app&&(z=Q(n));T.push({name:z})}else window.open("https://www.niucloud.com/product/","_blank")},Q=n=>n.children&&n.children.length?Q(n.children[0]):n.name,G=W(()=>u.sidebar),A=n=>b.value.includes(x.value)&&s.value==n.meta.app||!b.value.includes(x.value)&&(n.meta.key==x.value||n.meta.app==x.value)&&!n.meta.app||n.meta.app&&!b.value.includes(x.value)&&n.meta.key==x.value&&x.value.indexOf("index")==-1;return(n,r)=>{const z=te,S=Le,f=Ue,j=Fe,Y=je,pe=re;return L.value?(t(),i("div",{key:0,class:O(["flex",{"two-type":a(G)=="twoType"},{"three-type":a(G)=="threeType"}])},[o("div",Nt,[l(S,{class:O(["h-screen layout-aside w-[124px] pb-[30px] px-[8px] bg-[#282c34] ease-in duration-200"])},{default:e(()=>[o("div",Ot,[s.value?V("",!0):(t(),i("div",{key:0,class:"group flex items-center justify-center h-[64px] cursor-pointer",onMouseenter:v},Gt,32)),(t(!0),i(g,null,F(a(I),(h,M)=>(t(),i(g,{key:M},[s.value==h.meta.app&&h.meta.parentTitle?(t(),i(g,{key:0},[o("div",{class:"group flex items-center justify-center h-[64px] cursor-pointer",onMouseenter:v},[h.meta.parentIcon?(t(),i("img",{key:0,src:a(Qe)(h.meta.parentIcon),class:"w-[40px] h-[40px] rounded-full",alt:""},null,8,Zt)):(t(),i("div",Jt,[h.meta.icon?(t(),w(z,{key:0,name:h.meta.icon,class:"!w-auto",size:"24px"},null,8,["name"])):V("",!0)]))],32),(t(!0),i(g,null,F(h.children,(p,oe)=>(t(),i("div",{key:oe,onClick:k=>E(p),class:O(["rounded-sm flex items-center px-[8px] mb-[4px] h-[40px] cursor-pointer text-[#b9b9bf] hover:bg-[var(--el-color-primary)] hover:!text-[#fff] menu-item hover:text-color whitespace-nowrap",{"bg-[var(--el-color-primary)] !text-[#fff] menu-item-active ":x.value==p.meta.key}])},[p.meta.icon?(t(),w(z,{key:0,name:p.meta.icon,class:"!w-auto",size:"20px",title:p.meta.title},null,8,["name","title"])):V("",!0),o("span",Yt,_(p.meta.shortTitle),1)],10,Xt))),128))],64)):V("",!0)],64))),128)),(t(!0),i(g,null,F(a(I),(h,M)=>(t(),i(g,{key:M},[!h.meta.app&&(h.meta.attr=="common"||h.meta.attr=="system")?(t(),i("div",{key:0,onClick:p=>E(h),class:O(["rounded-sm flex items-center px-[8px] mb-[4px] h-[40px] cursor-pointer text-[#b9b9bf] hover:bg-[var(--el-color-primary)] hover:!text-[#fff] menu-item hover:text-color whitespace-nowrap",{"bg-[var(--el-color-primary)] !text-[#fff] menu-item-active ":h.path==H.value.path||H.value.path=="/admin"&&h.path=="/index"||H.value.meta.app&&h.path=="/index"}])},[h.meta.icon?(t(),w(z,{key:0,name:h.meta.icon,class:"!w-auto",size:"20px",title:h.meta.title},null,8,["name","title"])):V("",!0),o("span",eo,_(h.meta.shortTitle),1)],10,Mt)):V("",!0)],64))),128))])]),_:1})]),(t(!0),i(g,null,F(a(I),(h,M)=>(t(),i(g,{key:M},[A(h)?(t(),i("div",to,[o("div",{class:"group flex flex-col items-center justify-center h-[64px] border-b-[1px] border-solid second-head cursor-pointer relative",onMouseenter:r[0]||(r[0]=(...p)=>n.twofloatMenuHover&&n.twofloatMenuHover(...p))},_(h.meta.title),33),l(pe,{class:"overflow-y-auto menus-wrap"},{default:e(()=>[l(Y,{class:"apply-menu !border-0",router:!0,"unique-opened":"true","default-active":String(a(m).name)},{default:e(()=>[(t(!0),i(g,null,F(h.children,(p,oe)=>(t(),i(g,null,[p.children&&p.meta.show?(t(),w(j,{key:0,index:String(p.meta.title)},{title:e(()=>[o("span",oo,_(p.meta.title),1)]),default:e(()=>[(t(!0),i(g,null,F(p.children,(k,le)=>(t(),i(g,{key:le},[k.children&&k.meta.show?(t(),w(j,{key:0,index:String(k.meta.title),class:"three-menu"},{title:e(()=>[o("span",lo,_(k.meta.title),1)]),default:e(()=>[(t(!0),i(g,null,F(k.children,(D,ae)=>(t(),i(g,{key:ae},[D.children&&D.meta.show?(t(),w(j,{key:0,index:String(D.meta.title)},{title:e(()=>[o("span",ao,_(D.meta.title),1)]),default:e(()=>[(t(!0),i(g,null,F(D.children,(Z,Ce)=>(t(),i(g,{key:Ce},[Z.meta.show?(t(),w(f,{key:0,class:"!h-[52px] !pl-[55px]",index:String(Z.name),onClick:go=>E(Z)},{title:e(()=>[o("span",so,_(Z.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),128))]),_:2},1032,["index"])):D.meta.show?(t(),w(f,{key:1,class:"!h-[52px] !pl-[35px]",index:String(D.name),onClick:Z=>E(D)},{title:e(()=>[o("span",no,_(D.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),128))]),_:2},1032,["index"])):k.meta.show?(t(),w(f,{key:1,class:"!h-[52px] !pl-[42px]",index:String(k.name),onClick:D=>E(k)},{title:e(()=>[o("span",ro,_(k.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),128))]),_:2},1032,["index"])):p.meta.show&&p.meta.key!="official_market"?(t(),w(f,{key:1,class:"!pl-[25px] text-[#333]",index:String(p.name),onClick:k=>E(p)},{title:e(()=>[o("span",io,_(p.meta.title),1)]),_:2},1032,["index","onClick"])):p.meta.show&&p.meta.key=="official_market"?(t(),i("div",{key:2,class:"flex items-center !px-[25px] h-[56px] cursor-pointer text-[#333] el-menu-item",onClick:k=>E(p)},[o("span",po,_(p.meta.title),1)],8,co)):V("",!0)],64))),256)),h.children?V("",!0):(t(),w(f,{key:0,class:"!pl-[25px] text-[#333]",index:String(h.name),onClick:p=>E(h)},{title:e(()=>[o("span",uo,_(h.meta.title),1)]),_:2},1032,["index","onClick"])),P.value&&x.value=="app_center"?(t(!0),i(g,{key:1},F(a(I),(p,oe)=>(t(),i(g,null,[p.meta.app&&p.meta.app==P.value&&p.children?(t(),w(j,{key:0,index:String(p.meta.title)},{title:e(()=>[o("span",mo,_(p.meta.title),1)]),default:e(()=>[(t(!0),i(g,null,F(p.children,(k,le)=>(t(),i(g,{key:le},[k.children&&k.meta.show?(t(),w(j,{key:0,index:String(k.meta.title)},{title:e(()=>[o("span",_o,_(k.meta.title),1)]),default:e(()=>[(t(!0),i(g,null,F(k.children,(D,ae)=>(t(),i(g,{key:ae},[D.meta.show?(t(),w(f,{key:0,class:"!h-[52px] !pl-[55px]",index:String(D.name),onClick:Z=>E(D)},{title:e(()=>[o("span",fo,_(D.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),128))]),_:2},1032,["index"])):k.meta.show?(t(),w(f,{key:1,class:"!h-[52px] !pl-[35px]",index:String(k.name),onClick:D=>E(k)},{title:e(()=>[o("span",xo,_(k.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),128))]),_:2},1032,["index"])):p.meta.app&&p.meta.app==P.value?(t(),w(f,{key:1,class:"!pl-[25px] text-[#333]",index:String(p.name),onClick:k=>E(p)},{title:e(()=>[o("span",ho,_(p.meta.title),1)]),_:2},1032,["index","onClick"])):V("",!0)],64))),256)):V("",!0)]),_:2},1032,["default-active"])]),_:2},1024)])):V("",!0)],64))),128))],2)):V("",!0)}}});const yo={class:"common-layout min-w-[1200px]"},rl=X({__name:"index",setup(K){const C=ge(),u=ee(),m=W(()=>u.dark);return(T,s)=>{const x=Re,L=me("router-view"),d=re,c=Ae,b=_e;return t(),i("div",yo,[l(b,{class:"w-100 h-screen"},{default:e(()=>[l(vo),l(b,null,{default:e(()=>[l(x,null,{default:e(()=>[l(Kt)]),_:1}),l(c,{class:O(["main-wrap h-full p-0",{"bg-page":a(m)}])},{default:e(()=>[l(d,null,{default:e(()=>[o("div",null,[a(C).routeRefreshTag?(t(),w(L,{key:0},{default:e(({Component:U,route:B})=>[(t(),w(Te(U),{key:B.fullPath}))]),_:1})):V("",!0)])]),_:1})]),_:1},8,["class"])]),_:1})]),_:1})])}}});export{rl as default};
