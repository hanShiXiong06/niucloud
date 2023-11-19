import{bf as e,bg as t,bh as a,A as i,B as s,C as l,b5 as u,q as o,t as d,i as r,j as n,w as c,k as p,P as m,R as f,Q as h,p as _,G as y,H as g,m as b,E as v,F as w,D as k,W as x,I as z,x as I}from"./index-c8487b3e.js";import{_ as $}from"./u-icon.e1505caa.js";import{_ as j}from"./u-loading-icon.92d47c06.js";import{_ as C}from"./_plugin-vue_export-helper.1b428a4d.js";function F(e,t){return["[object Object]","[object File]"].includes(Object.prototype.toString.call(e))?Object.keys(e).reduce(((a,i)=>(t.includes(i)||(a[i]=e[i]),a)),{}):{}}function S(e){return e.tempFiles.map((e=>({...F(e,["path"]),url:e.path,size:e.size,name:e.name,type:e.type})))}function R({accept:i,multiple:s,capture:l,compressed:u,maxDuration:o,sizeType:d,camera:r,maxCount:n}){return new Promise(((c,p)=>{switch(i){case"image":a({count:s?Math.min(n,9):1,sourceType:l,sizeType:d,success:e=>c(function(e){return e.tempFiles.map((e=>({...F(e,["path"]),type:"image",url:e.path,thumb:e.path,size:e.size,name:e.name})))}(e)),fail:p});break;case"video":t({sourceType:l,compressed:u,maxDuration:o,camera:r,success:e=>c(function(e){return[{...F(e,["tempFilePath","thumbTempFilePath","errMsg"]),type:"video",url:e.tempFilePath,thumb:e.thumbTempFilePath,size:e.size,name:e.name}]}(e)),fail:p});break;case"file":e({count:s?n:1,type:i,success:e=>c(S(e)),fail:p});break;default:e({count:s?n:1,type:"all",success:e=>c(S(e)),fail:p})}}))}const O=C({name:"u-upload",mixins:[s,l,{watch:{accept:{immediate:!0,handler(e){"all"!==e&&"media"!==e||uni.$u.error("只有微信小程序才支持把accept配置为all、media之一")}}}},{props:{accept:{type:String,default:i.upload.accept},capture:{type:[String,Array],default:i.upload.capture},compressed:{type:Boolean,default:i.upload.compressed},camera:{type:String,default:i.upload.camera},maxDuration:{type:Number,default:i.upload.maxDuration},uploadIcon:{type:String,default:i.upload.uploadIcon},uploadIconColor:{type:String,default:i.upload.uploadIconColor},useBeforeRead:{type:Boolean,default:i.upload.useBeforeRead},afterRead:{type:Function,default:null},beforeRead:{type:Function,default:null},previewFullImage:{type:Boolean,default:i.upload.previewFullImage},maxCount:{type:[String,Number],default:i.upload.maxCount},disabled:{type:Boolean,default:i.upload.disabled},imageMode:{type:String,default:i.upload.imageMode},name:{type:String,default:i.upload.name},sizeType:{type:Array,default:i.upload.sizeType},multiple:{type:Boolean,default:i.upload.multiple},deletable:{type:Boolean,default:i.upload.deletable},maxSize:{type:[String,Number],default:i.upload.maxSize},fileList:{type:Array,default:i.upload.fileList},uploadText:{type:String,default:i.upload.uploadText},width:{type:[String,Number],default:i.upload.width},height:{type:[String,Number],default:i.upload.height},previewImage:{type:Boolean,default:i.upload.previewImage}}}],data:()=>({lists:[],isInCount:!0}),watch:{fileList:{handler(){this.formatFileList()},immediate:!0,deep:!0}},emits:["error","beforeRead","oversize","afterRead","delete","clickPreview"],methods:{formatFileList(){const{fileList:e=[],maxCount:t}=this,a=e.map((e=>Object.assign(Object.assign({},e),{isImage:"image"===this.accept||uni.$u.test.image(e.url||e.thumb),isVideo:"video"===this.accept||uni.$u.test.video(e.url||e.thumb),deletable:"boolean"==typeof e.deletable?e.deletable:this.deletable})));this.lists=a,this.isInCount=a.length<t},chooseFile(){const{maxCount:e,multiple:t,lists:a,disabled:i}=this;if(i)return;let s;try{s=uni.$u.test.array(this.capture)?this.capture:this.capture.split(",")}catch(l){s=[]}R(Object.assign({accept:this.accept,multiple:this.multiple,capture:s,compressed:this.compressed,maxDuration:this.maxDuration,sizeType:this.sizeType,camera:this.camera},{maxCount:e-a.length})).then((e=>{this.onBeforeRead(t?e:e[0])})).catch((e=>{this.$emit("error",e)}))},onBeforeRead(e){const{beforeRead:t,useBeforeRead:a}=this;let i=!0;uni.$u.test.func(t)&&(i=t(e,this.getDetail())),a&&(i=new Promise(((t,a)=>{this.$emit("beforeRead",Object.assign(Object.assign({file:e},this.getDetail()),{callback:e=>{e?t():a()}}))}))),i&&(uni.$u.test.promise(i)?i.then((t=>this.onAfterRead(t||e))):this.onAfterRead(e))},getDetail(e){return{name:this.name,index:null==e?this.fileList.length:e}},onAfterRead(e){const{maxSize:t,afterRead:a}=this;(Array.isArray(e)?e.some((e=>e.size>t)):e.size>t)?this.$emit("oversize",Object.assign({file:e},this.getDetail())):("function"==typeof a&&a(e,this.getDetail()),this.$emit("afterRead",Object.assign({file:e},this.getDetail())))},deleteItem(e){this.$emit("delete",Object.assign(Object.assign({},this.getDetail(e)),{file:this.fileList[e]}))},onPreviewImage(e){e.isImage&&this.previewFullImage&&u({urls:this.lists.filter((e=>"image"===this.accept||uni.$u.test.image(e.url||e.thumb))).map((e=>e.url||e.thumb)),current:e.url||e.thumb,fail(){uni.$u.toast("预览图片失败")}})},onPreviewVideo(e){if(!this.data.previewFullImage)return;const{index:t}=e.currentTarget.dataset,{lists:a}=this.data;wx.previewMedia({sources:a.filter((e=>isVideoFile(e))).map((e=>Object.assign(Object.assign({},e),{type:"video"}))),current:t,fail(){uni.$u.toast("预览视频失败")}})},onClickPreview(e){const{index:t}=e.currentTarget.dataset,a=this.data.lists[t];this.$emit("clickPreview",Object.assign(Object.assign({},a),this.getDetail(t)))}}},[["render",function(e,t,a,i,s,l){const u=x,C=o(d("u-icon"),$),F=z,S=I,R=o(d("u-loading-icon"),j);return r(),n(S,{class:"u-upload",style:_([e.$u.addStyle(e.customStyle)])},{default:c((()=>[p(S,{class:"u-upload__wrap"},{default:c((()=>[e.previewImage?(r(!0),m(f,{key:0},h(s.lists,((t,a)=>(r(),n(S,{class:"u-upload__wrap__preview",key:a},{default:c((()=>[t.isImage||t.type&&"image"===t.type?(r(),n(u,{key:0,src:t.thumb||t.url,mode:e.imageMode,class:"u-upload__wrap__preview__image",onClick:e=>l.onPreviewImage(t),style:_([{width:e.$u.addUnit(e.width),height:e.$u.addUnit(e.height)}])},null,8,["src","mode","onClick","style"])):(r(),n(S,{key:1,class:"u-upload__wrap__preview__other"},{default:c((()=>[p(C,{color:"#80CBF9",size:"26",name:t.isVideo||t.type&&"video"===t.type?"movie":"folder"},null,8,["name"]),p(F,{class:"u-upload__wrap__preview__other__text"},{default:c((()=>[y(g(t.isVideo||t.type&&"video"===t.type?"视频":"文件"),1)])),_:2},1024)])),_:2},1024)),"uploading"===t.status||"failed"===t.status?(r(),n(S,{key:2,class:"u-upload__status"},{default:c((()=>[p(S,{class:"u-upload__status__icon"},{default:c((()=>["failed"===t.status?(r(),n(C,{key:0,name:"close-circle",color:"#ffffff",size:"25"})):(r(),n(R,{key:1,size:"22",mode:"circle",color:"#ffffff"}))])),_:2},1024),t.message?(r(),n(F,{key:0,class:"u-upload__status__message"},{default:c((()=>[y(g(t.message),1)])),_:2},1024)):b("v-if",!0)])),_:2},1024)):b("v-if",!0),"uploading"!==t.status&&(e.deletable||t.deletable)?(r(),n(S,{key:3,class:"u-upload__deletable",onClick:v((e=>l.deleteItem(a)),["stop"])},{default:c((()=>[p(S,{class:"u-upload__deletable__icon"},{default:c((()=>[p(C,{name:"close",color:"#ffffff",size:"10"})])),_:1})])),_:2},1032,["onClick"])):b("v-if",!0),"success"===t.status?(r(),n(S,{key:4,class:"u-upload__success"},{default:c((()=>[p(S,{class:"u-upload__success__icon"},{default:c((()=>[p(C,{name:"checkmark",color:"#ffffff",size:"12"})])),_:1})])),_:1})):b("v-if",!0)])),_:2},1024)))),128)):b("v-if",!0),s.isInCount?(r(),m(f,{key:1},[e.$slots.default||e.$slots.$default?(r(),n(S,{key:0,onClick:l.chooseFile},{default:c((()=>[w(e.$slots,"default",{},void 0,!0)])),_:3},8,["onClick"])):(r(),n(S,{key:1,class:k(["u-upload__button",[e.disabled&&"u-upload__button--disabled"]]),"hover-class":e.disabled?"":"u-upload__button--hover","hover-stay-time":"150",onClick:l.chooseFile,style:_([{width:e.$u.addUnit(e.width),height:e.$u.addUnit(e.height)}])},{default:c((()=>[p(C,{name:e.uploadIcon,size:"26",color:e.uploadIconColor},null,8,["name","color"]),e.uploadText?(r(),n(F,{key:0,class:"u-upload__button__text"},{default:c((()=>[y(g(e.uploadText),1)])),_:1})):b("v-if",!0)])),_:1},8,["hover-class","onClick","class","style"]))],64)):b("v-if",!0)])),_:3})])),_:3},8,["style"])}],["__scopeId","data-v-1941ac11"]]);export{O as _};
