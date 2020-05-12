<?php
/*

      最小  —  Saishō

*/

$time = microtime( true );
$where = '';
$files = [];

define( 'SAISHO', true );
$config = include ( 'config.php' );
define( 'HOME_URI', $config->host );
define( 'DATA_FOLDER', $config->data_folder );
define( 'CACHE_FOLDER', $config->cache_folder );
define( 'TEMPLATE_FOLDER', $config->template_folder );
require ( 'inc/Parsedown.php' );
require ( 'inc/ParsedownExtra.php' );

class Saisho {

  public function __construct() {
    $this->where = ( $_SERVER['REQUEST_URI'] === '/' ) ? 'home' : 'page';
    $this->read_cache();
  }

  /**
   * Return configuration object
   *
   * @return object
   */
  public function get_config() {
    global $config;
    return $config;
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
    $page = [];
    preg_match_all ( '/(?:\[\[)(.*)(?:\]\] )(.*)\n?/', $data, $matches, PREG_SET_ORDER );
    foreach ( $matches as $match ) {
      $page[ $match[ 1 ] ] = $match[ 2 ];
    }
    $data = preg_replace( '/(?:\[\[.*\]\] ).*\n?/', '', $data );
    if ( $content ) {
      $page['content'] = $this->parse_markdown( $data );
    }
    return $page;
  }

  /**
   * Parse Markdown
   *
   * @param  string $data data to parse
   * @return string
   */
  private function parse_markdown( string $data ) {
    $parsedown = new ParsedownExtra();
    return $parsedown->text( $data );
  }
  
  /**
   * Get page
   *
   * @param  string $page page file name
   * @param  mixed $metadata_only return metadata alone?
   * @return void
   */
  public function get_page( string $page, bool $metadata_only = false ) {
    if ( file_exists( DATA_FOLDER . DIRECTORY_SEPARATOR . $page ) ) {
      if ( $metadata_only ) {
        return $this->parse_page( DATA_FOLDER . DIRECTORY_SEPARATOR . $page, false );
      }
      return $this->parse_page( DATA_FOLDER . DIRECTORY_SEPARATOR . $page );
    }
  }
  
  /**
   * Get page metadata
   *
   * @param  mixed $page
   * @return void
   */
  public function get_metadata( string $page ) {
    return $this->get_page( $page, true );
  }

  /**
   * Get cached HTML file.
   * Call write_cache if no file found or out of date.
   *
   * @return void
   */
  public function read_cache() {
    $request = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    $request_hash = md5( $request );
    $filename = substr( $_SERVER['REQUEST_URI'], 1 );
    $cache_filename = "cache/{$request_hash}.html";
    $now = time();
    if ( file_exists( $cache_filename ) ) {
      global $config;
      $changed = filectime( $cache_filename );
      if ( -1 !== $config->cache_time && ( $now - $changed ) >= $config->cache_time ) {
        $this->write_cache( $filename );
        return;
      }
      $fh = fopen( $cache_filename, 'r' );
      $data = fread( $fh, filesize( $cache_filename ) );
      fclose( $fh );
      header( 'Content-Type:text/html' );
      ob_start();
      echo $data;
      ob_flush();
      ob_end_clean();
    }
    else {
      $this->write_cache( $filename );
    }
  }

  public function include_template() {
    ob_start();
    include ( TEMPLATE_FOLDER . '/home.php' );
    return ob_get_clean();
  }

  /**
   * Load template file and inject content
   *
   * @param  object $page
   * @return string html
   */
  public function load_template( array $page ) {
    if ( file_exists( TEMPLATE_FOLDER . '/home.php' ) ) {
      $output = $this->include_template();
      $output = preg_replace( '/{title}/' , ($page['title'])??'', $output );
      $output = preg_replace( '/{content}/', $page['content'], $output );
      $output = preg_replace( '/{description}/', ($page['description'])??'', $output );
      return $output;
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
      $filename = "data/{$filename}.md";
    }
    elseif ( 'home' === $this->where ) {
      global $config;
      $filename = "data/{$config->home_page}.md";
    }
    if ( ! file_exists( $filename ) ) {
      http_response_code( 404 );
      die();
    }
    $md5 = md5( $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'] );
    $cache_filename = "cache/{$md5}.html";
    $page = $this->parse_page( $filename );
    $template = $this->load_template( $page );
    if ( is_null( $page['content'] ) ) {
      return;
    }
    $cache_handler = fopen( $cache_filename, 'w' );
    fwrite( $cache_handler, $template );
    fclose( $cache_handler );
    $this->read_cache();
  }

  /**
   * get_list
   *
   * @param  string $sort sorting methods: by_date, by_name
   * @param  string $order order: asc, desc
   * @return array list of pages
   */
  public function get_list( string $sortby = 'by_date', string $order = 'desc' ) {
    global $config;
    $file_list = glob( DATA_FOLDER . DIRECTORY_SEPARATOR . '*.md' );
    $files = [];
    foreach ( $file_list as $file ) {
      $file                     = basename( $file );
      if ( substr($file, 0, -3) === $config->home_page ) {
        continue;
      }
      $files[ $file ]['filename'] = $file;
      $files[ $file ]['url']      = HOME_URI . DIRECTORY_SEPARATOR . pathinfo( $files[ $file ]['filename'], PATHINFO_FILENAME );
      $files[ $file ]['updated']  = filectime( DATA_FOLDER . DIRECTORY_SEPARATOR . $file );
      $files[ $file ]['metadata'] = $this->get_metadata( $file );
    }
    $files = $this->sort_files( $files, $sortby, $order );
    return $files;
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
      return $pages;
    }
    return array_intersect_key( $pages, $only );
  }

  function sort_files(array $files, string $sortby = 'by_date', string $order = 'desc') {
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
    }
    return $files;
  }

  function sort_by_date_desc( $a, $b ) {
    return $a['metadata']['date'] < $b['metadata']['date'];
  }

  function sort_by_date_asc( $a, $b ) {
    return $a['metadata']['date'] > $b['metadata']['date'];
  }

  function sort_by_title_desc( $a, $b ) {
    return $a['metadata']['title'] < $b['metadata']['title'];
  }

  function sort_by_title_asc( $a, $b ) {
    return $a['metadata']['title'] > $b['metadata']['title'];
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

}

$saisho = new Saisho();

echo '<code class="g">' . round((microtime(true) - $time) * 1000, 2) . 'ms</code><br>';