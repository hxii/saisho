<?php

include 'head.php';

echo '<a href="' . HOST . '">'.META_TITLE.'</a> / <strong><span role="heading" aria-level=1>'.$page->content->title.'</span></strong>';
$html = <<<EOD
		<div class="content">{$page->content->body}</div>
		<div class="meta g">Last Modified: {$page->content->modified}</div>
		EOD;
echo $html;

include 'footer.php';
