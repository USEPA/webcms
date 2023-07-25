<?php

/**
 * @file
 */

/**
 *
 */
function addWithoutFilter(\Twig\Environment &$env, $config) {
  $env->addFilter(new \Twig\TwigFilter('without', function ($string) {
    return $string;
  }));
}
