<?php $pageTitle='Invites - Zxt Super OJ'; require __DIR__.'/inc/header.php'; requireRole('super_admin');
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'&&isset($_POST['action'])){
 if($_POST['action']==='generate'){
  $code=generateInviteCode();$max=intval($_POST['max_uses']??1);$exp=!empty($_POST['expires_at'])?$_POST['expires_at']:null;
  $pdo->prepare("INSERT INTO invite_codes (code,created_by,max_uses,expires_at) VALUES (?,?,?,?)")->execute([$code,$currentUser['username'],$max,$exp]);
  $msg="<b>$code</b>";
 }elseif($_POST['action']==='deactivate'&&isset($_POST['code_id'])){
  $pdo->prepare("UPDATE invite_codes SET is_active=0 WHERE id=?")->execute([intval($_POST['code_id'])]);$msg='已失效';
 }
}
$codes=$pdo->query("SELECT * FROM invite_codes ORDER BY created_at DESC")->fetchAll();
?>
<style>
.row{display:flex;gap:12px;align-items:end;flex-wrap:wrap;margin-bottom:20px}
.row>div{flex:1}
input{width:100%;padding:8px 10px;background:#111;border:1px solid #333;color:#ccc;font-size:12px;font-family:inherit;outline:none}
input:focus{border-color:#999}
.btn{padding:8px 20px;background:#2a2a2a;color:#ccc;border:none;font-size:12px;cursor:pointer;font-family:inherit;letter-spacing:1px}
.btn:hover{background:#3a3a3a;color:#fff}
.btn-sm{padding:4px 12px;font-size:11px}
.copy-btn{padding:2px 10px;background:#222;color:#aaa;border:1px solid #333;font-size:10px;cursor:pointer;font-family:inherit;margin-left:6px}
.copy-btn:hover{background:#333;color:#fff}
table{width:100%;border-collapse:collapse;font-size:12px}
th,td{padding:8px 12px;text-align:left;border-bottom:1px solid #111}
th{color:#999;font-size:11px;font-weight:400;text-transform:uppercase;letter-spacing:1px}
.green{color:#0c0}.red{color:#c00}.yellow{color:#c90}
.code-cell{font-family:monospace;font-size:11px}
</style>
<h1 class="page-title">Invites</h1>
<?php if($msg):?><div style="padding:8px 12px;border:1px solid #0c0;color:#0c0;margin-bottom:16px;font-size:12px"><?=$msg?></div><?php endif?>

<form method="POST"><input type="hidden" name="action" value="generate">
<div class="row">
  <div><label style="font-size:11px;color:#999;display:block;margin-bottom:4px">最大使用次数</label><input type="number" name="max_uses" value="1" min="1"></div>
  <div><label style="font-size:11px;color:#999;display:block;margin-bottom:4px">过期时间 (optional)</label><input type="datetime-local" name="expires_at"></div>
  <div style="display:flex;align-items:end"><button class="btn">生成</button></div>
</div>
</form>

<table>
<tr><th>邀请码</th><th>创建者</th><th>状态</th><th>已用</th><th>过期时间</th><th></th></tr>
<?php foreach($codes as $c): $exp=$c['expires_at']&&strtotime($c['expires_at'])<time(); ?>
<tr>
  <td class="code-cell"><?=substr($c['code'],0,20)?>... <button class="copy-btn" onclick="copyCode('<?=$c['code']?>',this)">复制</button></td>
  <td><?= userBadge($c['created_by']) ?></td>
  <td class="<?=$c['is_active']?($exp?'yellow':'green'):'red'?>"><?=$c['is_active']?($exp?'已过期':'有效'):'失效D'?></td>
  <td><?=$c['use_count']?>/<?=$c['max_uses']?></td>
  <td><?=$c['expires_at']??'-'?></td>
  <td><?php if($c['is_active']&&!$exp):?><form method="POST" style="display:inline"><input type="hidden" name="action" value="deactivate"><input type="hidden" name="code_id" value="<?=$c['id']?>"><button class="btn-sm" style="background:#400;color:#c00;border:1px solid #600">失效</button></form><?php endif?></td>
</tr>
<?php endforeach?>
</table>
<script>
function copyCode(code,btn){
 navigator.clipboard.writeText(code).then(()=>{btn.textContent='已复制';setTimeout(()=>btn.textContent='复制',1500)});
}
</script>
<?php require __DIR__.'/inc/footer.php'; ?>
