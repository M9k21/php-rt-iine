<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id'])) {
  $id = $_REQUEST['id'];

  // 投稿を検査する
  $messages = $db->prepare('SELECT * FROM posts WHERE id=?');
  $messages->execute(array($id));
  $message = $messages->fetch();

  if ($message['member_id'] === $_SESSION['id']) {
    $db->begintransaction();
    // 削除する
    $del = $db->prepare('UPDATE posts SET delete_flg=1 WHERE id=?');
    $del->execute(array($id));

    // リツイートを削除する
    $rtdel = $db->prepare('UPDATE posts SET delete_flg=1 WHERE rt_post_id=? AND delete_flg=0');
    $rtdel->execute(array($id));
    $db->commit();
  }
}

header('Location: index.php');
exit();
