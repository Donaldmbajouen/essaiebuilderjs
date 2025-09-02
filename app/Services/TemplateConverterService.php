<?php

namespace App\Services;

use DOMDocument;
use DOMXPath;

class TemplateConverterService
{
    private $elementMappings = [
        'h1' => 'TextElement',
        'h2' => 'TextElement',
        'h3' => 'TextElement',
        'h4' => 'TextElement',
        'h5' => 'TextElement',
        'h6' => 'TextElement',
        'p' => 'TextElement',
        'span' => 'TextElement',
        'div' => 'BlockElement',
        'section' => 'BlockElement',
        'article' => 'BlockElement',
        'header' => 'BlockElement',
        'footer' => 'BlockElement',
        'main' => 'BlockElement',
        'aside' => 'BlockElement',
        'nav' => 'BlockElement',
        'a' => 'LinkElement',
        'img' => 'ImageElement',
        'ul' => 'ListElement',
        'ol' => 'ListElement',
        'li' => 'TextElement',
        'button' => 'ButtonElement',
        'input[type="button"]' => 'ButtonElement',
        'input[type="submit"]' => 'ButtonElement',
    ];

    public function convertHtmlToBuilderJS(string $html): string
    {
        // Create DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Load HTML with error suppression for malformed HTML
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Add builderjs-layout class to body
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            $class = $body->getAttribute('class');
            if (strpos($class, 'builderjs-layout') === false) {
                $body->setAttribute('class', trim($class . ' builderjs-layout'));
            }
        }

        // Preserve and optimize styles
        $this->preserveStyles($dom);

        // Find or create main container
        $this->ensurePageElement($dom);

        // Convert elements
        $this->convertElements($dom);

        // Return converted HTML
        return $dom->saveHTML();
    }

    private function ensurePageElement(DOMDocument $dom): void
    {
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) return;

        // Check if PageElement already exists
        $xpath = new DOMXPath($dom);
        $pageElements = $xpath->query('//*[@builder-element="PageElement"]');

        if ($pageElements->length === 0) {
            // Wrap body content in PageElement
            $pageElement = $dom->createElement('div');
            $pageElement->setAttribute('builder-element', 'PageElement');
            $pageElement->setAttribute('style', 'padding: 20px;');

            // Move all body children to PageElement
            $children = [];
            foreach ($body->childNodes as $child) {
                if ($child->nodeType === XML_ELEMENT_NODE) {
                    $children[] = $child;
                }
            }

            foreach ($children as $child) {
                $body->removeChild($child);
                $pageElement->appendChild($child);
            }

            $body->appendChild($pageElement);
        }
    }

    private function convertElements(DOMDocument $dom): void
    {
        $xpath = new DOMXPath($dom);

        foreach ($this->elementMappings as $selector => $builderElement) {
            $elements = $this->getElementsBySelector($xpath, $selector);

            foreach ($elements as $element) {
                /** @var \DOMElement $element */
                // Skip if already has builder-element attribute
                if ($element->hasAttribute('builder-element')) {
                    continue;
                }

                $element->setAttribute('builder-element', $builderElement);
            }
        }

        // Handle special cases
        $this->handleSpecialCases($xpath);
    }

    private function getElementsBySelector(DOMXPath $xpath, string $selector): \DOMNodeList
    {
        // Convert CSS selector to XPath
        $xpathQuery = $this->cssToXPath($selector);
        return $xpath->query($xpathQuery);
    }

    private function cssToXPath(string $selector): string
    {
        // Simple CSS to XPath conversion
        $selector = trim($selector);

        // Handle attribute selectors
        if (strpos($selector, '[') !== false) {
            $selector = preg_replace('/\[([^=]+)="([^"]+)"\]/', '[@$1="$2"]', $selector);
        }

        // Handle simple tag selectors
        if (preg_match('/^[a-zA-Z]+$/', $selector)) {
            return '//' . $selector;
        }

        return '//' . $selector;
    }

    private function handleSpecialCases(DOMXPath $xpath): void
    {
        // Convert buttons styled as links
        $linkButtons = $xpath->query('//a[contains(@class, "btn") or contains(@class, "button")]');
        foreach ($linkButtons as $button) {
            /** @var \DOMElement $button */
            $button->setAttribute('builder-element', 'ButtonElement');
        }

        // Convert divs with text content to TextElement if they're small
        $textDivs = $xpath->query('//div[string-length(normalize-space(text())) > 0 and string-length(normalize-space(text())) < 200 and count(*) = 0]');
        foreach ($textDivs as $div) {
            /** @var \DOMElement $div */
            if (!$div->hasAttribute('builder-element')) {
                $div->setAttribute('builder-element', 'TextElement');
            }
        }
    }

    public function validateTemplate(string $html): array
    {
        $errors = [];

        // Check if it's valid HTML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        if (!$dom->loadHTML($html)) {
            $errors[] = 'HTML invalide détecté';
        }
        libxml_clear_errors();

        // Check file size (limit to 1MB)
        if (strlen($html) > 1024 * 1024) {
            $errors[] = 'Le template est trop volumineux (max 1MB)';
        }

        // // Check for dangerous content
        // if (preg_match('/<script[^>]*>.*?<\/script>/is', $html)) {
        //     $errors[] = 'Les scripts JavaScript ne sont pas autorisés';
        // }

        return $errors;
    }

    private function preserveStyles(DOMDocument $dom): void
    {
        // Les styles sont préservés de plusieurs façons :

        // 1. Styles inline (attribut style) - PRÉSERVÉS automatiquement
        // BuilderJS respecte tous les styles inline existants

        // 2. Classes CSS - PRÉSERVÉES automatiquement
        // Toutes les classes existantes sont maintenues

        // 3. Styles dans <head> - PRÉSERVÉS automatiquement
        // Les balises <style> et <link> dans le <head> sont conservées

        // 4. Optimisation : Ajouter des classes utiles pour BuilderJS
        $xpath = new DOMXPath($dom);

        // Marquer les éléments de contenu pour faciliter l'édition
        $contentElements = $xpath->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6');
        foreach ($contentElements as $element) {
            /** @var \DOMElement $element */
            $currentClass = $element->getAttribute('class');
            if (strpos($currentClass, 'builder-content') === false) {
                $element->setAttribute('class', trim($currentClass . ' builder-content'));
            }
        }
    }
}
