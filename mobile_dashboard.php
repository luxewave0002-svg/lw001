<?php
// スマホ用の簡易ダッシュボードは廃止し、PC版と同じ dashboard.php を表示する。
// 既存のブックマークやリンクから来た場合に備えてリダイレクトのみ行う。
require_once 'db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: mobile_login.php");
    exit;
}

header("Location: dashboard.php");
exit;
