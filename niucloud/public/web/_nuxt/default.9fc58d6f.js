import{a as De,b as Ae,E as Ge}from"./el-main.3879143d.js";import{af as A,a as L,ac as B,r as C,au as W,av as k,a0 as ze,u as I,at as he,N as ee,O as K,ag as h,e as O,c7 as Ye,aR as j,Z as be,ah as Ue,bI as y,o as S,c as D,w as r,l as u,n as He,g as Ve,$ as Je,bg as je,a3 as Ie,bG as We,ar as Ee,V as se,U as qe,b2 as Ze,i as q,aS as Qe,al as Q,D as ne,v as te,aM as Xe,j as p,a9 as xe,a2 as le,F as eo,a1 as oo,aj as no,ay as ye,b as to,aT as lo,t as x,m as Y,k as me,I as so,E as ro,G as ao}from"./entry.08c6ab45.js";import{_ as Ce}from"./nuxt-link.c2db7583.js";import{e as P,f as io,u as _e,E as co,O as uo,w as ve,c as po,d as fo}from"./el-popper.bf736cff.js";import{E as re,c as $e}from"./el-button.41e73ba9.js";import{E as mo}from"./el-scrollbar.691cd720.js";import{u as Te,F as _o}from"./index.34f12059.js";import{a as vo}from"./use-form-item.7dcbf65b.js";import{_ as ke}from"./_plugin-vue_export-helper.c27b6911.js";import{a as go}from"./system.6cdccf49.js";import"./vnode.7865e18e.js";const wo=L({inheritAttrs:!1});function ho(e,o,i,n,d,l){return B(e.$slots,"default")}var bo=A(wo,[["render",ho],["__file","/home/runner/work/element-plus/element-plus/packages/components/collection/src/collection.vue"]]);const Io=L({name:"ElCollectionItem",inheritAttrs:!1});function Eo(e,o,i,n,d,l){return B(e.$slots,"default")}var yo=A(Io,[["render",Eo],["__file","/home/runner/work/element-plus/element-plus/packages/components/collection/src/collection-item.vue"]]);const Se="data-el-collection-item",Oe=e=>{const o=`El${e}Collection`,i=`${o}Item`,n=Symbol(o),d=Symbol(i),l={...bo,name:o,setup(){const s=C(null),f=new Map;W(n,{itemMap:f,getItems:()=>{const g=I(s);if(!g)return[];const v=Array.from(g.querySelectorAll(`[${Se}]`));return[...f.values()].sort((t,_)=>v.indexOf(t.ref)-v.indexOf(_.ref))},collectionRef:s})}},a={...yo,name:i,setup(s,{attrs:f}){const w=C(null),g=k(n,void 0);W(d,{collectionItemRef:w}),ze(()=>{const v=I(w);v&&g.itemMap.set(v,{ref:v,...f})}),he(()=>{const v=I(w);g.itemMap.delete(v)})}};return{COLLECTION_INJECTION_KEY:n,COLLECTION_ITEM_INJECTION_KEY:d,ElCollection:l,ElCollectionItem:a}},Co=ee({style:{type:K([String,Array,Object])},currentTabId:{type:K(String)},defaultCurrentTabId:String,loop:Boolean,dir:{type:String,values:["ltr","rtl"],default:"ltr"},orientation:{type:K(String)},onBlur:Function,onFocus:Function,onMousedown:Function}),{ElCollection:$o,ElCollectionItem:To,COLLECTION_INJECTION_KEY:ae,COLLECTION_ITEM_INJECTION_KEY:ko}=Oe("RovingFocusGroup"),ie=Symbol("elRovingFocusGroup"),Fe=Symbol("elRovingFocusGroupItem"),So={ArrowLeft:"prev",ArrowUp:"prev",ArrowRight:"next",ArrowDown:"next",PageUp:"first",Home:"first",PageDown:"last",End:"last"},Oo=(e,o)=>{if(o!=="rtl")return e;switch(e){case h.right:return h.left;case h.left:return h.right;default:return e}},Fo=(e,o,i)=>{const n=Oo(e.key,i);if(!(o==="vertical"&&[h.left,h.right].includes(n))&&!(o==="horizontal"&&[h.up,h.down].includes(n)))return So[n]},No=(e,o)=>e.map((i,n)=>e[(n+o)%e.length]),ce=e=>{const{activeElement:o}=document;for(const i of e)if(i===o||(i.focus(),o!==document.activeElement))return},ge="currentTabIdChange",we="rovingFocusGroup.entryFocus",Ro={bubbles:!1,cancelable:!0},Bo=L({name:"ElRovingFocusGroupImpl",inheritAttrs:!1,props:Co,emits:[ge,"entryFocus"],setup(e,{emit:o}){var i;const n=C((i=e.currentTabId||e.defaultCurrentTabId)!=null?i:null),d=C(!1),l=C(!1),a=C(null),{getItems:s}=k(ae,void 0),f=O(()=>[{outline:"none"},e.style]),w=m=>{o(ge,m)},g=()=>{d.value=!0},v=P(m=>{var b;(b=e.onMousedown)==null||b.call(e,m)},()=>{l.value=!0}),$=P(m=>{var b;(b=e.onFocus)==null||b.call(e,m)},m=>{const b=!I(l),{target:z,currentTarget:N}=m;if(z===N&&b&&!I(d)){const U=new Event(we,Ro);if(N==null||N.dispatchEvent(U),!U.defaultPrevented){const E=s().filter(R=>R.focusable),M=E.find(R=>R.active),T=E.find(R=>R.id===I(n)),V=[M,T,...E].filter(Boolean).map(R=>R.ref);ce(V)}}l.value=!1}),t=P(m=>{var b;(b=e.onBlur)==null||b.call(e,m)},()=>{d.value=!1}),_=(...m)=>{o("entryFocus",...m)};W(ie,{currentTabbedId:Ye(n),loop:j(e,"loop"),tabIndex:O(()=>I(d)?-1:0),rovingFocusGroupRef:a,rovingFocusGroupRootStyle:f,orientation:j(e,"orientation"),dir:j(e,"dir"),onItemFocus:w,onItemShiftTab:g,onBlur:t,onFocus:$,onMousedown:v}),be(()=>e.currentTabId,m=>{n.value=m??null}),Ue(a,we,_)}});function Lo(e,o,i,n,d,l){return B(e.$slots,"default")}var Mo=A(Bo,[["render",Lo],["__file","/home/runner/work/element-plus/element-plus/packages/components/roving-focus-group/src/roving-focus-group-impl.vue"]]);const Po=L({name:"ElRovingFocusGroup",components:{ElFocusGroupCollection:$o,ElRovingFocusGroupImpl:Mo}});function Ko(e,o,i,n,d,l){const a=y("el-roving-focus-group-impl"),s=y("el-focus-group-collection");return S(),D(s,null,{default:r(()=>[u(a,He(Ve(e.$attrs)),{default:r(()=>[B(e.$slots,"default")]),_:3},16)]),_:3})}var Do=A(Po,[["render",Ko],["__file","/home/runner/work/element-plus/element-plus/packages/components/roving-focus-group/src/roving-focus-group.vue"]]);const Ao=L({components:{ElRovingFocusCollectionItem:To},props:{focusable:{type:Boolean,default:!0},active:{type:Boolean,default:!1}},emits:["mousedown","focus","keydown"],setup(e,{emit:o}){const{currentTabbedId:i,loop:n,onItemFocus:d,onItemShiftTab:l}=k(ie,void 0),{getItems:a}=k(ae,void 0),s=Te(),f=C(null),w=P(t=>{o("mousedown",t)},t=>{e.focusable?d(I(s)):t.preventDefault()}),g=P(t=>{o("focus",t)},()=>{d(I(s))}),v=P(t=>{o("keydown",t)},t=>{const{key:_,shiftKey:m,target:b,currentTarget:z}=t;if(_===h.tab&&m){l();return}if(b!==z)return;const N=Fo(t);if(N){t.preventDefault();let E=a().filter(M=>M.focusable).map(M=>M.ref);switch(N){case"last":{E.reverse();break}case"prev":case"next":{N==="prev"&&E.reverse();const M=E.indexOf(z);E=n.value?No(E,M+1):E.slice(M+1);break}}Je(()=>{ce(E)})}}),$=O(()=>i.value===I(s));return W(Fe,{rovingFocusGroupItemRef:f,tabIndex:O(()=>I($)?0:-1),handleMousedown:w,handleFocus:g,handleKeydown:v}),{id:s,handleKeydown:v,handleFocus:g,handleMousedown:w}}});function Go(e,o,i,n,d,l){const a=y("el-roving-focus-collection-item");return S(),D(a,{id:e.id,focusable:e.focusable,active:e.active},{default:r(()=>[B(e.$slots,"default")]),_:3},8,["id","focusable","active"])}var zo=A(Ao,[["render",Go],["__file","/home/runner/work/element-plus/element-plus/packages/components/roving-focus-group/src/roving-focus-item.vue"]]);const Yo=ee({trigger:io.trigger,effect:{..._e.effect,default:"light"},type:{type:K(String)},placement:{type:K(String),default:"bottom"},popperOptions:{type:K(Object),default:()=>({})},id:String,size:{type:String,default:""},splitButton:Boolean,hideOnClick:{type:Boolean,default:!0},loop:{type:Boolean,default:!0},showTimeout:{type:Number,default:150},hideTimeout:{type:Number,default:150},tabindex:{type:K([Number,String]),default:0},maxHeight:{type:K([Number,String]),default:""},popperClass:{type:String,default:""},disabled:{type:Boolean,default:!1},role:{type:String,default:"menu"},buttonProps:{type:K(Object)},teleported:_e.teleported}),Ne=ee({command:{type:[Object,String,Number],default:()=>({})},disabled:Boolean,divided:Boolean,textValue:String,icon:{type:je}}),Uo=ee({onKeydown:{type:K(Function)}}),Ho=[h.down,h.pageDown,h.home],Re=[h.up,h.pageUp,h.end],Vo=[...Ho,...Re],{ElCollection:Jo,ElCollectionItem:jo,COLLECTION_INJECTION_KEY:Wo,COLLECTION_ITEM_INJECTION_KEY:qo}=Oe("Dropdown"),oe=Symbol("elDropdown"),{ButtonGroup:Zo}=re,Qo=L({name:"ElDropdown",components:{ElButton:re,ElButtonGroup:Zo,ElScrollbar:mo,ElDropdownCollection:Jo,ElTooltip:co,ElRovingFocusGroup:Do,ElOnlyChild:uo,ElIcon:Ie,ArrowDown:We},props:Yo,emits:["visible-change","click","command"],setup(e,{emit:o}){const i=Ee(),n=se("dropdown"),{t:d}=qe(),l=C(),a=C(),s=C(null),f=C(null),w=C(null),g=C(null),v=C(!1),$=[h.enter,h.space,h.down],t=O(()=>({maxHeight:Ze(e.maxHeight)})),_=O(()=>[n.m(E.value)]),m=Te().value,b=O(()=>e.id||m);be([l,j(e,"trigger")],([c,F],[J])=>{var ue,pe,fe;const Ke=Xe(F)?F:[F];(ue=J==null?void 0:J.$el)!=null&&ue.removeEventListener&&J.$el.removeEventListener("pointerenter",T),(pe=c==null?void 0:c.$el)!=null&&pe.removeEventListener&&c.$el.removeEventListener("pointerenter",T),(fe=c==null?void 0:c.$el)!=null&&fe.addEventListener&&Ke.includes("hover")&&c.$el.addEventListener("pointerenter",T)},{immediate:!0}),he(()=>{var c,F;(F=(c=l.value)==null?void 0:c.$el)!=null&&F.removeEventListener&&l.value.$el.removeEventListener("pointerenter",T)});function z(){N()}function N(){var c;(c=s.value)==null||c.onClose()}function U(){var c;(c=s.value)==null||c.onOpen()}const E=vo();function M(...c){o("command",...c)}function T(){var c,F;(F=(c=l.value)==null?void 0:c.$el)==null||F.focus()}function H(){}function V(){const c=I(f);c==null||c.focus(),g.value=null}function R(c){g.value=c}function de(c){v.value||(c.preventDefault(),c.stopImmediatePropagation())}function X(){o("visible-change",!0)}function Z(c){(c==null?void 0:c.type)==="keydown"&&f.value.focus()}function Pe(){o("visible-change",!1)}return W(oe,{contentRef:f,role:O(()=>e.role),triggerId:b,isUsingKeyboard:v,onItemEnter:H,onItemLeave:V}),W("elDropdown",{instance:i,dropdownSize:E,handleClick:z,commandHandler:M,trigger:j(e,"trigger"),hideOnClick:j(e,"hideOnClick")}),{t:d,ns:n,scrollbar:w,wrapStyle:t,dropdownTriggerKls:_,dropdownSize:E,triggerId:b,triggerKeys:$,currentTabId:g,handleCurrentTabIdChange:R,handlerMainButtonClick:c=>{o("click",c)},handleEntryFocus:de,handleClose:N,handleOpen:U,handleBeforeShowTooltip:X,handleShowTooltip:Z,handleBeforeHideTooltip:Pe,onFocusAfterTrapped:c=>{var F,J;c.preventDefault(),(J=(F=f.value)==null?void 0:F.focus)==null||J.call(F,{preventScroll:!0})},popperRef:s,contentRef:f,triggeringElementRef:l,referenceElementRef:a}}});function Xo(e,o,i,n,d,l){var a;const s=y("el-dropdown-collection"),f=y("el-roving-focus-group"),w=y("el-scrollbar"),g=y("el-only-child"),v=y("el-tooltip"),$=y("el-button"),t=y("arrow-down"),_=y("el-icon"),m=y("el-button-group");return S(),q("div",{class:ne([e.ns.b(),e.ns.is("disabled",e.disabled)])},[u(v,{ref:"popperRef",role:e.role,effect:e.effect,"fallback-placements":["bottom","top"],"popper-options":e.popperOptions,"gpu-acceleration":!1,"hide-after":e.trigger==="hover"?e.hideTimeout:0,"manual-mode":!0,placement:e.placement,"popper-class":[e.ns.e("popper"),e.popperClass],"reference-element":(a=e.referenceElementRef)==null?void 0:a.$el,trigger:e.trigger,"trigger-keys":e.triggerKeys,"trigger-target-el":e.contentRef,"show-after":e.trigger==="hover"?e.showTimeout:0,"stop-popper-mouse-event":!1,"virtual-ref":e.triggeringElementRef,"virtual-triggering":e.splitButton,disabled:e.disabled,transition:`${e.ns.namespace.value}-zoom-in-top`,teleported:e.teleported,pure:"",persistent:"",onBeforeShow:e.handleBeforeShowTooltip,onShow:e.handleShowTooltip,onBeforeHide:e.handleBeforeHideTooltip},Qe({content:r(()=>[u(w,{ref:"scrollbar","wrap-style":e.wrapStyle,tag:"div","view-class":e.ns.e("list")},{default:r(()=>[u(f,{loop:e.loop,"current-tab-id":e.currentTabId,orientation:"horizontal",onCurrentTabIdChange:e.handleCurrentTabIdChange,onEntryFocus:e.handleEntryFocus},{default:r(()=>[u(s,null,{default:r(()=>[B(e.$slots,"dropdown")]),_:3})]),_:3},8,["loop","current-tab-id","onCurrentTabIdChange","onEntryFocus"])]),_:3},8,["wrap-style","view-class"])]),_:2},[e.splitButton?void 0:{name:"default",fn:r(()=>[u(g,{id:e.triggerId,ref:"triggeringElementRef",role:"button",tabindex:e.tabindex},{default:r(()=>[B(e.$slots,"default")]),_:3},8,["id","tabindex"])])}]),1032,["role","effect","popper-options","hide-after","placement","popper-class","reference-element","trigger","trigger-keys","trigger-target-el","show-after","virtual-ref","virtual-triggering","disabled","transition","teleported","onBeforeShow","onShow","onBeforeHide"]),e.splitButton?(S(),D(m,{key:0},{default:r(()=>[u($,Q({ref:"referenceElementRef"},e.buttonProps,{size:e.dropdownSize,type:e.type,disabled:e.disabled,tabindex:e.tabindex,onClick:e.handlerMainButtonClick}),{default:r(()=>[B(e.$slots,"default")]),_:3},16,["size","type","disabled","tabindex","onClick"]),u($,Q({id:e.triggerId,ref:"triggeringElementRef"},e.buttonProps,{role:"button",size:e.dropdownSize,type:e.type,class:e.ns.e("caret-button"),disabled:e.disabled,tabindex:e.tabindex,"aria-label":e.t("el.dropdown.toggleDropdown")}),{default:r(()=>[u(_,{class:ne(e.ns.e("icon"))},{default:r(()=>[u(t)]),_:1},8,["class"])]),_:1},16,["id","size","type","class","disabled","tabindex","aria-label"])]),_:3})):te("v-if",!0)],2)}var xo=A(Qo,[["render",Xo],["__file","/home/runner/work/element-plus/element-plus/packages/components/dropdown/src/dropdown.vue"]]);const en=L({name:"DropdownItemImpl",components:{ElIcon:Ie},props:Ne,emits:["pointermove","pointerleave","click","clickimpl"],setup(e,{emit:o}){const i=se("dropdown"),{role:n}=k(oe,void 0),{collectionItemRef:d}=k(qo,void 0),{collectionItemRef:l}=k(ko,void 0),{rovingFocusGroupItemRef:a,tabIndex:s,handleFocus:f,handleKeydown:w,handleMousedown:g}=k(Fe,void 0),v=$e(d,l,a),$=O(()=>n.value==="menu"?"menuitem":n.value==="navigation"?"link":"button"),t=P(_=>{const{code:m}=_;if(m===h.enter||m===h.space)return _.preventDefault(),_.stopImmediatePropagation(),o("clickimpl",_),!0},w);return{ns:i,itemRef:v,dataset:{[Se]:""},role:$,tabIndex:s,handleFocus:f,handleKeydown:t,handleMousedown:g}}}),on=["aria-disabled","tabindex","role"];function nn(e,o,i,n,d,l){const a=y("el-icon");return S(),q(eo,null,[e.divided?(S(),q("li",Q({key:0,role:"separator",class:e.ns.bem("menu","item","divided")},e.$attrs),null,16)):te("v-if",!0),p("li",Q({ref:e.itemRef},{...e.dataset,...e.$attrs},{"aria-disabled":e.disabled,class:[e.ns.be("menu","item"),e.ns.is("disabled",e.disabled)],tabindex:e.tabIndex,role:e.role,onClick:o[0]||(o[0]=s=>e.$emit("clickimpl",s)),onFocus:o[1]||(o[1]=(...s)=>e.handleFocus&&e.handleFocus(...s)),onKeydown:o[2]||(o[2]=le((...s)=>e.handleKeydown&&e.handleKeydown(...s),["self"])),onMousedown:o[3]||(o[3]=(...s)=>e.handleMousedown&&e.handleMousedown(...s)),onPointermove:o[4]||(o[4]=s=>e.$emit("pointermove",s)),onPointerleave:o[5]||(o[5]=s=>e.$emit("pointerleave",s))}),[e.icon?(S(),D(a,{key:0},{default:r(()=>[(S(),D(xe(e.icon)))]),_:1})):te("v-if",!0),B(e.$slots,"default")],16,on)],64)}var tn=A(en,[["render",nn],["__file","/home/runner/work/element-plus/element-plus/packages/components/dropdown/src/dropdown-item-impl.vue"]]);const Be=()=>{const e=k("elDropdown",{}),o=O(()=>e==null?void 0:e.dropdownSize);return{elDropdown:e,_elDropdownSize:o}},ln=L({name:"ElDropdownItem",components:{ElDropdownCollectionItem:jo,ElRovingFocusItem:zo,ElDropdownItemImpl:tn},inheritAttrs:!1,props:Ne,emits:["pointermove","pointerleave","click"],setup(e,{emit:o,attrs:i}){const{elDropdown:n}=Be(),d=Ee(),l=C(null),a=O(()=>{var t,_;return(_=(t=I(l))==null?void 0:t.textContent)!=null?_:""}),{onItemEnter:s,onItemLeave:f}=k(oe,void 0),w=P(t=>(o("pointermove",t),t.defaultPrevented),ve(t=>{if(e.disabled){f(t);return}const _=t.currentTarget;_===document.activeElement||_.contains(document.activeElement)||(s(t),t.defaultPrevented||_==null||_.focus())})),g=P(t=>(o("pointerleave",t),t.defaultPrevented),ve(t=>{f(t)})),v=P(t=>{if(!e.disabled)return o("click",t),t.type!=="keydown"&&t.defaultPrevented},t=>{var _,m,b;if(e.disabled){t.stopImmediatePropagation();return}(_=n==null?void 0:n.hideOnClick)!=null&&_.value&&((m=n.handleClick)==null||m.call(n)),(b=n.commandHandler)==null||b.call(n,e.command,d,t)}),$=O(()=>({...e,...i}));return{handleClick:v,handlePointerMove:w,handlePointerLeave:g,textContent:a,propsAndAttrs:$}}});function sn(e,o,i,n,d,l){var a;const s=y("el-dropdown-item-impl"),f=y("el-roving-focus-item"),w=y("el-dropdown-collection-item");return S(),D(w,{disabled:e.disabled,"text-value":(a=e.textValue)!=null?a:e.textContent},{default:r(()=>[u(f,{focusable:!e.disabled},{default:r(()=>[u(s,Q(e.propsAndAttrs,{onPointerleave:e.handlePointerLeave,onPointermove:e.handlePointerMove,onClickimpl:e.handleClick}),{default:r(()=>[B(e.$slots,"default")]),_:3},16,["onPointerleave","onPointermove","onClickimpl"])]),_:3},8,["focusable"])]),_:3},8,["disabled","text-value"])}var Le=A(ln,[["render",sn],["__file","/home/runner/work/element-plus/element-plus/packages/components/dropdown/src/dropdown-item.vue"]]);const rn=L({name:"ElDropdownMenu",props:Uo,setup(e){const o=se("dropdown"),{_elDropdownSize:i}=Be(),n=i.value,{focusTrapRef:d,onKeydown:l}=k(_o,void 0),{contentRef:a,role:s,triggerId:f}=k(oe,void 0),{collectionRef:w,getItems:g}=k(Wo,void 0),{rovingFocusGroupRef:v,rovingFocusGroupRootStyle:$,tabIndex:t,onBlur:_,onFocus:m,onMousedown:b}=k(ie,void 0),{collectionRef:z}=k(ae,void 0),N=O(()=>[o.b("menu"),o.bm("menu",n==null?void 0:n.value)]),U=$e(a,w,d,v,z),E=P(T=>{var H;(H=e.onKeydown)==null||H.call(e,T)},T=>{const{currentTarget:H,code:V,target:R}=T;if(H.contains(R),h.tab===V&&T.stopImmediatePropagation(),T.preventDefault(),R!==I(a)||!Vo.includes(V))return;const X=g().filter(Z=>!Z.disabled).map(Z=>Z.ref);Re.includes(V)&&X.reverse(),ce(X)});return{size:n,rovingFocusGroupRootStyle:$,tabIndex:t,dropdownKls:N,role:s,triggerId:f,dropdownListWrapperRef:U,handleKeydown:T=>{E(T),l(T)},onBlur:_,onFocus:m,onMousedown:b}}}),an=["role","aria-labelledby"];function cn(e,o,i,n,d,l){return S(),q("ul",{ref:e.dropdownListWrapperRef,class:ne(e.dropdownKls),style:oo(e.rovingFocusGroupRootStyle),tabindex:-1,role:e.role,"aria-labelledby":e.triggerId,onBlur:o[0]||(o[0]=(...a)=>e.onBlur&&e.onBlur(...a)),onFocus:o[1]||(o[1]=(...a)=>e.onFocus&&e.onFocus(...a)),onKeydown:o[2]||(o[2]=le((...a)=>e.handleKeydown&&e.handleKeydown(...a),["self"])),onMousedown:o[3]||(o[3]=le((...a)=>e.onMousedown&&e.onMousedown(...a),["self"]))},[B(e.$slots,"default")],46,an)}var Me=A(rn,[["render",cn],["__file","/home/runner/work/element-plus/element-plus/packages/components/dropdown/src/dropdown-menu.vue"]]);const dn=no(xo,{DropdownItem:Le,DropdownMenu:Me});ye(Le);ye(Me);const un=""+new URL("logo.58dc7b81.jpg",import.meta.url).href,G=e=>(ro("data-v-abe708ca"),e=e(),ao(),e),pn={class:"flex h-full min-w-[1200px]"},fn={class:"flex items-center ml-[20px]"},mn=G(()=>p("div",{class:"w-[132px] mr-[10px]"},[p("img",{src:un})],-1)),_n=G(()=>p("div",{class:"hidden text-[14px] text-[#A6B0C8] xl:block"},[Y("|"),p("span",{class:"ml-[10px]"},"一款快速开发SAAS通用管理系统后台框架")],-1)),vn={class:"mx-auto flex-shrink"},gn=G(()=>p("span",{class:"text-base mx-4"},"首页",-1)),wn=G(()=>p("span",null,null,-1)),hn=G(()=>p("span",{class:"text-base mx-4"},"文章",-1)),bn=G(()=>p("span",null,null,-1)),In=G(()=>p("span",{class:"text-base mx-4"},"社区",-1)),En=G(()=>p("span",null,null,-1)),yn={class:"flex items-center justify-end mr-[20px] ml-auto whitespace-pre"},Cn={class:"cursor-pointer"},$n=G(()=>p("span",{class:"mx-2"},"|",-1)),Tn=L({__name:"index",setup(e){const o=to(),i=O(()=>o.info),n=()=>{o.logout(),so("/auth/login")},d=lo();return(l,a)=>{const s=Ce,f=po,w=fo,g=dn,v=re;return S(),q("div",pn,[p("div",fn,[u(s,{to:"/"},{default:r(()=>[mn]),_:1}),_n]),p("div",vn,[u(w,{"default-active":I(d).route,class:"h-full",mode:"horizontal",ellipsis:!1,router:!0},{default:r(()=>[u(f,{index:"/",route:"/"},{default:r(()=>[gn,wn]),_:1}),u(f,{index:"/community/answer",route:"/"},{default:r(()=>[hn,bn]),_:1}),u(f,{route:"/"},{default:r(()=>[In,En]),_:1})]),_:1},8,["default-active"])]),p("div",yn,[I(i)?(S(),D(g,{key:0},{default:r(()=>[p("div",null,[u(s,{to:"/member/center"},{default:r(()=>[p("span",Cn,x(I(i).nickname),1)]),_:1}),$n,p("span",{class:"cursor-pointer",onClick:n},"退出")])]),_:1})):(S(),D(s,{key:1,to:"/auth/login"},{default:r(()=>[u(v,{type:"primary",link:""},{default:r(()=>[Y(x(("t"in l?l.t:I(me))("login"))+" / "+x(("t"in l?l.t:I(me))("register")),1)]),_:1})]),_:1}))])])}}});const kn=ke(Tn,[["__scopeId","data-v-abe708ca"]]),Sn={class:"flex h-[220px] min-w-[1200px] bg-[#3F4045]"},On={class:"mt-[70px] w-full"},Fn={class:"text-center text-[#999]"},Nn=p("span",null,"友情链接：",-1),Rn=p("span",{class:"mr-[10px]"},"宝塔",-1),Bn=p("span",{class:"mr-[10px]"},"开源中国",-1),Ln=p("span",{class:"mr-[10px]"},"阿里云",-1),Mn=p("span",{class:"mr-[10px]"},"码云Gitee",-1),Pn=p("span",{class:"mr-[10px]"},"腾讯云",-1),Kn=p("span",{class:"mr-[10px]"},"微信公众平台",-1),Dn=p("span",{class:"mr-[10px]"},"Thinkphp",-1),An={class:"text-center mt-[20px] text-[#999]"},Gn=L({__name:"index",setup(e){const o=C();return(()=>{go({}).then(n=>{o.value=n.data.icp})})(),(n,d)=>{const l=Ce;return S(),q("div",Sn,[p("div",On,[p("p",Fn,[Nn,u(l,{to:"https://www.bt.cn"},{default:r(()=>[Rn,Y("| ")]),_:1}),u(l,{to:"https://www.oschina.net"},{default:r(()=>[Bn,Y("| ")]),_:1}),u(l,{to:"https://www.aliyun.com"},{default:r(()=>[Ln,Y("| ")]),_:1}),u(l,{to:"https://gitee.com/"},{default:r(()=>[Mn,Y("| ")]),_:1}),u(l,{to:"https://cloud.tencent.com/"},{default:r(()=>[Pn,Y("| ")]),_:1}),u(l,{to:"https://mp.weixin.qq.com"},{default:r(()=>[Kn,Y("| ")]),_:1}),u(l,{to:"http://www.thinkphp.cn"},{default:r(()=>[Dn]),_:1})]),p("p",An,[u(l,{to:"https://beian.miit.gov.cn/"},{default:r(()=>[p("span",null,"备案号:"+x(o.value),1)]),_:1})])])])}}}),zn=L({__name:"default",setup(e){return(o,i)=>{const n=De,d=Ae,l=Ge;return S(),D(l,{class:"w-screen h-screen"},{default:r(()=>[u(n,{class:"shadow z-1"},{default:r(()=>[u(kn)]),_:1}),u(d,{class:"p-0 min-w-[1200px]"},{default:r(()=>[B(o.$slots,"default",{},void 0,!0),u(Gn)]),_:3})]),_:3})}}});const ot=ke(zn,[["__scopeId","data-v-2559953f"]]);export{ot as default};
