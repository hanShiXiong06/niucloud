import{U as Q,v as le,T as se}from"./event-10eba222.js";import{u as te,b as ae,c as ne,E as re}from"./index-9fe5de95.js";import{b as W,t as ie,E as ue,a5 as de,d as ce,c as fe,O as Z,e as me,N as _,i as pe}from"./index-2fcd1254.js";import{W as ye,d as N,I as J,c as B,e as I,f as j,g as O,h as E,n as C,u as e,B as ve,y as R,x as k,v as q,Z as ge,H as K,j as Y,_ as X,r as D,aZ as Ce,w as G,E as be,o as he,P as ke,a_ as Ee,K as De,b as Ie,J as Be,Q as Fe,C as Te,D as we,a9 as Ae,l as Se}from"./base-06478700.js";import{u as $e}from"./index-f27d6ce0.js";import{F as Pe,u as Oe,E as Re}from"./focus-trap-3e826cdc.js";import{u as H}from"./index-818c0ce2.js";const Ne=(...o)=>a=>{o.forEach(i=>{ye(i)?i(a):i.value=a})},x=Symbol("dialogInjectionKey"),ee=W({center:{type:Boolean,default:!1},alignCenter:{type:Boolean,default:!1},closeIcon:{type:ie},customClass:{type:String,default:""},draggable:{type:Boolean,default:!1},fullscreen:{type:Boolean,default:!1},showClose:{type:Boolean,default:!0},title:{type:String,default:""}}),Me={close:()=>!0},Le=["aria-label"],ze=["id"],Ve=N({name:"ElDialogContent"}),Ue=N({...Ve,props:ee,emits:Me,setup(o){const a=o,{t:i}=$e(),{Close:u}=de,{dialogRef:c,headerRef:p,bodyId:F,ns:t,style:n}=J(x),{focusTrapRef:y}=J(Pe),f=Ne(y,c),v=B(()=>a.draggable);return te(c,p,v),(s,d)=>(I(),j("div",{ref:e(f),class:C([e(t).b(),e(t).is("fullscreen",s.fullscreen),e(t).is("draggable",e(v)),e(t).is("align-center",s.alignCenter),{[e(t).m("center")]:s.center},s.customClass]),style:Y(e(n)),tabindex:"-1"},[O("header",{ref_key:"headerRef",ref:p,class:C(e(t).e("header"))},[E(s.$slots,"header",{},()=>[O("span",{role:"heading",class:C(e(t).e("title"))},ve(s.title),3)]),s.showClose?(I(),j("button",{key:0,"aria-label":e(i)("el.dialog.close"),class:C(e(t).e("headerbtn")),type:"button",onClick:d[0]||(d[0]=S=>s.$emit("close"))},[R(e(ue),{class:C(e(t).e("close"))},{default:k(()=>[(I(),q(ge(s.closeIcon||e(u))))]),_:1},8,["class"])],10,Le)):K("v-if",!0)],2),O("div",{id:e(F),class:C(e(t).e("body"))},[E(s.$slots,"default")],10,ze),s.$slots.footer?(I(),j("footer",{key:0,class:C(e(t).e("footer"))},[E(s.$slots,"footer")],2)):K("v-if",!0)],6))}});var je=X(Ue,[["__file","/home/runner/work/element-plus/element-plus/packages/components/dialog/src/dialog-content.vue"]]);const qe=W({...ee,appendToBody:{type:Boolean,default:!1},beforeClose:{type:ce(Function)},destroyOnClose:{type:Boolean,default:!1},closeOnClickModal:{type:Boolean,default:!0},closeOnPressEscape:{type:Boolean,default:!0},lockScroll:{type:Boolean,default:!0},modal:{type:Boolean,default:!0},openDelay:{type:Number,default:0},closeDelay:{type:Number,default:0},top:{type:String},modelValue:{type:Boolean,default:!1},modalClass:String,width:{type:[String,Number]},zIndex:{type:Number},trapFocus:{type:Boolean,default:!1}}),Ke={open:()=>!0,opened:()=>!0,close:()=>!0,closed:()=>!0,[Q]:o=>fe(o),openAutoFocus:()=>!0,closeAutoFocus:()=>!0},Ze=(o,a)=>{const u=ke().emit,{nextZIndex:c}=Oe();let p="";const F=Z(),t=Z(),n=D(!1),y=D(!1),f=D(!1),v=D(o.zIndex||c());let s,d;const S=Ce("namespace",Ee),M=B(()=>{const r={},h=`--${S.value}-dialog`;return o.fullscreen||(o.top&&(r[`${h}-margin-top`]=o.top),o.width&&(r[`${h}-width`]=me(o.width))),r}),L=B(()=>o.alignCenter?{display:"flex"}:{});function z(){u("opened")}function $(){u("closed"),u(Q,!1),o.destroyOnClose&&(f.value=!1)}function V(){u("close")}function P(){d==null||d(),s==null||s(),o.openDelay&&o.openDelay>0?{stop:s}=_(()=>m(),o.openDelay):m()}function T(){s==null||s(),d==null||d(),o.closeDelay&&o.closeDelay>0?{stop:d}=_(()=>A(),o.closeDelay):A()}function w(){function r(h){h||(y.value=!0,n.value=!1)}o.beforeClose?o.beforeClose(r):T()}function U(){o.closeOnClickModal&&w()}function m(){pe&&(n.value=!0)}function A(){n.value=!1}function l(){u("openAutoFocus")}function g(){u("closeAutoFocus")}function b(r){var h;((h=r.detail)==null?void 0:h.focusReason)==="pointer"&&r.preventDefault()}o.lockScroll&&ae(n);function oe(){o.closeOnPressEscape&&w()}return G(()=>o.modelValue,r=>{r?(y.value=!1,P(),f.value=!0,v.value=o.zIndex?v.value++:c(),be(()=>{u("open"),a.value&&(a.value.scrollTop=0)})):n.value&&T()}),G(()=>o.fullscreen,r=>{a.value&&(r?(p=a.value.style.transform,a.value.style.transform=""):a.value.style.transform=p)}),he(()=>{o.modelValue&&(n.value=!0,f.value=!0,P())}),{afterEnter:z,afterLeave:$,beforeLeave:V,handleClose:w,onModalClick:U,close:T,doClose:A,onOpenAutoFocus:l,onCloseAutoFocus:g,onCloseRequested:oe,onFocusoutPrevented:b,titleId:F,bodyId:t,closed:y,style:M,overlayDialogStyle:L,rendered:f,visible:n,zIndex:v}},_e=["aria-label","aria-labelledby","aria-describedby"],Je=N({name:"ElDialog",inheritAttrs:!1}),Ge=N({...Je,props:qe,emits:Ke,setup(o,{expose:a}){const i=o,u=De();H({scope:"el-dialog",from:"the title slot",replacement:"the header slot",version:"3.0.0",ref:"https://element-plus.org/en-US/component/dialog.html#slots"},B(()=>!!u.title)),H({scope:"el-dialog",from:"custom-class",replacement:"class",version:"2.3.0",ref:"https://element-plus.org/en-US/component/dialog.html#attributes",type:"Attribute"},B(()=>!!i.customClass));const c=Ie("dialog"),p=D(),F=D(),t=D(),{visible:n,titleId:y,bodyId:f,style:v,overlayDialogStyle:s,rendered:d,zIndex:S,afterEnter:M,afterLeave:L,beforeLeave:z,handleClose:$,onModalClick:V,onOpenAutoFocus:P,onCloseAutoFocus:T,onCloseRequested:w,onFocusoutPrevented:U}=Ze(i,p);Be(x,{dialogRef:p,headerRef:F,bodyId:f,ns:c,rendered:d,style:v});const m=ne(V),A=B(()=>i.draggable&&!i.fullscreen);return a({visible:n,dialogContentRef:t}),(l,g)=>(I(),q(Ae,{to:"body",disabled:!l.appendToBody},[R(se,{name:"dialog-fade",onAfterEnter:e(M),onAfterLeave:e(L),onBeforeLeave:e(z),persisted:""},{default:k(()=>[Fe(R(e(re),{"custom-mask-event":"",mask:l.modal,"overlay-class":l.modalClass,"z-index":e(S)},{default:k(()=>[O("div",{role:"dialog","aria-modal":"true","aria-label":l.title||void 0,"aria-labelledby":l.title?void 0:e(y),"aria-describedby":e(f),class:C(`${e(c).namespace.value}-overlay-dialog`),style:Y(e(s)),onClick:g[0]||(g[0]=(...b)=>e(m).onClick&&e(m).onClick(...b)),onMousedown:g[1]||(g[1]=(...b)=>e(m).onMousedown&&e(m).onMousedown(...b)),onMouseup:g[2]||(g[2]=(...b)=>e(m).onMouseup&&e(m).onMouseup(...b))},[R(e(Re),{loop:"",trapped:e(n),"focus-start-el":"container",onFocusAfterTrapped:e(P),onFocusAfterReleased:e(T),onFocusoutPrevented:e(U),onReleaseRequested:e(w)},{default:k(()=>[e(d)?(I(),q(je,Te({key:0,ref_key:"dialogContentRef",ref:t},l.$attrs,{"custom-class":l.customClass,center:l.center,"align-center":l.alignCenter,"close-icon":l.closeIcon,draggable:e(A),fullscreen:l.fullscreen,"show-close":l.showClose,title:l.title,onClose:e($)}),we({header:k(()=>[l.$slots.title?E(l.$slots,"title",{key:1}):E(l.$slots,"header",{key:0,close:e($),titleId:e(y),titleClass:e(c).e("title")})]),default:k(()=>[E(l.$slots,"default")]),_:2},[l.$slots.footer?{name:"footer",fn:k(()=>[E(l.$slots,"footer")])}:void 0]),1040,["custom-class","center","align-center","close-icon","draggable","fullscreen","show-close","title","onClose"])):K("v-if",!0)]),_:3},8,["trapped","onFocusAfterTrapped","onFocusAfterReleased","onFocusoutPrevented","onReleaseRequested"])],46,_e)]),_:3},8,["mask","overlay-class","z-index"]),[[le,e(n)]])]),_:3},8,["onAfterEnter","onAfterLeave","onBeforeLeave"])],8,["disabled"]))}});var He=X(Ge,[["__file","/home/runner/work/element-plus/element-plus/packages/components/dialog/src/dialog.vue"]]);const lo=Se(He);export{lo as E,Ke as a,Ne as c,qe as d,Ze as u};
