<script src="assets/spark-md5.min.js"></script>
<script src="assets/highlight.min.js"></script>
<script src="assets/highlight-line-numbers.min.js"></script>
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
