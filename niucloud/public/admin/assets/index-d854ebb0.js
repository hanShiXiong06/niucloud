import{E as L,u as ge}from"./index-868cd458.js";import{a as _e,b as Ne,n as Ce,O as Te}from"./index-a3cf5375.js";import{d as ne,j as we,k as Ee}from"./index-2083be2e.js";import{b as D,d as Y,u as I,_ as oe,i as te,w as Pe,e as Se}from"./plugin-vue_export-helper-edbdb6f8.js";import{m as le}from"./index-95693143.js";import{t as J,v as $e}from"./error-492b6a5b.js";import{c as B}from"./index-9fbce820.js";import{s as xe,a0 as ke,d as M,D as Q,r as w,w as O,A as Z,b as re,e as ie,n as ce,u as k,h as Be,K,c as F,o as ue,W as Oe,q as v,E as Re,g as de,j as ze,G as Ae,M as Me,O as Fe,L as Le,C as Ve}from"./runtime-core.esm-bundler-7c3fd514.js";import{E as V}from"./focus-trap-bb1e8c7a.js";import{U as be}from"./event-9519ab40.js";import{f as De}from"./index-7b0897f9.js";import{a as Ie,m as ae}from"./index-f02197a7.js";const U=Symbol("tabsRootContextKey"),Ke=(e,n,f)=>De(e.subTree).filter(t=>{var o;return ke(t)&&((o=t.type)==null?void 0:o.name)===n&&!!t.component}).map(t=>t.component.uid).map(t=>f[t]).filter(t=>!!t),Ue=(e,n)=>{const f={},C=xe([]);return{children:C,addChild:o=>{f[o.uid]=o,C.value=Ke(e,n,f)},removeChild:o=>{delete f[o],C.value=C.value.filter(P=>P.uid!==o)}}},je=D({tabs:{type:Y(Array),default:()=>le([])}}),ve="ElTabBar",qe=M({name:ve}),We=M({...qe,props:je,setup(e,{expose:n}){const f=e,C=K(),i=Q(U);i||J(ve,"<el-tabs><el-tab-bar /></el-tabs>");const t=I("tabs"),o=w(),P=w(),u=()=>{let m=0,r=0;const d=["top","bottom"].includes(i.props.tabPosition)?"width":"height",b=d==="width"?"x":"y",S=b==="x"?"left":"top";return f.tabs.every(E=>{var R,a;const h=(a=(R=C.parent)==null?void 0:R.refs)==null?void 0:a[`tab-${E.uid}`];if(!h)return!1;if(!E.active)return!0;m=h[`offset${B(S)}`],r=h[`client${B(d)}`];const g=window.getComputedStyle(h);return d==="width"&&(f.tabs.length>1&&(r-=Number.parseFloat(g.paddingLeft)+Number.parseFloat(g.paddingRight)),m+=Number.parseFloat(g.paddingLeft)),!1}),{[d]:`${r}px`,transform:`translate${B(b)}(${m}px)`}},y=()=>P.value=u();return O(()=>f.tabs,async()=>{await Z(),y()},{immediate:!0}),ne(o,()=>y()),n({ref:o,update:y}),(m,r)=>(re(),ie("div",{ref_key:"barRef",ref:o,class:ce([k(t).e("active-bar"),k(t).is(k(i).props.tabPosition)]),style:Be(P.value)},null,6))}});var He=oe(We,[["__file","/home/runner/work/element-plus/element-plus/packages/components/tabs/src/tab-bar.vue"]]);const Ge=D({panes:{type:Y(Array),default:()=>le([])},currentName:{type:[String,Number],default:""},editable:Boolean,type:{type:String,values:["card","border-card",""],default:""},stretch:Boolean}),Xe={tabClick:(e,n,f)=>f instanceof Event,tabRemove:(e,n)=>n instanceof Event},se="ElTabNav",Ye=M({name:se,props:Ge,emits:Xe,setup(e,{expose:n,emit:f}){const C=K(),i=Q(U);i||J(se,"<el-tabs><tab-nav /></el-tabs>");const t=I("tabs"),o=we(),P=Ee(),u=w(),y=w(),m=w(),r=w(!1),d=w(0),b=w(!1),S=w(!0),E=F(()=>["top","bottom"].includes(i.props.tabPosition)?"width":"height"),R=F(()=>({transform:`translate${E.value==="width"?"X":"Y"}(-${d.value}px)`})),a=()=>{if(!u.value)return;const l=u.value[`offset${B(E.value)}`],c=d.value;if(!c)return;const s=c>l?c-l:0;d.value=s},h=()=>{if(!u.value||!y.value)return;const l=y.value[`offset${B(E.value)}`],c=u.value[`offset${B(E.value)}`],s=d.value;if(l-s<=c)return;const x=l-s>c*2?s+c:l-c;d.value=x},g=async()=>{const l=y.value;if(!r.value||!m.value||!u.value||!l)return;await Z();const c=m.value.querySelector(".is-active");if(!c)return;const s=u.value,x=["top","bottom"].includes(i.props.tabPosition),N=c.getBoundingClientRect(),_=s.getBoundingClientRect(),$=x?l.offsetWidth-_.width:l.offsetHeight-_.height,T=d.value;let p=T;x?(N.left<_.left&&(p=T-(_.left-N.left)),N.right>_.right&&(p=T+N.right-_.right)):(N.top<_.top&&(p=T-(_.top-N.top)),N.bottom>_.bottom&&(p=T+(N.bottom-_.bottom))),p=Math.max(p,0),d.value=Math.min(p,$)},z=()=>{if(!y.value||!u.value)return;const l=y.value[`offset${B(E.value)}`],c=u.value[`offset${B(E.value)}`],s=d.value;c<l?(r.value=r.value||{},r.value.prev=s,r.value.next=s+c<l,l-s<c&&(d.value=l-c)):(r.value=!1,s>0&&(d.value=0))},j=l=>{const c=l.code,{up:s,down:x,left:N,right:_}=V;if(![s,x,N,_].includes(c))return;const $=Array.from(l.currentTarget.querySelectorAll("[role=tab]:not(.is-disabled)")),T=$.indexOf(l.target);let p;c===N||c===s?T===0?p=$.length-1:p=T-1:T<$.length-1?p=T+1:p=0,$[p].focus({preventScroll:!0}),$[p].click(),ee()},ee=()=>{S.value&&(b.value=!0)},q=()=>b.value=!1;return O(o,l=>{l==="hidden"?S.value=!1:l==="visible"&&setTimeout(()=>S.value=!0,50)}),O(P,l=>{l?setTimeout(()=>S.value=!0,50):S.value=!1}),ne(m,z),ue(()=>setTimeout(()=>g(),0)),Oe(()=>z()),n({scrollToActiveTab:g,removeFocus:q}),O(()=>e.panes,()=>C.update(),{flush:"post"}),()=>{const l=r.value?[v("span",{class:[t.e("nav-prev"),t.is("disabled",!r.value.prev)],onClick:a},[v(L,null,{default:()=>[v(_e,null,null)]})]),v("span",{class:[t.e("nav-next"),t.is("disabled",!r.value.next)],onClick:h},[v(L,null,{default:()=>[v(Ne,null,null)]})])]:null,c=e.panes.map((s,x)=>{var N,_,$,T;const p=s.uid,W=s.props.disabled,H=(_=(N=s.props.name)!=null?N:s.index)!=null?_:`${x}`,G=!W&&(s.isClosable||e.editable);s.index=`${x}`;const pe=G?v(L,{class:"is-icon-close",onClick:A=>f("tabRemove",s,A)},{default:()=>[v(Ce,null,null)]}):null,he=((T=($=s.slots).label)==null?void 0:T.call($))||s.props.label,ye=!W&&s.active?0:-1;return v("div",{ref:`tab-${p}`,class:[t.e("item"),t.is(i.props.tabPosition),t.is("active",s.active),t.is("disabled",W),t.is("closable",G),t.is("focus",b.value)],id:`tab-${H}`,key:`tab-${p}`,"aria-controls":`pane-${H}`,role:"tab","aria-selected":s.active,tabindex:ye,onFocus:()=>ee(),onBlur:()=>q(),onClick:A=>{q(),f("tabClick",s,H,A)},onKeydown:A=>{G&&(A.code===V.delete||A.code===V.backspace)&&f("tabRemove",s,A)}},[he,pe])});return v("div",{ref:m,class:[t.e("nav-wrap"),t.is("scrollable",!!r.value),t.is(i.props.tabPosition)]},[l,v("div",{class:t.e("nav-scroll"),ref:u},[v("div",{class:[t.e("nav"),t.is(i.props.tabPosition),t.is("stretch",e.stretch&&["top","bottom"].includes(i.props.tabPosition))],ref:y,style:R.value,role:"tablist",onKeydown:j},[e.type?null:v(He,{tabs:[...e.panes]},null),c])])])}}}),Je=D({type:{type:String,values:["card","border-card",""],default:""},activeName:{type:[String,Number]},closable:Boolean,addable:Boolean,modelValue:{type:[String,Number]},editable:Boolean,tabPosition:{type:String,values:["top","right","bottom","left"],default:"top"},beforeLeave:{type:Y(Function),default:()=>!0},stretch:Boolean}),X=e=>ze(e)||Ie(e),Qe={[be]:e=>X(e),tabClick:(e,n)=>n instanceof Event,tabChange:e=>X(e),edit:(e,n)=>["remove","add"].includes(n),tabRemove:e=>X(e),tabAdd:()=>!0};var Ze=M({name:"ElTabs",props:Je,emits:Qe,setup(e,{emit:n,slots:f,expose:C}){var i,t;const o=I("tabs"),{children:P,addChild:u,removeChild:y}=Ue(K(),"ElTabPane"),m=w(),r=w((t=(i=e.modelValue)!=null?i:e.activeName)!=null?t:"0"),d=a=>{r.value=a,n(be,a),n("tabChange",a)},b=async a=>{var h,g,z;if(!(r.value===a||te(a)))try{await((h=e.beforeLeave)==null?void 0:h.call(e,a,r.value))!==!1&&(d(a),(z=(g=m.value)==null?void 0:g.removeFocus)==null||z.call(g))}catch{}},S=(a,h,g)=>{a.props.disabled||(b(h),n("tabClick",a,g))},E=(a,h)=>{a.props.disabled||te(a.props.name)||(h.stopPropagation(),n("edit",a.props.name,"remove"),n("tabRemove",a.props.name))},R=()=>{n("edit",void 0,"add"),n("tabAdd")};return ge({from:'"activeName"',replacement:'"model-value" or "v-model"',scope:"ElTabs",version:"2.3.0",ref:"https://element-plus.org/en-US/component/tabs.html#attributes",type:"Attribute"},F(()=>!!e.activeName)),O(()=>e.activeName,a=>b(a)),O(()=>e.modelValue,a=>b(a)),O(r,async()=>{var a;await Z(),(a=m.value)==null||a.scrollToActiveTab()}),Re(U,{props:e,currentName:r,registerPane:u,unregisterPane:y}),C({currentName:r}),()=>{const a=e.editable||e.addable?v("span",{class:o.e("new-tab"),tabindex:"0",onClick:R,onKeydown:z=>{z.code===V.enter&&R()}},[v(L,{class:o.is("icon-plus")},{default:()=>[v(Te,null,null)]})]):null,h=v("div",{class:[o.e("header"),o.is(e.tabPosition)]},[a,v(Ye,{ref:m,currentName:r.value,editable:e.editable,type:e.type,panes:P.value,stretch:e.stretch,onTabClick:S,onTabRemove:E},null)]),g=v("div",{class:o.e("content")},[de(f,"default")]);return v("div",{class:[o.b(),o.m(e.tabPosition),{[o.m("card")]:e.type==="card",[o.m("border-card")]:e.type==="border-card"}]},[...e.tabPosition!=="bottom"?[h,g]:[g,h]])}}});const et=D({label:{type:String,default:""},name:{type:[String,Number]},closable:Boolean,disabled:Boolean,lazy:Boolean}),tt=["id","aria-hidden","aria-labelledby"],fe="ElTabPane",at=M({name:fe}),st=M({...at,props:et,setup(e){const n=e,f=K(),C=Ae(),i=Q(U);i||J(fe,"usage: <el-tabs><el-tab-pane /></el-tabs/>");const t=I("tab-pane"),o=w(),P=F(()=>n.closable||i.props.closable),u=ae(()=>{var b;return i.currentName.value===((b=n.name)!=null?b:o.value)}),y=w(u.value),m=F(()=>{var b;return(b=n.name)!=null?b:o.value}),r=ae(()=>!n.lazy||y.value||u.value);O(u,b=>{b&&(y.value=!0)});const d=Me({uid:f.uid,slots:C,props:n,paneName:m,active:u,index:o,isClosable:P});return ue(()=>{i.registerPane(d)}),Fe(()=>{i.unregisterPane(d.uid)}),(b,S)=>k(r)?Le((re(),ie("div",{key:0,id:`pane-${k(m)}`,class:ce(k(t).b()),role:"tabpanel","aria-hidden":!k(u),"aria-labelledby":`tab-${k(m)}`},[de(b.$slots,"default")],10,tt)),[[$e,k(u)]]):Ve("v-if",!0)}});var me=oe(st,[["__file","/home/runner/work/element-plus/element-plus/packages/components/tabs/src/tab-pane.vue"]]);const pt=Pe(Ze,{TabPane:me}),ht=Se(me);export{pt as E,ht as a,Ue as u};
