<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_login('admin');

$id=(int)($_GET['id']??0);
$stmt=db()->prepare("SELECT * FROM exams WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$exam=$stmt->fetch();
if(!$exam) exit('Ujian tidak ditemukan.');

$qstmt=db()->prepare("SELECT * FROM questions WHERE exam_id=? ORDER BY sort_order,id");
$qstmt->execute([$id]);
$questions=$qstmt->fetchAll();

function preview_image(?string $path): string {
    if(!$path) return '';
    $path=trim(str_replace('\\','/',$path));
    if(preg_match('~^https?://~i',$path)) return $path;
    return '../'.ltrim($path,'/');
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preview · <?=htmlspecialchars($exam['title'])?></title>
<style>
:root{--bg:#f4f7fb;--card:#fff;--ink:#101828;--muted:#667085;--line:#e4e7ec;--blue:#175cd3;--green:#027a48;--purple:#6941c6}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,Segoe UI,Arial,sans-serif}
.top{background:#0b1324;color:#fff;padding:17px 0;position:sticky;top:0;z-index:10}.wrap{max-width:980px;margin:auto;padding:0 20px}
.topin{display:flex;justify-content:space-between;align-items:center;gap:15px}.brand{font-weight:900;letter-spacing:-.03em}.back{color:#cbd5e1;text-decoration:none;font-size:13px;font-weight:700}
.hero{padding:30px 0 18px}.hero h1{font-size:30px;margin:0 0 7px;letter-spacing:-.04em}.meta{color:var(--muted);font-size:13px}
.banner{background:#eef4ff;border:1px solid #bfd2ff;color:#174ea6;border-radius:12px;padding:12px 14px;margin-bottom:16px;font-size:13px}
.q{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;margin:14px 0;box-shadow:0 5px 20px #1018280a}
.qhead{display:flex;justify-content:space-between;gap:12px;align-items:center}.num{font-size:12px;font-weight:900;color:var(--blue);text-transform:uppercase;letter-spacing:.06em}.points{font-size:12px;color:var(--muted);font-weight:800}
.badge{display:inline-flex;padding:4px 8px;border-radius:99px;background:#ecfdf3;color:var(--green);font-size:11px;font-weight:800;margin-left:6px}
.question{font-size:18px;line-height:1.65;white-space:pre-wrap;margin:14px 0}.image{display:block;max-width:100%;max-height:480px;object-fit:contain;margin:14px auto;border:1px solid var(--line);border-radius:12px}
.matrix-preview{overflow:auto;margin:12px 0}.matrix-preview table{width:100%;border-collapse:collapse}.matrix-preview th,.matrix-preview td{padding:10px;border-bottom:1px solid var(--line);text-align:center}.matrix-preview th:first-child{text-align:left}.options{display:grid;gap:9px}.option{border:1px solid var(--line);border-radius:11px;padding:13px;background:#fafbfc;font-size:14px}.letter{display:inline-flex;width:28px;height:28px;align-items:center;justify-content:center;border-radius:50%;background:#eef4ff;color:#175cd3;font-weight:900;margin-right:8px}
.answer{margin-top:13px;padding:11px 13px;border-radius:10px;background:#f8fafc;font-size:12px;color:var(--muted)}
.footer{padding:20px 0 45px;text-align:center;color:var(--muted);font-size:12px}
.preview-nav{display:flex;justify-content:center;align-items:center;gap:10px;margin:18px 0;font-size:13px;font-weight:700}.preview-nav button:disabled{opacity:.45;cursor:not-allowed}.actions{display:flex;justify-content:center;gap:9px;margin:20px 0}.btn{display:inline-block;padding:10px 14px;border-radius:9px;text-decoration:none;font-size:13px;font-weight:800}.primary{background:var(--blue);color:#fff}.secondary{background:#eef2f7;color:#344054}
@media(max-width:600px){.hero h1{font-size:24px}.q{padding:17px}.question{font-size:16px}}
@media print{.top{position:static}.actions,.banner,.back{display:none}.q{break-inside:avoid;box-shadow:none}}
.matrix-options-preview{display:grid;gap:8px;margin:14px 0}.matrix-option-preview{display:flex;gap:12px;align-items:flex-start;line-height:1.55;font-size:16px}.matrix-option-preview b{min-width:24px;color:#344054}
</style>
<link rel="stylesheet" href="assets/admin-ui.css">
</head>
<body>
<div class="top"><div class="wrap topin"><div class="brand">Ujian Online · Preview</div><a class="back" href="questions.php?id=<?=$id?>">← Kembali ke Kelola Soal</a></div></div>
<div class="wrap">
<section class="hero">
<h1><?=htmlspecialchars($exam['title'])?></h1>
<div class="meta">
<?=count($questions)?> soal · Durasi <?=max(1,(int)floor(((int)$exam['duration_seconds'])/60))?> menit
<?php if(!empty($exam['question_mode'])): ?> · Mode <?=htmlspecialchars($exam['question_mode']==='one_by_one'?'satu per satu':'semua soal')?><?php endif; ?>
</div>
</section>
<div class="banner"><b>Mode Preview Admin.</b> Ini hanya untuk memeriksa tampilan soal sebelum link dibagikan kepada peserta. Timer dan pengiriman jawaban tidak dijalankan.</div>

<?php if(!$questions): ?>
<div class="q" style="text-align:center;color:var(--muted)">Belum ada soal. Tambahkan soal terlebih dahulu sebelum membagikan ujian.</div>
<?php else: ?>
<?php foreach($questions as $i=>$q): $img=preview_image($q['question_image']??null); ?>
<article class="q">
  <div class="qhead">
    <div class="num">Soal <?=($i+1)?> <span class="badge"><?=($q['type']==='essay'?'Essay':($q['type']==='matrix_disc'?'Matriks / DISC':'Pilihan Ganda'))?></span></div>
    <div class="points"><?=htmlspecialchars((string)$q['points'])?> poin</div>
  </div>
  <?php
    $isMatrix = ($q['type'] ?? '') === 'matrix_disc';
    $legacyMatrixText = trim((string)($q['question_text'] ?? ''));
    $matrixHasEmbeddedOptions = $isMatrix && preg_match_all('/(?:^|\s)[A-D]\.\s*/', $legacyMatrixText, $matrixMarkerMatches) >= 4;
  ?>
  <div class="question"><?= $matrixHasEmbeddedOptions ? '' : htmlspecialchars($q['question_text']) ?></div>
  <?php if($img): ?><img class="image" src="<?=htmlspecialchars($img)?>" alt="Gambar soal" onerror="this.style.display='none'"><?php endif; ?>
  <?php if($q['type']==='essay'): ?>
    <div class="answer">Kolom jawaban peserta: <b>Essay</b></div>
    <?php if(!empty($q['essay_answer_key'])): ?><div class="answer"><b>Jawaban acuan admin:</b> <?=htmlspecialchars($q['essay_answer_key'])?></div><?php endif; ?>
  <?php elseif($q['type']==='matrix_disc'): 
    $matrixOptions = [
      'A' => trim((string)($q['option_a'] ?? '')),
      'B' => trim((string)($q['option_b'] ?? '')),
      'C' => trim((string)($q['option_c'] ?? '')),
      'D' => trim((string)($q['option_d'] ?? '')),
    ];
    if (!array_filter($matrixOptions) && preg_match_all('/(?:^|\s)([A-D])\.\s*/', $legacyMatrixText, $m, PREG_OFFSET_CAPTURE) && count($m[0]) >= 4) {
      $matrixOptions = ['A'=>'','B'=>'','C'=>'','D'=>''];
      for ($mi=0; $mi<count($m[0]); $mi++) {
        $letter = $m[1][$mi][0];
        $start = $m[0][$mi][1] + strlen($m[0][$mi][0]);
        $end = ($mi+1<count($m[0])) ? $m[0][$mi+1][1] : strlen($legacyMatrixText);
        $matrixOptions[$letter] = trim(substr($legacyMatrixText, $start, $end-$start));
      }
    }
  ?>
    <div class="matrix-options-preview">
      <?php foreach(['A','B','C','D'] as $letter): if(trim((string)($matrixOptions[$letter]??''))==='') continue; ?>
      <div class="matrix-option-preview"><b><?=$letter?>.</b><span><?=htmlspecialchars((string)($matrixOptions[$letter]??''))?></span></div>
      <?php endforeach; ?>
    </div>
    <div class="matrix-preview"><table><thead><tr><th></th><th>A</th><th>B</th><th>C</th><th>D</th></tr></thead><tbody><tr><th>MIRIP</th><?php foreach(['A','B','C','D'] as $x):?><td>○</td><?php endforeach;?></tr><tr><th>TIDAK MIRIP</th><?php foreach(['A','B','C','D'] as $x):?><td>○</td><?php endforeach;?></tr></tbody></table></div>
    <?php /* Matriks / DISC tidak menampilkan kunci admin pada Preview. */ ?>
  <?php else: ?>
    <div class="options">
      <?php foreach(['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $letter=>$field): ?>
      <div class="option"><span class="letter"><?=$letter?></span><?=htmlspecialchars((string)($q[$field]??''))?></div>
      <?php endforeach; ?>
    </div>
    <div class="answer"><b>Kunci admin:</b> <?=htmlspecialchars((string)$q['correct_option'])?></div>
  <?php endif; ?>
</article>
<?php endforeach; ?>
<?php endif; ?>
<div class="preview-nav" id="previewNav"></div><div class="actions"><a class="btn secondary" href="questions.php?id=<?=$id?>">Kembali Edit Soal</a><?php if($questions): ?><a class="btn primary" href="exam_link.php?id=<?=$id?>">Lanjut ke Bagikan Link</a><?php endif; ?></div>
<div class="footer">Ujian Online · Preview ujian sebelum dibagikan</div>
</div>
<script>const qs=[...document.querySelectorAll('.q')];if(<?=json_encode(($exam['question_mode']??'all')==='one_by_one')?>&&qs.length){let i=0;function show(){qs.forEach((q,n)=>q.style.display=n===i?'':'none');document.getElementById('previewNav').innerHTML='<button class="btn secondary" '+(i?'':'disabled')+' id="pvPrev">← Sebelumnya</button><span>Soal '+(i+1)+' / '+qs.length+'</span><button class="btn primary" '+(i===qs.length-1?'disabled':'')+' id="pvNext">Berikutnya →</button>';let a=document.getElementById('pvPrev'),b=document.getElementById('pvNext');if(a)a.onclick=()=>{i--;show()};if(b)b.onclick=()=>{i++;show()}}show()}</script></body>
</html>
