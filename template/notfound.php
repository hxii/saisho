<?php

include 'head.php';

echo '<a href="' . HOST . '">↩</a> / oops';
$html = <<<EOD
<div class="content"><h1>(ノಠ益ಠ)ノ彡 Not found</h1></div>
EOD;
echo $html;

include 'footer.php';