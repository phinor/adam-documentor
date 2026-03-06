<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

define ("DOCUMENTATION_URL",
    "https://docs.google.com/document/d/e/2PACX-1vTD048j2WkcCJYgH3lxyNybc7vRr3gyxEXbSTocyNxmtj3eozNwLXlNFitLpHKA8ZV7L-W10LockjdP/pub");
define ("TEMPLATE_FOLDER",
    __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'template' . DIRECTORY_SEPARATOR);
define ("OUTPUT_FOLDER", __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'html' . DIRECTORY_SEPARATOR);
define ("SITEMAP_URL", "https://help.adam.co.za/");
define ("IMAGE_FOLDER", OUTPUT_FOLDER . 'images' . DIRECTORY_SEPARATOR);
define ("IMAGE_RELATIVE_PATH", 'images/'); // For the HTML src attribute

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
$style = preg_replace ('/line\-height\s*:\s*[0-9\.]+;/s', '', $style);
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

$menu = getMenuStructure ($outline, "");

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
}

copy (TEMPLATE_FOLDER . 'custom.css', OUTPUT_FOLDER . 'custom.css');
copy (TEMPLATE_FOLDER . 'display.js', OUTPUT_FOLDER . 'display.js');
copy (TEMPLATE_FOLDER . 'logo.png', OUTPUT_FOLDER . 'logo.png');
copy (TEMPLATE_FOLDER . 'favicon.ico', OUTPUT_FOLDER . 'favicon.ico');

file_put_contents (OUTPUT_FOLDER . "sitemap.txt", implode ("\n", $sitemap));
file_put_contents (OUTPUT_FOLDER . "robots.txt", "User-agent: *
Allow: *
Sitemap: " . SITEMAP_URL . "sitemap.txt");

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
    if (!is_dir(IMAGE_FOLDER)) {
        mkdir(IMAGE_FOLDER, 0755, true);
    }

    $images = $dom->getElementsByTagName('img');
    foreach ($images as $img) {
        $oldSrc = $img->getAttribute('src');

        // Skip empty or already processed images
        if (empty($oldSrc) || strpos($oldSrc, IMAGE_RELATIVE_PATH) === 0) continue;

        // Generate a unique filename (MD5 hash of the URL is safest)
        $filename = md5($oldSrc) . '.png';
        $savePath = IMAGE_FOLDER . $filename;

        // Only download if we don't have it locally already
        if (!file_exists($savePath)) {
            // Use a basic stream context to mimic a browser if Google blocks simple file_get_contents
            $options = ["http" => ["header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n"]];
            $context = stream_context_create($options);
            $imgData = @file_get_contents($oldSrc, false, $context);

            if ($imgData) {
                file_put_contents($savePath, $imgData);
            }
        }

        // Update the DOM to point to the local file
        $img->setAttribute('src', IMAGE_RELATIVE_PATH . $filename);
    }
}
