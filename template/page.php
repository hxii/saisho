<?php

include 'head.php';

echo '<a href="' . HOST . '">'.META_TITLE.'</a> / <strong><span role="heading" aria-level=1>'.$page->content->title.'</span></strong>';
$html = <<<EOD
		<div class="content">{$page->content->body}</div>
		<div class="meta g">Last Modified: {$page->content->modified}</div>
		EOD;
echo $html;
/* -- CUSTOM ------------ */
echo <<<EOD
	<hr>
	<form id="contactform" action="https://formsubmit.io/send/b6458e9e-99b8-4e08-9cbc-d758377014e1" method="POST">
		<textarea name="comment" id="comment" rows="4" aria-label="Your comment" placeholder="Let's discuss this.&#x1F603; This form sends me an anonymized email using Formsubmit. Don't forget to mention your name and e-mail address if you'd like me to respond."></textarea>
		<input name="entry" type="hidden" value="{$page->content->title}">
		<input name="_formsubmit_id" type="text" style="display:none">
		<button>Submit</button>
	</form>
	EOD;
// echo $form;
/* -- END --------------- */

include 'footer.php';
