<?php
session_start();
require('dbconnect.php');

if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {

  // リツイート履歴を確認
  $records = $db->prepare('SELECT COUNT(*) AS cnt FROM posts WHERE rt_post_id=? AND rt_member_id=? AND delete_flg=0');
  $records->execute(array(
    $_REQUEST['id'],
    $_SESSION['id']
  ));
  $record = $records->fetch();

  if ($record['cnt'] > 0) {
    // リツイート解除
    $rtcancel = $db->prepare('UPDATE posts SET delete_flg=1 WHERE rt_post_id=? AND rt_member_id=?');
    $rtcancel->execute(array(
      $_REQUEST['id'],
      $_SESSION['id']
    ));
  } else {
    // リツイートする
    $retweet = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? AND p.delete_flg=0 ORDER BY p.id DESC');
    $retweet->execute(array($_REQUEST['id']));
    $rtpost = $retweet->fetch();

    $rt = $db->prepare('INSERT INTO posts SET message=?, member_id=?, reply_post_id=?, rt_post_id=?, rt_member_id=?, created=?, modified=?');
    $rt->execute(array(
      $rtpost['message'],
      $rtpost['member_id'],
      $rtpost['reply_post_id'],
      $_REQUEST['id'],
      $_SESSION['id'],
      $rtpost['created'],
      $rtpost['modified']
    ));
  }
}

header('Location: index.php');
exit();
