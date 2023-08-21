<?php

/**
 * @file
 */

/**
 *
 */
function addUrlFunction(\Twig\Environment &$env, $config) {
  // https://www.drupal.org/node/2486991
  $env->addFunction(new \Twig\TwigFunction('url', function ($string) {
    return '#';
  }));
}
