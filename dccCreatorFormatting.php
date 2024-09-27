<?php
// Check if 'rssUrl' parameter is passed in the query string
if (isset($_GET['rssUrl'])) {
    $rssUrl = $_GET['rssUrl'];
} else {
    die("Error: 'rssUrl' parameter is missing.");
}

// Validate that the URL is valid
if (!filter_var($rssUrl, FILTER_VALIDATE_URL)) {
    die("Error: Invalid URL format.");
}

// Fetch the RSS feed content
$rssContent = file_get_contents($rssUrl);

if ($rssContent === false) {
    die("Error fetching the RSS feed.");
}

// Load the RSS content into a SimpleXMLElement object
$xml = new SimpleXMLElement($rssContent);

// Namespace for dc:creator and other DC fields
$namespaces = $xml->getNamespaces(true);

// Iterate through each item in the RSS feed
foreach ($xml->channel->item as $item) {
    // If there are multiple <dc:creator> elements, concatenate them
    $creators = [];
    
    // Get the <dc:creator> elements from the item
    $dcCreators = $item->children($namespaces['dc'])->creator;

    // If there are multiple creators, concatenate them into one field
    foreach ($dcCreators as $creator) {
        $creators[] = (string)$creator;
    }

    // Remove the existing <dc:creator> elements
    unset($item->children($namespaces['dc'])->creator);

    // Insert the <dc:creator> after <pubDate> but before <dc:date>
    $pubDate = $item->pubDate; // Find the <pubDate> element
    $dcDate = $item->children($namespaces['dc'])->date; // Find the <dc:date> element

    // Create the new <dc:creator> element with concatenated authors
    if (!empty($creators)) {
        $creatorString = implode(', ', $creators);

        // Convert the item to a DOMDocument for easier element reordering
        $domItem = dom_import_simplexml($item);
        $domDoc = $domItem->ownerDocument;

        // Create the new <dc:creator> element
        $dcCreatorElement = $domDoc->createElementNS($namespaces['dc'], 'dc:creator', $creatorString);

        // Insert <dc:creator> after <pubDate>
        if ($pubDate && $dcDate) {
            $domPubDate = dom_import_simplexml($pubDate);
            $domPubDate->parentNode->insertBefore($dcCreatorElement, dom_import_simplexml($dcDate));
        }
    }
}

// Convert SimpleXML to DOMDocument for pretty printing
$dom = new DOMDocument('1.0', 'UTF-8');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;

// Load SimpleXML into DOMDocument
$dom->loadXML($xml->asXML());

// Set the content type to RSS
header("Content-Type: application/rss+xml");

// Output the pretty-printed XML
echo $dom->saveXML();
?>
