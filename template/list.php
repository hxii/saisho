<?php

include 'head.php';

echo '<strong><span role="heading"><a href="' . HOST . '">' . META_TITLE . '</a></span></strong>';
?>
<div><p>Welcome to <a href="https://0xff.nu/saisho">Saisho Mk.2</a> version <?= SAISHO_VERSION ?>!</p>
<p>You can change this text in <code>template/list.php</code>.</div>
<?php
echo '<div>' . $page->content . '</div>';

include 'footer.php';
