<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable (__DIR__ . DIRECTORY_SEPARATOR . '..');
$dotenv->load ();
$dotenv->required (['DOCUMENTATION_URL', 'SITEMAP_URL'])->notEmpty ();

define ("DOCUMENTATION_URL", $_ENV ['DOCUMENTATION_URL']);
define ("SITEMAP_URL", $_ENV ['SITEMAP_URL']);
const TEMPLATE_FOLDER = __DIR__ . '/../template' . DIRECTORY_SEPARATOR;
const OUTPUT_FOLDER = __DIR__ . '/../html' . DIRECTORY_SEPARATOR;
const IMAGE_RELATIVE_PATH = 'images/'; // For the HTML src attribute
const IMAGE_FOLDER = OUTPUT_FOLDER . IMAGE_RELATIVE_PATH;
const LOG_FILE = OUTPUT_FOLDER . 'generate.log';

// Track files produced this run so we can sweep stale ones at the end.
$writtenImages = [];
$writtenHtml = [];
$imageStats = ['new' => 0, 'existing' => 0, 'failed' => 0, 'deleted' => 0];
$htmlStats = ['written' => 0, 'deleted' => 0];
$runStart = microtime (true);

$html = file_get_contents (DOCUMENTATION_URL);
if ($html === false)
{
    echo "Could not retrieve URL " . DOCUMENTATION_URL . ". Aborting.";
    exit ();
}

$dom = new DOMDocument();
if ($dom->loadHTML ('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR) === false)
{
    echo "Could not parse HTML. Aborting.";
    exit ();
}

sideloadImages($dom);

file_put_contents (OUTPUT_FOLDER . "full.html", $dom->saveHTML ());

$xPath = new DOMXPath ($dom);

$style = $xPath->query ('/html/body/div[@id="contents"]/style')
               ->item (0)->nodeValue;
$style = preg_replace ('/line-height\s*:\s*[0-9.]+;/s', '', $style);
$styleHash = md5 ($style);
file_put_contents (OUTPUT_FOLDER . 'default.css', $style);

$templateCssHash = md5_file (TEMPLATE_FOLDER . "/custom.css");

$menuChange = [];
$outline = [];
$h1id = null;
$h2id = null;
$h3id = null;

// To find the nodes, look for the first h1, then use that parent node.
$h1 = $xPath->query ('//h1');

/* @var DOMNode[] $nodes */
$nodes = $h1->item(0)->parentNode->childNodes;

foreach ($nodes as $node)
{
    if ($node->nodeName == 'h1' && $node->attributes->getNamedItem ('id') instanceof DOMNode)
    {
        $h1id = $node->attributes->getNamedItem ('id')->textContent;
        $filename = count ($outline) == 0 ? "index.html" : sanitiseTextForLink ($node->textContent) . ".html";

        $outline [$h1id] ['heading'] = $node->textContent;
        $outline [$h1id] ['newlink'] = $filename;
        $outline [$h1id] ['content'] = [];

        $menuChange [$h1id] = $outline [$h1id] ['newlink'];
        $h2id = null;
        $h3id = null;
    }
    elseif ($node->nodeName == 'h2' && $node->attributes->getNamedItem ('id') instanceof DOMNode)
    {
        $h2id = $node->attributes->getNamedItem ('id')->textContent;
        $outline [$h1id] [$h2id] ['heading'] = $node->textContent;
        $outline [$h1id] [$h2id] ['id'] = sanitiseTextForLink ($node->textContent);
        $outline [$h1id] [$h2id] ['newlink'] = $outline [$h1id] ['newlink'] . "#" . $outline [$h1id] [$h2id] ['id'];
        $outline [$h1id] [$h2id] ['content'] = [];

        $menuChange [$h2id] = $outline [$h1id] [$h2id] ['newlink'];
        $h3id = null;
    }
    elseif ($node->nodeName == 'h3' && $node->attributes->getNamedItem ('id') instanceof DOMNode)
    {
        $h3id = $node->attributes->getNamedItem ('id')->textContent;
        $outline [$h1id] [$h2id] [$h3id] ['heading'] = $node->textContent;
        $outline [$h1id] [$h2id] [$h3id] ['id'] = sanitiseTextForLink ($node->textContent);
        $outline [$h1id] [$h2id] [$h3id] ['newlink'] = $outline [$h1id] ['newlink'] . "#" . $outline [$h1id] [$h2id] [$h3id] ['id'];
        $outline [$h1id] [$h2id] [$h3id]['content'] = [];

        $menuChange [$h3id] = $outline [$h1id] [$h2id] [$h3id] ['newlink'];
    }
    elseif ($h1id === null)
    {
        // do nothing.
    }
    elseif ($h2id === null)
    {
        $outline [$h1id] ['content'] [] = $node->ownerDocument->saveHTML ($node);
    }
    elseif ($h3id === null)
    {
        $outline [$h1id] [$h2id] ['content'] [] = $node->ownerDocument->saveHTML ($node);
    }
    else
    {
        $outline [$h1id] [$h2id] [$h3id] ['content'] [] = $node->ownerDocument->saveHTML ($node);
    }
}

$sitemap = [];
$template = file_get_contents (TEMPLATE_FOLDER . 'template.html');

$matches = [];
preg_match_all ('/\#hash\(([a-z\-\.\/]+)\)/', $template, $matches);
if (count ($matches) == 2)
{
    foreach ($matches [0] as $index => $match)
    {
        $file = $matches [1] [$index];
        $hash = md5_file (TEMPLATE_FOLDER . $file);
        $template = str_replace ($match, $hash, $template);
    }
}

foreach ($outline as $h1 => $content)
{
    $filename = $outline [$h1] ['newlink'];
    $sitemap [] = SITEMAP_URL . $filename;
    $menu = getMenuStructure ($outline, $h1);
    $html = str_replace ("#title", $outline [$h1] ['heading'], $template);
    $html = str_replace ("#cssfile", "default.css?{$styleHash}", $html);
    $html = str_replace ("#templatecss", "custom.css?{$templateCssHash}", $html);
    $html = str_replace ("#menu", $menu, $html);
    $content = "<h1>" . $outline [$h1] ['heading'] . "</h1>";
    foreach ($outline [$h1] ['content'] as $htmlSnip)
    {
        $content .= relink ($htmlSnip);
    }
    foreach ($outline [$h1] as $h2 => $subheadings)
    {
        if (substr ($h2, 0, 2) == 'h.')
        {
            $content .= "<h2 id=\"{$outline [$h1] [$h2] ['id']}\">" . $outline [$h1] [$h2] ['heading'] . "</h2>";
            foreach ($outline [$h1] [$h2] ['content'] as $htmlSnip)
            {
                $content .= relink ($htmlSnip);
            }
            foreach ($outline [$h1] [$h2] as $h3 => $subheadings)
            {
                if (substr ($h3, 0, 2) == 'h.')
                {
                    $content .= "<h3 id=\"{$outline [$h1] [$h2] [$h3] ['id']}\">" . $outline [$h1] [$h2] [$h3] ['heading'] . "</h3>";
                    foreach ($outline [$h1] [$h2] [$h3] ['content'] as $htmlSnip)
                    {
                        $content .= relink ($htmlSnip);
                    }
                }
            }

            $content = imageFix ($content);
        }
    }
    $html = str_replace ("#body", $content, $html);

    $file = fopen (OUTPUT_FOLDER . $filename, "w");
    fwrite ($file, $html);
    fclose ($file);
    $writtenHtml [$filename] = true;
    $htmlStats ['written']++;
}

copy (TEMPLATE_FOLDER . 'custom.css', OUTPUT_FOLDER . 'custom.css');
copy (TEMPLATE_FOLDER . 'display.js', OUTPUT_FOLDER . 'display.js');
copy (TEMPLATE_FOLDER . 'logo.png', OUTPUT_FOLDER . 'logo.png');
copy (TEMPLATE_FOLDER . 'favicon.ico', OUTPUT_FOLDER . 'favicon.ico');

file_put_contents (OUTPUT_FOLDER . "sitemap.txt", implode ("\n", $sitemap));
file_put_contents (OUTPUT_FOLDER . "robots.txt", "User-agent: *
Allow: *
Sitemap: " . SITEMAP_URL . "sitemap.txt");

// Sweep stale files. Delete any .html in OUTPUT_FOLDER we didn't write this run,
// and any image in IMAGE_FOLDER we didn't touch. Template assets and the log
// file are preserved explicitly.
$protectedHtml = ['full.html' => true];
foreach (scandir (OUTPUT_FOLDER) as $file)
{
    if ($file === '.' || $file === '..') continue;
    $path = OUTPUT_FOLDER . $file;
    if (!is_file ($path)) continue;
    if (substr ($file, -5) !== '.html') continue;
    if (isset ($protectedHtml [$file])) continue;
    if (isset ($writtenHtml [$file])) continue;
    if (unlink ($path))
    {
        $htmlStats ['deleted']++;
    }
}

foreach (scandir (IMAGE_FOLDER) as $file)
{
    if ($file === '.' || $file === '..') continue;
    $path = IMAGE_FOLDER . $file;
    if (!is_file ($path)) continue;
    if (isset ($writtenImages [$file])) continue;
    if (unlink ($path))
    {
        $imageStats ['deleted']++;
    }
}

$duration = round (microtime (true) - $runStart, 2);
$logLines = [
    "Generation run: " . date ('Y-m-d H:i:s'),
    "Duration: {$duration}s",
    "",
    "Images:",
    "  New downloads:     {$imageStats ['new']}",
    "  Existing (reused): {$imageStats ['existing']}",
    "  Failed downloads:  {$imageStats ['failed']}",
    "  Deleted (stale):   {$imageStats ['deleted']}",
    "",
    "HTML pages:",
    "  Written:           {$htmlStats ['written']}",
    "  Deleted (stale):   {$htmlStats ['deleted']}",
    "",
];
file_put_contents (LOG_FILE, implode ("\n", $logLines));

function relink ($html)
{
    global $menuChange;
    $matches = [];
    preg_match_all ('/<a [^>]+href="#(h\.[a-zA-Z0-9]+)"/s', $html, $matches);
    foreach ($matches [1] as $match)
    {
        if (isset ($match) && isset ($menuChange [trim ($match)]))
        {
            $html = str_replace ("#{$match}", $menuChange [trim ($match)], $html);
        }
    }

    $matches = [];
    preg_match_all ('/<a\s[^>]*href="(?<oldurl>https:\/\/www\.google\.com\/url\?q=(?<newurl>[^"]+?)&amp;[^"]+)">/s',
        $html, $matches);
    foreach ($matches [0] as $key => $match)
    {
        if (isset ($matches ['oldurl'] [$key]) && isset ($matches ['newurl'] [$key]))
        {
            $html = str_replace ($matches ['oldurl'] [$key], $matches ['newurl'] [$key], $html);
        }
    }

    $matches = [];
    preg_match_all ('/<p[^>]*>.*?<a [^>]*href="https?:\/\/(?>www\.)?youtu(?>be\.com|\.be)\/(watch\?v=|watch\?v%3[dD])?(?<code>[a-zA-Z0-9-_]+)"[^>]*>.*?<\/p>/',
        $html, $matches);
    foreach ($matches [0] as $key => $match)
    {
        if (isset ($matches ['code'] [$key]) && !empty ($matches ['code'] [$key]))
        {
            $html = "<iframe width=\"640\" height=\"360\" src=\"https://www.youtube.com/embed/{$matches ['code'] [$key]}\" frameborder=\"0\" allow=\"accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";
        }
    }

    return $html;
}

function imageFix ($html)
{
    if (empty($html)) return '';
    $dom = new DOMDocument();
    // Use encoding to prevent character issues
    @$dom->loadHTML ('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);

    $xPath = new DOMXPath ($dom);
    $images = $xPath->query ('//img'); // Simplified query to catch all images

    foreach ($images as $image)
    {
        $image->setAttribute ("style", "max-width:100%; height:auto;");

        // Clean up parent span styles if they exist (Google adds fixed widths there)
        if ($image->parentNode && $image->parentNode->nodeName == 'span') {
            $parentStyle = $image->parentNode->getAttribute("style");
            $parentStyle = preg_replace ("/(width|height): [0-9.]+px;/", "", $parentStyle);
            $image->parentNode->setAttribute ("style", $parentStyle);
        }
    }
    // Return only the body content to avoid nested <html> tags
    return preg_replace('/^<!DOCTYPE.+?>/', '', str_replace( array('<html>', '</html>', '<body>', '</body>'), array('', '', '', ''), $dom->saveHTML()));
}
function getMenuStructure ($outline, $current, $level = 1)
{
    if ($level > 3)
    {
        return '';
    }

    $menu = "";
    foreach ($outline as $heading1 => $details)
    {
        if (isset ($outline [$heading1] ['heading']))
        {
            $menu .= '<li><a href="' . $outline [$heading1] ['newlink'] . '">' . $outline [$heading1] ['heading'] . "</a></li>\n";
        }
        if (is_array ($outline [$heading1]))
        {
            // At the top level, only expand the sub-menu under the current
            // page's h1 — other h1s get just their top-level link.
            if ($level === 1 && $heading1 !== $current)
            {
                continue;
            }
            $menu .= getMenuStructure ($details, $current, $level + 1);
        }
    }

    if ($menu == '')
    {
        return '';
    }
    $final = "<ul class='menu{$level}'";

    $final .= ">" . trim ($menu) . "</ul>\n";

    return $final;
}

function sanitiseTextForLink ($text)
{
    return strtolower (str_replace ('--', '-', str_replace (' ', '-', preg_replace ('/[^a-zA-Z0-9 ]/', '', $text))));
}

function sideloadImages(&$dom) {
    global $writtenImages, $imageStats;

    if (!is_dir(IMAGE_FOLDER)) {
        mkdir(IMAGE_FOLDER, 0755, true);
    }

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $oldSrc = $img->getAttribute('src');

        // Skip empty or already processed images
        if (empty($oldSrc) || strpos($oldSrc, IMAGE_RELATIVE_PATH) === 0) continue;

        // Hash the URL path only (without query string) so rotating ?key= values
        // don't defeat deduplication. Falls back to full URL if parsing fails.
        $hashInput = parse_url($oldSrc, PHP_URL_PATH);
        if (empty($hashInput)) $hashInput = $oldSrc;
        $filename = md5($hashInput) . '.png';
        $savePath = IMAGE_FOLDER . $filename;

        if (file_exists($savePath)) {
            $imageStats['existing']++;
            $writtenImages[$filename] = true;
        } else {
            // Use a basic stream context to mimic a browser if Google blocks simple file_get_contents
            $options = ["http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"]];
            $context = stream_context_create($options);
            $imgData = @file_get_contents($oldSrc, false, $context);

            if ($imgData) {
                file_put_contents($savePath, $imgData);
                $imageStats['new']++;
                $writtenImages[$filename] = true;
            } else {
                $imageStats['failed']++;
            }
        }

        // Update the DOM to point to the local file
        $img->setAttribute('src', IMAGE_RELATIVE_PATH . $filename);
    }
}
