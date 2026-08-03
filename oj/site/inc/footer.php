<script src="assets/spark-md5.min.js"></script>
<script src="assets/highlight.min.js"></script>
<script src="assets/highlight-line-numbers.min.js"></script>
<script>document.querySelectorAll('pre code').forEach(el=>{hljs.highlightElement(el);hljs.lineNumbersBlock(el.parentElement)})</script>
<script>
function copyCode(el){
 const c=el.closest('div, .sample-box, .code-block');
 let pre=c?c.querySelector('pre'):null;
 if(!pre&&el.parentElement&&el.parentElement.parentElement)pre=el.parentElement.parentElement.querySelector('pre');
 if(!pre)return;
 const text=pre.textContent;
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
