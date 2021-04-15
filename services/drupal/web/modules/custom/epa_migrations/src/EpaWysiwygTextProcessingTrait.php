<?php

namespace Drupal\epa_migrations;

use DOMDocument;

/**
 * Helpers to reformat/strip inline HTML.
 */
trait EpaWysiwygTextProcessingTrait {
  /**
   * A string indicating the type of paragraph this text will be wrapped in.
   *
   * @var string
   */
  protected $wrapperContext;

  /**
   * Transform inline html in wysiwyg content.
   *
   * @param string $wysiwyg_content
   *   The content to search and transform inline html.
   * @param string $context
   *   The context the type of paragraph this text will be wrapped in.
   * @return string
   *   The original wysiwyg_content with transformed inline html.
   */
  public function processText($wysiwyg_content, $wrapper_context = NULL) {
    $this->wrapperContext = $wrapper_context;

    $pattern = '/';
    $pattern .= 'class=".*?(box).*?"|';
    $pattern .= 'class=".*?(pagetop).*?"|';
    $pattern .= 'class=".*?(exit-disclaimer).*?"|';
    $pattern .= 'class=".*?(tabs).*?"|';
    $pattern .= 'class=".*?(accordion).*?"|';
    $pattern .= 'class=".*?(termlookup-tooltip).*?"|';
    $pattern .= 'class=".*?(row).*?"|';
    $pattern .= 'class=".*?(pipeline).*?"|';
    $pattern .= 'class=".*?(pullquote).*?"|';
    $pattern .= 'class=".*?(nostyle).*?"|';
    $pattern .= 'class=".*?(tablesorter).*?"|';
    $pattern .= 'class=".*?(highlighted).*?"|';
    $pattern .= 'class=".*?(govdelivery-form).*?"|';
    $pattern .= 'class=".*?(epa-archive-link).*?"|';
    $pattern .= 'href=".*?(exitepa).*?"|';
    $pattern .= '(need Adobe Reader to view)|(need a PDF reader to view)';
    $pattern .= '/';

    $matches = [];

    if (preg_match_all($pattern, $wysiwyg_content, $matches) > 0) {
      // Add a temp wrapper around the wysiwyg content.
      $wysiwyg_content = '<?xml encoding="UTF-8"><tempwrapper>' . $wysiwyg_content . '</tempwrapper>';

      // Load the content as a DOMDocument for more powerful transformation.
      $doc = new \DomDocument();
      libxml_use_internal_errors(TRUE);
      $doc->loadHtml($wysiwyg_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOENT);
      libxml_clear_errors();

      // Run the document through the transformation methods depending on the
      // matches identified.
      foreach ($matches as $key => $match_strings) {

        // Skip the first value, which contains the full pattern matches.
        if ($key > 0) {
          // Get unique values with array_unique, then remove any empty strings
          // with array_filter, and finally get the remaining match text.
          $non_empty_strings = array_filter(array_unique($match_strings));
          $match = array_pop($non_empty_strings);

          switch ($match) {
            case 'box':
              $doc = $this->transformRelatedInfoBox($doc);
              break;

            case 'pagetop':
              $doc = $this->stripPageTopLinks($doc);
              break;

            case 'exit-disclaimer':
              $doc = $this->stripExitEpaLinks($doc);
              break;

            case 'exitepa':
              $doc = $this->stripExitEpaLinks($doc);
              break;

            case 'tabs':
              $doc = $this->stripTabClasses($doc);
              break;

            case 'accordion':
              $doc = $this->transformAccordion($doc);
              break;

            case 'need Adobe Reader to view':
              $doc = $this->stripPdfDisclaimers($doc);
              break;

            case 'need a PDF reader to view':
              $doc = $this->stripPdfDisclaimers($doc);
              break;

            case 'termlookup-tooltip':
              $doc = $this->transformDefinition($doc);
              break;

            case 'row':
              $doc = $this->transformColumns($doc);
              break;

            case 'pipeline':
              $doc = $this->transformPipelineUls($doc);
              break;

            case 'pullquote':
              $doc = $this->transformPullquote($doc);
              break;

            case 'nostyle':
              $xpath ='//table[contains(concat(" ", @class, " "), " nostyle ")]';
              $old_class = 'nostyle';
              $new_classes = 'usa-table--unstyled';
              $doc = $this->simpleClassReplacement($doc, $xpath, $old_class, $new_classes);
              break;
            case 'tablesorter':
              $xpath = '//table[contains(concat(" ", @class, " "), " tablesorter ")]';
              $old_class = 'tablesorter';
              $new_classes = 'usa-table usa-table--sortable';
              $doc = $this->simpleClassReplacement($doc, $xpath, $old_class, $new_classes);
              break;
            case 'highlighted':
              $xpath = '//*[(self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6) and contains(concat(" ", @class, " "), " highlighted ")]';
              $old_class = 'highlighted';
              $new_classes = 'highlight';
              $doc = $this->simpleClassReplacement($doc, $xpath, $old_class, $new_classes);
              break;

            case 'govdelivery-form':
              $doc = $this->transformGovDeliveryForm($doc);
              break;

            case 'epa-archive-link':
              $doc = $this->transformArchiveLink($doc);
              break;
          }
        }
      }

      // Transform the document back to HTML.
      $wysiwyg_content = $doc->saveHtml();

      // Remove the temp wrapper and encoding from the output.
      return str_replace([
        '<?xml encoding="UTF-8">',
        '<tempwrapper>',
        '</tempwrapper>',
      ], '', $wysiwyg_content);
    }

    return $wysiwyg_content;
  }

  /**
   * Safely make replacements within a DOM classname
   *
   * @param string | string[] $searches
   *   Classnames needing replacement
   *
   * @param string | string[] $replacements
   *   Replacement classnames
   *
   * @param string $classname
   *   A DOM classname
   *
   * @return string
   *   Replacement classname with replacements made
   */
  public static function classReplace($searches, $replacements, $classname) {
    if (!is_array($searches)) {
      $searches = [$searches];
    }
    if (!is_array($replacements)) {
      $replacements = [$replacements];
    }

    if (count($searches) !== count($replacements)) {
      throw new \InvalidArgumentException('$from and $to not the same length');
    }

    // Work with maps so we get unique elements for free
    $create_map = function ($str) {
      $map = [];
      foreach (preg_split('~\s+~', $str, -1, PREG_SPLIT_NO_EMPTY) as $name) {
        $map[$name] = true;
      }
      return $map;
    };

    $class_map = $create_map($classname);

    foreach ($searches as $i => $search) {
      $search_map = $create_map($search);
      $diff = array_diff_assoc($class_map, $search_map);

      // If all in search were found, we'll see size of $class_map
      // change by the size of $search_map.
      if (count($class_map) - count($diff) === count($search_map)) {
        // We just need to re-add the replacement names
        $class_map = array_merge(
          $diff,
          $create_map($replacements[$i])
        );
      }
    }

    return implode(' ', array_keys($class_map));
  }

  /**
   * Transform Related Info Box.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed info boxes.
   */
  private function transformRelatedInfoBox(DOMDocument $doc) {

    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    $related_info_boxes = $xpath->query('//div[contains(@class, "box")]');

    if ($related_info_boxes) {
      foreach ($related_info_boxes as $key => $rib_wrapper) {
        // Replace div classes on box wrapper.
        // Note: done with classReplace, so even "special foo box" will be properly
        // recognized as matching "box special".
        $searches = [
          'box multi related-info',
          'box multi highlight',
          'box multi news',
          'box multi alert',
          'box simple',
          'box special',
          'box multi rss',
          'box multi blog',
          'box multi',
          'right',
          'left',
        ];

        // Note the remaining replacements for left and right are added below.
        $replacements = [
          'box box--related-info',
          'box box--highlight',
          'box box--news',
          'box box--alert',
          'box box--multipurpose',
          'box box--special',
          'box box--rss',
          'box box--blog',
          'box box--multipurpose',
        ];

        if ($this->wrapperContext == 'box') {
          // If this box is stored within a box paragraph, we want to strip
          // the left and right alignment classes from the box.
          $replacements[] = 'test-replacement-left';
          $replacements[] = 'test-replacement-right';
        }
        else {
          // This box is not stored within a box paragraph, let's replace the
          // left and right alignment classes on the box.
          $replacements[] = 'u-align-right';
          $replacements[] = 'u-align-left';
        }

        $wrapper_classes = $rib_wrapper->attributes->getNamedItem('class')->value;
        $wrapper_classes = self::classReplace($searches, $replacements, $wrapper_classes);
        $rib_wrapper->setAttribute('class', $wrapper_classes);

        // Change child H2 to div and replace classes.
        $heading = $xpath->query('*[(self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6) and contains(@class, "pane-title")]', $rib_wrapper)[0];
        if ($heading) {
          $box_title = $doc->createElement('div', $heading->nodeValue);
          $box_title_classes = $heading->attributes->getNamedItem('class')->value;
          $box_title_classes = self::classReplace('pane-title', 'box__title', $box_title_classes);
          $box_title->setAttribute('class', $box_title_classes);
          $heading->parentNode->replaceChild($box_title, $heading);
        }

        // Replace div class on pane content.
        $box_content = $xpath->query('div[contains(@class, "pane-content")]', $rib_wrapper)[0];
        if ($box_content) {
          $box_content_classes = $box_content->attributes->getNamedItem('class')->value;
          $box_content_classes = self::classReplace('pane-content', 'box__content', $box_content_classes);
          $box_content->setAttribute('class', $box_content_classes);
        }

        // Replace the original element with the modified element in the doc.
        $rib_wrapper->parentNode->replaceChild($rib_wrapper, $related_info_boxes[$key]);
      }
    }

    return $doc;

  }

  /**
   * Strip Top of Page links.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped links.
   */
  private function stripPageTopLinks(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    $page_top_links = $xpath->query('//*[contains(concat(" ", @class, " "), " pagetop ")]');

    if ($page_top_links) {
      foreach ($page_top_links as $link) {
        // Delete the element and any parent elements that are now empty.
        $element_to_remove = $this->determineElementToRemove($link);
        if ($element_to_remove->parentNode) {
          $element_to_remove->parentNode->removeChild($element_to_remove);
        }
      }
    }

    return $doc;
  }

  /**
   * Strip Exit EPA link disclaimers.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped links.
   */
  private function stripExitEpaLinks(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Links that use the exit-disclaimer class.
    $exit_epa_links = $xpath->query('//*[contains(concat(" ", @class, " "), " exit-disclaimer ") or contains(@href, "exit-epa") or contains(@href, "exitepa")]');

    if ($exit_epa_links) {
      foreach ($exit_epa_links as $link) {
        // Delete the element and any parent elements that are now empty.
        $element_to_remove = $this->determineElementToRemove($link);
        if ($element_to_remove->parentNode) {
          $element_to_remove->parentNode->removeChild($element_to_remove);
        }
      }
    }

    return $doc;
  }

  /**
   * Strip PDF disclaimers.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped disclaimers.
   */
  private function stripPdfDisclaimers(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Elements that include the PDF disclaimer.
    $pdf_disclaimer_elements = $xpath->query('//*[contains(text(), "need Adobe Reader to view") or contains(text(), "need a PDF reader to view")]');

    if ($pdf_disclaimer_elements) {
      foreach ($pdf_disclaimer_elements as $element) {
        // Delete the element and any parent elements that are now empty.
        $element_to_remove = $this->determineElementToRemove($element);
        if ($element_to_remove->parentNode) {
          $element_to_remove->parentNode->removeChild($element_to_remove);
        }
      }
    }

    return $doc;

  }

  /**
   * Strip Tab classes.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped tab classes.
   */
  private function stripTabClasses(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Tab elements.
    $tabs_parent_element = $xpath->query('//div[@id="tabs"]');
    if (count($tabs_parent_element) == 0) {
      $tabs_parent_element = $xpath->query('//ul[contains(concat(" ", @class, " "), " tabs ")]');
    }

    if (count($tabs_parent_element) > 0) {
      foreach ($tabs_parent_element as $parent_element) {
        if ($parent_element->tagName == 'div') {
          $parent_element->removeAttribute('id');
          $uls = $xpath->query('ul[contains(concat(" ", @class, " "), " tabs ") or @id="tabsnav"]', $parent_element);
          foreach ($uls as $ul) {
            $ul->setAttribute('class', self::classReplace('tabs', '', $ul->attributes->getNamedItem('class')->value));
            if ($ul->attributes->getNamedItem('id')->value == 'tabsnav') {
              $ul->removeAttribute('id');
            }
          }
        }
        else {
          $parent_element->setAttribute('class', self::classReplace('tabs', '', $parent_element->attributes->getNamedItem('class')->value));
          if ($parent_element->attributes->getNamedItem('id')->value == 'tabsnav') {
            $parent_element->removeAttribute('id');
          }
        }

        $lis = $xpath->query('li[contains(concat(" ", @class, " "), " active ")]', $parent_element);
        foreach ($lis as $li) {
          $li->setAttribute('class', self::classReplace('active', '', $li->attributes->getNamedItem('class')->value));
        }

        $links = $xpath->query('a[contains(concat(" ", @class, " "), " menu-internal ")]', $parent_element);
        foreach ($links as $link) {
          $link->setAttribute('class', self::classReplace('menu-internal', '', $link->attributes->getNamedItem('class')->value));
        }

      }
    }

    return $doc;

  }

  /**
   * Transform accordions to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with stripped accordion classes.
   */
  private function transformAccordion(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Accordion elements.
    $accordion_elements = $xpath->query('//ul[contains(concat(" ", @class, " "), " accordion ")]');

    if ($accordion_elements) {
      foreach ($accordion_elements as $ul) {
        $ul->setAttribute('class', self::classReplace('accordion', '', $ul->attributes->getNamedItem('class')->value));
        $lis = $xpath->query('li', $ul);

        foreach ($lis as $li) {
          $as = $xpath->query('a[contains(concat(" ", @class, " "), " accordion-title ")]', $li);
          foreach ($as as $a) {
            // Change a to strong.
            $strong = $doc->createElement('strong', $a->nodeValue);
            $a->parentNode->replaceChild($strong, $a);
          }

          $divs = $xpath->query('div[contains(concat(" ", @class, " "), " accordion-pane ")]', $li);
          foreach ($divs as $div) {
            // Remove old classes, id, and any 'display: none' styles.
            $div->setAttribute('class', self::classReplace(['accordion-pane', 'is-closed'], ['', ''], $div->attributes->getNamedItem('class')->value));
            $div->removeAttribute('id');
            $div->setAttribute('style', str_replace('style="display: none;"', '', $div->attributes->getNamedItem('style')->array_count_values));
          }
        }
      }
    }

    return $doc;

  }

  /**
   * Transform definitions to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed definitions.
   */
  private function transformDefinition(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Definition elements.
    $elements = $xpath->query('//a[contains(concat(" ", @class, " "), " termlookup-tooltip ")]');

    if ($elements) {
      foreach ($elements as $element) {
        // Extract term and definition.
        $term = $element->firstChild->nodeValue;
        $definition = $element->lastChild->lastChild->nodeValue;

        // Ensure the last child is a DOMElement and extract the name attribute.
        if ($element->lastChild->nodeType == 1) {
          $definition_name_attr = $element->lastChild->getAttribute('name');
        }

        // Build the new element.
        $button_element = $doc->createElement('button', $term);
        $button_element->setAttribute('class', 'definition__trigger js-definition__trigger');

        $dfn_element = $doc->createElement('dfn', $term);
        $dfn_element->setAttribute('class', 'definition__term');

        $span_element = $doc->createElement('span');
        $span_element->setAttribute('class', 'definition__tooltip js-definition__tooltip');
        $span_element->setAttribute('role', 'tooltip');
        if (!empty($definition_name_attr)) {
          $span_element->setAttribute('name', $definition_name_attr);
        }

        $span_element->appendChild($dfn_element);

        $definition_text_node = $doc->createTextNode($definition);
        $span_element->appendChild($definition_text_node);

        $new_element = $doc->createElement('span');
        $new_element->setAttribute('class', 'definition js-definition');
        $new_element->appendChild($button_element);
        $new_element->appendChild($span_element);

        $element->parentNode->replaceChild($new_element, $element);
      }
    }

    return $doc;

  }

  /**
   * Replace a class on an element.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with updated classes.
   */
  private function simpleClassReplacement(DOMDocument $doc, $xpath, $old_class, $new_classes): DOMDocument {
    // Create a DOM XPath object for searching the document.
    $xpath_doc = new \DOMXPath($doc);

    $elements = $xpath_doc->query($xpath);

    if ($elements) {
      foreach ($elements as $element) {
        $element->setAttribute('class', self::classReplace($old_class, $new_classes, $element->attributes->getNamedItem('class')->value));
      }
    }

    return $doc;

  }

  /**
   * Transform columns to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed columns.
   */
  private function transformColumns(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Row elements.
    $elements = $xpath->query('//div[contains(concat(" ", @class, " "), " row ")]');

    if ($elements) {
      foreach ($elements as $element) {
        // Extract number of cols from the class name.
        $classes = $element->attributes->getNamedItem('class')->value;
        $num_cols = substr($classes, strpos($classes, 'cols-') + 5, 1);

        if ($num_cols) {
          // Update class.
          $element->setAttribute('class', "l-grid l-grid--{$num_cols}-col");

          // Remove col class from children.
          $children = $xpath->query('div[contains(concat(" ", @class, " "), " col ")]', $element);
          foreach ($children as $child) {
            $child->setAttribute('class', self::classReplace('col', '', $child->attributes->getNamedItem('class')->value));
          }
        }

      }
    }

    return $doc;

  }

  /**
   * Transform pipeline uls to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed pipeline uls.
   */
  private function transformPipelineUls(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Row elements.
    $elements = $xpath->query('//ul[contains(concat(" ", @class, " "), " pipeline ")]');

    if ($elements) {
      foreach ($elements as $element) {

        // Replace class.  Sometimes this got the additional "menu" class applied to it, sometimes not.
        // Running both these commands ensures we do the replacement regardless of whether "menu" exists, and
        // ensures the "menu" class is removed from this, but only when paired with the "pipeline" class
        // (I don't think we want to universally remove the menu class everywhere it's used).
        $element->setAttribute('class', self::classReplace('menu pipeline', 'list list--pipeline', $element->attributes->getNamedItem('class')->value));
        $element->setAttribute('class', self::classReplace('pipeline', 'list list--pipeline', $element->attributes->getNamedItem('class')->value));

        // Remove menu-item class from children.
        $children = $xpath->query('li[contains(concat(" ", @class, " "), " menu-item ")]', $element);
        foreach ($children as $child) {
          $child->setAttribute('class', self::classReplace('menu-item', '', $child->attributes->getNamedItem('class')->value));
        }
      }
    }

    return $doc;

  }

  /**
   * Transform govdelivery forms to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed govdelivery forms.
   */
  private function transformGovDeliveryForm(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Govdelivery form elements.
    $elements = $xpath->query('//form[contains(concat(" ", @class, " "), " govdelivery-form ")]');

    if ($elements) {
      foreach ($elements as $element) {
        // Replace the class on the form element.
        $element->setAttribute('class', self::classReplace('govdelivery-form', 'govdelivery', $element->attributes->getNamedItem('class')->value));

        // Replace classes.
        $fieldset = $xpath->query('fieldset[contains(concat(" ", @class, " "), " govdelivery-fieldset ")]', $element)[0];
        if ($fieldset) {
          $fieldset->setAttribute('class', self::classReplace('govdelivery-fieldset', 'govdelivery__fieldset', $fieldset->attributes->getNamedItem('class')->value));
        }

        $legend = $xpath->query('legend[contains(concat(" ", @class, " "), " govdelivery-legend ")]', $element)[0];
        if ($legend) {
          $legend->setAttribute('class', self::classReplace('govdelivery-legend', 'govdelivery__legend h3', $legend->attributes->getNamedItem('class')->value));
        }

        $label = $xpath->query('label[contains(concat(" ", @class, " "), " element-invisible ")]', $element)[0];
        if ($label) {
          $label->setAttribute('class', self::classReplace('element-invisible', 'form-item__label u-visually-hidden', $label->attributes->getNamedItem('class')->value));
        }

        $input = $xpath->query('input[contains(concat(" ", @class, " "), " govdelivery-text ")]', $element)[0];
        if ($input) {
          $input->setAttribute('class', self::classReplace('govdelivery-text form-text', 'form-item__email', $input->attributes->getNamedItem('class')->value));
        }

        $button = $xpath->query('button[contains(concat(" ", @class, " "), " govdelivery-submit ")]', $element)[0];
        if ($button) {
          $button->setAttribute('class', self::classReplace('govdelivery-submit', 'button', $button->attributes->getNamedItem('class')->value));
        }

        // Wrap label and input in a new div.
        if ($label && $input) {
          $div = $doc->createElement('div');
          $div->setAttribute('class', 'form-item form-item--email is-inline');
          $div->appendChild($label);
          $div->appendChild($input);
        }

        // Insert the div into the fieldset.
        if ($fieldset && $div && $button) {
          $fieldset->insertBefore($div, $button);
        }
      }
    }

    return $doc;

  }

  /**
   * Transform pullquote to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed pullqoute.
   */
  private function transformPullquote(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Pullquote elements.
    $elements = $xpath->query('//*[contains(concat(" ", @class, " "), " pullquote ")]');

    if ($elements) {
      foreach ($elements as $element) {
        // Extract quote.
        $quote = $element->firstChild->nodeValue;

        // Extract the citation.
        $citation_element = $xpath->query('span[contains(concat(" ", @class, " " ), " author ")]');
        if ($citation_element) {
          $citation = str_replace('—', '', $citation_element->firstChild->nodeValue);
        }

        // Build the new element.
        if ($citation) {
          $cite_element = $doc->createElement('cite', $citation);
          $cite_element->setAttribute('class', 'pull-quote__cite');
        }

        $p_element = $doc->createElement('p', $quote);

        $new_element = $doc->createElement('blockquote');
        $new_element->setAttribute('class', 'pull-quote');
        $new_element->appendChild($p_element);

        if ($citation) {
          $new_element->appendChild($cite_element);
        }

        $element->parentNode->replaceChild($new_element, $element);
      }
    }

    return $doc;

  }

  /**
   * Transform archive links to D8 markup.
   *
   * @param \DOMDocument $doc
   *   The document to search and replace.
   *
   * @return \DOMDocument
   *   The document with transformed archive links.
   */
  private function transformArchiveLink(DOMDocument $doc) {
    // Create a DOM XPath object for searching the document.
    $xpath = new \DOMXPath($doc);

    // Archive link elements.
    $elements = $xpath->query('//a[contains(concat(" ", @class, " "), " epa-archive-link ")]');

    if ($elements) {
      foreach ($elements as $element) {
        // Extract text.
        $text = $element->firstChild->nodeValue;

        // Build the span element.
        $span_element = $doc->createElement('span', $text);
        $span_element->setAttribute('class', 'usa-tag');

        // Update the a class and remove title attribute.
        $element->setAttribute('class', self::classReplace('epa-archive-link', 'tag-link', $element->attributes->getNamedItem('class')->value));
        $element->removeAttribute('title');

        $element->replaceChild($span_element, $element->firstChild);
      }
    }

    return $doc;

  }

  /**
   * Remove an element's white-space only child nodes.
   *
   * @param \DOMElement|\DOMDocument $element
   *   The element to have its child elements cleaned.
   *
   * @return \DOMElement
   *   The element with cleaned children.
   */
  private function removeEmptyTextNodes($element) {
    $num_children = count($element->childNodes);
    if ($num_children > 1) {
      $empty_text_nodes = [];
      foreach ($element->childNodes as $node) {
        if ($node->nodeType == 3 && trim($node->nodeValue) == '') {
          $empty_text_nodes[] = $node;
        }
      }

      if ($empty_text_nodes) {
        foreach ($empty_text_nodes as $node) {
          $node->parentNode->removeChild($node);
        }
      }
    }
    return $element;
  }

  /**
   * Traverse ancestor tree of an element to determine if it is an only child.
   *
   * @param \DOMElement|\DOMDocument $element
   *   The element to have its ancestors checked.
   *
   * @return \DOMElement
   *   The top-most ancestor that has no children other than the element.
   */
  private function determineElementToRemove($element) {

    // Initially the element to remove is the original one.
    $element_to_remove = $element;

    // Find any ancestor elements that only contain this element.
    // Start by seeing if the immediate parent has any other children.
    $cleaned_parent = $this->removeEmptyTextNodes($element->parentNode);
    if (count($cleaned_parent->childNodes) == 1 && $cleaned_parent->childNodes[0]->isSameNode($element)) {
      $only_child = TRUE;
      $element_to_remove = $cleaned_parent;
    }
    else {
      $only_child = FALSE;
    }

    // If the original element is an only child, traverse the ancestors.
    while ($only_child && $element->name !== 'tempwrapper') {
      $cleaned_parent = $this->removeEmptyTextNodes($element_to_remove->parentNode);

      if (count($cleaned_parent->childNodes) == 1 && $cleaned_parent->childNodes[0]->isSameNode($element_to_remove)) {
        $only_child = TRUE;
        $element_to_remove = $element_to_remove->parentNode;
      }
      else {
        $only_child = FALSE;
      }
    }

    return $element_to_remove;
  }

}
