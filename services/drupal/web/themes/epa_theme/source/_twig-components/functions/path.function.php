<?php

/**
 * @file
 */

/**
 *
 */
function addPathFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction('path', function ($string) {
    if ($string === '<front>') {
      return '/';
    }
    else {
      return $string;
    }
  }));
}
