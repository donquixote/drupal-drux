<?php


/**
 * Implements hook_drush_command().
 */
function drux_drush_command() {
  $items['drux-enable-dependencies'] = array(
    'description' => 'Download and enable dependencies of existing modules.',
    'aliases' => array('dep'),
  );
  $items['drux-find-obsolete'] = array(
    'description' => 'Find modules that are not direct or indirect dependencies of the modules given as arguments',
    'arguments' => array(
      'modules' => 'List of modules to seed the dependencies.',
    ),
    'aliases' => array('obs'),
  );
  return $items;
}


function drush_drux_enable_dependencies() {

  // xautoload should find that..
  $extinfo = new drux_ExtensionInfo();
  $tracker = new drux_DependencyTracker($extinfo);
  $tracker->requireModules($extinfo->enabledKeys());

  while ($tracker->nModulesToDownload()) {
    $continue = $tracker->reportToBeEnabled();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $continue = $tracker->confirmDownload();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $tracker->refresh();
  }

  if ($tracker->nModulesToEnable()) {
    $continue = $tracker->reportToBeEnabled();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $continue = $tracker->confirmEnable();
    if (!$continue) {
      drush_user_abort();
      return FALSE;
    }
    $tracker->refresh();
  }
  else {
    drush_log(dt("All required extensions are already enabled."), 'ok');
  }
}


function drush_drux_find_obsolete() {

  $args = drux_parse_arguments(func_get_args());

  // xautoload should find that..
  $extinfo = new drux_ExtensionInfo();
  $tracker = new drux_DependencyTracker($extinfo);
  $tracker->requireModules($args);
  $obsolete = $tracker->obsoleteModules();
  $rows = $extinfo->modulesTableRows($obsolete);
  drush_print_table($rows, array('Module', 'Human name'));
}


/**
 * Sanitize user provided arguments to several pm commands.
 *
 * Return an array of arguments off a space and/or comma separated values. It also
 * lowercase arguments and optionally convert dashes to underscores.
 */
function drux_parse_arguments($args, $dashes_to_underscores = TRUE, $lowercase = TRUE) {
  $arguments = _convert_csv_to_array($args);
  foreach ($arguments as $key => $argument) {
    $argument = ($dashes_to_underscores) ? strtr($argument, '-', '_') : $argument;
    if ($lowercase) {
      $arguments[$key] = strtolower($argument);
    }
  }
  return $arguments;
}



