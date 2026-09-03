<?php

namespace App\Services\AirBlueService;

use DOMDocument;

trait TransverseXML
{
    /**
     * Parses an XML string and extracts data from it.
     *
     * @param string $xmlString The XML string to be parsed.
     * @return mixed The extracted data.
     */
    public function transverseXML($xmlString)
    {
        $dom = new DOMDocument();
        $dom->loadXML($xmlString);

        return $this->transverseDOMNode($dom->documentElement);
    }

    /**
     * Recursively traverses the XML nodes and extracts data.
     *
     * @param DOMNode $node The XML node to be traversed.
     * @return mixed The extracted data.
     */
    public function transverseDOMNode($node)
    {
        $output = [];

        if ($node->nodeType === XML_TEXT_NODE) {
            return trim($node->textContent);
        }

        if ($node->hasAttributes()) {
            foreach ($node->attributes as $attr) {
                $output[$attr->nodeName] = $attr->nodeValue;
            }
        }

        foreach ($node->childNodes as $child) {
            $childNode = $this->transverseDOMNode($child);

            if (isset($child->tagName)) {
                $tagName = $child->localName;
                if (isset($output[$tagName])) {
                    if (!is_array($output[$tagName]) || !array_key_exists(0, $output[$tagName])) {
                        $output[$tagName] = [$output[$tagName]];
                    }
                    $output[$tagName][] = $childNode;
                } else {
                    $output[$tagName] = $childNode;
                }
            } elseif ($childNode !== '') {
                if (is_array($output)) {
                    $output = $childNode;
                } else {
                    // If it's not an array, convert it to an array and add the childNode
                    $output = [$output, $childNode];
                }
            }
        }
        // Check if there's only one child element and convert it to a single array
        if (is_array($output)) {
            if (count($output) === 1 && isset($output[0])) {
                $output = $output[0];
            }
        }

        return $output;
    }
}
