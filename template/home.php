<!DOCTYPE html>
<html>
<head>
    <link rel="icon" href="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>{title}</title>
    <style> :root { --b: #000; --g: #888; --w: #fff}body { font: 400 18px/1.6 sans-serif; padding: 2rem; background: var(--w); color: var(--b)}.container { max-width: 70ch }.mbh { margin: 0 0 .5rem }.mb1 { margin: 0 0 1rem }.mb2 { margin: 0 0 2rem }.g, pre { color: var(--g) }img { width: 100%; image-rendering: -moz-crisp-edges; image-rendering: pixelated }a, a:visited { color: var(--b); text-decoration-color: var(--g) }a:hover { text-decoration-color: var(--b) }pre, ul, ol, blockquote { margin: 0; padding: 0 1rem }pre { white-space: pre-wrap }@media (prefers-color-scheme: dark) { :root { --b: #fff; --g: #888; --w: #000 }} </style>
</head>
<body>
<div class="head mb2">
    <?php echo '<a href="'.$this->get_config()->host.'" rel="canonical">'.$this->get_config()->site_name.'</a>';
    echo ( $this->where === 'page' )? ' / {title}' : ''; ?>
</div>
    <?php if ( $this->where === 'home' ): ?>
    <div class="entries mb2">
        <?php
        $page_list = $this->get_list('by_date','desc');
        $pages = $this->filter( $page_list, 'flags', 'guide', true );
        foreach ( $pages as $page ) {
            echo '<div class="entry mb1">';
            echo '<a href="' . $page['url'] . '">' . $page['metadata']['title'] . '</a>' . (( $page['metadata']['date'] )? ' &mdash; ' . $page['metadata']['date'] : '');
            echo '</div>';
        }
        ?>
    </div>
    <div class="entries mb2">
    <h2>Guides</h2>
    <?php
        $guides = $this->filter( $page_list, 'flags', 'guide', false );
        foreach ( $guides as $page ) {
            echo '<div class="entry mb1">';
            echo '<a href="' . $page['url'] . '">' . $page['metadata']['title'] . '</a>' . (( $page['metadata']['date'] )? ' &mdash; ' . $page['metadata']['date'] : '');
            echo '</div>';
        }
        ?>
    </div>
    <?php endif; ?>
    <div class="container mb2">
        <?php echo ( $this->where === 'page' )? '{description}' : ''; ?>
        {content}
    </div>
    <div class="footer">
        <img style="width:32px;height:auto;" src="<?php echo $this->get_config()->logo; ?>">
    </div>
</body>
</html>
