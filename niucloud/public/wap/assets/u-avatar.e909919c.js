import{_ as A}from"./u-icon.45222ba8.js";import{O as e,P as t,Q as a,e as n,f as l,w as o,x as i,y as r,R as s,n as u,G as c,aJ as d,k as f,l as p,I as m,B as y,h as g,m as x,aK as h,ac as S,S as b,z as v,F as k,L as I}from"./index-b8ec63bc.js";import{_ as R}from"./_plugin-vue_export-helper.1b428a4d.js";const B=R({name:"u-link",mixins:[t,a,{props:{color:{type:String,default:e.link.color},fontSize:{type:[String,Number],default:e.link.fontSize},underLine:{type:Boolean,default:e.link.underLine},href:{type:String,default:e.link.href},mpTips:{type:String,default:e.link.mpTips},lineColor:{type:String,default:e.link.lineColor},text:{type:String,default:e.link.text}}}],computed:{linkStyle(){return{color:this.color,fontSize:uni.$u.addUnit(this.fontSize),lineHeight:uni.$u.addUnit(uni.$u.getPx(this.fontSize)+2),textDecoration:this.underLine?"underline":"none"}}},methods:{openLink(){window.open(this.href),this.$emit("click")}}},[["render",function(A,e,t,a,d,f){const p=c;return n(),l(p,{class:"u-link",onClick:s(f.openLink,["stop"]),style:u([f.linkStyle,A.$u.addStyle(A.customStyle)])},{default:o((()=>[i(r(A.text),1)])),_:1},8,["onClick","style"])}],["__scopeId","data-v-a339e5f1"]]),H={computed:{value(){const{text:A,mode:e,format:t,href:a}=this;return"price"===e?(/^\d+(\.\d+)?$/.test(A)||uni.$u.error("金额模式下，text参数需要为金额格式"),uni.$u.test.func(t)?t(A):uni.$u.priceFormat(A,2)):"date"===e?(!uni.$u.test.date(A)&&uni.$u.error("日期模式下，text参数需要为日期或时间戳格式"),uni.$u.test.func(t)?t(A):t?uni.$u.timeFormat(A,t):uni.$u.timeFormat(A,"yyyy-mm-dd")):"phone"===e?uni.$u.test.func(t)?t(A):"encrypt"===t?`${A.substr(0,3)}****${A.substr(7)}`:A:"name"===e?("string"!=typeof A&&uni.$u.error("姓名模式下，text参数需要为字符串格式"),uni.$u.test.func(t)?t(A):"encrypt"===t?this.formatName(A):A):"link"===e?(!uni.$u.test.url(a)&&uni.$u.error("超链接模式下，href参数需要为URL格式"),A):A}},methods:{formatName(A){let e="";if(2===A.length)e=A.substr(0,1)+"*";else if(A.length>2){let t="";for(let e=0,a=A.length-2;e<a;e++)t+="*";e=A.substr(0,1)+t+A.substr(-1,1)}else e=A;return e}}},N={props:{type:{type:String,default:e.text.type},show:{type:Boolean,default:e.text.show},text:{type:[String,Number],default:e.text.text},prefixIcon:{type:String,default:e.text.prefixIcon},suffixIcon:{type:String,default:e.text.suffixIcon},mode:{type:String,default:e.text.mode},href:{type:String,default:e.text.href},format:{type:[String,Function],default:e.text.format},call:{type:Boolean,default:e.text.call},openType:{type:String,default:e.text.openType},bold:{type:Boolean,default:e.text.bold},block:{type:Boolean,default:e.text.block},lines:{type:[String,Number],default:e.text.lines},color:{type:String,default:e.text.color},size:{type:[String,Number],default:e.text.size},iconStyle:{type:[Object,String],default:e.text.iconStyle},decoration:{tepe:String,default:e.text.decoration},margin:{type:[Object,String,Number],default:e.text.margin},lineHeight:{type:[String,Number],default:e.text.lineHeight},align:{type:String,default:e.text.align},wordWrap:{type:String,default:e.text.wordWrap}}};const w=R({name:"u--text",mixins:[t,a,N],components:{uvText:R({name:"u--text",mixins:[t,a,H,N],emits:["click"],computed:{valueStyle(){const A={textDecoration:this.decoration,fontWeight:this.bold?"bold":"normal",wordWrap:this.wordWrap,fontSize:uni.$u.addUnit(this.size)};return!this.type&&(A.color=this.color),this.isNvue&&this.lines&&(A.lines=this.lines),this.lineHeight&&(A.lineHeight=uni.$u.addUnit(this.lineHeight)),!this.isNvue&&this.block&&(A.display="block"),uni.$u.deepMerge(A,uni.$u.addStyle(this.customStyle))},isNvue:()=>!1,isMp:()=>!1},data:()=>({}),methods:{clickHandler(){this.call&&"phone"===this.mode&&d({phoneNumber:this.text}),this.$emit("click")}}},[["render",function(e,t,a,s,d,S){const b=c,v=f(p("u-icon"),A),k=x,I=f(p("u-link"),B),R=h;return e.show?(n(),l(k,{key:0,class:m(["u-text",[]]),style:u({margin:e.margin,justifyContent:"left"===e.align?"flex-start":"center"===e.align?"center":"flex-end"}),onClick:S.clickHandler},{default:o((()=>["price"===e.mode?(n(),l(b,{key:0,class:m(["u-text__price",e.type&&`u-text__value--${e.type}`]),style:u([S.valueStyle])},{default:o((()=>[i("￥")])),_:1},8,["class","style"])):y("",!0),e.prefixIcon?(n(),l(k,{key:1,class:"u-text__prefix-icon"},{default:o((()=>[g(v,{name:e.prefixIcon,customStyle:e.$u.addStyle(e.iconStyle)},null,8,["name","customStyle"])])),_:1})):y("",!0),"link"===e.mode?(n(),l(I,{key:2,text:e.value,href:e.href,underLine:""},null,8,["text","href"])):e.openType&&S.isMp?(n(),l(R,{key:3,class:"u-reset-button u-text__value",style:u([S.valueStyle]),"data-index":e.index,openType:e.openType,onGetuserinfo:e.onGetUserInfo,onContact:e.onContact,onGetphonenumber:e.onGetPhoneNumber,onError:e.onError,onLaunchapp:e.onLaunchApp,onOpensetting:e.onOpenSetting,lang:e.lang,"session-from":e.sessionFrom,"send-message-title":e.sendMessageTitle,"send-message-path":e.sendMessagePath,"send-message-img":e.sendMessageImg,"show-message-card":e.showMessageCard,"app-parameter":e.appParameter},{default:o((()=>[i(r(e.value),1)])),_:1},8,["style","data-index","openType","onGetuserinfo","onContact","onGetphonenumber","onError","onLaunchapp","onOpensetting","lang","session-from","send-message-title","send-message-path","send-message-img","show-message-card","app-parameter"])):(n(),l(b,{key:4,class:m(["u-text__value",[e.type&&`u-text__value--${e.type}`,e.lines&&`u-line-${e.lines}`]]),style:u([S.valueStyle])},{default:o((()=>[i(r(e.value),1)])),_:1},8,["style","class"])),e.suffixIcon?(n(),l(k,{key:5,class:"u-text__suffix-icon"},{default:o((()=>[g(v,{name:e.suffixIcon,customStyle:e.$u.addStyle(e.iconStyle)},null,8,["name","customStyle"])])),_:1})):y("",!0)])),_:1},8,["style","onClick"])):y("",!0)}],["__scopeId","data-v-b9da4249"]])}},[["render",function(A,e,t,a,o,i){const r=S("uvText");return n(),l(r,{type:A.type,show:A.show,text:A.text,prefixIcon:A.prefixIcon,suffixIcon:A.suffixIcon,mode:A.mode,href:A.href,format:A.format,call:A.call,openType:A.openType,bold:A.bold,block:A.block,lines:A.lines,color:A.color,decoration:A.decoration,size:A.size,iconStyle:A.iconStyle,margin:A.margin,lineHeight:A.lineHeight,align:A.align,wordWrap:A.wordWrap,customStyle:A.customStyle},null,8,["type","show","text","prefixIcon","suffixIcon","mode","href","format","call","openType","bold","block","lines","color","decoration","size","iconStyle","margin","lineHeight","align","wordWrap","customStyle"])}]]),G={props:{src:{type:String,default:e.avatar.src},shape:{type:String,default:e.avatar.shape},size:{type:[String,Number],default:e.avatar.size},mode:{type:String,default:e.avatar.mode},text:{type:String,default:e.avatar.text},bgColor:{type:String,default:e.avatar.bgColor},color:{type:String,default:e.avatar.color},fontSize:{type:[String,Number],default:e.avatar.fontSize},icon:{type:String,default:e.avatar.icon},mpAvatar:{type:Boolean,default:e.avatar.mpAvatar},randomBgColor:{type:Boolean,default:e.avatar.randomBgColor},defaultUrl:{type:String,default:e.avatar.defaultUrl},colorIndex:{type:[String,Number],validator:A=>uni.$u.test.range(A,[0,19])||""===A,default:e.avatar.colorIndex},name:{type:String,default:e.avatar.name}}};const M=R({name:"u-avatar",mixins:[t,a,G],data(){return{colors:["#ffb34b","#f2bba9","#f7a196","#f18080","#88a867","#bfbf39","#89c152","#94d554","#f19ec2","#afaae4","#e1b0df","#c38cc1","#72dcdc","#9acdcb","#77b1cc","#448aca","#86cefa","#98d1ee","#73d1f1","#80a7dc"],avatarUrl:this.src,allowMp:!1}},watch:{src:{immediate:!0,handler(A){this.avatarUrl=A,A||this.errorHandler()}}},computed:{imageStyle:()=>({})},created(){this.init()},methods:{init(){},isImg(){return-1!==this.src.indexOf("/")},errorHandler(){this.avatarUrl=this.defaultUrl||"data:image/jpg;base64,/9j/4QAYRXhpZgAASUkqAAgAAAAAAAAAAAAAAP/sABFEdWNreQABAAQAAAA8AAD/4QMraHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLwA8P3hwYWNrZXQgYmVnaW49Iu+7vyIgaWQ9Ilc1TTBNcENlaGlIenJlU3pOVGN6a2M5ZCI/PiA8eDp4bXBtZXRhIHhtbG5zOng9ImFkb2JlOm5zOm1ldGEvIiB4OnhtcHRrPSJBZG9iZSBYTVAgQ29yZSA1LjMtYzAxMSA2Ni4xNDU2NjEsIDIwMTIvMDIvMDYtMTQ6NTY6MjcgICAgICAgICI+IDxyZGY6UkRGIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyI+IDxyZGY6RGVzY3JpcHRpb24gcmRmOmFib3V0PSIiIHhtbG5zOnhtcD0iaHR0cDovL25zLmFkb2JlLmNvbS94YXAvMS4wLyIgeG1sbnM6eG1wTU09Imh0dHA6Ly9ucy5hZG9iZS5jb20veGFwLzEuMC9tbS8iIHhtbG5zOnN0UmVmPSJodHRwOi8vbnMuYWRvYmUuY29tL3hhcC8xLjAvc1R5cGUvUmVzb3VyY2VSZWYjIiB4bXA6Q3JlYXRvclRvb2w9IkFkb2JlIFBob3Rvc2hvcCBDUzYgKFdpbmRvd3MpIiB4bXBNTTpJbnN0YW5jZUlEPSJ4bXAuaWlkOjREMEQwRkY0RjgwNDExRUE5OTY2RDgxODY3NkJFODMxIiB4bXBNTTpEb2N1bWVudElEPSJ4bXAuZGlkOjREMEQwRkY1RjgwNDExRUE5OTY2RDgxODY3NkJFODMxIj4gPHhtcE1NOkRlcml2ZWRGcm9tIHN0UmVmOmluc3RhbmNlSUQ9InhtcC5paWQ6NEQwRDBGRjJGODA0MTFFQTk5NjZEODE4Njc2QkU4MzEiIHN0UmVmOmRvY3VtZW50SUQ9InhtcC5kaWQ6NEQwRDBGRjNGODA0MTFFQTk5NjZEODE4Njc2QkU4MzEiLz4gPC9yZGY6RGVzY3JpcHRpb24+IDwvcmRmOlJERj4gPC94OnhtcG1ldGE+IDw/eHBhY2tldCBlbmQ9InIiPz7/7gAOQWRvYmUAZMAAAAAB/9sAhAAGBAQEBQQGBQUGCQYFBgkLCAYG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"},clickHandler(){this.$emit("click",this.name)}}},[["render",function(e,t,a,i,r,s){const c=f(p("u-icon"),A),d=f(p("u--text"),w),y=I,g=x;return n(),l(g,{class:m(["u-avatar",[`u-avatar--${e.shape}`]]),style:u([{backgroundColor:e.text||e.icon?e.randomBgColor?r.colors[""!==e.colorIndex?e.colorIndex:e.$u.random(0,19)]:e.bgColor:"transparent",width:e.$u.addUnit(e.size),height:e.$u.addUnit(e.size)},e.$u.addStyle(e.customStyle)]),onClick:s.clickHandler},{default:o((()=>[b(e.$slots,"default",{},(()=>[e.mpAvatar&&r.allowMp?(n(),v(k,{key:0},[],64)):e.icon?(n(),l(c,{key:1,name:e.icon,size:e.fontSize,color:e.color},null,8,["name","size","color"])):e.text?(n(),l(d,{key:2,text:e.text,size:e.fontSize,color:e.color,align:"center",customStyle:"justify-content: center"},null,8,["text","size","color"])):(n(),l(y,{key:3,class:m(["u-avatar__image",[`u-avatar__image--${e.shape}`]]),src:r.avatarUrl||e.defaultUrl,mode:e.mode,onError:s.errorHandler,style:u([{width:e.$u.addUnit(e.size),height:e.$u.addUnit(e.size)}])},null,8,["class","src","mode","onError","style"]))]),!0)])),_:3},8,["class","style","onClick"])}],["__scopeId","data-v-03b4cb4d"]]);export{M as _};
