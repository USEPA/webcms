<?php

/**
 * @file
 */

/**
 *
 */
function addPlaceholderFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('placeholder', function ($string) {
    return $string;
  }));
}
