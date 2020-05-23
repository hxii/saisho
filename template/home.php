<?php $time = microtime(true); ?>
<!DOCTYPE html>
<html>
<head>
	<link rel="icon" href="data:image/png;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=">
	<link href="https://fonts.googleapis.com/css2?family=Roboto+Mono:ital,wght@0,300;0,400;1,300;1,400&display=swap" rel="stylesheet">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta charset="UTF-8">
	<title>{title}</title>
	<style>* {
  box-sizing: border-box;
  margin: 0;
  padding: 0;
}

html {
  font: 300 20px/1.62 'Roboto Mono', Monaco, Consolas, monospace;
}

body {
  padding: 1.62rem;
  display: flex;
  justify-content: center;
}

a {
  text-decoration: none;
    color: #000;
}

div {
	word-break: break-word;
}

.container {
  max-width: 60ch;
  font-size: min(max(1rem, 4vw), 1.62rem);
  margin: 0 auto;
}

ul.entry-list {
  list-style-type: none;
  text-transform: uppercase;
  line-height: 1;
  font-size: 0;
}

ul.entry-list li {
  display: inline-block;
  font-size: 2.2rem;
}

ul.entry-list li:not(:last-child):after {
  content: '\00B7';
  font-weight: 100;
}

ul.entry-list:hover li a {
  color: #ddd;
}

ul.entry-list li a:hover, .entry a, a:hover {
  color: white;
  background: black;
}

.entry {
  font-family: sans-serif;
}
.i {
  font-style: italic;
}
.s {
  text-decoration: underline
}
.db {
  display: block;
}
.dib {
  display: inline-block
}

.mt1, hr {
  margin: 1rem 0 0
}
.mb1, hr, p {
  margin: 0 0 1rem
}
.mb2 {
  margin: 0 0 2rem
}
.b {
  color: #1111ff;
}
.r {
  color: #ee1111;
}
.g {
  color: #eee
}</style>
</head>
<body>
	<div class="container">
		<div class="head mb2">
			<?php echo '<a class="b" href="' . $this->get_config()->host . '" rel="canonical">' . $this->get_config()->site_name . '</a>';
			echo ( $this->where === 'page' ) ? ' / {title}' : ''; ?>
		</div>
		<?php if ( $this->where === 'home' ): ?>
            <ul class="entry-list mb2">
                <?php
                $page_list = $this->get_list( 'by_date','desc', true );
                foreach ( $page_list as $page ) {
					$style = ( isset( $page['metadata']['style']) )? $page['metadata']['style'] : '';
					$title = ( isset( $page['metadata']['title']) )? $page['metadata']['title'] : '';
                    echo '<li>';
                    echo '<a style="'.$style.'" href="' . $page['url'] . '">' . $title . '</a>';
                    echo '</li>';
                }
                ?>
            </ul>
		<?php endif; ?>
		<?php if ( $this->where === 'page' ): ?>
			<?php
				$page = substr( $_SERVER['REQUEST_URI'], 1 );
				$page = $this->get_page( $page, false );
			?>
            <div class="container mb2">
				<div class="entry">
				<?php echo ( $this->where === 'page' ) ? '{description}' : ''; ?>
                {content}</div>
            </div>
		<?php endif; ?>
		<div class="footer">
			<!-- <img style="width:32px;height:auto;" src="<?php //echo $this->get_config()->logo; ?>"> -->
            <span class="g">
				<?php
				echo 'L'. round((microtime(true) - $this->time)*1000,3) .
				(( isset( $page_list ) )? ' A' . count($page_list) : '') .
				(( $this->where === 'page' )? ' W' . str_word_count($page['content']) : '') .
				(( $this->where === 'page' )? ' D' . $page['date'] : '') .
				' V' . SAISHO_VERSION
				;?>
            </span>
		</div>
	</div>
</body>
</html>
