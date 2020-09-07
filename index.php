<?php
/*
最小  —  Saishō MK. 2
https://paulglushak.com/saisho
*/
define('SAISHO_VERSION', '20200830');
define('META_TITLE', 'Saisho');
define('META_DESCRIPTION', 'Very (VERY) simple and quick semi-static site engine');
define('HOST', '//' . $_SERVER['HTTP_HOST']);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . DS . 'data');
define('TEMPLATE_DIR', ROOT_DIR . DS . 'template');
define('CACHE_DIR', ROOT_DIR . DS . 'cache');
define('INC_DIR', ROOT_DIR . DS . 'inc');
define('CACHE_TIME', 1000);
define('TAGS_TO_HIDE', ['hide']);
class Saisho {
    public $requestedPage, $pagePath, $cachedPagePath, $time;

    public function __construct()
    {
        $this->setPagePaths($this->getRequestedPage());
        $this->time = microtime(true);
        $this->handleRequest($this->requestedPage);
    }

    /**
     * Get the requested page from the URI
     *
     * @return string
     */
    public function getRequestedPage() {
        return (string)trim($_SERVER['REQUEST_URI'], '/');
    }
    /**
     * Set the data and cache page paths for the requested page
     *
     * @param string $page the requested page
     * @return void
     */
    public function setPagePaths(string $page) {
        $this->requestedPage = $page;
        $this->pagePath = DATA_DIR . DS . $this->requestedPage. '.md';
        $this->cachedPagePath = CACHE_DIR . DS . $this->requestedPage. '.html';
    }
    /**
     * Request handle aka router.
     * Constructs page object based on requested page where empty is a list and a string is a page.
     *
     * @param string $requestedPage
     * @return void
     */
    public function handleRequest(string $requestedPage) {
        switch ($requestedPage) {
            case '':
                $list = $this->listPages();
                $page = (object)['type' => 'list', 'content' => $this->renderPageList($list, true) ];
            break;
            case 'projects':
                $list = $this->listPages();
                $page = (object)['type'=>'list', 'content'=>$this->renderPageList($list, 'project')];
            break;
            default:
                if (file_exists($this->pagePath)) {
                    if (!$this->tryCache($this->cachedPagePath)) {
                        $page = (object)['type' => 'page', 'content' => $this->renderPage($this->pagePath) ];
                    }
                } else {
                    http_response_code(404);
                    $this->setPagePaths('notfound');
                    $page = (object)['type' => 'notfound', 'content' => ''];
                }
            break;
        }
        $this->servePage($page->type, ['content'=>$page->content]);
    }
    /**
     * Returns a list of the available pages in the DATA_DIR folder.
     *
     * @return array page list
     */
    public function listPages() {
        $pages = glob(DATA_DIR . DS . '*.md');
        $list = [];
        foreach ($pages as $page) {
            $pageName = str_replace('.md', '', basename($page));
            $list[$pageName] = $page;
        }
        return (array)$list;
    }
    
    /** 
     * Sorts a list of pages based on date attribute.
     * 
     * @return array sorted page list
     */
    public function sortPageList(array & $list) {
        uasort($list, function ($a, $b) {
            return strtotime($b->date) - strtotime($a->date);
        });
        return (array)$list;
    }
    /**
     * Parses a list of pages and filters.
     *
     * @param array $list list of pages
     * @param mixed $filter true for hidden tags, string for specific tag
     * @return array parsed and filtered list of pages
     */
    public function parsePageList(array $list, $filter) {
        if (empty($list)) {
            return;
        }
        $parsedPages = [];
        foreach ($list as $name => $path) {
            $page = $this->renderPage($path, true);
            $parsedPages[$name] = $page;
        }
        return (array)array_filter($parsedPages, function ($item) use ($filter) {
            if ($filter === true) {
                return !array_intersect(TAGS_TO_HIDE, $item->tags);
            } elseif (is_string($filter)) {
                return in_array($filter, $item->tags);
            } else {
                return 1;
            }
        });
    }
    /**
     * Creates the HTML for a list of pages.
     *
     * @param array $list a list of pages
     * @param mixed $filter true for hidden tags, string for specific tag
     * @return string OL list of pages
     */
    public function renderPageList(array $list, $filter) {
        if (empty($list)) {
            return '<h1>ʅ(°⊱,°)ʃ Nothing found</h1>';
        }
        $list = $this->parsePageList($list, $filter);
        $this->sortPageList($list);
        $html = '<ol reversed class="pl">' . PHP_EOL;
        foreach ($list as $handle => $page) {
            if (isset($page->style)) {
                $style = ' style="' . $page->style . '"';
            } else {
                $style = '';
            }
            if (!empty($page->tags)) {
                $tags = ' [' . implode(', ', $page->tags) . ']';
            } else {
                $tags = '';
            }
            // $html.= "<li><a{$style} href='{$handle}'>{$page->title}</a> <span class='g'> {$page->date}{$tags}</span></li>" . PHP_EOL;
            $html.= "<li><span class='g'>{$page->date}</span> <a{$style} href='{$handle}'>{$page->title}</a></li>" . PHP_EOL;
        }
        $html.= '</ol>' . PHP_EOL;
        return (string)$html;
    }
    /**
     * Get raw content of page
     *
     * @param string $pageName the page to read
     * @return string raw page content
     */
    private function getRawContent(string $pageName) {
        return (string) file_get_contents($pageName);
    }
    /**
     * Parse page YAML Front Matter header, return an array of attributes and body.
     *
     * @param string $str raw page content to parse
     * @return array array of page attributes
     */
    private function parseYaml(string $str) {
        $parsed = [];
        preg_match("'^---(.+?)---'si", $str, $yaml);
        if (!isset($yaml[0])) {
            return (object)['body' => $str];
        }
        $str = str_replace($yaml[0], '', $str);
        $parsed['body'] = $str;
        $yaml = trim($yaml[0]);
        preg_match_all("'(\w+):\s?(.+)'m", $yaml, $yaml_attribs, PREG_SET_ORDER);
        foreach ($yaml_attribs as $attribute) {
            $parsed[$attribute[1]] = trim($attribute[2]);
        }
        return (array) $parsed;
    }
    /**
     * Render page markdown and assign meta values. If no value present, required
     * attributes are given default values.
     *
     * @param string $pageName
     * @param boolean $onlyYaml if true only returns page metadata with no body.
     * @return object page object
     */
    public function renderPage(string $pageName, bool $onlyYaml = false) {
        include_once INC_DIR . DS . 'Parsedown.php';
        include_once INC_DIR . DS . 'ParsedownExtra.php';
        include_once INC_DIR . DS . 'ParsedownExtended.php';
        $contentRaw = $this->getRawContent($pageName);
        $page = $this->parseYaml($contentRaw);
        $page['title'] = $page['title']??'untitled';
        $page['date'] = $page['date']??'1969-01-01';
        $page['modified'] = filectime($pageName);
        $page['tags'] = isset($page['tags']) ? explode(',', $page['tags']) : [];
        $parsedown = new ParsedownExtended(["toc" => ["enable" => true, "inline" => true], "mark" => true, "insert" => true, "task" => true, "kbd" => true]);
        if (!$onlyYaml) {
            $page['body'] = $parsedown->text($page['body']);
        } else {
            unset($page['body']);
        }
        return (object)$page;
    }
    /**
     * Construct XML RSS feed
     *
     * @return string RSS feed
     */
    private function buildFeed() {
        $pages = $this->listPages();
        $pages = $this->parsePageList($pages, true);
        $xml = '
        <rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
        <channel>
        <title>' . META_TITLE . '</title>
        <link>' . HOST . '</link>
        <description>' . META_DESCRIPTION . '</description>
        <generator>Saisho</generator>
        <language>en-us</language>
        <lastBuildDate>' . date('r') . '</lastBuildDate>
        <atom:link href="' . HOST . '/feed.xml" rel="self" type="application/rss+xml"/>' . PHP_EOL;
        foreach ($pages as $handle => $page) {
            $xml.= '<item>' . PHP_EOL;
            $xml.= '<title>' . $page->title . '</title>' . PHP_EOL;
            $xml.= '<link>' . HOST . DS . $handle . '</link>' . PHP_EOL;
            $xml.= '<pubDate>' . date('r', strtotime($page->date)) . '</pubDate>' . PHP_EOL;
            $xml.= '<description>' . ($page->description??'') . '</description>' . PHP_EOL;
            $xml.= '</item>' . PHP_EOL;
        }
        $xml.= '</channel></rss>';
        return $xml;
    }
    /**
     * Save RSS feed
     *
     * @param string $filename filename to use, feed.xml by default.
     * @return string RSS feed URL
     */
    public function saveFeed($filename = 'feed.xml') {
        if ((time() - filectime($filename) >= CACHE_TIME) || !file_exists($filename)) {
            $fh = fopen($filename, 'w');
            if (!$fh) {
                return false;
            }
            fwrite($fh, $this->buildFeed());
            fclose($fh);
            return HOST . DS . 'feed.xml';
        }
    }
    public function servePage(string $template, array $args) {
        $render = $this->renderTemplate( $template, $args);
        if ($this->requestedPage!== '') {
            file_put_contents($this->cachedPagePath, $render);
            $this->saveFeed();
        }
        echo $render;
    }
    /**
     * Try serving page from cached HTML.
     * Returns false if page not found or out of date.
     *
     * @param string $filePath file to read
     * @return void|bool reads the file and exits if cache exits, returns false if not
     */
    private function tryCache(string $filePath) {
        $filectime = @filectime($filePath);
        if ($filectime && (time() - $filectime <= CACHE_TIME)) {
            readfile($filePath);
            echo '<pre>' . round(((microtime(true) - $this->time) * 1000), 3) . 'ms</pre>';
            exit;
        }
        return false;
    }
    /**
     * Render template and return result from output buffer
     *
     * @param string $template template name
     * @param array $args arguments to pass
     * @return void
     */
    private function renderTemplate(string $template, array $args)
    {
        ob_start();
        if (!file_exists(TEMPLATE_DIR.DS.$template.'.php')) return (string) "Template $template.php not found!";
        $page = (object)[];
        foreach ( $args as $key => $value) {
            $page->{$key} = &$value;
        }
        if ($this->requestedPage!== '') {
            header('Last-Modified:' . gmdate('D, d M Y H:i:s ', $page->content->modified??time()) . 'GMT');
            echo '<!-- Saisho Cached Copy - ' . date('Y-m-d h:i:s') . ' -->' . PHP_EOL;
        }
        include TEMPLATE_DIR.DS.$template.'.php';
        return ob_get_clean();
    }
    public function debug($var) {
        echo '<pre>' . var_export($var, true) . '</pre>';
    }
}
$saisho = new Saisho();
