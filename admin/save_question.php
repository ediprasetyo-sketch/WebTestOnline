<?php
declare(strict_types=1);
require __DIR__.'/../config.php';
require_once __DIR__.'/../includes/schema_sync.php';
require_login('admin'); check_csrf();
// V6.3.46: repair Matrix/DISC schema before reading or writing question columns.
ensure_matrix_disc_schema();
$examId=(int)($_POST['exam_id']??0);
$type=$_POST['type']??'mcq';
if ($type === 'matrix_disc') { $_POST['correct_option'] = null; }

$text=trim((string)($_POST['question_text']??''));
$correct=strtoupper(trim((string)($_POST['correct_option']??''))) ?: null;
$matrixMirip=strtoupper(trim((string)($_POST['matrix_correct_mirip']??''))) ?: null;
$matrixTidak=strtoupper(trim((string)($_POST['matrix_correct_tidak']??''))) ?: null;
$points=max(0,(float)($_POST['points']??1));
$essayKey=trim((string)($_POST['essay_answer_key']??'')) ?: null;
if(!$examId||$text==='') exit('Data soal tidak lengkap.');
if(!in_array($type,['mcq','essay','matrix_disc'],true)) $type='mcq';
if($type==='mcq'&&!in_array($correct,['A','B','C','D'],true)) exit('Kunci jawaban PG harus A-D.');
if($type==='essay') $correct=null;
if($type==='matrix_disc'){
 if(!in_array($matrixMirip,['A','B','C','D'],true)||!in_array($matrixTidak,['A','B','C','D'],true)) exit('Kunci Matriks / DISC harus A-D.');
 $correct=null;
} else { $matrixMirip=null; $matrixTidak=null; }
$check=db()->prepare('SELECT id FROM exams WHERE id=? LIMIT 1');$check->execute([$examId]);if(!$check->fetch())exit('Ujian tidak ditemukan.');
$imagePath=null;
if(isset($_FILES['question_image'])&&$_FILES['question_image']['error']!==UPLOAD_ERR_NO_FILE){
 $f=$_FILES['question_image']; if($f['error']!==UPLOAD_ERR_OK)exit('Upload gambar gagal.');
 $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);$allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
 if(!isset($allowed[$mime]))exit('Format gambar harus JPG, PNG, WEBP, atau GIF.');if($f['size']>5*1024*1024)exit('Ukuran gambar maksimal 5 MB.');
 $dir=__DIR__.'/../uploads/questions';if(!is_dir($dir)&&!mkdir($dir,0755,true))exit('Folder upload tidak dapat dibuat.');
 $name='q_'.bin2hex(random_bytes(12)).'.'.$allowed[$mime];if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name))exit('Gagal menyimpan gambar.');
 $imagePath='uploads/questions/'.$name;
}
$o=db()->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM questions WHERE exam_id=?');$o->execute([$examId]);$order=(int)$o->fetchColumn();
$hasMatrixCols = false;
try {
 $cols = db()->query("SHOW COLUMNS FROM questions")->fetchAll();
 $names = array_column($cols, 'Field');
 $hasMatrixCols = in_array('matrix_correct_mirip', $names, true) && in_array('matrix_correct_tidak', $names, true);
} catch (Throwable $e) { $hasMatrixCols = false; }
if ($type==='matrix_disc' && !$hasMatrixCols) exit('Schema Matriks / DISC tidak dapat disiapkan otomatis. Periksa izin ALTER TABLE pada database.');
if ($hasMatrixCols) {
 $ins=db()->prepare('INSERT INTO questions(exam_id,type,question_text,question_image,essay_answer_key,option_a,option_b,option_c,option_d,correct_option,matrix_correct_mirip,matrix_correct_tidak,points,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
 $params=[$examId,$type,$text,$imagePath,$type==='essay'?$essayKey:null,$_POST['A']??null,$_POST['B']??null,$_POST['C']??null,$_POST['D']??null,$correct,$matrixMirip,$matrixTidak,$points,$order];
} else {
 $ins=db()->prepare('INSERT INTO questions(exam_id,type,question_text,question_image,essay_answer_key,option_a,option_b,option_c,option_d,correct_option,points,sort_order) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
 $params=[$examId,$type,$text,$imagePath,$type==='essay'?$essayKey:null,$_POST['A']??null,$_POST['B']??null,$_POST['C']??null,$_POST['D']??null,$correct,$points,$order];
}
try{$ins->execute($params);}
catch(Throwable $e){if($imagePath&&is_file(__DIR__.'/../'.$imagePath))@unlink(__DIR__.'/../'.$imagePath);exit('Gagal menyimpan soal: '.htmlspecialchars($e->getMessage()));}
header('Location: questions.php?id='.$examId.'&updated=1');exit;
