<?php

/**
 * @file
 */

/**
 *
 */
function addAttachLibraryFunction(\Twig\Environment &$env, $config) {
  $env->addFunction(new \Twig\TwigFunction('attach_library', function ($string) {
    return;
  }));
}
