<?php
/*
最小  —  Saishō MK. 2
https://paulglushak.com/saisho
 */
$time = microtime(true);
define('SAISHO_VERSION', '20200725');
define('META_TITLE', 'Saisho');
define('META_DESCRIPTION', 'Saisho Website');
define('HOST', '//' . $_SERVER['HTTP_HOST']);
define('DS', DIRECTORY_SEPARATOR);
define('ROOT_DIR', __DIR__);
define('DATA_DIR', ROOT_DIR . DS . 'data');
define('CACHE_DIR', ROOT_DIR . DS . 'cache');
define('INC_DIR', ROOT_DIR . DS . 'inc');
define('CACHE_TIME', 600);

class Saisho
{
    public function getRequestedPage()
    {
        return (string) trim($_SERVER['REQUEST_URI'], '/');
    }

    public function handleRequest(string $requestedPage)
    {
        if ($requestedPage == '') { /* Empty request - Homepage */
            $list = $this->listPages();
            return (object) ['type' => 'list', 'content' => $this->renderPageList($list)];
        }
        $pagePath = DATA_DIR . DS . $requestedPage . '.md';
        if ($this->pageExists($pagePath)) {
            return (object) ['type' => 'page', 'content' => $this->renderPage($pagePath)];
        } else {
            http_response_code(404);
            return (object) ['type' => 'notfound', 'content' => '<h1>(ノಠ益ಠ)ノ彡 Not found</h1>'];
        }
    }

    public function listPages()
    {
        $pages = glob(DATA_DIR . DS . '*.md');
        $list = [];
        foreach ($pages as $page) {
            $pageName = str_replace('.md', '', basename($page));
            $list[$pageName] = $page;
        }
        return $list;
    }

    public function sortPageList(array &$list)
    {
        uasort($list, function ($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });
        return (object) $list;
    }

    public function parsePageList(array $list)
    {
        if (!empty($list)) {
            $parsedPages = [];
            foreach ($list as $name => $path) {
                $page = $this->renderPage($path, true);
                $pageProperties = get_object_vars($page);
                foreach ($pageProperties as $property => $value) {
                    $parsedPages[$name][$property] = $value;
                }
            }
            return (array) $parsedPages;
        } else {
            return false;
        }
    }

    public function renderPageList(array $list)
    {
        if (!empty($list)) {
            $list = $this->parsePageList($list);
            $this->sortPageList($list);
            $html = '<ol reversed>' . PHP_EOL;
            foreach ($list as $handle => $page) {
                $html .= "<li><a href='{$handle}'>{$page['title']}</a> &mdash; {$page['date']}</li>" . PHP_EOL;
            }
            $html .= '</ol>' . PHP_EOL;
            return (string) $html;
        } else {
            return '<h1>ʅ(°⊱,°)ʃ Nothing found</h1>';
        }
    }

    private function pageExists(string $pageName)
    {
        return file_exists($pageName);
    }

    private function getRawContent(string $pageName)
    {
        return (string) file_get_contents($pageName);
    }

    private function parseYaml(string &$str)
    {
        $parsed = [];
        preg_match("'^---(.+?)---'si", $str, $yaml);
        if (isset($yaml[0])) {
            $str = str_replace($yaml[0], '', $str);
            $parsed['body'] = $str;
            $yaml = trim($yaml[0]);
            preg_match_all("'(\w+):\s?(.+)'m", $yaml, $yaml_attribs, PREG_SET_ORDER);
            foreach ($yaml_attribs as $attribute) {
                $parsed[$attribute[1]] = trim($attribute[2]);
            }
            return (object) $parsed;
        } else {
            return (object) ['body' => $str];
        }
    }

    public function renderPage(string $pageName, bool $onlyYaml = false)
    {
        include_once INC_DIR . DS . 'Parsedown.php';
        include_once INC_DIR . DS . 'ParsedownExtra.php';
        include_once INC_DIR . DS . 'ParsedownExtended.php';
        $contentRaw = $this->getRawContent($pageName);
        $page = $this->parseYaml($contentRaw);
        $page->title = $page->title ?? 'untitled'; /* Set default values if missing */
        $page->date = $page->date ?? '1969-01-01';
        $parsedown = new ParsedownExtended(["toc" => ["enable" => true, "inline" => true], "mark" => true, "insert" => true, "task" => true, "kbd" => true]);
        if (!$onlyYaml) {
            $page->body = $parsedown->text($page->body);
        } else {
            unset($page->body);
        }
        return (object) $page;
    }

    private function buildFeed()
    {
        $pages = $this->listPages();
        $pages = $this->parsePageList($pages);
        if ($pages) {
            $xml = '<rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
          <channel>
          <title>' . META_TITLE . '</title>
          <link>' . HOST . '</link>
          <description>' . META_DESCRIPTION . '</description>
          <generator>Saisho</generator>
          <language>en-us</language>
          <lastBuildDate>' . date('r') . '</lastBuildDate>
          <atom:link href="' . HOST . '/feed.xml" rel="self" type="application/rss+xml"/>' . PHP_EOL;
            foreach ($pages as $handle => $page) {
                $xml .= '<item>' . PHP_EOL;
                $xml .= '<title>' . $page['title'] . '</title>' . PHP_EOL;
                $xml .= '<link>' . HOST . DS . $handle . '</link>' . PHP_EOL;
                $xml .= '<pubDate>' . date('r', strtotime($page['date'])) . '</pubDate>' . PHP_EOL;
                $xml .= '<description>' . ($page['description'] ?? '') . '</description>' . PHP_EOL;
                $xml .= '</item>' . PHP_EOL;
            }
            $xml .= '</channel></rss>';
            return $xml;
        }
    }

    public function saveFeed($filename = 'feed.xml')
    {
        if ((time() - filemtime($filename) >= CACHE_TIME) || !file_exists($filename)) {
            $fh = fopen($filename, 'w');
            if ($fh) {
                fwrite($fh, $this->buildFeed());
                fclose($fh);
                return HOST . DS . 'feed.xml';
            }
        }
    }
}

$saisho = new Saisho();
$pageName = $saisho->getRequestedPage();
$page = $saisho->handleRequest($pageName);
$filePath = CACHE_DIR . DS . $pageName . '.html';
header('x-generator: Saisho Mk.2 ' . SAISHO_VERSION);
header('Cache-Control: public, max-age=' . CACHE_TIME . ', immutable');
if (($pageName !== '') && (time()-@filemtime($filePath) <= CACHE_TIME)) {
    header('Last-Modified:' . gmdate('D, d M Y H:i:s ', @filemtime($filePath)) . 'GMT');
    readfile($filePath);
    exit;
}
ob_start();
echo '<!-- Saisho Cached Copy - ' . date('Y-m-d h:i:s') . ' -->' . PHP_EOL;
?>
<!DOCTYPE html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="data:," rel="icon">
  <link href="feed.xml" rel="alternate" type="application/rss+xml" title="<?php echo META_TITLE ?> RSS Feed">
  <title><?= isset($page->content->title) ? $page->content->title . ' &mdash; ' . META_TITLE : META_TITLE; ?>
  </title>
  <style>
    body { font: 17px/1.6 sans-serif; text-rendering: optimizeLegibility; padding: 2rem }
    pre { white-space: pre-wrap }
    img { image-rendering: pixelated; max-width: 100% }
    a, a:visited { color: initial }
    a:hover, a.heading-link { text-decoration: none }
    .c { max-width: 70ch }
    .fa-link:before { font-style: normal; content: "§" }
    @media screen and (max-width: 800px) { .c { max-width: 100% } }
  </style>
</head>
<body>
  <div class="c">
    <?php
    switch ($page->type) {
        case 'list':
            echo '<h1>' . META_TITLE . '</h1>';
            echo '<div>' . $page->content . '</div>';
            break;
        case 'page':
            echo '<a href="' . HOST . '">↩</a> / <time datetime="' . $page->content->date . '">' . $page->content->date . '</time>';
            echo <<<EOD
                        <h1>{$page->content->title}</h1>
                        <div class="content">{$page->content->body}</div>
                        EOD;
            break;
        case 'notfound':
            echo '<a href="' . HOST . '">escape</a>';
            echo $page->content;
            break;
        default:
            echo '<a href="' . HOST . '">escape</a>';
            echo "Sorry, don't know what {$page->type} is.";
            break;
    }
    ?>
  </div>
</body>
</html>
<?php
if ($pageName !== '') {
    file_put_contents($filePath, ob_get_contents());
    ob_end_flush();
}
$saisho->saveFeed();