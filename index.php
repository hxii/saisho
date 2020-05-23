<?php
/*

      最小  —  Saishō

*/
$time = microtime(true);
// $where = '';
$files = [];

define( 'SAISHO', true );
define( 'SAISHO_VERSION', '0.1.1' );
$config = include ( 'config.php' );
define( 'HOME_URI', $config->host );
define( 'DATA_FOLDER', $config->data_folder );
define( 'CACHE_FOLDER', $config->cache_folder );
define( 'TEMPLATE_FOLDER', $config->template_folder );
require ( 'inc/Parsedown.php' );
require ( 'inc/ParsedownExtra.php' );

class Saisho {

  public $config, $time, $extime;

  public function __construct( object $config ) {
    $this->time = microtime(true);
    $this->config = $config;
    $this->where = ( $_SERVER['REQUEST_URI'] === '/' ) ? 'home' : 'page';
    $this->handle_cache();
  }

  /**
   * Return configuration object
   *
   * @return object
   */
  public function get_config() {
    return $this->config;
  }
  
  /**
   * Parse page content and metadata
   *
   * @param  string $filename file to parse
   * @param  bool $content return the content or just metadata?
   * @return array page data
   */
  private function parse_page( string $filename, bool $content = true ) {
    $fh   = fopen( $filename, 'r' );
    $data = fread( $fh, filesize ( $filename ) );
    fclose( $fh );
    preg_match_all ( '/(?:\[\[)(.*)(?:\]\] )(.*)\n?/', $data, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) {
      $page[ $match[ 1 ] ] = $match[ 2 ];
    }
    $data = preg_replace( '/(?:\[\[.*\]\] ).*\n?/', '', $data );
    if ( $content ) {
      $page['content'] = $this->parse_markdown( $data );
    }
    return (array) $page;
  }

  /**
   * Parse Markdown
   *
   * @param  string $data data to parse
   * @return string
   */
  private function parse_markdown( string $data ) {
    $parsedown = new ParsedownExtra();
    return (string) $parsedown->text( $data );
  }
  
  /**
   * Get page
   *
   * @param  string $page page file name
   * @param  mixed $metadata_only return metadata alone?
   * @return array
   */
  public function get_page( string $page, bool $metadata_only = false ) {
    if ( '.md' !== substr( $page, -3 ) ) {
      $page = $page . '.md';
    }
    if ( file_exists( DATA_FOLDER . DIRECTORY_SEPARATOR . $page ) ) {
      if ( $metadata_only ) {
        return (array) $this->parse_page( DATA_FOLDER . DIRECTORY_SEPARATOR . $page, false ); 
      }
      return (array) $this->parse_page( DATA_FOLDER . DIRECTORY_SEPARATOR . $page );
    }
  }
  
  /**
   * Get page metadata
   *
   * @param  mixed $page
   * @return void
   */
  public function get_metadata( string $page ) {
    return (array) $this->get_page( $page, true );
  }

  private function handle_cache() {
    $hash_time = microtime(true);
    $this->rss( $this->config->feed_file );
    $request_hash = crc32( $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] );
    $filename = substr( $_SERVER['REQUEST_URI'], 1 ) ?: $this->config->home_page;
    if ( ! file_exists( DATA_FOLDER . DIRECTORY_SEPARATOR . "{$filename}.md" ) ) {
      $this->where = 'not_found';
      http_response_code( 404 );
      die('404 Not Found');
    }
    $cache_filename = CACHE_FOLDER . DIRECTORY_SEPARATOR . "{$request_hash}.html";
    $cached_file = $this->read_cache( $cache_filename );
    if ( ! $cached_file ) {
      $cached_file = $this->write_cache( $filename );
      if ( $cached_file ) {
        $cached_file = $this->read_cache( $cache_filename );
      } else {
        die('Failed to read/write cache.');
      }
    }
  }

  /**
   * Get cached HTML file.
   * Call write_cache if no file found or out of date.
   *
   * @return void
   */
  private function read_cache( string $cache_filename ) {
    if ( file_exists( $cache_filename ) ) {
      global $config;
      $changed = filectime( $cache_filename );
      if ( -1 !== $this->config->cache_time && ( time() - $changed ) >= $this->config->cache_time ) {
        return false;
      }
      $fh = fopen( $cache_filename, 'r' );
      $data = fread( $fh, filesize( $cache_filename ) );
      fclose( $fh );
      header( 'Content-Type:text/html' );
      header( 'X-Generator: Saisho ' . SAISHO_VERSION );
      ob_start();
      echo $data;
      ob_flush();
      ob_end_clean();
      return true;
    }
    else {
      return false;
    }
  }

  private function include_template( string $template_file ) {
    ob_start();
    include ( TEMPLATE_FOLDER . DIRECTORY_SEPARATOR . $template_file );
    return ob_get_clean();
  }

  /**
   * Load template file and inject content
   *
   * @param  object $page
   * @return string html
   */
  private function load_template( array $page, string $template_file = 'home.php' ) {
    if ( file_exists( TEMPLATE_FOLDER . DIRECTORY_SEPARATOR . $template_file ) ) {
      $output = $this->include_template( $template_file );
      $search = ['{title}','{content}','{description}'];
      $replace = [($page['title'])??'',($page['content'])??'',($page['description'])??''];
      return (string) str_replace( $search, $replace, $output );
    }
  }

  /**
   * Write cached HTML file and return it.
   *
   * @param  string $filename
   * @return void
   */
  private function write_cache( $filename ) {
    if ( 'page' === $this->where ) {
      $filename = DATA_FOLDER . DIRECTORY_SEPARATOR . "{$filename}.md";
    } elseif ( 'home' === $this->where ) {
      global $config;
      $filename = DATA_FOLDER . DIRECTORY_SEPARATOR . "{$this->config->home_page}.md";
    } elseif ( 'not_found' === $this->where ) {

    }
    $crc32 = crc32( $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] );
    $cache_filename = CACHE_FOLDER . DIRECTORY_SEPARATOR . "{$crc32}.html";
    $page = $this->parse_page( $filename );
    $template = $this->load_template( $page );
    if ( is_null( $page['content'] ) ) {
      return false;
    }
    $cache_handler = fopen( $cache_filename, 'w' );
    if ( ! $cache_handler ) {
      die( 'Failed to open ' . $cache_filename );
    }
    fwrite( $cache_handler, $template );
    fclose( $cache_handler );
    return true;
  }

  /**
   * get_list
   *
   * @param  string $sort sorting methods: by_date, by_name
   * @param  string $order order: asc, desc
   * @param  bool   $metadata return metadata?
   * @return array list of pages
   */
  public function get_list( string $sortby = 'by_date', string $order = 'desc', bool $metadata = true ) {
    global $config;
    $file_list = glob( DATA_FOLDER . DIRECTORY_SEPARATOR . '*.md' );
    $files = [];
    foreach ( $file_list as $file ) {
      $file                     = basename( $file );
      if ( basename( $file, '.md' ) === $this->config->home_page ) {
        continue;
      }
      $files[ $file ]['filename'] = $file;
      $files[ $file ]['url']      = HOME_URI . DIRECTORY_SEPARATOR . pathinfo( $files[ $file ]['filename'], PATHINFO_FILENAME );
      $files[ $file ]['updated']  = filectime( DATA_FOLDER . DIRECTORY_SEPARATOR . $files[ $file ]['filename'] );
      if ( $metadata ) {
        $files[ $file ]['metadata'] = $this->get_metadata( $files[ $file ]['filename'] );
      }
    }
    $files = $this->sort_files( $files, $sortby, $order );
    return (array) $files;
  }
  
  /**
   * Page filter
   *
   * @param  array $pages array of pages from get_list()
   * @param  mixed $metadata metadata to filter for e.g. flags
   * @param  mixed $filter filter value e.g. hide
   * @param  mixed $exclude false to exclude results, true to only retrieve result
   * @return void
   */
  public function filter( array $pages, string $metadata, $filter, bool $exclude = false ) {
    $only = [];
    foreach ( $pages as $page=>$data ) {
      if ( array_key_exists( $metadata, $data['metadata'] ) && strpos($data['metadata'][$metadata], $filter) !== false ) {
        if ( $exclude ) {
          unset( $pages[ $page ] );
        } else {
          $only[$page] = $data;
        }
      }
    }
    if ( $exclude ) {
      return (array) $pages;
    }
    return (array) array_intersect_key( $pages, $only );
  }

  private function sort_files(array $files, string $sortby = 'by_date', string $order = 'desc') {
    if ( $sortby === 'by_date' ) {
      if ( $order === 'desc' ) {
        uasort( $files, array( $this, 'sort_by_date_desc' ) );
      } else {
        uasort( $files, array( $this, 'sort_by_date_asc' ) );
      }
    } elseif ( $sortby === 'by_title' ) {
      if ( $order === 'desc' ) {
        uasort( $files, array( $this, 'sort_by_title_desc' ) );
      } else {
        uasort( $files, array( $this, 'sort_by_title_asc' ) );
      }
    } elseif ( $sortby === 'by_position' ) {
      if ( $order === 'desc' ) {
        uasort( $files, array( $this, 'sort_by_position_desc' ) );
      } else {
        uasort( $files, array( $this, 'sort_by_position_asc' ) );
      }
    }
    return (array) $files;
  }

  private function sort_by_date_desc( $a, $b ) {
    if ( ! (isset($a['metadata']['date']) && isset($b['metadata']['date']) ) ) {
      return 0;
    }
    return $a['metadata']['date'] < $b['metadata']['date'];
  }

  private function sort_by_date_asc( $a, $b ) {
    if ( ! (isset($a['metadata']['date']) && isset($b['metadata']['date']) ) ) {
      return 0;
    }
    return $a['metadata']['date'] > $b['metadata']['date'];
  }

  private function sort_by_title_desc( $a, $b ) {
    if ( ! (isset($a['metadata']['title']) && isset($b['metadata']['title']) ) ) {
      return 0;
    }
    return $a['metadata']['title'] < $b['metadata']['title'];
  }

  private function sort_by_title_asc( $a, $b ) {
    if ( ! (isset($a['metadata']['title']) && isset($b['metadata']['title']) ) ) {
      return 0;
    }
    return $a['metadata']['title'] > $b['metadata']['title'];
  }

  private function sort_by_position_desc( $a, $b ) {
    if ( ! (isset($a['metadata']['position']) && isset($b['metadata']['position']) ) ) {
      return 0;
    }
    return $a['metadata']['position'] < $b['metadata']['position'];
  }

  private function sort_by_position_asc( $a, $b ) {
    if ( ! (isset($a['metadata']['position']) && isset($b['metadata']['position']) ) ) {
      return 0;
    }
    return $a['metadata']['position'] > $b['metadata']['position'];
  }

  /**
   * Clear cache
   *
   * @param  string $filename optinal filename. Will clear all if empty.
   * @return void
   */
  public function clear_cache(string $filename = '') {
    if ( ! empty( $filename ) ) {
      $this->write_cache( $filename );
    }
    else {
      foreach ( $this->get_list( true ) as $filename ) {
        $this->write_cache( $filename );
      }
    }
  }

  public function build_rss() {
    global $config;
    $home = $this->get_page('home');
    $pages = $this->get_list( 'by_date', 'desc' );
    $xml = '
    <rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
    <channel>
    <title>'.$home['title'].'</title>
    <link>'.$config->host.'</link>
    <description>'.$home['description'].'</description>
    <generator>Saisho '.SAISHO_VERSION.'</generator>
    <language>en-us</language>
    <lastBuildDate>'.date('r').'</lastBuildDate>
    <atom:link href="'.$config->host.'/index.xml" rel="self" type="application/rss+xml"/>'.PHP_EOL;
    foreach ( $pages as $page ) {
      $xml .= '<item>'.PHP_EOL;
      $xml .= '<title>' . $page['metadata']['title'] . '</title>'.PHP_EOL;
      $xml .= '<link>' . $page['url'] . '</link>'.PHP_EOL;
      $xml .= '<pubDate>' . date('r', $page['updated']) . '</pubDate>'.PHP_EOL;
      $xml .= '<description>' . (($page['metadata']['description'])?? '') . '</description>'.PHP_EOL;
      $xml .= '</item>'.PHP_EOL;
    }
    $xml .= '</channel></rss>';
    return $xml;
  }

  public function rss( $filename = 'feed.xml' ) {
    global $config;
    if ( ( time() - filemtime($filename) >= $config->feed_time ) || ! file_exists( $filename ) ) {
      $fh = fopen( $filename, 'w' );
      if ( ! $fh ) {
        return false;
      }
      fwrite( $fh, $this->build_rss() );
      fclose( $fh );
      return $config->host . DIRECTORY_SEPARATOR . 'feed.xml';
    }
  }

}
$saisho = new Saisho( $config );
