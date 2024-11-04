<?php

/**
 * @file
 * Add "get_environment" function for Pattern Lab.
 */

/**
 *
 */
function addGetEnvironmentFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction('get_environment', function () {
    // Should return whether we’re in the production environment or not.
    return getenv('WEBCMS_SITE');
  }));
}
