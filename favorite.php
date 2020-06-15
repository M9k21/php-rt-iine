<?php
session_start();
require('dbconnect.php');

// 遷移元のページを取得する
$referer = $_SERVER['HTTP_REFERER'];

if (isset($_REQUEST['post_id']) && isset($referer)) {

  // いいね履歴の取得
  $records = $db->prepare('SELECT COUNT(*) AS cnt FROM favorites WHERE post_id=? AND member_id=?');
  $records->execute(array(
    $_REQUEST['post_id'],
    $_SESSION['id']
  ));
  $record = $records->fetch();

  if ($record['cnt'] > 0) {
    // いいね削除
    $unfav = $db->prepare('DELETE FROM favorites WHERE post_id=? AND member_id=?');
    $unfav->execute(array(
      $_REQUEST['post_id'],
      $_SESSION['id']
    ));
  } else {
    // いいねする
    $fav = $db->prepare('INSERT INTO favorites SET member_id=?, post_id=?, created=NOW()');
    $fav->execute(array(
      $_SESSION['id'],
      $_REQUEST['post_id']
    ));
  }
  header('Location:' . $referer);
  exit();
}

header('Location: index.php');
exit();
