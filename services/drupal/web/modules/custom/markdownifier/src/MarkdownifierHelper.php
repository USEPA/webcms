<?php
namespace Drupal\markdownifier;

use Drupal\Core\Security\TrustedCallbackInterface;
use Markdownify\Converter;

class MarkdownifierHelper implements TrustedCallbackInterface {

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return ['postRender'];
  }

  /**
   * #post_render callback: Renders HTML in Markdown.
   */
  public static function postRender($markup, $element) {
    $converter = new Converter(Converter::LINK_AFTER_CONTENT, 80, false);
    return $converter->parseString($markup);
  }

}
