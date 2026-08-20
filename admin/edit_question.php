<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/schema_sync.php';
ensure_matrix_disc_schema();
require_login('admin');

$id=(int)($_GET['id']??0);
$stmt=db()->prepare('SELECT * FROM questions WHERE id=? LIMIT 1');
$stmt->execute([$id]);
$q=$stmt->fetch();
if(!$q) exit('Soal tidak ditemukan.');
$examId=(int)$q['exam_id'];

if($_SERVER['REQUEST_METHOD']==='POST'){
  check_csrf();
  $type=$_POST['type']??'mcq';
  $text=trim((string)($_POST['question_text']??''));
  $correct=trim((string)($_POST['correct_option']??'')) ?: null;
  $matrixMirip=strtoupper(trim((string)($_POST['matrix_correct_mirip']??''))) ?: null;
  $matrixTidak=strtoupper(trim((string)($_POST['matrix_correct_tidak']??''))) ?: null;
  $points=(float)($_POST['points']??1);
$essayKey=trim((string)($_POST['essay_answer_key']??'')) ?: null;
  if($text==='') exit('Pertanyaan wajib diisi.');
  if(!in_array($type,['mcq','essay','matrix_disc'],true)) $type='mcq';
  if($type==='mcq' && !in_array($correct,['A','B','C','D'],true)) exit('Kunci jawaban PG harus A-D.');
  if($type==='essay') $correct=null;
  if($type==='matrix_disc'){
    if(!in_array($matrixMirip,['A','B','C','D'],true) || !in_array($matrixTidak,['A','B','C','D'],true)) exit('Kunci Matriks / DISC harus A-D.');
    $correct=null;
  } else { $matrixMirip=null; $matrixTidak=null; }

  $imagePath=$q['question_image']??null;
  if(isset($_POST['remove_image']) && $_POST['remove_image']==='1'){
    if($imagePath && is_file(__DIR__.'/../'.$imagePath)) @unlink(__DIR__.'/../'.$imagePath);
    $imagePath=null;
  }
  if(!empty($_FILES['question_image']['tmp_name'])){
    $f=$_FILES['question_image'];
    if($f['error']!==UPLOAD_ERR_OK) exit('Upload gambar gagal.');
    $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    if(!isset($allowed[$mime])) exit('Format gambar harus JPG, PNG, WEBP, atau GIF.');
    if($f['size']>5*1024*1024) exit('Ukuran gambar maksimal 5 MB.');
    $dir=__DIR__.'/../uploads/questions';
    if(!is_dir($dir)) mkdir($dir,0755,true);
    $filename='q_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$filename)) exit('Gagal menyimpan gambar.');
    if($imagePath && is_file(__DIR__.'/../'.$imagePath)) @unlink(__DIR__.'/../'.$imagePath);
    $imagePath='uploads/questions/'.$filename;
  }

  $upd=db()->prepare('UPDATE questions SET type=?, question_text=?, question_image=?, essay_answer_key=?, option_a=?, option_b=?, option_c=?, option_d=?, correct_option=?, matrix_correct_mirip=?, matrix_correct_tidak=?, points=? WHERE id=?');
  $upd->execute([$type,$text,$imagePath,$type==='essay'?$essayKey:null,$_POST['A']??null,$_POST['B']??null,$_POST['C']??null,$_POST['D']??null,$correct,$matrixMirip,$matrixTidak,$points,$id]);
  header('Location: questions.php?id='.$examId.'&updated=1'); exit;
}
?>
<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Soal</title><link rel="stylesheet" href="assets/admin-ui.css"></head><body><div class="admin-layout"><aside class="admin-sidebar"><div class="admin-brand"><span class="mark">✓</span> Ujian Online</div><div class="admin-section">MENU UTAMA</div><nav class="admin-nav"><a href="index.php">⌂ Dashboard</a><a class="active" href="index.php#ujian-list">▣ Ujian</a><a href="participants.php">♟ Peserta</a></nav><div class="admin-section">SISTEM</div><nav class="admin-nav"><a href="update.php">↻ Update Sistem</a></nav></aside><main class="admin-main"><header class="admin-topbar"><div class="top-left"><button class="hamb menu-toggle">☰</button><h1>Edit Soal</h1></div></header><div class="admin-content"><div class="page-head"><div><div class="backline"><a href="questions.php?id=<?=$examId?>">← Kembali ke Kelola Soal</a></div><h2 class="page-title">Edit Soal</h2><p class="page-subtitle">Perbarui pertanyaan, gambar, jawaban, dan poin.</p></div></div>
<section class="ui-card edit-question-card"><form method="post" enctype="multipart/form-data" class="exam-form"><input type="hidden" name="csrf" value="<?=htmlspecialchars(csrf_token())?>">
<div class="form-grid"><div><label>Jenis</label><select name="type" id="editType"><option value="mcq" <?=$q['type']==='mcq'?'selected':''?>>Pilihan ganda</option><option value="essay" <?=$q['type']==='essay'?'selected':''?>>Essay</option><option value="matrix_disc" <?=$q['type']==='matrix_disc'?'selected':''?>>Matriks / DISC</option></select></div><div><label>Poin</label><input type="number" name="points" min="0" step="0.5" value="<?=htmlspecialchars((string)$q['points'])?>"></div></div>
<label>Pertanyaan</label><textarea name="question_text" required><?=htmlspecialchars($q['question_text'])?></textarea>
<label>Gambar soal</label><?php if(!empty($q['question_image'])): ?><img class="question-image edit-preview" src="<?=htmlspecialchars(app_url($q['question_image']))?>" alt="Gambar soal"><label class="check-row"><input type="checkbox" name="remove_image" value="1"> Hapus gambar saat menyimpan</label><?php endif;?><input type="file" name="question_image" accept="image/jpeg,image/png,image/webp,image/gif">
<div id="editMcq"><div class="form-grid"><?php foreach(['A'=>'option_a','B'=>'option_b','C'=>'option_c','D'=>'option_d'] as $k=>$c): ?><div><label>Pilihan <?=$k?></label><input name="<?=$k?>" value="<?=htmlspecialchars((string)$q[$c])?>"></div><?php endforeach;?></div><label>Kunci jawaban</label><select name="correct_option"><?php foreach(['A','B','C','D'] as $k): ?><option value="<?=$k?>" <?=$q['correct_option']===$k?'selected':''?>><?=$k?></option><?php endforeach;?></select></div>
<div id="editEssay"><label>Jawaban acuan / kunci essay</label><textarea name="essay_answer_key"><?=htmlspecialchars((string)($q['essay_answer_key']??''))?></textarea></div><div id="editMatrix"><p class="ui-sub">Peserta memilih satu jawaban untuk MIRIP dan satu untuk TIDAK MIRIP.</p><div class="form-grid"><div><label>Kunci MIRIP</label><select name="matrix_correct_mirip"><?php foreach(['A','B','C','D'] as $k): ?><option value="<?=$k?>" <?=($q['matrix_correct_mirip']??'A')===$k?'selected':''?>><?=$k?></option><?php endforeach;?></select></div><div><label>Kunci TIDAK MIRIP</label><select name="matrix_correct_tidak"><?php foreach(['A','B','C','D'] as $k): ?><option value="<?=$k?>" <?=($q['matrix_correct_tidak']??'A')===$k?'selected':''?>><?=$k?></option><?php endforeach;?></select></div></div></div>
<div class="form-actions"><a class="ui-btn secondary" href="questions.php?id=<?=$examId?>">Batal</a><button class="ui-btn">Simpan Perubahan</button></div></form></section></div></main></div><script>document.querySelector('.menu-toggle').onclick=()=>document.body.classList.toggle('sidebar-open');const t=document.getElementById('editType');function s(){editMcq.style.display=(t.value==='mcq'||t.value==='matrix_disc')?'block':'none';editEssay.style.display=t.value==='essay'?'block':'none';editMatrix.style.display=t.value==='matrix_disc'?'block':'none'}t.onchange=s;s()</script></body></html>