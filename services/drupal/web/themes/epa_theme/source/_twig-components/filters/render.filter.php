<?php

/**
 * @file
 */

/**
 *
 */
function addRenderFilter(\Twig\Environment &$env, $config) {
  // Drupal Render filter.
  $env->addFilter(new \Twig\TwigFilter('render', function ($string) {
    return $string;
  }));
}
