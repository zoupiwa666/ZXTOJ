<?php
require __DIR__ . '/inc/config.php';
require_once __DIR__ . '/inc/article_tables.php';
require __DIR__ . '/inc/auth.php';
requireLogin();

$pid = $_GET['id'] ?? '';
$s = $pdo->prepare("SELECT * FROM problems WHERE problem_id = ?");
$s->execute([$pid]); $problem = $s->fetch();
if (!$problem) die("题目不存在");
if ($problem['visibility'] === 'hidden') {
    if (!canViewProblem($pdo, $problem, currentUser()['username'], currentUser()['role'])) {
        die("题目未开放");
    }
}

// 样例
$s = $pdo->prepare("SELECT * FROM problem_samples WHERE problem_id = ? ORDER BY sort_order");
$s->execute([$pid]); $samples = $s->fetchAll();

// 测试数据检查
$s = $pdo->prepare("SELECT COUNT(*) FROM problem_testcases WHERE problem_id = ?");
$s->execute([$pid]); $hasData = $s->fetchColumn() > 0;

// 用户提交状态
$uname = currentUser()['username'];
$lastSub = null; $acSub = null;
$s = $pdo->prepare("SELECT * FROM submissions WHERE username = ? AND problem_id = ? ORDER BY id DESC LIMIT 1");
$s->execute([$uname, $pid]); $lastSub = $s->fetch();
$s = $pdo->prepare("SELECT id FROM submissions WHERE username = ? AND problem_id = ? AND status='AC' LIMIT 1");
$s->execute([$uname, $pid]); $acSub = $s->fetch();

$pageTitle = $problem['title'] . ' - Zxt Super OJ';
$colorMap = ['AC'=>'#25ad40','WA'=>'#ff4f4f','TLE'=>'#ffab00','RE'=>'#f8603a','MLE'=>'#d500f9','OLE'=>'#0091ea','CE'=>'#ff9100','SE'=>'#999','judging'=>'#09f','waiting'=>'#666'];
require __DIR__ . '/inc/header.php';

// 查找该题已审核通过的题解
$sol = null;
try {
    $ss = $pdo->prepare("SELECT id, title, author FROM articles WHERE solution_problem=? AND is_solution=1 AND solution_status='approved' ORDER BY id DESC LIMIT 1");
    $ss->execute([$pid]); $sol = $ss->fetch();
} catch (Exception $e) {}
?>
<style>
.problem-layout{max-width:800px;margin:0 auto}
.problem-header{margin-bottom:24px}
.pid-tag{font-size:11px;color:#888;border:1px solid #2a2a2a;padding:2px 10px;margin-right:8px}
.problem-header h1{font-size:22px;color:#fff;font-weight:400;margin:8px 0;letter-spacing:0}
.problem-header .meta{font-size:12px;color:#888;margin-bottom:12px;display:flex;gap:16px;align-items:center}
.problem-header .meta a{color:#ddd;text-decoration:none;margin-left:auto;padding:8px 24px;background:#2a2a2a;font-size:13px;letter-spacing:1px}
.problem-header .meta a:hover{background:#3a3a3a;color:#fff}
.last-status{display:inline-flex;align-items:center;gap:6px;padding:4px 12px;border:1px solid #2a2a2a;font-size:11px;font-weight:600;text-decoration:none}
.last-status a{color:inherit!important;text-decoration:none;background:none!important;padding:0!important;margin:0!important}
.sec{margin:20px 0}
.sec h2{font-size:16px;color:#fff;font-weight:500;margin-bottom:12px;padding-bottom:6px;border-bottom:1px solid #333;letter-spacing:0}
.sec .md{font-size:13px;color:#bbb;line-height:1.8}
.sample-row{display:flex;gap:10px;margin-bottom:8px}
.sample-box{flex:1;background:#141414;border:1px solid #222;padding:12px;position:relative}
.sample-box h3{font-size:10px;color:#888;margin-bottom:4px;text-transform:uppercase;letter-spacing:1px}
.sample-box pre{font-family:Consolas,'Courier New',monospace;font-size:12px;color:#bbb;white-space:pre-wrap;word-break:break-all;line-height:1.5}
.copy-btn{position:absolute;top:4px;right:4px;padding:2px 10px;background:#1a3a5c;color:#5af;border:1px solid #2a5a8c;font-size:10px;cursor:pointer;opacity:0;transition:opacity .15s;font-family:inherit}
.sample-box:hover .copy-btn{opacity:1}
.copy-btn:hover{background:#2a4a7c;color:#8cf}
.copy-done{color:#0c0!important;border-color:#0c0!important}

</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.css">
<style>
.katex .mathcal{font-family:KaTeX_Caligraphic;font-weight:400}
.katex .mathscr,.katex .textscr{font-family:KaTeX_Caligraphic;font-weight:700}
</style>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/katex.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/katex@0.16.21/dist/contrib/auto-render.min.js"></script>
<script src="assets/marked.min.js"></script>

<div class="problem-layout">
  <div class="problem-header card">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
      <span class="pid-tag"><?=$pid?></span>
      <?php if ($acSub): ?>
      <a href="submission.php?id=<?=$acSub['id']?>" title="查看 AC 提交记录" class="last-status" style="color:#25ad40;border-color:#25ad40">AC</a>
      <?php elseif ($lastSub): $st = $lastSub['status']; $color = $colorMap[$st] ?? '#999'; ?>
      <a href="submission.php?id=<?=$lastSub['id']?>" title="查看提交记录" class="last-status" style="color:<?=$color?>;border-color:<?=$color?>"><?=$st?> <?=intval($lastSub['score'])?></a>
      <?php endif; ?>
      <h1 style="font-size:22px;color:#fff;font-weight:400;margin:0;display:flex;align-items:center;flex-wrap:wrap;gap:10px"><?=htmlspecialchars($problem['title'])?>
  <a href="solutions.php?problem=<?=urlencode($pid)?>" style="display:inline-flex;align-items:center;background:#1a3a5c;color:#5af;border:1px solid #2a5a8c;padding:5px 16px;font-size:13px;font-weight:600;letter-spacing:1px;text-decoration:none"><i class="fa-solid fa-book-open"></i> 题解<?= $sol ? '' : '' ?></a>
</h1>
    </div>
    <div class="meta" style="margin-top:10px">
      <span>时限: <?=$problem['time_limit']?>s</span>
      <span>内存: <?=$problem['memory_limit']?>MB</span>
      <span>创建者: <?= creator_display($problem['created_by'] ?? null, 16) ?></span>
      <?php if (isAdmin()): ?><a href="edit.php?id=<?=$pid?>" style="color:#888;text-decoration:none;margin-left:auto;font-size:11px;border:1px solid #333;padding:2px 10px">编辑</a><?php endif; ?>
    </div>
  </div>
  <?php if ($hasData): ?>
  <div style="margin:12px 0;display:flex;gap:8px;flex-wrap:wrap">
    <?php $li = isset($_GET["list"]) ? "&list=".intval($_GET["list"]) : ""; ?><a href="submit.php?id=<?=$pid?><?=$li?>" style="display:inline-block;padding:8px 24px;background:#2a2a2a;color:#ccc;text-decoration:none;font-size:12px;letter-spacing:1px">提交</a>
    <a href="stats.php?problem_id=<?=$pid?>" style="display:inline-block;padding:8px 24px;background:#1a3a5c;color:#5af;text-decoration:none;font-size:12px;letter-spacing:1px;border:1px solid #2a5a8c"><i class="fa-solid fa-chart-bar"></i> 统计</a>
  </div>
  <?php endif; ?>

  <?php foreach(['background'=>'题目背景','description'=>'题目描述','input_format'=>'输入格式','output_format'=>'输出格式'] as $k=>$lb):
    if(!empty($problem[$k])):?><div class="sec"><h2><?=$lb?></h2><div class="md"><?=htmlspecialchars($problem[$k])?></div></div><?php endif;endforeach?>

  <?php if($samples):?><div class="sec"><h2>样例</h2>
  <?php foreach($samples as $i=>$s):?><div class="sample-row">
    <div class="sample-box"><button class="copy-btn" onclick="copyCode(this)">复制</button><h3>输入 #<?=$i+1?></h3><pre><?=htmlspecialchars($s['input_text']?:'(空)')?></pre></div>
    <div class="sample-box"><button class="copy-btn" onclick="copyCode(this)">复制</button><h3>输出 #<?=$i+1?></h3><pre><?=htmlspecialchars($s['output_text']?:'(空)')?></pre></div>
  </div><?php endforeach?>
  </div><?php endif?>

  <?php if(!empty($problem['hints'])):?><div class="sec"><h2>提示</h2><div class="md"><?=htmlspecialchars($problem['hints'])?></div></div><?php endif?>

</div>

<script>
document.querySelectorAll('.md').forEach(el=>{el.innerHTML=marked.parse(el.textContent);renderMathInElement(el,{delimiters:[{left:'$$',right:'$$',display:true},{left:'$',right:'$',display:false}],throwOnError:false})});
function copyCode(el){const pre=el.parentElement.querySelector('pre');if(!pre)return;const text=pre.textContent;if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(text).then(()=>done(el))}else{const ta=document.createElement('textarea');ta.value=text;ta.style.position='fixed';ta.style.left='-9999px';document.body.appendChild(ta);ta.select();document.execCommand('copy');document.body.removeChild(ta);done(el)}}
function done(el){el.textContent='已复制';el.classList.add('copy-done');setTimeout(()=>{el.textContent='复制';el.classList.remove('copy-done')},1500)}
</script>
<?php require __DIR__ . '/inc/footer.php'; ?>
