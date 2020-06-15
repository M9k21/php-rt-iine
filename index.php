<?php
session_start();
require('dbconnect.php');

if (isset($_SESSION['id']) && $_SESSION['time'] + 3600 > time()) {
  // ログインしている
  $_SESSION['time'] = time();

  $members = $db->prepare('SELECT * FROM members WHERE id=?');
  $members->execute(array($_SESSION['id']));
  $member = $members->fetch();
} else {
  // ログインしていない
  header('Location: login.php');
  exit();
}

// 投稿を記録する
if (!empty($_POST)) {
  if ($_POST['message'] != '') {
    $message = $db->prepare('INSERT INTO posts SET member_id=?, message=?, reply_post_id=?, created=NOW()');
    $message->execute(array(
      $member['id'],
      $_POST['message'],
      $_POST['reply_post_id']
    ));

    header('Location: index.php');
    exit();
  }
}

// 投稿を取得する
$page = $_REQUEST['page'];
$page = max($page, 1);

// 最終ページを取得する
$counts = $db->query('SELECT COUNT(*) AS cnt FROM posts WHERE delete_flg=0');
$cnt = $counts->fetch();
$maxPage = ceil($cnt['cnt'] / 5);
$page = min($page, $maxPage);

$start = ($page - 1) * 5;

$posts = $db->prepare('SELECT m.name, m.picture, rtm.name AS rt_name, p.* FROM members m, posts p LEFT JOIN members rtm ON p.rt_member_id=rtm.id WHERE m.id=p.member_id AND p.delete_flg=0 GROUP BY p.id ORDER BY p.id DESC LIMIT ?, 5');
$posts->bindParam(1, $start, PDO::PARAM_INT);
$posts->execute();

// いいね記録の取得
$favrecords = $db->prepare('SELECT * FROM favorites WHERE member_id=?');
$favrecords->execute(array($_SESSION['id']));
$favorites = $favrecords->fetchall();
$favorite_post = array_column($favorites, 'post_id');

// いいねカウント
$favcounts = $db->query('SELECT post_id, COUNT(*) AS cnt FROM favorites GROUP BY post_id ORDER BY post_id DESC');
$favcounts = $favcounts->fetchall();
$favcount_id = array_column($favcounts, 'post_id');

// リツイートカウント
$rtcounts = $db->query('SELECT rt_post_id AS id, COUNT(*) AS cnt FROM posts WHERE delete_flg=0 AND rt_post_id>0 GROUP BY rt_post_id ORDER BY rt_post_id DESC');
$rtcounts = $rtcounts->fetchall();
$rtcount_id = array_column($rtcounts, 'id');

// リツイート記録の取得
$rtrecords = $db->prepare('SELECT * FROM posts WHERE rt_member_id=? AND delete_flg=0');
$rtrecords->execute(array($member['id']));
$rtweetall = $rtrecords->fetchall();
$rt_post = array_column($rtweetall, 'rt_post_id');

// 返信の場合
if (isset($_REQUEST['res'])) {
  $response = $db->prepare('SELECT m.name, m.picture, p.* FROM members m, posts p WHERE m.id=p.member_id AND p.id=? AND p.delete_flg=0 ORDER BY p.id DESC');
  $response->execute(array($_REQUEST['res']));

  $table = $response->fetch();
  $message = '@' . $table['name'] . ' ' . $table['message'];
}

// htmlspecialcharsのショートカット
function h($value)
{
  return htmlspecialchars($value, ENT_QUOTES);
}

// 本文内のURLにリンクを設定
function makeLink($value)
{
  return mb_ereg_replace("(https?)(://[[:alnum:]\+\$\;\?\.%,!#~*/:@&=_-]+)", '<a href="\1\2">\1\2</a>', $value);
}

// retweetのURLのショートカット
function retweet_url($value)
{
  return 'retweet.php?post_id=' . $value;
}
?>
<!DOCTYPE html>
<html lang="ja">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="ie=edge">
  <title>ひとこと掲示板</title>

  <link rel="stylesheet" href="css/style.css" />
  <script src="https://kit.fontawesome.com/a6c1df6d9e.js" crossorigin="anonymous"></script>
</head>

<body>
  <div id="wrap">
    <div id="head">
      <h1>ひとこと掲示板</h1>
    </div>
    <div id="content">
      <div style="text-align: right"><a href="logout.php">ログアウト</a></div>
      <form action="" method="post">
        <dl>
          <dt><?php echo h($member['name']); ?>さん、メッセージをどうぞ</dt>
          <dd>
            <textarea name="message" cols="50" rows="5"><?php echo h($message); ?></textarea>
            <input type="hidden" name="reply_post_id" value="<?php echo h($_REQUEST['res']); ?>">
          </dd>
        </dl>
        <div>
          <p>
            <input type="submit" value="投稿する" />
          </p>
        </div>
      </form>

      <?php
      foreach ($posts as $post) :
      ?>
        <div class="msg">
          <img src="member_picture/<?php echo h($post['picture']); ?>" width="48" height="48" alt="<?php echo h($post['name']); ?>" />
          <?php if ($post['rt_member_id'] > 0) : ?>
            <p class="retweet"><i class="fas fa-retweet"></i><?php echo h($post['rt_name']); ?>さんがリツイートしました</p>
          <?php endif; ?>
          <p><?php echo makeLink(h($post['message'])); ?><span class="name"> (<?php echo h($post['name']); ?>) </span>
            <?php if ($post['rt_post_id'] > 0) : ?>
              [<a href="index.php?res=<?php echo h($post['rt_post_id']); ?>">Re</a>]
            <?php else : ?>
              [<a href="index.php?res=<?php echo h($post['id']); ?>">Re</a>]
            <?php endif; ?>
          </p>
          <p class="day">
            <?php
            if ($post['rt_post_id'] > 0) :
              $post['id'] = $post['rt_post_id'];
            ?>
              <?php if (in_array(($post['id']), $favorite_post)) : ?>
                <a href="favorite.php?post_id=<?php echo h($post['id']); ?>"><i class="fas fa-heart unfavorite_btn"></i></a>
                <?php
                if (in_array($post['id'], $favcount_id)) :
                  $fav = array_search($post['id'], $favcount_id);
                ?>
                  <span class="favorite_count"><?php echo h($favcounts[$fav]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php else : ?>
                <a href="favorite.php?post_id=<?php echo h($post['id']); ?>"><i class="far fa-heart favorite_btn"></i></a>
                <?php
                if (in_array($post['id'], $favcount_id)) :
                  $fav = array_search($post['id'], $favcount_id);
                ?>
                  <span class="before_favorite_count"><?php echo h($favcounts[$fav]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php endif; ?>
              <?php if (in_array(($post['id']), $rt_post)) : ?>
                <a href="<?php echo retweet_url(h($post['id'])) ?>"><i class="fas fa-retweet cancel_rt_btn"></i></a>
                <?php
                if (in_array($post['id'], $rtcount_id)) :
                  $rt = array_search($post['id'], $rtcount_id);
                ?>
                  <span class="rt_count"><?php echo h($rtcounts[$rt]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php else : ?>
                <a href="<?php echo retweet_url(h($post['id'])) ?>"><i class="fas fa-retweet rt_btn"></i></a>
                <?php
                if (in_array($post['id'], $rtcount_id)) :
                  $rt = array_search($post['id'], $rtcount_id);
                ?>
                  <span class="before_rt_count"><?php echo h($rtcounts[$rt]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php endif; ?>
              <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
            <?php
            else :
            ?>
              <?php if (in_array(($post['id']), $favorite_post)) : ?>
                <a href="favorite.php?post_id=<?php echo h($post['id']); ?>"><i class="fas fa-heart unfavorite_btn"></i></a>
                <?php
                if (in_array($post['id'], $favcount_id)) :
                  $fav = array_search($post['id'], $favcount_id);
                ?>
                  <span class="favorite_count"><?php echo h($favcounts[$fav]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php else : ?>
                <a href="favorite.php?post_id=<?php echo h($post['id']); ?>"><i class="far fa-heart favorite_btn"></i></a>
                <?php
                if (in_array($post['id'], $favcount_id)) :
                  $fav = array_search($post['id'], $favcount_id);
                ?>
                  <span class="before_favorite_count"><?php echo h($favcounts[$fav]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php endif; ?>
              <?php if (in_array(($post['id']), $rt_post)) : ?>
                <a href="<?php echo retweet_url(h($post['id'])) ?>"><i class="fas fa-retweet cancel_rt_btn"></i></a>
                <?php
                if (in_array($post['id'], $rtcount_id)) :
                  $rt = array_search($post['id'], $rtcount_id);
                ?>
                  <span class="rt_count"><?php echo h($rtcounts[$rt]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php else : ?>
                <a href="<?php echo retweet_url(h($post['id'])) ?>"><i class="fas fa-retweet rt_btn"></i></a>
                <?php
                if (in_array($post['id'], $rtcount_id)) :
                  $rt = array_search($post['id'], $rtcount_id);
                ?>
                  <span class="before_rt_count"><?php echo h($rtcounts[$rt]['cnt']); ?></span>
                <?php
                endif;
                ?>
              <?php endif; ?>
              <a href="view.php?id=<?php echo h($post['id']); ?>"><?php echo h($post['created']); ?></a>
            <?php
            endif;
            ?>
            <?php
            if ($post['reply_post_id'] > 0) :
            ?>
              <a href="view.php?id=<?php echo h($post['reply_post_id']); ?>">返信元のメッセージ</a>
            <?php
            endif;
            ?>
            <?php
            if ($_SESSION['id'] == $post['member_id']) :
            ?>
              <?php
              if ($post['rt_post_id'] > 0) :
              ?>
                [<a href="delete.php?id=<?php echo h($post['rt_post_id']); ?>" style="color:#F33;">削除</a>]
              <?php
              else :
              ?>
                [<a href="delete.php?id=<?php echo h($post['id']); ?>" style="color:#F33;">削除</a>]
              <?php
              endif;
              ?>
            <?php
            endif;
            ?>
          </p>
        </div>
      <?php
      endforeach;
      ?>

      <ul class="paging">
        <?php if ($page > 1) : ?>
          <li><a href="index.php?page=<?php print($page - 1); ?>">前のページへ</a></li>
        <?php else : ?>
          <li>前のページへ</li>
        <?php endif; ?>
        <?php if ($page < $maxPage) : ?>
          <li><a href="index.php?page=<?php print($page + 1); ?>">次のページへ</a></li>
        <?php else : ?>
          <li>次のページへ</li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</body>

</html>
