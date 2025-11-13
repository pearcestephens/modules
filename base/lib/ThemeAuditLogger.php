<?php
namespace CIS\Base;
class ThemeAuditLogger { public static function log(string $action, ?int $themeId = null, array $details = []): void { $entry = ['timestamp'=>date('c'),'action'=>$action,'theme_id'=>$themeId,'user_id'=>$_SESSION['user_id']??null,'details'=>$details]; $file='/home/master/applications/jcepnzzkmj/logs/theme_audit.log'; $dir=dirname($file); if(!is_dir($dir)){@mkdir($dir,0755,true);} @file_put_contents($file,json_encode($entry).PHP_EOL,FILE_APPEND); } }
