import{d as J,r as I,c as q,e,f as l,g as t,y as o,x as s,B as p,u as d,i as ee,A as B,bl as be,bm as we,v as c,V as me,o as $e,w as oe,z as E,F as _,H as m,Q as Ce,n as Z,Z as Se}from"./base-d77b0726.js";import{E as _e,c as Ee,d as Te}from"./index-704f0685.js";/* empty css                     *//* empty css                   *//* empty css                  *//* empty css               */import{_ as Ve,a as ze,b as Le}from"./el-menu-item-7374f44c.js";import{_ as Y}from"./index.vue_vue_type_script_setup_true_lang-8d43c28e.js";import{h as Ie,G as je,g as De}from"./index-331c6de1.js";/* empty css                        *//* empty css                 *//* empty css                  *//* empty css                 */import{u as M,b as ue,d as Ae,f as de}from"./index-822bfdfd.js";import{s as ne,a as W,d as xe}from"./common-56ee0a80.js";import{t as U,a as Re,u as he}from"./index-c7fb4804.js";import{E as Ue,b as Be}from"./index-ee35aabd.js";import{E as Fe}from"./index-9b58fc9a.js";import{E as He}from"./index-b1914892.js";import{E as ie}from"./index-74352d71.js";import{b as qe,a as We,E as Ne,d as Ke,f as Ge,c as Oe}from"./index-008fac09.js";import{O as Pe,ax as Qe}from"./index-e37943c3.js";import{_ as fe}from"./_plugin-vue_export-helper-c27b6911.js";/* empty css                         *//* empty css                  */import{u as ce,b as ve}from"./vue-router-57155f94.js";import{a as ye,b as ke,E as ge}from"./index-6f5bf0a3.js";/* empty css                  */import{E as Ze}from"./index-3322df72.js";import{aa as Je,ab as Xe}from"./sys-953663dd.js";import{E as Ye,a as Me}from"./index-c314892b.js";import{E as et}from"./index-6a54cf26.js";import{E as tt}from"./index-91bdda63.js";import"./el-tooltip-4ed993c7.js";import{_ as st}from"./apply_empty-58e89635.js";/* empty css                */import"./index-9e51ba8b.js";import"./typescript-defaf979.js";import"./aria-60e0cdc6.js";import"./index-de9bede2.js";import"./event-e06a23af.js";import"./index-6245131d.js";import"./validator-7b087194.js";import"./index-f2dc9b9f.js";import"./index-45cca80f.js";import"./focus-trap-98fda164.js";import"./index-c1eb81db.js";import"./index-d1e433eb.js";import"./position-09adcf79.js";import"./index-a20d1a31.js";import"./index-ef0eb7b1.js";import"./debounce-8a1738b0.js";import"./index-b3418ddc.js";import"./scroll-59301fd6.js";import"./vnode-5920e7a9.js";import"./aria-adfa05c5.js";import"./index-40fcecbc.js";import"./dropdown-2ff49e9b.js";const re=F=>(be("data-v-4f12bef8"),F=F(),we(),F),lt={class:"h-[100%] w-[100%] flex items-center justify-center px-[8px]"},at={class:"setting-item flex items-baseline justify-between mb-[10px]"},nt={class:"title text-base text-tx-secondary whitespace-nowrap"},ot={class:""},it={class:"setting-item flex items-baseline justify-between mb-[10px]"},ct={class:"title text-base text-tx-secondary whitespace-nowrap"},rt={class:""},pt=re(()=>t("img",{class:"w-[35px] h-[35px]",src:Ve,alt:""},null,-1)),dt=re(()=>t("img",{class:"w-[35px] h-[35px]",src:ze,alt:""},null,-1)),mt=re(()=>t("img",{class:"w-[35px] h-[35px]",src:Le,alt:""},null,-1)),_t={class:"setting-item flex items-center justify-between mb-[10px]"},ut={class:"title text-base text-tx-secondary"},xt={class:""},ht={class:"setting-item flex items-center justify-between mb-[10px]"},ft={class:"title text-base text-tx-secondary"},vt={class:""},yt=J({__name:"layout-setting",setup(F){const $=I(!1),u=M(),C=Ie(),A=Pe(C),h=q({get(){return u.dark},set(y){u.setTheme("dark",y),A(y),ne(u.theme,u.dark?"dark":"light")}}),x=q({get(){return u.sidebar},set(y){u.setTheme("sidebar",y),ne(u.theme,u.dark?"dark":"light")}}),S=q({get(){return u.sidebarStyle},set(y){u.setTheme("sidebarStyle",y)}}),g=q({get(){return u.theme},set(y){u.setTheme("theme",y),ne(u.theme,u.dark?"dark":"light")}});return(y,k)=>{const N=Y,T=Ue,R=Be,K=Fe,H=He,G=ie,L=qe;return e(),l("div",{class:"flex w-[100%] h-[100%]",onClick:k[5]||(k[5]=i=>$.value=!0)},[t("div",lt,[o(N,{name:"element-Setting"})]),o(L,{modelValue:$.value,"onUpdate:modelValue":k[4]||(k[4]=i=>$.value=i),title:d(U)("layout.layoutSetting"),size:"300px"},{default:s(()=>[o(G,null,{default:s(()=>[t("div",at,[t("div",nt,p(d(U)("layout.sidebarStyle")),1),t("div",ot,[o(R,{modelValue:d(S),"onUpdate:modelValue":k[0]||(k[0]=i=>ee(S)?S.value=i:null),class:"ml-4"},{default:s(()=>[o(T,{label:"oneType",size:"large"},{default:s(()=>[B("样式一")]),_:1}),o(T,{label:"twoType",size:"large"},{default:s(()=>[B("样式二")]),_:1}),o(T,{label:"threeType",size:"large"},{default:s(()=>[B("样式三")]),_:1})]),_:1},8,["modelValue"])])]),t("div",it,[t("div",ct,p(d(U)("layout.sidebarMode")),1),t("div",rt,[o(R,{modelValue:d(x),"onUpdate:modelValue":k[1]||(k[1]=i=>ee(x)?x.value=i:null),class:"ml-4"},{default:s(()=>[o(T,{label:"oneType",size:"large"},{default:s(()=>[pt]),_:1}),o(T,{label:"twoType",size:"large"},{default:s(()=>[dt]),_:1}),o(T,{label:"threeType",size:"large"},{default:s(()=>[mt]),_:1})]),_:1},8,["modelValue"])])]),t("div",_t,[t("div",ut,p(d(U)("layout.darkMode")),1),t("div",xt,[o(K,{modelValue:d(h),"onUpdate:modelValue":k[2]||(k[2]=i=>ee(h)?h.value=i:null),"active-value":!0,"inactive-value":!1},null,8,["modelValue"])])]),t("div",ht,[t("div",ft,p(d(U)("layout.themeColor")),1),t("div",vt,[o(H,{modelValue:d(g),"onUpdate:modelValue":k[3]||(k[3]=i=>ee(g)?g.value=i:null)},null,8,["modelValue"])])])]),_:1})]),_:1},8,["modelValue","title"])])}}});const kt=fe(yt,[["__scopeId","data-v-4f12bef8"]]),gt={class:"h-[100%] w-[100%] flex items-center justify-center px-[8px]"},bt=J({__name:"switch-lang",setup(F){const $=ce(),u=M(),C=A=>{u.$patch(h=>{h.lang=A,W.set({key:"lang",data:A})}),Re.loadLocaleMessages($.meta.app||"",$.path,u.lang),location.reload()};return(A,h)=>{const x=Y,S=ye,g=ke,y=ge;return e(),c(y,{onCommand:C,tabindex:1,class:"h-[100%] w-[100%]"},{dropdown:s(()=>[o(g,null,{default:s(()=>[o(S,{command:"zh-cn",disabled:d(u).lang=="zh-cn"},{default:s(()=>[B("简体中文")]),_:1},8,["disabled"]),o(S,{command:"en",disabled:d(u).lang=="en"},{default:s(()=>[B("English")]),_:1},8,["disabled"])]),_:1})]),default:s(()=>[t("div",gt,[o(x,{name:"iconfont-iconfanyi"})])]),_:1})}}}),wt={class:"userinfo flex h-full items-center"},$t={class:"user-name pl-[8px]"},Ct=J({__name:"user-info",setup(F){const $=ue(),u=C=>{switch(C){case"logout":$.logout();break}};return(C,A)=>{const h=Ze,x=Y,S=me("router-link"),g=ye,y=ke,k=ge;return e(),c(k,{onCommand:u,tabindex:1},{dropdown:s(()=>[o(y,null,{default:s(()=>[o(g,{command:"usercenter"},{default:s(()=>[o(S,{to:"/user/center"},{default:s(()=>[B("个人中心")]),_:1})]),_:1}),o(g,{command:"logout"},{default:s(()=>[B("退出登录")]),_:1})]),_:1})]),default:s(()=>[t("div",wt,[o(h,{size:25,icon:d(Qe)},null,8,["icon"]),t("div",$t,p(d($).userInfo.username),1),o(x,{name:"element-ArrowDown",class:"ml-[5px]"})])]),_:1})}}}),St={class:"left-panel h-full flex items-center"},Et={class:"flex items-center h-full pl-[10px] hidden-xs-only"},Tt={class:"right-panel h-full flex items-center justify-end"},Vt=["title"],zt={class:"navbar-item !px-[0] flex items-center h-full cursor-pointer"},Lt={class:"navbar-item !px-[0] flex items-center h-full cursor-pointer"},It={class:"navbar-item flex items-center h-full cursor-pointer"},jt={class:"dialog-footer"},Dt={class:"flex flex-wrap"},At=["onClick"],Rt=["onClick"],Ut={class:"dialog-footer"},Bt=J({__name:"index",setup(F){const $=ve(),u=W.get("app_type"),{toggle:C,isFullscreen:A}=je(),h=M(),x=he(),S=ce(),g=I(window.innerWidth||document.documentElement.clientWidth||document.body.clientWidth),y=q(()=>h.dark);$e(()=>{window.onresize=()=>(()=>{g.value=window.innerWidth||document.documentElement.clientWidth||document.body.clientWidth})()}),oe(g,()=>{g.value<992?h.menuIsCollapse||h.toggleMenuCollapse(!0):h.menuIsCollapse&&h.toggleMenuCollapse(!1)});const k=()=>{x.routeRefreshTag&&x.refreshRouterView()},N=q(()=>{const L=S.matched.filter(i=>i.meta.title);return L[0]&&L[0].path=="/"&&L.splice(0,1),L}),T=I(),R=I(!1),K=()=>{Je().then(L=>{R.value=!0,T.value=L.data;for(let i=0;i<T.value.length;i++)T.value[i].is_use==1&&(H.value=T.value[i].view_path)})},H=I(""),G=()=>{Xe({view_path:H.value}).then(()=>{R.value=!1,$.go(0)})};return(L,i)=>{const O=Y,te=We,f=Ne,v=Ye,z=Me,b=tt,j=et,se=_e;return e(),c(se,{class:Z(["h-full px-[10px]",{"layout-header border-b border-color":!d(y)}])},{default:s(()=>[o(z,{class:"w-100 h-full w-full"},{default:s(()=>[o(v,{span:12},{default:s(()=>[t("div",St,[t("div",{class:"navbar-item flex items-center h-full cursor-pointer",onClick:k},[o(O,{name:"element-Refresh"})]),t("div",Et,[o(f,{separator:"/"},{default:s(()=>[(e(!0),l(_,null,E(d(N),(V,D)=>(e(),c(te,{key:D},{default:s(()=>[B(p(V.meta.title),1)]),_:2},1024))),128))]),_:1})])])]),_:1}),o(v,{span:12},{default:s(()=>[t("div",Tt,[d(u)=="site"?(e(),l("i",{key:0,class:"iconfont iconlingdang-xianxing cursor-pointer px-[8px]",title:d(U)("newInfo")},null,8,Vt)):m("",!0),d(u)=="site"?(e(),l("div",{key:1,class:"navbar-item flex items-center h-full cursor-pointer",onClick:K},[o(O,{name:"iconfont-iconqiehuan",title:d(U)("indexSwitch")},null,8,["title"])])):m("",!0),t("div",zt,[o(bt)]),t("div",{class:"navbar-item flex items-center h-full cursor-pointer",onClick:i[0]||(i[0]=(...V)=>d(C)&&d(C)(...V))},[d(A)?(e(),c(O,{key:0,name:"iconfont-icontuichuquanping"})):(e(),c(O,{key:1,name:"iconfont-iconquanping"}))]),t("div",Lt,[o(kt)]),t("div",It,[o(Ct)])])]),_:1})]),_:1}),Ce(t("input",{type:"hidden","onUpdate:modelValue":i[1]||(i[1]=V=>L.comparisonToken=V)},null,512),[[De,L.comparisonToken]]),o(j,{modelValue:L.detectionLoginDialog,"onUpdate:modelValue":i[2]||(i[2]=V=>L.detectionLoginDialog=V),title:d(U)("layout.detectionLoginTip"),width:"30%","close-on-click-modal":!1,"close-on-press-escape":!1,"show-close":!1},{footer:s(()=>[t("span",jt,[o(b,{onClick:L.detectionLoginFn},{default:s(()=>[B(p(d(U)("layout.detectionLoginOperation")),1)]),_:1},8,["onClick"])])]),default:s(()=>[t("span",null,p(d(U)("layout.detectionLoginContent")),1)]),_:1},8,["modelValue","title"]),o(j,{modelValue:R.value,"onUpdate:modelValue":i[3]||(i[3]=V=>R.value=V),title:d(U)("indexTemplate"),width:"550px","destroy-on-close":!0},{footer:s(()=>[t("span",Ut,[o(b,{type:"primary",onClick:G},{default:s(()=>[B(p(d(U)("confirm")),1)]),_:1})])]),default:s(()=>[t("div",Dt,[H.value==""?(e(!0),l(_,{key:0},E(T.value,(V,D)=>(e(),l("div",{key:D},[t("div",{onClick:le=>H.value=V.view_path,class:Z(["index-item py-[5px] px-[10px] mr-[10px] rounded-[3px] cursor-pointer",V.is_use==1?"bg-primary text-[#fff]":""])},[t("span",null,p(V.name),1)],10,At)]))),128)):(e(!0),l(_,{key:1},E(T.value,(V,D)=>(e(),l("div",{key:D},[t("div",{onClick:le=>H.value=V.view_path,class:Z(["index-item py-[5px] px-[10px] mr-[10px] rounded-[3px] cursor-pointer",H.value==V.view_path?"bg-primary text-[#fff]":""])},[t("span",null,p(V.name),1)],10,Rt)]))),128))])]),_:1},8,["modelValue","title"])]),_:1},8,["class"])}}});const Ft=fe(Bt,[["__scopeId","data-v-da1be2d2"]]),Ht=["onClick"],qt=["src","title"],Wt={key:0,class:"flex-1 flex flex-col justify-center items-center pb-[30px]"},Nt=t("div",{class:"w-[130px]"},[t("img",{src:st,class:"max-w-full",alt:""})],-1),Kt=t("div",{class:"text-[14px] text-[#909399]"},[B("暂无安装任何应用或插件，马上去"),t("a",{href:"https://www.niucloud.com/product/",target:"_blank",class:"text-[var(--el-color-primary)]"},"官方应用市场"),B("逛逛")],-1),Gt=[Nt,Kt],Ot=J({__name:"app-menu",props:["isShowHover","data","hoverType"],emits:["child-click"],setup(F,{emit:$}){const u=F;console.log();let C=I([]);u.data&&u.data.forEach((h,x)=>{h.type=="app"&&C.value.push(h)});const A=(h,x)=>{$("child-click",h,x)};return(h,x)=>(e(),l("div",{class:Z([{"group-hover:flex":u.isShowHover},"hidden fixed left-0 top-[65px] z-[5555] bg-[#fff] w-[640px] px-[28px] py-[20px] flex-wrap box-border shadow-lg "])},[(e(!0),l(_,null,E(d(C),(S,g)=>(e(),l("div",{key:g,onClick:y=>A(S,u.hoverType),class:"flex items-center cursor-pointer text-[#6d7278] hover:bg-[#f1f2f6] whitespace-nowrap py-[10px] px-[15px] box-border w-[165px]"},[t("img",{src:d(xe)(S.icon),class:"w-[44px] h-[44px] rounded-full mr-[5px]",alt:"",title:S.title},null,8,qt),t("span",null,p(S.title),1)],8,Ht))),128)),d(C).length?m("",!0):(e(),l("div",Wt,Gt))],2))}}),Pt={key:0,class:"w-[210px] box-border border-r-[1px] border-solid second-menu"},Qt={class:"flex items-center"},Zt=["src"],Jt={key:1,class:"flex items-center justify-center w-[30px] h-[30px]"},Xt={class:"w-[16px] h-[16px] relative flex items-center"},Yt={class:"ml-[11px] text-[15px]"},Mt={class:"w-[16px] h-[16px] relative flex items-center"},es={class:"ml-[11px] text-[15px]"},ts=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont icondian !text-[25px]"})],-1),ss={class:"ml-[11px] text-[15px]"},ls={class:"text-[14px]"},as={class:"text-[14px]"},ns={class:"text-[14px]"},os={key:0,class:"w-[16px] h-[16px] relative flex items-center"},is={class:"ml-[11px] text-[15px]"},cs=["onClick"],rs={key:0,class:"w-[16px] h-[16px] relative flex items-center"},ps={class:"ml-[11px] text-[15px]"},ds=t("div",{class:"!border-0 !border-t-[1px] border-solid mx-[25px] bg-[#f7f7f7] my-[5px]"},null,-1),ms={class:"w-[16px] h-[16px] relative flex items-center"},_s={class:"ml-[11px] text-[15px]"},us=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont iconyuanquan_huaban1 !text-[20px]"})],-1),xs={class:"ml-[11px] text-[15px]"},hs=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont icondian !text-[25px]"})],-1),fs={class:"ml-[11px] text-[15px]"},vs={class:"text-[14px]"},ys={class:"text-[14px]"},ks={class:"text-[14px]"},gs=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont iconyuanquan_huaban1 !text-[20px]"})],-1),bs={class:"ml-[11px] text-[15px]"},ws=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont icondian !text-[25px]"})],-1),$s={class:"ml-[11px] text-[15px]"},Cs={class:"text-[14px]"},Ss={class:"text-[14px]"},Es={key:0,class:"w-[16px] h-[16px] relative flex items-center"},Ts={class:"ml-[11px] text-[15px]"},Vs={key:0,class:"w-[16px] h-[16px] relative flex items-center"},zs={class:"ml-[11px] text-[15px]"},Ls={class:"w-[16px] h-[16px] relative flex items-center"},Is={class:"ml-[11px] text-[15px]"},js=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont iconyuanquan_huaban1 !text-[20px]"})],-1),Ds={class:"ml-[11px] text-[15px]"},As=t("div",{class:"w-[16px] h-[16px] relative flex items-center justify-center"},[t("span",{class:"iconfont icondian !text-[25px]"})],-1),Rs={class:"ml-[11px] text-[15px]"},Us={class:"text-[14px]"},Bs={class:"text-[14px]"},Fs={class:"text-[14px]"},Hs=["onClick"],qs={class:"text-[15px]"},Ws={key:0,class:"w-[16px] h-[16px] relative flex items-center"},Ns={class:"ml-[11px] text-[15px]"},Ks=J({__name:"index",setup(F){const $=ue(),u=M(),C=ce(),A=ve(),h=I(""),x=I("");h.value=W.get("menuAppStorage"),x.value=W.get("menuAppStorage");const S=I(!1),g=I([]),y=I([]),k=I([]),N=async()=>{const f=await Ae();g.value=g.value.concat(f.data),g.value.forEach((v,z)=>{v.type=="app"&&y.value.push(v.key),v.type=="addon"&&k.value.push(v.key)}),k.value=k.value.concat(["member","app_center"]),S.value=!0};N();const T=I({}),R=q(()=>{const f=[];return $.routers.forEach((v,z)=>{v.children&&v.children.length?(v.name=de(v.children),T.value[v.meta.app]=de(v.children),f.push(v)):(T.value[v.meta.app]=v.name,f.push(v))}),g.value&&g.value.length&&g.value.forEach((v,z)=>{f.forEach((b,j)=>{v.key==b.meta.key&&(b.meta.parentTitle=v.title,b.meta.parentIcon=v.icon)})}),g.value.length||(W.set({key:"menuAppStorage",data:""}),h.value=""),g.value.length&&!h.value&&(W.set({key:"menuAppStorage",data:y.value[0]}),h.value=y.value[0]),f});q(()=>u.dark),oe(()=>$.globalAppKey,(f,v)=>{N()},{deep:!0});const K=I(""),H=I("");oe(C,()=>{K.value=W.get("plugMenuTypeStorage");const f=C.matched[1];H.value=C.matched[1],x.value=f.meta.key||"overview",u.$patch(v=>{v.menuDrawer=!1})},{immediate:!0});let G=I(!0);const L=()=>{G.value=!0},i=(f,v)=>{if(v=="twofloatMenu"&&(G.value=!1),!f.meta&&f.type=="app"||f.meta.key!="official_market"){let z=f.name;if(f.type=="app"){h.value=f.key,x.value=f.key,W.set({key:"menuAppStorage",data:f.key}),W.set({key:"plugMenuTypeStorage",data:""});const b=$.appMenuList;b.push(f.key),$.setAppMenuList(b),z=T.value[f.key]}A.push({name:z})}else window.open("https://www.niucloud.com/product/","_blank")},O=q(()=>u.sidebar),te=f=>k.value.includes(x.value)&&h.value==f.meta.app||!y.value.includes(x.value)&&!k.value.includes(x.value)&&h.value&&h.value==f.meta.app||y.value.includes(x.value)&&(f.meta.key==x.value||f.meta.app==x.value)||!y.value.length&&(f.meta.key==x.value||f.meta.app==x.value);return(f,v)=>{const z=Y,b=Ke,j=Ge,se=Oe,V=ie;return S.value?(e(),l("div",{key:0,class:Z(["flex",{"two-type":d(O)=="twoType"},{"three-type":d(O)=="threeType"}])},[(e(!0),l(_,null,E(d(R),(D,le)=>(e(),l(_,{key:le},[te(D)?(e(),l("div",Pt,[t("div",{class:"group flex flex-col items-center justify-center h-[64px] border-b-[1px] border-solid second-head cursor-pointer relative",onMouseenter:L},[t("div",Qt,[D.meta.parentIcon?(e(),l("img",{key:0,src:d(xe)(D.meta.parentIcon),class:"w-[40px] h-[40px] mr-[8px]",alt:""},null,8,Zt)):(e(),l("div",Jt,[D.meta.icon?(e(),c(z,{key:0,name:D.meta.icon,class:"!w-auto",size:"24px"},null,8,["name"])):m("",!0)])),t("span",null,p(D.meta.app?D.meta.parentTitle:D.meta.title),1)]),o(Ot,{isShowHover:d(G),data:g.value,onChildClick:i,hoverType:"twofloatMenu"},null,8,["isShowHover","data"])],32),o(V,{class:"overflow-y-auto menus-wrap"},{default:s(()=>[o(se,{class:"apply-menu !border-0",router:!0,"unique-opened":"true","default-active":String(d(C).name)},{default:s(()=>[y.value.length?(e(),l(_,{key:0},[(e(!0),l(_,null,E(D.children,(a,pe)=>(e(),l(_,null,[a.children&&a.meta.show?(e(),c(j,{key:0,index:String(a.meta.title)},{title:s(()=>[t("div",Xt,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)]),t("span",Yt,p(a.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(a.children,(n,X)=>(e(),l(_,{key:X},[n.children&&n.meta.show?(e(),c(j,{key:0,index:String(n.meta.title),class:"three-menu"},{title:s(()=>[t("div",Mt,[n.meta.icon?(e(),c(z,{key:0,name:n.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)]),t("span",es,p(n.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(n.children,(r,P)=>(e(),l(_,{key:P},[r.children&&r.meta.show?(e(),c(j,{key:0,index:String(r.meta.title)},{title:s(()=>[ts,t("span",ss,p(r.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(r.children,(w,Q)=>(e(),l(_,{key:Q},[w.meta.show?(e(),c(b,{key:0,class:"!h-[52px] !pl-[55px]",index:String(w.name),onClick:ae=>i(w)},{title:s(()=>[t("span",ls,p(w.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):r.meta.show?(e(),c(b,{key:1,class:"!h-[52px] !pl-[35px]",index:String(r.name),onClick:w=>i(r)},{title:s(()=>[t("span",as,p(r.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):n.meta.show?(e(),c(b,{key:1,class:"!h-[52px] !pl-[52px]",index:String(n.name),onClick:r=>i(n)},{title:s(()=>[t("span",ns,p(n.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):a.meta.show&&a.meta.key!="official_market"?(e(),c(b,{key:1,class:"!pl-[25px] text-[#333]",index:String(a.name),onClick:n=>i(a)},{title:s(()=>[a.meta.icon?(e(),l("div",os,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)])):m("",!0),t("span",is,p(a.meta.title),1)]),_:2},1032,["index","onClick"])):a.meta.show&&a.meta.key=="official_market"?(e(),l("div",{key:2,class:"flex items-center !px-[25px] h-[56px] cursor-pointer text-[#333] el-menu-item",onClick:n=>i(a)},[a.meta.icon?(e(),l("div",rs,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)])):m("",!0),t("span",ps,p(a.meta.title),1)],8,cs)):m("",!0)],64))),256)),y.value.includes(x.value)||k.value.includes(x.value)?(e(),l(_,{key:0},[ds,(e(!0),l(_,null,E(d(R),(a,pe)=>(e(),l(_,null,[a.meta.attr=="system"&&!a.meta.app&&a.children?(e(),c(j,{key:0,index:String(a.meta.title)},{title:s(()=>[t("div",ms,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)]),t("span",_s,p(a.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(a.children,(n,X)=>(e(),l(_,{key:X},[n.meta.app&&n.children?(e(),c(j,{key:0,index:String(n.meta.title)},{title:s(()=>[us,t("span",xs,p(n.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(n.children,(r,P)=>(e(),l(_,{key:P},[r.children&&r.meta.show?(e(),c(j,{key:0,index:String(r.meta.title)},{title:s(()=>[hs,t("span",fs,p(r.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(r.children,(w,Q)=>(e(),l(_,{key:Q},[w.meta.show?(e(),c(b,{key:0,class:"!h-[52px] !pl-[55px]",index:String(w.name),onClick:ae=>i(w)},{title:s(()=>[t("span",vs,p(w.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):r.meta.show?(e(),c(b,{key:1,class:"!ml-[30px] !h-[52px] !pl-[35px]",index:String(r.name),onClick:w=>i(r)},{title:s(()=>[t("span",ys,p(r.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):m("",!0),n.meta.show?(e(),c(b,{key:1,class:"!h-[52px] !pl-[52px]",index:String(n.name),onClick:r=>i(n)},{title:s(()=>[t("span",ks,p(n.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128)),k.value.includes(x.value)&&a.meta.key=="app_center"&&K.value?(e(!0),l(_,{key:0},E(d(R),(n,X)=>(e(),l(_,null,[n.meta.app&&n.meta.app==K.value&&n.children?(e(),c(j,{key:0,index:String(n.meta.title)},{title:s(()=>[gs,t("span",bs,p(n.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(n.children,(r,P)=>(e(),l(_,{key:P},[r.children&&r.meta.show?(e(),c(j,{key:0,index:String(r.meta.title)},{title:s(()=>[ws,t("span",$s,p(r.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(r.children,(w,Q)=>(e(),l(_,{key:Q},[w.meta.show?(e(),c(b,{key:0,class:"!h-[52px] !pl-[55px]",index:String(w.name),onClick:ae=>i(w)},{title:s(()=>[t("span",Cs,p(w.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):r.meta.show?(e(),c(b,{key:1,class:"!ml-[30px] !h-[52px] !pl-[35px]",index:String(r.name),onClick:w=>i(r)},{title:s(()=>[t("span",Ss,p(r.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):n.meta.app&&n.meta.app==K.value?(e(),c(b,{key:1,class:"!pl-[25px] text-[#333]",index:String(n.name),onClick:r=>i(n)},{title:s(()=>[n.meta.icon?(e(),l("div",Es,[n.meta.icon?(e(),c(z,{key:0,name:n.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)])):m("",!0),t("span",Ts,p(n.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),256)):m("",!0)]),_:2},1032,["index"])):a.meta.attr=="system"&&!a.meta.app?(e(),c(b,{key:1,class:"!pl-[25px] text-[#333]",index:String(a.name),onClick:n=>i(a)},{title:s(()=>[a.meta.icon?(e(),l("div",Vs,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)])):m("",!0),t("span",zs,p(a.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),256))],64)):m("",!0)],64)):m("",!0),t("div",{class:Z(["!border-0 border-solid mx-[25px] bg-[#f7f7f7] my-[5px]",y.value.length?"!border-t-[1px]":""])},null,2),(e(!0),l(_,null,E(d(R),(a,pe)=>(e(),l(_,null,[a.meta.attr=="common"&&!a.meta.app&&a.children?(e(),c(j,{key:0,index:String(a.meta.title)},{title:s(()=>[t("div",Ls,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)]),t("span",Is,p(a.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(a.children,(n,X)=>(e(),l(_,{key:X},[n.children&&n.meta.show?(e(),c(j,{key:0,index:String(n.meta.title)},{title:s(()=>[js,t("span",Ds,p(n.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(n.children,(r,P)=>(e(),l(_,{key:P},[r.children&&r.meta.show?(e(),c(j,{key:0,index:String(r.meta.title)},{title:s(()=>[As,t("span",Rs,p(r.meta.title),1)]),default:s(()=>[(e(!0),l(_,null,E(n.children,(w,Q)=>(e(),l(_,{key:Q},[w.meta.show?(e(),c(b,{key:0,class:"!h-[52px] !pl-[55px]",index:String(w.name),onClick:ae=>i(w)},{title:s(()=>[t("span",Us,p(w.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):r.meta.show?(e(),c(b,{key:1,class:"!h-[52px] !pl-[55px]",index:String(r.name),onClick:w=>i(r)},{title:s(()=>[t("span",Bs,p(r.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),128))]),_:2},1032,["index"])):n.meta.show&&n.meta.key!="official_market"?(e(),c(b,{key:1,class:"!h-[52px] !pl-[52px]",index:String(n.name),onClick:r=>i(n)},{title:s(()=>[t("span",Fs,p(n.meta.title),1)]),_:2},1032,["index","onClick"])):n.meta.show&&n.meta.key=="official_market"?(e(),l("div",{key:2,class:"flex items-center !px-[52px] h-[56px] cursor-pointer text-[#333] el-menu-item",onClick:r=>i(n)},[t("span",qs,p(a.meta.title),1)],8,Hs)):m("",!0)],64))),128))]),_:2},1032,["index"])):a.meta.attr=="common"?(e(),c(b,{key:1,class:"!pl-[35px] text-[#333]",index:String(a.name),onClick:n=>i(a)},{title:s(()=>[a.meta.icon?(e(),l("div",Ws,[a.meta.icon?(e(),c(z,{key:0,name:a.meta.icon,class:"absolute !w-auto",size:"18px"},null,8,["name"])):m("",!0)])):m("",!0),t("span",Ns,p(a.meta.title),1)]),_:2},1032,["index","onClick"])):m("",!0)],64))),256))]),_:2},1032,["default-active"])]),_:2},1024)])):m("",!0)],64))),128))],2)):m("",!0)}}});const Gs={class:"common-layout min-w-[1200px]"},Jl=J({__name:"index",setup(F){const $=he(),u=M(),C=q(()=>u.dark);return(A,h)=>{const x=Ee,S=me("router-view"),g=ie,y=Te,k=_e;return e(),l("div",Gs,[o(k,{class:"w-100 h-screen"},{default:s(()=>[o(Ks),o(k,null,{default:s(()=>[o(x,null,{default:s(()=>[o(Ft)]),_:1}),o(y,{class:Z(["main-wrap h-full p-0",{"bg-page":d(C)}])},{default:s(()=>[o(g,null,{default:s(()=>[t("div",null,[d($).routeRefreshTag?(e(),c(S,{key:0},{default:s(({Component:N,route:T})=>[(e(),c(Se(N),{key:T.fullPath}))]),_:1})):m("",!0)])]),_:1})]),_:1},8,["class"])]),_:1})]),_:1})])}}});export{Jl as default};
