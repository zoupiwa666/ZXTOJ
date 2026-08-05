<script src="assets/spark-md5.min.js"></script>
<script src="assets/highlight.min.js"></script>
<script src="assets/highlight-line-numbers.min.js"></script>
<script>
// ===== 用户名片悬停卡片（头像+名字+格言，点击进主页）=====
(function(){
  var card=null, timer=null;
  function ensureCard(){
    if(card) return card;
    card=document.createElement('div'); card.className='ucard'; card.style.display='none';
    card.addEventListener('click',function(){ if(card.dataset.url) location.href=card.dataset.url; });
    document.body.appendChild(card); return card;
  }
  function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
  function showCard(a){
    var name='';
    try{ name=decodeURIComponent(new URL(a.href).searchParams.get('name')||''); }catch(e){}
    if(!name) return;
    clearTimeout(timer);
    timer=setTimeout(function(){
      fetch('api/user_card.php?name='+encodeURIComponent(name)).then(function(r){return r.json();}).then(function(d){
        var c=ensureCard();
        if(!d||!d.ok){ c.style.display='none'; return; }
        var ch=(d.username||'?')[0].toUpperCase();
        var av=d.avatar
          ? '<img class="uc-avatar" src="'+esc(d.avatar)+'">'
          : '<span class="uc-avatar" style="display:flex;align-items:center;justify-content:center;color:#5af;font-size:17px;font-weight:700">'+esc(ch)+'</span>';
        var role=d.role==='super_admin'?'<span class="uc-role">SA</span>':(d.role==='admin'?'<span class="uc-role">AD</span>':'');
        var color=(d.role==='super_admin'||d.role==='admin')?'#a855f7':'#b0815a';
        var tagHtml=d.tag?'<span style="background:'+color+';color:#fff;font-size:9px;padding:0 5px;border-radius:3px;margin-left:5px;vertical-align:middle">'+esc(d.tag)+'</span>':'';
        c.innerHTML='<div class="uc-top">'+av+'<div class="uc-name" style="color:'+color+'">'+esc(d.username)+role+tagHtml+'</div></div>'
          +'<div class="uc-motto">'+(d.motto?esc(d.motto):'这个人很懒，什么都没写~')+'</div>'
          +'<div class="uc-tip">点击查看主页 →</div>';
        c.dataset.url=a.href;
        c.style.display='block';
        var r=a.getBoundingClientRect();
        c.classList.add('show');
        var cw=c.offsetWidth, chh=c.offsetHeight;
        var x=Math.max(4,Math.min(r.left, window.innerWidth-cw-8));
        var y=r.bottom+2;
        if(y+chh>window.innerHeight-8) y=r.top-chh-2;
        c.style.left=x+'px'; c.style.top=y+'px';
      }).catch(function(){});
    },300);
  }
  function hideCard(){
    clearTimeout(timer);
    if(!card) return;
    setTimeout(function(){ if(card){ card.classList.remove('show'); card.style.display='none'; } },250);
  }
  // 立即隐藏（滚轮触发，事件一出现就消失）
  function hideCardNow(){
    clearTimeout(timer);
    if(card){ card.classList.remove('show'); card.style.display='none'; }
  }
  document.addEventListener('mouseover',function(e){
    var a=e.target.closest?e.target.closest('a[href*="user.php?name="]'):null;
    if(a){ showCard(a); }
    else if(!e.target.closest||!e.target.closest('.ucard')){ hideCard(); }
  });
  // 鼠标滚轮滚动时立即隐藏名片
  document.addEventListener('wheel',function(){ hideCardNow(); },{passive:true});
})();
</script>
<script>
// 自动把带 placeholder 的输入框升级为浮动标签样式（OJ 风格）
document.addEventListener('DOMContentLoaded',function(){
  document.querySelectorAll('input[placeholder], textarea[placeholder]').forEach(function(el){
    var t=(el.type||'').toLowerCase();
    if(['hidden','checkbox','radio','file','submit','button','color','range','date','time'].indexOf(t)>=0) return;
    var par=el.parentElement;
    if(!par||par.querySelector('.float-label')) return;
    // 已有独立 <label> 的输入框跳过（如筛选栏），避免文字重复
    var hasLabel = par.querySelector('label') || (par.previousElementSibling && par.previousElementSibling.tagName==='LABEL');
    if(hasLabel) return;
    var ph=el.getAttribute('placeholder')||'';
    if(!ph) return;
    var wrap=document.createElement('div');
    wrap.className='float-wrap'+(getComputedStyle(par).display==='flex'?' in-flex':'');
    // 标签字号与输入框高度成固定比例：无下限，但有上限（不挡输入文字）
    var ih = el.offsetHeight || 34;
    wrap.style.setProperty('--flabel', Math.max(1, Math.min(Math.round(ih*0.26), 18)) + 'px');
    wrap.style.setProperty('--flabel-up', Math.max(1, Math.min(Math.round(ih*0.15), 12)) + 'px');
    par.insertBefore(wrap,el);
    wrap.appendChild(el);
    var lab=document.createElement('label');
    lab.className='float-label';
    lab.textContent=ph;
    wrap.appendChild(lab);
    el.removeAttribute('placeholder');
    if(el.value) wrap.classList.add('filled');
    el.addEventListener('input',function(){wrap.classList.toggle('filled',!!el.value);});
    el.addEventListener('focus',function(){wrap.classList.add('focused');});
    el.addEventListener('blur',function(){wrap.classList.remove('focused');});
  });
});
</script>
<script>
// 可复用的代码高亮函数：高亮范围内所有带语言的代码块（含动态渲染的，如聊天）
function highlightCodeBlocks(scope){
 scope=(scope&&scope.querySelectorAll)?scope:document;
 scope.querySelectorAll('pre code').forEach(el=>{
  if(!el.classList.contains('hljs')){
   hljs.highlightElement(el);
   if(hljs.lineNumbersBlock)hljs.lineNumbersBlock(el.parentElement);
  }
 });
}
document.addEventListener('DOMContentLoaded',function(){highlightCodeBlocks(document)});
</script>
<script>
function copyCode(el){
 const c=el.closest('div, .sample-box, .code-block');
 let pre=c?c.querySelector('pre'):null;
 if(!pre&&el.parentElement&&el.parentElement.parentElement)pre=el.parentElement.parentElement.querySelector('pre');
 if(!pre)return;
 // 行号插件会把代码重构成表格，直接 textContent 会丢换行并混入行号
 const ln=pre.querySelector('.hljs-ln');
 let text;
 if(ln){
  text=[].map.call(ln.querySelectorAll('.hljs-ln-code'),function(cell){return cell.textContent}).join('\n');
 }else{
  text=pre.textContent;
 }
 if(navigator.clipboard&&window.isSecureContext){
  navigator.clipboard.writeText(text).then(()=>done(el));
 }else{
  const ta=document.createElement('textarea');
  ta.value=text;ta.style.position='fixed';ta.style.left='-9999px';
  document.body.appendChild(ta);ta.select();
  document.execCommand('copy');document.body.removeChild(ta);
  done(el);
 }
}
function done(el){
 el.textContent='已复制';el.classList.add('copy-done');
 setTimeout(()=>{el.textContent='复制';el.classList.remove('copy-done')},1500);
}
</script>
</div></div>
</body>
</html>
